<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Database\Connection;

class MenuService
{
    public function __construct(
        private ?PDO $db = null
    ) {
        $this->db = $db ?? Connection::getInstance();
    }

    /**
     * Lấy danh sách món đang phục vụ (hiển thị trên web cho khách)
     */
    public function getActiveMenu(): array
    {
        $stmt = $this->db->query('
            SELECT id, name, description, price, category, image_url, is_available, sort_order 
            FROM menu_items 
            WHERE is_available = 1 
            ORDER BY sort_order ASC, id ASC
        ');
        return $stmt->fetchAll();
    }

    /**
     * Lấy toàn bộ thực đơn cho admin
     */
    public function getAllMenu(): array
    {
        $stmt = $this->db->query('
            SELECT id, name, description, price, category, image_url, is_available, sort_order 
            FROM menu_items 
            ORDER BY sort_order ASC, id ASC
        ');
        return $stmt->fetchAll();
    }

    /**
     * Lấy thông tin một món theo ID
     */
    public function getItemById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, description, price, category, image_url, is_available, sort_order FROM menu_items WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Bật / tắt khả dụng của món (Admin)
     */
    public function toggleAvailability(int $id, bool $isAvailable): bool
    {
        $stmt = $this->db->prepare('UPDATE menu_items SET is_available = :avail WHERE id = :id');
        return $stmt->execute([
            ':avail' => $isAvailable ? 1 : 0,
            ':id' => $id,
        ]);
    }

    /**
     * Cập nhật giá món (Admin)
     */
    public function updatePrice(int $id, int $price): bool
    {
        $stmt = $this->db->prepare('UPDATE menu_items SET price = :price WHERE id = :id');
        return $stmt->execute([
            ':price' => max(0, $price),
            ':id' => $id,
        ]);
    }

    /**
     * Thêm món mới vào thực đơn (Admin)
     */
    public function createItem(string $name, ?string $description, int $price, string $category = 'food', int $sortOrder = 0): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO menu_items (name, description, price, category, is_available, sort_order) 
            VALUES (:name, :desc, :price, :cat, 1, :sort)
        ');
        $stmt->execute([
            ':name' => trim($name),
            ':desc' => $description !== null ? trim($description) : null,
            ':price' => max(0, $price),
            ':cat' => in_array($category, ['food', 'drink', 'extra'], true) ? $category : 'food',
            ':sort' => $sortOrder,
        ]);
        return (int)$this->db->lastInsertId();
    }
}
