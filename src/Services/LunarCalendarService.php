<?php

namespace App\Services;

/**
 * LunarCalendarService
 * 
 * Dịch vụ tính toán Âm Lịch Việt Nam (Múi giờ GMT+7) thuần PHP không phụ thuộc thư viện ngoài.
 * Thuật toán thiên văn chuẩn của TS. Hồ Ngọc Đức (Jean Meeus astronomical algorithms).
 */
class LunarCalendarService
{
    private const TIMEZONE = 7.0;

    /**
     * Chuyển đổi ngày Dương Lịch (dd, mm, yyyy) sang Âm Lịch Việt Nam.
     * 
     * @param int $dd Ngày dương
     * @param int $mm Tháng dương
     * @param int $yy Năm dương
     * @param float $timeZone Múi giờ (mặc định 7 cho Việt Nam)
     * @return array [lunarDay, lunarMonth, lunarYear, isLeap]
     */
    public static function solarToLunar(int $dd, int $mm, int $yy, float $timeZone = self::TIMEZONE): array
    {
        $dayNumber = self::jdFromDate($dd, $mm, $yy);
        $k = (int)floor(($yy + ($mm - 0.5) / 12.0 - 1900) * 12.3685);

        // Tìm k sao cho getNewMoonDay(k) <= dayNumber < getNewMoonDay(k + 1)
        $nm = self::getNewMoonDay($k, $timeZone);
        while ($nm > $dayNumber) {
            $k--;
            $nm = self::getNewMoonDay($k, $timeZone);
        }
        while (true) {
            $nextNm = self::getNewMoonDay($k + 1, $timeZone);
            if ($nextNm <= $dayNumber) {
                $k++;
                $nm = $nextNm;
            } else {
                break;
            }
        }

        $a11 = self::getLunarMonth11($yy, $timeZone);
        $b11 = $a11;

        if ($a11 >= $dayNumber) {
            $lunarYear = $yy;
            $a11 = self::getLunarMonth11($yy - 1, $timeZone);
        } else {
            $lunarYear = $yy + 1;
            $b11 = self::getLunarMonth11($yy + 1, $timeZone);
        }

        $lunarDay = $dayNumber - $nm + 1;
        $diff = (int)floor(($nm - $a11) / 29.0);
        $lunarLeap = 0;
        $lunarMonth = $diff + 11;

        if ($b11 - $a11 > 365) {
            $leapMonthDiff = self::getLeapMonthOffset($a11, $timeZone);
            if ($diff >= $leapMonthDiff) {
                $lunarMonth = $diff + 10;
                if ($diff === $leapMonthDiff) {
                    $lunarLeap = 1;
                }
            }
        }

        if ($lunarMonth > 12) {
            $lunarMonth -= 12;
        }
        if ($lunarMonth >= 11 && $diff < 4) {
            $lunarYear--;
        }

        return [$lunarDay, $lunarMonth, $lunarYear, $lunarLeap === 1];
    }

