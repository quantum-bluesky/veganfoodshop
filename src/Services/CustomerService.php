<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Database\Connection;

class CustomerService
{
    public function __construct(
        private ?PDO $db = null
    ) {
        $this->db = $db ?? Connection::getInstance();
    }

    /**
     * Chuẩn hóa số điện thoại (chỉ giữ lại số)
     */
    public function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', trim($phone)) ?? '';
    }

    /**
     * Tìm khách hàng theo số điện thoại
     */
    public function findByPhone(string $phone): ?array
    {
        $cleanPhone = $this->normalizePhone($phone);
        if ($cleanPhone === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT phone, name, is_trusted, note, created_at, updated_at FROM customers WHERE phone = :phone LIMIT 1');
        $stmt->execute([':phone' => $cleanPhone]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Kiểm tra số điện thoại có phải khách quen tin cậy hay không
     */
    public function isTrusted(string $phone): bool
    {
        $customer = $this->findByPhone($phone);
        return $customer !== null && ((int)$customer['is_trusted'] === 1);
    }

    /**
     * Lưu hoặc cập nhật thông tin khách hàng khi đặt đơn
     */
    public function saveOrUpdate(string $phone, string $name, ?string $note = null): array
    {
        $cleanPhone = $this->normalizePhone($phone);
        $cleanName = trim($name);

        $existing = $this->findByPhone($cleanPhone);

        if ($existing) {
            $stmt = $this->db->prepare('
                UPDATE customers 
                SET name = :name, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE phone = :phone
            ');
            $stmt->execute([
                ':name' => $cleanName !== '' ? $cleanName : $existing['name'],
                ':phone' => $cleanPhone,
            ]);
            return $this->findByPhone($cleanPhone) ?? [];
        }

        $stmt = $this->db->prepare('
            INSERT INTO customers (phone, name, is_trusted, note, created_at, updated_at) 
            VALUES (:phone, :name, 0, :note, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':phone' => $cleanPhone,
            ':name' => $cleanName,
            ':note' => $note,
        ]);

        return $this->findByPhone($cleanPhone) ?? [];
    }

    /**
     * Cập nhật trạng thái khách quen (admin)
     */
    public function setTrustedStatus(string $phone, bool $isTrusted): bool
    {
        $cleanPhone = $this->normalizePhone($phone);
        $stmt = $this->db->prepare('UPDATE customers SET is_trusted = :is_trusted, updated_at = CURRENT_TIMESTAMP WHERE phone = :phone');
        return $stmt->execute([
            ':is_trusted' => $isTrusted ? 1 : 0,
            ':phone' => $cleanPhone,
        ]);
    }

    /**
     * Lấy toàn bộ danh sách khách hàng phục vụ quản trị
     */
    public function getAllCustomers(?string $search = null): array
    {
        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $stmt = $this->db->prepare('
                SELECT phone, name, is_trusted, note, created_at, updated_at 
                FROM customers 
                WHERE phone LIKE :term OR name LIKE :term 
                ORDER BY is_trusted DESC, updated_at DESC
            ');
            $stmt->execute([':term' => $term]);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->query('
            SELECT phone, name, is_trusted, note, created_at, updated_at 
            FROM customers 
            ORDER BY is_trusted DESC, updated_at DESC
        ');
        return $stmt->fetchAll();
    }
}
