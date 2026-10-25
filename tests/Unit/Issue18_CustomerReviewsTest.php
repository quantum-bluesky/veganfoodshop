<?php
declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ReviewService;
use App\Services\ConfigService;
use App\Services\SlotService;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Services\NotificationService;
use App\Services\ZaloAuthService;
use App\Exceptions\ValidationException;

class Issue18_CustomerReviewsTest extends TestCase
{
    private ReviewService $reviewService;
    private ConfigService $configService;
    private OrderService $orderService;
    private SlotService $slotService;

    public function runAllTests(): void
    {
        $this->setUp();

        $this->configService = new ConfigService($this->db);
        $customerService = new CustomerService($this->db);
        $notificationService = new NotificationService($this->db);
        $this->slotService = new SlotService($this->db, $this->configService);
        $this->reviewService = new ReviewService($this->db, $this->configService);

        $zaloService = new ZaloAuthService($this->db, $this->configService, $customerService, [
            'app_id' => '123',
            'secret_key' => '123',
            'mock_test_mode' => true,
            'require_zalo_login' => false,
        ]);

        $this->orderService = new OrderService(
            $this->db,
            $customerService,
            $this->slotService,
            $this->configService,
            $notificationService,
            $zaloService
        );

        $this->testFeatureFlagDisabledByDefault();
        $this->testReviewValidation();
        $this->testReviewCreationAndModerationFlow();
        $this->testVerifiedBuyerOrderLinkingAndDuplicatePrevention();
        $this->testKitchenReplyAndRatingSummary();

        $this->tearDown();
    }

    private function testFeatureFlagDisabledByDefault(): void
    {
        // 1. Mặc định tính năng phải TẮT (chưa bật lên)
        $this->assert($this->reviewService->isReviewsEnabled() === false, 'Tính năng đánh giá mặc định phải TẮT');

        // 2. Thử gửi đánh giá khi đang tắt -> Phải throw ValidationException
        $threw = false;
        try {
            $this->reviewService->createReview([
                'customer_name' => 'Bạn An',
                'rating' => 5,
                'comment' => 'Món bún chay rất thanh tịnh và ngon miệng!',
            ]);
        } catch (ValidationException $e) {
            $threw = true;
            $this->assert(str_contains($e->getMessage(), 'tạm đóng'), 'Thông báo phải nêu rõ tính năng đang tạm đóng');
        }
        $this->assert($threw, 'Phải chặn gửi đánh giá khi tính năng chưa bật');

        // 3. Bật tính năng lên
        $this->reviewService->setReviewsEnabled(true);
        $this->assert($this->reviewService->isReviewsEnabled() === true, 'Bật tính năng đánh giá thành công');
    }

    private function testReviewValidation(): void
    {
        // Đảm bảo tính năng đang bật
        $this->reviewService->setReviewsEnabled(true);

        // Tên trống
        $threw = false;
        try {
            $this->reviewService->createReview([
                'customer_name' => '',
                'rating' => 5,
                'comment' => 'Món ngon lắm',
            ]);
        } catch (ValidationException $e) {
            $threw = true;
        }
        $this->assert($threw, 'Phải bắt lỗi tên khách để trống');

        // Sao không hợp lệ
        $threw = false;
        try {
            $this->reviewService->createReview([
                'customer_name' => 'Bạn An',
                'rating' => 6,
                'comment' => 'Món ngon lắm',
            ]);
        } catch (ValidationException $e) {
            $threw = true;
        }
        $this->assert($threw, 'Phải bắt lỗi số sao > 5');

        // Bình luận quá ngắn
        $threw = false;
        try {
            $this->reviewService->createReview([
                'customer_name' => 'Bạn An',
                'rating' => 5,
                'comment' => 'Ngon',
            ]);
        } catch (ValidationException $e) {
            $threw = true;
        }
        $this->assert($threw, 'Phải bắt lỗi bình luận quá ngắn');
    }

