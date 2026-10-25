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
use App\Services\LunarCalendarService;
use App\Services\MenuService;
use App\Services\PromotionService;
use App\Services\SlotService;

$configService = new ConfigService();
$customerService = new \App\Services\CustomerService();
$menuService = new MenuService();
$slotService = new SlotService();
$promotionService = new PromotionService(\App\Database\Connection::getInstance(), $configService, $customerService);
$zaloService = new \App\Services\ZaloAuthService();
$zaloConfig = $zaloService->getConfig();

// Xử lý clean routes cho Zalo khi dùng Nginx / Apache front-controller
$requestPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (preg_match('#/(api/public/)?zalo-login/?$#', $requestPath)) {
    try {
        $authData = $zaloService->generateAuthUrl();
        if (!$authData['is_mock']) {
            setcookie('zalo_code_verifier', $authData['code_verifier'], [
                'expires' => time() + 300,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        header('Location: ' . $authData['auth_url']);
        exit;
    } catch (\Throwable $e) {
        $zaloLoginError = $e->getMessage();
    }
} elseif (preg_match('#/(api/public/)?zalo-callback/?$#', $requestPath)) {
    $code = (string)($_GET['code'] ?? '');
    $codeVerifier = (string)($_COOKIE['zalo_code_verifier'] ?? '');
    try {
        $user = $zaloService->handleCallback($code, $codeVerifier);
        echo $zaloService->renderCallbackHtml($user, 'index.php');
        exit;
    } catch (\Throwable $e) {
        $zaloLoginError = $e->getMessage();
    }
}

$shopName = $configService->get('shop_name', 'Bếp Chay An Yên');
$shopTagline = $configService->get('shop_tagline', 'Chốn dừng chân thanh tĩnh');
$shopQuote = $configService->get('shop_quote', 'Một bát canh thanh ngọt, vỗ về những bộn bề ngày thường');
$noticeText = $configService->get('notice_text', 'Lịch bếp hôm nay: Ca trưa 11:30 - 13:30 (Chỉ nhận tối đa 12 suất)');
$orderNoteShipping = (string)($zaloConfig['order_note_shipping'] ?? '');
$sellerZaloUrl = (string)($zaloConfig['seller_zalo_url'] ?? 'https://zalo.me/0987862241');
$sellerPhone = (string)$configService->get('seller_phone', '0901 234 567');
$minLeadTimeHours = (int)$configService->get('min_lead_time_hours', '3');
$advanceBookingDays = (int)$configService->get('advance_booking_days', '7');
$requireZaloLogin = (bool)($zaloConfig['require_zalo_login'] ?? false);

$today = date('Y-m-d');
$todayLunarInfo = LunarCalendarService::getLunarInfoForDate($today);
$upcomingVeganDays = LunarCalendarService::getUpcomingSpecialVeganDays($today, 2);

$shopClosedStatusToday = $configService->getShopClosedStatus($today);
$availableDates = $slotService->getAvailableDates($advanceBookingDays);
$reqDate = (string)($_GET['date'] ?? '');
$selectedDate = ($reqDate !== '' && in_array($reqDate, $availableDates, true)) ? $reqDate : (!empty($availableDates) ? $availableDates[0] : $today);

$datesWithLunar = [];
$datesWithPromos = [];
foreach ($availableDates as $d) {
    $datesWithLunar[$d] = LunarCalendarService::getLunarInfoForDate($d);
    $activeP = $promotionService->getActivePromotionsForDate($d);
    if (!empty($activeP)) {
        $datesWithPromos[$d] = $activeP;
    }
}
$selectedDateLunar = $datesWithLunar[$selectedDate] ?? LunarCalendarService::getLunarInfoForDate($selectedDate);
$activePromosForSelectedDate = $datesWithPromos[$selectedDate] ?? $promotionService->getActivePromotionsForDate($selectedDate);

$slots = !empty($availableDates) ? $slotService->getSlotsByDate($selectedDate, true, $minLeadTimeHours) : [];
$isBookingDisabled = empty($availableDates) || empty($slots);
$scheduledSuite = $menuService->getScheduledSuiteForDate($selectedDate);
$rawMenuItems = $menuService->getMenuItemsForDate($selectedDate);
$menuItems = $promotionService->enrichMenuItemsWithPromotions($selectedDate, $rawMenuItems);
$hasAnyPromoInMenu = false;
foreach ($menuItems as $it) {
    if (!empty($it['has_promo'])) {
        $hasAnyPromoInMenu = true;
        break;
    }
}
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

        <!-- Thanh điều hướng tối giản kết nối các trang & Nút cỡ chữ A+/A- (Issue #30) -->
        <nav class="zen-nav-bar" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 14px; background: #FAF7F2; border: 1px solid var(--brand-border-soft); border-radius: 999px; font-size: 0.76rem; margin-bottom: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-weight: 700; color: var(--brand-tea-green);">
                    🍲 Đặt Chỗ
                </span>
                <span style="color: #D1D5DB;">•</span>
                <a href="about.php" style="text-decoration: none; color: var(--brand-tea-dark); font-weight: 600;">
                    🌿 Về Bếp An Yên
                </a>
                <span style="color: #D1D5DB;">•</span>
                <a href="reviews.php" style="text-decoration: none; color: var(--brand-tea-dark); font-weight: 600;">
                    🌸 Cảm Nhận
                </a>
            </div>
            <!-- Nút điều chỉnh kích thước chữ A+/A- (Issue #30) -->
            <button type="button" id="btnToggleFontSize" class="font-size-toggle-btn" title="Tăng cỡ chữ để đọc rõ hơn / Về cỡ chữ mặc định" aria-label="Điều chỉnh cỡ chữ">
                <span class="fs-icon" id="fsIcon">A<sup>+</sup></span>
                <span class="fs-label" id="fsLabel">Chữ lớn</span>
            </button>
        </nav>

        <!-- Top Zalo Profile / Connection Pill (Issue #27) -->
        <div id="topZaloPill" style="display: flex; justify-content: space-between; align-items: center; padding: 5px 12px; background: #FAF7F2; border: 1px solid var(--brand-border-soft); border-radius: 999px; font-size: 0.75rem; margin-bottom: 8px;">
            <!-- Khi đã đăng nhập Zalo: icon avatar + Tên + Nút Đơn của tôi + Nút Đăng xuất -->
            <div id="topZaloLoggedIn" class="hidden" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div style="display: flex; align-items: center; gap: 7px; overflow: hidden;">
                    <div id="topZaloAvatar" style="width: 24px; height: 24px; border-radius: 50%; overflow: hidden; background: #0068FF; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.72rem; flex-shrink: 0;">
                        Z
                    </div>
                    <span id="topZaloName" style="font-weight: 700; color: #1E40AF; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Khách Zalo</span>
                    <span style="font-size: 0.62rem; background: #DBEAFE; color: #1E40AF; padding: 1px 6px; border-radius: 999px; font-weight: 600; flex-shrink: 0;">✓ Đã nối</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                    <button type="button" id="btnTopMyBookings" style="background: #E0E7FF; border: 1px solid #C7D2FE; color: #3730A3; font-size: 0.72rem; border-radius: 999px; padding: 3px 9px; font-weight: 600; cursor: pointer; white-space: nowrap;">
                        📅 Đơn của tôi
                    </button>
                    <button type="button" id="btnTopLogout" title="Đăng xuất tài khoản Zalo" style="background: none; border: none; color: #9CA3AF; font-size: 0.74rem; cursor: pointer; padding: 2px 4px; text-decoration: underline;">
                        Thoát
                    </button>
                </div>
            </div>
            <!-- Khi chưa đăng nhập Zalo: gợi ý kết nối Zalo tinh tế -->
            <div id="topZaloLoggedOut" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div style="display: flex; align-items: center; gap: 6px; color: var(--text-muted-warm); font-size: 0.73rem;">
                    <span>💬</span>
                    <span>Kết nối Zalo để xem đơn & nhận tin</span>
                </div>
                <a href="api/public/zalo-login" style="display: inline-flex; align-items: center; gap: 4px; background: #0068FF; color: #FFFFFF; font-size: 0.72rem; padding: 3px 9px; border-radius: 999px; text-decoration: none; font-weight: 600; box-shadow: 0 1px 4px rgba(0, 104, 255, 0.2);">
                    <span>Nối Zalo</span>
                </a>
            </div>
        </div>

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

        <!-- Notice & Event Hub (Trung tâm thông báo & lịch âm chay - Issue #19) -->
        <section class="zen-hub-card" aria-label="Thông báo & Lịch âm">
            <div class="zen-hub-header">
                <div class="zen-lunar-today">
                    <span style="font-size: 1.1rem;"><?= $todayLunarInfo['is_vegan_day'] ? '🌕' : '🗓️' ?></span>
                    <span>Hôm nay: <?= date('d/m') ?> • <?= htmlspecialchars($todayLunarInfo['lunar_text'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (!empty($todayLunarInfo['badge'])): ?>
                        <span class="badge-lunar-chip <?= htmlspecialchars($todayLunarInfo['badge']['class'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($todayLunarInfo['badge']['icon'] . ' ' . $todayLunarInfo['badge']['label'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.72rem; color: var(--brand-tea-green); font-weight: 600; display: flex; align-items: center; gap: 4px;">
                    <span class="pulse-dot" style="width: 7px; height: 7px;" aria-hidden="true"></span>
                    <span><?= $shopClosedStatusToday['is_closed'] ? 'Tạm nghỉ' : 'Đang mở ca' ?></span>
                </div>
            </div>

            <div class="zen-hub-events">
                <!-- Thông báo chung từ bếp -->
                <div class="zen-event-item">
                    <span style="font-size: 0.9rem;">🌿</span>
                    <span style="flex: 1; line-height: 1.4;"><?= htmlspecialchars($noticeText, ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <!-- Chương trình Khuyến Mại & Ưu đãi đặc biệt (Issue #20) -->
                <?php if (!empty($activePromosForSelectedDate)): ?>
                    <?php foreach ($activePromosForSelectedDate as $promo): ?>
                        <div class="zen-event-item" style="background: #FFF7ED; border-left: 3px solid #EA580C;">
                            <span style="font-size: 0.95rem;">🔥</span>
                            <div style="flex: 1; line-height: 1.4;">
                                <strong style="color: #9A3412;"><?= htmlspecialchars($promo['badge_text'] ?: $promo['title'], ENT_QUOTES, 'UTF-8') ?>:</strong>
                                <span style="color: #7C2D12; font-weight: 600;"><?= htmlspecialchars($promo['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if (!empty($promo['description'])): ?>
                                    <div style="font-size: 0.72rem; color: #9A3412; opacity: 0.9; margin-top: 1px;">
                                        <?= htmlspecialchars($promo['description'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Lịch ngày Mùng 1 & Rằm sắp tới (Issue #19) -->
                <?php if (!empty($upcomingVeganDays)): ?>
                    <?php foreach ($upcomingVeganDays as $uvd): ?>
                        <div class="zen-event-item" style="background: <?= $uvd['is_full_moon'] ? '#FFFBEB' : '#F8FAFC' ?>; border-left: 3px solid <?= $uvd['is_full_moon'] ? '#F59E0B' : '#475569' ?>;">
                            <span style="font-size: 0.9rem;"><?= $uvd['is_full_moon'] ? '🌕' : '🌑' ?></span>
                            <div style="flex: 1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 4px;">
                                <span>
                                    <strong><?= htmlspecialchars($uvd['badge']['label'] ?? $uvd['lunar_text'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    (<?= date('d/m', strtotime($uvd['solar_date'])) ?> Dương lịch)
                                    <?php if (!empty($uvd['special_event'])): ?>
                                        • <em><?= htmlspecialchars($uvd['special_event'], ENT_QUOTES, 'UTF-8') ?></em>
                                    <?php endif; ?>
                                </span>
                                <span style="font-size: 0.7rem; color: var(--accent-wood); font-weight: 600;">
                                    <?= $uvd['days_away'] === 0 ? 'Hôm nay' : ($uvd['days_away'] === 1 ? 'Ngày mai' : 'Còn ' . $uvd['days_away'] . ' ngày') ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Thẻ Nhắc Nhở Lịch Hẹn Sắp Tới Cá Nhân Hóa (Issue #14) -->
        <section id="upcomingBookingBanner" class="hidden" style="background: #ECFDF5; border: 1.5px solid #10B981; border-radius: var(--radius-input); padding: 12px 14px; margin-top: -6px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                <div style="display: flex; gap: 8px; align-items: center;">
                    <span style="font-size: 1.25rem;">🌿</span>
                    <div>
                        <div style="font-weight: 700; font-size: 0.8rem; color: #065F46;">
                            Nhắc bạn lịch hẹn sắp tới
                        </div>
                        <div id="upcomingBookingText" style="font-size: 0.74rem; color: #047857; line-height: 1.4;">
                            <!-- JS tự động điền -->
                        </div>
                    </div>
                </div>
                <button type="button" id="btnViewUpcoming" style="background: #059669; color: #FFFFFF; border: none; border-radius: 6px; padding: 5px 9px; font-size: 0.72rem; font-weight: 600; cursor: pointer; white-space: nowrap;">
                    Xem đơn ↗
                </button>
            </div>
        </section>

        <?php if ($shopClosedStatusToday['is_closed']): ?>
            <!-- Thông báo Bếp Tạm Nghỉ / Đi Vắng (Issue #12) -->
            <section style="background-color: #FDF6EE; border: 1.5px solid #EBD5BE; border-radius: var(--radius-card); padding: 14px 16px; display: flex; gap: 12px; align-items: flex-start;" aria-label="Thông báo tạm nghỉ">
                <span style="font-size: 1.4rem; line-height: 1;">🍃</span>
                <div style="flex: 1;">
                    <p style="font-weight: 700; color: #8C4A26; font-size: 0.9rem; margin-bottom: 4px;">
                        Bếp An Yên Tạm Nghỉ & Đi Vắng
                    </p>
                    <p style="font-size: 0.8rem; color: #5C4033; line-height: 1.45; margin-bottom: 6px;">
                        <?= nl2br(htmlspecialchars($shopClosedStatusToday['reason'], ENT_QUOTES, 'UTF-8')) ?>
                    </p>
                    <?php if (!empty($shopClosedStatusToday['from_date']) || !empty($shopClosedStatusToday['to_date'])): ?>
                        <p style="font-size: 0.72rem; color: #8C6A50; margin-bottom: 6px;">
                            🗓️ Lịch nghỉ: 
                            <?php if (!empty($shopClosedStatusToday['from_date']) && !empty($shopClosedStatusToday['to_date'])): ?>
                                từ <strong><?= date('d/m/Y', strtotime($shopClosedStatusToday['from_date'])) ?></strong> đến <strong><?= date('d/m/Y', strtotime($shopClosedStatusToday['to_date'])) ?></strong>
                            <?php elseif (!empty($shopClosedStatusToday['from_date'])): ?>
                                từ ngày <strong><?= date('d/m/Y', strtotime($shopClosedStatusToday['from_date'])) ?></strong>
                            <?php else: ?>
                                đến hết ngày <strong><?= date('d/m/Y', strtotime($shopClosedStatusToday['to_date'])) ?></strong>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <div>
                        <a href="<?= htmlspecialchars($sellerZaloUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem; font-weight: 600; color: #0068FF; background: #FFFFFF; border: 1px solid #BFDBFE; padding: 5px 12px; border-radius: 999px; text-decoration: none;">
                            <span>💬 Nhắn tin trực tiếp với chủ bếp qua Zalo</span> ↗
                        </a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

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
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <h2 class="zen-label" style="margin-bottom: 0;">
                        <span class="badge-step">2</span> Chọn ngày & Khung giờ phục vụ
                    </h2>
                    <span style="font-size: 0.72rem; color: var(--accent-wood);">
                        Âm lịch: <strong><?= htmlspecialchars($selectedDateLunar['lunar_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                    </span>
                </div>

                <!-- Thanh Lịch Ngang Trượt (Horizontal Date Strip - Issue #19 & #20) -->
                <?php if (count($availableDates) > 1): ?>
                    <div class="date-strip-container" role="tablist" aria-label="Chọn ngày phục vụ">
                        <?php foreach ($availableDates as $d): 
                            $lunar = $datesWithLunar[$d] ?? null;
                            $ts = strtotime($d);
                            $dow = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][(int)date('w', $ts)];
                            if ($d === $today) {
                                $dow = 'Hôm nay';
                            } elseif ($d === date('Y-m-d', strtotime('+1 day'))) {
                                $dow = 'Ngày mai';
                            }
                            $isActive = ($d === $selectedDate);
                            $hasPromoOnDate = !empty($datesWithPromos[$d]);
                        ?>
                            <a href="index.php?date=<?= urlencode($d) ?>" 
                               class="date-chip <?= $isActive ? 'is-active' : '' ?>"
                               role="tab" 
                               aria-selected="<?= $isActive ? 'true' : 'false' ?>">
                                <span class="date-chip-dow"><?= $dow ?></span>
                                <span class="date-chip-solar"><?= date('d/m', $ts) ?></span>
                                <span class="date-chip-lunar"><?= $lunar ? $lunar['lunar_text'] : '' ?></span>
                                <?php if ($lunar && !empty($lunar['badge'])): ?>
                                    <span class="badge-lunar-chip <?= htmlspecialchars($lunar['badge']['class'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($lunar['badge']['icon'] . ' ' . ($lunar['is_first_day'] ? 'M1' : ($lunar['is_full_moon'] ? 'Rằm' : 'Lễ')), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($hasPromoOnDate): ?>
                                    <span class="badge-lunar-chip" style="background: #FFEDD5; color: #C2410C; border: 1px solid #FDBA74;">
                                        🔥 KM
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Select ẩn để giữ tương thích form -->
                <select id="dateSelector" name="order_date" class="hidden">
                    <?php foreach ($availableDates as $d): ?>
                        <option value="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?>" <?= $d === $selectedDate ? 'selected' : '' ?>>
                            <?= date('d/m/Y', strtotime($d)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="slotContainer" class="zen-radio-group">
                    <?php if (empty($slots)): ?>
                        <div style="grid-column: span 2; text-align: center; padding: 18px 12px; background: #FAF7F2; border-radius: 12px;">
                            <?php if ($shopClosedStatusToday['is_closed']): ?>
                                <p style="font-size: 0.85rem; font-weight: 600; color: #8C4A26; margin-bottom: 3px;">🍃 Bếp đang trong thời gian tạm nghỉ</p>
                                <p style="font-size: 0.75rem; color: #5C4033; line-height: 1.4;">Bếp tạm dừng nhận đặt trước qua web vào thời gian này. Xin bạn thương ghé lại sau hoặc nhắn trực tiếp qua Zalo với chủ bếp nhé.</p>
                            <?php else: ?>
                                <p style="font-size: 0.82rem; color: var(--text-muted-warm);">Hôm nay bếp chưa mở ca nhận đặt, bạn thương ghé lại sau nhé.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php 
                        $hasPastCutoff = false;
                        $firstChecked = false;
                        foreach ($slots as $slot): 
                            $isFull = (bool)($slot['is_full'] ?? false);
                            $isPastSlot = (bool)($slot['is_past_slot'] ?? false);
                            $isPastCutoff = (bool)($slot['is_past_cutoff'] ?? false);
                            $isExceeded3M = (bool)($slot['is_exceeded_3m'] ?? false);
                            $isUnavailable = ($isFull || $isPastCutoff);

                            if ($isPastCutoff) {
                                $hasPastCutoff = true;
                            }

                            $statusText = 'Còn ' . (int)$slot['remaining_capacity'] . ' chỗ';
                            if ($isPastSlot) {
                                $statusText = 'Đã qua giờ';
                            } elseif ($isExceeded3M) {
                                $statusText = 'Quá giờ đặt (>3p)';
                            } elseif ($isPastCutoff) {
                                $statusText = 'Quá giờ đặt (trước ' . (int)($slot['cutoff_hours'] ?? $minLeadTimeHours) . 'h)';
                            } elseif ($isFull) {
                                $statusText = 'Đã hết suất';
                            }

                            $shouldCheck = (!$firstChecked && !$isUnavailable);
                            if ($shouldCheck) {
                                $firstChecked = true;
                            }
                        ?>
                            <label class="zen-option <?= $shouldCheck ? 'is-checked' : '' ?> <?= $isUnavailable ? 'is-disabled' : '' ?>"
                                   data-is-overdue="<?= ($isPastCutoff || $isPastSlot) ? '1' : '0' ?>"
                                   data-is-exceeded-3m="<?= $isExceeded3M ? '1' : '0' ?>"
                                   data-slot-time="<?= htmlspecialchars($slot['slot_time'], ENT_QUOTES, 'UTF-8') ?>"
                                   data-slot-date="<?= htmlspecialchars($slot['slot_date'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="radio" name="slot_id" value="<?= (int)$slot['id'] ?>" <?= $shouldCheck ? 'checked' : '' ?> <?= $isUnavailable ? 'disabled' : '' ?>>
                                <p style="font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars($slot['slot_time'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p style="font-size: 0.7rem; opacity: 0.8; margin-top: 2px;">
                                    <?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Thông báo lịch sự khi vượt quá thời gian đặt lịch hoặc cần đặt gấp (Issue #29) -->
                <div id="urgentZaloNote" style="display: <?= !empty($hasPastCutoff) ? 'block' : 'none' ?>; background: #FAF7F2; border: 1.5px dashed var(--brand-border-soft); border-radius: 14px; padding: 12px 14px; margin-top: 12px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                        <div style="font-size: 0.76rem; color: var(--text-charcoal); line-height: 1.5; flex: 1;">
                            🌿 <strong>Thông báo từ Bếp:</strong> Khung giờ cần đặt trước <?= $minLeadTimeHours ?>h để bếp kịp ninh nước dùng tươi mới. Nếu thời gian thực tế đã vượt quá giờ đặt lịch hơn 3 phút, bạn thương vui lòng <strong>lựa chọn đặt vào ca tiếp theo</strong> hoặc <strong>liên hệ trực tiếp với chủ bếp</strong> qua Zalo/Hotline để được hỗ trợ nhé.
                        </div>
                        <?php if (!empty($sellerZaloUrl)): ?>
                            <a href="<?= htmlspecialchars($sellerZaloUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" 
                               style="flex-shrink: 0; background: #0068FF; color: #FFFFFF; font-size: 0.72rem; padding: 7px 12px; border-radius: 8px; text-decoration: none; font-weight: 600; white-space: nowrap; box-shadow: 0 1px 4px rgba(0, 104, 255, 0.2);">
                                💬 Nhắn Zalo Bếp ↗
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- BƯỚC 3: Chọn món thanh tịnh tinh tuyển (Issue #17, #19 & #20) -->
            <section class="zen-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
                    <h2 class="zen-label" style="margin-bottom: 0;">
                        <span class="badge-step">3</span> Món lành hôm nay
                    </h2>
                    <span id="menuSuiteBadge" style="font-size: 0.72rem; background: #EEF2E6; color: var(--brand-tea-green); padding: 3px 8px; border-radius: 6px; font-weight: 600;">
                        🍱 <?= htmlspecialchars($scheduledSuite['name'] ?? 'Thực đơn Bếp An Yên', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <!-- Category Pills (Bộ lọc nhóm món tiện lợi - Issue #19 & #20) -->
                <div class="category-pills-bar" role="tablist" aria-label="Lọc nhóm món">
                    <button type="button" class="category-pill is-active" data-category="all">✨ Tất cả món</button>
                    <button type="button" class="category-pill" data-category="food">🍜 Món chính</button>
                    <button type="button" class="category-pill" data-category="drink">🍵 Đồ uống</button>
                    <button type="button" class="category-pill" data-category="feast">🍱 Mâm cỗ</button>
                    <button type="button" class="category-pill" data-category="promo" style="<?= $hasAnyPromoInMenu ? 'display: inline-block;' : 'display: none;' ?>" id="promoCategoryPill">🔥 Khuyến mại</button>
                </div>
                
                <div id="menuItemsContainer" style="display: flex; flex-direction: column;">
                    <?php if (empty($menuItems)): ?>
                        <div style="text-align: center; color: var(--text-muted-warm); padding: 24px 0; font-size: 0.85rem;">
                            🍃 Hôm nay bếp đang chuẩn bị thực đơn mới, mong bạn quay lại sau ít phút nhé.
                        </div>
                    <?php else: ?>
                        <?php foreach ($menuItems as $idx => $item): 
                            $defaultQty = ($idx === 0) ? 1 : 0;
                            $isCombo = !empty($item['is_feast_combo']);
                            $scope = $item['service_scope'] ?? 'both';
                            $hasPromo = !empty($item['has_promo']);
                            $finalPrice = (int)($item['final_price'] ?? $item['price']);
                            $origPrice = (int)($item['original_price'] ?? $item['price']);
                        ?>
                            <div class="menu-item-row" 
                                 data-price="<?= $finalPrice ?>" 
                                 data-item-id="<?= (int)$item['id'] ?>"
                                 data-category="<?= htmlspecialchars($item['category'] ?? 'food', ENT_QUOTES, 'UTF-8') ?>"
                                 data-scope="<?= htmlspecialchars($scope, ENT_QUOTES, 'UTF-8') ?>"
                                 data-combo="<?= $isCombo ? '1' : '0' ?>"
                                 data-has-promo="<?= $hasPromo ? '1' : '0' ?>"
                                 style="<?= $isCombo ? 'background: #FAF7F2; border-radius: 12px; padding: 10px 12px; margin-bottom: 8px;' : '' ?>">
                                <div class="menu-item-info">
                                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                                        <h3 class="menu-item-title"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                        <?php if (!empty($item['promo_badge'])): ?>
                                            <span style="font-size: 0.65rem; background: #FFEDD5; color: #C2410C; border: 1px solid #FDBA74; padding: 1px 6px; border-radius: 4px; font-weight: 700;">
                                                <?= htmlspecialchars($item['promo_badge'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($isCombo): ?>
                                            <span style="font-size: 0.65rem; background: #FEF3C7; color: #92400E; padding: 2px 6px; border-radius: 4px; font-weight: 700;">
                                                🍱 Mâm cỗ trọn vị
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($scope === 'dine_in_only'): ?>
                                            <span style="font-size: 0.62rem; background: #E0E7FF; color: #3730A3; padding: 1px 5px; border-radius: 4px;">
                                                🍵 Chỉ tại quán
                                            </span>
                                        <?php elseif ($scope === 'takeaway_only'): ?>
                                            <span style="font-size: 0.62rem; background: #FEF2F2; color: #991B1B; padding: 1px 5px; border-radius: 4px;">
                                                🥡 Chỉ mang về
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="menu-item-desc"><?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <div style="display: flex; align-items: baseline; gap: 6px;">
                                        <?php if ($hasPromo): ?>
                                            <span class="menu-item-price" style="color: #EA580C; font-weight: 700;">
                                                <?= number_format($finalPrice, 0, ',', '.') ?>đ
                                            </span>
                                            <del style="font-size: 0.75rem; color: var(--text-muted-warm);">
                                                <?= number_format($origPrice, 0, ',', '.') ?>đ
                                            </del>
                                        <?php else: ?>
                                            <span class="menu-item-price"><?= number_format($finalPrice, 0, ',', '.') ?>đ</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="qty-control">
                                    <button type="button" class="qty-btn qty-minus" aria-label="Giảm số lượng">-</button>
                                    <input type="text" name="items[<?= (int)$item['id'] ?>]" value="<?= $defaultQty ?>" class="qty-input" readonly>
                                    <button type="button" class="qty-btn qty-plus" aria-label="Tăng số lượng">+</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
            </section>

            <!-- BƯỚC 4: Thông tin & Thanh toán linh hoạt -->
            <section class="zen-card" id="step4CustomerInfo" style="display: flex; flex-direction: column; gap: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="zen-label" style="margin-bottom: 0;">
                        <span class="badge-step">4</span> Người thương gửi đơn
                    </h2>
                </div>

                <!-- Nút Đăng nhập Zalo (Tuỳ chọn) -->
                <div id="zaloAuthContainer">
                    <?php if (!empty($zaloLoginError)): ?>
                        <div style="background: #FEE2E2; border: 1px solid #FECACA; color: #DC2626; padding: 8px 12px; border-radius: 8px; font-size: 0.8rem; margin-bottom: 8px; line-height: 1.4;">
                            ⚠️ <?= htmlspecialchars($zaloLoginError, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    <div id="zaloLoginSection">
                        <a href="api/public/zalo-login" id="zaloLoginBtn" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 11px 16px; border-radius: var(--radius-input); background: #0068FF; color: #FFFFFF; text-decoration: none; font-size: 0.85rem; font-weight: 600; box-shadow: 0 2px 8px rgba(0, 104, 255, 0.2); transition: all 0.2s;">
                            <svg width="18" height="18" viewBox="0 0 48 48" fill="none"><path fill="#fff" d="M24 4C12.95 4 4 12.95 4 24c0 3.73 1.02 7.22 2.79 10.22L4 44l10.15-2.67C17.06 43.04 20.44 44 24 44c11.05 0 20-8.95 20-20S35.05 4 24 4z"/></svg>
                            <span>Đăng nhập Zalo để tự động điền</span>
                        </a>
                        <?php if ($requireZaloLogin): ?>
                            <p style="font-size: 0.72rem; color: #DC2626; margin-top: 4px; text-align: center;">
                                * Quán yêu cầu kết nối Zalo để gửi xác nhận đơn hàng
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Hiển thị tài khoản Zalo đã kết nối -->
                    <div id="zaloProfileSection" class="hidden" style="background: #F0F7FF; border: 1px solid #BFDBFE; border-radius: var(--radius-input); padding: 10px 14px; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div id="zaloAvatarWrapper" style="width: 38px; height: 38px; border-radius: 50%; overflow: hidden; background: #0068FF; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; flex-shrink: 0;">
                                Z
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; font-weight: 700; color: #1E40AF; display: flex; align-items: center; gap: 4px;">
                                    <span id="zaloProfileName">Khách Zalo</span>
                                    <span style="font-size: 0.65rem; background: #DBEAFE; color: #1E40AF; padding: 2px 6px; border-radius: 6px; font-weight: normal;">Đã nối Zalo</span>
                                </div>
                                <div id="zaloProfilePhone" style="font-size: 0.75rem; color: #4B5563;"></div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <button type="button" id="btnOpenMyBookings" style="background: #DBEAFE; border: 1px solid #BFDBFE; color: #1E40AF; font-size: 0.72rem; border-radius: 6px; padding: 4px 8px; font-weight: 600; cursor: pointer; white-space: nowrap;">
                                📅 Đơn của tôi
                            </button>
                            <button type="button" id="zaloLogoutBtn" style="background: none; border: none; color: #6B7280; font-size: 0.75rem; cursor: pointer; text-decoration: underline; padding: 4px;">
                                Đổi
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Lưu Zalo ID và Avatar ẩn -->
                <input type="hidden" name="zalo_id" id="custZaloId" value="">
                <input type="hidden" name="avatar_url" id="custAvatarUrl" value="">

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
            <?php if ($isBookingDisabled): ?>
                <button type="button" id="submitBtn" class="zen-btn-primary" disabled style="opacity: 0.65; cursor: not-allowed; background: var(--text-muted-warm); box-shadow: none;">
                    <span><?= $shopClosedStatusToday['is_closed'] ? 'Bếp Đang Tạm Nghỉ' : 'Bếp Chưa Mở Ca Đặt' ?></span>
                </button>
            <?php else: ?>
                <button type="submit" id="submitBtn" class="zen-btn-primary">
                    <span>Gửi Đặt Bếp An Yên</span>
                    <span id="btnTotalText"></span>
                </button>
            <?php endif; ?>

            <?php if (!empty($orderNoteShipping)): ?>
                <p style="font-size: 0.75rem; color: var(--accent-wood); text-align: center; font-style: italic; margin-top: -6px;">
                    * <?= htmlspecialchars($orderNoteShipping, ENT_QUOTES, 'UTF-8') ?> *
                </p>
            <?php endif; ?>
        </form>

        <!-- MODAL: Lịch Đặt Của Tôi (Issue #14) -->
        <div id="myBookingsModal" class="hidden" style="position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 999; display: flex; align-items: flex-end; justify-content: center;">
            <div style="background: #FAF7F2; width: 100%; max-width: 448px; max-height: 85vh; border-radius: 24px 24px 0 0; padding: 20px 18px 30px 18px; display: flex; flex-direction: column; box-shadow: 0 -4px 20px rgba(0,0,0,0.15); animation: slideUp 0.25s ease-out;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--brand-border-soft); padding-bottom: 12px; margin-bottom: 14px;">
                    <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--brand-tea-dark); display: flex; align-items: center; gap: 6px; margin: 0;">
                        <span>🌿</span> Lịch Đặt Của Tôi
                    </h3>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button type="button" id="btnRefreshMyBookings" title="Làm mới trạng thái đơn hàng" style="background: #F3F4F6; border: 1px solid var(--brand-border-soft); font-size: 0.74rem; color: var(--brand-tea-dark); cursor: pointer; padding: 4px 10px; border-radius: 999px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                            <span>🔄</span> Làm mới
                        </button>
                        <button type="button" id="btnCloseMyBookings" style="background: none; border: none; font-size: 1.3rem; color: var(--text-muted-warm); cursor: pointer; padding: 4px 8px;">✕</button>
                    </div>
                </div>
                <div id="myBookingsList" style="overflow-y: auto; display: flex; flex-direction: column; gap: 12px; flex: 1;">
                    <p style="text-align: center; color: var(--text-muted-warm); font-size: 0.82rem; padding: 20px 0;">Đang tải lịch đặt...</p>
                </div>
            </div>
        </div>

        <footer style="text-align: center; font-size: 0.72rem; color: var(--text-muted-warm); margin-top: 10px; display: flex; flex-direction: column; gap: 6px; align-items: center;">
            <div style="display: flex; justify-content: center; gap: 12px; margin-bottom: 2px;">
                <a href="about.php" style="color: var(--brand-tea-green); text-decoration: none; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px;">
                    <span>🌿</span> Về Bếp An Yên
                </a>
                <span>•</span>
                <a href="reviews.php" style="color: var(--brand-tea-green); text-decoration: none; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px;">
                    <span>🌸</span> Góc Cảm Nhận & Kỷ Niệm
                </a>
            </div>
            <p style="margin: 0;">Bếp Chay An Yên • Bình an trong từng bát bún phở</p>
        </footer>

        <!-- Floating Bottom Summary Bar (Thanh tóm tắt đơn nổi ở đáy mobile - Issue #19) -->
        <div id="floatingBottomBar" class="floating-bottom-bar is-hidden">
            <div class="floating-bottom-content">
                <div class="floating-summary-text">
                    <span id="floatingQtyText" class="floating-summary-qty">0 món đã chọn</span>
                    <span id="floatingPriceText" class="floating-summary-price">0đ</span>
                </div>
                <button type="button" id="btnFloatingView" class="btn-floating-view">
                    <span>Xem đơn & Đặt</span> <span>➔</span>
                </button>
            </div>
        </div>

    </main>

    <!-- Client Script & Config -->
    <script>
        window.REQUIRE_ZALO_LOGIN = <?= $requireZaloLogin ? 'true' : 'false' ?>;
        window.SELLER_PHONE = <?= json_encode($sellerPhone, JSON_UNESCAPED_UNICODE) ?>;
        window.SELLER_ZALO_URL = <?= json_encode($sellerZaloUrl, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
