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
}
