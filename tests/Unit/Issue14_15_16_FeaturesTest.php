<?php
declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\OrderService;
use App\Services\SlotService;
use App\Services\ConfigService;
use App\Services\CustomerService;
use App\Services\NotificationService;
use App\Services\ZaloAuthService;
use App\Exceptions\ValidationException;

class Issue14_15_16_FeaturesTest extends TestCase
{
    private OrderService $orderService;
    private SlotService $slotService;
    private ConfigService $configService;

    public function runAllTests(): void
    {
        $this->setUp();

        $this->configService = new ConfigService($this->db);
        $customerService = new CustomerService($this->db);
        $notificationService = new NotificationService($this->db);
        $this->slotService = new SlotService($this->db, $this->configService);

        $zaloService = new ZaloAuthService($this->db, $this->configService, $customerService, [
            'app_id' => '123',
            'secret_key' => '123',
            'mock_test_mode' => true,
            'require_zalo_login' => false,
        ]);

        $this->orderService = new OrderService(
            $this->db,
            $customerService,
            $this->slotService,
            $this->configService,
            $notificationService,
            $zaloService
        );

        $this->testMandatoryZaloLoginEnforcement();
        $this->testCustomerBookingHistoryAndReminder();
        $this->testBatchSlotReplicationAndSync();
        $this->testReconciliationAndDailyClosing();

        $this->tearDown();
    }

    private function testMandatoryZaloLoginEnforcement(): void
    {
        // 1. Khi bật bắt buộc đăng nhập Zalo
        $this->configService->set('zalo_require_login', '1');
        $customerService = new CustomerService($this->db);
        $zaloStrictService = new ZaloAuthService($this->db, $this->configService, $customerService, [
            'app_id' => '123',
            'secret_key' => '123',
            'mock_test_mode' => true,
            'require_zalo_login' => true,
        ]);

        $strictOrderService = new OrderService(
            $this->db,
            $customerService,
            $this->slotService,
            $this->configService,
            new NotificationService($this->db),
            $zaloStrictService
        );

        $orderData = [
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'customer_name' => 'Khách Zalo Mẫu',
            'customer_phone' => '0912345678',
            'payment_method' => 'vietqr',
            'items' => [1 => 1]
        ];

        // Đặt hàng mà không có zalo_id -> Phải ném ValidationException
        $threw = false;
        try {
            $strictOrderService->createOrder($orderData);
        } catch (ValidationException $e) {
            $threw = true;
            $this->assertTrue(str_contains($e->getMessage(), 'yêu cầu đăng nhập tài khoản Zalo'));
        }
        $this->assertTrue($threw, 'Phải ném ValidationException khi require_zalo_login bật mà khách không đăng nhập Zalo');

        // Khi có zalo_id -> Đặt hàng thành công
        $orderData['zalo_id'] = 'zalo_user_999';
        $orderData['zalo_name'] = 'Bình An';
        $created = $strictOrderService->createOrder($orderData);
        $this->assertNotNull($created['order_code']);
        $this->assertEquals('zalo_user_999', $created['zalo_id']);

        // Trả lại cấu hình tắt require login cho các test sau
        $this->configService->set('zalo_require_login', '0');
    }

    private function testCustomerBookingHistoryAndReminder(): void
    {
        $zaloId = 'zalo_cust_history_01';
        $phone = '0901234567';

        // Tạo 2 đơn hàng cho khách này
        $futureDate = date('Y-m-d', strtotime('+2 days'));
        $this->db->exec("INSERT INTO time_slots (id, slot_date, slot_time, max_capacity, is_active) VALUES (99, '$futureDate', '11:30 - 12:15', 10, 1)");

        $this->orderService->createOrder([
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'customer_name' => 'Tuệ Minh',
            'customer_phone' => $phone,
            'payment_method' => 'vietqr',
            'zalo_id' => $zaloId,
            'items' => [1 => 2]
        ]);

        $this->orderService->createOrder([
            'slot_id' => 99,
            'order_type' => 'takeaway',
            'customer_name' => 'Tuệ Minh',
            'customer_phone' => $phone,
            'payment_method' => 'vietqr',
            'zalo_id' => $zaloId,
            'items' => [2 => 1]
        ]);

        // Kiểm tra lịch sử đơn
        $history = $this->orderService->getOrdersByCustomer($zaloId, 10);
        $this->assertTrue(count($history) >= 2, 'Phải lấy được lịch sử các đơn của khách theo Zalo ID');

        // Kiểm tra đơn sắp tới gần nhất (reminder banner)
        $upcoming = $this->orderService->getUpcomingActiveOrder($zaloId);
        $this->assertNotNull($upcoming, 'Phải tìm thấy đơn sắp tới gần nhất');
        $this->assertTrue(!empty($upcoming['slot_date']));
        $this->assertTrue(!empty($upcoming['slot_time']));
    }

