<?php
declare(strict_types=1);

/**
 * CLI Migration Runner cho VeganFoodShop (Bếp Chay An Lành)
 * 
 * Cách chạy:
 * php bin/migrate.php           (Thực thi các migration đang chờ)
 * php bin/migrate.php status    (Kiểm tra trạng thái các migration)
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Database\Connection;
use App\Services\MigrationService;

echo "\n======================================================\n";
echo "   VEGAN FOOD SHOP (Bếp Chay An Lành) - MIGRATIONS     \n";
echo "======================================================\n\n";

try {
    $db = Connection::getInstance();
    $service = new MigrationService($db);

    $command = strtolower((string)($argv[1] ?? 'migrate'));

    $available = $service->getAvailableMigrations();
    $applied = $service->getAppliedMigrations();
    $pending = $service->getPendingMigrations();

    if ($command === 'status') {
        echo "TRẠNG THÁI CÁC BẢN MIGRATION:\n";
        echo "------------------------------------------------------\n";
        foreach ($available as $migration) {
            $isApplied = in_array($migration, $applied, true);
            $badge = $isApplied ? "[\033[32m ĐÃ CHẠY \033[0m]" : "[\033[33m ĐANG CHỜ \033[0m]";
            echo sprintf("  %-35s %s\n", $migration, $badge);
        }
        echo "\nTổng số: " . count($available) . " bản | Đã chạy: " . count($applied) . " | Đang chờ: " . count($pending) . "\n";
        echo "======================================================\n\n";
        exit(0);
    }

    if (empty($pending)) {
        echo "✓ Cơ sở dữ liệu đã ở phiên bản mới nhất! Không có migration nào cần chạy.\n";
        echo "  (Tổng cộng " . count($applied) . " bản migration đã được ghi nhận)\n";
        echo "======================================================\n\n";
        exit(0);
    }

    echo "Phát hiện " . count($pending) . " migration đang chờ thực thi:\n";
    foreach ($pending as $migration) {
        echo "  ▶ Đang áp dụng: {$migration} ... ";
        $service->runPendingMigrations();
        echo "[ XONG ] ✓\n";
    }

    echo "\n------------------------------------------------------\n";
    echo "KẾT QUẢ: 100% MIGRATIONS ĐÃ ĐƯỢC ÁP DỤNG THÀNH CÔNG!\n";
    echo "======================================================\n\n";
    exit(0);

} catch (\Throwable $e) {
    echo "\n[ LỖI MIGRATION ]: " . $e->getMessage() . "\n";
    echo "Tại: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "======================================================\n\n";
    exit(1);
}
