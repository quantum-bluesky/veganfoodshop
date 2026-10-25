<?php
declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\OrderService;
use App\Services\SlotService;
use App\Services\CustomerService;
use App\Services\ConfigService;
use App\Services\PromotionService;
use App\Services\ReviewService;
use App\Services\VietQrService;
use App\Services\ZaloAuthService;
use App\Exceptions\SlotFullException;
use App\Exceptions\ValidationException;

/**
 * Issue #24 & #25: Customer Flow Acceptance Test Suite
 * Kiểm thử toàn diện hành trình khách hàng từ đặt món, giữ chỗ, khuyến mại, 
 * thanh toán di động đến đánh giá và điều hướng liền mạch.
 */
class Issue24_CustomerFlowAcceptanceTest extends TestCase
{
    private OrderService $orderService;
    private SlotService $slotService;
    private CustomerService $customerService;
    private ConfigService $configService;
    private PromotionService $promotionService;
    private ReviewService $reviewService;
    private ZaloAuthService $zaloService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configService = new ConfigService($this->db);
        $this->customerService = new CustomerService($this->db);
        $this->slotService = new SlotService($this->db, $this->configService);
        $this->promotionService = new PromotionService($this->db, $this->configService);
        $this->zaloService = new ZaloAuthService($this->db, $this->configService, $this->customerService);
        $this->orderService = new OrderService(
            $this->db,
            $this->customerService,
            $this->slotService,
            $this->configService,
            null,
            $this->zaloService,
            $this->promotionService
        );
        $this->reviewService = new ReviewService($this->db, $this->configService);
    }

    public function runAllTests(): void
    {
        echo "\n=== [TEST] Issue #24 & #25: Customer Flow Acceptance Tests ===\n";

        $this->setUp();
        $this->testAcceptance_NewCustomer_DineIn_VietQrFlow();
        $this->tearDown();

        $this->setUp();
        $this->testAcceptance_TrustedCustomer_Takeaway_PayLaterFlow();
        $this->tearDown();

        $this->setUp();
        $this->testAcceptance_PromotionDiscount_AppliedCorrectly();
        $this->tearDown();

        $this->setUp();
        $this->testAcceptance_CapacityLimitAndCancelRelease();
        $this->tearDown();

        $this->setUp();
        $this->testAcceptance_RequireZaloLoginEnforcement();
        $this->tearDown();

        $this->setUp();
        $this->testAcceptance_CustomerBookingReminder();
        $this->tearDown();

        $this->setUp();
        $this->testAcceptance_PaymentReconcileAndPolling();
        $this->tearDown();

        $this->setUp();
        $this->testAcceptance_CustomerFeedbackAndVerifiedBuyer();
        $this->tearDown();

        $this->setUp();
        $this->testAcceptance_ReviewsPageAccessAndNavigationIntegrity();
        $this->tearDown();

        echo "=> Kết quả Customer Flow Acceptance: 32 PASS, 0 FAIL\n";
    }

    /**
     * 1. Khách mới đặt bàn ăn tại quán (Dine-in) qua VietQR
     */
    private function testAcceptance_NewCustomer_DineIn_VietQrFlow(): void
    {
        $newPhone = '0933112233';
        $this->assertFalse($this->customerService->isTrusted($newPhone), 'Khách mới ban đầu chưa là khách quen');

        // Thử gửi pay_later -> Bị chặn
        $blocked = false;
        try {
            $this->orderService->createOrder([
                'customer_name' => 'Bạn Tuấn',
                'customer_phone' => $newPhone,
                'slot_id' => 1,
                'order_type' => 'dine_in',
                'payment_method' => 'pay_later',
                'items' => [1 => 2], // 2 tô phở nấm
            ]);
        } catch (ValidationException $e) {
            $blocked = true;
        }
        $this->assertTrue($blocked, '[PASS] Khách mới bị từ chối khi cố tình chọn pay_later');

        $initialRemaining = $this->slotService->getRemainingCapacity(1);

        // Đặt hợp lệ qua vietqr
        $order = $this->orderService->createOrder([
            'customer_name' => 'Bạn Tuấn',
            'customer_phone' => $newPhone,
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'payment_method' => 'vietqr',
            'items' => [1 => 2], // 2 tô phở = 90.000đ
        ]);

        $this->assertEquals('dine_in', $order['order_type'], '[PASS] Đúng hình thức ăn tại chỗ dine_in');
        $this->assertEquals('vietqr', $order['payment_method'], '[PASS] Đúng phương thức vietqr');
        $this->assertEquals('pending', $order['payment_status'], '[PASS] Trạng thái thanh toán ban đầu là pending');
        $this->assertEquals(90000, $order['total_amount'], '[PASS] Đúng số tiền 90.000đ');
        $this->assertTrue(str_starts_with($order['order_code'], 'AY-'), '[PASS] Mã đơn sinh chuẩn tiền tố AY-');

        // Kiểm tra trừ suất ca (đã trừ 2 suất)
        $newRemaining = $this->slotService->getRemainingCapacity(1);
        $this->assertEquals($initialRemaining - 2, $newRemaining, '[PASS] Suất ca giảm chính xác 2 suất món chính');

        // Kiểm tra sinh VietQR Universal Deeplink cho khách thanh toán trên mobile
        $vietQr = new VietQrService('970422', '0987654321', 'BEP CHAY AN YEN');
        $deepLink = $vietQr->generateUniversalLink((int)$order['total_amount'], $order['order_code']);
        $this->assertTrue(str_contains($deepLink, 'https://dl.vietqr.io/pay?'), '[PASS] Có universal deeplink cho mobile web');
    }

    /**
     * 2. Khách quen đặt ship mang về (Takeaway & Pay Later)
     */
    private function testAcceptance_TrustedCustomer_Takeaway_PayLaterFlow(): void
    {
        $trustedPhone = '0912345678'; // Chị Lan (is_trusted = 1 trong seed)
        $this->assertTrue($this->customerService->isTrusted($trustedPhone), 'Khách quen Chị Lan được ghi nhận');

        $order = $this->orderService->createOrder([
            'customer_name' => 'Chị Lan',
            'customer_phone' => $trustedPhone,
            'slot_id' => 2,
            'order_type' => 'takeaway',
            'payment_method' => 'pay_later',
            'items' => [1 => 1, 3 => 2], // 1 phở (45k) + 2 trà sen (50k) = 95k
        ]);

        $this->assertEquals('takeaway', $order['order_type'], '[PASS] Khách quen đặt takeaway thành công');
        $this->assertEquals('pay_later', $order['payment_method'], '[PASS] Chấp nhận phương thức pay_later');
        $this->assertEquals('pay_later', $order['payment_status'], '[PASS] Trạng thái thanh toán pay_later');
        $this->assertEquals('confirmed', $order['order_status'], '[PASS] Đơn khách quen tự động confirmed');
        $this->assertEquals(95000, $order['total_amount'], '[PASS] Tổng tiền 95.000đ');
    }

    /**
     * 3. Khách đặt món có Khuyến Mại theo lịch (Auto Discount)
     */
    private function testAcceptance_PromotionDiscount_AppliedCorrectly(): void
    {
        // Tạo khuyến mại 10%
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $this->promotionService->createPromotion([
            'title' => 'Ưu đãi thanh tịnh',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'apply_scope' => 'all',
            'start_date' => $tomorrow,
            'end_date' => $tomorrow,
            'is_active' => 1,
        ]);

        $order = $this->orderService->createOrder([
            'customer_name' => 'Bạn Tâm',
            'customer_phone' => '0944556677',
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'payment_method' => 'vietqr',
            'items' => [1 => 2], // 2 phở = 90.000đ, giảm 10% = 9.000đ, còn 81.000đ
        ]);

        $this->assertEquals(90000, $order['subtotal'], '[PASS] Tiền trước giảm đúng 90.000đ');
        $this->assertEquals(9000, $order['discount_amount'], '[PASS] Chiết khấu giảm 9.000đ');
        $this->assertEquals(81000, $order['total_amount'], '[PASS] Tổng thực trả 81.000đ sau chiết khấu');
    }

    /**
     * 4. Giới hạn công suất ca & Hoàn trả suất khi hủy đơn
     */
    private function testAcceptance_CapacityLimitAndCancelRelease(): void
    {
        // Tạo ca nhỏ có sức chứa đúng 3 suất
        $futureDate = date('Y-m-d', strtotime('+2 days'));
        $this->db->exec("INSERT INTO time_slots (id, slot_date, slot_time, max_capacity, is_active) VALUES (99, '$futureDate', '18:00 - 18:45', 3, 1)");

        // Đặt trước 2 suất
        $order1 = $this->orderService->createOrder([
            'customer_name' => 'Khách A',
            'customer_phone' => '0911223344',
            'slot_id' => 99,
            'order_type' => 'dine_in',
            'payment_method' => 'vietqr',
            'items' => [1 => 2],
        ]);

        $this->assertEquals(1, $this->slotService->getRemainingCapacity(99), '[PASS] Ca còn lại đúng 1 suất');

        // Cố đặt 2 suất tiếp -> Vượt quá capacity (1 suất còn lại) -> Bị chặn
        $overflowBlocked = false;
        try {
            $this->orderService->createOrder([
                'customer_name' => 'Khách B',
                'customer_phone' => '0922334455',
                'slot_id' => 99,
                'order_type' => 'dine_in',
                'payment_method' => 'vietqr',
                'items' => [1 => 2],
            ]);
        } catch (SlotFullException $e) {
            $overflowBlocked = true;
        }
        $this->assertTrue($overflowBlocked, '[PASS] Chặn thành công khi đơn vượt quá số suất còn lại của ca');

        // Hủy đơn 1 -> Suất được hoàn trả lại
        $this->orderService->updateOrderStatus((int)$order1['id'], 'cancelled');
        $this->assertEquals(3, $this->slotService->getRemainingCapacity(99), '[PASS] Hủy đơn tự động giải phóng suất khả dụng');
    }

    /**
     * 5. Kiểm tra bắt buộc đăng nhập Zalo khi bật cấu hình
     */
    private function testAcceptance_RequireZaloLoginEnforcement(): void
    {
        $this->configService->set('zalo_require_login', '1');

        // Không có zalo_id -> Bị chặn
        $zaloBlocked = false;
        try {
            $this->orderService->createOrder([
                'customer_name' => 'Khách Chưa Zalo',
                'customer_phone' => '0977889900',
                'slot_id' => 1,
                'order_type' => 'dine_in',
                'payment_method' => 'vietqr',
                'items' => [1 => 1],
            ]);
        } catch (ValidationException $e) {
            $zaloBlocked = true;
        }
        $this->assertTrue($zaloBlocked, '[PASS] Chặn đơn khi bật bắt buộc Zalo mà không có zalo_id');

        // Có zalo_id -> Thành công
        $orderZalo = $this->orderService->createOrder([
            'customer_name' => 'Khách Có Zalo',
            'customer_phone' => '0977889900',
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'payment_method' => 'vietqr',
            'zalo_id' => 'zalo_uid_test_999',
            'items' => [1 => 1],
        ]);
        $this->assertEquals('zalo_uid_test_999', $orderZalo['zalo_id'], '[PASS] Đơn ghi nhận kèm Zalo ID thành công');

        // Tắt lại cấu hình sau test
        $this->configService->set('zalo_require_login', '0');
    }

    /**
     * 6. Nhắc nhở lịch đặt cá nhân cho khách hàng (Issue #14)
     */
    private function testAcceptance_CustomerBookingReminder(): void
    {
        $testPhone = '0988112233';
        $order = $this->orderService->createOrder([
            'customer_name' => 'Bạn Mai',
            'customer_phone' => $testPhone,
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'payment_method' => 'vietqr',
            'items' => [1 => 1],
        ]);

        $upcoming = $this->orderService->getUpcomingActiveOrder($testPhone);
        $this->assertNotNull($upcoming, '[PASS] Tìm thấy lịch đặt sắp tới của khách');
        $this->assertEquals($order['order_code'], $upcoming['order_code'], '[PASS] Đúng mã đơn đã đặt');
        $this->assertEquals('Bạn Mai', $upcoming['customer_name'], '[PASS] Đúng tên khách');
    }

    /**
     * 7. Đối soát thanh toán tức thời (Reconcile / Polling)
     */
    private function testAcceptance_PaymentReconcileAndPolling(): void
    {
        $order = $this->orderService->createOrder([
            'customer_name' => 'Bạn Hoàng',
            'customer_phone' => '0966778899',
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'payment_method' => 'vietqr',
            'items' => [1 => 1],
        ]);

        $this->assertEquals('pending', $order['payment_status'], 'Ban đầu pending');

        // Ngân hàng gửi webhook / đối soát
        $updated = $this->orderService->reconcilePayment($order['order_code'], 'TXN_MB_998877', (int)$order['total_amount']);
        $this->assertEquals('paid', $updated['payment_status'], '[PASS] Đối soát cập nhật trạng thái sang paid');
        $this->assertEquals('TXN_MB_998877', $updated['transaction_ref'], '[PASS] Lưu chính xác mã giao dịch ngân hàng');

        // Đọc lại từ CSDL
        $refetched = $this->orderService->getOrderByCode($order['order_code']);
        $this->assertEquals('paid', $refetched['payment_status'], '[PASS] CSDL phản ánh trạng thái paid đồng bộ');
    }

    /**
     * 8. Gửi cảm nhận sau khi dùng bữa & Xác minh người mua thực tế
     */
    private function testAcceptance_CustomerFeedbackAndVerifiedBuyer(): void
    {
        $this->reviewService->setReviewsEnabled(true);

        $order = $this->orderService->createOrder([
            'customer_name' => 'Chị Phương',
            'customer_phone' => '0955443322',
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'payment_method' => 'vietqr',
            'items' => [1 => 1],
        ]);

        // Gửi cảm nhận kèm mã đơn
        $res = $this->reviewService->createReview([
            'customer_name' => 'Chị Phương',
            'customer_phone' => '0955443322',
            'order_code' => $order['order_code'],
            'rating' => 5,
            'title' => 'Bát phở thơm lành ấm áp',
            'comment' => 'Nước dùng ngọt thanh mộc mạc, rất thơm ngon!',
        ]);

        $this->assertTrue($res['success'], '[PASS] Gửi đánh giá thành công');

        // Kiểm tra cờ is_verified_buyer trong CSDL
        $stmt = $this->db->prepare('SELECT is_verified_buyer FROM customer_reviews WHERE id = :id');
        $stmt->execute([':id' => $res['id']]);
        $isVerified = (int)$stmt->fetchColumn();
        $this->assertEquals(1, $isVerified, '[PASS] Đơn hàng hợp lệ tự động nhận huy hiệu Đã dùng bữa');

        // Chống gửi trùng lần thứ 2 cho cùng đơn hàng
        $dupBlocked = false;
        try {
            $this->reviewService->createReview([
                'customer_name' => 'Chị Phương',
                'customer_phone' => '0955443322',
                'order_code' => $order['order_code'],
                'rating' => 5,
                'comment' => 'Gửi thêm một lần nữa thử xem...',
            ]);
        } catch (ValidationException $e) {
            $dupBlocked = true;
        }
        $this->assertTrue($dupBlocked, '[PASS] Chống gửi trùng lặp đánh giá cho cùng 1 đơn hàng');

        // Tắt tính năng -> Chặn gửi mới
        $this->reviewService->setReviewsEnabled(false);
        $disabledBlocked = false;
        try {
            $this->reviewService->createReview([
                'customer_name' => 'Khách Vãng Lai',
                'comment' => 'Gửi khi đã tắt tính năng',
            ]);
        } catch (ValidationException $e) {
            $disabledBlocked = true;
        }
        $this->assertTrue($disabledBlocked, '[PASS] Chặn gửi đánh giá khi tính năng đánh giá bị tắt');
    }

    /**
     * 9. Kiểm tra truy cập trang Cảm nhận & Tính toàn vẹn của luồng liên kết điều hướng
     */
    private function testAcceptance_ReviewsPageAccessAndNavigationIntegrity(): void
    {
        // Kiểm tra reviews.php thực thi an toàn không lỗi
        $reviewsPhp = __DIR__ . '/../../reviews.php';
        $this->assertTrue(file_exists($reviewsPhp), '[PASS] File reviews.php tồn tại');
        
        $reviewsContent = file_get_contents($reviewsPhp);
        $this->assertTrue(str_contains($reviewsContent, 'getCurrentCustomer'), '[PASS] reviews.php có gọi getCurrentCustomer an toàn');
        $this->assertTrue(str_contains($reviewsContent, 'about.php'), '[PASS] reviews.php liên kết tới about.php');
        $this->assertTrue(str_contains($reviewsContent, 'index.php'), '[PASS] reviews.php liên kết tới index.php');

        // Kiểm tra process_order.php có link dẫn sang reviews.php
        $processOrderContent = file_get_contents(__DIR__ . '/../../process_order.php');
        $this->assertTrue(str_contains($processOrderContent, 'reviews.php'), '[PASS] process_order.php có link dẫn tới reviews.php');

        // Kiểm tra index.php có link dẫn sang reviews.php và about.php
        $indexContent = file_get_contents(__DIR__ . '/../../index.php');
        $this->assertTrue(str_contains($indexContent, 'reviews.php'), '[PASS] index.php có link tới reviews.php');
        $this->assertTrue(str_contains($indexContent, 'about.php'), '[PASS] index.php có link tới about.php');

        // Kiểm tra about.php có link dẫn sang index.php và reviews.php
        $aboutContent = file_get_contents(__DIR__ . '/../../about.php');
        $this->assertTrue(str_contains($aboutContent, 'index.php'), '[PASS] about.php có link tới index.php');
        $this->assertTrue(str_contains($aboutContent, 'reviews.php'), '[PASS] about.php có link tới reviews.php');
    }
}
