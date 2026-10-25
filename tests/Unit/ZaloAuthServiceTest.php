<?php
declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ZaloAuthService;

class ZaloAuthServiceTest extends TestCase
{
    private array $mockConfig = [
        'app_id' => '123456',
        'secret_key' => 'secret_xyz',
        'mock_test_mode' => true,
        'require_zalo_login' => false,
        'require_zalo_group_to_order' => false,
        'seller_zalo_url' => 'https://zalo.me/0987862241',
        'order_note_shipping' => 'Chưa bao gồm chi phí vận chuyển',
        'zalo_confirm_template' => 'Chào shop, mình vừa đặt đơn {order_id}.',
    ];

    public function runAllTests(): void
    {
        $this->setUp();
        $this->testMockAuthUrlGeneration();
        $this->tearDown();

        $this->setUp();
        $this->testRealAuthUrlGeneration();
        $this->tearDown();

        $this->setUp();
        $this->testMockCallbackHandling();
        $this->tearDown();

        $this->setUp();
        $this->testRenderCallbackHtml();
        $this->tearDown();
    }

    /**
     * Test sinh URL đăng nhập ở chế độ mock test
     */
    public function testMockAuthUrlGeneration(): void
    {
        $service = new ZaloAuthService($this->db, null, null, $this->mockConfig);
        $this->assertTrue($service->isMockMode(), 'Service phải nhận diện mock mode = true');

        $redirectUri = 'http://localhost:8008/zalo_callback.php';
        $res = $service->generateAuthUrl($redirectUri);

        $this->assertTrue(
            str_contains($res['auth_url'], 'zalo_callback.php?code=MOCK_CODE'),
            'Ở mock mode, auth URL phải trỏ thẳng về callback với MOCK_CODE'
        );
        $this->assertTrue($res['is_mock']);
    }

    /**
     * Test sinh URL OAuth thật khi mock_test_mode = false
     */
    public function testRealAuthUrlGeneration(): void
    {
        $realConfig = $this->mockConfig;
        $realConfig['mock_test_mode'] = false;

        $service = new ZaloAuthService($this->db, null, null, $realConfig);
        $this->assertFalse($service->isMockMode(), 'Service phải nhận diện mock mode = false');

        $redirectUri = 'http://localhost:8008/zalo_callback.php';
        $res = $service->generateAuthUrl($redirectUri);

        $this->assertTrue(
            str_contains($res['auth_url'], 'https://oauth.zaloapp.com/v4/permission'),
            'URL thật phải trỏ tới OAuth endpoint của Zalo'
        );
        $this->assertTrue(
            str_contains($res['auth_url'], 'app_id=123456'),
            'URL phải chứa app_id'
        );
        $this->assertTrue(
            str_contains($res['auth_url'], 'code_challenge='),
            'URL phải sinh PKCE code_challenge'
        );
        $this->assertFalse($res['is_mock']);
    }

    /**
     * Test xử lý callback với mock code
     */
    public function testMockCallbackHandling(): void
    {
        $service = new ZaloAuthService($this->db, null, null, $this->mockConfig);
        $profile = $service->handleCallback('MOCK_CODE', 'some_verifier');

        $this->assertEquals('mock_zalo_123456', $profile['zalo_id']);
        $this->assertEquals('Khách Zalo Test', $profile['name']);
        $this->assertEquals('0999999999', $profile['phone']);
    }

    /**
     * Test render HTML callback lưu localStorage
     */
    public function testRenderCallbackHtml(): void
    {
        $service = new ZaloAuthService($this->db, null, null, $this->mockConfig);
        $profile = [
            'zalo_id' => 'zalo_888',
            'name' => 'Tuệ Mẫn',
            'phone' => '0911223344',
            'avatar_url' => 'https://example.com/avatar.png',
        ];

        $html = $service->renderCallbackHtml($profile, 'http://localhost:8008/index.php');

        $this->assertTrue(
            str_contains($html, 'localStorage.setItem("public_customer_info"'),
            'HTML callback phải có đoạn code lưu localStorage public_customer_info'
        );
        $this->assertTrue(
            str_contains($html, 'Tuệ Mẫn'),
            'HTML callback phải chứa tên người dùng'
        );
        $this->assertTrue(
            str_contains($html, 'zalo_888'),
            'HTML callback phải chứa Zalo ID'
        );
    }
}
