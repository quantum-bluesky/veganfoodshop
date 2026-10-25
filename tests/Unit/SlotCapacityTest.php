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

        $this->setUp();
        $this->testSlotCutoffLeadTimeAndPastSlotValidation();
        $this->tearDown();

        $this->setUp();
        $this->testAdvanceBookingWindowLimit();
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

    /**
     * TC-UNIT-05: Kiểm tra thời gian đặt trước tối thiểu (Cut-off n giờ) và ca đã qua
     */
    public function testSlotCutoffLeadTimeAndPastSlotValidation(): void
    {
        $slotService = new SlotService($this->db);
        $orderService = new OrderService($this->db);

        $today = date('Y-m-d');
        // Tạo ca lúc 11:30 hôm nay
        $slotId = $slotService->createSlot($today, '11:30 - 12:15', 10);

        // 1. Lúc 07:00 (cách 4.5h > cut-off 3h) -> Hợp lệ, được phép đặt
        $timeEarly = strtotime("$today 07:00:00");
        $slotsEarly = $slotService->getSlotsByDate($today, true, 3, $timeEarly);
        $targetSlotEarly = null;
        foreach ($slotsEarly as $s) {
            if ((int)$s['id'] === $slotId) {
                $targetSlotEarly = $s;
                break;
            }
        }
        $this->assertNotNull($targetSlotEarly);
        $this->assertFalse($targetSlotEarly['is_past_cutoff'], '07:00 chưa tới cut-off đặt ca 11:30');
        $this->assertTrue($targetSlotEarly['is_bookable'], '07:00 ca 11:30 vẫn được phép đặt');

        // 2. Lúc 09:30 (cách 2h < cut-off 3h) -> Quá hạn đặt trước
        $timeCutoff = strtotime("$today 09:30:00");
        $slotsCutoff = $slotService->getSlotsByDate($today, true, 3, $timeCutoff);
        $targetSlotCutoff = null;
        foreach ($slotsCutoff as $s) {
            if ((int)$s['id'] === $slotId) {
                $targetSlotCutoff = $s;
                break;
            }
        }
        $this->assertNotNull($targetSlotCutoff);
        $this->assertTrue($targetSlotCutoff['is_past_cutoff'], '09:30 đã quá hạn cut-off 3 tiếng');
        $this->assertFalse($targetSlotCutoff['is_bookable'], 'Quá hạn cut-off thì không được đặt tự động');

        // 3. Thử tạo đơn lúc 09:30 -> Phải ném ValidationException
        $cutoffFailed = false;
        try {
            $orderService->createOrder([
                'customer_name' => 'Khách Muộn',
                'customer_phone' => '0901234567',
                'slot_id' => $slotId,
                'order_type' => 'dine_in',
                'payment_method' => 'vietqr',
                'items' => [1 => 1],
                'current_time' => $timeCutoff,
            ]);
        } catch (\App\Exceptions\ValidationException $e) {
            $cutoffFailed = true;
        }
        $this->assertTrue($cutoffFailed, 'Phải chặn không cho tạo đơn khi quá thời gian cut-off');

        // 4. Lúc 12:00 -> Ca đã qua giờ
        $timePast = strtotime("$today 12:00:00");
        $slotsPast = $slotService->getSlotsByDate($today, true, 3, $timePast);
        $targetSlotPast = null;
        foreach ($slotsPast as $s) {
            if ((int)$s['id'] === $slotId) {
                $targetSlotPast = $s;
                break;
            }
        }
        $this->assertNotNull($targetSlotPast);
        $this->assertTrue($targetSlotPast['is_past_slot'], '12:00 ca 11:30 đã qua giờ bắt đầu');
    }

    /**
     * TC-UNIT-06: Kiểm tra giới hạn ngày đặt trước trong khoảng advance_booking_days (mặc định 7 ngày)
     */
    public function testAdvanceBookingWindowLimit(): void
    {
        $slotService = new SlotService($this->db);

        $today = date('Y-m-d');
        $day3 = date('Y-m-d', strtotime('+3 days'));
        $day7 = date('Y-m-d', strtotime('+7 days'));
        $day14 = date('Y-m-d', strtotime('+14 days'));

        $slotService->createSlot($day3, '11:30 - 12:15', 10);
        $slotService->createSlot($day7, '11:30 - 12:15', 10);
        $slotService->createSlot($day14, '11:30 - 12:15', 10);

        // Với giới hạn 7 ngày
        $dates = $slotService->getAvailableDates(7);

        $this->assertTrue(in_array($day3, $dates, true), 'Ngày +3 phải nằm trong danh sách mở đặt');
        $this->assertTrue(in_array($day7, $dates, true), 'Ngày +7 phải nằm trong danh sách mở đặt');
        $this->assertFalse(in_array($day14, $dates, true), 'Ngày +14 vượt quá 7 ngày nên không được hiển thị');
    }
}
