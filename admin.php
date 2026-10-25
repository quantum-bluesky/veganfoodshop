<?php
declare(strict_types=1);

/**
 * Trang Quản Trị Bếp An Yên - admin.php
 * Phong cách Zen tối giản, gọn gàng, thao tác mượt mà trên Mobile & Desktop
 */

session_start();
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/src/autoload.php';

use App\Services\ConfigService;
use App\Services\CustomerService;
use App\Services\MenuService;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\SlotService;

$configService = new ConfigService();
$customerService = new CustomerService();
$menuService = new MenuService();
$notificationService = new NotificationService();
$orderService = new OrderService();
$slotService = new SlotService();

$config = require __DIR__ . '/config/config.php';
$defaultPassword = $config['admin']['default_password'] ?? 'AnYen@2026';
$sessionKey = $config['admin']['session_key'] ?? 'bep_an_yen_admin_auth';

// Xử lý Đăng xuất
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION[$sessionKey]);
    header('Location: admin.php');
    exit;
}

// Xử lý Đăng nhập
$loginError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $password = (string)($_POST['password'] ?? '');
    if ($password === $defaultPassword) {
        $_SESSION[$sessionKey] = true;
        header('Location: admin.php');
        exit;
    } else {
        $loginError = 'Mật khẩu quản trị chưa chính xác.';
    }
}

// Kiểm tra quyền đăng nhập
$isAuthenticated = !empty($_SESSION[$sessionKey]);

if (!$isAuthenticated) {
    // Hiển thị màn hình đăng nhập
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Quản trị Bếp Chay An Yên</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,500;1,400&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body style="display: flex; align-items: center; justify-content: center; min-height: 100vh;">
        <div class="zen-card" style="width: 100%; max-width: 360px; text-align: center; margin: 20px;">
            <div style="font-size: 2.2rem; margin-bottom: 8px;">🏮</div>
            <h1 class="font-serif-zen" style="font-size: 1.4rem; color: var(--brand-tea-dark); margin-bottom: 4px;">Quản Trị Bếp An Yên</h1>
            <p style="font-size: 0.78rem; color: var(--text-muted-warm); margin-bottom: 20px;">Nhập mật khẩu nội bộ để vào bếp</p>
            
            <?php if ($loginError): ?>
                <div style="background: #FEE2E2; color: #991B1B; padding: 10px; border-radius: 12px; font-size: 0.8rem; margin-bottom: 14px;">
                    <?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" style="display: flex; flex-direction: column; gap: 12px;">
                <input type="hidden" name="action" value="login">
                <input type="password" name="password" class="zen-input" placeholder="Mật khẩu quản trị" required autofocus>
                <button type="submit" class="zen-btn-primary" style="padding: 12px;">Đăng Nhập</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Xử lý các thao tác POST nghiệp vụ của Admin
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['admin_action'] ?? '');

    switch ($action) {
        // 1. Quản lý ca
        case 'create_slot':
            $slotDate = trim((string)$_POST['slot_date']);
            $slotTime = trim((string)$_POST['slot_time']);
            $capacity = (int)($_POST['max_capacity'] ?? 12);
            if ($slotDate && $slotTime) {
                $slotService->createSlot($slotDate, $slotTime, $capacity);
                $message = 'Đã tạo khung giờ phục vụ mới thành công.';
            }
            break;

        case 'toggle_slot':
            $slotId = (int)$_POST['slot_id'];
            $isActive = (int)$_POST['is_active'] === 1;
            $slotService->toggleSlotActive($slotId, $isActive);
            $message = 'Đã cập nhật trạng thái ca.';
            break;

        // 2. Quản lý trạng thái đơn hàng
        case 'update_order_status':
            $orderId = (int)$_POST['order_id'];
            $status = (string)$_POST['order_status'];
            $orderService->updateOrderStatus($orderId, $status);
            $message = 'Đã cập nhật trạng thái đơn hàng.';
            break;

        case 'update_payment_status':
            $orderId = (int)$_POST['order_id'];
            $status = (string)$_POST['payment_status'];
            $orderService->updatePaymentStatus($orderId, $status);
            $message = 'Đã cập nhật trạng thái thanh toán.';
            break;

        // 3. Quản lý khách quen
        case 'toggle_trusted':
            $phone = (string)$_POST['phone'];
            $isTrusted = (int)$_POST['is_trusted'] === 1;
            $customerService->setTrustedStatus($phone, $isTrusted);
            $message = 'Đã cập nhật trạng thái khách quen cho số ' . $phone;
            break;

        case 'save_customer':
            $phone = (string)$_POST['phone'];
            $name = (string)$_POST['name'];
            $note = (string)($_POST['note'] ?? '');
            $customerService->saveOrUpdate($phone, $name, $note);
            $message = 'Đã lưu thông tin khách hàng.';
            break;

        // 4. Quản lý thực đơn
        case 'toggle_menu':
            $itemId = (int)$_POST['item_id'];
            $isAvail = (int)$_POST['is_available'] === 1;
            $menuService->toggleAvailability($itemId, $isAvail);
            $message = 'Đã cập nhật món ăn.';
            break;

        case 'update_price':
            $itemId = (int)$_POST['item_id'];
            $price = (int)$_POST['price'];
            $menuService->updatePrice($itemId, $price);
            $message = 'Đã đổi đơn giá món.';
            break;

        case 'create_menu_item':
            $name = trim((string)$_POST['name']);
            $desc = trim((string)$_POST['description']);
            $price = (int)$_POST['price'];
            $cat = (string)$_POST['category'];
            if ($name && $price >= 0) {
                $menuService->createItem($name, $desc, $price, $cat);
                $message = 'Đã thêm món mới vào thực đơn.';
            }
            break;

        // 5. Cập nhật cấu hình hệ thống
        case 'update_config':
            $shopName = (string)$_POST['shop_name'];
            $shopQuote = (string)$_POST['shop_quote'];
            $noticeText = (string)$_POST['notice_text'];
            $bankId = (string)$_POST['bank_id'];
            $bankName = (string)$_POST['bank_name'];
            $accNo = (string)$_POST['account_no'];
            $accName = (string)$_POST['account_name'];

            // Cấu hình đặt chỗ & thời gian bếp
            $minLeadTime = max(0, (int)($_POST['min_lead_time_hours'] ?? 3));
            $advanceBookingDays = max(1, (int)($_POST['advance_booking_days'] ?? 7));

            // Chế độ tạm nghỉ & đi vắng (Issue #12)
            $isShopClosed = isset($_POST['is_shop_closed']) ? '1' : '0';
            $closedFromDate = trim((string)($_POST['closed_from_date'] ?? ''));
            $closedToDate = trim((string)($_POST['closed_to_date'] ?? ''));
            $closedReason = trim((string)($_POST['closed_reason'] ?? ''));

            // Zalo Config
            $zaloAppId = (string)($_POST['zalo_app_id'] ?? '123');
            $zaloSecretKey = (string)($_POST['zalo_secret_key'] ?? '123');
            $zaloMockMode = isset($_POST['zalo_mock_test_mode']) ? '1' : '0';
            $zaloRequireLogin = isset($_POST['zalo_require_login']) ? '1' : '0';
            $zaloRequireGroup = isset($_POST['zalo_require_group']) ? '1' : '0';
            $zaloSellerUrl = (string)($_POST['zalo_seller_url'] ?? 'https://zalo.me/0987862241');
            $zaloCallbackUrl = trim((string)($_POST['zalo_callback_url'] ?? ''));
            $orderNoteShipping = (string)($_POST['order_note_shipping'] ?? 'Chưa bao gồm chi phí vận chuyển');
            $zaloConfirmTemplate = (string)($_POST['zalo_confirm_template'] ?? 'Chào Bếp, mình vừa đặt đơn trên web. Mã đơn: {order_id}. Nhờ bếp kiểm tra và xác nhận giúp mình nhé!');

            $configService->set('shop_name', $shopName);
            $configService->set('shop_quote', $shopQuote);
            $configService->set('notice_text', $noticeText);
            $configService->set('bank_id', $bankId);
            $configService->set('bank_name', $bankName);
            $configService->set('account_no', $accNo);
            $configService->set('account_name', $accName);

            $configService->set('min_lead_time_hours', (string)$minLeadTime);
            $configService->set('advance_booking_days', (string)$advanceBookingDays);

            $configService->set('is_shop_closed', $isShopClosed);
            $configService->set('closed_from_date', $closedFromDate);
            $configService->set('closed_to_date', $closedToDate);
            $configService->set('closed_reason', $closedReason);

            $configService->set('zalo_app_id', $zaloAppId);
            $configService->set('zalo_secret_key', $zaloSecretKey);
            $configService->set('zalo_mock_test_mode', $zaloMockMode);
            $configService->set('zalo_require_login', $zaloRequireLogin);
            $configService->set('zalo_require_group', $zaloRequireGroup);
            $configService->set('zalo_seller_url', $zaloSellerUrl);
            $configService->set('zalo_callback_url', $zaloCallbackUrl);
            $configService->set('order_note_shipping', $orderNoteShipping);
            $configService->set('zalo_confirm_template', $zaloConfirmTemplate);

            // Cấu hình Email Thông Báo & SMTP (Issue #13)
            $notifEmailTo = trim((string)($_POST['notification_email_to'] ?? ''));
            $smtpHost = trim((string)($_POST['smtp_host'] ?? ''));
            $smtpPort = trim((string)($_POST['smtp_port'] ?? '587'));
            $smtpUsername = trim((string)($_POST['smtp_username'] ?? ''));
            $smtpPassword = (string)($_POST['smtp_password'] ?? '');
            $smtpEncryption = trim((string)($_POST['smtp_encryption'] ?? 'tls'));
            $smtpFromEmail = trim((string)($_POST['smtp_from_email'] ?? ''));
            $smtpFromName = trim((string)($_POST['smtp_from_name'] ?? 'Bếp Chay An Yên'));
            $smtpMockMode = isset($_POST['smtp_mock_mode']) ? '1' : '0';

            $configService->set('notification_email_to', $notifEmailTo);
            $configService->set('smtp_host', $smtpHost);
            $configService->set('smtp_port', $smtpPort);
            $configService->set('smtp_username', $smtpUsername);
            if ($smtpPassword !== '') {
                $configService->set('smtp_password', $smtpPassword);
            }
            $configService->set('smtp_encryption', $smtpEncryption);
            $configService->set('smtp_from_email', $smtpFromEmail);
            $configService->set('smtp_from_name', $smtpFromName);
            $configService->set('smtp_mock_mode', $smtpMockMode);

            $message = 'Đã lưu cấu hình hệ thống, thời gian đặt, Zalo & Email SMTP thành công.';
            break;

        // 6. Xử lý hàng đợi thông báo (Issue #13)
        case 'retry_notification':
            $notifId = (int)$_POST['notification_id'];
            $ok = $notificationService->retryNotification($notifId);
            $message = $ok 
                ? ('Đã gửi lại thành công thông báo #' . $notifId) 
                : ('Đã thử gửi lại thông báo #' . $notifId . ' nhưng gặp lỗi. Bạn kiểm tra cột chi tiết lỗi nhé.');
            break;

        case 'run_notification_worker':
            $processedCount = $notificationService->processQueue(20);
            $message = sprintf('Đã chạy xử lý hàng đợi thông báo thành công (Đã xử lý %d thông báo).', $processedCount);
            break;
    }
}

