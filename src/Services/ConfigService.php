<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Database\Connection;

class ConfigService
{
    public function __construct(
        private ?PDO $db = null
    ) {
        $this->db = $db ?? Connection::getInstance();
    }

    public function get(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare('SELECT config_value FROM system_config WHERE config_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : $default;
    }

    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT config_key, config_value, description FROM system_config');
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['config_key']] = $row['config_value'];
        }
        return $result;
    }

    public function set(string $key, string $value, ?string $description = null): bool
    {
        $stmt = $this->db->prepare('
            INSERT INTO system_config (config_key, config_value, description, updated_at) 
            VALUES (:key, :value, :desc, CURRENT_TIMESTAMP)
            ON CONFLICT(config_key) DO UPDATE SET 
                config_value = excluded.config_value, 
                description = COALESCE(excluded.description, system_config.description),
                updated_at = CURRENT_TIMESTAMP
        ');
        return $stmt->execute([
            ':key' => $key,
            ':value' => $value,
            ':desc' => $description,
        ]);
    }

    /**
     * Kiểm tra trạng thái tạm nghỉ / đi vắng của Bếp
     * 
     * @param ?string $date Ngày cần kiểm tra (Y-m-d), null = hôm nay
     * @return array{is_closed: bool, reason: string, from_date: ?string, to_date: ?string}
     */
    public function getShopClosedStatus(?string $date = null): array
    {
        $isClosed = ($this->get('is_shop_closed', '0') === '1');
        if (!$isClosed) {
            return [
                'is_closed' => false,
                'reason' => '',
                'from_date' => null,
                'to_date' => null,
            ];
        }

        $targetDate = $date ?? date('Y-m-d');
        $fromDate = $this->get('closed_from_date', '');
        $toDate = $this->get('closed_to_date', '');
        $reason = $this->get('closed_reason', 'Bếp An Yên xin phép tạm nghỉ đi hái trà & chuẩn bị nguyên liệu mới. Hẹn sớm gặp lại bạn thương nhé! 🍃');

        // Nếu không cài ngày cụ thể -> Tạm nghỉ vô thời hạn
        if ($fromDate === '' && $toDate === '') {
            return [
                'is_closed' => true,
                'reason' => $reason,
                'from_date' => null,
                'to_date' => null,
            ];
        }

        // Kiểm tra trong khoảng ngày nghỉ
        $inRange = true;
        if ($fromDate !== '' && $targetDate < $fromDate) {
            $inRange = false;
        }
        if ($toDate !== '' && $targetDate > $toDate) {
            $inRange = false;
        }

        return [
            'is_closed' => $inRange,
            'reason' => $reason,
            'from_date' => $fromDate ?: null,
            'to_date' => $toDate ?: null,
        ];
    }
}
