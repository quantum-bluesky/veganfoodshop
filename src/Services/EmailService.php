<?php
declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * Dịch Vụ Gửi Email Thông Báo - Bếp Chay An Yên
 * Thuần PHP (Zero External Dependencies), hỗ trợ SMTP chuẩn (TLS/SSL) hoặc PHP mail()
 */
class EmailService
{
    public function __construct(
        private ?ConfigService $configService = null
    ) {
        $this->configService = $configService ?? new ConfigService();
    }

    /**
     * Gửi email thông báo có đơn hàng mới cho chủ bếp
     */
    public function sendNewOrderEmail(array $order, ?string $recipientEmail = null): bool
    {
        $to = $recipientEmail ?: $this->configService->get('notification_email_to', '');
        if (empty($to)) {
            // Nếu chưa cấu hình email nhận, ghi vào log và bỏ qua
            $this->logMail("Bỏ qua gửi email do chưa cấu hình notification_email_to. Đơn: " . ($order['order_code'] ?? ''));
            return true;
        }

        $orderCode = (string)($order['order_code'] ?? 'UNKNOWN');
        $subject = sprintf('[%s] Có đơn hàng mới #%s', $this->configService->get('shop_name', 'Bếp Chay An Yên'), $orderCode);

        $htmlBody = $this->buildNewOrderHtml($order);
        $textBody = $this->buildNewOrderText($order);

        return $this->send($to, $subject, $htmlBody, $textBody);
    }

    /**
     * Gửi email qua SMTP socket hoặc PHP mail() fallback
     */
    public function send(string $to, string $subject, string $htmlContent, string $textContent): bool
    {
        $smtpHost = trim($this->configService->get('smtp_host', ''));
        $mockMode = $this->configService->get('smtp_mock_mode', '0') === '1';

        // Nếu bật chế độ mock hoặc chưa cấu hình SMTP Host -> Log nội dung email ra file
        if ($mockMode || empty($smtpHost)) {
            $this->logMail(sprintf("MOCK SEND to <%s> | Tiêu đề: %s\n%s\n---", $to, $subject, $textContent));
            return true;
        }

        $smtpPort = (int)$this->configService->get('smtp_port', '587');
        $smtpUser = trim($this->configService->get('smtp_username', ''));
        $smtpPass = trim($this->configService->get('smtp_password', ''));
        $smtpEncryption = strtolower(trim($this->configService->get('smtp_encryption', 'tls'))); // tls, ssl, none
        $fromEmail = trim($this->configService->get('smtp_from_email', $smtpUser ?: 'bepchay@anyen.local'));
        $fromName = trim($this->configService->get('smtp_from_name', 'Bếp Chay An Yên'));

        try {
            return $this->sendViaSmtpSocket(
                $smtpHost,
                $smtpPort,
                $smtpEncryption,
                $smtpUser,
                $smtpPass,
                $fromEmail,
                $fromName,
                $to,
                $subject,
                $htmlContent,
                $textContent
            );
        } catch (Throwable $e) {
            $this->logMail("Lỗi gửi SMTP: " . $e->getMessage() . ". Thử gửi bằng mail() native fallback...");
            // Thử fallback sang hàm mail() mặc định của PHP
            return $this->sendViaNativeMail($fromEmail, $fromName, $to, $subject, $htmlContent);
        }
    }

    /**
     * Gửi email trực tiếp qua SMTP Stream Socket (RFC 5321)
     */
    private function sendViaSmtpSocket(
        string $host,
        int $port,
        string $encryption,
        string $username,
        string $password,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        $timeout = 15;
        $remoteSocket = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);

        $socket = @stream_socket_client($remoteSocket, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new \RuntimeException(sprintf('Không thể kết nối đến SMTP Server %s:%d (%s)', $host, $port, $errstr));
        }

        stream_set_timeout($socket, $timeout);

        $readResponse = function () use ($socket): string {
            $response = '';
            while ($line = fgets($socket, 512)) {
                $response .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $response;
        };

        $sendCommand = function (string $cmd, array $expectedCodes) use ($socket, $readResponse): string {
            fwrite($socket, $cmd . "\r\n");
            $res = $readResponse();
            $code = (int)substr($res, 0, 3);
            if (!in_array($code, $expectedCodes, true)) {
                throw new \RuntimeException(sprintf('Lỗi SMTP lệnh "%s": %s', $cmd, trim($res)));
            }
            return $res;
        };

        // Đọc lời chào ban đầu
        $greeting = $readResponse();
        if ((int)substr($greeting, 0, 3) !== 220) {
            fclose($socket);
            throw new \RuntimeException('SMTP Server không phản hồi 220: ' . trim($greeting));
        }

        // Gửi EHLO
        $clientName = gethostname() ?: 'localhost';
        $sendCommand('EHLO ' . $clientName, [250]);

        // Kích hoạt STARTTLS nếu cấu hình tls
        if ($encryption === 'tls') {
            $sendCommand('STARTTLS', [220]);
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                fclose($socket);
                throw new \RuntimeException('Bắt tay TLS thất bại với máy chủ SMTP.');
            }
            $sendCommand('EHLO ' . $clientName, [250]);
        }