// Lấy dữ liệu cho các tab
$currentTab = (string)($_GET['tab'] ?? 'slots');
$slotFilter = (string)($_GET['slot_filter'] ?? 'upcoming');
$adminSlots = $slotService->getAllSlotsForAdmin(50, $slotFilter);

// Toàn bộ các ca để phục vụ dropdown chọn xem ca cụ thể
$allUpcomingAndTodaySlots = $slotService->getAllSlotsForAdmin(50, 'all');

// Tab Orders: Chế độ xem bếp & danh sách đơn
$orderScope = (string)($_GET['scope'] ?? '');
if ($orderScope === '' && !isset($_GET['slot_id'])) {
    $orderScope = 'today_all';
} elseif (isset($_GET['slot_id'])) {
    $orderScope = 'by_slot';
}

$selectedSlotId = (int)($_GET['slot_id'] ?? 0);
$todayDate = date('Y-m-d');

if ($orderScope === 'today_all') {
    $kitchenSummary = $orderService->getKitchenSummaryByDate($todayDate);
    $slotOrders = $orderService->getOrdersByDate($todayDate);
} elseif ($selectedSlotId > 0) {
    $kitchenSummary = $orderService->getKitchenSummaryBySlot($selectedSlotId);
    $slotOrders = $orderService->getOrdersBySlot($selectedSlotId);
} else {
    $kitchenSummary = $orderService->getKitchenSummaryByDate($todayDate);
    $slotOrders = $orderService->getOrdersByDate($todayDate);
}

// Tab Customers
$customerSearch = (string)($_GET['search_cust'] ?? '');
$customerList = $customerService->getAllCustomers($customerSearch);

// Tab Menu
$menuList = $menuService->getAllMenu();

// Tab Config
$currentConfig = $configService->getAll();

// Tab Notifications (Issue #13)
$notifChannelFilter = !empty($_GET['notif_channel']) ? (string)$_GET['notif_channel'] : null;
$notifStatusFilter = !empty($_GET['notif_status']) ? (string)$_GET['notif_status'] : null;
$notifList = $notificationService->getNotificationsForAdmin(60, $notifChannelFilter, $notifStatusFilter);
$notifStats = $notificationService->getStats();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Quản Trị - Bếp Chay An Yên</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,500;1,400&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .admin-layout {
            width: 100%;
            max-width: 900px;
            padding: 24px 16px 60px 16px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .admin-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            border-bottom: 2px solid var(--brand-border-soft);
            padding-bottom: 12px;
        }
        .admin-tab {
            padding: 8px 16px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted-warm);
            background: #FFFFFF;
            border: 1px solid var(--brand-border-soft);
            transition: all 0.2s;
        }
        .admin-tab.active {
            background: var(--brand-tea-green);
            color: #FFFFFF;
            border-color: var(--brand-tea-green);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            text-align: left;
        }
        .data-table th, .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--brand-border-soft);
        }
        .data-table th {
            background-color: var(--brand-sage-light);
            color: var(--brand-tea-dark);
            font-weight: 700;
        }
        .action-btn {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            cursor: pointer;
            border: 1px solid var(--brand-border-soft);
            background: #FFFFFF;
        }
    </style>
