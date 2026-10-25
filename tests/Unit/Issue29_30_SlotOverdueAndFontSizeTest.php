<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use App\Services\SlotService;
use App\Services\OrderService;
use App\Services\ConfigService;
use App\Exceptions\ValidationException;

/**
 * Test Suite cho Issue #29 & Issue #30:
 * - Issue #29: Thông báo lịch sự khi vượt quá thời gian đặt lịch hơn 3 phút (gợi ý ca tiếp theo hoặc liên hệ chủ bếp)
 * - Issue #30: Giao diện và cơ chế phóng to cỡ chữ A+/A- dễ đọc trên flow đặt món
 */
class Issue29_30_SlotOverdueAndFontSizeTest
{
    private ?PDO $db = null;
    private int $passed = 0;
    private int $failed = 0;

    public function runAllTests(): void
    {
        echo "\n=== [TEST] Issue #29 & #30: Thông Báo Quá Giờ Đặt 3p & Chế Độ Cỡ Chữ A+/A- ===\n";

        $this->setUp();
        $this->testSlotExceeded3MinutesFlag();
        $this->tearDown();

        $this->setUp();
        $this->testGetNextAvailableSlot();
        $this->tearDown();

        $this->setUp();
        $this->testOrderCreationPoliteMessageExceeded3Minutes();
        $this->tearDown();

        $this->testFontSizeToggleAndStylesInHtml();

        echo "=> Kết quả Issue #29 & #30: {$this->passed} PASS, {$this->failed} FAIL\n";

        if ($this->failed > 0) {
            throw new \RuntimeException("Có {$this->failed} test thất bại trong Issue29_30_SlotOverdueAndFontSizeTest!");
        }
    }

    private function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Khởi tạo schema đầy đủ
        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
        $this->db->exec($schema);

