<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;
use App\Services\OrderService;
use App\Exceptions\ValidationException;

class CreateOrderTransactionTest extends TestCase
{
    public function runAllTests(): void
    {
        $this->setUp();
        $this->testAtomicityRollbackOnInvalidDish();
        $this->tearDown();

        $this->setUp();
        $this->testOrderCancellationFreesCapacity();
        $this->tearDown();
    }

    /**
     * TC-INT-01: An toàn giao dịch - Rollback sạch sẽ khi có lỗi
     */
    public function testAtomicityRollbackOnInvalidDish(): void
    {
        $orderService = new OrderService($this->db);

        $initialOrdersCount = (int)$this->db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        $initialItemsCount = (int)$this->db->query('SELECT COUNT(*) FROM order_items')->fetchColumn();

        $failed = false;
        try {
            // Món ID 9999 không tồn tại trong thực đơn
            $orderService->createOrder([
                'customer_name' => 'Anh Minh',
                'customer_phone' => '0987654321',
                'slot_id' => 1,
                'order_type' => 'dine_in',
                'payment_method' => 'vietqr',
                'items' => [
                    1 => 1,
                    9999 => 1, // ID không hợp lệ
                ],
            ]);
        } catch (ValidationException $e) {
            $failed = true;
        }

        $this->assertTrue($failed, 'Bắt buộc phải ném lỗi khi món không tồn tại');

        // Số bản ghi orders và order_items phải giữ nguyên không đổi (đã rollback sạch)
        $currentOrdersCount = (int)$this->db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        $currentItemsCount = (int)$this->db->query('SELECT COUNT(*) FROM order_items')->fetchColumn();

        $this->assertEquals($initialOrdersCount, $currentOrdersCount, 'Bảng orders không được thêm bản ghi rác');
        $this->assertEquals($initialItemsCount, $currentItemsCount, 'Bảng order_items không được thêm bản ghi rác');
        $this->assertFalse($this->db->inTransaction(), 'Kết nối không được treo dở trong transaction');
    }

    /**
     * Kiểm tra khi đơn bị hủy (cancelled), số suất được hoàn trả tự động cho ca
     */
    public function testOrderCancellationFreesCapacity(): void
    {
        $orderService = new OrderService($this->db);
        $slotService = new \App\Services\SlotService($this->db);

        $order = $orderService->createOrder([
            'customer_name' => 'Chị Lan',
            'customer_phone' => '0912345678',
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'payment_method' => 'pay_later',
            'items' => [1 => 4], // 4 tô phở nấm
        ]);

        $this->assertEquals(6, $slotService->getRemainingCapacity(1), 'Sau khi đặt 4 tô, ca còn 6 suất');

        // Admin hoặc khách hủy đơn
        $orderService->updateOrderStatus((int)$order['id'], 'cancelled');

        // Suất phải được hoàn lại đầy đủ 10
        $this->assertEquals(10, $slotService->getRemainingCapacity(1), 'Sau khi hủy đơn, số suất hoàn lại 10');
    }
}
