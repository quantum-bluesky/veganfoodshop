<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use App\Services\ConfigService;
use App\Services\SlotService;
use App\Services\OrderService;
use App\Exceptions\ValidationException;

/**
 * Unit Test cho Issue #34 & Issue #35:
 * - Issue #34: Sửa text thông báo quá hạn (bỏ cụm "hơn 3 phút") & Sửa ConfigService lấy chuẩn từ file config khi DB chưa có.
 * - Issue #35: Không hard-code các thông tin nhận diện quán (Tên quán, Hotline, Email, Địa chỉ, Slogan...) và hỗ trợ cài đặt động qua Admin / Config file.
 */
class Issue34_35_DynamicConfigAndNoHardcodeTest
{
    private ?PDO $db = null;
    private int $passed = 0;
    private int $failed = 0;

    public function runAllTests(): void
    {
        echo "\n=== [TEST] Issue #34 & #35: Dynamic Config, Không Hardcode & Text Quá Hạn ===\n";

        $this->setUp();
        $this->testConfigServiceFallsBackToFileConfigWhenDbEmpty();
        $this->tearDown();

        $this->setUp();
        $this->testConfigServiceDbOverridesFileConfig();
        $this->tearDown();

        $this->setUp();
        $this->testConfigServiceAliasesResolveCorrectly();
        $this->tearDown();

        $this->setUp();
        $this->testOrderServiceOverdueMessageNoLongerContainsHon3Phut();
        $this->tearDown();

        $this->testAdminPhpHasDynamicConfigFields();

        echo "Result: {$this->passed} passed, {$this->failed} failed.\n";
        if ($this->failed > 0) {
            throw new \RuntimeException("Issue #34 & #35 test suite failed!");
        }
    }

