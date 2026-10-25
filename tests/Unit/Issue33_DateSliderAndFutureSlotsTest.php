<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ConfigService;
use App\Services\SlotService;
use PDO;

/**
 * Unit Test cho Issue #33:
 * 1. Nới rộng giới hạn khi hôm nay không đủ điều kiện chọn ca: tự động mở rộng và chuẩn bị ca cho các ngày tiếp theo trong lịch đặt.
 * 2. Hiển thị sẵn các ngày được phép chọn trong lịch đặt theo cấu hình advance_booking_days.
 * 3. Tự động nhận diện hôm nay hết ca để gợi ý/chọn ngày tiếp theo còn ca trống.
 * 4. Slide thanh trượt ngang chọn ngày (Horizontal Date Slider) với nút lướt và auto-scroll khi tràn màn hình.
 */
class Issue33_DateSliderAndFutureSlotsTest
{
    private ?PDO $db = null;
    private SlotService $slotService;
    private ConfigService $configService;
    private int $passed = 0;
    private int $failed = 0;

    public function runAllTests(): void
    {
        echo "\n=== [TEST] Issue #33: Nới Rộng Lịch Đặt & Slide Thanh Trượt Ngang Chọn Ngày ===\n";

        $this->setUp();
        $this->testEnsureSlotsForUpcomingDaysGeneratesStandardSlots();
        $this->tearDown();

        $this->setUp();
        $this->testEnsureSlotsRespectsExistingCustomSlots();
        $this->tearDown();

        $this->setUp();
        $this->testEnsureSlotsRespectsShopClosedDates();
        $this->tearDown();

        $this->setUp();
        $this->testGetAvailableDatesReturnsConsecutiveUpcomingDates();
        $this->tearDown();

        $this->setUp();
        $this->testTodayUnavailableFlagsAndSuggestsNextAvailableDate();
        $this->tearDown();

        $this->setUp();
        $this->testIndexHtmlAndCssAndJsHaveDateSliderIntegration();
        $this->tearDown();

        echo "Result: {$this->passed} passed, {$this->failed} failed.\n";
        if ($this->failed > 0) {
            throw new \RuntimeException("Issue #33 test suite failed!");
        }
    }

    private function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $schemaFile = __DIR__ . '/../../database/schema.sql';
        if (file_exists($schemaFile)) {
            $schemaSql = file_get_contents($schemaFile);
            $this->db->exec($schemaSql);
        }

        $this->configService = new ConfigService($this->db);
        $this->configService->set('advance_booking_days', '7');
        $this->configService->set('default_slot_capacity', '8');
        $this->configService->set('min_lead_time_hours', '3');
        $this->configService->set('urgent_lead_time_minutes', '3');

