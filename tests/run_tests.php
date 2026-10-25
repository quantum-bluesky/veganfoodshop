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
use Tests\Unit\Issue17_MenuSuitesTest;
use Tests\Unit\Issue18_CustomerReviewsTest;
use Tests\Unit\Issue19_LunarCalendarAndLayoutTest;
use Tests\Unit\Issue20_PromotionsTest;
use Tests\Unit\Issue21_TestimonialsTest;
use Tests\Unit\Issue22_AboutPageTest;
use Tests\Unit\Issue23_MobilePaymentQrTest;
use Tests\Unit\Issue24_CustomerFlowAcceptanceTest;
use Tests\Unit\Issue26_27_PromotionsAndZaloProfileTest;
use Tests\Unit\Issue29_30_SlotOverdueAndFontSizeTest;
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
    'Unit Tests - Issue 17 Bộ Thực Đơn & Lịch Món' => new Issue17_MenuSuitesTest(),
    'Unit Tests - Issue 18 Trang Đánh Giá Khách' => new Issue18_CustomerReviewsTest(),
    'Unit Tests - Issue 19 Lịch Âm & Bố Cục Giao Diện' => new Issue19_LunarCalendarAndLayoutTest(),
    'Unit Tests - Issue 20 Khuyến Mại & Giảm Giá' => new Issue20_PromotionsTest(),
    'Unit Tests - Issue 21 Lời Đề Tặng & Kỷ Niệm Testimonial' => new Issue21_TestimonialsTest(),
    'Unit Tests - Issue 22 Trang Giới Thiệu & Tôn Chỉ Bếp' => new Issue22_AboutPageTest(),
    'Unit Tests - Issue 23 Thanh Toán Mobile QR Deeplink & Tải Ảnh' => new Issue23_MobilePaymentQrTest(),
    'Acceptance Tests - Issue 24 & 25 Luồng Nghiệp Vụ Khách Hàng' => new Issue24_CustomerFlowAcceptanceTest(),
    'Unit Tests - Issue 26 & 27 Khuyến Mại Đa Chiều & Zalo Profile' => new Issue26_27_PromotionsAndZaloProfileTest(),
    'Unit Tests - Issue 29 & 30 Quá Giờ Đặt 3p & Cỡ Chữ A+/A-' => new Issue29_30_SlotOverdueAndFontSizeTest(),
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
