<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Database\Connection;

class SlotService
{
    private ConfigService $configService;

    public function __construct(
        private ?PDO $db = null,
        ?ConfigService $configService = null
    ) {
        $this->db = $db ?? Connection::getInstance();
        $this->configService = $configService ?? new ConfigService($this->db);
    }

    /**
     * Tách và phân tích thời điểm bắt đầu của ca (timestamp)
     */
    public function parseSlotStartTime(string $slotDate, string $slotTime): int
    {
        $parts = explode('-', $slotTime);
        $startTimeStr = trim($parts[0]);
        $dt = strtotime($slotDate . ' ' . $startTimeStr);
        return $dt !== false ? $dt : strtotime($slotDate . ' 00:00:00');
    }

    /**
     * Lấy thông tin một ca theo ID
     */
    public function getSlot(int $slotId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, slot_date, slot_time, max_capacity, is_active, created_at FROM time_slots WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $slotId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Tính tổng số suất món ăn chính ('food') đã được đặt trong ca (bỏ qua đơn bị hủy 'cancelled')
     */
    public function getBookedFoodCount(int $slotId): int
    {
        $stmt = $this->db->prepare('
            SELECT COALESCE(SUM(oi.quantity), 0) AS booked_count
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN menu_items mi ON oi.menu_item_id = mi.id
            WHERE o.slot_id = :slot_id 
              AND o.order_status != \'cancelled\'
              AND mi.category = \'food\'
        ');
        $stmt->execute([':slot_id' => $slotId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Tính số suất còn lại của ca
     */
    public function getRemainingCapacity(int $slotId): int
    {
        $slot = $this->getSlot($slotId);
        if (!$slot || (int)$slot['is_active'] !== 1) {
            return 0;
        }

        $booked = $this->getBookedFoodCount($slotId);
        $max = (int)$slot['max_capacity'];
        return max(0, $max - $booked);
    }

    /**
     * Lấy danh sách ca theo ngày kèm số suất còn lại và kiểm tra hạn cut-off đặt trước
     */
    public function getSlotsByDate(string $date, bool $activeOnly = true, ?int $minLeadTimeHours = null, ?int $currentTime = null): array
    {
        $leadHours = $minLeadTimeHours ?? (int)$this->configService->get('min_lead_time_hours', '3');
        $now = $currentTime ?? time();

        if ($activeOnly) {
            $closedStatus = $this->configService->getShopClosedStatus($date);
            if ($closedStatus['is_closed']) {
                return [];
            }
        }

        $sql = 'SELECT id, slot_date, slot_time, max_capacity, is_active FROM time_slots WHERE slot_date = :slot_date';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY slot_time ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slot_date' => $date]);
        $slots = $stmt->fetchAll();

        foreach ($slots as &$slot) {
            $slotId = (int)$slot['id'];
            $booked = $this->getBookedFoodCount($slotId);
            $max = (int)$slot['max_capacity'];
            $remaining = max(0, $max - $booked);

            $slotStart = $this->parseSlotStartTime((string)$slot['slot_date'], (string)$slot['slot_time']);
            $cutoff = $slotStart - ($leadHours * 3600);

            $isPastSlot = ($now >= $slotStart);
            $isPastCutoff = ($now > $cutoff);
            // Vượt quá thời gian đặt lịch từ 3 phút trở lên (Issue #29)
            $isExceeded3M = ($now >= ($cutoff + 180)) || ($now >= ($slotStart + 180));

            $slot['booked_count'] = $booked;
            $slot['remaining_capacity'] = $remaining;
            $slot['is_full'] = ($remaining <= 0);
            $slot['is_past_slot'] = $isPastSlot;
            $slot['is_past_cutoff'] = $isPastCutoff;
            $slot['is_exceeded_3m'] = $isExceeded3M;
            $slot['cutoff_hours'] = $leadHours;
            $slot['is_bookable'] = (!$slot['is_full'] && !$isPastCutoff && (int)$slot['is_active'] === 1);
        }
        unset($slot);

        return $slots;
    }

    /**
     * Tìm ca phục vụ khả dụng tiếp theo để gợi ý cho khách khi ca hiện tại bị đầy hoặc quá hạn đặt
     * (Issue #29)
     */
    public function getNextAvailableSlot(string $slotDate, int $currentSlotId, ?int $currentTime = null): ?array
    {
        $now = $currentTime ?? time();
        $leadHours = (int)$this->configService->get('min_lead_time_hours', '3');

        $currentSlot = $this->getSlot($currentSlotId);
        $currentStart = 0;
        if ($currentSlot) {
            $currentStart = $this->parseSlotStartTime((string)$currentSlot['slot_date'], (string)$currentSlot['slot_time']);
        }

        // 1. Tìm các ca còn trống trong cùng ngày (ưu tiên ca diễn ra sau ca hiện tại)
        $sameDaySlots = $this->getSlotsByDate($slotDate, true, $leadHours, $now);
        foreach ($sameDaySlots as $s) {
            if ((int)$s['id'] === $currentSlotId) {
                continue;
            }
            $sStart = $this->parseSlotStartTime((string)$s['slot_date'], (string)$s['slot_time']);
            if ($sStart > $currentStart && !empty($s['is_bookable'])) {
                return $s;
            }
        }

        // Thử bất kỳ ca nào khác còn bookable trong cùng ngày
        foreach ($sameDaySlots as $s) {
            if ((int)$s['id'] !== $currentSlotId && !empty($s['is_bookable'])) {
                return $s;
            }
        }

        // 2. Tìm ca khả dụng đầu tiên ở các ngày tiếp theo
        $availableDates = $this->getAvailableDates();
        foreach ($availableDates as $nextDate) {
            if ($nextDate <= $slotDate) {
                continue;
            }
            $nextDaySlots = $this->getSlotsByDate($nextDate, true, $leadHours, $now);
            foreach ($nextDaySlots as $ns) {
                if (!empty($ns['is_bookable'])) {
                    return $ns;
                }
            }
        }

        return null;
    }

    /**
     * Lấy danh sách các ngày có mở ca (sắp xếp tăng dần, trong khoảng advance_booking_days)
     */
    public function getAvailableDates(?int $advanceBookingDays = null): array
    {
        $days = $advanceBookingDays ?? (int)$this->configService->get('advance_booking_days', '7');
        $today = date('Y-m-d');
        $maxDate = date('Y-m-d', strtotime("+{$days} days"));

        $stmt = $this->db->prepare('
            SELECT DISTINCT slot_date 
            FROM time_slots 
            WHERE is_active = 1 
              AND slot_date >= :today
              AND slot_date <= :max_date
            ORDER BY slot_date ASC
        ');
        $stmt->execute([
            ':today' => $today,
            ':max_date' => $maxDate,
        ]);
        $rawDates = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $availableDates = [];
        foreach ($rawDates as $d) {
            $status = $this->configService->getShopClosedStatus((string)$d);
            if (!$status['is_closed']) {
                $availableDates[] = (string)$d;
            }
        }
        return $availableDates;
    }

    /**
     * Thêm ca mới (Admin)
     */
    public function createSlot(string $date, string $time, int $maxCapacity = 12): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO time_slots (slot_date, slot_time, max_capacity, is_active) 
            VALUES (:date, :time, :capacity, 1)
        ');
        $stmt->execute([
            ':date' => $date,
            ':time' => $time,
            ':capacity' => $maxCapacity,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Bật/Tắt ca phục vụ (Admin)
     */
    public function toggleSlotActive(int $slotId, bool $isActive): bool
    {
        $stmt = $this->db->prepare('UPDATE time_slots SET is_active = :is_active WHERE id = :id');
        return $stmt->execute([
            ':is_active' => $isActive ? 1 : 0,
            ':id' => $slotId,
        ]);
    }

    /**
     * Cập nhật số suất tối đa (Admin)
     */
    public function updateMaxCapacity(int $slotId, int $maxCapacity): bool
    {
        $stmt = $this->db->prepare('UPDATE time_slots SET max_capacity = :capacity WHERE id = :id');
        return $stmt->execute([
            ':capacity' => max(1, $maxCapacity),
            ':id' => $slotId,
        ]);
    }

    /**
     * Lấy danh sách ca phục vụ cho trang quản trị với bộ lọc tiện dụng (today, upcoming, history, all)
     */
    public function getAllSlotsForAdmin(int $limit = 50, string $filter = 'all'): array
    {
        $today = date('Y-m-d');
        $whereSql = '';
        $params = [];

        if ($filter === 'today') {
            $whereSql = 'WHERE slot_date = :today';
            $params[':today'] = $today;
        } elseif ($filter === 'upcoming') {
            $whereSql = 'WHERE slot_date >= :today';
            $params[':today'] = $today;
        } elseif ($filter === 'history') {
            $whereSql = 'WHERE slot_date < :today';
            $params[':today'] = $today;
        }

        $orderSql = ($filter === 'history') ? 'ORDER BY slot_date DESC, slot_time DESC' : 'ORDER BY slot_date ASC, slot_time ASC';

        $sql = "
            SELECT id, slot_date, slot_time, max_capacity, is_active, is_closed, created_at 
            FROM time_slots 
            $whereSql
            $orderSql 
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $slots = $stmt->fetchAll();

        $now = time();
        foreach ($slots as &$slot) {
            $slotId = (int)$slot['id'];
            $slot['booked_count'] = $this->getBookedFoodCount($slotId);
            $slot['remaining_capacity'] = max(0, (int)$slot['max_capacity'] - $slot['booked_count']);
            $slotStart = $this->parseSlotStartTime((string)$slot['slot_date'], (string)$slot['slot_time']);
            $slot['is_past'] = ($now >= $slotStart);
            $slot['is_closed'] = (int)($slot['is_closed'] ?? 0);
        }
        unset($slot);

        return $slots;
    }

    /**
     * Nhân bản lịch ca hàng loạt cho 1 tuần hoặc 1 tháng từ 1 ngày mẫu (Issue #15)
     *
     * @param string $templateDate Ngày mẫu lấy lịch ca (Y-m-d)
     * @param array<int> $selectedDaysOfWeek Danh sách các thứ trong tuần được mở ca (0=Chủ Nhật, 1=Thứ Hai, ..., 6=Thứ Bảy)
     * @param string $startDate Ngày bắt đầu nhân bản (Y-m-d)
     * @param string $endDate Ngày kết thúc nhân bản (Y-m-d)
     * @param bool $overwriteExisting Có làm mới ca các ngày đã tồn tại chưa có đơn không
     * @return array{created_count: int, days_count: int, template_slots: int}
     */
    public function replicateSlots(
        string $templateDate,
        array $selectedDaysOfWeek,
        string $startDate,
        string $endDate,
        bool $overwriteExisting = false
    ): array {
        // Lấy danh sách các ca của ngày mẫu
        $stmtTpl = $this->db->prepare('
            SELECT slot_time, max_capacity, is_active 
            FROM time_slots 
            WHERE slot_date = :template_date 
            ORDER BY slot_time ASC
        ');
        $stmtTpl->execute([':template_date' => $templateDate]);
        $templateSlots = $stmtTpl->fetchAll();

        if (empty($templateSlots)) {
            throw new \App\Exceptions\ValidationException(sprintf('Ngày mẫu %s chưa có ca phục vụ nào để nhân bản.', $templateDate));
        }

        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        if ($startTs === false || $endTs === false || $startTs > $endTs) {
            throw new \App\Exceptions\ValidationException('Khoảng ngày nhân bản không hợp lệ.');
        }

        $createdCount = 0;
        $daysCount = 0;

        $stmtCheck = $this->db->prepare('
            SELECT id FROM time_slots WHERE slot_date = :date AND slot_time = :time LIMIT 1
        ');
        $stmtInsert = $this->db->prepare('
            INSERT INTO time_slots (slot_date, slot_time, max_capacity, is_active, is_closed) 
            VALUES (:date, :time, :capacity, :active, 0)
        ');
        $stmtDeleteUnbooked = $this->db->prepare('
            DELETE FROM time_slots 
            WHERE slot_date = :date 
              AND id NOT IN (SELECT DISTINCT slot_id FROM orders)
        ');

        $currentTs = $startTs;
        while ($currentTs <= $endTs) {
            $curDate = date('Y-m-d', $currentTs);
            $dayOfWeek = (int)date('w', $currentTs);

            if (in_array($dayOfWeek, $selectedDaysOfWeek, true)) {
                $daysCount++;

                if ($overwriteExisting) {
                    $stmtDeleteUnbooked->execute([':date' => $curDate]);
                }

                foreach ($templateSlots as $tpl) {
                    // Kiểm tra xem ca đã tồn tại chưa
                    $stmtCheck->execute([
                        ':date' => $curDate,
                        ':time' => $tpl['slot_time'],
                    ]);
                    $exists = $stmtCheck->fetchColumn();

                    if (!$exists) {
                        $stmtInsert->execute([
                            ':date' => $curDate,
                            ':time' => $tpl['slot_time'],
                            ':capacity' => (int)$tpl['max_capacity'],
                            ':active' => (int)$tpl['is_active'],
                        ]);
                        $createdCount++;
                    }
                }
            }

            $currentTs = strtotime('+1 day', $currentTs);
        }

        return [
            'created_count' => $createdCount,
            'days_count' => $daysCount,
            'template_slots' => count($templateSlots),
        ];
    }

    /**
     * Đồng bộ các ngày mở ca trong tuần theo 1 ngày chỉnh sửa (Issue #15)
     */
    public function syncWeekSlotsFromDate(string $sourceDate, array $targetDaysOfWeek = []): array
    {
        $sourceTs = strtotime($sourceDate);
        if ($sourceTs === false) {
            throw new \App\Exceptions\ValidationException('Ngày nguồn không hợp lệ.');
        }

        // Tìm thứ 2 và Chủ nhật của tuần chứa ngày nguồn
        $monday = date('Y-m-d', strtotime('monday this week', $sourceTs));
        $sunday = date('Y-m-d', strtotime('sunday this week', $sourceTs));

        // Mặc định chọn tất cả các thứ trong tuần trừ ngày nguồn
        if (empty($targetDaysOfWeek)) {
            $targetDaysOfWeek = [1, 2, 3, 4, 5, 6, 0];
        }

        return $this->replicateSlots($sourceDate, $targetDaysOfWeek, $monday, $sunday, false);
    }

    /**
     * Cập nhật thông tin ca và tuỳ chọn đồng bộ sang các ca cùng giờ trong tuần (Issue #15)
     */
    public function updateSlot(int $slotId, string $newTime, int $newCapacity, bool $syncSameWeek = false): bool
    {
        $slot = $this->getSlot($slotId);
        if (!$slot) {
            return false;
        }

        $oldTime = (string)$slot['slot_time'];
        $slotDate = (string)$slot['slot_date'];

        $stmt = $this->db->prepare('
            UPDATE time_slots 
            SET slot_time = :time, max_capacity = :capacity 
            WHERE id = :id
        ');
        $success = $stmt->execute([
            ':time' => $newTime,
            ':capacity' => max(1, $newCapacity),
            ':id' => $slotId,
        ]);

        if ($success && $syncSameWeek) {
            $sourceTs = strtotime($slotDate);
            if ($sourceTs !== false) {
                $monday = date('Y-m-d', strtotime('monday this week', $sourceTs));
                $sunday = date('Y-m-d', strtotime('sunday this week', $sourceTs));

                $stmtSync = $this->db->prepare('
                    UPDATE time_slots 
                    SET slot_time = :new_time, max_capacity = :new_capacity 
                    WHERE slot_date >= :monday 
                      AND slot_date <= :sunday 
                      AND slot_time = :old_time
                      AND id != :current_id
                ');
                $stmtSync->execute([
                    ':new_time' => $newTime,
                    ':new_capacity' => max(1, $newCapacity),
                    ':monday' => $monday,
                    ':sunday' => $sunday,
                    ':old_time' => $oldTime,
                    ':current_id' => $slotId,
                ]);
            }
        }

        return $success;
    }

    /**
     * Xóa ca phục vụ nếu chưa có đơn đặt
     */
    public function deleteSlot(int $slotId): bool
    {
        $booked = $this->getBookedFoodCount($slotId);
        if ($booked > 0) {
            throw new \App\Exceptions\ValidationException('Không thể xóa ca đã có khách đặt chỗ. Bạn có thể tắt ca (Tạm ngưng) thay vì xóa.');
        }

        $stmt = $this->db->prepare('DELETE FROM time_slots WHERE id = :id');
        return $stmt->execute([':id' => $slotId]);
    }
}