        // Xác thực người dùng (nếu có thông tin)
        if (!empty($username) && !empty($password)) {
            $sendCommand('AUTH LOGIN', [334]);
            $sendCommand(base64_encode($username), [334]);
            $sendCommand(base64_encode($password), [235]);
        }

        // Khai báo người gửi & người nhận
        $sendCommand('MAIL FROM:<' . $fromEmail . '>', [250]);
        $sendCommand('RCPT TO:<' . $toEmail . '>', [250, 251]);

        // Bắt đầu gửi dữ liệu
        $sendCommand('DATA', [354]);

        $boundary = '=_bep_an_yen_' . md5((string)microtime(true));

        $headers = [];
        $headers[] = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>';
        $headers[] = 'To: <' . $toEmail . '>';
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body = [];
        $body[] = '--' . $boundary;
        $body[] = 'Content-Type: text/plain; charset=UTF-8';
        $body[] = 'Content-Transfer-Encoding: base64';
        $body[] = '';
        $body[] = chunk_split(base64_encode($textBody));

        $body[] = '--' . $boundary;
        $body[] = 'Content-Type: text/html; charset=UTF-8';
        $body[] = 'Content-Transfer-Encoding: base64';
        $body[] = '';
        $body[] = chunk_split(base64_encode($htmlBody));

        $body[] = '--' . $boundary . '--';
        $body[] = '.';

