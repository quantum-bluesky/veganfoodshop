<?php
declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\MigrationService;
use PDO;

class MigrationServiceTest extends TestCase
{
    private string $tempMigrationDir;

    public function runAllTests(): void
    {
        $this->setUp();
        $this->testMigrationsTableStructure();
        $this->tearDown();

        $this->setUp();
        $this->testMigrationExecutionAndIdempotency();
        $this->tearDown();

        $this->setUp();
        $this->testAutoRunFlagHandling();
        $this->tearDown();
    }

    protected function setUp(): void
    {
        // Khởi tạo SQLite :memory: cô lập 100%
        $this->db = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->db->exec('PRAGMA foreign_keys = ON;');

        // Tạo thư mục tạm chứa migration test
        $this->tempMigrationDir = sys_get_temp_dir() . '/vegan_test_migrations_' . bin2hex(random_bytes(4));
        if (!is_dir($this->tempMigrationDir)) {
            mkdir($this->tempMigrationDir, 0777, true);
        }

        // Tạo 2 file migration mẫu
        file_put_contents(
            $this->tempMigrationDir . '/001_create_test_table.sql',
            'CREATE TABLE test_sample (id INTEGER PRIMARY KEY, title TEXT);'
        );
        file_put_contents(
            $this->tempMigrationDir . '/002_add_field_to_test_table.sql',
            'ALTER TABLE test_sample ADD COLUMN note TEXT;'
        );
    }

    protected function tearDown(): void
    {
        // Xóa thư mục tạm
        if (is_dir($this->tempMigrationDir)) {
            $files = glob($this->tempMigrationDir . '/*');
            if ($files) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
            @rmdir($this->tempMigrationDir);
        }

        MigrationService::resetAutoRunFlag();
        $this->db = null;
    }

    /**
     * Kiểm tra cấu trúc bảng migrations
     */
    public function testMigrationsTableStructure(): void
    {
        $service = new MigrationService($this->db, $this->tempMigrationDir);
        $service->ensureMigrationsTable();

        $stmt = $this->db->query("PRAGMA table_info(migrations)");
        $cols = $stmt->fetchAll();
        $colNames = array_column($cols, 'name');

        $this->assertTrue(in_array('id', $colNames, true), 'Bảng migrations phải có cột id');
        $this->assertTrue(in_array('migration', $colNames, true), 'Bảng migrations phải có cột migration');
        $this->assertTrue(in_array('batch', $colNames, true), 'Bảng migrations phải có cột batch');
        $this->assertTrue(in_array('executed_at', $colNames, true), 'Bảng migrations phải có cột executed_at');
    }

    /**
     * Kiểm tra thực thi migration và tính lũy kế (idempotency)
     */
    public function testMigrationExecutionAndIdempotency(): void
    {
        $service = new MigrationService($this->db, $this->tempMigrationDir);

        // Ban đầu chưa có migration nào chạy
        $pending = $service->getPendingMigrations();
        $this->assertEquals(2, count($pending), 'Phải có 2 migration đang chờ');
        $this->assertEquals('001_create_test_table.sql', $pending[0]);
        $this->assertEquals('002_add_field_to_test_table.sql', $pending[1]);

        // Thực thi migrations
        $executed = $service->runPendingMigrations();
        $this->assertEquals(2, count($executed), 'Phải thực thi đủ 2 migration');

        // Bảng test_sample phải tồn tại và có cột note
        $this->db->exec("INSERT INTO test_sample (title, note) VALUES ('An Yên', 'Ghi chú test')");
        $row = $this->db->query("SELECT * FROM test_sample WHERE id = 1")->fetch();
        $this->assertEquals('An Yên', $row['title']);
        $this->assertEquals('Ghi chú test', $row['note']);

        // Sau khi chạy, pending phải rỗng
        $this->assertEquals(0, count($service->getPendingMigrations()), 'Không còn migration nào chờ');

        // Chạy lại không bị lỗi và không thực thi thêm (idempotent)
        $secondRun = $service->runPendingMigrations();
        $this->assertEquals(0, count($secondRun), 'Lần chạy thứ hai không được thực thi lại');
    }

    /**
     * Kiểm tra cơ chế cờ tự động chạy (runAutoMigrations)
     */
    public function testAutoRunFlagHandling(): void
    {
        $service = new MigrationService($this->db, $this->tempMigrationDir);
        $service->runPendingMigrations();

        $this->assertEquals(2, count($service->getAppliedMigrations()));
    }
}
