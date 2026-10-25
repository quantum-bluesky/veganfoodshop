<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ConfigService;
use App\Services\SlotService;
use App\Services\OrderService;
use App\Exceptions\ValidationException;
use PDO;

/**
 * Unit Test cho Issue #32:
 * 1. Khung thông báo: layout text ở trước, button góc dưới bên phải không thừa khoảng trống.
 * 2. Nội dung text cho Dine-in: 3 trường hợp chuẩn xác.
 * 3. Mở rộng cho Takeaway: các trường hợp sắp đóng cửa, sau 22h, xác nhận thời gian chuẩn bị & ship.
 */
class Issue32_NoticeUiAndTakeawayFlowTest
{
    private ?PDO $db = null;
    private SlotService $slotService;
    private OrderService $orderService;
    private ConfigService $configService;
    private int $passed = 0;
    private int $failed = 0;

    public function runAllTests(): void
    {
        echo "\n=== [TEST] Issue #32: UI Thông Báo Góc Phải & Logic Đặt Mang Về Takeaway ===\n";

        $this->setUp();
        $this->testDineInCase1NotOverdueAndHasLaterSlot();
        $this->tearDown();

        $this->setUp();
        $this->testDineInCase2OverdueDaySlotAndHasLaterSlot();
        $this->tearDown();

        $this->setUp();
        $this->testDineInCase3OverdueLastSlotUnder30MinutesAndHasCapacity();
        $this->tearDown();

        $this->setUp();
        $this->testDineInAllPastWhenOverdueLastSlot30MinutesOrMore();
        $this->tearDown();

        $this->setUp();
        $this->testTakeawayCaseLateNightAfter22h();
        $this->tearDown();

        $this->setUp();
        $this->testTakeawayCaseClosingSoonBetween21hAnd22h();
        $this->tearDown();

        $this->setUp();
        $this->testTakeawayCaseNormalBefore21h();
        $this->tearDown();

        $this->setUp();
        $this->testTakeawayCanStillOrderWhenDineInIsBlockedByCutoff();
        $this->tearDown();

        $this->testUiLayoutStructureNoticeAndTakeawayConfirmInIndexPhp();
        $this->testJsScriptImplementsDynamicNoticeAndTakeawayValidation();

        echo "=> Kết quả Issue #32: {$this->passed} PASS, {$this->failed} FAIL\n";

        if ($this->failed > 0) {
            throw new \RuntimeException("Có {$this->failed} test thất bại trong Issue32_NoticeUiAndTakeawayFlowTest!");
        }
    }

    protected function setUp(): void
    {
        // Sử dụng CSDL SQLite :memory: cô lập tuyệt đối, không đụng vào bep_an_yen.db
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $schemaFile = __DIR__ . '/../../database/schema.sql';
        if (file_exists($schemaFile)) {
            $schemaSql = file_get_contents($schemaFile);
            $this->db->exec($schemaSql);
        }

        $this->configService = new ConfigService($this->db);
        $this->slotService = new SlotService($this->db);
        $this->orderService = new OrderService($this->db);

        // Thiết lập cấu hình chuẩn
        $this->configService->set('min_lead_time_hours', '3');
        $this->configService->set('seller_phone', '0901 234 567');
        $this->configService->set('seller_zalo_url', 'https://zalo.me/0901234567');
    }

    protected function tearDown(): void
    {
        $this->db = null;
    }