    private function testBatchSlotReplicationAndSync(): void
    {
        $today = date('Y-m-d');
        // Tạo ca mẫu cho ngày hôm nay
        $this->db->exec("INSERT INTO time_slots (slot_date, slot_time, max_capacity, is_active) VALUES ('$today', '08:00 - 09:00', 15, 1)");
        $this->db->exec("INSERT INTO time_slots (slot_date, slot_time, max_capacity, is_active) VALUES ('$today', '17:30 - 18:30', 20, 1)");

        // Nhân bản cho 7 ngày tới với các ngày thứ 2, thứ 4, thứ 6
        $startDate = date('Y-m-d', strtotime('+1 day'));
        $endDate = date('Y-m-d', strtotime('+7 days'));
        $daysOfWeek = [1, 3, 5]; // T2, T4, T6

        $result = $this->slotService->replicateSlots($today, $daysOfWeek, $startDate, $endDate, false);
        $this->assertTrue($result['created_count'] > 0, 'Phải tạo thành công các ca nhân bản');
        $this->assertTrue($result['days_count'] > 0, 'Phải có số ngày mục tiêu tương ứng');

        // Kiểm tra cập nhật ca và an toàn xóa ca
        $allSlots = $this->slotService->getAllSlotsForAdmin();
        $sampleSlot = $allSlots[0];
        $this->slotService->updateSlot((int)$sampleSlot['id'], '11:45 - 12:45', 18, false);

        $updatedStmt = $this->db->prepare("SELECT slot_time, max_capacity FROM time_slots WHERE id = ?");
        $updatedStmt->execute([(int)$sampleSlot['id']]);
        $row = $updatedStmt->fetch();
        $this->assertEquals('11:45 - 12:45', $row['slot_time']);
        $this->assertEquals(18, (int)$row['max_capacity']);
    }

    private function testReconciliationAndDailyClosing(): void
    {
        // Tạo 1 đơn chờ thanh toán
        $created = $this->orderService->createOrder([
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'customer_name' => 'Khách Test Đối Soát',
            'customer_phone' => '0933445566',
            'payment_method' => 'vietqr',
            'items' => [1 => 2] // 90.000đ
        ]);
        $orderCode = $created['order_code'];

        // Kiểm tra danh sách chờ đối soát
        $pendingList = $this->orderService->getPendingReconcileOrders(50);
        $found = false;
        foreach ($pendingList as $po) {
            if ($po['order_code'] === $orderCode) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Đơn mới tạo chưa thanh toán phải nằm trong danh sách chờ đối soát');

        // Tiến hành đối soát khớp thanh toán
        $reconciled = $this->orderService->reconcilePayment($orderCode, 'FT20261025BANK', 90000);
        $this->assertNotNull($reconciled, 'Đối soát thanh toán phải trả về kết quả đơn');
        $this->assertEquals('paid', $reconciled['payment_status']);

        // Kiểm tra DB: đơn đã sang paid và có paid_at
        $checkStmt = $this->db->prepare("SELECT payment_status, paid_at, transaction_ref FROM orders WHERE order_code = ?");
        $checkStmt->execute([$orderCode]);
        $orderDb = $checkStmt->fetch();
        $this->assertEquals('paid', $orderDb['payment_status']);
        $this->assertNotNull($orderDb['paid_at']);
        $this->assertEquals('FT20261025BANK', $orderDb['transaction_ref']);

        // Chốt ca và chốt ngày
        $closingReport = $this->orderService->getClosingReport(date('Y-m-d', strtotime('+1 day')));
        $this->assertTrue(isset($closingReport['paid_amount']), 'Báo cáo chốt sổ phải có thông tin paid_amount');
        $this->assertTrue(isset($closingReport['total_items_served']), 'Báo cáo chốt sổ phải có total_items_served');

        // Chốt ca 1
        $closedSlotRes = $this->orderService->closeSlot(1, true);
        $this->assertNotNull($closedSlotRes, 'Chốt ca phải thành công');

        $slotStmt = $this->db->prepare("SELECT is_closed FROM time_slots WHERE id = 1");
        $slotStmt->execute();
        $this->assertEquals(1, (int)$slotStmt->fetchColumn(), 'Ca 1 phải có is_closed = 1');
    }
}
