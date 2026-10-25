<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use App\Services\PromotionService;
use App\Services\OrderService;
use App\Services\CustomerService;
use App\Services\SlotService;
use App\Services\ConfigService;
use App\Services\NotificationService;
use App\Services\ZaloAuthService;

/**
 * Test Suite cho Issue #20:
 * Quản lý Khuyến Mại theo lịch, ngày Âm Lịch (Mùng 1 & Rằm), món ăn & tích hợp đơn hàng
 */
class Issue20_PromotionsTest
{
    private PDO $db;
    private PromotionService $promotionService;
    private OrderService $orderService;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        $this->setUpDatabase();
        $this->promotionService = new PromotionService($this->db);

        $customerService = new CustomerService($this->db);
        $configService = new ConfigService($this->db);
        $configService->set('zalo_require_login', '0');
        $slotService = new SlotService($this->db, $configService);
        $notifService = new NotificationService($this->db);
        $zaloService = new ZaloAuthService($this->db, $configService, $customerService, [
            'app_id' => '123',
            'secret_key' => '123',
            'mock_test_mode' => true,
            'require_zalo_login' => false,
        ]);

        $this->orderService = new OrderService(
            $this->db,
            $customerService,
            $slotService,
            $configService,
            $notifService,
            $zaloService,
            $this->promotionService
        );
    }

    private function setUpDatabase(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $schemaSql = file_get_contents(__DIR__ . '/../../database/schema.sql');
        $this->db->exec($schemaSql);

        // Seed dữ liệu mẫu để test
        $this->db->exec("
            INSERT INTO customers (phone, name, is_trusted) VALUES ('0901234567', 'Chị Lan', 1);

            INSERT INTO time_slots (id, slot_date, slot_time, max_capacity, is_active) 
            VALUES (1, '2026-02-17', '11:30 - 12:30', 20, 1),
                   (2, '2026-10-25', '11:30 - 12:30', 20, 1);

            INSERT INTO menu_items (id, name, price, category, is_feast_combo, is_available) 
            VALUES (1, 'Phở Nấm Hương', 45000, 'food', 0, 1),
                   (2, 'Bún Huế Chay', 50000, 'food', 0, 1),
                   (3, 'Trà Sen Tuyết', 20000, 'drink', 0, 1),
                   (4, 'Mâm Cỗ An Lành 5 Món', 300000, 'food', 1, 1);
        ");
    }

    public function runAllTests(): void
    {
        echo "\n=== [TEST] Issue #20: Thiết Lập Khuyến Mại & Giảm Giá Theo Lịch ===\n";

        $this->testCreateAndGetPromotions();
        $this->testActivePromotionsWithLunarRule();
        $this->testCalculateOrderDiscountPercentage();
        $this->testCalculateOrderDiscountFixedAmount();
        $this->testCalculateOrderDiscountFeastCombo();
        $this->testEnrichMenuItemsWithPromotions();
        $this->testOrderServiceIntegratesPromotionDiscount();

        echo "=> Kết quả Issue #20: {$this->passed} PASS, {$this->failed} FAIL\n\n";

        if ($this->failed > 0) {
            throw new \RuntimeException("Issue20_PromotionsTest failed with {$this->failed} errors");
        }
    }

    private function testCreateAndGetPromotions(): void
    {
        $id = $this->promotionService->createPromotion([
            'title' => 'Ưu Đãi Khai Xuân',
            'code' => 'XUAN2026',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 50000,
            'applies_to' => 'all',
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'badge_text' => '🔥 Giảm 10%',
        ]);

        $this->assert($id > 0, "Tạo khuyến mại thành công, ID > 0");

        $promo = $this->promotionService->getPromotionById($id);
        $this->assert($promo !== null, "Lấy thông tin khuyến mại theo ID hợp lệ");
        $this->assert($promo['title'] === 'Ưu Đãi Khai Xuân', "Khuyến mại đúng tên");
        $this->assert((int)$promo['discount_value'] === 10, "Mức giảm đúng 10%");
        $this->assert((int)$promo['is_active'] === 1, "Mặc định khuyến mại ở trạng thái bật");

        // Toggle tắt
        $this->promotionService->togglePromotionStatus($id, false);
        $promoUpdated = $this->promotionService->getPromotionById($id);
        $this->assert((int)$promoUpdated['is_active'] === 0, "Đã toggle tắt trạng thái khuyến mại");

        // Bật lại
        $this->promotionService->togglePromotionStatus($id, true);
    }

    private function testActivePromotionsWithLunarRule(): void
    {
        // Tạo khuyến mại chỉ áp dụng vào ngày Mùng 1 & Rằm (1,15 ÂL)
        $promoId = $this->promotionService->createPromotion([
            'title' => 'Đại Lễ Chay Mùng 1 & Rằm',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'applies_to' => 'all',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'apply_on_lunar_days' => '1,15',
            'badge_text' => '🌕 Ưu Đãi Ngày Rằm',
        ]);

        // 2026-02-17 là Mùng 1 Tết (1/1 ÂL) -> Phải CÓ khuyến mại
        $promosMung1 = $this->promotionService->getActivePromotionsForDate('2026-02-17');
        $hasMung1Promo = false;
        foreach ($promosMung1 as $p) {
            if ((int)$p['id'] === $promoId) {
                $hasMung1Promo = true;
                break;
            }
        }
        $this->assert($hasMung1Promo, "2026-02-17 (Mùng 1 ÂL) nhận diện đúng khuyến mại Mùng 1 & Rằm");

        // 2026-10-25 là ngày 16/9 ÂL -> KHÔNG được áp dụng khuyến mại Mùng 1 & Rằm
        $promosNgayThuong = $this->promotionService->getActivePromotionsForDate('2026-10-25');
        $hasNgayThuongPromo = false;
        foreach ($promosNgayThuong as $p) {
            if ((int)$p['id'] === $promoId) {
                $hasNgayThuongPromo = true;
                break;
            }
        }
        $this->assert(!$hasNgayThuongPromo, "2026-10-25 (16/9 ÂL) tự động loại bỏ khuyến mại Mùng 1 & Rằm");
    }

    private function testCalculateOrderDiscountPercentage(): void
    {
        // 2026-02-17 có KM 10% (XUAN2026) và 15% (Mùng 1)
        // Đơn: 2 bát Phở (45k x 2 = 90k)
        $items = [
            [
                'item' => ['id' => 1, 'name' => 'Phở Nấm Hương', 'price' => 45000, 'category' => 'food', 'is_feast_combo' => 0],
                'quantity' => 2,
            ]
        ];
        $subtotal = 90000;

        $calc = $this->promotionService->calculateOrderDiscount('2026-02-17', $items, $subtotal);

        // Mức giảm 15% của 90.000 = 13.500đ
        $this->assert($calc['discount_amount'] === 13500, "Tính đúng mức chiết khấu 15% (13.500đ) trên 90.000đ");
        $this->assert($calc['final_total'] === 76500, "Tổng tiền sau giảm còn 76.500đ");
        $this->assert($calc['promotion_title'] === 'Đại Lễ Chay Mùng 1 & Rằm', "Ưu tiên áp dụng khuyến mại giảm sâu nhất");
    }

    private function testCalculateOrderDiscountFixedAmount(): void
    {
        $id = $this->promotionService->createPromotion([
            'title' => 'Giảm 20k Đơn Trưa',
            'discount_type' => 'fixed_amount',
            'discount_value' => 20000,
            'min_order_amount' => 60000,
            'applies_to' => 'all',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $items = [
            [
                'item' => ['id' => 1, 'name' => 'Phở Nấm Hương', 'price' => 45000, 'category' => 'food'],
                'quantity' => 2,
            ]
        ];
        $subtotal = 90000;

        $calc = $this->promotionService->calculateOrderDiscount('2026-10-25', $items, $subtotal);
        $this->assert($calc['discount_amount'] === 20000, "Giảm đúng số tiền cố định 20.000đ");
        $this->assert($calc['final_total'] === 70000, "Tổng còn 70.000đ");
    }

    private function testCalculateOrderDiscountFeastCombo(): void
    {
        $id = $this->promotionService->createPromotion([
            'title' => 'Ưu Đãi Mâm Cỗ 10%',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'applies_to' => 'feast_combo',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        // Đơn gồm: 1 Mâm cỗ (300k) + 2 Trà (20k x 2 = 40k) => Tổng 340k
        $items = [
            [
                'item' => ['id' => 4, 'name' => 'Mâm Cỗ An Lành 5 Món', 'price' => 300000, 'category' => 'food', 'is_feast_combo' => 1],
                'quantity' => 1,
            ],
            [
                'item' => ['id' => 3, 'name' => 'Trà Sen Tuyết', 'price' => 20000, 'category' => 'drink', 'is_feast_combo' => 0],
                'quantity' => 2,
            ],
        ];
        $subtotal = 340000;

        $calc = $this->promotionService->calculateOrderDiscount('2026-10-25', $items, $subtotal);
        // Chỉ giảm 10% trên mâm cỗ 300.000đ = 30.000đ (không giảm trên trà)
        $this->assert($calc['discount_amount'] === 30000, "Chỉ giảm 10% trên mâm cỗ 300.000đ (30.000đ)");
        $this->assert($calc['final_total'] === 310000, "Tổng tiền sau giảm còn 310.000đ");
    }

    private function testEnrichMenuItemsWithPromotions(): void
    {
        $rawItems = [
            ['id' => 1, 'name' => 'Phở Nấm Hương', 'price' => 45000, 'category' => 'food', 'is_feast_combo' => 0],
            ['id' => 4, 'name' => 'Mâm Cỗ An Lành 5 Món', 'price' => 300000, 'category' => 'food', 'is_feast_combo' => 1],
        ];

        $enriched = $this->promotionService->enrichMenuItemsWithPromotions('2026-02-17', $rawItems);

        $this->assert($enriched[0]['has_promo'] === true, "Món phở có cờ has_promo = true");
        $this->assert($enriched[0]['final_price'] < $enriched[0]['original_price'], "Giá sau giảm nhỏ hơn giá gốc");
        $this->assert($enriched[0]['promo_badge'] !== null, "Món phở có gắn promo_badge");
    }

    private function testOrderServiceIntegratesPromotionDiscount(): void
    {
        // Đặt đơn qua OrderService vào ngày có khuyến mại 2026-02-17
        $orderData = [
            'customer_name' => 'Chị Lan',
            'customer_phone' => '0901234567',
            'slot_id' => 1, // slot 2026-02-17
            'order_type' => 'dine_in',
            'payment_method' => 'pay_later',
            'items' => [
                1 => 2, // 2 Phở x 45.000đ = 90.000đ
            ],
            'customer_note' => 'Ăn nóng',
            'current_time' => strtotime('2026-02-17 07:00:00'),
        ];

        $order = $this->orderService->createOrder($orderData);

        $this->assert($order['subtotal'] === 90000, "Đơn có subtotal trước giảm là 90.000đ");
        $this->assert($order['discount_amount'] > 0, "Đơn tự động được áp dụng giảm giá chiết khấu > 0");
        $this->assert($order['total_amount'] < 90000, "total_amount được trừ chiết khấu đúng");
        $this->assert($order['total_amount'] === 76500, "total_amount thực trả là 76.500đ");
        $this->assert(!empty($order['promotion_title']), "Ghi nhận đúng tên chương trình khuyến mại");
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