</head>
<body>

    <div class="admin-layout">

        <!-- Top Header -->
        <header style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 class="font-serif-zen" style="font-size: 1.5rem; color: var(--brand-tea-dark); margin-bottom: 2px;">
                    Bếp Chay An Yên • Vận Hành
                </h1>
                <p style="font-size: 0.78rem; color: var(--text-muted-warm);">Hệ thống quản lý lịch ca, đơn đặt trước và khách quen</p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="index.php" target="_blank" class="action-btn" style="text-decoration: none;">Xem Trang Khách ↗</a>
                <a href="admin.php?action=logout" class="action-btn" style="color: #DC2626; text-decoration: none;">Đăng Xuất</a>
            </div>
        </header>

        <!-- Thông báo thao tác -->
        <?php if ($message): ?>
            <div style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; padding: 10px 14px; border-radius: 12px; font-size: 0.82rem;">
                🌿 <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php 
        $adminShopClosed = $configService->getShopClosedStatus(); 
        if ($adminShopClosed['is_closed']): 
        ?>
            <!-- Cảnh báo Bếp Tạm Nghỉ (Issue #12) -->
            <div style="background: #FEF3C7; border: 1.5px solid #F59E0B; color: #92400E; padding: 12px 16px; border-radius: 12px; font-size: 0.85rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 1.3rem;">⚠️</span>
                    <div>
                        <strong style="color: #78350F;">Bếp đang BẬT chế độ TẠM NGHỈ / ĐI VẮNG:</strong> Khách đặt trên web sẽ thấy thông báo tạm nghỉ và không thể gửi đơn.
                        <?php if (!empty($adminShopClosed['from_date']) || !empty($adminShopClosed['to_date'])): ?>
                            <span style="display: inline-block; margin-left: 6px; font-size: 0.78rem; background: #FDE68A; padding: 2px 8px; border-radius: 6px; color: #78350F;">
                                <?= !empty($adminShopClosed['from_date']) ? 'Từ ' . date('d/m/Y', strtotime($adminShopClosed['from_date'])) : '' ?>
                                <?= !empty($adminShopClosed['to_date']) ? ' đến ' . date('d/m/Y', strtotime($adminShopClosed['to_date'])) : '' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="admin.php?tab=config#vacation_settings" class="action-btn" style="text-decoration: none; font-weight: 600; color: #78350F; border-color: #F59E0B; white-space: nowrap;">
                    Cài đặt nghỉ ⚙️
                </a>
            </div>
        <?php endif; ?>

        <!-- Thanh điều hướng Tab -->
        <nav class="admin-nav">
            <a href="admin.php?tab=slots" class="admin-tab <?= $currentTab === 'slots' ? 'active' : '' ?>">⏰ Lịch Ca (Slots)</a>
            <a href="admin.php?tab=orders" class="admin-tab <?= $currentTab === 'orders' ? 'active' : '' ?>">🍲 Bếp & Đơn Hàng</a>
            <a href="admin.php?tab=customers" class="admin-tab <?= $currentTab === 'customers' ? 'active' : '' ?>">🌿 Khách Quen</a>
            <a href="admin.php?tab=menu" class="admin-tab <?= $currentTab === 'menu' ? 'active' : '' ?>">🥢 Thực Đơn</a>
            <a href="admin.php?tab=notifications" class="admin-tab <?= $currentTab === 'notifications' ? 'active' : '' ?>">
                🔔 Thông Báo
                <?php if ($notifStats['pending'] > 0 || $notifStats['failed'] > 0): ?>
                    <span style="background: <?= $notifStats['failed'] > 0 ? '#EF4444' : '#F59E0B' ?>; color: #FFFFFF; font-size: 0.65rem; padding: 2px 6px; border-radius: 999px; margin-left: 4px; font-weight: 700;">
                        <?= $notifStats['pending'] + $notifStats['failed'] ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="admin.php?tab=config" class="admin-tab <?= $currentTab === 'config' ? 'active' : '' ?>">⚙️ Cấu Hình</a>
        </nav>

        <!-- NỘI DUNG TỪNG TAB -->

        <?php if ($currentTab === 'slots'): ?>
            <!-- TAB 1: Quản lý Ca -->
            <div class="zen-card" style="margin-bottom: 16px;">
                <h2 style="font-size: 0.95rem; font-weight: 700; color: var(--brand-tea-green); margin-bottom: 12px;">
                    + Mở Khung Giờ Mới (Weekly Droplist)
                </h2>
                <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 120px; gap: 10px; align-items: flex-end;">
                    <input type="hidden" name="admin_action" value="create_slot">
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Ngày phục vụ:</label>
                        <input type="date" name="slot_date" class="zen-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Khung giờ:</label>
                        <input type="text" name="slot_time" class="zen-input" placeholder="VD: 11:30 - 12:15" required>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Suất tối đa:</label>
                        <input type="number" name="max_capacity" class="zen-input" value="12" min="1" max="50" required>
                    </div>
                    <div>
                        <button type="submit" class="zen-btn-primary" style="padding: 11px 14px; border-radius: 12px; font-size: 0.85rem;">+ Mở Ca</button>
                    </div>
                </form>
            </div>

            <!-- Bộ lọc thời gian ca phục vụ -->
            <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
                <a href="admin.php?tab=slots&slot_filter=upcoming" class="action-btn" style="text-decoration: none; padding: 6px 12px; border-radius: 8px; <?= $slotFilter === 'upcoming' ? 'background: var(--brand-tea-green); color: white;' : '' ?>">
                    🌿 Hôm nay & Sắp tới
                </a>
                <a href="admin.php?tab=slots&slot_filter=today" class="action-btn" style="text-decoration: none; padding: 6px 12px; border-radius: 8px; <?= $slotFilter === 'today' ? 'background: var(--brand-tea-green); color: white;' : '' ?>">
                    ☀️ Chỉ hôm nay
                </a>
                <a href="admin.php?tab=slots&slot_filter=history" class="action-btn" style="text-decoration: none; padding: 6px 12px; border-radius: 8px; <?= $slotFilter === 'history' ? 'background: var(--brand-tea-green); color: white;' : '' ?>">
                    📜 Lịch sử đã qua
                </a>
                <a href="admin.php?tab=slots&slot_filter=all" class="action-btn" style="text-decoration: none; padding: 6px 12px; border-radius: 8px; <?= $slotFilter === 'all' ? 'background: var(--brand-tea-green); color: white;' : '' ?>">
                    Toàn bộ
                </a>
            </div>

            <div class="zen-card" style="padding: 0; overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ngày</th>
                            <th>Khung Giờ</th>
                            <th>Đã Đặt / Tối Đa</th>
                            <th>Còn Lại</th>
                            <th>Trạng Thái</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($adminSlots)): ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--text-muted-warm); padding: 20px;">Không có ca nào trong mục này.</td></tr>
                        <?php else: ?>
                            <?php foreach ($adminSlots as $s): ?>
                                <tr style="<?= !empty($s['is_past']) ? 'opacity: 0.75; background: #FAF9F6;' : '' ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($s['slot_date'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if (!empty($s['is_past'])): ?>
                                            <span style="font-size: 0.65rem; background: #E5E7EB; color: #4B5563; padding: 2px 5px; border-radius: 4px; margin-left: 4px;">Đã qua</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($s['slot_time'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= (int)$s['booked_count'] ?> / <?= (int)$s['max_capacity'] ?> suất</td>
                                    <td>
                                        <?php if ((int)$s['remaining_capacity'] <= 0): ?>
                                            <span style="color: #DC2626; font-weight: 700;">Hết suất</span>
                                        <?php else: ?>
                                            <span style="color: var(--brand-tea-green); font-weight: 700;">Còn <?= (int)$s['remaining_capacity'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($s['is_past'])): ?>
                                            <span style="color:#6B7280; font-size: 0.78rem;">Đã kết thúc</span>
                                        <?php else: ?>
                                            <?= (int)$s['is_active'] === 1 ? '<span style="color:#059669;">Đang mở</span>' : '<span style="color:#9CA3AF;">Đóng</span>' ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="admin_action" value="toggle_slot">
                                            <input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>">
                                            <input type="hidden" name="is_active" value="<?= (int)$s['is_active'] === 1 ? '0' : '1' ?>">
                                            <button type="submit" class="action-btn">
                                                <?= (int)$s['is_active'] === 1 ? 'Đóng ca' : 'Mở lại' ?>
                                            </button>
                                        </form>
                                        <a href="admin.php?tab=orders&scope=by_slot&slot_id=<?= (int)$s['id'] ?>" class="action-btn" style="text-decoration: none; margin-left: 4px;">Xem đơn</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($currentTab === 'orders'): ?>
            <!-- TAB 2: Bếp Chuẩn Bị & Đơn Hàng -->
            <div class="zen-card" style="margin-bottom: 16px;">
                <label style="font-size: 0.8rem; font-weight: 600; color: var(--brand-tea-green); margin-bottom: 6px; display: block;">
                    Chế độ xem Bếp & Đơn Hàng:
                </label>
                <select onchange="window.location.href=this.value" class="zen-input" style="max-width: 480px;">
                    <option value="admin.php?tab=orders&scope=today_all" <?= $orderScope === 'today_all' ? 'selected' : '' ?>>
                        🌟 TỔNG HỢP TOÀN BỘ HÔM NAY (<?= date('d/m/Y') ?>)
                    </option>
                    <optgroup label="Xem chi tiết theo từng ca:">
                        <?php foreach ($allUpcomingAndTodaySlots as $s): ?>
                            <option value="admin.php?tab=orders&scope=by_slot&slot_id=<?= (int)$s['id'] ?>" <?= ($orderScope === 'by_slot' && (int)$s['id'] === $selectedSlotId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['slot_date'] . ' (' . $s['slot_time'] . ')' . (!empty($s['is_past']) ? ' [Đã qua]' : '') . ' - Đặt: ' . $s['booked_count'] . '/' . $s['max_capacity'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>

            <!-- Tổng hợp bếp cần nấu (Kitchen Prep Sheet) -->
            <div class="zen-card" style="background-color: var(--brand-sage-light); margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <h3 style="font-size: 0.92rem; font-weight: 700; color: var(--brand-tea-dark); margin: 0;">
                        🍲 <?= $orderScope === 'today_all' ? 'Bảng Chuẩn Bị Bếp Toàn Bộ Hôm Nay (' . date('d/m/Y') . ')' : 'Bảng Chuẩn Bị Bếp Cho Ca Này' ?>
                    </h3>
                    <span style="font-size: 0.78rem; color: var(--brand-tea-green); font-weight: 700;">
                        Tổng: <?= (int)array_sum(array_column($kitchenSummary, 'total_qty')) ?> suất món
                    </span>
                </div>

                <?php if (empty($kitchenSummary)): ?>
                    <p style="font-size: 0.8rem; color: var(--text-muted-warm); margin-top: 4px;">Chưa có món nào được đặt cho mục này.</p>
                <?php else: ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px;">
                        <?php foreach ($kitchenSummary as $ks): ?>
                            <div style="background: #FFFFFF; padding: 8px 14px; border-radius: 12px; border: 1px solid var(--brand-border-soft); font-size: 0.82rem; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                                <strong><?= htmlspecialchars($ks['name'], ENT_QUOTES, 'UTF-8') ?>:</strong> 
                                <span style="font-size: 1.05rem; color: var(--brand-tea-green); font-weight: 700;"><?= (int)$ks['total_qty'] ?></span> suất
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Danh sách đơn hàng trong ca / ngày -->
            <div class="zen-card" style="padding: 0; overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã Đơn</th>
                            <th>Khung Giờ</th>
                            <th>Khách Hàng & Zalo</th>
                            <th>Hình Thức</th>
                            <th>Chi Tiết Món</th>
                            <th>Tổng Tiền</th>
                            <th>Thanh Toán</th>
                            <th>Trạng Thái Đơn</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($slotOrders)): ?>
                            <tr><td colspan="8" style="text-align: center; color: var(--text-muted-warm); padding: 24px;">Chưa có đơn đặt nào trong mục này.</td></tr>
                        <?php else: ?>
                            <?php foreach ($slotOrders as $o): 
                                $cleanPhone = preg_replace('/[^0-9]/', '', (string)$o['customer_phone']);
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($o['order_code'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <div style="font-size: 0.7rem; color: var(--text-muted-warm);"><?= date('H:i d/m', strtotime($o['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <strong style="color: var(--brand-tea-dark); font-size: 0.82rem;"><?= htmlspecialchars((string)($o['slot_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <div style="font-size: 0.7rem; color: var(--text-muted-warm);"><?= htmlspecialchars((string)($o['slot_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--brand-tea-dark);">
                                            <?= htmlspecialchars($o['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ((int)$o['is_trusted'] === 1): ?>
                                                <span style="font-size: 0.65rem; background: #E6F3E6; color: #2D5A27; padding: 2px 5px; border-radius: 4px; font-weight: normal;">Bạn quen</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.72rem; color: var(--text-muted-warm);"><?= htmlspecialchars($o['customer_phone'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if (!empty($o['customer_note'])): ?>
                                            <div style="font-size: 0.7rem; color: #8C7A6B; font-style: italic; margin-top: 2px;">"<?= htmlspecialchars($o['customer_note'], ENT_QUOTES, 'UTF-8') ?>"</div>
                                        <?php endif; ?>

                                        <!-- Nút liên lạc Zalo trực tiếp với khách (Issue #10) -->
                                        <div style="margin-top: 5px; display: flex; align-items: center; gap: 5px;">
                                            <a href="https://zalo.me/<?= htmlspecialchars($cleanPhone, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" 
                                               style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; background: #0068FF; color: #FFFFFF; font-size: 0.7rem; font-weight: 600; text-decoration: none;">
                                                <svg width="12" height="12" viewBox="0 0 48 48" fill="none"><path fill="#fff" d="M24 4C12.95 4 4 12.95 4 24c0 3.73 1.02 7.22 2.79 10.22L4 44l10.15-2.67C17.06 43.04 20.44 44 24 44c11.05 0 20-8.95 20-20S35.05 4 24 4z"/></svg>
                                                <span>Nhắn Zalo</span>
                                            </a>
                                            <button type="button" class="copy-pill" style="margin-left: 0; padding: 2px 6px; font-size: 0.68rem;" 
                                                    onclick="copyCustomerZaloTemplate('<?= htmlspecialchars(addslashes((string)$o['customer_name']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars((string)$o['order_code'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars((string)($o['slot_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?>')">
                                                Mẫu nhắn
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <?= $o['order_type'] === 'dine_in' ? '🍵 Tại chỗ' : '🥡 Ship về' ?>
                                    </td>
                                    <td>
                                        <?php foreach ($o['items'] as $it): ?>
                                            <div><?= htmlspecialchars($it['item_name'], ENT_QUOTES, 'UTF-8') ?> x<?= (int)$it['quantity'] ?></div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><strong><?= number_format((int)$o['total_amount'], 0, ',', '.') ?>đ</strong></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="admin_action" value="update_payment_status">
                                            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                            <select name="payment_status" onchange="this.form.submit()" style="font-size: 0.75rem; padding: 3px 6px; border-radius: 6px; border: 1px solid var(--brand-border-soft);">
                                                <option value="pending" <?= $o['payment_status'] === 'pending' ? 'selected' : '' ?>>Chờ thanh toán</option>
                                                <option value="paid" <?= $o['payment_status'] === 'paid' ? 'selected' : '' ?>>Đã thanh toán (Paid)</option>
                                                <option value="pay_later" <?= $o['payment_status'] === 'pay_later' ? 'selected' : '' ?>>Trả sau tại bếp</option>
                                                <option value="refunded" <?= $o['payment_status'] === 'refunded' ? 'selected' : '' ?>>Hoàn tiền</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="admin_action" value="update_order_status">
                                            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                            <select name="order_status" onchange="this.form.submit()" style="font-size: 0.75rem; padding: 3px 6px; border-radius: 6px; border: 1px solid var(--brand-border-soft);">
                                                <option value="confirmed" <?= $o['order_status'] === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
                                                <option value="completed" <?= $o['order_status'] === 'completed' ? 'selected' : '' ?>>Đã hoàn thành</option>
                                                <option value="cancelled" <?= $o['order_status'] === 'cancelled' ? 'selected' : '' ?>>Hủy đơn</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($currentTab === 'customers'): ?>
            <!-- TAB 3: Quản lý Khách Quen -->
            <div class="zen-card" style="margin-bottom: 16px;">
                <form method="GET" style="display: flex; gap: 8px;">
                    <input type="hidden" name="tab" value="customers">
                    <input type="text" name="search_cust" class="zen-input" value="<?= htmlspecialchars($customerSearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="Tìm theo tên hoặc số điện thoại...">
                    <button type="submit" class="action-btn" style="padding: 10px 16px; font-weight: 600;">Tìm</button>
                </form>
            </div>

            <div class="zen-card" style="padding: 0; overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Số Điện Thoại</th>
                            <th>Tên Khách Hàng</th>
                            <th>Zalo</th>
                            <th>Khách Quen (Trả Sau)</th>
                            <th>Ghi Chú</th>
                            <th>Ngày Đăng Ký</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customerList as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['phone'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <?php if (!empty($c['avatar_url'])): ?>
                                            <img src="<?= htmlspecialchars($c['avatar_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($c['zalo_id'])): ?>
                                        <span style="font-size: 0.72rem; color: #1E40AF; background: #DBEAFE; padding: 2px 6px; border-radius: 6px; font-weight: 600;">
                                            <?= htmlspecialchars(substr((string)$c['zalo_id'], 0, 10), ENT_QUOTES, 'UTF-8') ?>...
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 0.72rem; color: #9CA3AF;">Chưa liên kết</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="admin_action" value="toggle_trusted">
                                        <input type="hidden" name="phone" value="<?= htmlspecialchars($c['phone'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="is_trusted" value="<?= (int)$c['is_trusted'] === 1 ? '0' : '1' ?>">
                                        <button type="submit" class="action-btn" style="<?= (int)$c['is_trusted'] === 1 ? 'background:#E6F3E6; color:#2D5A27; font-weight:700;' : '' ?>">
                                            <?= (int)$c['is_trusted'] === 1 ? '✓ Khách quen tin cậy' : 'Khách thường' ?>
                                        </button>
                                    </form>
                                </td>
                                <td><span style="font-size: 0.75rem; color: var(--text-muted-warm);"><?= htmlspecialchars((string)($c['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($currentTab === 'menu'): ?>
            <!-- TAB 4: Thực đơn -->
            <div class="zen-card" style="margin-bottom: 16px;">
                <h3 style="font-size: 0.9rem; font-weight: 700; color: var(--brand-tea-green); margin-bottom: 12px;">+ Thêm Món Mới</h3>
                <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 120px; gap: 10px; align-items: flex-end;">
                    <input type="hidden" name="admin_action" value="create_menu_item">
                    <div>
                        <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block;">Tên món:</label>
                        <input type="text" name="name" class="zen-input" placeholder="Tên món ăn/thức uống" required>
                    </div>
                    <div>
                        <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block;">Đơn giá (VND):</label>
                        <input type="number" name="price" class="zen-input" value="45000" min="0" required>
                    </div>
                    <div>
                        <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block;">Phân loại:</label>
                        <select name="category" class="zen-input">
                            <option value="food">Món ăn chính (food)</option>
                            <option value="drink">Thức uống thanh nhiệt (drink)</option>
                            <option value="extra">Món thêm (extra)</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="zen-btn-primary" style="padding: 11px 14px; border-radius: 12px; font-size: 0.85rem;">+ Thêm Món</button>
                    </div>
                </form>
            </div>

            <div class="zen-card" style="padding: 0; overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tên Món</th>
                            <th>Mô Tả</th>
                            <th>Phân Loại</th>
                            <th>Đơn Giá</th>
                            <th>Trạng Thái</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menuList as $m): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td style="font-size: 0.75rem; color: var(--text-muted-warm); max-width: 250px;"><?= htmlspecialchars((string)($m['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($m['category'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <form method="POST" style="display: flex; gap: 4px; align-items: center;">
                                        <input type="hidden" name="admin_action" value="update_price">
                                        <input type="hidden" name="item_id" value="<?= (int)$m['id'] ?>">
                                        <input type="number" name="price" value="<?= (int)$m['price'] ?>" style="width: 75px; font-size: 0.8rem; padding: 3px 6px; border: 1px solid var(--brand-border-soft); border-radius: 6px;">
                                        <button type="submit" class="action-btn">Lưu</button>
                                    </form>
                                </td>
                                <td>
                                    <?= (int)$m['is_available'] === 1 ? '<span style="color:#059669;">Đang phục vụ</span>' : '<span style="color:#DC2626;">Tạm hết</span>' ?>
                                </td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="admin_action" value="toggle_menu">
                                        <input type="hidden" name="item_id" value="<?= (int)$m['id'] ?>">
                                        <input type="hidden" name="is_available" value="<?= (int)$m['is_available'] === 1 ? '0' : '1' ?>">
                                        <button type="submit" class="action-btn">
                                            <?= (int)$m['is_available'] === 1 ? 'Tắt món' : 'Bật phục vụ' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($currentTab === 'notifications'): ?>
            <!-- TAB 5: Hàng Đợi Thông Báo SQLite (Issue #13) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px;">
                <div class="zen-card" style="padding: 14px; text-align: center; border-left: 4px solid #F59E0B;">
                    <p style="font-size: 0.72rem; color: var(--text-muted-warm); text-transform: uppercase;">⏳ Đang chờ gửi</p>
                    <p style="font-size: 1.6rem; font-weight: 700; color: #D97706; margin-top: 4px;">
                        <?= (int)$notifStats['pending'] ?>
                    </p>
                </div>
                <div class="zen-card" style="padding: 14px; text-align: center; border-left: 4px solid #3B82F6;">
                    <p style="font-size: 0.72rem; color: var(--text-muted-warm); text-transform: uppercase;">🔄 Đang xử lý</p>
                    <p style="font-size: 1.6rem; font-weight: 700; color: #2563EB; margin-top: 4px;">
                        <?= (int)$notifStats['processing'] ?>
                    </p>
                </div>
                <div class="zen-card" style="padding: 14px; text-align: center; border-left: 4px solid #10B981;">
                    <p style="font-size: 0.72rem; color: var(--text-muted-warm); text-transform: uppercase;">✓ Đã gửi thành công</p>
                    <p style="font-size: 1.6rem; font-weight: 700; color: #059669; margin-top: 4px;">
                        <?= (int)$notifStats['sent'] ?>
                    </p>
                </div>
                <div class="zen-card" style="padding: 14px; text-align: center; border-left: 4px solid <?= $notifStats['failed'] > 0 ? '#EF4444' : '#9CA3AF' ?>;">
                    <p style="font-size: 0.72rem; color: var(--text-muted-warm); text-transform: uppercase;">✗ Lỗi cần chú ý</p>
                    <p style="font-size: 1.6rem; font-weight: 700; color: <?= $notifStats['failed'] > 0 ? '#DC2626' : '#6B7280' ?>; margin-top: 4px;">
                        <?= (int)$notifStats['failed'] ?>
                    </p>
                </div>
            </div>

            <!-- Toolbar & Bộ lọc -->
            <div class="zen-card" style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <form method="GET" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="tab" value="notifications">
                    
                    <select name="notif_channel" class="zen-input" style="padding: 6px 10px; font-size: 0.78rem;" onchange="this.form.submit()">
                        <option value="">Tất cả kênh (All Channels)</option>
                        <option value="email" <?= $notifChannelFilter === 'email' ? 'selected' : '' ?>>✉️ Email</option>
                        <option value="web" <?= $notifChannelFilter === 'web' ? 'selected' : '' ?>>🔔 Web Dashboard</option>
                    </select>

                    <select name="notif_status" class="zen-input" style="padding: 6px 10px; font-size: 0.78rem;" onchange="this.form.submit()">
                        <option value="">Tất cả trạng thái</option>
                        <option value="pending" <?= $notifStatusFilter === 'pending' ? 'selected' : '' ?>>⏳ Chờ gửi (pending)</option>
                        <option value="processing" <?= $notifStatusFilter === 'processing' ? 'selected' : '' ?>>🔄 Đang xử lý (processing)</option>
                        <option value="sent" <?= $notifStatusFilter === 'sent' ? 'selected' : '' ?>>✓ Đã gửi (sent)</option>
                        <option value="failed" <?= $notifStatusFilter === 'failed' ? 'selected' : '' ?>>✗ Thất bại (failed)</option>
                    </select>
                </form>

                <div style="display: flex; gap: 8px; align-items: center;">
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="admin_action" value="run_notification_worker">
                        <button type="submit" class="action-btn" style="background: var(--brand-tea-green); color: #FFFFFF; border-color: var(--brand-tea-green); font-weight: 600; padding: 6px 14px;">
                            🚀 Xử Lý Hàng Đợi Ngay
                        </button>
                    </form>
                    <a href="admin.php?tab=config#smtp_settings" class="action-btn" style="text-decoration: none; font-size: 0.75rem;">
                        ⚙️ Cài đặt Email SMTP
                    </a>
                </div>
            </div>

            <!-- Bảng danh sách hàng đợi Outbox -->
            <div class="zen-card" style="padding: 0; overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Mã Đơn / Sự Kiện</th>
                            <th>Kênh</th>
                            <th>Trạng Thái</th>
                            <th>Số Lần Thử</th>
                            <th>Lần Thử Tiếp / Đã Gửi</th>
                            <th>Lỗi Gần Nhất</th>
                            <th style="text-align: right;">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifList)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 24px; color: var(--text-muted-warm);">
                                    🍃 Không có thông báo nào trong hàng đợi phù hợp với bộ lọc.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notifList as $item): ?>
                                <tr>
                                    <td style="font-size: 0.75rem; color: var(--text-muted-warm);">#<?= (int)$item['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars((string)($item['order_code'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span style="font-size: 0.72rem; color: var(--text-muted-warm); display: block;">
                                            <?= htmlspecialchars((string)$item['event_key'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($item['channel'] === 'email'): ?>
                                            <span style="background: #EFF6FF; color: #1D4ED8; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">✉️ Email</span>
                                        <?php elseif ($item['channel'] === 'web'): ?>
                                            <span style="background: #F0FDF4; color: #15803D; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">🔔 Web</span>
                                        <?php else: ?>
                                            <span style="background: #F3F4F6; color: #374151; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem;"><?= htmlspecialchars($item['channel'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['status'] === 'sent'): ?>
                                            <span style="background: #ECFDF5; color: #065F46; padding: 3px 8px; border-radius: 999px; font-size: 0.72rem; font-weight: 600;">✓ Đã gửi</span>
                                        <?php elseif ($item['status'] === 'pending'): ?>
                                            <span style="background: #FFFBEB; color: #92400E; padding: 3px 8px; border-radius: 999px; font-size: 0.72rem; font-weight: 600;">⏳ Chờ gửi</span>
                                        <?php elseif ($item['status'] === 'processing'): ?>
                                            <span style="background: #EFF6FF; color: #1E40AF; padding: 3px 8px; border-radius: 999px; font-size: 0.72rem; font-weight: 600;">🔄 Đang gửi...</span>
                                        <?php elseif ($item['status'] === 'failed'): ?>
                                            <span style="background: #FEE2E2; color: #991B1B; padding: 3px 8px; border-radius: 999px; font-size: 0.72rem; font-weight: 600;">✗ Thất bại</span>
                                        <?php else: ?>
                                            <span><?= htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.78rem; font-weight: 600; color: <?= (int)$item['attempt_count'] >= 5 ? '#DC2626' : 'inherit' ?>;">
                                            <?= (int)$item['attempt_count'] ?>/5
                                        </span>
                                    </td>
                                    <td style="font-size: 0.75rem; color: var(--text-muted-warm);">
                                        <?php if ($item['status'] === 'sent' && !empty($item['sent_at'])): ?>
                                            <?= date('d/m H:i:s', strtotime($item['sent_at'])) ?>
                                        <?php elseif (!empty($item['next_retry_at'])): ?>
                                            <?= date('d/m H:i:s', strtotime($item['next_retry_at'])) ?>
                                        <?php else: ?>
                                            <?= date('d/m H:i:s', strtotime($item['created_at'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.72rem; max-width: 220px; color: #B91C1C;">
                                        <?php if (!empty($item['last_error'])): ?>
                                            <?php
                                            $errStr = (string)$item['last_error'];
                                            $errLen = function_exists('mb_strlen') ? mb_strlen($errStr) : strlen($errStr);
                                            $errSnippet = $errLen > 45 ? ((function_exists('mb_substr') ? mb_substr($errStr, 0, 45) : substr($errStr, 0, 45)) . '...') : $errStr;
                                            ?>
                                            <span title="<?= htmlspecialchars($errStr, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($errSnippet, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #9CA3AF;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if ($item['status'] !== 'sent'): ?>
                                            <form method="POST" style="display: inline-block; margin: 0;">
                                                <input type="hidden" name="admin_action" value="retry_notification">
                                                <input type="hidden" name="notification_id" value="<?= (int)$item['id'] ?>">
                                                <button type="submit" class="action-btn" style="color: #1D4ED8; font-weight: 600;">
                                                    🔄 Gửi Lại
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="font-size: 0.72rem; color: #10B981;">Hoàn tất</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($currentTab === 'config'): ?>
            <!-- TAB 5: Cấu hình Hệ Thống -->
            <div class="zen-card">
                <h2 style="font-size: 0.95rem; font-weight: 700; color: var(--brand-tea-green); margin-bottom: 14px;">
                    ⚙️ Cấu Hình Quán & Tài Khoản VietQR
                </h2>
                <form method="POST" style="display: flex; flex-direction: column; gap: 14px;">
                    <input type="hidden" name="admin_action" value="update_config">
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px;">
                        <div>
                            <label style="font-size: 0.78rem; font-weight: 600; color: var(--text-charcoal); display: block; margin-bottom: 4px;">Tên quán:</label>
                            <input type="text" name="shop_name" class="zen-input" value="<?= htmlspecialchars($currentConfig['shop_name'] ?? 'Bếp Chay An Yên', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div>
                            <label style="font-size: 0.78rem; font-weight: 600; color: var(--text-charcoal); display: block; margin-bottom: 4px;">Châm ngôn quán:</label>
                            <input type="text" name="shop_quote" class="zen-input" value="<?= htmlspecialchars($currentConfig['shop_quote'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div>
                        <label style="font-size: 0.78rem; font-weight: 600; color: var(--text-charcoal); display: block; margin-bottom: 4px;">Thông báo lịch mở bếp hôm nay:</label>
                        <input type="text" name="notice_text" class="zen-input" value="<?= htmlspecialchars($currentConfig['notice_text'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <!-- Cấu hình Đặt chỗ & Khóa ca -->
                    <div style="border-top: 1px dashed var(--brand-border-soft); margin-top: 10px; padding-top: 14px;">
                        <h3 style="font-size: 0.88rem; font-weight: 700; color: var(--brand-tea-dark); margin-bottom: 10px;">
                            ⏰ Cấu hình Thời Gian Đặt Chỗ & Khóa Ca
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Đặt trước tối thiểu (tiếng trước khi ca bắt đầu):</label>
                                <input type="number" name="min_lead_time_hours" class="zen-input" value="<?= htmlspecialchars((string)($currentConfig['min_lead_time_hours'] ?? '3'), ENT_QUOTES, 'UTF-8') ?>" min="0" max="24" required>
                                <p style="font-size: 0.7rem; color: var(--text-muted-warm); margin-top: 2px;">* Mặc định 3h để bếp kịp ninh nước dùng.</p>
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Cho phép khách đặt trước tối đa (ngày):</label>
                                <input type="number" name="advance_booking_days" class="zen-input" value="<?= htmlspecialchars((string)($currentConfig['advance_booking_days'] ?? '7'), ENT_QUOTES, 'UTF-8') ?>" min="1" max="30" required>
                                <p style="font-size: 0.7rem; color: var(--text-muted-warm); margin-top: 2px;">* Mặc định 7 ngày (1 tuần trở lại).</p>
                            </div>
                        </div>
                    </div>

                    <!-- Cấu hình Tạm Nghỉ & Lịch Bếp Đi Vắng (Issue #12) -->
                    <div id="vacation_settings" style="border-top: 1px dashed var(--brand-border-soft); margin-top: 10px; padding-top: 14px;">
                        <h3 style="font-size: 0.88rem; font-weight: 700; color: #9A3412; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                            <span>🌿 Chế Độ Tạm Nghỉ & Lịch Bếp Đi Vắng</span>
                        </h3>
                        <div style="background: #FFFBEB; border: 1px solid #FDE68A; padding: 14px; border-radius: 12px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 700; color: #92400E; font-size: 0.85rem; margin-bottom: 10px;">
                                <input type="checkbox" name="is_shop_closed" value="1" <?= (isset($currentConfig['is_shop_closed']) && $currentConfig['is_shop_closed'] === '1') ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                                <span>Bật chế độ Tạm Nghỉ / Đi Vắng (Tạm dừng nhận đơn & hiển thị thông báo cho khách)</span>
                            </label>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 10px;">
                                <div>
                                    <label style="font-size: 0.75rem; color: #78350F; display: block; margin-bottom: 4px;">Nghỉ từ ngày (tùy chọn):</label>
                                    <input type="date" name="closed_from_date" class="zen-input" value="<?= htmlspecialchars($currentConfig['closed_from_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="background: #FFFFFF;">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: #78350F; display: block; margin-bottom: 4px;">Nghỉ đến hết ngày (tùy chọn):</label>
                                    <input type="date" name="closed_to_date" class="zen-input" value="<?= htmlspecialchars($currentConfig['closed_to_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="background: #FFFFFF;">
                                </div>
                            </div>

                            <div>
                                <label style="font-size: 0.75rem; color: #78350F; display: block; margin-bottom: 4px;">Lời nhắn gửi bạn thương khi vào web:</label>
                                <textarea name="closed_reason" class="zen-input" rows="2" style="background: #FFFFFF; resize: vertical;" placeholder="Bếp An Yên xin phép tạm nghỉ đi hái trà & chuẩn bị nguyên liệu mới. Hẹn sớm gặp lại bạn thương nhé! 🍃"><?= htmlspecialchars($currentConfig['closed_reason'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                <p style="font-size: 0.7rem; color: #92400E; margin-top: 4px; line-height: 1.4;">
                                    💡 <em>Ghi chú:</em> Nếu để trống ngày bắt đầu & kết thúc, bếp sẽ tạm nghỉ ngay lập tức vô thời hạn cho tới khi bạn bỏ chọn ô kích hoạt. Nếu cài khoảng ngày cụ thể, hệ thống sẽ tự động khóa ca và hiển thị thông báo trong khoảng thời gian đó.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div style="border-top: 1px dashed var(--brand-border-soft); margin-top: 10px; padding-top: 14px;">
                        <h3 style="font-size: 0.88rem; font-weight: 700; color: var(--brand-tea-dark); margin-bottom: 10px;">
                            📱 Cấu hình mã VietQR nhận tiền chuyển khoản
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Mã ngân hàng (BIN hoặc ShortName, ví dụ 970422 hoặc MB):</label>
                                <input type="text" name="bank_id" class="zen-input" value="<?= htmlspecialchars($currentConfig['bank_id'] ?? '970422', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Tên ngân hàng hiển thị:</label>
                                <input type="text" name="bank_name" class="zen-input" value="<?= htmlspecialchars($currentConfig['bank_name'] ?? 'MBBank', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Số tài khoản:</label>
                                <input type="text" name="account_no" class="zen-input" value="<?= htmlspecialchars($currentConfig['account_no'] ?? '0987654321', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Tên chủ tài khoản (In hoa không dấu):</label>
                                <input type="text" name="account_name" class="zen-input" value="<?= htmlspecialchars($currentConfig['account_name'] ?? 'BEP CHAY AN YEN', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Cấu hình Zalo Login & Xác nhận đơn -->
                    <div style="border-top: 1px dashed var(--brand-border-soft); margin-top: 10px; padding-top: 14px;">
                        <h3 style="font-size: 0.88rem; font-weight: 700; color: #0068FF; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                            <svg width="18" height="18" viewBox="0 0 48 48" fill="none"><path fill="#0068FF" d="M24 4C12.95 4 4 12.95 4 24c0 3.73 1.02 7.22 2.79 10.22L4 44l10.15-2.67C17.06 43.04 20.44 44 24 44c11.05 0 20-8.95 20-20S35.05 4 24 4z"/></svg>
                            <span>Cấu hình Zalo Login (OAuth2) & Chăm sóc khách</span>
                        </h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 12px;">
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Zalo App ID:</label>
                                <input type="text" name="zalo_app_id" class="zen-input" value="<?= htmlspecialchars($currentConfig['zalo_app_id'] ?? '123', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Zalo Secret Key:</label>
                                <input type="password" name="zalo_secret_key" class="zen-input" value="<?= htmlspecialchars($currentConfig['zalo_secret_key'] ?? '123', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Link Zalo Người Bán / Hotline:</label>
                                <input type="text" name="zalo_seller_url" class="zen-input" value="<?= htmlspecialchars($currentConfig['zalo_seller_url'] ?? 'https://zalo.me/0987862241', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Ghi chú vận chuyển (shipping note):</label>
                                <input type="text" name="order_note_shipping" class="zen-input" value="<?= htmlspecialchars($currentConfig['order_note_shipping'] ?? 'Chưa bao gồm chi phí vận chuyển', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">
                                Đường dẫn Callback Zalo (Redirect URI):
                            </label>
                            <input type="text" name="zalo_callback_url" class="zen-input" 
                                   placeholder="Để trống sẽ tự động dùng: <?= htmlspecialchars((new \App\Services\ZaloAuthService())->getCallbackUrl(), ENT_QUOTES, 'UTF-8') ?>" 
                                   value="<?= htmlspecialchars($currentConfig['zalo_callback_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <span style="font-size: 0.7rem; color: #6B7280; display: block; margin-top: 4px; line-height: 1.4;">
                                💡 Nhập URL này vào mục <strong>Redirect URI</strong> trong Zalo Developer Console. Khuyến nghị chuẩn hóa URL không có đuôi file (ví dụ: <code><?= htmlspecialchars((new \App\Services\ZaloAuthService())->getCallbackUrl(), ENT_QUOTES, 'UTF-8') ?></code>). Nếu bạn đã cấu hình dạng <code>.../zalo_callback.php</code> trong Zalo Console, bạn có thể điền vào ô này hoặc sửa lại trên Zalo Console theo clean URL trên.
                            </span>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Mẫu tin nhắn gửi xác nhận đơn qua Zalo ({order_id} sẽ tự thay thế):</label>
                            <input type="text" name="zalo_confirm_template" class="zen-input" value="<?= htmlspecialchars($currentConfig['zalo_confirm_template'] ?? 'Chào shop, mình vừa đặt đơn trên web. Mã đơn: {order_id}. Nhờ bếp kiểm tra và xác nhận giúp mình nhé!', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div style="display: flex; flex-wrap: wrap; gap: 16px; background: #F8FAFC; padding: 12px; border-radius: 12px; font-size: 0.8rem;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="checkbox" name="zalo_mock_test_mode" value="1" <?= (!isset($currentConfig['zalo_mock_test_mode']) || $currentConfig['zalo_mock_test_mode'] === '1') ? 'checked' : '' ?>>
                                <span>Bật chế độ thử nghiệm (Mock Test Mode)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="checkbox" name="zalo_require_login" value="1" <?= (isset($currentConfig['zalo_require_login']) && $currentConfig['zalo_require_login'] === '1') ? 'checked' : '' ?>>
                                <span>Bắt buộc đăng nhập Zalo mới được đặt</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="checkbox" name="zalo_require_group" value="1" <?= (isset($currentConfig['zalo_require_group']) && $currentConfig['zalo_require_group'] === '1') ? 'checked' : '' ?>>
                                <span>Bắt buộc tham gia nhóm Zalo</span>
                            </label>
                        </div>
                    </div>

                    <!-- Cấu hình Email Thông Báo & SMTP (Issue #13) -->
                    <div id="smtp_settings" style="border-top: 1px dashed var(--brand-border-soft); margin-top: 10px; padding-top: 14px;">
                        <h3 style="font-size: 0.88rem; font-weight: 700; color: #1E40AF; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                            <span>✉️ Cấu Hình Email Thông Báo & Máy Chủ SMTP (Dự Phòng)</span>
                        </h3>
                        <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 14px; border-radius: 12px; margin-bottom: 12px;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 10px;">
                                <div>
                                    <label style="font-size: 0.75rem; color: #166534; display: block; margin-bottom: 4px;"><strong>Email nhận thông báo đơn mới (Chủ bếp):</strong></label>
                                    <input type="email" name="notification_email_to" class="zen-input" value="<?= htmlspecialchars($currentConfig['notification_email_to'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="chubep@anyen.vn hoặc gmail của bạn" style="background: #FFFFFF;">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: #166534; display: block; margin-bottom: 4px;">Tên người gửi (From Name):</label>
                                    <input type="text" name="smtp_from_name" class="zen-input" value="<?= htmlspecialchars($currentConfig['smtp_from_name'] ?? 'Bếp Chay An Yên', ENT_QUOTES, 'UTF-8') ?>" style="background: #FFFFFF;">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 10px;">
                                <div>
                                    <label style="font-size: 0.75rem; color: #166534; display: block; margin-bottom: 4px;">Máy chủ SMTP (SMTP Host):</label>
                                    <input type="text" name="smtp_host" class="zen-input" value="<?= htmlspecialchars($currentConfig['smtp_host'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="smtp.gmail.com" style="background: #FFFFFF;">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: #166534; display: block; margin-bottom: 4px;">Cổng SMTP (Port):</label>
                                    <input type="number" name="smtp_port" class="zen-input" value="<?= htmlspecialchars((string)($currentConfig['smtp_port'] ?? '587'), ENT_QUOTES, 'UTF-8') ?>" placeholder="587 hoặc 465" style="background: #FFFFFF;">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: #166534; display: block; margin-bottom: 4px;">Phương thức mã hóa:</label>
                                    <select name="smtp_encryption" class="zen-input" style="background: #FFFFFF;">
                                        <option value="tls" <?= ($currentConfig['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Cổng 587 khuyến nghị)</option>
                                        <option value="ssl" <?= ($currentConfig['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (Cổng 465)</option>
                                        <option value="none" <?= ($currentConfig['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Không mã hóa (Cổng 25)</option>
                                    </select>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 10px;">
                                <div>
                                    <label style="font-size: 0.75rem; color: #166534; display: block; margin-bottom: 4px;">Tài khoản SMTP (Username/Email gửi):</label>
                                    <input type="text" name="smtp_username" class="zen-input" value="<?= htmlspecialchars($currentConfig['smtp_username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="ví dụ: bepchayanyen@gmail.com" style="background: #FFFFFF;">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: #166534; display: block; margin-bottom: 4px;">Mật khẩu ứng dụng SMTP (App Password):</label>
                                    <input type="password" name="smtp_password" class="zen-input" value="" placeholder="Để trống nếu không đổi mật khẩu" style="background: #FFFFFF;">
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.8rem; color: #166534;">
                                    <input type="checkbox" name="smtp_mock_mode" value="1" <?= (isset($currentConfig['smtp_mock_mode']) && $currentConfig['smtp_mock_mode'] === '1') ? 'checked' : '' ?>>
                                    <span>Bật chế độ thử nghiệm Email (Mock Mode - chỉ ghi vào logs/mail.log, không gửi thật ra ngoài)</span>
                                </label>
                            </div>
                            <p style="font-size: 0.7rem; color: #166534; margin-top: 6px; line-height: 1.4;">
                                💡 <em>Ghi chú:</em> Khi dùng Gmail, bạn hãy tạo <strong>Mật khẩu ứng dụng (App Password)</strong> trong tài khoản Google để điền vào ô mật khẩu. Nếu để trống SMTP Host, hệ thống sẽ tự động dùng hàm <code>mail()</code> hoặc ghi log giả lập an toàn.
                            </p>
                        </div>
                    </div>

                    <button type="submit" class="zen-btn-primary" style="margin-top: 8px;">Lưu Cấu Hình</button>
                </form>
            </div>

        <?php endif; ?>

    </div>

    <script>
        function copyCustomerZaloTemplate(name, code, slotTime) {
            const timeDesc = slotTime ? ' (khung giờ ' + slotTime + ')' : '';
            const text = 'Chào bạn ' + name + ', Bếp Chay An Yên nhắn bạn về đơn ' + code + timeDesc + ': Món thanh tịnh của bạn đang được bếp chuẩn bị chu đáo nhé! 🍃';
            navigator.clipboard.writeText(text).then(() => {
                alert('Đã sao chép tin nhắn mẫu gửi cho ' + name + ':\n\n' + text);
            }).catch(() => {
                prompt('Bạn hãy copy tin nhắn dưới đây để gửi cho khách:', text);
            });
        }
    </script>
</body>
</html>