    private function seedSlotsAndMenu(string $today): void
    {
        // 2 ca: Ca trưa 11:30 (cutoff 08:30), Ca tối 18:00 (cutoff 15:00)
        $stmtSlot = $this->db->prepare('
            INSERT INTO time_slots (slot_date, slot_time, max_capacity, is_active)
            VALUES (:d, :t, :cap, 1)
        ');
        $stmtSlot->execute([':d' => $today, ':t' => '11:30 - 12:15', ':cap' => 10]);
        $stmtSlot->execute([':d' => $today, ':t' => '18:00 - 19:00', ':cap' => 10]);

        // Seed món ăn
        $stmtMenu = $this->db->prepare('
            INSERT INTO menu_items (id, name, description, price, category, is_available)
            VALUES (:id, :name, :desc, :price, :cat, 1)
        ');
        $stmtMenu->execute([':id' => 1, ':name' => 'Phở nấm hương', ':desc' => 'Nước dùng thanh', ':price' => 45000, ':cat' => 'food']);
        $stmtMenu->execute([':id' => 2, ':name' => 'Trà sen tuyết', ':desc' => 'Trà ướp hoa sen', ':price' => 25000, ':cat' => 'drink']);
    }

    public function testDineInCase1NotOverdueAndHasLaterSlot(): void
    {
        $today = date('Y-m-d');
        $this->seedSlotsAndMenu($today);

        // 07:30 sáng: chưa quá cutoff 08:30 của ca trưa 11:30, còn ca tối 18:00
        $mockTime = strtotime($today . ' 07:30:00');
        $ctx = $this->slotService->getSlotNoticeContext($today, 'dine_in', $mockTime, 3);

        $this->assertTrue($ctx['show_notice'], 'Case 1: Phải hiển thị thông báo');
        $this->assertEquals('dine_in_case_1', $ctx['case'], 'Case 1: Rơi đúng Case 1 của Dine-in');
        $this->assertFalse($ctx['disable_all_slots'], 'Case 1: Không được disable slot trong Case 1');
        $this->assertStringContainsString('Khung giờ cần đặt trước 3h để bếp kịp ninh nước dùng tươi mới.', $ctx['notice_text'], 'Case 1: Đúng nội dung thông báo 1');
    }

    public function testDineInCase2OverdueDaySlotAndHasLaterSlot(): void
    {
        $today = date('Y-m-d');
        $this->seedSlotsAndMenu($today);

        // 10:00 sáng: đã quá cutoff 08:30 của ca trưa, nhưng chưa tới cutoff 15:00 của ca tối
        $mockTime = strtotime($today . ' 10:00:00');
        $ctx = $this->slotService->getSlotNoticeContext($today, 'dine_in', $mockTime, 3);

        $this->assertTrue($ctx['show_notice'], 'Case 2: Phải hiển thị thông báo');
        $this->assertEquals('dine_in_case_2', $ctx['case'], 'Case 2: Rơi đúng Case 2 của Dine-in');
        $this->assertFalse($ctx['disable_all_slots'], 'Case 2: Không disable toàn bộ slot vì vẫn còn ca sau');
        $this->assertStringContainsString('Nếu thời gian thực tế đã vượt quá giờ đặt lịch, bạn thương vui lòng lựa chọn đặt vào ca tiếp theo hoặc click liên hệ trực tiếp', $ctx['notice_text'], 'Case 2: Đúng nội dung thông báo 2');
    }

    public function testDineInCase3OverdueLastSlotUnder30MinutesAndHasCapacity(): void
    {
        $today = date('Y-m-d');
        $this->seedSlotsAndMenu($today);

        // Ca cuối là 18:00, cutoff là 15:00.
        // 15:15 chiều: vượt quá cutoff ca cuối 15 phút (< 30 phút) và ca còn 10 chỗ trống
        $mockTime = strtotime($today . ' 15:15:00');
        $ctx = $this->slotService->getSlotNoticeContext($today, 'dine_in', $mockTime, 3);

        $this->assertTrue($ctx['show_notice'], 'Case 3: Phải hiển thị thông báo');
        $this->assertEquals('dine_in_case_3', $ctx['case'], 'Case 3: Rơi đúng Case 3 của Dine-in');
        $this->assertTrue($ctx['disable_all_slots'], 'Case 3: Bắt buộc disable all slot trong ngày');
        $this->assertStringContainsString('Khung giờ cần đặt trước 3h để bếp kịp ninh nước dùng tươi mới. Bạn thương vui lòng liên hệ trực tiếp.', $ctx['notice_text'], 'Case 3: Đúng nội dung thông báo 3');
    }

    public function testDineInAllPastWhenOverdueLastSlot30MinutesOrMore(): void
    {
        $today = date('Y-m-d');
        $this->seedSlotsAndMenu($today);

        // 15:45 chiều: vượt quá cutoff ca cuối 45 phút (>= 30 phút)
        $mockTime = strtotime($today . ' 15:45:00');
        $ctx = $this->slotService->getSlotNoticeContext($today, 'dine_in', $mockTime, 3);

        $this->assertEquals('dine_in_all_past', $ctx['case'], 'Dine-in All Past: Đúng trạng thái all_past');
        $this->assertTrue($ctx['disable_all_slots'], 'Dine-in All Past: Disable toàn bộ slot');
    }

    public function testTakeawayCaseLateNightAfter22h(): void
    {
        $today = date('Y-m-d');
        $this->seedSlotsAndMenu($today);

        // 22:30 đêm: Quá muộn (sau 22:00)
        $mockTime = strtotime($today . ' 22:30:00');
        $ctx = $this->slotService->getSlotNoticeContext($today, 'takeaway', $mockTime, 3);

        $this->assertEquals('takeaway_late_night', $ctx['case'], 'Takeaway sau 22h: Đúng case late_night');
        $this->assertTrue($ctx['disable_all_slots'], 'Takeaway sau 22h: Không nhận giao ca tối nay');
        $this->assertFalse($ctx['can_submit'], 'Takeaway sau 22h: Không cho submit');
        $this->assertStringContainsString('Bếp đã vào giờ nghỉ ngơi (sau 22:00), không giao ngay trong đêm', $ctx['notice_text'], 'Takeaway sau 22h: Đúng text nhắc nhở');

        // OrderService từ chối đặt đơn mang về giao tối nay sau 22h
        $rejected = false;
        try {
            $this->orderService->createOrder([
                'customer_name' => 'Khách Đêm',
                'customer_phone' => '0912345678',
                'slot_id' => 2,
                'order_type' => 'takeaway',
                'payment_method' => 'vietqr',
                'current_time' => $mockTime,
                'items' => [1 => 1],
            ]);
        } catch (ValidationException $e) {
            $rejected = str_contains($e->getMessage(), 'Sau 22:00 bếp đã vào giờ nghỉ ngơi');
        }
        $this->assertTrue($rejected, 'Takeaway sau 22h: OrderService ném ValidationException thông báo giờ nghỉ ngơi');
    }

    public function testTakeawayCaseClosingSoonBetween21hAnd22h(): void
    {
        $today = date('Y-m-d');
        $this->seedSlotsAndMenu($today);

        // 21:15 tối: Sắp đóng cửa (21:00 - 22:00)
        $mockTime = strtotime($today . ' 21:15:00');
        $ctx = $this->slotService->getSlotNoticeContext($today, 'takeaway', $mockTime, 3);

        $this->assertEquals('takeaway_closing_soon', $ctx['case'], 'Takeaway 21h-22h: Đúng case closing_soon');
        $this->assertTrue($ctx['require_takeaway_confirm'], 'Takeaway 21h-22h: Bắt buộc confirm');
        $this->assertStringContainsString('Tôi xác nhận có thể nhận món trước 22:00 và đồng ý thời gian chuẩn bị & ship của bếp (30 - 45 phút).', $ctx['takeaway_confirm_label'], 'Takeaway 21h-22h: Nhãn confirm rõ ràng');
        $this->assertStringContainsString('Bếp chuẩn bị đóng cửa (nhận đơn mang đi trước 22:00). Món cần 30 - 45 phút chuẩn bị & giao hàng', $ctx['notice_text'], 'Takeaway 21h-22h: Đúng text thông báo');
    }

    public function testTakeawayCaseNormalBefore21h(): void
    {
        $today = date('Y-m-d');
        $this->seedSlotsAndMenu($today);

        // 14:00 chiều: Ban ngày bình thường
        $mockTime = strtotime($today . ' 14:00:00');
        $ctx = $this->slotService->getSlotNoticeContext($today, 'takeaway', $mockTime, 3);

        $this->assertEquals('takeaway_normal', $ctx['case'], 'Takeaway ban ngày: Đúng case normal');
        $this->assertTrue($ctx['require_takeaway_confirm'], 'Takeaway ban ngày: Cần confirm thời gian');
        $this->assertStringContainsString('Tôi đồng ý với thời gian chuẩn bị & ship món của bếp (khoảng 30 - 45 phút).', $ctx['takeaway_confirm_label'], 'Takeaway ban ngày: Đúng nhãn confirm 30-45p');
        $this->assertStringContainsString('thời gian chuẩn bị & ship khoảng 30 - 45 phút', $ctx['notice_text'], 'Takeaway ban ngày: Thông báo thời gian chuẩn bị');
    }

    public function testTakeawayCanStillOrderWhenDineInIsBlockedByCutoff(): void
    {
        $today = date('Y-m-d');
        $this->seedSlotsAndMenu($today);

        // Lúc 16:00: Ca tối 18:00 (slot_id 2) đã quá cutoff 3h (15:00)
        $mockTime = strtotime($today . ' 16:00:00');

        // 1. Nếu là dine_in -> Bị chặn bởi cutoff
        $dineInRejected = false;
        try {
            $this->orderService->createOrder([
                'customer_name' => 'Khách Ăn Tại Quán',
                'customer_phone' => '0901111222',
                'slot_id' => 2,
                'order_type' => 'dine_in',
                'payment_method' => 'vietqr',
                'current_time' => $mockTime,
                'items' => [1 => 1],
            ]);
        } catch (ValidationException $e) {
            $dineInRejected = true;
        }
        $this->assertTrue($dineInRejected, 'Dine-in phải bị chặn khi quá cutoff 3h');

        // 2. Nếu là takeaway -> Vẫn đặt được vì còn món ăn và chưa qua giờ
        $orderTakeaway = $this->orderService->createOrder([
            'customer_name' => 'Khách Mang Về',
            'customer_phone' => '0901111222',
            'slot_id' => 2,
            'order_type' => 'takeaway',
            'payment_method' => 'vietqr',
            'current_time' => $mockTime,
            'items' => [1 => 2],
        ]);
        $this->assertNotEmpty($orderTakeaway['id'], 'Đơn takeaway vẫn đặt thành công khi còn món');
        $this->assertEquals('takeaway', $orderTakeaway['order_type'], 'Đúng order_type takeaway');
    }

    public function testUiLayoutStructureNoticeAndTakeawayConfirmInIndexPhp(): void
    {
        $indexContent = file_get_contents(__DIR__ . '/../../index.php');

        // 1. Kiểm tra layout khung thông báo: text đi trước button
        $this->assertStringContainsString('id="urgentZaloNote"', $indexContent, 'index.php có #urgentZaloNote');
        $this->assertStringContainsString('id="urgentZaloNoteText"', $indexContent, 'index.php có #urgentZaloNoteText');
        $this->assertStringContainsString('id="urgentZaloBtnWrapper"', $indexContent, 'index.php có #urgentZaloBtnWrapper');
        $this->assertStringContainsString('id="btnUrgentZalo"', $indexContent, 'index.php có #btnUrgentZalo');

        // Đảm bảo urgentZaloNoteText xuất hiện trước btnUrgentZalo
        $posText = strpos($indexContent, 'id="urgentZaloNoteText"');
        $posBtn = strpos($indexContent, 'id="btnUrgentZalo"');
        $this->assertTrue($posText !== false && $posBtn !== false && $posText < $posBtn, 'Nội dung text phải ở trước button');

        // Đảm bảo nút Zalo nằm ở góc dưới bên phải
        $this->assertStringContainsString('justify-content: flex-end', $indexContent, 'Button phải được căn sát góc dưới bên phải');

        // 2. Kiểm tra khối xác nhận takeaway
        $this->assertStringContainsString('id="takeawayConfirmBox"', $indexContent, 'Có container takeawayConfirmBox');
        $this->assertStringContainsString('id="takeawayConfirmCheck"', $indexContent, 'Có checkbox takeawayConfirmCheck');
        $this->assertStringContainsString('name="takeaway_time_confirmed"', $indexContent, 'Có input name takeaway_time_confirmed');
    }

    public function testJsScriptImplementsDynamicNoticeAndTakeawayValidation(): void
    {
        $jsContent = file_get_contents(__DIR__ . '/../../assets/js/app.js');

        $this->assertStringContainsString('updateNoticeAndSlotsForOrderType', $jsContent, 'app.js có hàm cập nhật ca & thông báo theo loại đơn');
        $this->assertStringContainsString('takeawayConfirmBox', $jsContent, 'app.js có xử lý takeawayConfirmBox');
        $this->assertStringContainsString('takeawayConfirmCheck', $jsContent, 'app.js có kiểm tra takeawayConfirmCheck');
        $this->assertStringContainsString('tick xác nhận đồng ý với thời gian chuẩn bị', $jsContent, 'app.js nhắc nhở tick xác nhận thời gian chuẩn bị & ship');
        $this->assertStringContainsString('Sau 22:00 bếp đã vào giờ nghỉ ngơi', $jsContent, 'app.js cảnh báo giờ nghỉ ngơi sau 22:00');
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

    private function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assert($condition === true, $message);
    }

    private function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assert($condition === false, $message);
    }

    private function assertNotEmpty($value, string $message = ''): void
    {
        $this->assert(!empty($value), $message);
    }

    private function assertEquals($expected, $actual, string $message = ''): void
    {
        $this->assert($expected === $actual, $message ?: "Kỳ vọng: " . var_export($expected, true) . ", Thực tế: " . var_export($actual, true));
    }

    private function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        $this->assert(str_contains($haystack, $needle), $message ?: "Chuỗi không chứa '{$needle}'");
    }
}
