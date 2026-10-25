<?php

declare(strict_types=1);

namespace Tests\Unit;

/**
 * Test Suite cho Issue #22:
 * Trang Giới Thiệu - Nguồn gốc, sự hình thành & tôn chỉ của Bếp thuần chay An Yên
 */
class Issue22_AboutPageTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function runAllTests(): void
    {
        echo "\n=== [TEST] Issue #22: Trang Giới Thiệu Nguồn Gốc & Tôn Chỉ Bếp ===\n";

        $this->testAboutPageFileExistsAndValidSyntax();
        $this->testAboutPageContentAndPrinciples();
        $this->testCrossPageNavigationLinks();
        $this->testCleanUrlRoutingSupport();

        echo "=> Kết quả Issue #22: {$this->passed} PASS, {$this->failed} FAIL\n";

        if ($this->failed > 0) {
            throw new \RuntimeException("Có {$this->failed} test thất bại trong Issue22_AboutPageTest!");
        }
    }

    private function testAboutPageFileExistsAndValidSyntax(): void
    {
        $aboutPath = __DIR__ . '/../../about.php';
        $this->assert(file_exists($aboutPath), "File about.php tồn tại trong thư mục gốc");

        $content = file_get_contents($aboutPath);
        $this->assert($content !== false && strlen($content) > 500, "about.php có nội dung hợp lệ");
    }

    private function testAboutPageContentAndPrinciples(): void
    {
        $content = file_get_contents(__DIR__ . '/../../about.php');

        // 1. Khởi nguồn & Sự hình thành
        $this->assert(str_contains($content, 'Khởi Nguồn & Sự Hình Thành'), "Có mục Khởi Nguồn & Sự Hình Thành");
        $this->assert(str_contains($content, 'nguyên liệu có nguồn gốc thảo mộc'), "Có thông điệp nguyên liệu thảo mộc tự nhiên");

        // 2. Bốn tôn chỉ ẩm thực
        $this->assert(str_contains($content, 'Bốn Tôn Chỉ Ẩm Thực Cốt Lõi'), "Có tiêu đề Bốn Tôn Chỉ Ẩm Thực Cốt Lõi");
        $this->assert(str_contains($content, 'Thuần Khiết Tự Nhiên'), "Tôn chỉ 1: Thuần Khiết Tự Nhiên");
        $this->assert(str_contains($content, 'Nấu Theo Ca & Giới Hạn Suất'), "Tôn chỉ 2: Nấu Theo Ca & Giới Hạn Suất");
        $this->assert(str_contains($content, 'Nuôi Dưỡng Lòng Từ & Chậm Lại'), "Tôn chỉ 3: Nuôi Dưỡng Lòng Từ & Chậm Lại");
        $this->assert(str_contains($content, 'Đón Tiếp Như Người Thân Quen Trở Về'), "Tôn chỉ 4: Đón Tiếp Như Người Thân Quen Trở Về");

        // 3. Lời ngỏ từ người đứng bếp
        $this->assert(str_contains($content, 'Người Đứng Bếp An Yên'), "Có lời ngỏ ký tên Người Đứng Bếp An Yên");

        // 4. Các nút hành động CTA
        $this->assert(str_contains($content, 'index.php'), "Có link dẫn tới trang đặt món (index.php)");
        $this->assert(str_contains($content, 'reviews.php'), "Có link dẫn tới trang cảm nhận (reviews.php)");
    }

    private function testCrossPageNavigationLinks(): void
    {
        $indexContent = file_get_contents(__DIR__ . '/../../index.php');
        $this->assert(str_contains($indexContent, 'about.php'), "index.php có link liên kết sang about.php");

        $reviewsContent = file_get_contents(__DIR__ . '/../../reviews.php');
        $this->assert(str_contains($reviewsContent, 'about.php'), "reviews.php có link liên kết sang about.php");
    }

    private function testCleanUrlRoutingSupport(): void
    {
        $routerContent = file_get_contents(__DIR__ . '/../../router.php');
        $this->assert(str_contains($routerContent, '$phpFile'), "router.php có quy tắc ánh xạ clean URL tự động tới file .php tương ứng");
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            echo "  [PASS] {$message}\n";
            $this->passed++;
        } else {
            echo "  [FAIL] {$message}\n";
            $this->failed++;
        }
    }
}
