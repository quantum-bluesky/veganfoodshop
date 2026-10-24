<?php
declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\OrderService;
use App\Services\SlotService;
use App\Exceptions\SlotFullException;

class SlotCapacityTest extends TestCase
{
    public function runAllTests(): void
    {
        $this->setUp();
        $this->testRemainingCapacityCalculation();
        $this->tearDown();

        $this->setUp();
        $this->testSlotCapacityExceededException();
        $this->tearDown();
    }

    /**
     * Kiểm tra tính số suất khả dụng của ca
     */
    public function testRemainingCapacityCalculation(): void
    {
        $slotService = new SlotService($this->db);
        $orderService = new OrderService($this->db);

        // Ca 1 ban đầu có max_capacity = 10, chưa có đơn nào
        $this->assertEquals(10, $slotService->getRemainingCapacity(1), 'Ban đầu ca 1 còn đủ 10 suất');

        // Tạo đơn 3 tô phở nấm (món chính category = 'food')
        $orderService->createOrder([
            'customer_name' => 'Chị Lan',
            'customer_phone' => '0912345678',
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'payment_method' => 'pay_later',
            'items' => [
                1 => 3, // 3 tô phở nấm
                3 => 2, // 2 ly trà sen (category = 'drink', không tính vào suất ăn chính)
            ],
        ]);

        // Số suất đã đặt phải là 3 (chỉ tính món ăn chính)
        $this->assertEquals(3, $slotService->getBookedFoodCount(1), 'Số suất ăn chính đã đặt là 3');
        $this->assertEquals(7, $slotService->getRemainingCapacity(1), 'Số suất còn lại là 7');
    }

    /**
     * TC-UNIT-04: Đặt vượt quá max_capacity ném SlotFullException
     */
    public function testSlotCapacityExceededException(): void
    {
        $slotService = new SlotService($this->db);
        $orderService = new OrderService($this->db);

        // Đặt trước 9 tô phở nấm vào ca 1 (max_capacity = 10)
        $orderService->createOrder([
            'customer_name' => 'Khách A',
            'customer_phone' => '0900000001',
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'payment_method' => 'vietqr',
            'items' => [1 => 9],
        ]);

        $this->assertEquals(1, $slotService->getRemainingCapacity(1), 'Ca 1 chỉ còn 1 suất');

        // Khách B cố gắng đặt thêm 2 tô phở nấm -> Phải ném ngoại lệ SlotFullException
        $exceptionThrown = false;
        try {
            $orderService->createOrder([
                'customer_name' => 'Khách B',
                'customer_phone' => '0900000002',
                'slot_id' => 1,
                'order_type' => 'dine_in',
                'payment_method' => 'vietqr',
                'items' => [1 => 2],
            ]);
        } catch (SlotFullException $e) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown, 'Phải ném SlotFullException khi đặt vượt công suất ca');
        $this->assertEquals(1, $slotService->getRemainingCapacity(1), 'Số suất còn lại vẫn giữ nguyên là 1');
    }
}
