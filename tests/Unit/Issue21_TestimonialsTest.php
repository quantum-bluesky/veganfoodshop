<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use App\Services\ReviewService;
use App\Services\ConfigService;
use App\Exceptions\ValidationException;

/**
 * Test Suite cho Issue #21:
 * Cảm nhận đặc biệt, lời đề tặng, kỷ niệm (Testimonials) với UI trầm mặc sâu lắng
 */
class Issue21_TestimonialsTest
{
    private PDO $db;
    private ReviewService $reviewService;
    private ConfigService $configService;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        $this->setUpDatabase();
        $this->configService = new ConfigService($this->db);
        $this->reviewService = new ReviewService($this->db, $this->configService);
    }

    private function setUpDatabase(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $schemaSql = file_get_contents(__DIR__ . '/../../database/schema.sql');
        $this->db->exec($schemaSql);

        // Mở tính năng đánh giá
        $this->db->exec("INSERT OR REPLACE INTO system_config (config_key, config_value) VALUES ('reviews_enabled', '1');");
    }

    public function runAllTests(): void
    {
        echo "\n=== [TEST] Issue #21: Cảm Nhận Đặc Biệt & Lời Đề Tặng (Testimonials) ===\n";

        $this->testCreateTestimonialDirectly();
        $this->testToggleFeaturedAndNote();
        $this->testGetFeaturedTestimonialsOrdering();
        $this->testReviewsPhpContainsZenTestimonialsUI();

        echo "=> Kết quả Issue #21: {$this->passed} PASS, {$this->failed} FAIL\n";

        if ($this->failed > 0) {
            throw new \RuntimeException("Có {$this->failed} test thất bại trong Issue21_TestimonialsTest!");
        }
    }

    private function testCreateTestimonialDirectly(): void
    {
        $testData = [
            'customer_name' => 'Cư sĩ Minh Tâm',
            'title' => 'An yên giữa phố thị',
            'comment' => 'Bát phở nấm thanh ngọt xoa dịu tâm hồn sau một ngày làm việc mệt mỏi.',
            'testimonial_note' => 'Lưu bút bàn trà số 1 • Mùa Vu Lan',
            'kitchen_reply' => 'Biết ơn bạn đã ghé bếp an trú!',
            'rating' => 5,
        ];

        $newId = $this->reviewService->createTestimonial($testData);
        $this->assert($newId > 0, "Tạo lời đề tặng/kỷ niệm trực tiếp thành công (ID > 0)");

        $testimonials = $this->reviewService->getFeaturedTestimonials(10);
        $found = null;
        foreach ($testimonials as $t) {
            if ((int)$t['id'] === $newId) {
                $found = $t;
                break;
            }
        }

        $this->assert($found !== null, "Tìm thấy lời đề tặng vừa tạo trong danh sách Featured Testimonials");
        $this->assert($found['customer_name'] === 'Cư sĩ Minh Tâm', "Đúng tên người gửi đề tặng");
        $this->assert($found['testimonial_note'] === 'Lưu bút bàn trà số 1 • Mùa Vu Lan', "Đúng lời đề tựa/bối cảnh kỷ niệm");
        $this->assert((int)$found['is_featured'] === 1, "Mặc định được gắn cờ is_featured = 1");
        $this->assert($found['status'] === 'approved', "Mặc định trạng thái approved");
    }

    private function testToggleFeaturedAndNote(): void
    {
        // 1. Khách gửi đánh giá thông thường
        $revRes = $this->reviewService->createReview([
            'customer_name' => 'Bạn An',
            'rating' => 5,
            'title' => 'Món ngon tuyệt vời',
            'comment' => 'Canh hạt sen rất thơm ngon, thanh đạm.',
        ]);
        $revId = (int)$revRes['id'];

        // Duyệt hiển thị
        $this->reviewService->updateStatus($revId, 'approved');

        // Lúc này chưa featured
        $stmt = $this->db->prepare('SELECT is_featured, testimonial_note FROM customer_reviews WHERE id = :id');
        $stmt->execute([':id' => $revId]);
        $r = $stmt->fetch();
        $this->assert((int)$r['is_featured'] === 0, "Đánh giá thông thường ban đầu is_featured = 0");

        // Bếp bật cờ ghim đề tặng nổi bật (toggleFeatured)
        $this->reviewService->toggleFeatured($revId);
        $stmt->execute([':id' => $revId]);
        $r = $stmt->fetch();
        $this->assert((int)$r['is_featured'] === 1, "Sau khi toggleFeatured, is_featured = 1");

        // Cập nhật lời đề tựa
        $this->reviewService->updateTestimonialNote($revId, 'Lời cảm ơn từ phương xa', 'testimonial');
        $stmt->execute([':id' => $revId]);
        $r = $stmt->fetch();
        $this->assert($r['testimonial_note'] === 'Lời cảm ơn từ phương xa', "Cập nhật đúng testimonial_note");

        // Tắt ghim
        $this->reviewService->toggleFeatured($revId, 0);
        $stmt->execute([':id' => $revId]);
        $r = $stmt->fetch();
        $this->assert((int)$r['is_featured'] === 0, "Tắt ghim thành công (is_featured = 0)");
    }

    private function testGetFeaturedTestimonialsOrdering(): void
    {
        // Tạo thêm 2 testimonial
        $id1 = $this->reviewService->createTestimonial([
            'customer_name' => 'Khách A',
            'comment' => 'Kỷ niệm đẹp tại quán...',
        ]);

        $id2 = $this->reviewService->createTestimonial([
            'customer_name' => 'Khách B',
            'comment' => 'Lời cảm ơn sâu sắc...',
        ]);

        $list = $this->reviewService->getFeaturedTestimonials(10);
        $this->assert(count($list) >= 2, "Lấy danh sách testimonials trả về >= 2 bản ghi");
        // Kiểm tra thứ tự mới nhất xếp trước
        $this->assert((int)$list[0]['id'] === $id2, "Bản ghi tạo sau (Khách B) xuất hiện đầu tiên");
    }

    private function testReviewsPhpContainsZenTestimonialsUI(): void
    {
        $content = file_get_contents(__DIR__ . '/../../reviews.php');

        $this->assert(str_contains($content, 'testimonial-card-zen'), "reviews.php chứa class testimonial-card-zen");
        $this->assert(str_contains($content, 'testimonial-quote'), "reviews.php chứa class testimonial-quote");
        $this->assert(str_contains($content, 'Lời Đề Tặng & Kỷ Niệm'), "reviews.php có tiêu đề Lời Đề Tặng & Kỷ Niệm");
        $this->assert(str_contains($content, 'about.php'), "reviews.php có link liên kết sang trang Về Bếp An Yên (about.php)");
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
