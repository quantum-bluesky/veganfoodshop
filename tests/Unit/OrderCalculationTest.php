<?php
declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\OrderService;

class OrderCalculationTest extends TestCase
{
    public function runAllTests(): void
    {
        $this->setUp();
        $this->testOrderCalculationAndSnapshot();
        $this->tearDown();
    }

    /**
     * TC-UNIT-03: Tính tiền đơn hàng chính xác & snapshot giá vào order_items
     */
    public function testOrderCalculationAndSnapshot(): void
    {
        $orderService = new OrderService($this->db);

        // 2 Phở Nấm (45.000đ/tô) + 1 Trà Sen (25.000đ) = 115.000đ
        $order = $orderService->createOrder([
            'customer_name' => 'Anh Minh',
            'customer_phone' => '0987654321',
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'payment_method' => 'vietqr',
            'items' => [
                1 => 2, // Phở nấm: 45k * 2 = 90k
                3 => 1, // Trà sen: 25k * 1 = 25k
            ],
        ]);

        $this->assertEquals(115000, (int)$order['total_amount'], 'Tổng tiền phải bằng 115.000đ');

        // Kiểm tra snapshot trong order_items
        $this->assertEquals(2, count($order['items']), 'Đơn hàng gồm 2 dòng món');

        $line1 = $order['items'][0];
        $this->assertEquals(1, (int)$line1['menu_item_id']);
        $this->assertEquals(45000, (int)$line1['unit_price'], 'Giá phở snapshot là 45.000đ');
        $this->assertEquals(2, (int)$line1['quantity']);
        $this->assertEquals(90000, (int)$line1['subtotal']);

        $line2 = $order['items'][1];
        $this->assertEquals(3, (int)$line2['menu_item_id']);
        $this->assertEquals(25000, (int)$line2['unit_price'], 'Giá trà snapshot là 25.000đ');
        $this->assertEquals(1, (int)$line2['quantity']);
        $this->assertEquals(25000, (int)$line2['subtotal']);
    }
}
