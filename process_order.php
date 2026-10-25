<?php
declare(strict_types=1);

/**
 * Xử lý tiếp nhận đơn & Hiển thị màn hình xác nhận / VietQR
 * Bếp Chay An Yên
 */

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/src/autoload.php';

use App\Services\ConfigService;
use App\Services\OrderService;
use App\Services\VietQrService;
use App\Exceptions\SlotFullException;
use App\Exceptions\ValidationException;
use App\Exceptions\DomainException;

$configService = new ConfigService();
$orderService = new OrderService();
$zaloService = new \App\Services\ZaloAuthService();
$zaloConfig = $zaloService->getConfig();

$shopName = $configService->get('shop_name', 'Bếp Chay An Yên');
$bankId = $configService->get('bank_id', '970422');
$bankName = $configService->get('bank_name', 'MBBank');
$accountNo = $configService->get('account_no', '0987654321');
$accountName = $configService->get('account_name', 'BEP CHAY AN YEN');
$sellerZaloUrl = (string)$configService->get('seller_zalo_url', (string)($zaloConfig['seller_zalo_url'] ?? ''));
$sellerPhone = (string)$configService->get('seller_phone', (string)($zaloConfig['seller_phone'] ?? ''));
$orderNoteShipping = (string)$configService->get('order_note_shipping', (string)($zaloConfig['order_note_shipping'] ?? ''));
$zaloConfirmTemplate = (string)$configService->get('zalo_confirm_template', (string)($zaloConfig['zalo_confirm_template'] ?? ''));