    private function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $schemaFile = __DIR__ . '/../../database/schema.sql';
        if (file_exists($schemaFile)) {
            $schemaSql = file_get_contents($schemaFile);
            $this->db->exec($schemaSql);
        }
    }

    private function tearDown(): void
    {
        $this->db = null;
    }

    /**
     * 1. Kiểm tra ConfigService lấy đúng seller_phone và các thông tin từ file config khi DB trống
     */
    public function testConfigServiceFallsBackToFileConfigWhenDbEmpty(): void
    {
        $fileConfig = [
            'app' => [
                'name' => 'Bếp Chay Mộc Miên',
                'phone' => '0987862241',
                'email' => 'mocmien@vegan.vn',
                'address' => 'Số 88 Phố Vọng, Hà Nội',
                'quote' => 'Vị thanh mộc cho tâm bình yên',
            ],
            'zalo_login' => [
                'seller_phone' => '0987862241',
                'seller_zalo_url' => 'https://zalo.me/0987862241',
            ],
        ];

        $configService = new ConfigService($this->db, $fileConfig);

        // Kiểm tra lấy seller_phone khi bảng system_config chưa có bản ghi nào
        $sellerPhone = $configService->get('seller_phone');
        $this->assertEquals('0987862241', $sellerPhone, 'ConfigService phải lấy được seller_phone từ file config');

        $shopName = $configService->get('shop_name');
        $this->assertEquals('Bếp Chay Mộc Miên', $shopName, 'ConfigService phải lấy được shop_name từ file config');

        $shopAddress = $configService->get('shop_address');
        $this->assertEquals('Số 88 Phố Vọng, Hà Nội', $shopAddress, 'ConfigService phải lấy được shop_address từ file config');

        $sellerEmail = $configService->get('seller_email');
        $this->assertEquals('mocmien@vegan.vn', $sellerEmail, 'ConfigService phải lấy được seller_email từ file config');

        // Kiểm tra getAll() cũng nạp đầy đủ các giá trị từ file config làm nền tảng
        $all = $configService->getAll();
        $this->assertEquals('0987862241', $all['seller_phone'] ?? '', 'getAll() phải có seller_phone');
        $this->assertEquals('Bếp Chay Mộc Miên', $all['shop_name'] ?? '', 'getAll() phải có shop_name');
    }

    /**
     * 2. Kiểm tra CSDL SQLite có mức độ ưu tiên cao hơn và đè lên file config khi Admin chỉnh sửa
     */
    public function testConfigServiceDbOverridesFileConfig(): void
    {
        $fileConfig = [
            'app' => [
                'name' => 'Bếp Mặc Định',
                'phone' => '0987862241',
            ],
            'zalo_login' => [
                'seller_phone' => '0987862241',
            ],
        ];

        $configService = new ConfigService($this->db, $fileConfig);

        // Admin cập nhật số hotline mới trong DB
        $configService->set('seller_phone', '0911222333');
        $configService->set('shop_name', 'Quán Chay An Lạc');

        $this->assertEquals('0911222333', $configService->get('seller_phone'), 'DB phải đè lên file config cho seller_phone');
        $this->assertEquals('Quán Chay An Lạc', $configService->get('shop_name'), 'DB phải đè lên file config cho shop_name');
    }

    /**
     * 3. Kiểm tra các alias thông minh hoạt động mượt mà
     */
    public function testConfigServiceAliasesResolveCorrectly(): void
    {
        $fileConfig = [
            'app' => [
                'name' => 'Bếp An Tâm',
                'address' => '123 Đường Thiền, Đà Nẵng',
            ],
        ];

        $configService = new ConfigService($this->db, $fileConfig);

        // brand_name tự động map sang shop_name / app.name
        $this->assertEquals('Bếp An Tâm', $configService->get('brand_name'), 'brand_name phải tự động resolve từ app.name');
        // seller_address tự động map sang shop_address / app.address
        $this->assertEquals('123 Đường Thiền, Đà Nẵng', $configService->get('seller_address'), 'seller_address phải tự động resolve từ app.address');
    }

    /**
     * 4. Kiểm tra thông báo quá hạn trong OrderService bỏ cụm "hơn 3 phút" và nạp số điện thoại động
     */
    public function testOrderServiceOverdueMessageNoLongerContainsHon3Phut(): void
    {
        $fileConfig = [
            'app' => [
                'name' => 'Bếp Chay An Nhiên',
                'phone' => '0987862241',
            ],
            'zalo_login' => [
                'seller_phone' => '0987862241',
            ],
        ];

        $configService = new ConfigService($this->db, $fileConfig);
        $configService->set('min_lead_time_hours', '3');

        $slotService = new SlotService($this->db, $configService);
        $orderService = new OrderService($this->db, null, $slotService, $configService);

        $testDate = date('Y-m-d', strtotime('+1 day'));
        $stmt = $this->db->prepare("INSERT INTO time_slots (slot_date, slot_time, max_capacity, is_active) VALUES (?, '11:30 - 12:15', 10, 1)");
        $stmt->execute([$testDate]);
        $slotId = (int)$this->db->lastInsertId();

        // Tạo món ăn mẫu
        $this->db->exec("INSERT INTO menu_items (id, name, price, category, is_available) VALUES (1, 'Phở Chay', 45000, 'food', 1)");

        // Cutoff của ca 11:30 là 08:30:00.
        // Giả lập đặt lúc 08:35:00 (vượt quá cutoff 5 phút)
        $timeOverdue = strtotime("$testDate 08:35:00");

        $caught = false;
        $errorMessage = '';
        try {
            $orderService->createOrder([
                'customer_name' => 'Khách Thương',
                'customer_phone' => '0912345678',
                'slot_id' => $slotId,
                'order_type' => 'dine_in',
                'payment_method' => 'vietqr',
                'items' => [1 => 1],
                'current_time' => $timeOverdue,
            ]);
        } catch (ValidationException $e) {
            $caught = true;
            $errorMessage = $e->getMessage();
        }

        $this->assertTrue($caught, 'Phải ném ValidationException khi vượt quá thời gian đặt lịch');
        $this->assertStringContains('đã vượt quá thời gian nhận đặt lịch', $errorMessage, 'Thông báo phải có "đã vượt quá thời gian nhận đặt lịch"');
        $this->assertFalse(strpos($errorMessage, 'hơn 3 phút') !== false, 'Thông báo KHÔNG ĐƯỢC chứa cụm từ "hơn 3 phút"');
        $this->assertStringContains('0987862241', $errorMessage, 'Thông báo phải chứa số điện thoại hotline lấy động từ file config');
        $this->assertStringContains('Bếp Chay An Nhiên', $errorMessage, 'Thông báo phải chứa tên quán lấy động từ file config');
    }

    /**
     * 5. Kiểm tra admin.php đã bổ sung các trường cấu hình nhận diện quán
     */
    public function testAdminPhpHasDynamicConfigFields(): void
    {
        $adminContent = file_get_contents(__DIR__ . '/../../admin.php');
        $this->assertStringContains('name="seller_phone"', $adminContent, 'admin.php phải có input seller_phone');
        $this->assertStringContains('name="seller_email"', $adminContent, 'admin.php phải có input seller_email');
        $this->assertStringContains('name="shop_address"', $adminContent, 'admin.php phải có input shop_address');
        $this->assertStringContains('$configService->set(\'seller_phone\'', $adminContent, 'admin.php phải lưu seller_phone vào DB');
        $this->assertStringContains('$configService->set(\'seller_email\'', $adminContent, 'admin.php phải lưu seller_email vào DB');
        $this->assertStringContains('$configService->set(\'shop_address\'', $adminContent, 'admin.php phải lưu shop_address vào DB');
    }

    private function assertTrue(bool $condition, string $message = ''): void
    {
        if ($condition) {
            $this->passed++;
        } else {
            $this->failed++;
            echo "[FAIL] " . ($message ?: 'Condition is not true') . "\n";
        }
    }

    private function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertTrue(!$condition, $message ?: 'Condition is not false');
    }

    private function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            $this->passed++;
        } else {
            $this->failed++;
            echo "[FAIL] " . ($message ?: "Expected " . json_encode($expected) . " but got " . json_encode($actual)) . "\n";
        }
    }

    private function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) !== false) {
            $this->passed++;
        } else {
            $this->failed++;
            echo "[FAIL] " . ($message ?: "String does not contain needle: {$needle}") . "\n";
        }
    }
}