    /**
     * Lấy thông tin chi tiết ngày Âm lịch và các sự kiện chay/lễ từ chuỗi ngày (YYYY-MM-DD).
     */
    public static function getLunarInfoForDate(string $dateString): array
    {
        $parts = explode('-', $dateString);
        if (count($parts) !== 3) {
            return [
                'solar_date' => $dateString,
                'lunar_day' => 0,
                'lunar_month' => 0,
                'lunar_year' => 0,
                'is_leap' => false,
                'lunar_text' => '',
                'is_first_day' => false,
                'is_full_moon' => false,
                'is_vegan_day' => false,
                'special_event' => null,
                'badge' => null,
            ];
        }

        $yy = (int)$parts[0];
        $mm = (int)$parts[1];
        $dd = (int)$parts[2];

        [$lunarDay, $lunarMonth, $lunarYear, $isLeap] = self::solarToLunar($dd, $mm, $yy);

        $isFirstDay = ($lunarDay === 1);
        $isFullMoon = ($lunarDay === 15);
        $isVeganDay = ($isFirstDay || $isFullMoon);

        $specialEvent = self::getSpecialEvent($lunarDay, $lunarMonth, $isLeap);

        $lunarText = ($lunarDay === 1) 
            ? "Mùng 1/{$lunarMonth}" . ($isLeap ? ' (Nhuận)' : '')
            : ($lunarDay === 15 ? "Rằm/{$lunarMonth}" : "{$lunarDay}/{$lunarMonth} ÂL");

        $badge = null;
        if ($isFirstDay) {
            $badge = [
                'type' => 'first_day',
                'icon' => '🌑',
                'label' => 'Mùng 1 (Sóc)',
                'class' => 'badge-lunar-first'
            ];
        } elseif ($isFullMoon) {
            $badge = [
                'type' => 'full_moon',
                'icon' => '🌕',
                'label' => 'Rằm (Vọng)',
                'class' => 'badge-lunar-full'
            ];
        } elseif ($specialEvent !== null) {
            $badge = [
                'type' => 'special_event',
                'icon' => '✨',
                'label' => $specialEvent,
                'class' => 'badge-lunar-event'
            ];
        }

        return [
            'solar_date' => $dateString,
            'lunar_day' => $lunarDay,
            'lunar_month' => $lunarMonth,
            'lunar_year' => $lunarYear,
            'is_leap' => $isLeap,
            'lunar_text' => $lunarText,
            'is_first_day' => $isFirstDay,
            'is_full_moon' => $isFullMoon,
            'is_vegan_day' => $isVeganDay,
            'special_event' => $specialEvent,
            'badge' => $badge,
        ];
    }

    /**
     * Tìm các ngày Mùng 1 (Sóc) hoặc Rằm (Vọng) hoặc ngày lễ chay kế tiếp gần nhất.
     * 
     * @param string $fromDate YYYY-MM-DD
     * @param int $limit Số lượng ngày cần tìm
     * @return array Danh sách thông tin ngày âm đặc biệt sắp tới
     */
    public static function getUpcomingSpecialVeganDays(string $fromDate, int $limit = 2): array
    {
        $results = [];
        $currentTs = strtotime($fromDate);
        if ($currentTs === false) {
            $currentTs = time();
        }

        // Quét tối đa 60 ngày tới
        for ($i = 0; $i < 60 && count($results) < $limit; $i++) {
            $dateStr = date('Y-m-d', $currentTs + ($i * 86400));
            $info = self::getLunarInfoForDate($dateStr);
            if ($info['is_vegan_day'] || $info['special_event'] !== null) {
                $daysAway = $i;
                $info['days_away'] = $daysAway;
                $results[] = $info;
            }
        }

        return $results;
    }

    /**
     * Xác định sự kiện / ngày lễ Phật giáo hoặc dân gian truyền thống quan trọng.
     */
    public static function getSpecialEvent(int $day, int $month, bool $isLeap = false): ?string
    {
        if ($isLeap) {
            return null;
        }

        if ($month === 1 && $day === 1) return 'Tết Nguyên Đán';
        if ($month === 1 && $day === 15) return 'Tết Nguyên Tiêu (Rằm tháng Giêng)';
        if ($month === 3 && $day === 3) return 'Tết Hàn Thực';
        if ($month === 4 && $day === 8) return 'Đại lễ Phật Đản (Truyền thống)';
        if ($month === 4 && $day === 15) return 'Đại lễ Phật Đản (Vesak)';
        if ($month === 5 && $day === 5) return 'Tết Đoan Ngọ';
        if ($month === 7 && $day === 15) return 'Đại lễ Vu Lan Báo Hiếu';
        if ($month === 8 && $day === 15) return 'Tết Trung Thu';
        if ($month === 10 && $day === 15) return 'Tết Hạ Nguyên';
        if ($month === 12 && $day === 23) return 'Tết Ông Công Ông Táo';

        return null;
    }

    // --- CÁC HÀM THUẬT TOÁN THIÊN VĂN NỘI BỘ ---

    private static function jdFromDate(int $dd, int $mm, int $yy): int
    {
        $a = (int)floor((14 - $mm) / 12);
        $y = $yy + 4800 - $a;
        $m = $mm + 12 * $a - 3;
        $jd = $dd + (int)floor((153 * $m + 2) / 5) + 365 * $y + (int)floor($y / 4) - (int)floor($y / 100) + (int)floor($y / 400) - 32045;
        if ($jd < 2299161) {
            $jd = $dd + (int)floor((153 * $m + 2) / 5) + 365 * $y + (int)floor($y / 4) - 32083;
        }
        return $jd;
    }

