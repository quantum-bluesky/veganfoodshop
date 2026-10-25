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
$sellerZaloUrl = (string)($zaloConfig['seller_zalo_url'] ?? 'https://zalo.me/0987862241');
$orderNoteShipping = (string)($zaloConfig['order_note_shipping'] ?? '');
$zaloConfirmTemplate = (string)($zaloConfig['zalo_confirm_template'] ?? '');

$error = null;
$order = null;
$qrUrl = null;

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

        if ($order['payment_method'] === 'vietqr') {
            $vietQrService = new VietQrService($bankId, $accountNo, $accountName);
            $qrUrl = $vietQrService->generateQrUrl((int)$order['total_amount'], (string)$order['order_code']);
        }

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
        if ($order && $order['payment_method'] === 'vietqr') {
            $vietQrService = new VietQrService($bankId, $accountNo, $accountName);
            $qrUrl = $vietQrService->generateQrUrl((int)$order['total_amount'], (string)$order['order_code']);
        }
    } else {
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Xác nhận đơn - <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></title>
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
            <!-- Màn hình thông báo lỗi / hết ca mộc mạc -->
            <div class="zen-card" style="text-align: center; padding: 32px 20px;">
                <div style="font-size: 2.5rem; margin-bottom: 12px;">🌱</div>
                <h2 class="font-serif-zen" style="font-size: 1.4rem; color: var(--brand-tea-dark); margin-bottom: 8px;">
                    Thông báo từ Bếp
                </h2>
                <p style="font-size: 0.88rem; color: var(--text-muted-warm); margin-bottom: 24px; line-height: 1.5;">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </p>
                <a href="index.php" class="zen-btn-primary" style="text-decoration: none;">
                    Quay lại chọn ca khác
                </a>
            </div>

        <?php elseif ($order !== null): ?>
            <!-- Màn hình xác nhận thành công -->

            <header style="text-align: center;">
                <div style="font-size: 2.2rem; margin-bottom: 6px;">🍃</div>
                <h1 class="font-serif-zen" style="font-size: 1.5rem; color: var(--brand-tea-dark); margin-bottom: 4px;">
                    <?= $order['payment_method'] === 'pay_later' ? 'Bếp Đã Nhận Đơn An Lành' : 'Bếp Đã Tạm Giữ Chỗ Cho Bạn' ?>
                </h1>
                <p style="font-size: 0.8rem; color: var(--text-muted-warm);">
                    Mã đơn hàng: <strong style="color: var(--brand-tea-green);"><?= htmlspecialchars((string)$order['order_code'], ENT_QUOTES, 'UTF-8') ?></strong>
                </p>
            </header>

            <?php if ($order['payment_method'] === 'pay_later'): ?>
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
                <!-- Đơn chuyển khoản VietQR -->
                <section class="zen-card" style="text-align: center;">
                    <p style="font-size: 0.8rem; color: var(--text-muted-warm); margin-bottom: 12px;">
                        Quét mã VietQR chuyển khoản để bếp hoàn tất giữ suất bún/phở nhé:
                    </p>

                    <!-- Ảnh VietQR động -->
                    <?php if ($qrUrl): ?>
                        <div style="display: flex; justify-content: center; margin-bottom: 14px;">
                            <img src="<?= htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8') ?>" 
                                 alt="Mã VietQR Chuyển Khoản" 
                                 style="width: 230px; height: auto; border-radius: 16px; border: 1px solid var(--brand-border-soft); box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
                        </div>
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

            <a href="index.php" class="zen-btn-primary" style="text-decoration: none; text-align: center; background-color: #606C5D;">
                Về trang chủ Bếp An Yên
            </a>

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
                alert('Đã sao chép số tiền: ' + val);
            });
        }
    </script>
</body>
</html>
