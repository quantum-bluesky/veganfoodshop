<?php

declare(strict_types=1);

namespace Tests\Unit;

/**
 * Test Suite cho Issue #31:
 * - Thay ký tự lỗi '🪷' bằng icon tương thích phổ biến '🌸' trong about.php và reviews.php
 * - Kiểm tra các thẻ Preview Graph (Open Graph og: và Twitter Card) hiển thị xem trước khi chia sẻ link
 */
class Issue31_IconAndOpenGraphPreviewTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function runAllTests(): void
    {
        echo "\n=== [TEST] Issue #31: Thay Icon '🪷' & Thẻ Open Graph Preview Link ===\n";

        $this->testNoCorruptedLotusIconRemains();
        $this->testPreviewImageFileExists();
        $this->testOpenGraphAndTwitterCardMetaInIndex();
        $this->testOpenGraphAndTwitterCardMetaInAbout();
        $this->testOpenGraphAndTwitterCardMetaInReviews();
        $this->testOpenGraphAndTwitterCardMetaInProcessOrder();

        echo "=> Kết quả Issue #31: {$this->passed} PASS, {$this->failed} FAIL\n";

        if ($this->failed > 0) {
            throw new \RuntimeException("Có {$this->failed} test thất bại trong Issue31_IconAndOpenGraphPreviewTest!");
        }
    }

    /**
     * Kiểm tra không còn ký tự lỗi 🪷 trong about.php và reviews.php
     */
    private function testNoCorruptedLotusIconRemains(): void
    {
        $aboutContent = file_get_contents(__DIR__ . '/../../about.php');
        $reviewsContent = file_get_contents(__DIR__ . '/../../reviews.php');

        $this->assertFalse(str_contains($aboutContent, '🪷'), 'about.php không còn chứa ký tự 🪷');
        $this->assertTrue(str_contains($aboutContent, '<span>🌸</span> Bốn Tôn Chỉ Ẩm Thực Cốt Lõi'), 'about.php sử dụng icon 🌸 hiển thị chuẩn');

        $this->assertFalse(str_contains($reviewsContent, '🪷'), 'reviews.php không còn chứa ký tự 🪷');
        $this->assertTrue(str_contains($reviewsContent, '>🌸</div>'), 'reviews.php sử dụng icon 🌸 trong header');
    }

    /**
     * Kiểm tra file ảnh preview.jpg tồn tại và hợp lệ
     */
    private function testPreviewImageFileExists(): void
    {
        $assetsPreview = __DIR__ . '/../../assets/images/preview.jpg';
        $rootPreview = __DIR__ . '/../../images/preview.jpg';

        $this->assertTrue(file_exists($assetsPreview), 'File assets/images/preview.jpg tồn tại');
        $this->assertTrue(filesize($assetsPreview) > 10000, 'File assets/images/preview.jpg có dung lượng ảnh hợp lệ (>10KB)');

        $this->assertTrue(file_exists($rootPreview), 'File images/preview.jpg tương thích đường dẫn mẫu tồn tại');
        $this->assertTrue(filesize($rootPreview) > 10000, 'File images/preview.jpg có dung lượng ảnh hợp lệ');
    }

    /**
     * Kiểm tra thẻ Open Graph và Twitter Card trong index.php
     */
    private function testOpenGraphAndTwitterCardMetaInIndex(): void
    {
        $html = file_get_contents(__DIR__ . '/../../index.php');

        $this->assertStringContainsString('property="og:type" content="website"', $html, 'index.php có thẻ og:type');
        $this->assertStringContainsString('property="og:url"', $html, 'index.php có thẻ og:url');
        $this->assertStringContainsString('property="og:title"', $html, 'index.php có thẻ og:title');
        $this->assertStringContainsString('property="og:description"', $html, 'index.php có thẻ og:description');
        $this->assertStringContainsString('property="og:image"', $html, 'index.php có thẻ og:image');
        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $html, 'index.php có twitter:card');
        $this->assertStringContainsString('name="twitter:title"', $html, 'index.php có twitter:title');
        $this->assertStringContainsString('name="twitter:description"', $html, 'index.php có twitter:description');
        $this->assertStringContainsString('name="twitter:image"', $html, 'index.php có twitter:image');
    }

    /**
     * Kiểm tra thẻ Open Graph và Twitter Card trong about.php
     */
    private function testOpenGraphAndTwitterCardMetaInAbout(): void
    {
        $html = file_get_contents(__DIR__ . '/../../about.php');

        $this->assertStringContainsString('property="og:type" content="website"', $html, 'about.php có thẻ og:type');
        $this->assertStringContainsString('property="og:url"', $html, 'about.php có thẻ og:url');
        $this->assertStringContainsString('property="og:title"', $html, 'about.php có thẻ og:title');
        $this->assertStringContainsString('property="og:description"', $html, 'about.php có thẻ og:description');
        $this->assertStringContainsString('property="og:image"', $html, 'about.php có thẻ og:image');
        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $html, 'about.php có twitter:card');
        $this->assertStringContainsString('name="twitter:title"', $html, 'about.php có twitter:title');
        $this->assertStringContainsString('name="twitter:description"', $html, 'about.php có twitter:description');
        $this->assertStringContainsString('name="twitter:image"', $html, 'about.php có twitter:image');
    }

    /**
     * Kiểm tra thẻ Open Graph và Twitter Card trong reviews.php
     */
    private function testOpenGraphAndTwitterCardMetaInReviews(): void
    {
        $html = file_get_contents(__DIR__ . '/../../reviews.php');

        $this->assertStringContainsString('property="og:type" content="website"', $html, 'reviews.php có thẻ og:type');
        $this->assertStringContainsString('property="og:url"', $html, 'reviews.php có thẻ og:url');
        $this->assertStringContainsString('property="og:title"', $html, 'reviews.php có thẻ og:title');
        $this->assertStringContainsString('property="og:description"', $html, 'reviews.php có thẻ og:description');
        $this->assertStringContainsString('property="og:image"', $html, 'reviews.php có thẻ og:image');
        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $html, 'reviews.php có twitter:card');
        $this->assertStringContainsString('name="twitter:title"', $html, 'reviews.php có twitter:title');
        $this->assertStringContainsString('name="twitter:description"', $html, 'reviews.php có twitter:description');
        $this->assertStringContainsString('name="twitter:image"', $html, 'reviews.php có twitter:image');
    }

    /**
     * Kiểm tra thẻ Open Graph và Twitter Card trong process_order.php
     */
    private function testOpenGraphAndTwitterCardMetaInProcessOrder(): void
    {
        $html = file_get_contents(__DIR__ . '/../../process_order.php');

        $this->assertStringContainsString('property="og:type" content="website"', $html, 'process_order.php có thẻ og:type');
        $this->assertStringContainsString('property="og:url"', $html, 'process_order.php có thẻ og:url');
        $this->assertStringContainsString('property="og:title"', $html, 'process_order.php có thẻ og:title');
        $this->assertStringContainsString('property="og:description"', $html, 'process_order.php có thẻ og:description');
        $this->assertStringContainsString('property="og:image"', $html, 'process_order.php có thẻ og:image');
        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $html, 'process_order.php có twitter:card');
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message}\n";
        }
    }

    private function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assert($condition === true, $message);
    }

    private function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assert($condition === false, $message);
    }

    private function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        $this->assert(str_contains($haystack, $needle), $message ?: "Chuỗi không chứa '{$needle}'");
    }
}
