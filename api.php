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
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => true,
                    'found' => false,
                    'is_trusted' => 0,
                ], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'get_slots':
            $date = (string)($_GET['date'] ?? date('Y-m-d'));
            $slotService = new SlotService();
            $slots = $slotService->getSlotsByDate($date, true);

            echo json_encode([
                'success' => true,
                'date' => $date,
                'slots' => $slots,
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
        'message' => 'Có lỗi xảy ra trong quá trình xử lý.',
    ], JSON_UNESCAPED_UNICODE);
}