        $dataPayload = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $body);
        $sendCommand($dataPayload, [250]);

        $sendCommand('QUIT', [221]);
        fclose($socket);

        return true;
    }

    /**
     * Fallback qua hàm native mail() của PHP
     */
    private function sendViaNativeMail(string $fromEmail, string $fromName, string $to, string $subject, string $htmlContent): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'X-Mailer: PHP/' . phpversion(),
        ];

        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlContent, implode("\r\n", $headers));
    }

    /**
     * Xây dựng nội dung Text đơn giản cho Email
     */
    public function buildNewOrderText(array $order): string
    {
        $orderCode = $order['order_code'] ?? 'MỚI';
        $slotDate = $order['slot_date'] ?? date('Y-m-d');
        $slotTime = $order['slot_time'] ?? '';
        $orderType = ($order['order_type'] ?? '') === 'takeaway' ? 'Gửi ship mang về' : 'Ghé bếp ăn tại chỗ';
        $custName = $order['customer_name'] ?? 'Khách';
        $custPhone = $order['customer_phone'] ?? '';
        $total = number_format((int)($order['total_amount'] ?? 0), 0, ',', '.') . 'đ';
        $payment = ($order['payment_method'] ?? '') === 'pay_later' ? 'Thanh toán sau tại bếp (Khách quen)' : 'Quét mã VietQR chuyển khoản';

        $lines = [];
        $lines[] = "BẾP CHAY AN YÊN - CÓ ĐƠN HÀNG MỚI #{$orderCode}";
        $lines[] = "==========================================";
        $lines[] = "Khung giờ phục vụ: {$slotDate} ({$slotTime})";
        $lines[] = "Hình thức: {$orderType}";
        $lines[] = "Khách hàng: {$custName} - {$custPhone}";
        if (!empty($order['customer_note'])) {
            $lines[] = "Ghi chú: " . $order['customer_note'];
        }
        $lines[] = "Phương thức thanh toán: {$payment}";
        $lines[] = "------------------------------------------";
        $lines[] = "DANH SÁCH MÓN ĐẶT:";

        $items = $order['items'] ?? [];
        foreach ($items as $item) {
            $name = $item['item_name'] ?? 'Món';
            $qty = $item['quantity'] ?? 1;
            $sub = number_format((int)($item['subtotal'] ?? 0), 0, ',', '.') . 'đ';
            $lines[] = "- {$name} x {$qty} = {$sub}";
        }

        $lines[] = "------------------------------------------";
        $lines[] = "TỔNG CỘNG: {$total}";
        $lines[] = "Trạng thái: Chờ xác nhận";
        $lines[] = "\nĐăng nhập trang quản trị để xem chi tiết và chuẩn bị món chu đáo nhé!";

        return implode("\n", $lines);
    }

    /**
     * Xây dựng nội dung HTML chuẩn phong cách Zen / Washi cho Email
     */
    public function buildNewOrderHtml(array $order): string
    {
        $orderCode = htmlspecialchars((string)($order['order_code'] ?? 'MỚI'), ENT_QUOTES, 'UTF-8');
        $slotDate = date('d/m/Y', strtotime((string)($order['slot_date'] ?? date('Y-m-d'))));
        $slotTime = htmlspecialchars((string)($order['slot_time'] ?? ''), ENT_QUOTES, 'UTF-8');
        $orderType = ($order['order_type'] ?? '') === 'takeaway' ? '🥡 Bếp gửi ship mang về' : '🍵 Ghé bếp ăn tại chỗ';
        $custName = htmlspecialchars((string)($order['customer_name'] ?? 'Khách'), ENT_QUOTES, 'UTF-8');
        $custPhone = htmlspecialchars((string)($order['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $custNote = htmlspecialchars((string)($order['customer_note'] ?? ''), ENT_QUOTES, 'UTF-8');
        $total = number_format((int)($order['total_amount'] ?? 0), 0, ',', '.') . 'đ';
        $payment = ($order['payment_method'] ?? '') === 'pay_later' ? 'Thanh toán sau tại quán (Khách quen)' : 'Quét mã VietQR chuyển khoản';

        $itemsHtml = '';
        foreach (($order['items'] ?? []) as $item) {
            $name = htmlspecialchars((string)($item['item_name'] ?? 'Món'), ENT_QUOTES, 'UTF-8');
            $qty = (int)($item['quantity'] ?? 1);
            $sub = number_format((int)($item['subtotal'] ?? 0), 0, ',', '.') . 'đ';
            $itemsHtml .= "
                <tr>
                    <td style=\"padding: 8px 10px; border-bottom: 1px solid #EBE5DF;\">{$name}</td>
                    <td style=\"padding: 8px 10px; border-bottom: 1px solid #EBE5DF; text-align: center;\"><strong>{$qty}</strong></td>
                    <td style=\"padding: 8px 10px; border-bottom: 1px solid #EBE5DF; text-align: right; color: #5B7065; font-weight: 600;\">{$sub}</td>
                </tr>
            ";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đơn hàng mới #{$orderCode}</title>
</head>
<body style="margin: 0; padding: 20px; background-color: #FAF7F2; font-family: 'Segoe UI', Arial, sans-serif; color: #2C2A29;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; background-color: #FFFFFF; border-radius: 16px; border: 1px solid #E6DED6; overflow: hidden; box-shadow: 0 4px 12px rgba(91, 112, 101, 0.06);">
        <!-- Header -->
        <tr>
            <td style="background-color: #5B7065; padding: 22px 24px; text-align: center; color: #FFFFFF;">
                <p style="margin: 0 0 4px 0; font-size: 13px; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.9;">Bếp Chay An Yên</p>
                <h1 style="margin: 0; font-size: 20px; font-weight: 600;">🌿 Có Đơn Đặt Món Mới #{$orderCode}</h1>
            </td>
        </tr>
        
        <!-- Nội dung chính -->
        <tr>
            <td style="padding: 24px;">
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 18px; font-size: 14px; line-height: 1.6;">
                    <tr>
                        <td width="35%" style="color: #6E6862; padding: 4px 0;">Khung giờ:</td>
                        <td style="font-weight: 600; color: #5B7065;">{$slotDate} ({$slotTime})</td>
                    </tr>
                    <tr>
                        <td style="color: #6E6862; padding: 4px 0;">Hình thức:</td>
                        <td style="font-weight: 600;">{$orderType}</td>
                    </tr>
                    <tr>
                        <td style="color: #6E6862; padding: 4px 0;">Khách hàng:</td>
                        <td><strong>{$custName}</strong> - <a href="tel:{$custPhone}" style="color: #5B7065; text-decoration: none;">{$custPhone}</a></td>
                    </tr>
                    <tr>
                        <td style="color: #6E6862; padding: 4px 0;">Thanh toán:</td>
                        <td>{$payment}</td>
                    </tr>
                    <tr>
                        <td style="color: #6E6862; padding: 4px 0;">Ghi chú:</td>
                        <td style="font-style: italic; color: #7C4A15;">{$custNote}</td>
                    </tr>
                </table>

                <h3 style="font-size: 14px; color: #5B7065; border-bottom: 2px solid #5B7065; padding-bottom: 6px; margin: 18px 0 10px 0;">
                    Danh Sách Món Cần Chuẩn Bị
                </h3>

                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size: 13px; margin-bottom: 16px;">
                    <thead>
                        <tr style="background-color: #F4EFEA; color: #5B7065; font-weight: 600;">
                            <th align="left" style="padding: 8px 10px;">Tên món</th>
                            <th align="center" style="padding: 8px 10px;">SL</th>
                            <th align="right" style="padding: 8px 10px;">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="padding: 12px 10px; font-weight: 700; font-size: 14px; text-align: right;">Tổng thanh toán:</td>
                            <td style="padding: 12px 10px; font-weight: 700; font-size: 16px; color: #5B7065; text-align: right;">{$total}</td>
                        </tr>
                    </tfoot>
                </table>

                <div style="background-color: #FAF7F2; border-radius: 10px; padding: 12px 16px; font-size: 12px; color: #6E6862; text-align: center;">
                    🌸 Chúc bếp một ngày phục vụ an lạc, mang trọn vẹn vị thanh ngọt đến bạn thương!
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function logMail(string $msg): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/mail.log';
        $entry = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $msg);
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }
}
