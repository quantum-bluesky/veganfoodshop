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
use App\Services\OrderService;
use App\Services\SlotService;

$configService = new ConfigService();
$customerService = new CustomerService();
$menuService = new MenuService();
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

            // Zalo Config
            $zaloAppId = (string)($_POST['zalo_app_id'] ?? '123');
            $zaloSecretKey = (string)($_POST['zalo_secret_key'] ?? '123');
            $zaloMockMode = isset($_POST['zalo_mock_test_mode']) ? '1' : '0';
            $zaloRequireLogin = isset($_POST['zalo_require_login']) ? '1' : '0';
            $zaloRequireGroup = isset($_POST['zalo_require_group']) ? '1' : '0';
            $zaloSellerUrl = (string)($_POST['zalo_seller_url'] ?? 'https://zalo.me/0987862241');
            $zaloCallbackUrl = trim((string)($_POST['zalo_callback_url'] ?? ''));
            $orderNoteShipping = (string)($_POST['order_note_shipping'] ?? 'Chưa bao gồm chi phí vận chuyển');
            $zaloConfirmTemplate = (string)($_POST['zalo_confirm_template'] ?? 'Chào shop, mình vừa đặt đơn trên web. Mã đơn: {order_id}. Nhờ bếp kiểm tra và xác nhận giúp mình nhé!');

            $configService->set('shop_name', $shopName);
            $configService->set('shop_quote', $shopQuote);
            $configService->set('notice_text', $noticeText);
            $configService->set('bank_id', $bankId);
            $configService->set('bank_name', $bankName);
            $configService->set('account_no', $accNo);
            $configService->set('account_name', $accName);

            $configService->set('zalo_app_id', $zaloAppId);
            $configService->set('zalo_secret_key', $zaloSecretKey);
            $configService->set('zalo_mock_test_mode', $zaloMockMode);
            $configService->set('zalo_require_login', $zaloRequireLogin);
            $configService->set('zalo_require_group', $zaloRequireGroup);
            $configService->set('zalo_seller_url', $zaloSellerUrl);
            $configService->set('zalo_callback_url', $zaloCallbackUrl);
            $configService->set('order_note_shipping', $orderNoteShipping);
            $configService->set('zalo_confirm_template', $zaloConfirmTemplate);

            $message = 'Đã lưu cấu hình hệ thống & Zalo Login thành công.';
            break;
    }
}

// Lấy dữ liệu cho các tab
$currentTab = (string)($_GET['tab'] ?? 'slots');
$adminSlots = $slotService->getAllSlotsForAdmin(30);

// Tab Orders: ca được chọn để xem
$selectedSlotId = (int)($_GET['slot_id'] ?? ($adminSlots[0]['id'] ?? 0));
$kitchenSummary = $selectedSlotId ? $orderService->getKitchenSummaryBySlot($selectedSlotId) : [];
$slotOrders = $selectedSlotId ? $orderService->getOrdersBySlot($selectedSlotId) : [];

// Tab Customers
$customerSearch = (string)($_GET['search_cust'] ?? '');
$customerList = $customerService->getAllCustomers($customerSearch);

// Tab Menu
$menuList = $menuService->getAllMenu();

