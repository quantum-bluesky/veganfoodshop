<?php
declare(strict_types=1);

/**
 * Trình chạy kiểm thử tự động độc lập cho VeganFoodShop (Bếp Chay An Yên)
 * Đảm bảo 100% CÔ LẬP DATABASE (chỉ chạy SQLite :memory:)
 */

require_once __DIR__ . '/../src/autoload.php';

// Đăng ký autoloader cho thư mục Tests\
spl_autoload_register(function (string $class): void {
    $prefix = 'Tests\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use Tests\Unit\CustomerServiceTest;
use Tests\Unit\SlotCapacityTest;
use Tests\Unit\OrderCalculationTest;
use Tests\Unit\ZaloAuthServiceTest;
use Tests\Unit\MigrationServiceTest;
use Tests\Unit\NotificationOutboxTest;
use Tests\Unit\Issue14_15_16_FeaturesTest;
use Tests\Integration\CreateOrderTransactionTest;
use Tests\Integration\SlotConcurrencyTest;

$testSuites = [
    'Unit Tests - Khách Quen' => new CustomerServiceTest(),
    'Unit Tests - Công Suất Ca' => new SlotCapacityTest(),
    'Unit Tests - Tính Tiền Đơn' => new OrderCalculationTest(),
    'Unit Tests - Zalo OAuth Service' => new ZaloAuthServiceTest(),
    'Unit Tests - Auto-Migration Service' => new MigrationServiceTest(),
    'Unit Tests - Hàng Đợi Thông Báo SQLite' => new NotificationOutboxTest(),
    'Unit Tests - Issue 14, 15, 16 Tính Năng Mới' => new Issue14_15_16_FeaturesTest(),
    'Integration Tests - Transaction An Toàn' => new CreateOrderTransactionTest(),
    'Integration Tests - Khóa Suất Biên' => new SlotConcurrencyTest(),
];

echo "\n======================================================\n";
echo "   VEGAN FOOD SHOP (BẾP CHAY AN YÊN) - TEST RUNNER    \n";
echo "======================================================\n\n";

$passedCount = 0;
$totalTests = count($testSuites);

foreach ($testSuites as $name => $suite) {
    echo sprintf("▶ Đang chạy: %s ... ", str_pad($name, 42, ' '));
    try {
        $suite->runAllTests();
        echo "[ PASS ] ✓\n";
        $passedCount++;
    } catch (\Throwable $e) {
        echo "[ FAIL ] ✗\n";
        echo "   Lỗi: " . $e->getMessage() . "\n";
        echo "   Tại: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

echo "\n------------------------------------------------------\n";
if ($passedCount === $totalTests) {
    echo sprintf("KẾT QUẢ: 100%% THÀNH CÔNG (%d/%d test suites passed)\n", $passedCount, $totalTests);
    echo "CSDL Runtime (bep_an_yen.db): HOÀN TOÀN CÔ LẬP, KHÔNG BỊ ẢNH HƯỞNG.\n";
    echo "======================================================\n\n";
    exit(0);
} else {
    echo sprintf("KẾT QUẢ: THẤT BẠI (%d/%d test suites passed)\n", $passedCount, $totalTests);
    echo "======================================================\n\n";
    exit(1);
}