    private static function getNewMoonDay(int $k, float $timeZone): int
    {
        $t = $k / 1236.85;
        $t2 = $t * $t;
        $t3 = $t2 * $t;
        $dr = M_PI / 180;

        $jde = 2415020.75933 + 29.53058868 * $k + 0.0001178 * $t2 - 0.000000155 * $t3;
        $jde += 0.00033 * sin((166.56 + 132.87 * $t - 0.009173 * $t2) * $dr);

        $m = 359.2242 + 29.10535608 * $k - 0.0000333 * $t2 - 0.00000347 * $t3;
        $mpr = 306.0253 + 385.81691806 * $k + 0.0107306 * $t2 + 0.00001236 * $t3;
        $f = 21.2964 + 390.67050646 * $k - 0.0016528 * $t2 - 0.00000239 * $t3;

        $c1 = (0.1734 - 0.000393 * $t) * sin($m * $dr) + 0.0021 * sin(2 * $m * $dr);
        $c1 -= 0.4068 * sin($mpr * $dr) + 0.0161 * sin(2 * $mpr * $dr);
        $c1 -= 0.0004 * sin(3 * $mpr * $dr);
        $c1 += 0.0104 * sin(2 * $f * $dr) - 0.0051 * sin(($m + $mpr) * $dr);
        $c1 -= 0.0074 * sin(($m - $mpr) * $dr) + 0.0004 * sin((2 * $f + $m) * $dr);
        $c1 -= 0.0004 * sin((2 * $f - $m) * $dr) - 0.0006 * sin((2 * $f + $mpr) * $dr);
        $c1 += 0.0010 * sin((2 * $f - $mpr) * $dr) + 0.0005 * sin(($m + 2 * $mpr) * $dr);

        $deltat = ($t < -11) ? (0.001 + 0.000839 * $t + 0.0002261 * $t2 - 0.00000845 * $t3 - 0.00000061 * $t * $t3) : (-0.000078 + 0.000045 * $t);
        $jd = $jde + $c1 - $deltat;

        return (int)floor($jd + 0.5 + $timeZone / 24.0);
    }

    private static function getSunLongitude(int $dayNumber, float $timeZone): int
    {
        $t = ($dayNumber - 0.5 - $timeZone / 24.0 - 2451545.0) / 36525.0;
        $t2 = $t * $t;
        $dr = M_PI / 180;

        $l0 = 280.46645 + 36000.76983 * $t + 0.0003032 * $t2;
        $m = 357.52910 + 35999.05029 * $t - 0.0001537 * $t2;
        $c = (1.914600 - 0.004817 * $t - 0.000014 * $t2) * sin($m * $dr);
        $c += (0.019993 - 0.000101 * $t) * sin(2 * $m * $dr) + 0.000289 * sin(3 * $m * $dr);

        $theta = $l0 + $c;
        $theta = $theta - 360.0 * floor($theta / 360.0);

        return (int)floor($theta / 30.0);
    }

    private static function getLunarMonth11(int $yy, float $timeZone): int
    {
        $off = self::jdFromDate(31, 12, $yy) - 2415021;
        $k = (int)floor($off / 29.530588853);
        $nm = self::getNewMoonDay($k, $timeZone);
        $sunLong = self::getSunLongitude($nm, $timeZone);

        if ($sunLong >= 9) {
            $nm = self::getNewMoonDay($k - 1, $timeZone);
        }
        return $nm;
    }

    private static function getLeapMonthOffset(int $a11, float $timeZone): int
    {
        $k = (int)floor(($a11 - 2415021.0769986) / 29.530588853 + 0.5);
        $last = 0;
        $i = 1;
        $arc = self::getSunLongitude(self::getNewMoonDay($k + $i, $timeZone), $timeZone);

        do {
            $last = $arc;
            $i++;
            $arc = self::getSunLongitude(self::getNewMoonDay($k + $i, $timeZone), $timeZone);
        } while ($arc !== $last && $i < 14);

        return $i - 1;
    }
}
