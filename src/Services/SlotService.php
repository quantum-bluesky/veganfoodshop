<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Database\Connection;

class SlotService
{
    public function __construct(
        private ?PDO $db = null
    ) {
        $this->db = $db ?? Connection::getInstance();
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
     * Lấy danh sách ca theo ngày kèm số suất còn lại
     */
    public function getSlotsByDate(string $date, bool $activeOnly = true): array
    {
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

            $slot['booked_count'] = $booked;
            $slot['remaining_capacity'] = $remaining;
            $slot['is_full'] = ($remaining <= 0);
        }
        unset($slot);

        return $slots;
    }

    /**
     * Lấy danh sách các ngày có mở ca (sắp xếp tăng dần)
     */
    public function getAvailableDates(): array
    {
        $stmt = $this->db->query('
            SELECT DISTINCT slot_date 
            FROM time_slots 
            WHERE is_active = 1 
              AND slot_date >= date(\'now\', \'localtime\')
            ORDER BY slot_date ASC 
            LIMIT 7
        ');
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
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
     * Lấy tất cả ca phục vụ gần nhất cho trang quản trị
     */
    public function getAllSlotsForAdmin(int $limit = 30): array
    {
        $stmt = $this->db->prepare('
            SELECT id, slot_date, slot_time, max_capacity, is_active, created_at 
            FROM time_slots 
            ORDER BY slot_date DESC, slot_time ASC 
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $slots = $stmt->fetchAll();

        foreach ($slots as &$slot) {
            $slotId = (int)$slot['id'];
            $slot['booked_count'] = $this->getBookedFoodCount($slotId);
            $slot['remaining_capacity'] = max(0, (int)$slot['max_capacity'] - $slot['booked_count']);
        }
        unset($slot);

        return $slots;
    }
}