        $this->slotService = new SlotService($this->db, $this->configService);
    }

    private function tearDown(): void
    {
        $this->db = null;
    }

    /**
     * 1. Kiểm tra ensureSlotsForUpcomingDays tự động sinh đủ 4 ca mẫu cho các ngày tới
     */
    public function testEnsureSlotsForUpcomingDaysGeneratesStandardSlots(): void
    {
        $today = date('Y-m-d');
        // Ban đầu chỉ có 1 ca hôm nay
        $stmt = $this->db->prepare("INSERT INTO time_slots (slot_date, slot_time, max_capacity, is_active) VALUES (?, '11:30 - 12:15', 8, 1)");
        $stmt->execute([$today]);

        // Gọi hàm ensure 5 ngày tới
        $this->slotService->ensureSlotsForUpcomingDays(5);

        // Kiểm tra ngày mai và ngày kia đều có 4 ca
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $dayAfterTomorrow = date('Y-m-d', strtotime('+2 days'));

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM time_slots WHERE slot_date = ?");
        $stmt->execute([$tomorrow]);
        $countTomorrow = (int)$stmt->fetchColumn();

        $stmt->execute([$dayAfterTomorrow]);
        $countDayAfter = (int)$stmt->fetchColumn();

        $this->assertEquals(4, $countTomorrow, "Ngày mai phải có đủ 4 ca chuẩn");
        $this->assertEquals(4, $countDayAfter, "Ngày kia phải có đủ 4 ca chuẩn");
    }

    /**
     * 2. Kiểm tra không đè lên ngày đã được Admin cấu hình thủ công
     */
    public function testEnsureSlotsRespectsExistingCustomSlots(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        // Admin tạo 1 ca riêng biệt cho ngày mai
        $stmt = $this->db->prepare("INSERT INTO time_slots (slot_date, slot_time, max_capacity, is_active) VALUES (?, '15:00 - 16:00', 12, 1)");
        $stmt->execute([$tomorrow]);

        $this->slotService->ensureSlotsForUpcomingDays(3);

        // Số ca của ngày mai phải giữ nguyên là 1 (không tự ý nhét thêm ca mẫu đè lên)
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM time_slots WHERE slot_date = ?");
        $stmt->execute([$tomorrow]);
        $countTomorrow = (int)$stmt->fetchColumn();

        $this->assertEquals(1, $countTomorrow, "Ngày mai đã có ca admin cấu hình thì không bị sinh đè ca mẫu");
    }

    /**
     * 3. Kiểm tra tôn trọng ngày shop đóng cửa tạm nghỉ
     */
    public function testEnsureSlotsRespectsShopClosedDates(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        // Cấu hình shop tạm nghỉ vào ngày mai
        $this->configService->set('is_shop_closed', '1');
        $this->configService->set('closed_from_date', $tomorrow);
        $this->configService->set('closed_to_date', $tomorrow);

        $this->slotService->ensureSlotsForUpcomingDays(3);

        // Ngày mai không được sinh ca
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM time_slots WHERE slot_date = ?");
        $stmt->execute([$tomorrow]);
        $countTomorrow = (int)$stmt->fetchColumn();

        $this->assertEquals(0, $countTomorrow, "Ngày shop đóng cửa không được tự động sinh ca");
    }

    /**
     * 4. Kiểm tra getAvailableDates trả về danh sách các ngày liên tiếp trong phạm vi advance_booking_days
     */
    public function testGetAvailableDatesReturnsConsecutiveUpcomingDates(): void
    {
        $dates = $this->slotService->getAvailableDates();
        $this->assertTrue(count($dates) >= 7, "Danh sách ngày khả dụng phải có ít nhất 7 ngày tiếp theo");

        $today = date('Y-m-d');
        $this->assertEquals($today, $dates[0], "Ngày đầu tiên trong danh sách phải là hôm nay");
        $this->assertEquals(date('Y-m-d', strtotime('+1 day')), $dates[1], "Ngày thứ 2 phải là ngày mai");
    }

    /**
     * 5. Kiểm tra khi hôm nay hết ca / quá giờ, cờ isTodayAllUnavailable bật và chỉ đúng ngày mai còn ca
     */
    public function testTodayUnavailableFlagsAndSuggestsNextAvailableDate(): void
    {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        // Giả lập hôm nay có 1 ca nhưng max_capacity = 0 (đã hết chỗ)
        $stmt = $this->db->prepare("INSERT INTO time_slots (id, slot_date, slot_time, max_capacity, is_active) VALUES (101, ?, '11:30 - 12:15', 0, 1)");
        $stmt->execute([$today]);

        // Ngày mai có ca trống
        $stmt = $this->db->prepare("INSERT INTO time_slots (id, slot_date, slot_time, max_capacity, is_active) VALUES (102, ?, '11:30 - 12:15', 8, 1)");
        $stmt->execute([$tomorrow]);

        // Lấy danh sách slot hôm nay
        $todaySlots = $this->slotService->getSlotsByDate($today);
        $isTodayAllUnavailable = true;
        foreach ($todaySlots as $s) {
            if ($s['is_bookable']) {
                $isTodayAllUnavailable = false;
                break;
            }
        }
        $this->assertTrue($isTodayAllUnavailable, "Hôm nay hết chỗ thì isTodayAllUnavailable phải là true");

        // Tìm ngày tương lai khả dụng gần nhất
        $allDates = [$today, $tomorrow];
        $firstAvailableFutureDate = null;
        foreach ($allDates as $d) {
            if ($d <= $today) continue;
            $fSlots = $this->slotService->getSlotsByDate($d);
            foreach ($fSlots as $fs) {
                if ($fs['is_bookable']) {
                    $firstAvailableFutureDate = $d;
                    break 2;
                }
            }
        }

        $this->assertEquals($tomorrow, $firstAvailableFutureDate, "Ngày tương lai gần nhất còn chỗ phải là ngày mai");
    }

    /**
     * 6. Kiểm tra tích hợp HTML, CSS và JavaScript của Slide Thanh Trượt Ngang
     */
    public function testIndexHtmlAndCssAndJsHaveDateSliderIntegration(): void
    {
        $indexContent = file_get_contents(__DIR__ . '/../../index.php');
        $cssContent = file_get_contents(__DIR__ . '/../../assets/css/style.css');
        $jsContent = file_get_contents(__DIR__ . '/../../assets/js/app.js');

        // Kiểm tra index.php
        $this->assertStringContains('date-slider-wrapper', $indexContent, "index.php phải có date-slider-wrapper");
        $this->assertStringContains('btnDateSliderPrev', $indexContent, "index.php phải có btnDateSliderPrev");
        $this->assertStringContains('btnDateSliderNext', $indexContent, "index.php phải có btnDateSliderNext");
        $this->assertStringContains('dateStripContainer', $indexContent, "index.php phải có dateStripContainer");
        $this->assertStringContains('data-date=', $indexContent, "index.php phải có thuộc tính data-date cho mỗi chip");
        $this->assertStringContains('btnNextAvailableDate', $indexContent, "index.php phải có nút chuyển nhanh sang ngày tiếp theo");

        // Kiểm tra style.css
        $this->assertStringContains('.date-slider-wrapper', $cssContent, "style.css phải định nghĩa .date-slider-wrapper");
        $this->assertStringContains('.date-slider-btn', $cssContent, "style.css phải định nghĩa .date-slider-btn");
        $this->assertStringContains('.date-slider-btn.is-disabled', $cssContent, "style.css phải có class .is-disabled cho nút trượt");
        $this->assertStringContains('.badge-ended-chip', $cssContent, "style.css phải có class .badge-ended-chip");

        // Kiểm tra app.js
        $this->assertStringContains('btnDateSliderPrev', $jsContent, "app.js phải tương tác với btnDateSliderPrev");
        $this->assertStringContains('btnDateSliderNext', $jsContent, "app.js phải tương tác với btnDateSliderNext");
        $this->assertStringContains('updateDateSliderButtons', $jsContent, "app.js phải có hàm updateDateSliderButtons");
        $this->assertStringContains('activeChip', $jsContent, "app.js phải có logic tự cuộn activeChip vào giữa");
    }

    private function assertTrue(bool $condition, string $message = ''): void
    {
        if ($condition) {
            $this->passed++;
        } else {
            $this->failed++;
            echo "[FAIL] " . ($message ?: 'Condition is not true') . "\n";
        }
    }

    private function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertTrue(!$condition, $message ?: 'Condition is not false');
    }

    private function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            $this->passed++;
        } else {
            $this->failed++;
            echo "[FAIL] " . ($message ?: "Expected " . json_encode($expected) . " but got " . json_encode($actual)) . "\n";
        }
    }

    private function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) !== false) {
            $this->passed++;
        } else {
            $this->failed++;
            echo "[FAIL] " . ($message ?: "String does not contain needle: {$needle}") . "\n";
        }
    }
}