$error = null;
$order = null;
$qrUrl = null;
$universalDeeplink = null;
$popularBankingApps = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $customerName = (string)($_POST['customer_name'] ?? '');
        $customerPhone = (string)($_POST['customer_phone'] ?? '');
        $slotId = (int)($_POST['slot_id'] ?? 0);
        $orderType = (string)($_POST['order_type'] ?? 'dine_in');
        $paymentMethod = (string)($_POST['payment_method'] ?? 'vietqr');
        $customerNote = (string)($_POST['customer_note'] ?? '');
        $zaloId = (string)($_POST['zalo_id'] ?? '');
        $avatarUrl = (string)($_POST['avatar_url'] ?? '');
        $items = (array)($_POST['items'] ?? []);

        // Kiểm tra bắt buộc đăng nhập Zalo nếu shop bật cấu hình
        if ($zaloService->isRequireLogin() && trim($zaloId) === '') {
            throw new ValidationException('Bếp An Yên yêu cầu đăng nhập tài khoản Zalo trước khi gửi đơn để đảm bảo kết nối giữ chỗ.');
        }

        $order = $orderService->createOrder([
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'slot_id' => $slotId,
            'order_type' => $orderType,
            'payment_method' => $paymentMethod,
            'customer_note' => $customerNote,
            'zalo_id' => $zaloId,
            'avatar_url' => $avatarUrl,
            'items' => $items,
        ]);

        // Áp dụng mô hình Post/Redirect/Get (PRG): Chuyển hướng sang GET với mã đơn để tránh submit lại khi refresh
        header('Location: process_order.php?code=' . urlencode((string)$order['order_code']), true, 303);
        exit;

    } catch (SlotFullException $e) {
        $error = $e->getMessage();
    } catch (ValidationException $e) {
        $error = $e->getMessage();
    } catch (DomainException $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = 'Đã có lỗi không mong muốn xảy ra. Bếp xin bạn thử lại sau ít phút nhé.';
    }
} else {
    // Nếu truy cập bằng GET kiểm tra mã đơn
    $orderCode = (string)($_GET['code'] ?? '');
    if ($orderCode !== '') {
        $order = $orderService->getOrderByCode($orderCode);
        if ($order) {
            if ($order['payment_method'] === 'vietqr') {
                $vietQrService = new VietQrService($bankId, $accountNo, $accountName);
                $qrUrl = $vietQrService->generateQrUrl((int)$order['total_amount'], (string)$order['order_code']);
                $universalDeeplink = $vietQrService->generateUniversalLink((int)$order['total_amount'], (string)$order['order_code']);
                $popularBankingApps = $vietQrService->getPopularBankingApps((int)$order['total_amount'], (string)$order['order_code']);
            }
        } else {
            $error = 'Không tìm thấy thông tin đơn hàng hoặc mã đơn không hợp lệ.';
        }
    } else {
        header('Location: index.php');
        exit;
    }
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443 || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8008';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$fullCurrentUrl = $protocol . $host . $requestUri;
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$previewImageUrl = $protocol . $host . ($scriptDir !== '' ? $scriptDir : '') . '/assets/images/preview.jpg';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Xác nhận đơn - <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="Chi tiết xác nhận đơn đặt chỗ và món chay tại <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>.">

    <!-- Các thẻ Open Graph cơ bản (Issue #31) -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($fullCurrentUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="Xác nhận đơn - <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="Chi tiết xác nhận đơn đặt chỗ và món chay tại <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>.">
    <meta property="og:image" content="<?= htmlspecialchars($previewImageUrl, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Thẻ hỗ trợ riêng cho Twitter / X -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Xác nhận đơn - <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="Chi tiết xác nhận đơn đặt chỗ và món chay tại <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>.">
    <meta name="twitter:image" content="<?= htmlspecialchars($previewImageUrl, ENT_QUOTES, 'UTF-8') ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;1,400&family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-container {
            width: 100%;
            max-width: 448px;
            padding: 24px 18px 48px 18px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .copy-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            font-size: 0.72rem;
            background: #FAF7F2;
            border: 1px solid var(--brand-border-soft);
            border-radius: 8px;
            color: var(--brand-tea-green);
            cursor: pointer;
            margin-left: 6px;
        }
        .copy-pill:active {
            background: var(--brand-sage-light);
        }
    </style>
</head>
<body>

    <main class="page-container">

        <?php if ($error !== null): ?>
            <!-- Màn hình thông báo lỗi / hết ca mộc mạc (Issue #29) -->
            <div class="zen-card" style="text-align: center; padding: 32px 20px;">
                <div style="font-size: 2.5rem; margin-bottom: 12px;">🌱</div>
                <h2 class="font-serif-zen" style="font-size: 1.4rem; color: var(--brand-tea-dark); margin-bottom: 12px;">
                    Thông báo từ Bếp
                </h2>
                <div style="font-size: 0.9rem; color: var(--text-charcoal); margin-bottom: 24px; line-height: 1.6; background: #FAF7F2; padding: 16px 18px; border-radius: 14px; border: 1px dashed var(--brand-border-soft); text-align: left;">
                    <?= nl2br(htmlspecialchars($error, ENT_QUOTES, 'UTF-8')) ?>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="index.php" class="zen-btn-primary" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px;">
                        <span>🌿</span>
                        <span>Lựa chọn đặt vào ca tiếp theo</span>
                    </a>
                    <?php if (!empty($sellerZaloUrl)): ?>
                        <a href="<?= htmlspecialchars($sellerZaloUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" 
                           style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: #0068FF; color: #FFFFFF; font-size: 0.88rem; font-weight: 600; padding: 12px 18px; border-radius: 999px; text-decoration: none; box-shadow: 0 2px 8px rgba(0, 104, 255, 0.25);">
                            <span>💬 Liên hệ trực tiếp chủ bếp qua Zalo</span> ↗
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($sellerPhone)): ?>
                        <a href="tel:<?= preg_replace('/[^0-9]/', '', $sellerPhone) ?>" 
                           style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: #FAF7F2; color: var(--brand-tea-dark); border: 1px solid var(--brand-border-soft); font-size: 0.85rem; font-weight: 600; padding: 10px 18px; border-radius: 999px; text-decoration: none;">
                            <span>📞 Gọi Hotline chủ bếp: <?= htmlspecialchars($sellerPhone, ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($order !== null): ?>
            <!-- Màn hình xác nhận thành công -->

            <header style="text-align: center;">
                <div style="font-size: 2.2rem; margin-bottom: 6px;">🍃</div>
                <h1 class="font-serif-zen" style="font-size: 1.5rem; color: var(--brand-tea-dark); margin-bottom: 4px;">
                    <?php if ($order['payment_status'] === 'paid'): ?>
                        Bếp Đã Nhận Thanh Toán An Lành
                    <?php elseif ($order['payment_method'] === 'pay_later'): ?>
                        Bếp Đã Nhận Đơn An Lành
                    <?php else: ?>
                        Bếp Đã Tạm Giữ Chỗ Cho Bạn
                    <?php endif; ?>
                </h1>
                <p style="font-size: 0.8rem; color: var(--text-muted-warm);">
                    Mã đơn hàng: <strong style="color: var(--brand-tea-green);"><?= htmlspecialchars((string)$order['order_code'], ENT_QUOTES, 'UTF-8') ?></strong>
                </p>
            </header>

            <?php if ($order['order_status'] === 'cancelled'): ?>
                <!-- Đơn hàng đã bị huỷ (Issue #27) -->
                <section class="zen-card" style="background-color: #FEF2F2; border: 1.5px solid #F87171; text-align: center; padding: 22px 16px;">
                    <div style="font-size: 2.2rem; margin-bottom: 8px;">🍃</div>
                    <h2 style="font-size: 1.15rem; color: #991B1B; font-weight: 700; margin-bottom: 6px;">
                        Đơn Hàng Đã Được Huỷ
                    </h2>
                    <p style="font-size: 0.82rem; color: #7F1D1D; line-height: 1.5;">
                        Suất đặt này đã được huỷ theo yêu cầu hoặc do thay đổi lịch phục vụ của bếp.
                    </p>
                    <p style="font-size: 0.76rem; color: #B91C1C; margin-top: 6px;">
                        Nếu bạn cần hỗ trợ thêm, xin vui lòng liên hệ trực tiếp với chủ bếp qua Zalo nhé.
                    </p>
                </section>

            <?php elseif ($order['order_status'] === 'completed'): ?>
                <!-- Đơn hàng đã hoàn tất (Issue #27) -->
                <section class="zen-card" style="background-color: #F0FDF4; border: 1.5px solid #86EFAC; text-align: center; padding: 22px 16px;">
                    <div style="font-size: 2.2rem; margin-bottom: 8px;">✨</div>
                    <h2 style="font-size: 1.15rem; color: #166534; font-weight: 700; margin-bottom: 6px;">
                        Đơn Hàng Đã Hoàn Tất
                    </h2>
                    <p style="font-size: 0.82rem; color: #15803D; line-height: 1.5;">
                        Bếp An Yên xin gửi lời tri ân sâu sắc vì bạn đã ghé thăm và thưởng thức bữa chay an lành!
                    </p>
                </section>

            <?php elseif ($order['payment_status'] === 'paid'): ?>
                <!-- Đã thanh toán thành công (Issue #16) -->
                <section class="zen-card" style="background-color: #ECFDF5; border: 1.5px solid #10B981; text-align: center; padding: 22px 16px;">
                    <div style="font-size: 2.2rem; margin-bottom: 8px;">🌸</div>
                    <h2 style="font-size: 1.15rem; color: #065F46; font-weight: 700; margin-bottom: 6px;">
                        Đã Nhận Chuyển Khoản Thành Công!
                    </h2>
                    <p style="font-size: 0.82rem; color: #047857; line-height: 1.5;">
                        Bếp An Yên đã nhận được thanh toán <strong><?= number_format((int)$order['total_amount'], 0, ',', '.') ?>đ</strong> từ bạn.
                    </p>
                    <p style="font-size: 0.76rem; color: #059669; margin-top: 6px;">
                        Nồi nước dùng đang sôi liu riu chờ bạn ghé vào khung giờ <strong><?= htmlspecialchars((string)$order['slot_time'], ENT_QUOTES, 'UTF-8') ?></strong> nhé.
                    </p>
                </section>

            <?php elseif ($order['payment_method'] === 'pay_later'): ?>
                <!-- Đơn dành cho khách quen (trả sau) -->
                <section class="zen-card" style="background-color: var(--brand-sage-light); border-color: var(--brand-border-soft);">
                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <span style="font-size: 1.4rem;">🍵</span>
                        <div style="font-size: 0.85rem; color: var(--brand-tea-dark); line-height: 1.45;">
                            <p style="font-weight: 700; margin-bottom: 4px;">Chào bạn quen thân thương,</p>
                            <p>Bếp đã lưu đơn và sẽ bắt đầu hầm nước dùng chuẩn bị cho bạn vào khung giờ <strong><?= htmlspecialchars((string)$order['slot_time'], ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                            <p style="margin-top: 4px; font-size: 0.78rem; opacity: 0.85;">Bạn có thể thanh toán trực tiếp tại quán sau khi dùng bữa nhé.</p>
                        </div>
                    </div>
                </section>

            <?php else: ?>
                <!-- Đơn chuyển khoản VietQR (Chưa thanh toán - Issue #22 / #23 Mobile QR Solutions) -->
                <section class="zen-card" style="text-align: center;">
                    <p style="font-size: 0.8rem; color: var(--text-muted-warm); margin-bottom: 12px;">
                        Quét mã VietQR chuyển khoản để bếp hoàn tất giữ suất bún/phở nhé:
                    </p>

                    <!-- Ảnh VietQR động -->
                    <?php if ($qrUrl): ?>
                        <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                            <img id="vietQrImg" 
                                 src="<?= htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8') ?>" 
                                 alt="Mã VietQR Chuyển Khoản" 
                                 style="width: 230px; height: auto; border-radius: 16px; border: 1px solid var(--brand-border-soft); box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
                        </div>

                        <!-- GIẢI PHÁP 1: Mở thẳng App Ngân Hàng qua Deeplink -->
                        <?php if (!empty($universalDeeplink)): ?>
                            <a href="<?= htmlspecialchars($universalDeeplink, ENT_QUOTES, 'UTF-8') ?>" 
                               class="zen-btn-primary" 
                               style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 14px; border-radius: 14px; font-size: 0.92rem; font-weight: 700; margin-bottom: 10px; box-shadow: 0 4px 14px rgba(91, 112, 101, 0.2);">
                                <span>🚀</span> Mở Ứng Dụng Ngân Hàng Thanh Toán
                            </a>
                        <?php endif; ?>

                        <!-- GIẢI PHÁP 2: Nút Lưu ảnh QR vào máy để quét từ Album -->
                        <button type="button" 
                                onclick="downloadQRCode('<?= htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars((string)$order['order_code'], ENT_QUOTES, 'UTF-8') ?>')" 
                                class="action-btn" 
                                style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 14px; font-size: 0.84rem; font-weight: 700; background: #FAF7F2; border: 1.5px dashed var(--brand-tea-green); color: var(--brand-tea-dark); border-radius: 12px; margin-bottom: 12px;">
                            <span>📥</span> Lưu Ảnh QR Vào Máy (Để Quét Từ Thư Viện)
                        </button>

                        <!-- Hướng dẫn 2 bước mộc mạc và trực quan -->
                        <div style="background: #FBF9F5; border: 1px solid #EAE3D9; border-radius: 12px; padding: 10px 12px; text-align: left; font-size: 0.76rem; color: #344037; line-height: 1.5; margin-bottom: 14px;">
                            <div style="font-weight: 700; color: var(--brand-tea-green); margin-bottom: 4px; display: flex; align-items: center; gap: 4px;">
                                <span>💡</span> Khi dùng điện thoại không thể quét mã trên màn hình:
                            </div>
                            <div style="display: flex; gap: 6px; margin-bottom: 4px;">
                                <span style="background: var(--brand-tea-green); color: #FFF; border-radius: 50%; width: 16px; height: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; flex-shrink: 0; margin-top: 1px;">1</span>
                                <span>Bấm <strong>"Lưu ảnh QR vào máy"</strong> ở trên để tải mã về cuộn ảnh điện thoại.</span>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <span style="background: var(--brand-tea-green); color: #FFF; border-radius: 50%; width: 16px; height: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; flex-shrink: 0; margin-top: 1px;">2</span>
                                <span>Mở App Ngân hàng ➜ Chọn <strong>Quét QR</strong> ➜ Chọn <strong>Ảnh từ thư viện (Album)</strong> để thanh toán.</span>
                            </div>
                        </div>

                        <!-- Khay phím tắt mở trực tiếp App Ngân Hàng phổ biến -->
                        <?php if (!empty($popularBankingApps)): ?>
                            <div style="margin-bottom: 14px; text-align: left;">
                                <div style="font-size: 0.74rem; color: var(--text-muted-warm); margin-bottom: 6px; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                                    <span>⚡</span> Hoặc chọn mở thẳng app ngân hàng của bạn:
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px;">
                                    <?php foreach ($popularBankingApps as $app): ?>
                                        <a href="<?= htmlspecialchars($app['url'], ENT_QUOTES, 'UTF-8') ?>" 
                                           style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 8px 4px; background: <?= htmlspecialchars($app['bg'], ENT_QUOTES, 'UTF-8') ?>; border: 1px solid rgba(0,0,0,0.06); border-radius: 12px; text-decoration: none; color: <?= htmlspecialchars($app['color'], ENT_QUOTES, 'UTF-8') ?>; transition: transform 0.15s ease;">
                                            <span style="font-size: 1.15rem; margin-bottom: 2px;"><?= $app['icon'] ?></span>
                                            <span style="font-size: 0.72rem; font-weight: 700;"><?= htmlspecialchars($app['short_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>

                    <div style="background-color: #FAF7F2; border-radius: 14px; padding: 12px; font-size: 0.82rem; text-align: left; display: flex; flex-direction: column; gap: 6px;">
                        <div>
                            <span style="color: var(--text-muted-warm);">Ngân hàng:</span> 
                            <strong><?= htmlspecialchars($bankName, ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <div>
                            <span style="color: var(--text-muted-warm);">Số tài khoản:</span> 
                            <strong id="accNoText"><?= htmlspecialchars($accountNo, ENT_QUOTES, 'UTF-8') ?></strong>
                            <button type="button" class="copy-pill" onclick="copyText('accNoText')">Sao chép</button>
                        </div>
                        <div>
                            <span style="color: var(--text-muted-warm);">Chủ tài khoản:</span> 
                            <strong><?= htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <div>
                            <span style="color: var(--text-muted-warm);">Số tiền:</span> 
                            <strong style="color: var(--brand-tea-green); font-size: 0.95rem;" id="amountText"><?= number_format((int)$order['total_amount'], 0, ',', '.') ?>đ</strong>
                            <button type="button" class="copy-pill" onclick="copyRaw('<?= (int)$order['total_amount'] ?>')">Sao chép</button>
                        </div>
                        <div>
                            <span style="color: var(--text-muted-warm);">Nội dung CK:</span> 
                            <strong id="codeText"><?= htmlspecialchars((string)$order['order_code'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <button type="button" class="copy-pill" onclick="copyText('codeText')">Sao chép</button>
                        </div>
                    </div>

                    <!-- Thông báo ứng dụng hỗ trợ & tự động xác nhận (Issue #16) -->
                    <div style="margin-top: 10px; background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 10px; padding: 10px; text-align: left; font-size: 0.74rem; color: #475569; line-height: 1.45;">
                        <p style="margin-bottom: 4px;">
                            📱 <strong>Ứng dụng hỗ trợ:</strong> Hầu hết 40+ ứng dụng Ngân hàng (VCB, MB, Techcombank, BIDV, VPBank, ACB, Agribank...) và Ví MoMo, ZaloPay (chuyển Napas 247 bằng VietQR).
                        </p>
                        <p style="color: #047857;">
                            ⚡ <strong>Xác nhận tức thời:</strong> Vui lòng giữ nguyên nội dung chuyển khoản để hệ thống tự động xác nhận trong 5 - 15 giây.
                        </p>
                    </div>

                    <div id="paymentCheckingIndicator" style="display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 0.74rem; color: var(--brand-tea-green); margin-top: 12px;">
                        <span style="animation: pulse 1.5s infinite;">⏳</span>
                        <span>Hệ thống đang tự động lắng nghe xác nhận từ ngân hàng...</span>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Chi tiết đơn hàng tóm tắt -->
            <section class="zen-card">
                <h2 style="font-size: 0.88rem; font-weight: 700; color: var(--brand-tea-green); margin-bottom: 12px;">
                    Chi tiết suất đặt
                </h2>

                <div style="font-size: 0.82rem; display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px;">
                    <p><span style="color: var(--text-muted-warm);">Người nhận:</span> <strong><?= htmlspecialchars((string)$order['customer_name'], ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars((string)$order['customer_phone'], ENT_QUOTES, 'UTF-8') ?>)</p>
                    <p><span style="color: var(--text-muted-warm);">Hình thức:</span> <?= $order['order_type'] === 'dine_in' ? '🍵 Ghé bếp ăn tại chỗ' : '🥡 Bếp gửi ship về' ?></p>
                    <p><span style="color: var(--text-muted-warm);">Khung giờ:</span> <strong><?= htmlspecialchars((string)($order['slot_date'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars((string)$order['slot_time'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <?php if (!empty($order['customer_note'])): ?>
                        <p><span style="color: var(--text-muted-warm);">Ghi chú:</span> <em><?= htmlspecialchars((string)$order['customer_note'], ENT_QUOTES, 'UTF-8') ?></em></p>
                    <?php endif; ?>
                </div>

                <div style="border-top: 1px dashed var(--brand-border-soft); padding-top: 10px; display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($order['items'] as $item): ?>
                        <div style="display: flex; justify-content: space-between; font-size: 0.82rem;">
                            <span><?= htmlspecialchars((string)$item['item_name'], ENT_QUOTES, 'UTF-8') ?> <span style="color: var(--text-muted-warm);">x<?= (int)$item['quantity'] ?></span></span>
                            <span style="font-weight: 600;"><?= number_format((int)$item['subtotal'], 0, ',', '.') ?>đ</span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="border-top: 1px solid var(--brand-border-soft); margin-top: 10px; padding-top: 10px; display: flex; justify-content: space-between; font-weight: 700; font-size: 0.95rem; color: var(--brand-tea-dark);">
                    <span>Tổng cộng:</span>
                    <span style="color: var(--brand-tea-green);"><?= number_format((int)$order['total_amount'], 0, ',', '.') ?>đ</span>
                </div>
            </section>

            <?php if (!empty($orderNoteShipping)): ?>
                <p style="font-size: 0.75rem; color: var(--accent-wood); text-align: center; font-style: italic; margin-top: -6px;">
                    * <?= htmlspecialchars($orderNoteShipping, ENT_QUOTES, 'UTF-8') ?> *
                </p>
            <?php endif; ?>

            <?php if (!empty($sellerZaloUrl)): 
                $zaloMsg = str_replace('{order_id}', (string)$order['order_code'], $zaloConfirmTemplate);
            ?>
                <!-- Khối liên hệ & xác nhận qua Zalo -->
                <section class="zen-card" style="background: #F0F7FF; border-color: #BFDBFE; padding: 16px;">
                    <h3 style="font-size: 0.88rem; font-weight: 700; color: #1E40AF; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <svg width="18" height="18" viewBox="0 0 48 48" fill="none"><path fill="#0068FF" d="M24 4C12.95 4 4 12.95 4 24c0 3.73 1.02 7.22 2.79 10.22L4 44l10.15-2.67C17.06 43.04 20.44 44 24 44c11.05 0 20-8.95 20-20S35.05 4 24 4z"/></svg>
                        <span>Xác nhận đơn qua Zalo</span>
                    </h3>
                    <p style="font-size: 0.78rem; color: #4B5563; margin-bottom: 12px; line-height: 1.4;">
                        Bạn có thể gửi tin nhắn trực tiếp đến Zalo của Bếp để nhận xác nhận tức thời:
                    </p>
                    <div style="display: flex; gap: 8px;">
                        <a href="<?= htmlspecialchars($sellerZaloUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" 
                           style="flex: 1; text-align: center; padding: 10px 12px; border-radius: 12px; background: #0068FF; color: #FFFFFF; font-size: 0.8rem; font-weight: 600; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px;">
                            <span>Mở Zalo Bếp ↗</span>
                        </a>
                        <button type="button" class="action-btn" onclick="copyRaw('<?= addslashes(htmlspecialchars($zaloMsg, ENT_QUOTES, 'UTF-8')) ?>')" 
                                style="padding: 10px 14px; border-radius: 12px; font-size: 0.78rem; font-weight: 600; background: #FFFFFF; border: 1px solid #BFDBFE; color: #1E40AF;">
                            Sao chép tin nhắn
                        </button>
                    </div>
                </section>
            <?php endif; ?>

            <div style="display: flex; gap: 8px; flex-direction: column;">
                <a href="reviews.php?order_code=<?= urlencode((string)$order['order_code']) ?>&name=<?= urlencode((string)$order['customer_name']) ?>&phone=<?= urlencode((string)$order['customer_phone']) ?>" 
                   style="text-decoration: none; text-align: center; padding: 12px; border-radius: 12px; background: #FAF7F2; border: 1px solid var(--brand-border-soft); color: var(--brand-tea-dark); font-size: 0.84rem; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <span>🌸</span> Gửi cảm nhận & lưu bút đến Bếp An Yên
                </a>
                <a href="index.php" class="zen-btn-primary" style="text-decoration: none; text-align: center; background-color: #606C5D;">
                    Về trang chủ Bếp An Yên
                </a>
            </div>

        <?php endif; ?>

    </main>

    <script>
        function copyText(elementId) {
            const el = document.getElementById(elementId);
            if (el) {
                navigator.clipboard.writeText(el.innerText.trim()).then(() => {
                    alert('Đã sao chép: ' + el.innerText.trim());
                });
            }
        }
        function copyRaw(val) {
            navigator.clipboard.writeText(val).then(() => {
                alert('Đã sao chép: ' + val);
            });
        }

        /**
         * Lưu/tải ảnh mã QR về máy điện thoại để quét từ thư viện ảnh (Issue #22 Mobile QR)
         */
        function downloadQRCode(qrImageUrl, orderCode) {
            if (!qrImageUrl) return;

            // Thử tải bằng fetch blob (tương thích tốt với lưu file trên mobile)
            fetch(qrImageUrl)
                .then(res => {
                    if (!res.ok) throw new Error('Network error');
                    return res.blob();
                })
                .then(blob => {
                    const blobUrl = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = blobUrl;
                    link.download = `VietQR_Don_${orderCode}.png`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
                    alert('Đã lưu ảnh mã QR vào máy! Bạn hãy mở App Ngân hàng ➜ Quét QR ➜ Chọn "Ảnh từ thư viện (Album)" để thanh toán nhé 🍃');
                })
                .catch(() => {
                    // Fallback: Mở ảnh trực tiếp hoặc tạo link tải thông thường
                    const link = document.createElement('a');
                    link.href = qrImageUrl;
                    link.download = `VietQR_Don_${orderCode}.png`;
                    link.target = '_blank';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    alert('Đã mở ảnh mã QR. Nếu chưa tự động lưu, bạn hãy nhấn giữ vào ảnh để Lưu vào Album ảnh nhé 🍃');
                });
        }

        // Tự động kiểm tra trạng thái thanh toán theo thời gian thực (Issue #16)
        (function() {
            const isPaid = <?= ($order && $order['payment_status'] === 'paid') ? 'true' : 'false' ?>;
            const isPayLater = <?= ($order && $order['payment_method'] === 'pay_later') ? 'true' : 'false' ?>;
            const orderCode = '<?= $order ? htmlspecialchars((string)$order['order_code'], ENT_QUOTES, 'UTF-8') : '' ?>';

            if (!isPaid && !isPayLater && orderCode) {
                const interval = setInterval(function() {
                    fetch(`api.php?action=check_payment_status&order_code=${encodeURIComponent(orderCode)}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.success && data.is_paid) {
                                clearInterval(interval);
                                // Tải lại trang để hiển thị màn hình xác nhận thanh toán thành công
                                window.location.reload();
                            }
                        })
                        .catch(() => {});
                }, 3500);
            }
        })();
    </script>
</body>
</html>