        // Nạp seed data mẫu
        $seed = file_get_contents(__DIR__ . '/../../database/seed.sql');
        $this->db->exec($seed);
    }

    private function tearDown(): void
    {
        $this->db = null;
    }

    /**
     * Test cờ is_exceeded_3m khi vượt quá thời gian đặt lịch >= 3 phút
     */
    private function testSlotExceeded3MinutesFlag(): void
    {
        $slotService = new SlotService($this->db);
        $testDate = date('Y-m-d', strtotime('+1 day'));
        
        // Tạo ca 11:30 - 12:15. Với min_lead_time_hours = 3, cutoff = 08:30:00
        $slotService->createSlot($testDate, '11:30 - 12:15', 10);
        $slots = $slotService->getSlotsByDate($testDate, false);
        $targetSlot = $slots[0];
        $slotId = (int)$targetSlot['id'];

        // 1. Lúc 08:29:00 -> Chưa tới cutoff
        $timeBefore = strtotime("$testDate 08:29:00");
        $slotsBefore = $slotService->getSlotsByDate($testDate, true, 3, $timeBefore);
        $this->assertFalse($slotsBefore[0]['is_past_cutoff'], '08:29 chưa tới cutoff');
        $this->assertFalse($slotsBefore[0]['is_exceeded_3m'], '08:29 chưa vượt quá 3 phút');
        $this->assertTrue($slotsBefore[0]['is_bookable'], '08:29 vẫn được đặt ca');

        // 2. Lúc 08:31:00 -> Đã quá cutoff 1 phút (chưa quá 3 phút)
        $time1M = strtotime("$testDate 08:31:00");
        $slots1M = $slotService->getSlotsByDate($testDate, true, 3, $time1M);
        $this->assertTrue($slots1M[0]['is_past_cutoff'], '08:31 đã quá cutoff');
        $this->assertFalse($slots1M[0]['is_exceeded_3m'], '08:31 mới quá 1 phút, chưa vượt quá 3 phút');

        // 3. Lúc 08:33:05 -> Đã quá cutoff hơn 3 phút (>= 180s)
        $time3M = strtotime("$testDate 08:33:05");
        $slots3M = $slotService->getSlotsByDate($testDate, true, 3, $time3M);
        $this->assertTrue($slots3M[0]['is_past_cutoff'], '08:33 đã quá cutoff');
        $this->assertTrue($slots3M[0]['is_exceeded_3m'], '08:33 đã vượt quá khoảng thời gian đặt lịch hơn 3 phút');
    }

    /**
     * Test hàm getNextAvailableSlot tìm ca khả dụng tiếp theo
     */
    private function testGetNextAvailableSlot(): void
    {
        $slotService = new SlotService($this->db);
        $testDate = date('Y-m-d', strtotime('+1 day'));

        // Xoá ca cũ để test độc lập
        $this->db->exec("DELETE FROM time_slots WHERE slot_date = '$testDate'");

        // Tạo 3 ca:
        // Ca 1: 11:30 - 12:15
        // Ca 2: 12:30 - 13:15
        // Ca 3: 18:00 - 18:45
        $slotService->createSlot($testDate, '11:30 - 12:15', 10);
        $slotService->createSlot($testDate, '12:30 - 13:15', 10);
        $slotService->createSlot($testDate, '18:00 - 18:45', 10);

        $slots = $slotService->getSlotsByDate($testDate, false);
        $slot1Id = (int)$slots[0]['id'];
        $slot2Id = (int)$slots[1]['id'];

        $timeMorning = strtotime("$testDate 08:00:00");

        // Tìm ca tiếp theo của Ca 1 -> Phải là Ca 2 (12:30 - 13:15)
        $nextSlot = $slotService->getNextAvailableSlot($testDate, $slot1Id, $timeMorning);
        $this->assertNotNull($nextSlot, 'Phải tìm thấy ca tiếp theo');
        $this->assertEquals('12:30 - 13:15', $nextSlot['slot_time'], 'Ca tiếp theo của Ca 1 là 12:30');

        // Giả lập Ca 2 bị đầy (hết chỗ)
        $this->db->prepare("UPDATE time_slots SET max_capacity = 0 WHERE id = :id")->execute([':id' => $slot2Id]);

        // Tìm lại ca tiếp theo của Ca 1 -> Phải nhảy sang Ca 3 (18:00 - 18:45)
        $nextSlotFallback = $slotService->getNextAvailableSlot($testDate, $slot1Id, $timeMorning);
        $this->assertNotNull($nextSlotFallback, 'Phải tìm thấy ca khả dụng kế tiếp');
        $this->assertEquals('18:00 - 18:45', $nextSlotFallback['slot_time'], 'Khi Ca 2 hết chỗ, ca tiếp theo là 18:00');
    }

    /**
     * Test khi tạo đơn bị quá hạn 3 phút, hệ thống thông báo lịch sự đầy đủ 2 phương án
     */
    private function testOrderCreationPoliteMessageExceeded3Minutes(): void
    {
        $slotService = new SlotService($this->db);
        $orderService = new OrderService($this->db);
        $configService = new ConfigService($this->db);
        $configService->set('seller_phone', '0987 654 321');

        $testDate = date('Y-m-d', strtotime('+1 day'));
        $this->db->exec("DELETE FROM time_slots WHERE slot_date = '$testDate'");

        $slotService->createSlot($testDate, '11:30 - 12:15', 10);
        $slotService->createSlot($testDate, '12:30 - 13:15', 10);

        $slots = $slotService->getSlotsByDate($testDate, false);
        $slot1Id = (int)$slots[0]['id'];

        // Cutoff của ca 11:30 là 08:30:00.
        // Giả lập đặt lúc 08:34:00 (vượt quá 3 phút so với thời gian chốt đặt lịch)
        $timeOverdue3M = strtotime("$testDate 08:34:00");

        $caught = false;
        $errorMessage = '';
        try {
            $orderService->createOrder([
                'customer_name' => 'Bạn An Nhiên',
                'customer_phone' => '0909888999',
                'slot_id' => $slot1Id,
                'order_type' => 'dine_in',
                'payment_method' => 'vietqr',
                'items' => [1 => 1],
                'current_time' => $timeOverdue3M,
            ]);
        } catch (ValidationException $e) {
            $caught = true;
            $errorMessage = $e->getMessage();
        }

        $this->assertTrue($caught, 'Phải ném ValidationException khi vượt quá thời gian đặt lịch hơn 3 phút');
        $this->assertStringContainsString('vượt quá thời gian nhận đặt lịch hơn 3 phút', $errorMessage, 'Thông báo nêu rõ vượt quá thời gian nhận đặt hơn 3 phút');
        $this->assertStringContainsString('đặt vào ca tiếp theo', $errorMessage, 'Thông báo gợi ý lựa chọn đặt vào ca tiếp theo');
        $this->assertStringContainsString('12:30 - 13:15', $errorMessage, 'Thông báo gợi ý chính xác khung giờ ca tiếp theo');
        $this->assertStringContainsString('liên hệ trực tiếp với chủ bếp', $errorMessage, 'Thông báo gợi ý liên hệ trực tiếp chủ bếp');
        $this->assertStringContainsString('0987 654 321', $errorMessage, 'Thông báo chứa số điện thoại hotline chủ bếp');
    }

    /**
     * Test giao diện HTML và CSS hỗ trợ Issue #30 (A+/A-) và Issue #29
     */
    private function testFontSizeToggleAndStylesInHtml(): void
    {
        $indexHtml = file_get_contents(__DIR__ . '/../../index.php');
        $cssContent = file_get_contents(__DIR__ . '/../../assets/css/style.css');
        $appJsContent = file_get_contents(__DIR__ . '/../../assets/js/app.js');
        $processOrderHtml = file_get_contents(__DIR__ . '/../../process_order.php');

        // 1. Kiểm tra index.php có nút chuyển đổi cỡ chữ A+/A-
        $this->assertStringContainsString('id="btnToggleFontSize"', $indexHtml, 'index.php có nút btnToggleFontSize');
        $this->assertStringContainsString('id="fsIcon"', $indexHtml, 'index.php có icon fsIcon A+');
        $this->assertStringContainsString('id="fsLabel"', $indexHtml, 'index.php có nhãn fsLabel');
        $this->assertStringContainsString('window.SELLER_PHONE', $indexHtml, 'index.php truyền window.SELLER_PHONE vào client script');

        // 2. Kiểm tra CSS style.css có các rules cho font size mode
        $this->assertStringContainsString('.font-size-toggle-btn', $cssContent, 'style.css có style cho .font-size-toggle-btn');
        $this->assertStringContainsString('body.zen-font-large', $cssContent, 'style.css có rule body.zen-font-large');
        $this->assertStringContainsString('#urgentZaloNote', $cssContent, 'style.css tối ưu phóng to font chữ #urgentZaloNote');

        // 3. Kiểm tra app.js có xử lý click và lưu trữ localStorage
        $this->assertStringContainsString('btnToggleFontSize', $appJsContent, 'app.js xử lý sự kiện cho btnToggleFontSize');
        $this->assertStringContainsString('vegan_font_size_large', $appJsContent, 'app.js lưu trạng thái vào localStorage vegan_font_size_large');
        $this->assertStringContainsString('zen-font-large', $appJsContent, 'app.js toggle class zen-font-large');
        $this->assertStringContainsString('data-is-overdue', $appJsContent, 'app.js gắn attribute data-is-overdue cho slot');
        $this->assertStringContainsString('vượt quá thời gian nhận đặt lịch hơn 3 phút', $appJsContent, 'app.js có thông báo lịch sự khi bấm ca quá hạn');

        // 4. Kiểm tra process_order.php có 2 lựa chọn hành động khi lỗi
        $this->assertStringContainsString('Lựa chọn đặt vào ca tiếp theo', $processOrderHtml, 'process_order.php có nút Lựa chọn đặt vào ca tiếp theo');
        $this->assertStringContainsString('Liên hệ trực tiếp chủ bếp qua Zalo', $processOrderHtml, 'process_order.php có nút Liên hệ trực tiếp chủ bếp qua Zalo');
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

    private function assertNotNull($value, string $message = ''): void
    {
        $this->assert($value !== null, $message);
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