// Tab Config
$currentConfig = $configService->getAll();
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

        <!-- Thanh điều hướng Tab -->
        <nav class="admin-nav">
            <a href="admin.php?tab=slots" class="admin-tab <?= $currentTab === 'slots' ? 'active' : '' ?>">⏰ Lịch Ca (Slots)</a>
            <a href="admin.php?tab=orders" class="admin-tab <?= $currentTab === 'orders' ? 'active' : '' ?>">🍲 Bếp & Đơn Hàng</a>
            <a href="admin.php?tab=customers" class="admin-tab <?= $currentTab === 'customers' ? 'active' : '' ?>">🌿 Khách Quen</a>
            <a href="admin.php?tab=menu" class="admin-tab <?= $currentTab === 'menu' ? 'active' : '' ?>">🥢 Thực Đơn</a>
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
                        <?php foreach ($adminSlots as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['slot_date'], ENT_QUOTES, 'UTF-8') ?></strong></td>
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
                                    <?= (int)$s['is_active'] === 1 ? '<span style="color:#059669;">Đang mở</span>' : '<span style="color:#9CA3AF;">Đóng</span>' ?>
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
                                    <a href="admin.php?tab=orders&slot_id=<?= (int)$s['id'] ?>" class="action-btn" style="text-decoration: none; margin-left: 4px;">Xem đơn</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($currentTab === 'orders'): ?>
            <!-- TAB 2: Bếp Chuẩn Bị & Đơn Hàng -->
            <div class="zen-card" style="margin-bottom: 16px;">
                <label style="font-size: 0.8rem; font-weight: 600; color: var(--brand-tea-green); margin-bottom: 6px; display: block;">
                    Chọn ca phục vụ để kiểm tra nguyên liệu & danh sách đơn:
                </label>
                <select onchange="window.location.href='admin.php?tab=orders&slot_id=' + this.value" class="zen-input" style="max-width: 400px;">
                    <?php foreach ($adminSlots as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $selectedSlotId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['slot_date'] . ' (' . $s['slot_time'] . ') - Đặt: ' . $s['booked_count'] . '/' . $s['max_capacity'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tổng hợp bếp cần nấu cho ca này -->
            <div class="zen-card" style="background-color: var(--brand-sage-light); margin-bottom: 16px;">
                <h3 style="font-size: 0.9rem; font-weight: 700; color: var(--brand-tea-dark); margin-bottom: 8px;">
                    🍲 Bảng Chuẩn Bị Bếp (Batch Kitchen Summary)
                </h3>
                <?php if (empty($kitchenSummary)): ?>
                    <p style="font-size: 0.8rem; color: var(--text-muted-warm);">Chưa có món nào được đặt cho ca này.</p>
                <?php else: ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <?php foreach ($kitchenSummary as $ks): ?>
                            <div style="background: #FFFFFF; padding: 8px 14px; border-radius: 12px; border: 1px solid var(--brand-border-soft); font-size: 0.82rem;">
                                <strong><?= htmlspecialchars($ks['name'], ENT_QUOTES, 'UTF-8') ?>:</strong> 
                                <span style="font-size: 1rem; color: var(--brand-tea-green); font-weight: 700;"><?= (int)$ks['total_qty'] ?></span> suất
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Danh sách đơn hàng trong ca -->
            <div class="zen-card" style="padding: 0; overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã Đơn</th>
                            <th>Khách Hàng</th>
                            <th>Hình Thức</th>
                            <th>Chi Tiết Món</th>
                            <th>Tổng Tiền</th>
                            <th>Thanh Toán</th>
                            <th>Trạng Thái Đơn</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($slotOrders)): ?>
                            <tr><td colspan="7" style="text-align: center; color: var(--text-muted-warm); padding: 20px;">Chưa có đơn đặt nào trong ca này.</td></tr>
                        <?php else: ?>
                            <?php foreach ($slotOrders as $o): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($o['order_code'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <div style="font-size: 0.7rem; color: var(--text-muted-warm);"><?= date('H:i d/m', strtotime($o['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($o['customer_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if ((int)$o['is_trusted'] === 1): ?>
                                            <span style="font-size: 0.68rem; background: #E6F3E6; color: #2D5A27; padding: 2px 6px; border-radius: 6px;">Bạn quen</span>
                                        <?php endif; ?>
                                        <div style="font-size: 0.72rem; color: var(--text-muted-warm);"><?= htmlspecialchars($o['customer_phone'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if (!empty($o['customer_note'])): ?>
                                            <div style="font-size: 0.7rem; color: #8C7A6B; font-style: italic;">"<?= htmlspecialchars($o['customer_note'], ENT_QUOTES, 'UTF-8') ?>"</div>
                                        <?php endif; ?>
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

                    <button type="submit" class="zen-btn-primary" style="margin-top: 8px;">Lưu Cấu Hình</button>
                </form>
            </div>

        <?php endif; ?>

    </div>

</body>
</html>
