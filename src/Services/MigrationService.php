<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;
use PDOException;
use RuntimeException;

class MigrationService
{
    private static bool $autoRunCompleted = false;
    private string $migrationsDir;

    public function __construct(
        private ?PDO $db = null,
        ?string $migrationsDir = null
    ) {
        $this->db = $db ?? Connection::getInstance();
        $this->migrationsDir = $migrationsDir ?? (__DIR__ . '/../../database/migrations');
        $this->ensureMigrationsTable();
    }

    /**
     * Tự động chạy migrations khi ứng dụng khởi động (chỉ chạy 1 lần duy nhất trong vòng đời tiến trình)
     */
    public static function runAutoMigrations(?PDO $db = null): array
    {
        if (self::$autoRunCompleted) {
            return [];
        }

        $service = new self($db);
        $executed = $service->runPendingMigrations();
        self::$autoRunCompleted = true;

        return $executed;
    }

    /**
     * Đặt lại cờ tự động chạy (hữu ích cho unit test)
     */
    public static function resetAutoRunFlag(): void
    {
        self::$autoRunCompleted = false;
    }

    /**
     * Khởi tạo bảng migrations nếu chưa tồn tại
     */
    public function ensureMigrationsTable(): void
    {
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT UNIQUE NOT NULL,
                batch INTEGER NOT NULL DEFAULT 1,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ');
    }

    /**
     * Lấy danh sách các file migration đã thực thi
     * 
     * @return string[]
     */
    public function getAppliedMigrations(): array
    {
        $stmt = $this->db->query('SELECT migration FROM migrations ORDER BY id ASC');
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Lấy danh sách tất cả các file migration có trong thư mục
     * 
     * @return string[]
     */
    public function getAvailableMigrations(): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }

        $files = scandir($this->migrationsDir);
        if ($files === false) {
            return [];
        }

        $sqlFiles = [];
        foreach ($files as $file) {
            if (str_ends_with($file, '.sql')) {
                $sqlFiles[] = $file;
            }
        }

        sort($sqlFiles);
        return $sqlFiles;
    }

    /**
     * Lấy danh sách các migration đang chờ thực thi
     * 
     * @return string[]
     */
    public function getPendingMigrations(): array
    {
        $applied = $this->getAppliedMigrations();
        $available = $this->getAvailableMigrations();

        return array_values(array_diff($available, $applied));
    }

    /**
     * Thực thi toàn bộ các migration chưa chạy
     * 
     * @return string[] Danh sách tên các file migration đã được áp dụng
     */
    public function runPendingMigrations(): array
    {
        $pending = $this->getPendingMigrations();
        if (empty($pending)) {
            return [];
        }

        // Lấy số batch tiếp theo
        $stmt = $this->db->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations');
        $nextBatch = (int)$stmt->fetchColumn();

        $executed = [];

        foreach ($pending as $migrationFile) {
            $filePath = $this->migrationsDir . '/' . $migrationFile;
            if (!file_exists($filePath)) {
                continue;
            }

            $sql = file_get_contents($filePath);
            if ($sql === false || trim($sql) === '') {
                continue;
            }

            $this->executeMigrationSql($sql);

            // Ghi nhận migration đã hoàn tất
            $insertStmt = $this->db->prepare('INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)');
            $insertStmt->execute([
                ':migration' => $migrationFile,
                ':batch' => $nextBatch,
            ]);

            $executed[] = $migrationFile;
        }

        return $executed;
    }

    /**
     * Thực thi câu lệnh SQL của 1 migration có xử lý an toàn lỗi trùng cột của SQLite
     */
    private function executeMigrationSql(string $sql): void
    {
        // Tách câu lệnh theo dấu chấm phẩy
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn(string $stmt) => $stmt !== ''
        );

        foreach ($statements as $stmt) {
            try {
                $this->db->exec($stmt);
            } catch (PDOException $e) {
                // SQLite: Nếu lỗi duplicate column name khi ALTER TABLE ADD COLUMN, bỏ qua an toàn
                if (str_contains(strtolower($e->getMessage()), 'duplicate column name')) {
                    continue;
                }
                throw new PDOException($e->getMessage() . " | Error on SQL: [" . $stmt . "]", (int)$e->getCode(), $e);
            }
        }
    }
}
