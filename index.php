<?php
declare(strict_types=1);

/**
 * Trang Đặt Chỗ & Đặt Món Chính - Bếp Chay An Yên
 * Phong cách: Hơi thở Ghibli & Thiền Vị (Zen UI/UX)
 * Tương thích Mobile-First (375px - 430px)
 */

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/src/autoload.php';

use App\Services\ConfigService;
use App\Services\MenuService;
use App\Services\SlotService;

$configService = new ConfigService();
$menuService = new MenuService();
$slotService = new SlotService();

$shopName = $configService->get('shop_name', 'Bếp Chay An Yên');
$shopTagline = $configService->get('shop_tagline', 'Chốn dừng chân thanh tĩnh');
$shopQuote = $configService->get('shop_quote', 'Một bát canh thanh ngọt, vỗ về những bộn bề ngày thường');
$noticeText = $configService->get('notice_text', 'Lịch bếp hôm nay: Ca trưa 11:30 - 13:30 (Chỉ nhận tối đa 12 suất)');

$today = date('Y-m-d');
$availableDates = $slotService->getAvailableDates();
if (empty($availableDates)) {
    $availableDates = [$today];
}
$selectedDate = $availableDates[0];
$slots = $slotService->getSlotsByDate($selectedDate, true);
$menuItems = $menuService->getActiveMenu();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($shopTagline, ENT_QUOTES, 'UTF-8') ?></title>
    
    <!-- Google Fonts: Lora (Serif) & Quicksand (Sans-Serif) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;1,400&family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Design System Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* CSS nội dòng bổ trợ cho bố cục Mobile */
        .page-container {
            width: 100%;
            max-width: 448px; /* max-w-md */
            padding: 28px 18px 48px 18px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .notice-banner {
            background-color: var(--brand-sage-light);
            border: 1px solid var(--brand-border-soft);
            border-radius: var(--radius-input);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.82rem;
            color: var(--brand-tea-dark);
        }

        .pulse-dot {
            width: 10px;
            height: 10px;
            background-color: var(--brand-tea-green);
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow: 0 0 0 0 rgba(91, 112, 101, 0.7);
            animation: pulseGreen 2s infinite;
        }

        @keyframes pulseGreen {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(91, 112, 101, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 8px rgba(91, 112, 101, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(91, 112, 101, 0);
            }
        }

        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>

    <main class="page-container">

        <!-- Header: Không gian an yên mộc mạc -->
        <header style="text-align: center; margin-bottom: 6px;">
            <p style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.15em; color: var(--brand-tea-green); font-weight: 700; margin-bottom: 4px;">
                <?= htmlspecialchars($shopTagline, ENT_QUOTES, 'UTF-8') ?>
            </p>
            <h1 class="font-serif-zen" style="font-size: 1.85rem; color: var(--brand-tea-dark); font-weight: 500; margin-bottom: 6px;">
                <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p style="font-size: 0.78rem; color: var(--text-muted-warm); font-style: italic;">
                "<?= htmlspecialchars($shopQuote, ENT_QUOTES, 'UTF-8') ?>"
            </p>
        </header>

        <!-- Thông báo lịch bếp hôm nay -->
        <section class="notice-banner" aria-label="Lịch bếp">
            <div class="pulse-dot" aria-hidden="true"></div>
            <div>
                <p style="font-weight: 600; margin-bottom: 1px;">Lịch bếp phục vụ:</p>
                <p style="opacity: 0.9;"><?= htmlspecialchars($noticeText, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </section>

        <!-- Form Đặt Chỗ & Món Lành -->
        <form id="orderForm" action="process_order.php" method="POST" style="display: flex; flex-direction: column; gap: 18px;">

            <!-- BƯỚC 1: Chọn hình thức dùng món -->
            <section class="zen-card">
                <h2 class="zen-label">
                    <span class="badge-step">1</span> Bạn muốn dùng món như thế nào?
                </h2>
                <div class="zen-radio-group">
                    <label class="zen-option is-checked">
                        <input type="radio" name="order_type" value="dine_in" checked>
                        <span style="font-size: 1.25rem; margin-bottom: 4px;">🍵</span>
                        <span style="font-weight: 600; font-size: 0.85rem;">Ghé bếp ăn tại chỗ</span>
                        <span style="font-size: 0.7rem; color: var(--text-muted-warm);">Chỗ ngồi an tĩnh</span>
                    </label>
                    <label class="zen-option">
                        <input type="radio" name="order_type" value="takeaway">
                        <span style="font-size: 1.25rem; margin-bottom: 4px;">🥡</span>
                        <span style="font-weight: 600; font-size: 0.85rem;">Bếp gửi ship về</span>
                        <span style="font-size: 0.7rem; color: var(--text-muted-warm);">Đóng gói tách nước</span>
                    </label>
                </div>
            </section>

            <!-- BƯỚC 2: Chọn ngày & Khung giờ phục vụ (Time Slots) -->
            <section class="zen-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h2 class="zen-label" style="margin-bottom: 0;">
                        <span class="badge-step">2</span> Khung giờ bạn ghé / nhận món
                    </h2>
                    <?php if (count($availableDates) > 1): ?>
                    <select id="dateSelector" name="order_date" style="font-size: 0.75rem; padding: 4px 8px; border-radius: 8px; border: 1px solid var(--brand-border-soft); background: #FAF7F2; color: var(--brand-tea-dark); outline: none;">
                        <?php foreach ($availableDates as $d): ?>
                            <option value="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?>" <?= $d === $selectedDate ? 'selected' : '' ?>>
                                <?= date('d/m/Y', strtotime($d)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>

                <div id="slotContainer" class="zen-radio-group">
                    <?php if (empty($slots)): ?>
                        <p style="grid-column: span 2; text-align: center; font-size: 0.8rem; color: var(--text-muted-warm); padding: 16px 0;">
                            Hôm nay bếp chưa mở ca nhận đặt, bạn thương ghé lại sau nhé.
                        </p>
                    <?php else: ?>
                        <?php foreach ($slots as $index => $slot): 
                            $isFull = $slot['is_full'];
                            $isChecked = ($index === 0 && !$isFull);
                        ?>
                            <label class="zen-option <?= $isChecked ? 'is-checked' : '' ?> <?= $isFull ? 'is-disabled' : '' ?>">
                                <input type="radio" name="slot_id" value="<?= (int)$slot['id'] ?>" <?= $isChecked ? 'checked' : '' ?> <?= $isFull ? 'disabled' : '' ?>>
                                <p style="font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars($slot['slot_time'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p style="font-size: 0.7rem; opacity: 0.8; margin-top: 2px;">
                                    <?= $isFull ? 'Đã hết suất' : 'Còn ' . (int)$slot['remaining_capacity'] . ' chỗ' ?>
                                </p>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- BƯỚC 3: Chọn món thanh tịnh tinh tuyển -->
            <section class="zen-card">
                <h2 class="zen-label">
                    <span class="badge-step">3</span> Món lành hôm nay
                </h2>
                
                <div style="display: flex; flex-direction: column;">
                    <?php foreach ($menuItems as $idx => $item): 
                        $defaultQty = ($idx === 0) ? 1 : 0;
                    ?>
                        <div class="menu-item-row" data-price="<?= (int)$item['price'] ?>" data-item-id="<?= (int)$item['id'] ?>">
                            <div class="menu-item-info">
                                <h3 class="menu-item-title"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <?php if (!empty($item['description'])): ?>
                                    <p class="menu-item-desc"><?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <span class="menu-item-price"><?= number_format((int)$item['price'], 0, ',', '.') ?>đ</span>
                            </div>
                            <div class="qty-control">
                                <button type="button" class="qty-btn qty-minus" aria-label="Giảm số lượng">-</button>
                                <input type="text" name="items[<?= (int)$item['id'] ?>]" value="<?= $defaultQty ?>" class="qty-input" readonly>
                                <button type="button" class="qty-btn qty-plus" aria-label="Tăng số lượng">+</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- BƯỚC 4: Thông tin & Thanh toán linh hoạt -->
            <section class="zen-card" style="display: flex; flex-direction: column; gap: 14px;">
                <h2 class="zen-label">
                    <span class="badge-step">4</span> Người thương gửi đơn
                </h2>

                <div>
                    <input type="text" name="customer_name" id="custName" class="zen-input" 
                           placeholder="Tên hoặc biệt danh của bạn" required autocomplete="name">
                </div>

                <div>
                    <input type="tel" name="customer_phone" id="custPhone" class="zen-input" 
                           placeholder="Số điện thoại của bạn (VD: 0901234567)" required autocomplete="tel">
                    <p style="font-size: 0.72rem; color: var(--text-muted-warm); margin-top: 4px; padding-left: 4px;">
                        * Bếp dùng số điện thoại để giữ chỗ và liên hệ khi món sẵn sàng.
                    </p>
                </div>

                <!-- Lời chào nhận diện khách quen (AJAX) -->
                <div id="trustedBadge" class="trusted-badge hidden">
                    <p style="font-weight: 700; margin-bottom: 2px;">🌿 Chào bạn quen trở lại!</p>
                    <p>Bếp ghi nhận đơn ngay, bạn có thể chọn thanh toán sau khi ghé quán nhé.</p>
                </div>

                <div>
                    <textarea name="customer_note" class="zen-input" rows="2" 
                              placeholder="Ghi chú thêm cho bếp (ví dụ: ít cay, không ớt, xin thêm chanh...)"></textarea>
                </div>

                <!-- Phương thức thanh toán -->
                <div style="margin-top: 6px;">
                    <p style="font-size: 0.8rem; font-weight: 600; color: var(--brand-tea-green); margin-bottom: 8px;">
                        Hình thức thanh toán:
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <!-- Quét mã VietQR -->
                        <label class="zen-option is-checked" style="flex-direction: row; justify-content: flex-start; text-align: left; padding: 12px 14px; gap: 10px;">
                            <input type="radio" name="payment_method" id="vietqrRadio" value="vietqr" checked>
                            <span style="font-size: 1.1rem;">📱</span>
                            <div>
                                <p style="font-weight: 600; font-size: 0.85rem;">Quét mã VietQR chuyển khoản</p>
                                <p style="font-size: 0.72rem; color: var(--text-muted-warm);">Chuyển khoản nhanh để bếp chuẩn bị nước dùng chu đáo</p>
                            </div>
                        </label>

                        <!-- Thanh toán sau (chỉ hiển thị cho khách quen) -->
                        <div id="payLaterWrapper" class="hidden">
                            <label class="zen-option" style="flex-direction: row; justify-content: flex-start; text-align: left; padding: 12px 14px; gap: 10px; width: 100%;">
                                <input type="radio" name="payment_method" id="payLaterRadio" value="pay_later">
                                <span style="font-size: 1.1rem;">🌿</span>
                                <div>
                                    <p style="font-weight: 600; font-size: 0.85rem;">Thanh toán khi dùng bữa (Dành cho khách quen)</p>
                                    <p style="font-size: 0.72rem; color: var(--text-muted-warm);">Bạn thanh toán trực tiếp tại quán sau khi thưởng thức món</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Nút gửi đơn to bản cố định trải nghiệm di động -->
            <button type="submit" id="submitBtn" class="zen-btn-primary">
                <span>Gửi Đặt Bếp An Yên</span>
                <span id="btnTotalText"></span>
            </button>
        </form>

        <footer style="text-align: center; font-size: 0.72rem; color: var(--text-muted-warm); margin-top: 10px;">
            <p>Bếp Chay An Yên • Bình an trong từng bát bún phở</p>
        </footer>

    </main>

    <!-- Client Script -->
    <script src="assets/js/app.js"></script>
</body>
</html>
