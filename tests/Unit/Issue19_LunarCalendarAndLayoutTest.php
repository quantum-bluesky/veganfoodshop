<?php

namespace Tests\Unit;

use App\Services\LunarCalendarService;

/**
 * Test Suite cho Issue #19:
 * Lịch âm Mùng 1 & Rằm, Trung tâm thông báo và Tối ưu bố cục giao diện
 */
class Issue19_LunarCalendarAndLayoutTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function runAllTests(): void
    {
        echo "\n=== [TEST] Issue #19: Lịch Âm Mùng 1 & Rằm và Tối Ưu Bố Cục Frontend ===\n";

        $this->testSolarToLunarAccurate();
        $this->testGetLunarInfoForDate();
        $this->testUpcomingSpecialVeganDays();
        $this->testFrontendMarkupStructure();

        echo "=> Kết quả Issue #19: {$this->passed} PASS, {$this->failed} FAIL\n\n";

        if ($this->failed > 0) {
            throw new \RuntimeException("Issue19_LunarCalendarAndLayoutTest failed with {$this->failed} errors");
        }
    }

    private function testSolarToLunarAccurate(): void
    {
        // 2026-02-17 là Mùng 1 Tết Bính Ngọ (1/1/2026 ÂL)
        [$d1, $m1, $y1, $leap1] = LunarCalendarService::solarToLunar(17, 2, 2026);
        $this->assert($d1 === 1 && $m1 === 1 && $y1 === 2026, "2026-02-17 là Mùng 1 Tết 1/1/2026 ÂL");

        // 2026-03-03 là Rằm tháng Giêng (15/1/2026 ÂL)
        [$d2, $m2, $y2, $leap2] = LunarCalendarService::solarToLunar(3, 3, 2026);
        $this->assert($d2 === 15 && $m2 === 1 && $y2 === 2026, "2026-03-03 là Rằm tháng Giêng 15/1/2026 ÂL");

        // 2024-08-18 là Rằm tháng 7 Vu Lan (15/7/2024 ÂL)
        [$d3, $m3, $y3, $leap3] = LunarCalendarService::solarToLunar(18, 8, 2024);
        $this->assert($d3 === 15 && $m3 === 7 && $y3 === 2024, "2024-08-18 là Rằm tháng 7 15/7/2024 ÂL");
    }

    private function testGetLunarInfoForDate(): void
    {
        // Mùng 1
        $info1 = LunarCalendarService::getLunarInfoForDate('2026-02-17');
        $this->assert($info1['is_first_day'] === true, "Mùng 1 nhận diện đúng is_first_day = true");
        $this->assert($info1['is_vegan_day'] === true, "Mùng 1 nhận diện đúng is_vegan_day = true");
        $this->assert($info1['badge']['type'] === 'first_day', "Mùng 1 có badge type first_day");
        $this->assert($info1['special_event'] === 'Tết Nguyên Đán', "2026-02-17 có event Tết Nguyên Đán");

        // Rằm
        $info2 = LunarCalendarService::getLunarInfoForDate('2026-03-03');
        $this->assert($info2['is_full_moon'] === true, "Rằm nhận diện đúng is_full_moon = true");
        $this->assert($info2['is_vegan_day'] === true, "Rằm nhận diện đúng is_vegan_day = true");
        $this->assert($info2['badge']['type'] === 'full_moon', "Rằm có badge type full_moon");
    }

    private function testUpcomingSpecialVeganDays(): void
    {
        $upcoming = LunarCalendarService::getUpcomingSpecialVeganDays('2026-10-25', 2);
        $this->assert(count($upcoming) === 2, "Tìm được 2 ngày âm lịch đặc biệt sắp tới");
        $this->assert($upcoming[0]['solar_date'] === '2026-11-09', "Ngày âm đặc biệt kế tiếp là 2026-11-09 (Mùng 1/10 ÂL)");
        $this->assert($upcoming[0]['is_first_day'] === true, "Ngày kế tiếp là Mùng 1");
        $this->assert($upcoming[0]['days_away'] === 15, "Cách 15 ngày tính từ 25/10");
        $this->assert($upcoming[1]['solar_date'] === '2026-11-23', "Ngày kế tiếp nữa là 2026-11-23 (Rằm 15/10 ÂL)");
    }

    private function testFrontendMarkupStructure(): void
    {
        $indexContent = file_get_contents(__DIR__ . '/../../index.php');
        $this->assert(str_contains($indexContent, 'zen-hub-card'), "index.php có thẻ zen-hub-card");
        $this->assert(str_contains($indexContent, 'date-strip-container'), "index.php có thanh date-strip-container");
        $this->assert(str_contains($indexContent, 'category-pills-bar'), "index.php có thanh category-pills-bar");
        $this->assert(str_contains($indexContent, 'floatingBottomBar'), "index.php có thanh floatingBottomBar");
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            echo "  [PASS] {$message}\n";
            $this->passed++;
        } else {
            echo "  [FAIL] {$message}\n";
            $this->failed++;
        }
    }
}
