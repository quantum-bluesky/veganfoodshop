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
