<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;
use App\Services\OrderService;
use App\Services\SlotService;
use App\Exceptions\SlotFullException;

class SlotConcurrencyTest extends TestCase
{
    public function runAllTests(): void
    {
        $this->setUp();
        $this->testLastSlotCapacityBoundary();
        $this->tearDown();
    }

    /**
     * TC-INT-02: Kiểm tra ngưỡng chặn quá tải khi đặt suất cuối cùng
     */
    public function testLastSlotCapacityBoundary(): void
    {
        $orderService = new OrderService($this->db);
        $slotService = new SlotService($this->db);

        // Đặt 9 suất trong ca 1 (max_capacity = 10)
        $orderService->createOrder([
            'customer_name' => 'Khách 1',
            'customer_phone' => '0900000001',
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'payment_method' => 'vietqr',
            'items' => [1 => 9],
        ]);

        $this->assertEquals(1, $slotService->getRemainingCapacity(1), 'Chỉ còn đúng 1 suất cuối cùng');

        // Khách A đặt thành công suất cuối cùng
        $orderA = $orderService->createOrder([
            'customer_name' => 'Khách A',
            'customer_phone' => '0900000002',
            'slot_id' => 1,
            'order_type' => 'takeaway',
            'payment_method' => 'vietqr',
            'items' => [1 => 1],
        ]);

        $this->assertTrue(!empty($orderA['id']), 'Khách A đặt thành công suất cuối');
        $this->assertEquals(0, $slotService->getRemainingCapacity(1), 'Ca 1 đã đầy 10/10 suất');

        // Khách B đến sau gửi đơn đặt 1 suất -> Bị từ chối với SlotFullException
        $rejected = false;
        try {
            $orderService->createOrder([
                'customer_name' => 'Khách B',
                'customer_phone' => '0900000003',
                'slot_id' => 1,
                'order_type' => 'dine_in',
                'payment_method' => 'vietqr',
                'items' => [1 => 1],
            ]);
        } catch (SlotFullException $e) {
            $rejected = true;
        }

        $this->assertTrue($rejected, 'Khách B phải bị từ chối do ca đã hết suất');
        $this->assertEquals(10, $slotService->getBookedFoodCount(1), 'Tổng số suất không bao giờ vượt quá 10');
    }
}
