<?php
declare(strict_types=1);

namespace Tests;

use PDO;
use App\Database\Connection;

abstract class TestCase
{
    protected ?PDO $db = null;

    protected function setUp(): void
    {
        // 100% CÔ LẬP: Dùng SQLite :memory: để không đụng vào bep_an_yen.db
        $this->db = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->db->exec('PRAGMA foreign_keys = ON;');

        // Nạp schema
        $schemaSql = file_get_contents(__DIR__ . '/../database/schema.sql');
        if ($schemaSql === false) {
            throw new \RuntimeException('Không đọc được file schema.sql');
        }
        $this->db->exec($schemaSql);

        // Seed dữ liệu tối thiểu
        $this->seedTestData();

        // Gán instance cho Connection
        Connection::setInstance($this->db);
    }

    protected function tearDown(): void
    {
        Connection::setInstance(null);
        $this->db = null;
    }

    protected function seedTestData(): void
    {
        // Khách quen mẫu
        $this->db->exec("
            INSERT INTO customers (phone, name, is_trusted) VALUES 
            ('0912345678', 'Chị Lan', 1),
            ('0987654321', 'Anh Minh', 0);
        ");

        // Món mẫu
        $this->db->exec("
            INSERT INTO menu_items (id, name, description, price, category, is_available, sort_order) VALUES 
            (1, 'Phở Nấm Hương Cổ Truyền', 'Nước dùng lê mía', 45000, 'food', 1, 1),
            (2, 'Bún Huế Chay An Điền', 'Nước dùng sả', 45000, 'food', 1, 2),
            (3, 'Trà Sen Tuyết Búp Nhẹ', 'Ướp hoa sen', 25000, 'drink', 1, 3);
        ");

        // Ca phục vụ mẫu (ngày mai để đảm bảo luôn hợp lệ thời gian đặt)
        $futureDate = date('Y-m-d', strtotime('+1 day'));
        $this->db->exec("
            INSERT INTO time_slots (id, slot_date, slot_time, max_capacity, is_active) VALUES 
            (1, '$futureDate', '11:30 - 12:15', 10, 1),
            (2, '$futureDate', '12:30 - 13:15', 12, 1);
        ");

        // Cấu hình mẫu
        $this->db->exec("
            INSERT INTO system_config (config_key, config_value) VALUES 
            ('min_lead_time_hours', '3'),
            ('advance_booking_days', '7');
        ");
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $msg = $message ?: sprintf("Failed asserting that %s matches expected %s", var_export($actual, true), var_export($expected, true));
            throw new \AssertionError($msg);
        }
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            throw new \AssertionError($message ?: "Failed asserting that condition is true");
        }
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        if ($condition) {
            throw new \AssertionError($message ?: "Failed asserting that condition is false");
        }
    }

    protected function assertNotNull(mixed $actual, string $message = ''): void
    {
        if ($actual === null) {
            throw new \AssertionError($message ?: "Failed asserting that value is not null");
        }
    }

    protected function assertNotEmpty(mixed $actual, string $message = ''): void
    {
        if (empty($actual)) {
            throw new \AssertionError($message ?: "Failed asserting that value is not empty");
        }
    }

    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            $msg = $message ?: sprintf("Failed asserting that '%s' contains '%s'", $haystack, $needle);
            throw new \AssertionError($msg);
        }
    }
}
