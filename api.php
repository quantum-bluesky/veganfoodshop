<?php
declare(strict_types=1);

/**
 * Endpoint xử lý AJAX cho VeganFoodShop (Bếp Chay An Yên)
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/src/autoload.php';

use App\Services\CustomerService;
use App\Services\SlotService;

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

try {
    switch ($action) {
        case 'check_phone':
            $phone = (string)($_GET['phone'] ?? $_POST['phone'] ?? '');
            $customerService = new CustomerService();
            $customer = $customerService->findByPhone($phone);

            if ($customer) {
                echo json_encode([
                    'success' => true,
                    'found' => true,
                    'name' => $customer['name'],
                    'is_trusted' => (int)$customer['is_trusted'],
                    'zalo_id' => (string)($customer['zalo_id'] ?? ''),
                    'avatar_url' => (string)($customer['avatar_url'] ?? ''),
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => true,
                    'found' => false,
                    'is_trusted' => 0,
                    'zalo_id' => '',
                    'avatar_url' => '',
                ], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'get_slots':
            $date = (string)($_GET['date'] ?? date('Y-m-d'));
            $slotService = new SlotService();
            $configService = new \App\Services\ConfigService();
            $closedStatus = $configService->getShopClosedStatus($date);
            $slots = $slotService->getSlotsByDate($date, true);

            echo json_encode([
                'success' => true,
                'date' => $date,
                'is_closed' => $closedStatus['is_closed'],
                'closed_reason' => $closedStatus['reason'],
                'slots' => $slots,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'get_menu_for_date':
            $date = (string)($_GET['date'] ?? date('Y-m-d'));
            $orderType = isset($_GET['order_type']) ? (string)$_GET['order_type'] : null;

            $menuService = new \App\Services\MenuService();
            $scheduledSuite = $menuService->getScheduledSuiteForDate($date);
            $items = $menuService->getMenuItemsForDate($date, $orderType);

            echo json_encode([
                'success' => true,
                'date' => $date,
                'order_type' => $orderType,
                'suite_name' => $scheduledSuite['name'] ?? 'Thực đơn Bếp An Yên',
                'suite_desc' => $scheduledSuite['description'] ?? '',
                'items' => $items,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'get_zalo_config':
            $zaloService = new \App\Services\ZaloAuthService();
            $zCfg = $zaloService->getConfig();

            echo json_encode([
                'success' => true,
                'mock_test_mode' => (bool)$zCfg['mock_test_mode'],
                'require_zalo_login' => (bool)$zCfg['require_zalo_login'],
                'require_zalo_group_to_order' => (bool)$zCfg['require_zalo_group_to_order'],
                'seller_zalo_url' => (string)$zCfg['seller_zalo_url'],
                'order_note_shipping' => (string)$zCfg['order_note_shipping'],
                'zalo_confirm_template' => (string)$zCfg['zalo_confirm_template'],
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'zalo_login':
            $zaloService = new \App\Services\ZaloAuthService();
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

        case 'zalo_callback':
            header('Content-Type: text/html; charset=UTF-8');
            $code = (string)($_GET['code'] ?? '');
            $codeVerifier = (string)($_COOKIE['zalo_code_verifier'] ?? '');

            $zaloService = new \App\Services\ZaloAuthService();
            $user = $zaloService->handleCallback($code, $codeVerifier);

            echo $zaloService->renderCallbackHtml($user, 'index.php');
            exit;

        case 'get_my_orders':
            $identifier = (string)($_GET['identifier'] ?? $_GET['zalo_id'] ?? $_GET['phone'] ?? '');
            if (trim($identifier) === '') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Vui lòng cung cấp Zalo ID hoặc số điện thoại.',
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            $orderService = new \App\Services\OrderService();
            $orders = $orderService->getOrdersByCustomer($identifier, 10);
            $upcoming = $orderService->getUpcomingActiveOrder($identifier);

            echo json_encode([
                'success' => true,
                'orders' => $orders,
                'upcoming' => $upcoming,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'check_payment_status':
            $orderCode = (string)($_GET['order_code'] ?? $_POST['order_code'] ?? '');
            if (trim($orderCode) === '') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Thiếu mã đơn hàng.',
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            $orderService = new \App\Services\OrderService();
            $order = $orderService->getOrderByCode($orderCode);

            if (!$order) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Không tìm thấy đơn hàng.',
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            echo json_encode([
                'success' => true,
                'order_code' => $order['order_code'],
                'payment_status' => $order['payment_status'],
                'order_status' => $order['order_status'],
                'is_paid' => ($order['payment_status'] === 'paid'),
                'total_amount' => (int)$order['total_amount'],
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'bank_webhook':
            // Endpoint Webhook nhận thông báo chuyển khoản ngân hàng (Casso, SePay, Webhook tự tạo)
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true) ?: $_POST;
            $orderService = new \App\Services\OrderService();

            $reconciledCount = 0;
            $itemsToProcess = [];

            // Chuẩn hóa danh sách giao dịch
            if (isset($data['data']) && is_array($data['data'])) {
                // Định dạng Casso: {"data": [...]}
                $itemsToProcess = $data['data'];
            } elseif (isset($data['content']) || isset($data['description'])) {
                $itemsToProcess = [$data];
            }

            foreach ($itemsToProcess as $tx) {
                $content = (string)($tx['content'] ?? $tx['description'] ?? '');
                $amount = isset($tx['amount']) ? (int)$tx['amount'] : (isset($tx['transferAmount']) ? (int)$tx['transferAmount'] : null);
                $ref = (string)($tx['reference'] ?? $tx['referenceCode'] ?? $tx['id'] ?? '');

                // Trích xuất mã đơn có dạng AY-YYYYMMDD-XXXX
                if (preg_match('/AY-\d{8}-[A-Z0-9]{4}/i', $content, $matches)) {
                    $matchedCode = strtoupper($matches[0]);
                    try {
                        $orderService->reconcilePayment($matchedCode, $ref ?: null, $amount);
                        $reconciledCount++;
                    } catch (\Throwable) {
                        // Tiếp tục xử lý giao dịch tiếp theo nếu đơn này lỗi
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'reconciled_count' => $reconciledCount,
                'message' => sprintf('Đã đối soát thành công %d đơn hàng.', $reconciledCount),
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Action không hợp lệ.',
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
