<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use App\Services\PromotionService;
use App\Services\OrderService;
use App\Services\CustomerService;
use App\Services\SlotService;
use App\Services\ConfigService;
use App\Services\NotificationService;
use App\Services\ZaloAuthService;

/**
 * Test Suite cho Issue #26 & Issue #27:
 * - Issue #26: Khuyến mại đa chiều (theo từng khách, nhóm khách, nhóm đơn, đơn đầu tiên, mã đơn).
 * - Issue #27: Icon trạng thái Zalo trên header, logout Zalo, và cập nhật trạng thái đơn của tôi.
 */
class Issue26_27_PromotionsAndZaloProfileTest
{
    private PDO $db;
    private CustomerService $customerService;
    private PromotionService $promotionService;
    private OrderService $orderService;
    private string $targetDate;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        $this->targetDate = date('Y-m-d', strtotime('+1 day'));
        $this->setUpDatabase();
        $this->customerService = new CustomerService($this->db);
        $this->promotionService = new PromotionService($this->db, null, $this->customerService);

        $configService = new ConfigService($this->db);
        $configService->set('zalo_require_login', '0');
        $slotService = new SlotService($this->db, $configService);
        $notifService = new NotificationService($this->db);
        $zaloService = new ZaloAuthService($this->db, $configService, $this->customerService, [
            'app_id' => 'mock_app_id',
            'secret_key' => 'mock_secret_key',
            'mock_test_mode' => true,
            'require_zalo_login' => false,
        ]);

