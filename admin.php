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
use App\Services\ReviewService;
use App\Services\PromotionService;

$configService = new ConfigService();
$customerService = new CustomerService();
$menuService = new MenuService();
$notificationService = new NotificationService();
$orderService = new OrderService();
$slotService = new SlotService();
$reviewService = new ReviewService(\App\Database\Connection::getInstance(), $configService);
$promotionService = new PromotionService(\App\Database\Connection::getInstance(), $configService, $customerService);

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
            $serviceScope = (string)($_POST['service_scope'] ?? 'both');
            $isFeastCombo = !empty($_POST['is_feast_combo']) ? 1 : 0;
            if ($name && $price >= 0) {
                $menuService->createItem($name, $desc, $price, $cat, $serviceScope, $isFeastCombo);
                $message = 'Đã thêm món/mâm cỗ mới vào thực đơn.';
            }
            break;

        case 'update_menu_item':
            $itemId = (int)$_POST['item_id'];
            $name = trim((string)$_POST['name']);
            $desc = trim((string)$_POST['description']);
            $price = (int)$_POST['price'];
            $cat = (string)$_POST['category'];
            $serviceScope = (string)($_POST['service_scope'] ?? 'both');
            $isFeastCombo = !empty($_POST['is_feast_combo']) ? 1 : 0;
            if ($itemId > 0 && $name && $price >= 0) {
                $menuService->updateItem($itemId, $name, $desc, $price, $cat, $serviceScope, $isFeastCombo);
                $message = 'Đã cập nhật thông tin món ăn.';
            }
            break;

        case 'delete_menu_item':
            $itemId = (int)$_POST['item_id'];
            if ($itemId > 0) {
                if ($menuService->deleteItem($itemId)) {
                    $message = 'Đã xóa món khỏi thực đơn.';
                } else {
                    $message = 'Món này đã có trong đơn đặt của khách, không thể xóa vĩnh viễn (bếp có thể bấm Tắt món).';
                }
            }
            break;

        case 'create_menu_suite':
            $name = trim((string)($_POST['name'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $isDefault = !empty($_POST['is_default']) ? 1 : 0;
            $itemIds = !empty($_POST['item_ids']) && is_array($_POST['item_ids']) ? array_map('intval', $_POST['item_ids']) : [];
            if ($name !== '') {
                $menuService->createSuite($name, $desc, $isDefault, $itemIds);
                $message = 'Đã tạo bộ thực đơn mẫu mới.';
            }
            break;

        case 'update_menu_suite':
            $suiteId = (int)($_POST['suite_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $isDefault = !empty($_POST['is_default']) ? 1 : 0;
            $itemIds = !empty($_POST['item_ids']) && is_array($_POST['item_ids']) ? array_map('intval', $_POST['item_ids']) : [];
            if ($suiteId > 0 && $name !== '') {
                $menuService->updateSuite($suiteId, $name, $desc, $isDefault, $itemIds);
                $message = 'Đã cập nhật bộ thực đơn mẫu.';
            }
            break;

        case 'delete_menu_suite':
            $suiteId = (int)($_POST['suite_id'] ?? 0);
            if ($suiteId > 0) {
                $menuService->deleteSuite($suiteId);
                $message = 'Đã xóa bộ thực đơn mẫu.';
            }
            break;

        case 'assign_menu_schedule':
            $date = trim((string)($_POST['schedule_date'] ?? ''));
            $suiteId = (int)($_POST['suite_id'] ?? 0);
            $note = trim((string)($_POST['note'] ?? ''));
            if ($date && $suiteId > 0) {
                $menuService->assignSuiteToDate($date, $suiteId, $note);
                $message = 'Đã lên lịch thực đơn cho ngày ' . date('d/m/Y', strtotime($date));
            }
            break;

        case 'replicate_menu_schedule':
            $suiteId = (int)($_POST['suite_id'] ?? 0);
            $startDate = trim((string)($_POST['start_date'] ?? ''));
            $endDate = trim((string)($_POST['end_date'] ?? ''));
            $daysOfWeek = !empty($_POST['days_of_week']) && is_array($_POST['days_of_week']) ? array_map('intval', $_POST['days_of_week']) : [];
            if ($suiteId > 0 && $startDate && $endDate) {
                $count = $menuService->replicateSchedule($suiteId, $startDate, $endDate, $daysOfWeek);
                $message = "Đã áp dụng bộ thực đơn cho {$count} ngày trong khoảng từ {$startDate} đến {$endDate}.";
            }
            break;

        case 'remove_menu_schedule':
            $date = trim((string)($_POST['schedule_date'] ?? ''));
            if ($date) {
                $menuService->removeScheduleForDate($date);
                $message = 'Đã hủy lịch chỉ định riêng cho ngày ' . date('d/m/Y', strtotime($date)) . ' (ngày này sẽ xoay tua theo quy tắc chung).';
            }
            break;

        case 'update_menu_rules':
            $rule = (string)($_POST['menu_schedule_rule'] ?? 'default');
            $evenSuiteId = (int)($_POST['menu_even_suite_id'] ?? 2);
            $oddSuiteId = (int)($_POST['menu_odd_suite_id'] ?? 3);
            $defaultSuiteId = (int)($_POST['menu_default_suite_id'] ?? 1);
            $configService->set('menu_schedule_rule', $rule);
            $configService->set('menu_even_suite_id', (string)$evenSuiteId);
            $configService->set('menu_odd_suite_id', (string)$oddSuiteId);
            $configService->set('menu_default_suite_id', (string)$defaultSuiteId);
            $message = 'Đã lưu quy tắc xoay tua thực đơn tự động.';
            break;

        case 'notify_menu_change':
            $itemId = (int)($_POST['item_id'] ?? 0);
            $changeTitle = trim((string)($_POST['change_title'] ?? 'Thông báo thay đổi món'));
            $changeMsg = trim((string)($_POST['change_message'] ?? ''));
            $fromDate = trim((string)($_POST['from_date'] ?? date('Y-m-d')));
            if ($itemId > 0 && $changeMsg !== '') {
                $res = $menuService->notifyCustomersOnItemChange($itemId, $changeTitle, $changeMsg, $fromDate);
                $message = "Đã tạo thông báo gửi đến {$res['enqueued_count']} khách có đơn đặt món bị ảnh hưởng.";
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

            // Cấu hình Đánh Giá & Cảm Nhận Khách Hàng (Issue #18)
            $reviewsEnabled = isset($_POST['reviews_enabled']) ? '1' : '0';
            $reviewsRequireApproval = isset($_POST['reviews_require_approval']) ? '1' : '0';
            $reviewsAllowAnon = isset($_POST['reviews_allow_anonymous']) ? '1' : '0';

            $configService->set('reviews_enabled', $reviewsEnabled);
            $configService->set('reviews_require_approval', $reviewsRequireApproval);
            $configService->set('reviews_allow_anonymous', $reviewsAllowAnon);

            $message = 'Đã lưu cấu hình hệ thống, thời gian đặt, Zalo, Email & Đánh giá thành công.';
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

        // 7. Nhân bản & đồng bộ lịch ca (Issue #15)
        case 'replicate_slots':
            $templateDate = trim((string)($_POST['template_date'] ?? ''));
            $period = (string)($_POST['period'] ?? '1_week');
            $rawDays = (array)($_POST['days_of_week'] ?? []);
            $daysOfWeek = array_map('intval', $rawDays);
            $overwrite = isset($_POST['overwrite_existing']) ? true : false;

            $startDate = date('Y-m-d');
            if ($period === '1_week') {
                $endDate = date('Y-m-d', strtotime('+7 days'));
            } elseif ($period === '2_weeks') {
                $endDate = date('Y-m-d', strtotime('+14 days'));
            } elseif ($period === '1_month') {
                $endDate = date('Y-m-d', strtotime('+30 days'));
            } else {
                $startDate = trim((string)($_POST['start_date'] ?? date('Y-m-d')));
                $endDate = trim((string)($_POST['end_date'] ?? date('Y-m-d', strtotime('+7 days'))));
            }

            if (empty($daysOfWeek)) {
                $daysOfWeek = [1, 2, 3, 4, 5, 6, 0];
            }

            try {
                $res = $slotService->replicateSlots($templateDate, $daysOfWeek, $startDate, $endDate, $overwrite);
                $message = sprintf('Đã nhân bản thành công %d ca phục vụ cho %d ngày mở ca (theo ngày mẫu %s).', $res['created_count'], $res['days_count'], $templateDate);
            } catch (\Throwable $e) {
                $message = 'Lỗi nhân bản ca: ' . $e->getMessage();
            }
            break;

        case 'sync_week_slots':
            $sourceDate = trim((string)($_POST['source_date'] ?? ''));
            try {
                $res = $slotService->syncWeekSlotsFromDate($sourceDate);
                $message = sprintf('Đã đồng bộ thành công %d ca phục vụ cho các ngày mở trong tuần theo ngày mẫu %s.', $res['created_count'], $sourceDate);
            } catch (\Throwable $e) {
                $message = 'Lỗi đồng bộ tuần: ' . $e->getMessage();
            }
            break;

        case 'delete_slot':
            $slotId = (int)$_POST['slot_id'];
            try {
                $slotService->deleteSlot($slotId);
                $message = 'Đã xóa ca phục vụ thành công.';
            } catch (\Throwable $e) {
                $message = 'Không thể xóa ca: ' . $e->getMessage();
            }
            break;

        // 8. Đối soát thanh toán & chốt ca / ngày (Issue #16)
        case 'reconcile_payment':
            $rawInput = trim((string)($_POST['raw_input'] ?? ''));
            $ref = trim((string)($_POST['transaction_ref'] ?? ''));
            $amount = !empty($_POST['paid_amount']) ? (int)$_POST['paid_amount'] : null;

            $orderCode = trim((string)($_POST['order_code'] ?? ''));
            if ($orderCode === '' && $rawInput !== '') {
                if (preg_match('/AY-\d{8}-[A-Z0-9]{4}/i', $rawInput, $matches)) {
                    $orderCode = strtoupper($matches[0]);
                } else {
                    $orderCode = $rawInput;
                }
            }

            if ($orderCode === '') {
                $message = 'Vui lòng nhập mã đơn hàng hoặc nội dung biến động số dư để đối soát.';
            } else {
                try {
                    $reconciled = $orderService->reconcilePayment($orderCode, $ref ?: 'Admin-Manual', $amount);
                    $message = sprintf('Đã đối soát & xác nhận thanh toán thành công cho đơn %s (%sđ).', $reconciled['order_code'], number_format((int)$reconciled['total_amount'], 0, ',', '.'));
                } catch (\Throwable $e) {
                    $message = 'Lỗi đối soát thanh toán: ' . $e->getMessage();
                }
            }
            break;

        case 'close_slot':
            $slotId = (int)$_POST['slot_id'];
            $summary = $orderService->closeSlot($slotId, true);
            $message = sprintf('Đã chốt sổ ca phục vụ #%d thành công. Đã hoàn tất các đơn đang phục vụ.', $slotId);
            break;

        case 'close_day':
            $closeDate = trim((string)($_POST['close_date'] ?? date('Y-m-d')));
            $closingReport = $orderService->closeDay($closeDate, true);
            $message = sprintf('Đã chốt sổ toàn bộ ngày %s thành công. Doanh thu ghi nhận: %sđ.', $closeDate, number_format((int)$closingReport['total_revenue'], 0, ',', '.'));
            break;

        // 9. Quản lý Đánh Giá & Cảm Nhận Của Khách (Issue #18)
        case 'update_review_status':
            $revId = (int)($_POST['review_id'] ?? 0);
            $revStatus = (string)($_POST['review_status'] ?? 'pending');
            if ($revId > 0) {
                $reviewService->updateStatus($revId, $revStatus);
                $message = 'Đã cập nhật trạng thái hiển thị của đánh giá.';
            }
            break;

        case 'reply_review':
            $revId = (int)($_POST['review_id'] ?? 0);
            $kitchenReply = (string)($_POST['kitchen_reply'] ?? '');
            if ($revId > 0) {
                $reviewService->replyToReview($revId, $kitchenReply);
                $message = 'Đã lưu lời phản hồi của Bếp gửi khách.';
            }
            break;

        case 'delete_review':
            $revId = (int)($_POST['review_id'] ?? 0);
            if ($revId > 0) {
                $reviewService->deleteReview($revId);
                $message = 'Đã xóa đánh giá.';
            }
            break;

        case 'toggle_reviews_feature':
            $enabled = (int)($_POST['reviews_enabled'] ?? 0) === 1;
            $reviewService->setReviewsEnabled($enabled);
            $message = $enabled 
                ? 'Đã BẬT trang đánh giá & cảm nhận cho khách hàng.' 
                : 'Đã TẮT trang đánh giá của khách hàng (chế độ tạm đóng).';
            break;

        case 'toggle_review_featured':
            $revId = (int)($_POST['review_id'] ?? 0);
            if ($revId > 0) {
                $reviewService->toggleFeatured($revId);
                $message = 'Đã cập nhật trạng thái Ghim Đề Tặng / Cảm Nhận Nổi Bật (Testimonial).';
            }
            break;

        case 'update_testimonial_note':
            $revId = (int)($_POST['review_id'] ?? 0);
            $note = (string)($_POST['testimonial_note'] ?? '');
            $type = (string)($_POST['review_type'] ?? 'testimonial');
            if ($revId > 0) {
                $reviewService->updateTestimonialNote($revId, $note, $type);
                $message = 'Đã cập nhật lời đề tựa / ghi chú kỷ niệm.';
            }
            break;

        case 'create_testimonial':
            try {
                $newId = $reviewService->createTestimonial($_POST);
                $message = 'Đã thêm lời đề tặng, kỷ niệm mới thành công (ID #' . $newId . ')!';
            } catch (\Throwable $e) {
                $message = 'Lỗi thêm kỷ niệm: ' . $e->getMessage();
            }
            break;

        // 10. Quản lý Khuyến Mại & Ưu Đãi (Issue #20)
        case 'create_promotion':
            try {
                $promotionService->createPromotion($_POST);
                $message = 'Đã tạo chương trình khuyến mại mới thành công.';
            } catch (\Throwable $e) {
                $message = 'Lỗi tạo khuyến mại: ' . $e->getMessage();
            }
            break;

        case 'update_promotion':
            $promoId = (int)($_POST['promo_id'] ?? 0);
            try {
                $promotionService->updatePromotion($promoId, $_POST);
                $message = 'Đã cập nhật chương trình khuyến mại.';
            } catch (\Throwable $e) {
                $message = 'Lỗi cập nhật: ' . $e->getMessage();
            }
            break;

        case 'toggle_promotion':
            $promoId = (int)($_POST['promo_id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0) === 1;
            $promotionService->togglePromotionStatus($promoId, $isActive);
            $message = 'Đã cập nhật trạng thái hoạt động của khuyến mại.';
            break;

        case 'delete_promotion':
            $promoId = (int)($_POST['promo_id'] ?? 0);
            $promotionService->deletePromotion($promoId);
            $message = 'Đã xóa chương trình khuyến mại.';
            break;
    }
}

// Lấy dữ liệu cho các tab
$currentTab = (string)($_GET['tab'] ?? 'slots');
$allPromotions = $promotionService->getPromotions();
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
$closingReportDate = (string)($_GET['closing_date'] ?? $todayDate);

$kitchenSummary = [];
$slotOrders = [];
$pendingReconcileOrders = [];
$dailyClosingReport = [];

if ($orderScope === 'pending_reconcile') {
    $pendingReconcileOrders = $orderService->getPendingReconcileOrders(100);
} elseif ($orderScope === 'daily_closing') {
    $dailyClosingReport = $orderService->getClosingReport($closingReportDate);
    $slotOrders = $orderService->getOrdersByDate($closingReportDate);
} elseif ($orderScope === 'today_all') {
    $kitchenSummary = $orderService->getKitchenSummaryByDate($todayDate);
    $slotOrders = $orderService->getOrdersByDate($todayDate);
} elseif ($selectedSlotId > 0) {
    $kitchenSummary = $orderService->getKitchenSummaryBySlot($selectedSlotId);
    $slotOrders = $orderService->getOrdersBySlot($selectedSlotId);
} else {
    $kitchenSummary = $orderService->getKitchenSummaryByDate($todayDate);
    $slotOrders = $orderService->getOrdersByDate($todayDate);
}

// Thống kê tồn đọng đối soát để hiển thị badge
$allPendingReconcile = $orderService->getPendingReconcileOrders(50);
$pendingReconcileCount = count($allPendingReconcile);

// Tab Customers
$customerSearch = (string)($_GET['search_cust'] ?? '');
$customerList = $customerService->getAllCustomers($customerSearch);

// Tab Menu (Issue #17)
$menuSubtab = (string)($_GET['menu_subtab'] ?? 'items');
$menuList = $menuService->getAllMenu();
$allSuites = $menuService->getAllSuites();
$menuScheduleRule = $configService->get('menu_schedule_rule', 'default');
$menuEvenSuiteId = (int)$configService->get('menu_even_suite_id', '2');
$menuOddSuiteId = (int)$configService->get('menu_odd_suite_id', '3');
$menuDefaultSuiteId = (int)$configService->get('menu_default_suite_id', '1');
$upcomingSchedules = $menuService->getSchedulesBetween(date('Y-m-d'), date('Y-m-d', strtotime('+30 days')));

$impactItemId = !empty($_GET['check_impact_item_id']) ? (int)$_GET['check_impact_item_id'] : 0;
$impactData = null;
if ($impactItemId > 0) {
    $impactData = $menuService->checkItemBookedImpact($impactItemId);
}

// Tab Config
$currentConfig = $configService->getAll();

// Tab Notifications (Issue #13)
$notifChannelFilter = !empty($_GET['notif_channel']) ? (string)$_GET['notif_channel'] : null;
$notifStatusFilter = !empty($_GET['notif_status']) ? (string)$_GET['notif_status'] : null;
$notifList = $notificationService->getNotificationsForAdmin(60, $notifChannelFilter, $notifStatusFilter);
$notifStats = $notificationService->getStats();

// Tab Reviews (Issue #18)
$reviewStatusFilter = !empty($_GET['review_status']) ? (string)$_GET['review_status'] : null;
$allReviews = $reviewService->getAllReviewsForAdmin($reviewStatusFilter);
$reviewAdminStats = $reviewService->getAdminStats();
$isReviewsEnabled = $reviewService->isReviewsEnabled();
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
            <a href="admin.php?tab=orders" class="admin-tab <?= $currentTab === 'orders' ? 'active' : '' ?>">
                🍲 Bếp & Đơn Hàng
                <?php if (!empty($pendingReconcileCount) && $pendingReconcileCount > 0): ?>
                    <span style="background: #D97706; color: #FFFFFF; font-size: 0.65rem; padding: 2px 6px; border-radius: 999px; margin-left: 4px; font-weight: 700;" title="Đơn cần đối soát thanh toán">
                        <?= $pendingReconcileCount ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="admin.php?tab=customers" class="admin-tab <?= $currentTab === 'customers' ? 'active' : '' ?>">🌿 Khách Quen</a>
            <a href="admin.php?tab=menu" class="admin-tab <?= $currentTab === 'menu' ? 'active' : '' ?>">🥢 Thực Đơn</a>
            <a href="admin.php?tab=promotions" class="admin-tab <?= $currentTab === 'promotions' ? 'active' : '' ?>">
                🔥 Khuyến Mại
                <?php 
                $activePromoCount = count(array_filter($allPromotions, fn($p) => (int)$p['is_active'] === 1));
                if ($activePromoCount > 0): 
                ?>
                    <span style="background: #EA580C; color: #FFFFFF; font-size: 0.65rem; padding: 2px 6px; border-radius: 999px; margin-left: 4px; font-weight: 700;">
                        <?= $activePromoCount ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="admin.php?tab=reviews" class="admin-tab <?= $currentTab === 'reviews' ? 'active' : '' ?>">
                🌸 Đánh Giá
                <?php if ($reviewAdminStats['pending'] > 0): ?>
                    <span style="background: #F59E0B; color: #FFFFFF; font-size: 0.65rem; padding: 2px 6px; border-radius: 999px; margin-left: 4px; font-weight: 700;" title="Đánh giá đang chờ duyệt">
                        <?= $reviewAdminStats['pending'] ?>
                    </span>
                <?php endif; ?>
            </a>
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

            <!-- Nhân bản ca hàng loạt 1 tuần / 1 tháng từ ngày mẫu (Issue #15) -->
            <div class="zen-card" style="margin-bottom: 16px; background: #FAF7F2; border: 1.5px dashed var(--brand-border-soft);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <h2 style="font-size: 0.95rem; font-weight: 700; color: var(--brand-tea-dark); display: flex; align-items: center; gap: 6px; margin: 0;">
                        <span>📅</span> Nhân Bản Lịch Ca Hàng Loạt (Tuần / Tháng)
                    </h2>
                    <span style="font-size: 0.72rem; color: var(--brand-tea-green); font-weight: 600;">Issue #15</span>
                </div>
                <p style="font-size: 0.78rem; color: var(--text-muted-warm); margin-bottom: 12px; line-height: 1.45;">
                    Tạo sẵn các ca mẫu cho một ngày (ví dụ: hôm nay), sau đó nhân bản nhanh cho 1 tuần hoặc 1 tháng tới theo các thứ trong tuần được mở ca.
                </p>
                <form method="POST" style="display: flex; flex-direction: column; gap: 12px;">
                    <input type="hidden" name="admin_action" value="replicate_slots">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;">
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Ngày lấy làm mẫu:</label>
                            <input type="date" name="template_date" class="zen-input" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Khoảng thời gian nhân bản:</label>
                            <select name="period" class="zen-input">
                                <option value="1_week">1 Tuần tới (7 ngày)</option>
                                <option value="2_weeks">2 Tuần tới (14 ngày)</option>
                                <option value="1_month">1 Tháng tới (30 ngày)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 4px;">
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm);">Chọn các thứ trong tuần được mở ca:</label>
                            <div style="display: flex; gap: 4px;">
                                <button type="button" class="copy-pill" onclick="setSlotWeekdays('weekdays')">T2 - T6</button>
                                <button type="button" class="copy-pill" onclick="setSlotWeekdays('weekends')">T7 & CN</button>
                                <button type="button" class="copy-pill" onclick="setSlotWeekdays('all')">Cả tuần</button>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; background: #FFFFFF; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--brand-border-soft);" id="slotWeekdayGroup">
                            <label style="font-size: 0.78rem; display: flex; align-items: center; gap: 4px;"><input type="checkbox" name="days_of_week[]" value="1" checked> Thứ 2</label>
                            <label style="font-size: 0.78rem; display: flex; align-items: center; gap: 4px;"><input type="checkbox" name="days_of_week[]" value="2" checked> Thứ 3</label>
                            <label style="font-size: 0.78rem; display: flex; align-items: center; gap: 4px;"><input type="checkbox" name="days_of_week[]" value="3" checked> Thứ 4</label>
                            <label style="font-size: 0.78rem; display: flex; align-items: center; gap: 4px;"><input type="checkbox" name="days_of_week[]" value="4" checked> Thứ 5</label>
                            <label style="font-size: 0.78rem; display: flex; align-items: center; gap: 4px;"><input type="checkbox" name="days_of_week[]" value="5" checked> Thứ 6</label>
                            <label style="font-size: 0.78rem; display: flex; align-items: center; gap: 4px;"><input type="checkbox" name="days_of_week[]" value="6" checked> Thứ 7</label>
                            <label style="font-size: 0.78rem; display: flex; align-items: center; gap: 4px;"><input type="checkbox" name="days_of_week[]" value="0" checked> Chủ Nhật</label>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; padding-top: 6px;">
                        <label style="font-size: 0.76rem; color: var(--text-muted-warm); display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="overwrite_existing" value="1">
                            <span>Làm mới các ca chưa có đơn trong các ngày mở</span>
                        </label>
                        <button type="submit" class="zen-btn-primary" style="padding: 9px 18px; border-radius: 12px; font-size: 0.82rem;">
                            🚀 Bắt Đầu Nhân Bản Ca
                        </button>
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
                                        <?php if (!empty($s['is_closed'])): ?>
                                            <span style="font-size: 0.65rem; background: #FEF3C7; color: #92400E; padding: 2px 5px; border-radius: 4px; margin-left: 4px;">Đã chốt</span>
                                        <?php elseif (!empty($s['is_past'])): ?>
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
                                        <?php if (!empty($s['is_closed'])): ?>
                                            <span style="color:#B45309; font-size: 0.78rem;">Đã chốt sổ</span>
                                        <?php elseif (!empty($s['is_past'])): ?>
                                            <span style="color:#6B7280; font-size: 0.78rem;">Đã kết thúc</span>
                                        <?php else: ?>
                                            <?= (int)$s['is_active'] === 1 ? '<span style="color:#059669;">Đang mở</span>' : '<span style="color:#9CA3AF;">Đóng</span>' ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 4px; align-items: center; flex-wrap: wrap;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="admin_action" value="toggle_slot">
                                                <input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>">
                                                <input type="hidden" name="is_active" value="<?= (int)$s['is_active'] === 1 ? '0' : '1' ?>">
                                                <button type="submit" class="action-btn">
                                                    <?= (int)$s['is_active'] === 1 ? 'Đóng ca' : 'Mở lại' ?>
                                                </button>
                                            </form>
                                            
                                            <!-- Đồng bộ lịch tuần theo ngày này (Issue #15) -->
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Đồng bộ các ca ngày <?= $s['slot_date'] ?> cho tất cả các ngày mở trong tuần này?')">
                                                <input type="hidden" name="admin_action" value="sync_week_slots">
                                                <input type="hidden" name="source_date" value="<?= htmlspecialchars($s['slot_date'], ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="action-btn" title="Đồng bộ cho các ngày trong tuần">🔄 Tuần</button>
                                            </form>

                                            <a href="admin.php?tab=orders&scope=by_slot&slot_id=<?= (int)$s['id'] ?>" class="action-btn" style="text-decoration: none;">Xem đơn</a>

                                            <!-- Xóa ca chưa có đơn -->
                                            <?php if ((int)$s['booked_count'] === 0): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Bạn chắc chắn muốn xóa ca <?= $s['slot_time'] ?> ngày <?= $s['slot_date'] ?>?')">
                                                    <input type="hidden" name="admin_action" value="delete_slot">
                                                    <input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>">
                                                    <button type="submit" class="action-btn" style="color: #DC2626;" title="Xóa ca chưa có đơn">✕</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($currentTab === 'orders'): ?>
            <!-- TAB 2: Bếp Chuẩn Bị & Đơn Hàng -->
            
            <!-- Thanh điều hướng phụ trong Tab Bếp & Đơn Hàng -->
            <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 16px;">
                <a href="admin.php?tab=orders&scope=today_all" 
                   class="action-btn" 
                   style="padding: 8px 14px; font-weight: 600; text-decoration: none; border-radius: 10px; <?= $orderScope === 'today_all' ? 'background: var(--brand-tea-green); color: #FFFFFF; border-color: var(--brand-tea-green);' : '' ?>">
                    🌟 Bếp Hôm Nay (<?= date('d/m') ?>)
                </a>

                <a href="admin.php?tab=orders&scope=pending_reconcile" 
                   class="action-btn" 
                   style="padding: 8px 14px; font-weight: 600; text-decoration: none; border-radius: 10px; display: inline-flex; align-items: center; gap: 6px; <?= $orderScope === 'pending_reconcile' ? 'background: #D97706; color: #FFFFFF; border-color: #D97706;' : '' ?>">
                    <span>⚡ Chờ Đối Soát</span>
                    <?php if ($pendingReconcileCount > 0): ?>
                        <span style="background: <?= $orderScope === 'pending_reconcile' ? '#FFFFFF' : '#D97706' ?>; color: <?= $orderScope === 'pending_reconcile' ? '#D97706' : '#FFFFFF' ?>; font-size: 0.7rem; padding: 1px 6px; border-radius: 999px; font-weight: 700;">
                            <?= $pendingReconcileCount ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a href="admin.php?tab=orders&scope=daily_closing" 
                   class="action-btn" 
                   style="padding: 8px 14px; font-weight: 600; text-decoration: none; border-radius: 10px; <?= $orderScope === 'daily_closing' ? 'background: #4B5563; color: #FFFFFF; border-color: #4B5563;' : '' ?>">
                    📊 Chốt Sổ Ngày & Báo Cáo
                </a>

                <div style="flex: 1; min-width: 200px;">
                    <select onchange="window.location.href=this.value" class="zen-input" style="padding: 7px 10px; font-size: 0.8rem;">
                        <option value="admin.php?tab=orders&scope=today_all" <?= $orderScope === 'today_all' ? 'selected' : '' ?>>-- Xem chi tiết theo từng ca --</option>
                        <?php foreach ($allUpcomingAndTodaySlots as $s): ?>
                            <option value="admin.php?tab=orders&scope=by_slot&slot_id=<?= (int)$s['id'] ?>" <?= ($orderScope === 'by_slot' && (int)$s['id'] === $selectedSlotId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['slot_date'] . ' (' . $s['slot_time'] . ')' . (!empty($s['is_closed']) ? ' [Đã chốt]' : (!empty($s['is_past']) ? ' [Đã qua]' : '')) . ' - Đặt: ' . $s['booked_count'] . '/' . $s['max_capacity'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($orderScope === 'pending_reconcile'): ?>
                <!-- CHẾ ĐỘ XEM: ĐỐI SOÁT THANH TOÁN (Issue #16) -->
                <div class="zen-card" style="margin-bottom: 16px; background: #FFFBEB; border: 1.5px solid #FCD34D;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <h2 style="font-size: 0.95rem; font-weight: 700; color: #92400E; display: flex; align-items: center; gap: 6px; margin: 0;">
                            <span>⚡</span> Đối Soát & Khớp Thanh Toán Nhanh
                        </h2>
                        <span style="font-size: 0.72rem; color: #B45309; font-weight: 600;">Tự động & Thủ công</span>
                    </div>
                    <p style="font-size: 0.78rem; color: #78350F; margin-bottom: 12px; line-height: 1.45;">
                        Khi khách quét VietQR nhưng ứng dụng ngân hàng gửi tin nhắn/biến động số dư có độ trễ, bạn có thể dán mã đơn hàng hoặc nội dung tin nhắn ngân hàng vào đây để hệ thống tự động bóc tách mã <code>AY-...</code> và xác nhận thanh toán tức thì.
                    </p>
                    <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 120px; gap: 10px; align-items: flex-end;">
                        <input type="hidden" name="admin_action" value="reconcile_payment">
                        <div>
                            <label style="font-size: 0.72rem; color: #92400E; display: block; margin-bottom: 4px;">Mã đơn hoặc Nội dung SMS ngân hàng:</label>
                            <input type="text" name="order_code" class="zen-input" placeholder="VD: AY-20261025-XXXX..." style="background: #FFFFFF;" required>
                        </div>
                        <div>
                            <label style="font-size: 0.72rem; color: #92400E; display: block; margin-bottom: 4px;">Mã chuẩn chi / Trace / GD ngân hàng:</label>
                            <input type="text" name="transaction_ref" class="zen-input" placeholder="Tùy chọn: FT24..., 123456" style="background: #FFFFFF;">
                        </div>
                        <div>
                            <label style="font-size: 0.72rem; color: #92400E; display: block; margin-bottom: 4px;">Số tiền khớp (đ):</label>
                            <input type="number" name="paid_amount" class="zen-input" placeholder="Tùy chọn" style="background: #FFFFFF;">
                        </div>
                        <div>
                            <button type="submit" class="zen-btn-primary" style="background: #D97706; border-color: #D97706; padding: 11px 14px; border-radius: 12px; font-size: 0.85rem; width: 100%;">
                                ✓ Xác Nhận
                            </button>
                        </div>
                    </form>
                </div>

                <div class="zen-card" style="padding: 0; overflow-x: auto;">
                    <div style="padding: 12px 16px; border-bottom: 1px solid var(--brand-border-soft); display: flex; justify-content: space-between; align-items: center;">
                        <strong style="font-size: 0.88rem; color: var(--brand-tea-dark);">
                            Danh Sách Đơn Hàng Đang Chờ Thu / Đối Soát (<?= count($pendingReconcileOrders) ?> đơn)
                        </strong>
                        <span style="font-size: 0.75rem; color: var(--text-muted-warm);">Tồn đọng tài chính cần kiểm tra</span>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Mã Đơn</th>
                                <th>Thời Gian Đặt</th>
                                <th>Khung Giờ</th>
                                <th>Khách Hàng</th>
                                <th>Món Đặt</th>
                                <th>Số Tiền</th>
                                <th>Thanh Toán</th>
                                <th>Thao Tác Đối Soát</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingReconcileOrders)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: var(--brand-tea-green); padding: 32px 16px;">
                                        🌿 <strong>Tuyệt vời!</strong> Toàn bộ các đơn hàng đã được đối soát và thanh toán đầy đủ, không có khoản tồn đọng.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pendingReconcileOrders as $po): 
                                    $pPhone = preg_replace('/[^0-9]/', '', (string)$po['customer_phone']);
                                ?>
                                    <tr>
                                        <td>
                                            <strong style="color: #D97706;"><?= htmlspecialchars($po['order_code'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        </td>
                                        <td style="font-size: 0.72rem; color: var(--text-muted-warm);">
                                            <?= date('H:i d/m/Y', strtotime($po['created_at'])) ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars((string)($po['slot_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                            <div style="font-size: 0.7rem; color: var(--text-muted-warm);"><?= htmlspecialchars((string)($po['slot_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600;"><?= htmlspecialchars($po['customer_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div style="font-size: 0.72rem; color: var(--text-muted-warm);"><?= htmlspecialchars($po['customer_phone'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <a href="https://zalo.me/<?= htmlspecialchars($pPhone, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" 
                                               style="display: inline-block; margin-top: 2px; font-size: 0.68rem; color: #0068FF; text-decoration: underline;">
                                                Nhắn Zalo khách ↗
                                            </a>
                                        </td>
                                        <td style="font-size: 0.75rem;">
                                            <?php foreach ($po['items'] as $it): ?>
                                                <div><?= htmlspecialchars($it['item_name'], ENT_QUOTES, 'UTF-8') ?> x<?= (int)$it['quantity'] ?></div>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <strong style="color: #B45309; font-size: 0.9rem;"><?= number_format((int)$po['total_amount'], 0, ',', '.') ?>đ</strong>
                                        </td>
                                        <td>
                                            <span style="display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; <?= $po['payment_method'] === 'vietqr' ? 'background: #FEF3C7; color: #92400E;' : 'background: #E0E7FF; color: #3730A3;' ?>">
                                                <?= $po['payment_method'] === 'vietqr' ? 'VietQR' : 'Tại bếp' ?>
                                            </span>
                                            <div style="font-size: 0.68rem; color: #DC2626; margin-top: 2px;">
                                                <?= $po['payment_status'] === 'pay_later' ? 'Trả sau' : 'Chờ TT' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('Xác nhận bạn đã nhận đủ <?= number_format((int)$po['total_amount'], 0, ',', '.') ?>đ cho đơn <?= htmlspecialchars($po['order_code'], ENT_QUOTES, 'UTF-8') ?>?')">
                                                <input type="hidden" name="admin_action" value="reconcile_payment">
                                                <input type="hidden" name="order_code" value="<?= htmlspecialchars($po['order_code'], ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="paid_amount" value="<?= (int)$po['total_amount'] ?>">
                                                <input type="hidden" name="transaction_ref" value="ADMIN_MANUAL_MATCH">
                                                <button type="submit" class="action-btn" style="background: #ECFDF5; color: #065F46; border-color: #A7F3D0; font-weight: 700; padding: 4px 8px;">
                                                    ✓ Đã Nhận Tiền
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($orderScope === 'daily_closing'): ?>
                <!-- CHẾ ĐỘ XEM: BÁO CÁO CHỐT SỔ NGÀY (Issue #16) -->
                <div class="zen-card" style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
                        <h2 style="font-size: 0.95rem; font-weight: 700; color: var(--brand-tea-green); margin: 0;">
                            📊 Báo Cáo Chốt Sổ & Kết Quả Phục Vụ Trong Ngày
                        </h2>
                        <form method="GET" style="display: flex; gap: 6px; align-items: center;">
                            <input type="hidden" name="tab" value="orders">
                            <input type="hidden" name="scope" value="daily_closing">
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm);">Chọn ngày:</label>
                            <input type="date" name="closing_date" class="zen-input" value="<?= htmlspecialchars($closingReportDate, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()" style="padding: 5px 8px; font-size: 0.8rem;">
                        </form>
                    </div>

                    <!-- 4 Thẻ chỉ số tổng kết ngày -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px;">
                        <div style="background: #ECFDF5; border: 1px solid #A7F3D0; padding: 12px 14px; border-radius: 12px;">
                            <div style="font-size: 0.72rem; color: #065F46; font-weight: 600;">DOANH THU THỰC TẾ (ĐÃ THU)</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: #047857; margin-top: 4px;">
                                <?= number_format((int)($dailyClosingReport['paid_amount'] ?? 0), 0, ',', '.') ?>đ
                            </div>
                        </div>

                        <div style="background: #FFFBEB; border: 1px solid #FDE68A; padding: 12px 14px; border-radius: 12px;">
                            <div style="font-size: 0.72rem; color: #92400E; font-weight: 600;">DOANH THU TỒN ĐỌNG (CHỜ THU)</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: #D97706; margin-top: 4px;">
                                <?= number_format((int)($dailyClosingReport['pending_amount'] ?? 0), 0, ',', '.') ?>đ
                            </div>
                        </div>

                        <div style="background: #F0FDF4; border: 1px solid #BBF7D0; padding: 12px 14px; border-radius: 12px;">
                            <div style="font-size: 0.72rem; color: #166534; font-weight: 600;">TỔNG SUẤT MÓN ĐÃ NẤU / PHỤC VỤ</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: #15803D; margin-top: 4px;">
                                <?= (int)($dailyClosingReport['total_items_served'] ?? 0) ?> <span style="font-size: 0.85rem; font-weight: normal;">suất</span>
                            </div>
                        </div>

                        <div style="background: #F3F4F6; border: 1px solid #E5E7EB; padding: 12px 14px; border-radius: 12px;">
                            <div style="font-size: 0.72rem; color: #374151; font-weight: 600;">ĐƠN HOÀN TẤT / TỔNG ĐƠN</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: #1F2937; margin-top: 4px;">
                                <?= (int)($dailyClosingReport['completed_orders'] ?? 0) ?> / <?= (int)($dailyClosingReport['total_orders'] ?? 0) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Nút chốt sổ toàn bộ ngày -->
                    <div style="background: #FAF7F2; padding: 12px 16px; border-radius: 12px; border: 1px dashed var(--brand-border-soft); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <strong style="font-size: 0.85rem; color: var(--brand-tea-dark);">Chốt Sổ Toàn Bộ Ngày <?= date('d/m/Y', strtotime($closingReportDate)) ?>:</strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted-warm); margin-top: 2px;">
                                Khóa toàn bộ các ca trong ngày thành lịch sử đã qua, không còn hiện ở ca đang phục vụ.
                            </div>
                        </div>
                        <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn chốt sổ toàn bộ các ca trong ngày <?= date('d/m/Y', strtotime($closingReportDate)) ?>? Các ca sẽ được đánh dấu đóng và các đơn đã xác nhận sẽ hoàn tất.')">
                            <input type="hidden" name="admin_action" value="close_day">
                            <input type="hidden" name="close_date" value="<?= htmlspecialchars($closingReportDate, ENT_QUOTES, 'UTF-8') ?>">
                            <label style="font-size: 0.75rem; color: var(--brand-tea-dark); margin-right: 8px;">
                                <input type="checkbox" name="complete_orders" value="1" checked> Hoàn tất các đơn đã xác nhận
                            </label>
                            <button type="submit" class="action-btn" style="background: #1F2937; color: #FFFFFF; font-weight: 700; padding: 6px 14px;">
                                🔒 Chốt Sổ Ngày Này
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Danh sách đơn hàng trong ngày được chốt -->
                <div class="zen-card" style="padding: 0; overflow-x: auto;">
                    <div style="padding: 10px 16px; border-bottom: 1px solid var(--brand-border-soft);">
                        <strong style="font-size: 0.85rem; color: var(--brand-tea-dark);">Chi tiết tất cả đơn trong ngày <?= date('d/m/Y', strtotime($closingReportDate)) ?></strong>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Mã Đơn</th>
                                <th>Khung Giờ</th>
                                <th>Khách Hàng</th>
                                <th>Chi Tiết Món</th>
                                <th>Tổng Tiền</th>
                                <th>Thanh Toán</th>
                                <th>Trạng Thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($slotOrders)): ?>
                                <tr><td colspan="7" style="text-align: center; color: var(--text-muted-warm); padding: 24px;">Không có đơn hàng nào trong ngày này.</td></tr>
                            <?php else: ?>
                                <?php foreach ($slotOrders as $o): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($o['order_code'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td><?= htmlspecialchars((string)($o['slot_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($o['customer_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($o['customer_phone'], ENT_QUOTES, 'UTF-8') ?>)</td>
                                        <td>
                                            <?php foreach ($o['items'] as $it): ?>
                                                <div><?= htmlspecialchars($it['item_name'], ENT_QUOTES, 'UTF-8') ?> x<?= (int)$it['quantity'] ?></div>
                                            <?php endforeach; ?>
                                        </td>
                                        <td><strong><?= number_format((int)$o['total_amount'], 0, ',', '.') ?>đ</strong></td>
                                        <td>
                                            <span style="font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; <?= $o['payment_status'] === 'paid' ? 'background:#ECFDF5; color:#065F46;' : 'background:#FEF3C7; color:#92400E;' ?>">
                                                <?= $o['payment_status'] === 'paid' ? 'Đã TT' : ($o['payment_status'] === 'pay_later' ? 'Trả sau' : 'Chờ TT') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; <?= $o['order_status'] === 'completed' ? 'background:#EFF6FF; color:#1E40AF;' : 'background:#F3F4F6; color:#374151;' ?>">
                                                <?= $o['order_status'] === 'completed' ? 'Đã hoàn tất' : $o['order_status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <!-- CHẾ ĐỘ XEM MẶC ĐỊNH: BẢNG BẾP HÔM NAY HOẶC THEO TỪNG CA -->

                <?php 
                $activeSlotInfo = null;
                if ($selectedSlotId > 0) {
                    foreach ($allUpcomingAndTodaySlots as $sl) {
                        if ((int)$sl['id'] === $selectedSlotId) {
                            $activeSlotInfo = $sl;
                            break;
                        }
                    }
                }
                ?>

                <?php if ($activeSlotInfo): ?>
                    <!-- Thông tin ca đang chọn và nút Chốt Ca (Issue #16) -->
                    <div class="zen-card" style="margin-bottom: 14px; background: #FAF7F2; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <div style="font-size: 0.75rem; color: var(--brand-tea-green); font-weight: 600;">ĐANG XEM CHI TIẾT CA:</div>
                            <h3 style="font-size: 1.05rem; color: var(--brand-tea-dark); margin: 2px 0;">
                                <?= htmlspecialchars($activeSlotInfo['slot_date'] . ' • ' . $activeSlotInfo['slot_time'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($activeSlotInfo['is_closed'])): ?>
                                    <span style="font-size: 0.72rem; background: #E5E7EB; color: #374151; padding: 2px 8px; border-radius: 6px; font-weight: normal; margin-left: 6px;">🔒 Đã Chốt Ca (Lịch sử)</span>
                                <?php else: ?>
                                    <span style="font-size: 0.72rem; background: #ECFDF5; color: #065F46; padding: 2px 8px; border-radius: 6px; font-weight: normal; margin-left: 6px;">🟢 Đang Phục Vụ</span>
                                <?php endif; ?>
                            </h3>
                            <div style="font-size: 0.75rem; color: var(--text-muted-warm);">
                                Đã đặt: <strong><?= (int)$activeSlotInfo['booked_count'] ?></strong> / <?= (int)$activeSlotInfo['max_capacity'] ?> suất
                            </div>
                        </div>

                        <?php if (empty($activeSlotInfo['is_closed'])): ?>
                            <form method="POST" onsubmit="return confirm('Bạn muốn chốt ca này? Các đơn hàng đã xác nhận trong ca sẽ được đánh dấu hoàn thành và ca sẽ đóng lại.')">
                                <input type="hidden" name="admin_action" value="close_slot">
                                <input type="hidden" name="slot_id" value="<?= (int)$activeSlotInfo['id'] ?>">
                                <input type="hidden" name="complete_orders" value="1">
                                <button type="submit" class="action-btn" style="background: #1F2937; color: #FFFFFF; font-weight: 700; padding: 7px 14px;">
                                    🔒 Chốt Ca Này (Hoàn tất)
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

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
            <?php endif; ?>

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
            <!-- TAB 4: Quản Lý Thực Đơn Nâng Cao (Issue #17) -->
            
            <!-- Thanh Subtab Thực Đơn -->
            <div style="display: flex; gap: 8px; margin-bottom: 16px; border-bottom: 1.5px solid var(--brand-border-soft); padding-bottom: 10px; flex-wrap: wrap;">
                <a href="admin.php?tab=menu&menu_subtab=items" class="action-btn" style="<?= $menuSubtab === 'items' ? 'background: var(--brand-tea-green); color: #FFF; font-weight: 700; border-color: var(--brand-tea-green);' : '' ?>">🥢 Danh Sách Món & Mâm Cỗ</a>
                <a href="admin.php?tab=menu&menu_subtab=suites" class="action-btn" style="<?= $menuSubtab === 'suites' ? 'background: var(--brand-tea-green); color: #FFF; font-weight: 700; border-color: var(--brand-tea-green);' : '' ?>">🍱 Bộ Thực Đơn Mẫu (<?= count($allSuites) ?>)</a>
                <a href="admin.php?tab=menu&menu_subtab=schedule" class="action-btn" style="<?= $menuSubtab === 'schedule' ? 'background: var(--brand-tea-green); color: #FFF; font-weight: 700; border-color: var(--brand-tea-green);' : '' ?>">📅 Lên Lịch & Xoay Tua</a>
                <a href="admin.php?tab=menu&menu_subtab=impact" class="action-btn" style="<?= $menuSubtab === 'impact' ? 'background: #DC2626; color: #FFF; font-weight: 700; border-color: #DC2626;' : ($impactItemId > 0 ? 'background: #FEE2E2; color: #B91C1C; font-weight: 600;' : '') ?>">
                    ⚠️ Cảnh Báo Sửa Món <?= $impactItemId > 0 ? '(Đang soi món #' . $impactItemId . ')' : '' ?>
                </a>
            </div>

            <?php if ($menuSubtab === 'items'): ?>
                <!-- SUBTAB 1: Danh Sách Món & Mâm Cỗ -->
                <div class="zen-card" style="margin-bottom: 16px;">
                    <h3 style="font-size: 0.92rem; font-weight: 700; color: var(--brand-tea-green); margin-bottom: 12px;">+ Thêm Món / Mâm Cỗ Mới</h3>
                    <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: flex-end;">
                        <input type="hidden" name="admin_action" value="create_menu_item">
                        <div>
                            <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Tên món / Tên mâm cỗ:</label>
                            <input type="text" name="name" class="zen-input" placeholder="Ví dụ: Mâm Cỗ An Lạc, Phở nấm..." required>
                        </div>
                        <div>
                            <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Đơn giá (VND):</label>
                            <input type="number" name="price" class="zen-input" value="45000" min="0" required>
                        </div>
                        <div>
                            <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Phân loại:</label>
                            <select name="category" class="zen-input">
                                <option value="food">Món ăn chính (food)</option>
                                <option value="drink">Thức uống thanh nhiệt (drink)</option>
                                <option value="extra">Món thêm / Tráng miệng (extra)</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Phạm vi phục vụ:</label>
                            <select name="service_scope" class="zen-input">
                                <option value="both">🌿 Cả tại quán & Mang đi</option>
                                <option value="dine_in_only">🍵 Chỉ dùng tại quán</option>
                                <option value="takeaway_only">🥡 Chỉ giao mang về</option>
                            </select>
                        </div>
                        <div style="grid-column: 1 / -1; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 250px;">
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Mô tả / Các món trong mâm cỗ:</label>
                                <input type="text" name="description" class="zen-input" placeholder="Ghi chú thành phần món hoặc số người phù hợp...">
                            </div>
                            <div style="padding-top: 18px;">
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; color: #92400E; background: #FEF3C7; padding: 7px 12px; border-radius: 8px;">
                                    <input type="checkbox" name="is_feast_combo" value="1">
                                    🍱 Là Mâm Cỗ Chay Trọn Vị
                                </label>
                            </div>
                            <div style="padding-top: 18px;">
                                <button type="submit" class="zen-btn-primary" style="padding: 10px 18px; border-radius: 10px; font-size: 0.85rem; white-space: nowrap;">
                                    + Lưu Món
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="zen-card" style="padding: 0; overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tên Món & Phân Loại</th>
                                <th>Mô Tả Chi Tiết</th>
                                <th>Phục Vụ</th>
                                <th>Đơn Giá</th>
                                <th>Trạng Thái</th>
                                <th>Thao Tác & Cảnh Báo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($menuList as $m): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--brand-tea-dark);">
                                            <?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div style="display: flex; gap: 4px; margin-top: 4px; flex-wrap: wrap;">
                                            <?php if (!empty($m['is_feast_combo'])): ?>
                                                <span style="background: #FEF3C7; color: #92400E; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">🍱 Mâm Cỗ</span>
                                            <?php endif; ?>
                                            <?php if ($m['category'] === 'drink'): ?>
                                                <span style="background: #E0F2FE; color: #0369A1; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px;">🍹 Đồ Uống</span>
                                            <?php elseif ($m['category'] === 'food'): ?>
                                                <span style="background: #ECFDF5; color: #047857; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px;">🍲 Món Ăn</span>
                                            <?php else: ?>
                                                <span style="background: #F3F4F6; color: #4B5563; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px;">Thêm</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="font-size: 0.75rem; color: var(--text-muted-warm); max-width: 240px;">
                                        <?= htmlspecialchars((string)($m['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $scope = (string)($m['service_scope'] ?? 'both');
                                        if ($scope === 'dine_in_only') {
                                            echo '<span style="font-size: 0.72rem; color: #2563EB; font-weight: 600;">🍵 Chỉ tại quán</span>';
                                        } elseif ($scope === 'takeaway_only') {
                                            echo '<span style="font-size: 0.72rem; color: #D97706; font-weight: 600;">🥡 Chỉ mang đi</span>';
                                        } else {
                                            echo '<span style="font-size: 0.72rem; color: #059669;">🌿 Cả hai</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: flex; gap: 4px; align-items: center;">
                                            <input type="hidden" name="admin_action" value="update_price">
                                            <input type="hidden" name="item_id" value="<?= (int)$m['id'] ?>">
                                            <input type="number" name="price" value="<?= (int)$m['price'] ?>" style="width: 78px; font-size: 0.8rem; padding: 4px 6px; border: 1px solid var(--brand-border-soft); border-radius: 6px;">
                                            <button type="submit" class="action-btn" title="Cập nhật giá">Lưu</button>
                                        </form>
                                    </td>
                                    <td>
                                        <?= (int)$m['is_available'] === 1 ? '<span style="color:#059669; font-weight:600;">Đang phục vụ</span>' : '<span style="color:#DC2626; font-weight:600;">Tạm hết</span>' ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="admin_action" value="toggle_menu">
                                                <input type="hidden" name="item_id" value="<?= (int)$m['id'] ?>">
                                                <input type="hidden" name="is_available" value="<?= (int)$m['is_available'] === 1 ? '0' : '1' ?>">
                                                <button type="submit" class="action-btn">
                                                    <?= (int)$m['is_available'] === 1 ? 'Tắt món' : 'Bật phục vụ' ?>
                                                </button>
                                            </form>
                                            <a href="admin.php?tab=menu&menu_subtab=impact&check_impact_item_id=<?= (int)$m['id'] ?>" class="action-btn" style="color: #B91C1C; border-color: #FCA5A5; text-decoration: none;" title="Kiểm tra xem đã có khách nào đặt món này cho các ca sắp tới chưa">
                                                ⚠️ Soi đơn đặt
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($menuSubtab === 'suites'): ?>
                <!-- SUBTAB 2: Bộ Thực Đơn Mẫu (Menu Suites) -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <!-- Form tạo Suite mới -->
                    <div class="zen-card">
                        <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--brand-tea-green); margin-bottom: 12px;">+ Tạo Bộ Thực Đơn Mẫu Mới</h3>
                        <form method="POST">
                            <input type="hidden" name="admin_action" value="create_menu_suite">
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Tên bộ thực đơn:</label>
                                <input type="text" name="name" class="zen-input" placeholder="Ví dụ: Thực Đơn Ngày Rằm, Thực Đơn Mùa Vu Lan..." required>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Ghi chú / Mô tả:</label>
                                <input type="text" name="description" class="zen-input" placeholder="Mô tả tóm tắt phong vị ẩm thực...">
                            </div>
                            <div style="margin-bottom: 12px;">
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; cursor: pointer;">
                                    <input type="checkbox" name="is_default" value="1">
                                    Đặt làm bộ thực đơn mặc định của quán
                                </label>
                            </div>
                            <div style="margin-bottom: 14px;">
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 6px; font-weight: 700;">Chọn các món & mâm cỗ thuộc bộ này:</label>
                                <div style="max-height: 220px; overflow-y: auto; border: 1px solid var(--brand-border-soft); border-radius: 8px; padding: 8px; display: grid; grid-template-columns: 1fr; gap: 6px; background: #FAF8F5;">
                                    <?php foreach ($menuList as $m): ?>
                                        <label style="display: flex; align-items: center; gap: 8px; font-size: 0.78rem; cursor: pointer; padding: 2px 4px;">
                                            <input type="checkbox" name="item_ids[]" value="<?= (int)$m['id'] ?>">
                                            <span>
                                                <strong><?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span style="color: var(--text-muted-warm); font-size: 0.7rem;">(<?= number_format($m['price'], 0, ',', '.') ?>đ)</span>
                                                <?php if (!empty($m['is_feast_combo'])): ?>
                                                    <span style="background: #FEF3C7; color: #92400E; font-size: 0.65rem; padding: 1px 4px; border-radius: 4px;">🍱 Mâm cỗ</span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button type="submit" class="zen-btn-primary" style="width: 100%; padding: 10px; border-radius: 8px; font-size: 0.85rem;">
                                + Lưu Bộ Thực Đơn Mẫu
                            </button>
                        </form>
                    </div>

                    <!-- Danh sách các Suite hiện có -->
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($allSuites as $suite): ?>
                            <?php $suiteItems = $menuService->getSuiteItems((int)$suite['id']); ?>
                            <div class="zen-card" style="border-left: 4px solid <?= (int)$suite['is_default'] === 1 ? 'var(--brand-tea-green)' : 'var(--brand-border-soft)' ?>;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div>
                                        <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--brand-tea-dark);">
                                            <?= htmlspecialchars($suite['name'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ((int)$suite['is_default'] === 1): ?>
                                                <span style="background: #ECFDF5; color: #047857; font-size: 0.68rem; padding: 2px 6px; border-radius: 999px; margin-left: 6px; font-weight: 700;">★ Mặc Định</span>
                                            <?php endif; ?>
                                        </h4>
                                        <p style="font-size: 0.76rem; color: var(--text-muted-warm); margin-top: 2px;">
                                            <?= htmlspecialchars((string)($suite['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    </div>
                                    <div style="display: flex; gap: 6px;">
                                        <?php if ((int)$suite['is_default'] !== 1): ?>
                                            <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa bộ thực đơn này?');">
                                                <input type="hidden" name="admin_action" value="delete_menu_suite">
                                                <input type="hidden" name="suite_id" value="<?= (int)$suite['id'] ?>">
                                                <button type="submit" class="action-btn" style="color: #DC2626; border-color: #FCA5A5;">Xóa</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed var(--brand-border-soft);">
                                    <div style="font-size: 0.72rem; color: var(--text-muted-warm); margin-bottom: 4px;">
                                        Các món phục vụ (<?= count($suiteItems) ?> món):
                                    </div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                        <?php foreach ($suiteItems as $si): ?>
                                            <span style="font-size: 0.7rem; background: #F4EFEA; padding: 2px 6px; border-radius: 6px; color: #4B5563;">
                                                <?= htmlspecialchars($si['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($menuSubtab === 'schedule'): ?>
                <!-- SUBTAB 3: Lên Lịch & Quy Tắc Xoay Tua -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;">
                    <!-- Card 1: Quy tắc xoay tua tự động -->
                    <div class="zen-card">
                        <h3 style="font-size: 0.92rem; font-weight: 700; color: var(--brand-tea-green); margin-bottom: 8px;">
                            🔄 Quy Tắc Xoay Tua Tự Động
                        </h3>
                        <p style="font-size: 0.75rem; color: var(--text-muted-warm); margin-bottom: 12px;">
                            Hệ thống tự động đổi thực đơn mỗi ngày mà không cần bếp phải cài đặt thủ công liên tục.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="admin_action" value="update_menu_rules">
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Chế độ áp dụng:</label>
                                <select name="menu_schedule_rule" class="zen-input">
                                    <option value="default" <?= $menuScheduleRule === 'default' ? 'selected' : '' ?>>Luôn dùng bộ mặc định</option>
                                    <option value="even_odd" <?= $menuScheduleRule === 'even_odd' ? 'selected' : '' ?>>Tự động xoay tua Ngày Chẵn / Lẻ</option>
                                </select>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Bộ thực đơn Ngày Chẵn (T2, T4, T6):</label>
                                <select name="menu_even_suite_id" class="zen-input">
                                    <?php foreach ($allSuites as $s): ?>
                                        <option value="<?= (int)$s['id'] ?>" <?= $menuEvenSuiteId === (int)$s['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Bộ thực đơn Ngày Lẻ (T3, T5, T7, CN):</label>
                                <select name="menu_odd_suite_id" class="zen-input">
                                    <?php foreach ($allSuites as $s): ?>
                                        <option value="<?= (int)$s['id'] ?>" <?= $menuOddSuiteId === (int)$s['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="zen-btn-primary" style="width: 100%; padding: 10px; border-radius: 8px; font-size: 0.85rem;">
                                Lưu Quy Tắc Xoay Tua
                            </button>
                        </form>
                    </div>

                    <!-- Card 2: Chỉ định riêng cho từng ngày (Override) -->
                    <div class="zen-card">
                        <h3 style="font-size: 0.92rem; font-weight: 700; color: var(--brand-tea-green); margin-bottom: 8px;">
                            🎯 Chỉ Định Thực Đơn Cho Ngày Cụ Thể
                        </h3>
                        <p style="font-size: 0.75rem; color: var(--text-muted-warm); margin-bottom: 12px;">
                            Ghi đè thực đơn cho ngày lễ, ngày Rằm 15, Mùng 1 hoặc tiệc mâm cỗ đặt riêng.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="admin_action" value="assign_menu_schedule">
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Chọn ngày:</label>
                                <input type="date" name="schedule_date" class="zen-input" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Áp dụng bộ thực đơn:</label>
                                <select name="suite_id" class="zen-input">
                                    <?php foreach ($allSuites as $s): ?>
                                        <option value="<?= (int)$s['id'] ?>">
                                            <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Ghi chú ngày (tùy chọn):</label>
                                <input type="text" name="note" class="zen-input" placeholder="Ví dụ: Rằm tháng Giêng, Mâm cỗ tiệc...">
                            </div>
                            <button type="submit" class="zen-btn-primary" style="width: 100%; padding: 10px; border-radius: 8px; font-size: 0.85rem;">
                                Áp Dụng Cho Ngày Này
                            </button>
                        </form>
                    </div>

                    <!-- Card 3: Nhân bản hàng loạt theo tuần/tháng -->
                    <div class="zen-card">
                        <h3 style="font-size: 0.92rem; font-weight: 700; color: var(--brand-tea-green); margin-bottom: 8px;">
                            📑 Nhân Bản Lịch Thực Đơn Hàng Loạt
                        </h3>
                        <p style="font-size: 0.75rem; color: var(--text-muted-warm); margin-bottom: 12px;">
                            Gán một bộ thực đơn cho chuỗi ngày liên tiếp hoặc các ngày cố định trong tuần.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="admin_action" value="replicate_menu_schedule">
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Bộ thực đơn:</label>
                                <select name="suite_id" class="zen-input">
                                    <?php foreach ($allSuites as $s): ?>
                                        <option value="<?= (int)$s['id'] ?>">
                                            <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px;">
                                <div>
                                    <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block;">Từ ngày:</label>
                                    <input type="date" name="start_date" class="zen-input" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div>
                                    <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block;">Đến ngày:</label>
                                    <input type="date" name="end_date" class="zen-input" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                                </div>
                            </div>
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Lọc các thứ trong tuần (để trống = tất cả):</label>
                                <div style="display: flex; gap: 6px; flex-wrap: wrap; font-size: 0.72rem;">
                                    <label><input type="checkbox" name="days_of_week[]" value="1"> T2</label>
                                    <label><input type="checkbox" name="days_of_week[]" value="2"> T3</label>
                                    <label><input type="checkbox" name="days_of_week[]" value="3"> T4</label>
                                    <label><input type="checkbox" name="days_of_week[]" value="4"> T5</label>
                                    <label><input type="checkbox" name="days_of_week[]" value="5"> T6</label>
                                    <label><input type="checkbox" name="days_of_week[]" value="6"> T7</label>
                                    <label><input type="checkbox" name="days_of_week[]" value="0"> CN</label>
                                </div>
                            </div>
                            <button type="submit" class="zen-btn-primary" style="width: 100%; padding: 10px; border-radius: 8px; font-size: 0.85rem;">
                                Nhân Bản Cho Chuỗi Ngày
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Danh sách ngày đã chỉ định riêng -->
                <div class="zen-card" style="padding: 0; overflow-x: auto;">
                    <div style="padding: 12px 16px; border-bottom: 1px solid var(--brand-border-soft); font-weight: 700; color: var(--brand-tea-green); font-size: 0.9rem;">
                        📅 Các Ngày Đã Chỉ Định Thực Đơn Riêng Trong 30 Ngày Tới (<?= count($upcomingSchedules) ?> ngày)
                    </div>
                    <?php if (empty($upcomingSchedules)): ?>
                        <div style="padding: 24px; text-align: center; color: var(--text-muted-warm); font-size: 0.82rem;">
                            Chưa có ngày nào được chỉ định riêng. Mọi ngày hiện đang tự động xoay tua theo <strong>Quy Tắc Xoay Tua Tự Động</strong> ở trên.
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Ngày</th>
                                    <th>Bộ Thực Đơn Áp Dụng</th>
                                    <th>Ghi Chú Ngày</th>
                                    <th>Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingSchedules as $sch): ?>
                                    <tr>
                                        <td><strong><?= date('d/m/Y (l)', strtotime($sch['schedule_date'])) ?></strong></td>
                                        <td>
                                            <span style="background: #ECFDF5; color: #047857; font-weight: 600; padding: 2px 8px; border-radius: 6px; font-size: 0.78rem;">
                                                🍱 <?= htmlspecialchars($sch['suite_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 0.78rem; color: var(--text-muted-warm);"><?= htmlspecialchars((string)($sch['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="admin_action" value="remove_menu_schedule">
                                                <input type="hidden" name="schedule_date" value="<?= htmlspecialchars($sch['schedule_date'], ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="action-btn" style="color: #DC2626;">Hủy Chỉ Định</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            <?php elseif ($menuSubtab === 'impact'): ?>
                <!-- SUBTAB 4: Cảnh Báo Thay Đổi Món & Thông Báo Khách Hàng (Issue #17) -->
                <div class="zen-card" style="margin-bottom: 16px;">
                    <h3 style="font-size: 0.95rem; font-weight: 700; color: #B91C1C; margin-bottom: 8px;">
                        ⚠️ Kiểm Tra Ảnh Hưởng Khi Sửa / Hết Món Đã Được Khách Đặt
                    </h3>
                    <p style="font-size: 0.78rem; color: var(--text-muted-warm); margin-bottom: 12px;">
                        Khi bếp đổi giá, hết món hoặc muốn thay món tương đương, chọn món bên dưới để kiểm tra toàn bộ các đơn đặt trước từ hôm nay trở đi bị ảnh hưởng và hỗ trợ thông báo tức thì cho khách.
                    </p>
                    <form method="GET" style="display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap;">
                        <input type="hidden" name="tab" value="menu">
                        <input type="hidden" name="menu_subtab" value="impact">
                        <div style="flex: 1; min-width: 260px;">
                            <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Chọn món ăn / mâm cỗ cần kiểm tra:</label>
                            <select name="check_impact_item_id" class="zen-input" required>
                                <option value="">-- Chọn món cần soi đơn đặt trước --</option>
                                <?php foreach ($menuList as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>" <?= $impactItemId === (int)$m['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?> (<?= number_format($m['price'], 0, ',', '.') ?>đ)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="zen-btn-primary" style="padding: 10px 18px; border-radius: 10px; font-size: 0.85rem;">
                            🔍 Soi Đơn Đặt Trước
                        </button>
                    </form>
                </div>

                <?php if ($impactData): ?>
                    <div class="zen-card" style="margin-bottom: 16px; border-left: 4px solid <?= count($impactData['affected_orders']) > 0 ? '#DC2626' : '#10B981' ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <h4 style="font-size: 1rem; font-weight: 700; color: var(--brand-tea-dark);">
                                    Món đang soi: <span style="color: var(--brand-tea-green);"><?= htmlspecialchars($impactData['item']['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </h4>
                                <p style="font-size: 0.8rem; color: var(--text-muted-warm); margin-top: 4px;">
                                    Đơn giá hiện hành: <strong><?= number_format($impactData['item']['price'], 0, ',', '.') ?>đ</strong> • Trạng thái: <?= (int)$impactData['item']['is_available'] === 1 ? 'Đang phục vụ' : 'Tạm hết' ?>
                                </p>
                            </div>
                            <div>
                                <span style="background: <?= count($impactData['affected_orders']) > 0 ? '#FEE2E2' : '#ECFDF5' ?>; color: <?= count($impactData['affected_orders']) > 0 ? '#B91C1C' : '#047857' ?>; padding: 6px 12px; border-radius: 999px; font-weight: 700; font-size: 0.82rem;">
                                    <?= count($impactData['affected_orders']) ?> đơn đã đặt • <?= (int)$impactData['total_quantity'] ?> suất cần phục vụ
                                </span>
                            </div>
                        </div>
                    </div>

                    <?php if (count($impactData['affected_orders']) === 0): ?>
                        <div class="zen-card" style="text-align: center; padding: 32px; color: #047857; background: #F0FDF4;">
                            <div style="font-size: 2rem; margin-bottom: 8px;">✓</div>
                            <strong>Yên tâm! Chưa có khách nào đặt trước món này cho các ca từ hôm nay trở đi.</strong>
                            <p style="font-size: 0.78rem; color: var(--text-muted-warm); margin-top: 4px;">Bếp có thể thoải mái sửa giá, sửa mô tả hoặc tạm tắt món mà không làm ảnh hưởng đến đơn của khách.</p>
                        </div>
                    <?php else: ?>
                        <!-- Form Thông báo đồng loạt vào Outbox & Gợi ý Zalo -->
                        <div class="zen-card" style="margin-bottom: 16px; background: #FFFBF0; border: 1.5px solid #FDE68A;">
                            <h4 style="font-size: 0.92rem; font-weight: 700; color: #92400E; margin-bottom: 8px;">
                                📢 Gửi Thông Báo Vào Hàng Đợi Cho <?= count($impactData['affected_orders']) ?> Khách Hàng Đã Đặt Món Này
                            </h4>
                            <p style="font-size: 0.75rem; color: #78350F; margin-bottom: 12px;">
                                Thông báo sẽ được lưu vào hàng đợi Outbox của từng khách hàng và hiển thị trên màn hình tra cứu đơn của khách.
                            </p>
                            <form method="POST">
                                <input type="hidden" name="admin_action" value="notify_menu_change">
                                <input type="hidden" name="item_id" value="<?= (int)$impactData['item']['id'] ?>">
                                <input type="hidden" name="from_date" value="<?= date('Y-m-d') ?>">
                                <div style="margin-bottom: 10px;">
                                    <label style="font-size: 0.72rem; color: #78350F; display: block; margin-bottom: 4px;">Tiêu đề thông báo:</label>
                                    <input type="text" name="change_title" class="zen-input" value="Bếp An Yên: Thông báo thay đổi món <?= htmlspecialchars($impactData['item']['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <label style="font-size: 0.72rem; color: #78350F; display: block; margin-bottom: 4px;">Nội dung nhắn gửi khách:</label>
                                    <textarea name="change_message" class="zen-input" rows="3" required>Dạ chào bạn, Bếp Chay An Yên xin phép thông báo về món [<?= htmlspecialchars($impactData['item']['name'], ENT_QUOTES, 'UTF-8') ?>] trong đơn đặt của bạn. Bếp xin được điều chỉnh/đổi món tương đương giúp bạn hoặc hoàn tiền chênh lệch nhé ạ!</textarea>
                                </div>
                                <button type="submit" class="zen-btn-primary" style="background: #D97706; padding: 10px 18px; border-radius: 8px; font-size: 0.85rem;">
                                    📤 Xếp Lịch Gửi Thông Báo Cho Khách
                                </button>
                            </form>
                        </div>

                        <!-- Danh sách các đơn cụ thể và nút nhắn Zalo từng người -->
                        <div class="zen-card" style="padding: 0; overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Mã Đơn</th>
                                        <th>Khách Hàng</th>
                                        <th>SĐT</th>
                                        <th>Ngày & Giờ Ca</th>
                                        <th>Số Lượng Đặt</th>
                                        <th>Thao Tác Khẩn Cấp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($impactData['affected_orders'] as $ord): ?>
                                        <?php 
                                        $cleanPhone = preg_replace('/\D+/', '', (string)$ord['customer_phone']);
                                        $zaloMsg = "Chào bạn " . ($ord['customer_name'] ?? 'bạn') . ", Bếp Chay An Yên xin liên hệ về đơn #" . $ord['order_id'] . " (ca " . $ord['slot_time'] . " ngày " . date('d/m', strtotime($ord['slot_date'])) . "). Về món " . $impactData['item']['name'] . " bạn đặt, bếp xin phép được thông báo điều chỉnh nhé ạ!";
                                        ?>
                                        <tr>
                                            <td><strong>#<?= (int)$ord['order_id'] ?></strong></td>
                                            <td><?= htmlspecialchars((string)($ord['customer_name'] ?? 'Khách'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($ord['customer_phone'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($ord['slot_date'])) ?> 
                                                <span style="font-size: 0.75rem; color: var(--text-muted-warm);">(<?= htmlspecialchars($ord['slot_time'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                            </td>
                                            <td>
                                                <span style="background: #FEE2E2; color: #B91C1C; font-weight: 700; padding: 2px 8px; border-radius: 6px; font-size: 0.8rem;">
                                                    <?= (int)$ord['quantity'] ?> suất
                                                </span>
                                            </td>
                                            <td>
                                                <a href="https://zalo.me/<?= urlencode($cleanPhone) ?>" target="_blank" class="action-btn" style="color: #0068FF; border-color: #93C5FD; text-decoration: none; font-weight: 600;">
                                                    💬 Mở Zalo Khách
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            <?php endif; ?>

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

        <?php elseif ($currentTab === 'promotions'): ?>
            <!-- TAB: Quản Lý Khuyến Mại & Giảm Giá (Issue #20) -->
            <div class="zen-card" style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h2 style="font-size: 1.05rem; font-weight: 700; color: #EA580C; margin-bottom: 2px;">
                            🔥 Quản Lý Chương Trình Khuyến Mại & Ưu Đãi
                        </h2>
                        <p style="font-size: 0.78rem; color: var(--text-muted-warm);">
                            Thiết lập giảm giá theo thời gian, theo ngày Âm lịch (Mùng 1 & Rằm), cho từng món hoặc mâm cỗ chay.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Thống kê nhanh khuyến mại -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px;">
                <div class="zen-card" style="padding: 14px; text-align: center; border-left: 4px solid #EA580C;">
                    <p style="font-size: 0.72rem; color: var(--text-muted-warm); text-transform: uppercase;">🔥 Đang Hoạt Động</p>
                    <p style="font-size: 1.6rem; font-weight: 700; color: #EA580C; margin-top: 4px;">
                        <?= count(array_filter($allPromotions, fn($p) => (int)$p['is_active'] === 1)) ?>
                    </p>
                </div>
                <div class="zen-card" style="padding: 14px; text-align: center; border-left: 4px solid #F59E0B;">
                    <p style="font-size: 0.72rem; color: var(--text-muted-warm); text-transform: uppercase;">🌕 Ưu Đãi Mùng 1 & Rằm</p>
                    <p style="font-size: 1.6rem; font-weight: 700; color: #D97706; margin-top: 4px;">
                        <?= count(array_filter($allPromotions, fn($p) => !empty($p['apply_on_lunar_days']))) ?>
                    </p>
                </div>
                <div class="zen-card" style="padding: 14px; text-align: center; border-left: 4px solid #6B7280;">
                    <p style="font-size: 0.72rem; color: var(--text-muted-warm); text-transform: uppercase;">📋 Tổng Chương Trình</p>
                    <p style="font-size: 1.6rem; font-weight: 700; color: #4B5563; margin-top: 4px;">
                        <?= count($allPromotions) ?>
                    </p>
                </div>
            </div>

            <!-- Form Thêm Khuyến Mại Mới -->
            <div class="zen-card" style="margin-bottom: 20px;">
                <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--brand-tea-dark); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <span>✨</span> Tạo Chương Trình Khuyến Mại Mới
                </h3>
                <form method="POST" style="display: flex; flex-direction: column; gap: 14px;">
                    <input type="hidden" name="admin_action" value="create_promotion">

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px;">
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;"><strong>Tên chương trình khuyến mại (*):</strong></label>
                            <input type="text" name="title" class="zen-input" placeholder="Ví dụ: Ưu Đãi Ngày Rằm & Mùng 1" required>
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Mã khuyến mại (Code - tuỳ chọn):</label>
                            <input type="text" name="code" class="zen-input" placeholder="Ví dụ: RAMANYEN15">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;"><strong>Loại giảm giá:</strong></label>
                            <select name="discount_type" class="zen-input">
                                <option value="percentage">Giảm theo Phần trăm (%)</option>
                                <option value="fixed_amount">Giảm theo Số tiền cố định (đ)</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;"><strong>Mức giảm (*):</strong></label>
                            <input type="number" name="discount_value" class="zen-input" placeholder="Ví dụ: 15 (với %) hoặc 20000 (với đ)" min="1" required>
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Đơn tối thiểu để áp dụng (đ):</label>
                            <input type="number" name="min_order_amount" class="zen-input" value="0" min="0" placeholder="0 = không yêu cầu">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;"><strong>Đối tượng áp dụng:</strong></label>
                            <select name="applies_to" id="promoAppliesTo" class="zen-input" onchange="
                                document.getElementById('targetCatWrapper').style.display = (this.value === 'category' ? 'block' : 'none');
                                document.getElementById('targetItemWrapper').style.display = (this.value === 'item' ? 'block' : 'none');
                            ">
                                <option value="all">Toàn bộ đơn hàng</option>
                                <option value="category">Theo danh mục món</option>
                                <option value="feast_combo">Mâm cỗ chay trọn vị</option>
                                <option value="item">Món ăn cụ thể</option>
                            </select>
                        </div>

                        <div id="targetCatWrapper" style="display: none;">
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Chọn danh mục:</label>
                            <select name="target_category" class="zen-input">
                                <option value="food">Món chính (Bún, Phở, Cơm)</option>
                                <option value="drink">Đồ uống / Trà</option>
                                <option value="extra">Món thêm</option>
                            </select>
                        </div>

                        <div id="targetItemWrapper" style="display: none;">
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Chọn món ăn:</label>
                            <select name="target_id" class="zen-input">
                                <?php foreach ($menuList as $mItem): ?>
                                    <option value="<?= (int)$mItem['id'] ?>">
                                        <?= htmlspecialchars($mItem['name']) ?> (<?= number_format((int)$mItem['price'], 0, ',', '.') ?>đ)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Huy hiệu hiển thị (Badge):</label>
                            <input type="text" name="badge_text" class="zen-input" placeholder="Ví dụ: 🔥 Giảm 15% hoặc 🌕 Ưu Đãi Rằm">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; background: #FAF7F2; padding: 12px; border-radius: 12px;">
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;"><strong>Từ ngày (Dương lịch):</strong></label>
                            <input type="date" name="start_date" class="zen-input" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;"><strong>Đến hết ngày:</strong></label>
                            <input type="date" name="end_date" class="zen-input" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                        </div>
                        <div style="display: flex; flex-direction: column; justify-content: center;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.8rem; font-weight: 700; color: #9A3412;">
                                <input type="checkbox" name="apply_on_lunar_days" value="1,15" style="width: 16px; height: 16px;">
                                <span>🌕 Chỉ kích hoạt ngày Mùng 1 & Rằm (Âm lịch)</span>
                            </label>
                            <span style="font-size: 0.68rem; color: var(--text-muted-warm); margin-top: 2px;">
                                Tự động nhận diện Mùng 1 & Rằm hàng tháng không cần cài đặt lại
                            </span>
                        </div>
                    </div>

                    <!-- Thiết lập mục tiêu khách hàng & đơn hàng (Issue #26) -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; background: #F4EFEA; padding: 12px; border-radius: 12px; border: 1px dashed #D6C7B2;">
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;"><strong>Nhóm khách hàng:</strong></label>
                            <select name="target_customer_group" class="zen-input">
                                <option value="all">Tất cả khách hàng</option>
                                <option value="trusted">⭐️ Khách quen đã xác thực (is_trusted = 1)</option>
                                <option value="new">🌱 Khách mới (chưa có đơn trước đó)</option>
                                <option value="loyal">💎 Khách thân thiết (≥ 3 đơn hoàn thành / quen)</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Khách hàng cụ thể (SĐT):</label>
                            <input type="text" name="target_customer_phone" class="zen-input" placeholder="Để trống = áp dụng chung">
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;"><strong>Nhóm đơn hàng:</strong></label>
                            <select name="target_order_type" class="zen-input">
                                <option value="all">Tất cả hình thức</option>
                                <option value="dine_in">🍽 Chỉ dùng tại quán (dine_in)</option>
                                <option value="takeaway">🛵 Chỉ giao ship mang về (takeaway)</option>
                            </select>
                        </div>
                        <div style="display: flex; flex-direction: column; justify-content: center;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.8rem; font-weight: 700; color: #166534;">
                                <input type="checkbox" name="first_order_only" value="1" style="width: 16px; height: 16px;">
                                <span>🌱 Chỉ áp dụng cho đơn đầu tiên</span>
                            </label>
                            <span style="font-size: 0.68rem; color: var(--text-muted-warm); margin-top: 2px;">
                                Tự động giới hạn mỗi số điện thoại chỉ áp dụng 1 lần
                            </span>
                        </div>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted-warm); display: block; margin-bottom: 4px;">Mô tả / Lưu ý cho khách:</label>
                        <input type="text" name="description" class="zen-input" placeholder="Ví dụ: Áp dụng cho cả ăn tại quán và mang về">
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="zen-btn-primary" style="width: auto; padding: 10px 24px;">
                            <span>Lưu & Kích Hoạt Khuyến Mại</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bảng danh sách các chương trình khuyến mại -->
            <div class="zen-card">
                <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--brand-tea-dark); margin-bottom: 12px;">
                    📜 Danh Sách Chương Trình Khuyến Mại Hiện Có
                </h3>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tên & Huy Hiệu</th>
                            <th>Mức Giảm</th>
                            <th>Đối Tượng</th>
                            <th>Thời Gian Áp Dụng</th>
                            <th>Trạng Thái</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allPromotions)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted-warm); padding: 24px;">
                                    Chưa có chương trình khuyến mại nào. Hãy tạo chương trình đầu tiên ở trên nhé!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allPromotions as $promo): 
                                $isPct = ($promo['discount_type'] === 'percentage');
                                $discountStr = $isPct ? "{$promo['discount_value']}%" : number_format((int)$promo['discount_value'], 0, ',', '.') . 'đ';
                                $isActive = (int)$promo['is_active'] === 1;
                            ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--brand-tea-dark); display: flex; align-items: center; gap: 6px;">
                                            <span><?= htmlspecialchars($promo['title']) ?></span>
                                            <?php if (!empty($promo['badge_text'])): ?>
                                                <span style="font-size: 0.65rem; background: #FFEDD5; color: #C2410C; padding: 2px 6px; border-radius: 4px; font-weight: 700;">
                                                    <?= htmlspecialchars($promo['badge_text']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($promo['code'])): ?>
                                            <span style="font-size: 0.72rem; color: #2563EB; font-family: monospace;">Mã: <?= htmlspecialchars($promo['code']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($promo['description'])): ?>
                                            <div style="font-size: 0.72rem; color: var(--text-muted-warm);"><?= htmlspecialchars($promo['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="color: #EA580C; font-size: 0.95rem;"><?= $discountStr ?></strong>
                                        <?php if ((int)$promo['min_order_amount'] > 0): ?>
                                            <div style="font-size: 0.68rem; color: var(--text-muted-warm);">
                                                Đơn từ <?= number_format((int)$promo['min_order_amount'], 0, ',', '.') ?>đ
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $appliesText = 'Toàn bộ đơn';
                                        if ($promo['applies_to'] === 'category') {
                                            $appliesText = 'Danh mục: ' . ($promo['target_category'] === 'drink' ? 'Đồ uống' : 'Món chính');
                                        } elseif ($promo['applies_to'] === 'feast_combo') {
                                            $appliesText = '🍱 Mâm cỗ tiệc';
                                        } elseif ($promo['applies_to'] === 'item') {
                                            $appliesText = 'Món ID #' . (int)$promo['target_id'];
                                        }
                                        ?>
                                        <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                            <span style="font-size: 0.75rem; background: #EEF2E6; color: var(--brand-tea-green); padding: 2px 6px; border-radius: 4px; font-weight: 600;">
                                                <?= htmlspecialchars($appliesText) ?>
                                            </span>
                                            <?php if (!empty($promo['target_customer_phone'])): ?>
                                                <span style="font-size: 0.68rem; background: #E0E7FF; color: #3730A3; padding: 1px 5px; border-radius: 4px; font-weight: 600;">
                                                    👤 Khách: <?= htmlspecialchars($promo['target_customer_phone']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($promo['target_customer_group']) && $promo['target_customer_group'] !== 'all'): ?>
                                                <span style="font-size: 0.68rem; background: #FEF3C7; color: #92400E; padding: 1px 5px; border-radius: 4px; font-weight: 600;">
                                                    <?= match ($promo['target_customer_group']) {
                                                        'trusted' => '⭐️ Khách quen',
                                                        'new' => '🌱 Khách mới',
                                                        'loyal' => '💎 Khách thân thiết',
                                                        default => htmlspecialchars($promo['target_customer_group'])
                                                    } ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($promo['target_order_type']) && $promo['target_order_type'] !== 'all'): ?>
                                                <span style="font-size: 0.68rem; background: #F3E8FF; color: #6B21A8; padding: 1px 5px; border-radius: 4px; font-weight: 600;">
                                                    <?= $promo['target_order_type'] === 'dine_in' ? '🍽 Tại quán' : '🛵 Ship mang về' ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($promo['first_order_only'])): ?>
                                                <span style="font-size: 0.68rem; background: #DCFCE7; color: #166534; padding: 1px 5px; border-radius: 4px; font-weight: 700;">
                                                    1️⃣ Chỉ đơn đầu
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.75rem;">
                                            <?= date('d/m/Y', strtotime($promo['start_date'])) ?> - <?= date('d/m/Y', strtotime($promo['end_date'])) ?>
                                        </div>
                                        <?php if (!empty($promo['apply_on_lunar_days'])): ?>
                                            <span style="font-size: 0.68rem; background: #FEF3C7; color: #92400E; padding: 1px 5px; border-radius: 4px; font-weight: 700;">
                                                🌕 Mùng 1 & Rằm ÂL
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="admin_action" value="toggle_promotion">
                                            <input type="hidden" name="promo_id" value="<?= (int)$promo['id'] ?>">
                                            <input type="hidden" name="is_active" value="<?= $isActive ? '0' : '1' ?>">
                                            <button type="submit" class="action-btn" style="<?= $isActive ? 'background: #ECFDF5; color: #047857; font-weight: 700;' : 'color: #9CA3AF;' ?>">
                                                <?= $isActive ? '🟢 Đang Bật' : '⚪ Đã Tắt' ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa chương trình khuyến mại này?');">
                                            <input type="hidden" name="admin_action" value="delete_promotion">
                                            <input type="hidden" name="promo_id" value="<?= (int)$promo['id'] ?>">
                                            <button type="submit" class="action-btn" style="color: #DC2626;">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($currentTab === 'reviews'): ?>
            <!-- TAB: Quản Lý Đánh Giá Khách Hàng (Issue #18) -->
            <div class="zen-card" style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h2 style="font-size: 1.05rem; font-weight: 700; color: var(--brand-tea-green); margin-bottom: 2px;">
                            🌸 Quản Lý Đánh Giá & Cảm Nhận Của Khách
                        </h2>
                        <p style="font-size: 0.78rem; color: var(--text-muted-warm);">
                            Lắng nghe phản hồi từ khách và kiểm duyệt lời cảm nhận trước khi hiển thị công khai.
                        </p>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <button type="button" onclick="document.getElementById('addTestimonialBox').style.display = document.getElementById('addTestimonialBox').style.display === 'none' ? 'block' : 'none';" class="action-btn" style="background: #FDF4E7; color: #B45309; font-weight: 700; border-color: #FDE68A;">
                            ✨ + Thêm Lời Đề Tặng / Kỷ Niệm
                        </button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="admin_action" value="toggle_reviews_feature">
                            <input type="hidden" name="reviews_enabled" value="<?= $isReviewsEnabled ? '0' : '1' ?>">
                            <button type="submit" class="action-btn" style="<?= $isReviewsEnabled ? 'background: #ECFDF5; color: #047857; font-weight: 700; border-color: #A7F3D0;' : 'background: #FEF3C7; color: #92400E; font-weight: 700; border-color: #FDE68A;' ?>">
                                <?= $isReviewsEnabled ? '🟢 Đang MỞ CHO KHÁCH ĐÁNH GIÁ (Bấm Tắt)' : '🔒 Đang TẮT (Chưa bật - Bấm để Mở)' ?>
                            </button>
                        </form>
                        <a href="reviews.php" target="_blank" class="action-btn" style="text-decoration: none;">
                            Xem Trang Đánh Giá ↗
                        </a>
                    </div>
                </div>

                <!-- Form thêm lời đề tặng, kỷ niệm mới (ẩn mặc định) -->
                <div id="addTestimonialBox" style="display: none; margin-top: 14px; padding: 16px; background: #FFFDF9; border: 1.5px dashed #D5C7B7; border-radius: 14px;">
                    <h3 style="font-size: 0.92rem; font-weight: 700; color: var(--brand-tea-green); margin-bottom: 10px;">
                        ✨ Ghi Lại Lời Đề Tặng / Kỷ Niệm (Sổ Lưu Bút Quán Chay)
                    </h3>
                    <form method="POST" style="display: flex; flex-direction: column; gap: 10px;">
                        <input type="hidden" name="admin_action" value="create_testimonial">
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px;">
                            <div>
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 3px; font-weight: 600;">
                                    Tên người gửi đề tặng / khách thương: <span style="color:#DC2626;">*</span>
                                </label>
                                <input type="text" name="customer_name" class="zen-input" placeholder="VD: Bác Minh Tuệ, Cư sĩ Thanh Phong..." required style="padding: 6px 10px; font-size: 0.8rem;">
                            </div>
                            <div>
                                <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 3px; font-weight: 600;">
                                    Bối cảnh / Lời đề tựa:
                                </label>
                                <input type="text" name="testimonial_note" class="zen-input" placeholder="VD: Lưu bút bàn trà số 3 • Ngày Rằm tháng Bảy" style="padding: 6px 10px; font-size: 0.8rem;">
                            </div>
                        </div>

                        <div>
                            <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 3px; font-weight: 600;">
                                Tiêu đề cảm nhận:
                            </label>
                            <input type="text" name="title" class="zen-input" placeholder="VD: Một chiều mưa tìm lại sự an yên..." style="padding: 6px 10px; font-size: 0.8rem;">
                        </div>

                        <div>
                            <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 3px; font-weight: 600;">
                                Nội dung cảm nhận / Lời đề tặng: <span style="color:#DC2626;">*</span>
                            </label>
                            <textarea name="comment" class="zen-input" rows="3" placeholder="Đôi dòng lưu bút, cảm nhận sâu lắng của khách gửi tặng quán..." required style="padding: 8px 10px; font-size: 0.8rem;"></textarea>
                        </div>

                        <div>
                            <label style="font-size: 0.72rem; color: var(--text-muted-warm); display: block; margin-bottom: 3px; font-weight: 600;">
                                Lời phản hồi tri ân của Bếp:
                            </label>
                            <input type="text" name="kitchen_reply" class="zen-input" placeholder="VD: Bếp nhớ mãi nụ cười hiền của Bác chiều hôm ấy..." style="padding: 6px 10px; font-size: 0.8rem;">
                        </div>

                        <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 4px;">
                            <button type="button" onclick="document.getElementById('addTestimonialBox').style.display='none';" class="action-btn">
                                Hủy Bỏ
                            </button>
                            <button type="submit" class="action-btn" style="background: var(--brand-tea-green); color: #FFF; font-weight: 700; border: none; padding: 6px 16px;">
                                Lưu Lời Đề Tặng 🌸
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Thẻ thống kê đánh giá -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px;">
                <div class="zen-card" style="padding: 14px; text-align: center; border-left: 4px solid #F59E0B;">
                    <p style="font-size: 0.72rem; color: var(--text-muted-warm); text-transform: uppercase;">⏳ Chờ Kiểm Duyệt</p>
                    <p style="font-size: 1.6rem; font-weight: 700; color: #D97706; margin-top: 4px;">
                        <?= (int)$reviewAdminStats['pending'] ?>
                    </p>
                </div>
                <div class="zen-card" style="padding: 14px; text-align: center; border-left: 4px solid #10B981;">
                    <p style="font-size: 0.72rem; color: var(--text-muted-warm); text-transform: uppercase;">✓ Đang Hiển Thị</p>
                    <p style="font-size: 1.6rem; font-weight: 700; color: #059669; margin-top: 4px;">
                        <?= (int)$reviewAdminStats['approved'] ?>
                    </p>
                </div>
                <div class="zen-card" style="padding: 14px; text-align: center; border-left: 4px solid #6B7280;">
                    <p style="font-size: 0.72rem; color: var(--text-muted-warm); text-transform: uppercase;">👁️ Đã Ẩn</p>
                    <p style="font-size: 1.6rem; font-weight: 700; color: #4B5563; margin-top: 4px;">
                        <?= (int)$reviewAdminStats['hidden'] ?>
                    </p>
                </div>
                <div class="zen-card" style="padding: 14px; text-align: center; border-left: 4px solid #D97706;">
                    <p style="font-size: 0.72rem; color: var(--text-muted-warm); text-transform: uppercase;">★ Điểm Trung Bình</p>
                    <p style="font-size: 1.6rem; font-weight: 700; color: #D97706; margin-top: 4px;">
                        <?= number_format((float)$reviewAdminStats['avg_rating'], 1) ?><span style="font-size: 1rem; color: #F59E0B;">★</span>
                    </p>
                </div>
            </div>

            <!-- Bộ lọc trạng thái đánh giá -->
            <div style="display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap;">
                <a href="admin.php?tab=reviews" class="action-btn" style="<?= $reviewStatusFilter === null ? 'background: var(--brand-tea-green); color: #FFF; font-weight: 700;' : '' ?>">
                    Tất Cả (<?= (int)$reviewAdminStats['total'] ?>)
                </a>
                <a href="admin.php?tab=reviews&review_status=pending" class="action-btn" style="<?= $reviewStatusFilter === 'pending' ? 'background: #D97706; color: #FFF; font-weight: 700;' : '' ?>">
                    ⏳ Chờ Duyệt (<?= (int)$reviewAdminStats['pending'] ?>)
                </a>
                <a href="admin.php?tab=reviews&review_status=approved" class="action-btn" style="<?= $reviewStatusFilter === 'approved' ? 'background: #059669; color: #FFF; font-weight: 700;' : '' ?>">
                    ✓ Đã Duyệt (<?= (int)$reviewAdminStats['approved'] ?>)
                </a>
                <a href="admin.php?tab=reviews&review_status=hidden" class="action-btn" style="<?= $reviewStatusFilter === 'hidden' ? 'background: #4B5563; color: #FFF; font-weight: 700;' : '' ?>">
                    👁️ Đã Ẩn (<?= (int)$reviewAdminStats['hidden'] ?>)
                </a>
            </div>

            <!-- Danh sách đánh giá -->
            <div class="zen-card" style="padding: 0; overflow-x: auto;">
                <?php if (empty($allReviews)): ?>
                    <div style="padding: 32px; text-align: center; color: var(--text-muted-warm); font-size: 0.85rem;">
                        Chưa có đánh giá nào phù hợp với bộ lọc hiện tại.
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column;">
                        <?php foreach ($allReviews as $rev): ?>
                            <div style="padding: 16px; border-bottom: 1px solid var(--brand-border-soft); display: flex; flex-direction: column; gap: 10px;">
                                <!-- Dòng tiêu đề đánh giá -->
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 8px;">
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                            <strong style="font-size: 0.95rem; color: var(--brand-tea-dark);">
                                                <?= htmlspecialchars($rev['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </strong>
                                            <?php if (!empty($rev['customer_phone'])): ?>
                                                <span style="font-size: 0.78rem; color: var(--text-muted-warm);">
                                                    (<?= htmlspecialchars($rev['customer_phone'], ENT_QUOTES, 'UTF-8') ?>)
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($rev['is_verified_buyer'])): ?>
                                                <span style="background: #ECFDF5; color: #047857; font-size: 0.65rem; padding: 2px 6px; border-radius: 999px; font-weight: 700;">
                                                    ✓ Đã dùng bữa
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($rev['is_featured'])): ?>
                                                <span style="background: #FEF3C7; color: #92400E; font-size: 0.65rem; padding: 2px 6px; border-radius: 999px; font-weight: 700;">
                                                    ⭐ Đề Tặng / Testimonial
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($rev['order_code'])): ?>
                                                <span style="font-size: 0.72rem; color: #2563EB; background: #EFF6FF; padding: 2px 6px; border-radius: 4px;">
                                                    Đơn #<?= htmlspecialchars($rev['order_code'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.72rem; color: var(--text-muted-warm); margin-top: 2px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                                            <span>Gửi lúc: <?= date('H:i d/m/Y', strtotime($rev['created_at'])) ?></span>
                                            <?php if (!empty($rev['testimonial_note'])): ?>
                                                <span style="color: #8C7A6B; font-style: italic;">• "<?= htmlspecialchars($rev['testimonial_note'], ENT_QUOTES, 'UTF-8') ?>"</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="color: #F59E0B; font-size: 0.95rem;">
                                            <?= str_repeat('★', (int)$rev['rating']) ?><?= str_repeat('☆', 5 - (int)$rev['rating']) ?>
                                        </div>
                                        <div>
                                            <?php if ($rev['status'] === 'approved'): ?>
                                                <span style="background: #ECFDF5; color: #047857; font-size: 0.72rem; padding: 3px 8px; border-radius: 6px; font-weight: 700;">✓ Hiển thị</span>
                                            <?php elseif ($rev['status'] === 'pending'): ?>
                                                <span style="background: #FEF3C7; color: #92400E; font-size: 0.72rem; padding: 3px 8px; border-radius: 6px; font-weight: 700;">⏳ Chờ duyệt</span>
                                            <?php else: ?>
                                                <span style="background: #F3F4F6; color: #4B5563; font-size: 0.72rem; padding: 3px 8px; border-radius: 6px; font-weight: 700;">👁️ Đã ẩn</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Nội dung cảm nhận -->
                                <?php if (!empty($rev['title'])): ?>
                                    <div style="font-weight: 700; font-size: 0.88rem; color: var(--brand-tea-green);">
                                        <?= htmlspecialchars($rev['title'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                                <div style="font-size: 0.82rem; color: #374151; line-height: 1.5; background: #FAF8F5; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--brand-border-soft);">
                                    <?= nl2br(htmlspecialchars($rev['comment'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>

                                <!-- Phản hồi từ Bếp (nếu có) -->
                                <?php if (!empty($rev['kitchen_reply'])): ?>
                                    <div style="background: #F0FDF4; border-left: 3px solid #10B981; padding: 8px 12px; border-radius: 6px; font-size: 0.78rem; color: #065F46;">
                                        <strong>🌿 Bếp An Yên phản hồi (<?= !empty($rev['replied_at']) ? date('d/m/Y', strtotime($rev['replied_at'])) : '' ?>):</strong>
                                        <div style="margin-top: 2px;">
                                            <?= nl2br(htmlspecialchars($rev['kitchen_reply'], ENT_QUOTES, 'UTF-8')) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Thao tác kiểm duyệt & Phản hồi -->
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; padding-top: 4px;">
                                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                        <!-- Nút Ghim Kỷ Niệm / Testimonial -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="admin_action" value="toggle_review_featured">
                                            <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                                            <button type="submit" class="action-btn" style="<?= !empty($rev['is_featured']) ? 'background: #FEF3C7; color: #B45309; font-weight: 700; border-color: #FCD34D;' : 'color: #6B7280;' ?>" title="Bật/tắt ghim lên góc kỷ niệm trầm mặc">
                                                <?= !empty($rev['is_featured']) ? '⭐ Đang Ghim Kỷ Niệm' : '☆ Ghim Testimonial' ?>
                                            </button>
                                        </form>

                                        <?php if ($rev['status'] !== 'approved'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="admin_action" value="update_review_status">
                                                <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                                                <input type="hidden" name="review_status" value="approved">
                                                <button type="submit" class="action-btn" style="color: #047857; border-color: #A7F3D0; font-weight: 600;">
                                                    ✓ Duyệt Hiển Thị
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($rev['status'] !== 'hidden'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="admin_action" value="update_review_status">
                                                <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                                                <input type="hidden" name="review_status" value="hidden">
                                                <button type="submit" class="action-btn" style="color: #4B5563;">
                                                    Ẩn Đánh Giá
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bạn có chắc muốn xóa đánh giá này vĩnh viễn?');">
                                            <input type="hidden" name="admin_action" value="delete_review">
                                            <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                                            <button type="submit" class="action-btn" style="color: #DC2626; border-color: #FECACA;">
                                                Xóa
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Form phản hồi ngắn gọn của Bếp -->
                                    <form method="POST" style="display: flex; gap: 4px; align-items: center; flex: 1; max-width: 420px; min-width: 260px;">
                                        <input type="hidden" name="admin_action" value="reply_review">
                                        <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                                        <input type="text" name="kitchen_reply" class="zen-input" value="<?= htmlspecialchars((string)($rev['kitchen_reply'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Nhập lời cảm ơn hoặc phản hồi của Bếp..." style="padding: 4px 8px; font-size: 0.78rem;">
                                        <button type="submit" class="action-btn" style="white-space: nowrap; font-size: 0.75rem;">
                                            Phản Hồi
                                        </button>
                                    </form>
                                </div>

                                <!-- Form chỉnh sửa Lời đề tựa / bối cảnh kỷ niệm -->
                                <form method="POST" style="display: flex; gap: 4px; align-items: center; padding-top: 2px;">
                                    <input type="hidden" name="admin_action" value="update_testimonial_note">
                                    <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                                    <span style="font-size: 0.7rem; color: #8C7A6B; white-space: nowrap;">📌 Lời đề tựa:</span>
                                    <input type="text" name="testimonial_note" class="zen-input" value="<?= htmlspecialchars((string)($rev['testimonial_note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="VD: Lưu bút bàn trà số 3 • Ngày Rằm tháng Bảy..." style="padding: 3px 8px; font-size: 0.74rem; flex: 1;">
                                    <button type="submit" class="action-btn" style="white-space: nowrap; font-size: 0.7rem; padding: 3px 8px;">
                                        Lưu Đề Tựa
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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

                    <!-- Cấu hình Đánh Giá & Cảm Nhận Khách Hàng (Issue #18) -->
                    <div id="reviews_settings" style="border-top: 1px dashed var(--brand-border-soft); margin-top: 10px; padding-top: 14px;">
                        <h3 style="font-size: 0.88rem; font-weight: 700; color: #9D174D; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                            <span>🌸 Cấu Hình Trang Đánh Giá Của Khách Hàng</span>
                        </h3>
                        <div style="background: #FDF2F8; border: 1px solid #FBCFE8; padding: 14px; border-radius: 12px; margin-bottom: 12px;">
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 700; color: #831843;">
                                    <input type="checkbox" name="reviews_enabled" value="1" <?= (isset($currentConfig['reviews_enabled']) && $currentConfig['reviews_enabled'] === '1') ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                                    <span>Bật trang đánh giá cho khách hàng (Khi tắt, trang reviews.php sẽ hiện thông báo hẹn dịp khác & tạm khóa form gửi)</span>
                                </label>

                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.82rem; color: #9D174D;">
                                    <input type="checkbox" name="reviews_require_approval" value="1" <?= (!isset($currentConfig['reviews_require_approval']) || $currentConfig['reviews_require_approval'] === '1') ? 'checked' : '' ?>>
                                    <span>Kiểm duyệt trước khi hiển thị (Đánh giá mới sẽ ở trạng thái Chờ Duyệt cho đến khi Bếp bấm Duyệt)</span>
                                </label>

                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.82rem; color: #9D174D;">
                                    <input type="checkbox" name="reviews_allow_anonymous" value="1" <?= (!isset($currentConfig['reviews_allow_anonymous']) || $currentConfig['reviews_allow_anonymous'] === '1') ? 'checked' : '' ?>>
                                    <span>Cho phép khách chưa có đơn đặt trước gửi cảm nhận/góp ý</span>
                                </label>
                            </div>

                            <div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #FBCFE8; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 0.75rem; color: #9D174D;">
                                    🔗 Đường dẫn trang đánh giá: <code>reviews.php</code>
                                </span>
                                <a href="reviews.php" target="_blank" class="action-btn" style="text-decoration: none; font-size: 0.75rem; border-color: #F472B6; color: #BE185D;">
                                    Xem trước trang đánh giá ↗
                                </a>
                            </div>
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

        function setSlotWeekdays(type) {
            const checkboxes = document.querySelectorAll('#slotWeekdayGroup input[type="checkbox"]');
            checkboxes.forEach(cb => {
                const val = parseInt(cb.value);
                if (type === 'all') {
                    cb.checked = true;
                } else if (type === 'weekdays') {
                    cb.checked = (val >= 1 && val <= 5);
                } else if (type === 'weekends') {
                    cb.checked = (val === 6 || val === 0);
                }
            });
        }
    </script>
</body>
</html>
