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

            $slot['booked_count'] = $booked;
            $slot['remaining_capacity'] = $remaining;
            $slot['is_full'] = ($remaining <= 0);
            $slot['is_past_slot'] = $isPastSlot;
            $slot['is_past_cutoff'] = $isPastCutoff;
            $slot['cutoff_hours'] = $leadHours;
            $slot['is_bookable'] = (!$slot['is_full'] && !$isPastCutoff && (int)$slot['is_active'] === 1);
        }
        unset($slot);

        return $slots;
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
            SELECT id, slot_date, slot_time, max_capacity, is_active, created_at 
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
        }
        unset($slot);

        return $slots;
    }
}