        $this->orderService = new OrderService(
            $this->db,
            $this->customerService,
            $slotService,
            $configService,
            $notifService,
            $zaloService,
            $this->promotionService
        );
    }

    private function setUpDatabase(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $schemaSql = file_get_contents(__DIR__ . '/../../database/schema.sql');
        $this->db->exec($schemaSql);

        // Chạy migration nếu cần
        $migrationFiles = glob(__DIR__ . '/../../database/migrations/*.sql');
        sort($migrationFiles);
        foreach ($migrationFiles as $file) {
            $sql = file_get_contents($file);
            try {
                $this->db->exec($sql);
            } catch (\Throwable $e) {
                // Bỏ qua duplicate column
            }
        }

        $futureDate1 = date('Y-m-d', strtotime('+1 day'));
        $futureDate2 = date('Y-m-d', strtotime('+2 days'));

        // Seed dữ liệu mẫu để test
        $this->db->exec("
            INSERT INTO customers (phone, name, is_trusted) VALUES 
                ('0901234567', 'Chị Lan Khách Quen', 1),
                ('0988888888', 'Anh Bình Khách Thường', 0);

            INSERT INTO time_slots (id, slot_date, slot_time, max_capacity, is_active) 
            VALUES (1, '{$futureDate1}', '11:30 - 12:30', 20, 1),
                   (2, '{$futureDate2}', '11:30 - 12:30', 20, 1);

            INSERT INTO menu_items (id, name, price, category, is_feast_combo, is_available) 
            VALUES (1, 'Phở Nấm Hương', 45000, 'food', 0, 1),
                   (2, 'Bún Huế Chay', 50000, 'food', 0, 1),
                   (3, 'Trà Sen Tuyết', 20000, 'drink', 0, 1);
        ");
    }

    public function runAllTests(): void
    {
        echo "\n=== [TEST] Issue #26 & #27: Khuyến Mại Đa Chiều & Zalo Profile Đơn Của Tôi ===\n";

        $this->testTargetIndividualCustomer();
        $this->testTargetCustomerGroupTrusted();
        $this->testTargetCustomerGroupNew();
        $this->testTargetCustomerGroupLoyal();
        $this->testTargetOrderTypeDineInAndTakeaway();
        $this->testFirstOrderOnlyPromotion();
        $this->testTargetOrderCodePromotion();
        $this->testOrderServiceIntegratesTargetingContext();
        $this->testZaloProfilePillAndModalInIndexHtml();
        $this->testProcessOrderReflectsCancelledAndCompletedStatuses();
        $this->testGetMyOrdersApiReflectsRealtimeStatusUpdates();

        echo "=> Kết quả Issue #26 & #27: {$this->passed} PASS, {$this->failed} FAIL\n\n";

        if ($this->failed > 0) {
            throw new \RuntimeException("Issue26_27_PromotionsAndZaloProfileTest failed with {$this->failed} errors");
        }
    }

    private function testTargetIndividualCustomer(): void
    {
        $id = $this->promotionService->createPromotion([
            'title' => 'Ưu Đãi Riêng Chị Lan',
            'discount_type' => 'fixed_amount',
            'discount_value' => 15000,
            'applies_to' => 'all',
            'start_date' => date('Y-m-d', strtotime('-5 days')),
            'end_date' => date('Y-m-d', strtotime('+30 days')),
            'target_customer_phone' => '0901234567',
        ]);

        $items = [
            ['item' => ['id' => 1, 'price' => 45000, 'category' => 'food'], 'quantity' => 2]
        ];
        $subtotal = 90000;

        // Đúng khách được nhận
        $calcLan = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'customer_phone' => '0901234567',
            'order_type' => 'dine_in'
        ]);
        $this->assert($calcLan['discount_amount'] === 15000, "Khách 0901234567 được nhận ưu đãi cá nhân 15.000đ");

        // Khách khác không được nhận
        $calcBinh = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'customer_phone' => '0988888888',
            'order_type' => 'dine_in'
        ]);
        $this->assert($calcBinh['discount_amount'] === 0, "Khách khác không được nhận ưu đãi cá nhân của người khác");

        $this->promotionService->deletePromotion($id);
    }

    private function testTargetCustomerGroupTrusted(): void
    {
        $id = $this->promotionService->createPromotion([
            'title' => 'Tri Ân Khách Quen An Yên',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'applies_to' => 'all',
            'start_date' => date('Y-m-d', strtotime('-5 days')),
            'end_date' => date('Y-m-d', strtotime('+30 days')),
            'target_customer_group' => 'trusted',
        ]);

        $items = [
            ['item' => ['id' => 1, 'price' => 50000, 'category' => 'food'], 'quantity' => 2]
        ];
        $subtotal = 100000;

        // Chị Lan là khách quen (is_trusted = 1) -> Giảm 20% = 20.000đ
        $calcLan = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'customer_phone' => '0901234567',
        ]);
        $this->assert($calcLan['discount_amount'] === 20000, "Khách quen được giảm 20% (20.000đ)");

        // Anh Bình chưa là khách quen -> 0đ
        $calcBinh = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'customer_phone' => '0988888888',
        ]);
        $this->assert($calcBinh['discount_amount'] === 0, "Khách chưa được xác thực quen không được áp dụng nhóm trusted");

        $this->promotionService->deletePromotion($id);
    }

    private function testTargetCustomerGroupNew(): void
    {
        $id = $this->promotionService->createPromotion([
            'title' => 'Chào Bạn Mới Ghé Bếp',
            'discount_type' => 'fixed_amount',
            'discount_value' => 10000,
            'applies_to' => 'all',
            'start_date' => date('Y-m-d', strtotime('-5 days')),
            'end_date' => date('Y-m-d', strtotime('+30 days')),
            'target_customer_group' => 'new',
        ]);

        $items = [
            ['item' => ['id' => 1, 'price' => 50000, 'category' => 'food'], 'quantity' => 1]
        ];
        $subtotal = 50000;

        // Khách hoàn toàn mới (chưa có trong DB hoặc chưa có đơn, chưa trusted)
        $calcNew = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'customer_phone' => '0912999888',
        ]);
        $this->assert($calcNew['discount_amount'] === 10000, "Khách mới được nhận ưu đãi Chào Bạn Mới (10.000đ)");

        // Khách quen (Chị Lan) -> không được nhận ưu đãi khách mới
        $calcLan = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'customer_phone' => '0901234567',
        ]);
        $this->assert($calcLan['discount_amount'] === 0, "Khách quen cũ không được nhận ưu đãi chỉ dành cho khách mới");

        $this->promotionService->deletePromotion($id);
    }

    private function testTargetCustomerGroupLoyal(): void
    {
        $id = $this->promotionService->createPromotion([
            'title' => 'Ưu Đãi Khách Thân Thiết',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'applies_to' => 'all',
            'start_date' => date('Y-m-d', strtotime('-5 days')),
            'end_date' => date('Y-m-d', strtotime('+30 days')),
            'target_customer_group' => 'loyal',
        ]);

        // Tạo 3 đơn đã hoàn thành cho khách 0977666555
        $this->db->exec("
            INSERT INTO customers (phone, name, is_trusted) VALUES ('0977666555', 'Bác Minh', 0);
            INSERT INTO orders (order_code, customer_phone, slot_id, order_type, total_amount, payment_method, payment_status, order_status)
            VALUES 
                ('AN-TEST-01', '0977666555', 1, 'dine_in', 50000, 'vietqr', 'paid', 'completed'),
                ('AN-TEST-02', '0977666555', 1, 'dine_in', 50000, 'vietqr', 'paid', 'completed'),
                ('AN-TEST-03', '0977666555', 1, 'dine_in', 50000, 'vietqr', 'paid', 'completed');
        ");

        $items = [
            ['item' => ['id' => 1, 'price' => 100000, 'category' => 'food'], 'quantity' => 1]
        ];
        $subtotal = 100000;

        $calcMinh = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'customer_phone' => '0977666555',
        ]);
        $this->assert($calcMinh['discount_amount'] === 15000, "Khách đã có 3 đơn hoàn tất được nhận ưu đãi khách thân thiết (15%)");

        // Khách chỉ có 1 đơn
        $this->db->exec("
            INSERT INTO customers (phone, name, is_trusted) VALUES ('0933333333', 'Bạn Hương', 0);
            INSERT INTO orders (order_code, customer_phone, slot_id, order_type, total_amount, payment_method, payment_status, order_status)
            VALUES ('AN-TEST-04', '0933333333', 1, 'dine_in', 50000, 'vietqr', 'paid', 'completed');
        ");

        $calcHuong = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'customer_phone' => '0933333333',
        ]);
        $this->assert($calcHuong['discount_amount'] === 0, "Khách mới có 1 đơn chưa đủ điều kiện nhóm thân thiết");

        $this->promotionService->deletePromotion($id);
    }

    private function testTargetOrderTypeDineInAndTakeaway(): void
    {
        // Khuyến mại chỉ dành cho ăn tại quán
        $idDineIn = $this->promotionService->createPromotion([
            'title' => 'Thảnh Thơi Tại Quán',
            'discount_type' => 'fixed_amount',
            'discount_value' => 8000,
            'applies_to' => 'all',
            'start_date' => date('Y-m-d', strtotime('-5 days')),
            'end_date' => date('Y-m-d', strtotime('+30 days')),
            'target_order_type' => 'dine_in',
        ]);

        $items = [
            ['item' => ['id' => 1, 'price' => 50000, 'category' => 'food'], 'quantity' => 1]
        ];
        $subtotal = 50000;

        $calcDine = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'order_type' => 'dine_in',
        ]);
        $this->assert($calcDine['discount_amount'] === 8000, "Đơn ăn tại quán nhận đúng ưu đãi dine_in");

        $calcTakeaway = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'order_type' => 'takeaway',
        ]);
        $this->assert($calcTakeaway['discount_amount'] === 0, "Đơn mang về không được nhận ưu đãi dine_in");

        $this->promotionService->deletePromotion($idDineIn);

        // Khuyến mại chỉ dành cho mang về
        $idTakeaway = $this->promotionService->createPromotion([
            'title' => 'Trợ Ship Mang Về',
            'discount_type' => 'fixed_amount',
            'discount_value' => 12000,
            'applies_to' => 'all',
            'start_date' => date('Y-m-d', strtotime('-5 days')),
            'end_date' => date('Y-m-d', strtotime('+30 days')),
            'target_order_type' => 'takeaway',
        ]);

        $calcShip = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'order_type' => 'takeaway',
        ]);
        $this->assert($calcShip['discount_amount'] === 12000, "Đơn mang về nhận đúng ưu đãi trợ ship takeaway");

        $this->promotionService->deletePromotion($idTakeaway);
    }

    private function testFirstOrderOnlyPromotion(): void
    {
        $id = $this->promotionService->createPromotion([
            'title' => 'Món Quà Đơn Đầu',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'applies_to' => 'all',
            'start_date' => date('Y-m-d', strtotime('-5 days')),
            'end_date' => date('Y-m-d', strtotime('+30 days')),
            'first_order_only' => 1,
        ]);

        $items = [
            ['item' => ['id' => 1, 'price' => 100000, 'category' => 'food'], 'quantity' => 1]
        ];
        $subtotal = 100000;

        $phoneNew = '0944555666';

        // Đơn đầu tiên
        $calcFirst = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'customer_phone' => $phoneNew,
        ]);
        $this->assert($calcFirst['discount_amount'] === 10000, "Đơn đầu tiên được giảm 10%");

        // Giả lập sau khi đã tạo 1 đơn trong hệ thống
        $this->db->exec("
            INSERT INTO customers (phone, name, is_trusted) VALUES ('{$phoneNew}', 'Khách Mới', 0);
            INSERT INTO orders (order_code, customer_phone, slot_id, order_type, total_amount, payment_method, payment_status, order_status)
            VALUES ('AN-FIRST-01', '{$phoneNew}', 1, 'dine_in', 90000, 'vietqr', 'paid', 'confirmed');
        ");

        // Đơn thứ hai
        $calcSecond = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'customer_phone' => $phoneNew,
        ]);
        $this->assert($calcSecond['discount_amount'] === 0, "Đơn thứ hai không còn được áp dụng khuyến mại đơn đầu tiên");

        $this->promotionService->deletePromotion($id);
    }

    private function testTargetOrderCodePromotion(): void
    {
        $id = $this->promotionService->createPromotion([
            'title' => 'Đặc Quyền Mã Đơn Tri Ân',
            'discount_type' => 'fixed_amount',
            'discount_value' => 25000,
            'applies_to' => 'all',
            'start_date' => date('Y-m-d', strtotime('-5 days')),
            'end_date' => date('Y-m-d', strtotime('+30 days')),
            'target_order_code' => 'VIP-2026',
        ]);

        $items = [
            ['item' => ['id' => 1, 'price' => 100000, 'category' => 'food'], 'quantity' => 1]
        ];
        $subtotal = 100000;

        $calcMatch = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'order_code' => 'VIP-2026',
        ]);
        $this->assert($calcMatch['discount_amount'] === 25000, "Khớp target_order_code VIP-2026 được giảm 25.000đ");

        $calcOther = $this->promotionService->calculateOrderDiscount($this->targetDate, $items, $subtotal, [
            'order_code' => 'OTHER-001',
        ]);
        $this->assert($calcOther['discount_amount'] === 0, "Khác target_order_code không được giảm");

        $this->promotionService->deletePromotion($id);
    }

    private function testOrderServiceIntegratesTargetingContext(): void
    {
        // Tạo khuyến mại chỉ dành cho takeaway
        $promoId = $this->promotionService->createPromotion([
            'title' => 'Giảm 10k Ship Mang Về',
            'discount_type' => 'fixed_amount',
            'discount_value' => 10000,
            'applies_to' => 'all',
            'start_date' => date('Y-m-d', strtotime('-5 days')),
            'end_date' => date('Y-m-d', strtotime('+30 days')),
            'target_order_type' => 'takeaway',
        ]);

        // Tạo đơn takeaway qua OrderService
        $order = $this->orderService->createOrder([
            'customer_name' => 'Bác Hùng',
            'customer_phone' => '0912345678',
            'slot_id' => 1,
            'order_type' => 'takeaway',
            'payment_method' => 'vietqr',
            'items' => [
                1 => 2, // Phở 45k x 2 = 90k
            ],
        ]);

        $this->assert((int)$order['total_amount'] === 80000, "OrderService tích hợp context takeaway: 90k - 10k = 80k");
        $this->assert(str_contains((string)$order['customer_note'], 'Giảm 10k Ship Mang Về'), "Ghi nhận đúng tên ưu đãi trong ghi chú đơn");

        $this->promotionService->deletePromotion($promoId);
    }

    private function testZaloProfilePillAndModalInIndexHtml(): void
    {
        $html = file_get_contents(__DIR__ . '/../../index.php');

        $this->assert(str_contains($html, 'id="topZaloPill"'), "index.php có thanh hiển thị topZaloPill");
        $this->assert(str_contains($html, 'id="topZaloLoggedIn"'), "index.php có khối topZaloLoggedIn");
        $this->assert(str_contains($html, 'id="topZaloLoggedOut"'), "index.php có khối topZaloLoggedOut");
        $this->assert(str_contains($html, 'id="btnTopMyBookings"'), "index.php có nút btnTopMyBookings");
        $this->assert(str_contains($html, 'id="btnTopLogout"'), "index.php có nút btnTopLogout");
        $this->assert(str_contains($html, 'id="btnRefreshMyBookings"'), "Modal có nút btnRefreshMyBookings làm mới đơn");

        $js = file_get_contents(__DIR__ . '/../../assets/js/app.js');
        $this->assert(str_contains($js, 'btnRefreshMyBookings'), "app.js có xử lý sự kiện btnRefreshMyBookings");
        $this->assert(str_contains($js, 'btnTopLogout'), "app.js có xử lý sự kiện btnTopLogout");
        $this->assert(str_contains($js, 'topZaloLoggedIn'), "app.js đồng bộ hiển thị topZaloLoggedIn khi đã login");
    }

    private function testProcessOrderReflectsCancelledAndCompletedStatuses(): void
    {
        $code = file_get_contents(__DIR__ . '/../../process_order.php');

        $this->assert(str_contains($code, "\$order['order_status'] === 'cancelled'"), "process_order.php xử lý hiển thị riêng khi đơn bị huỷ");
        $this->assert(str_contains($code, 'Đơn Hàng Đã Được Huỷ'), "process_order.php có thông báo Đơn Hàng Đã Được Huỷ");
        $this->assert(str_contains($code, "\$order['order_status'] === 'completed'"), "process_order.php xử lý hiển thị riêng khi đơn đã hoàn tất");
        $this->assert(str_contains($code, 'Đơn Hàng Đã Hoàn Tất'), "process_order.php có thông báo Đơn Hàng Đã Hoàn Tất");
    }

    private function testGetMyOrdersApiReflectsRealtimeStatusUpdates(): void
    {
        // 1. Tạo đơn mới
        $order = $this->orderService->createOrder([
            'customer_name' => 'Cô Mai',
            'customer_phone' => '0987111222',
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'payment_method' => 'vietqr',
            'items' => [
                1 => 1, // 45k
            ],
        ]);
        $orderId = (int)$order['id'];
        $orderCode = (string)$order['order_code'];

        // Ban đầu status là pending và confirmed
        $orders1 = $this->orderService->getOrdersByCustomer('0987111222');
        $this->assert(!empty($orders1), "Lấy được danh sách đơn của khách 0987111222");
        $this->assert($orders1[0]['payment_status'] === 'pending', "Trạng thái ban đầu là pending");
        $this->assert($orders1[0]['order_status'] === 'confirmed', "Trạng thái đơn ban đầu là confirmed");

        // 2. Admin xác nhận thanh toán -> Chuyển sang paid
        $this->orderService->updatePaymentStatus($orderId, 'paid');

        $orders2 = $this->orderService->getOrdersByCustomer('0987111222');
        $this->assert($orders2[0]['payment_status'] === 'paid', "getOrdersByCustomer phản ánh ngay trạng thái paid sau khi cập nhật");

        // 3. Admin hoàn tất đơn (hoặc chốt ca)
        $this->orderService->updateOrderStatus($orderId, 'completed');

        $orders3 = $this->orderService->getOrdersByCustomer('0987111222');
        $this->assert($orders3[0]['order_status'] === 'completed', "getOrdersByCustomer phản ánh ngay trạng thái completed khi đơn được hoàn tất");

        // 4. Admin huỷ đơn
        $this->orderService->updateOrderStatus($orderId, 'cancelled');

        $orders4 = $this->orderService->getOrdersByCustomer('0987111222');
        $this->assert($orders4[0]['order_status'] === 'cancelled', "getOrdersByCustomer phản ánh ngay trạng thái cancelled khi đơn bị huỷ");
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message}\n";
        }
    }
}