    private function testReviewCreationAndModerationFlow(): void
    {
        $this->reviewService->setReviewsEnabled(true);

        // 1. Gửi đánh giá hợp lệ
        $res = $this->reviewService->createReview([
            'customer_name' => 'Chị Diệu Hương',
            'customer_phone' => '0912345678',
            'rating' => 5,
            'title' => 'Nước dùng ngọt thanh mộc',
            'comment' => 'Lần đầu ghé bếp chay, cảm giác rất an lành và nhẹ nhõm. Nước lẩu nấm nấu bằng mía lê rất thanh!',
        ]);

        $this->assert(!empty($res['id']), 'Tạo đánh giá thành công, có ID');
        $this->assert($res['status'] === 'pending', 'Mặc định đánh giá ở trạng thái chờ duyệt (pending)');

        // 2. Kiểm tra danh sách hiển thị công khai -> Chưa được hiện vì chưa duyệt
        $publicReviews = $this->reviewService->getApprovedReviews();
        $this->assert(empty($publicReviews), 'Đánh giá pending không được xuất hiện ở danh sách công khai');

        // 3. Admin xem danh sách chờ duyệt
        $adminReviews = $this->reviewService->getAllReviewsForAdmin('pending');
        $this->assert(count($adminReviews) === 1, 'Admin phải thấy đúng 1 đánh giá pending');
        $this->assert($adminReviews[0]['customer_name'] === 'Chị Diệu Hương', 'Tên khách phải khớp');

        // 4. Admin duyệt đánh giá (approved)
        $this->reviewService->updateStatus((int)$res['id'], 'approved');

        // 5. Kiểm tra danh sách công khai sau khi duyệt -> Phải xuất hiện
        $publicReviewsAfter = $this->reviewService->getApprovedReviews();
        $this->assert(count($publicReviewsAfter) === 1, 'Sau khi duyệt, đánh giá phải hiển thị công khai');
        $this->assert($publicReviewsAfter[0]['title'] === 'Nước dùng ngọt thanh mộc', 'Tiêu đề đánh giá phải khớp');
    }

    private function testVerifiedBuyerOrderLinkingAndDuplicatePrevention(): void
    {
        $this->reviewService->setReviewsEnabled(true);
        $slotDate = date('Y-m-d', strtotime('+2 days'));
        $slotId = $this->slotService->createSlot($slotDate, '11:30 - 12:15', 20);

        // Tạo đơn hàng mẫu
        $order = $this->orderService->createOrder([
            'customer_name' => 'Chị Lan',
            'customer_phone' => '0912345678',
            'slot_id' => $slotId,
            'order_type' => 'dine_in',
            'payment_method' => 'pay_later',
            'items' => [
                1 => 2,
            ],
        ]);
        $orderCode = $order['order_code'];

        // Gửi đánh giá kèm mã đơn hàng hợp lệ
        $res = $this->reviewService->createReview([
            'customer_name' => 'Chị Lan',
            'order_code' => $orderCode,
            'rating' => 5,
            'comment' => 'Bún riêu chay và chả nấm rất đậm đà, cả nhà ai cũng khen!',
        ]);

        $this->assert($res['is_verified_buyer'] === 1, 'Đánh giá có mã đơn hợp lệ phải được gắn cờ is_verified_buyer = 1');

        // Thử gửi lại đánh giá lần thứ 2 cho cùng mã đơn hàng này -> Phải chặn
        $threwDup = false;
        try {
            $this->reviewService->createReview([
                'customer_name' => 'Chị Lan',
                'order_code' => $orderCode,
                'rating' => 4,
                'comment' => 'Đánh giá trùng lặp lần 2',
            ]);
        } catch (ValidationException $e) {
            $threwDup = true;
            $this->assert(str_contains($e->getMessage(), 'trước đó'), 'Thông báo phải nêu rõ đơn hàng đã được đánh giá');
        }
        $this->assert($threwDup, 'Phải chặn đánh giá trùng lặp cho cùng một đơn hàng');
    }

    private function testKitchenReplyAndRatingSummary(): void
    {
        $this->reviewService->setReviewsEnabled(true);

        // Duyệt tất cả đánh giá hiện có thành approved để tính điểm
        $adminAll = $this->reviewService->getAllReviewsForAdmin();
        foreach ($adminAll as $rev) {
            $this->reviewService->updateStatus((int)$rev['id'], 'approved');
        }

        // Bếp hồi đáp đánh giá đầu tiên
        $firstRevId = (int)$adminAll[0]['id'];
        $replySuccess = $this->reviewService->replyToReview($firstRevId, 'Bếp An Lành xin chân thành cảm ơn bạn đã ghé thăm và gửi lời động viên ấm lòng! 🍃');
        $this->assert($replySuccess === true, 'Bếp phản hồi thành công');

        $approvedList = $this->reviewService->getApprovedReviews();
        $repliedRev = null;
        foreach ($approvedList as $r) {
            if ((int)$r['id'] === $firstRevId) {
                $repliedRev = $r;
                break;
            }
        }
        $this->assert($repliedRev !== null, 'Tìm thấy đánh giá đã duyệt');
        $this->assert(str_contains($repliedRev['kitchen_reply'], 'ấm lòng'), 'Phản hồi của bếp phải hiển thị đúng');

        // Kiểm tra thống kê Rating Summary
        $summary = $this->reviewService->getReviewSummary();
        $this->assert($summary['total_count'] >= 2, 'Tổng số đánh giá đã duyệt phải >= 2');
        $this->assert($summary['average_rating'] >= 4.0, 'Điểm số trung bình phải >= 4.0');
        $this->assert(isset($summary['breakdown'][5]), 'Phải có số lượng đánh giá 5 sao');
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException("Kiểm thử thất bại: {$message}");
        }
    }
}
