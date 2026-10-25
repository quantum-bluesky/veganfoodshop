<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;
use RuntimeException;
use Throwable;

class ZaloAuthService
{
    private array $config;

    public function __construct(
        private ?PDO $db = null,
        private ?ConfigService $configService = null,
        private ?CustomerService $customerService = null,
        ?array $customConfig = null
    ) {
        $this->db = $db ?? Connection::getInstance();
        $this->configService = $configService ?? new ConfigService($this->db);
        $this->customerService = $customerService ?? new CustomerService($this->db);

        $fileConfig = require __DIR__ . '/../../config/config.php';
        $zaloConfig = $fileConfig['zalo_login'] ?? [];

        // Hỗ trợ ghi đè cấu hình runtime từ bảng system_config (nếu có)
        $this->config = [
            'app_id' => $this->configService->get('zalo_app_id', (string)($zaloConfig['app_id'] ?? '123')),
            'secret_key' => $this->configService->get('zalo_secret_key', (string)($zaloConfig['secret_key'] ?? '123')),
            'mock_test_mode' => filter_var($this->configService->get('zalo_mock_test_mode', (string)($zaloConfig['mock_test_mode'] ? '1' : '0')), FILTER_VALIDATE_BOOLEAN),
            'require_zalo_login' => filter_var($this->configService->get('zalo_require_login', (string)($zaloConfig['require_zalo_login'] ? '1' : '0')), FILTER_VALIDATE_BOOLEAN),
            'require_zalo_group_to_order' => filter_var($this->configService->get('zalo_require_group', (string)($zaloConfig['require_zalo_group_to_order'] ? '1' : '0')), FILTER_VALIDATE_BOOLEAN),
            'seller_zalo_url' => $this->configService->get('zalo_seller_url', (string)($zaloConfig['seller_zalo_url'] ?? 'https://zalo.me/0987862241')),
            'order_note_shipping' => $this->configService->get('order_note_shipping', (string)($zaloConfig['order_note_shipping'] ?? 'Chưa bao gồm chi phí vận chuyển')),
            'zalo_confirm_template' => $this->configService->get('zalo_confirm_template', (string)($zaloConfig['zalo_confirm_template'] ?? 'Chào shop, mình vừa đặt đơn trên web. Mã đơn: {order_id}. Nhờ bếp kiểm tra và xác nhận giúp mình nhé!')),
        ];

        if ($customConfig !== null) {
            $this->config = array_merge($this->config, $customConfig);
        }
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function isMockMode(): bool
    {
        return (bool)($this->config['mock_test_mode'] ?? false);
    }

    public function isRequireLogin(): bool
    {
        return (bool)($this->config['require_zalo_login'] ?? false);
    }

    /**
     * Tạo đường dẫn đăng nhập Zalo OAuth với PKCE
     * 
     * @param string $callbackUrl
     * @return array{auth_url: string, code_verifier: string, is_mock: bool}
     */
    public function generateAuthUrl(string $callbackUrl): array
    {
        if ($this->config['mock_test_mode']) {
            $mockUrl = $callbackUrl . (str_contains($callbackUrl, '?') ? '&' : '?') . 'code=MOCK_CODE';
            return [
                'auth_url' => $mockUrl,
                'code_verifier' => 'mock_verifier',
                'is_mock' => true,
            ];
        }

        $appId = $this->config['app_id'];
        if ($appId === '' || $appId === '123') {
            throw new RuntimeException('Chưa cấu hình Zalo App ID thực tế hoặc đang để ID mặc định.');
        }

        // Tạo PKCE Code Verifier & Challenge (chuẩn Zalo PKCE)
        $codeVerifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $authUrl = sprintf(
            'https://oauth.zaloapp.com/v4/permission?app_id=%s&redirect_uri=%s&code_challenge=%s&state=zalo_login',
            urlencode($appId),
            urlencode($callbackUrl),
            urlencode($codeChallenge)
        );

        return [
            'auth_url' => $authUrl,
            'code_verifier' => $codeVerifier,
            'is_mock' => false,
        ];
    }

    /**
     * Xử lý callback sau khi người dùng đồng ý cấp quyền trên Zalo
     * 
     * @param string $code
     * @param string $codeVerifier
     * @return array{zalo_id: string, name: string, phone: string, avatar_url: string}
     */
    public function handleCallback(string $code, string $codeVerifier = ''): array
    {
        $code = trim($code);
        if ($code === '') {
            throw new RuntimeException('Thiếu mã xác thực (Authorization Code) từ Zalo.');
        }

        // 1. Chế độ Mock Test Mode
        if ($this->config['mock_test_mode'] && $code === 'MOCK_CODE') {
            $user = [
                'zalo_id' => 'mock_zalo_123456',
                'name' => 'Khách Zalo Test',
                'phone' => '0999999999',
                'avatar_url' => '',
            ];

            // Lưu/cập nhật thông tin vào DB
            $this->customerService->upsertZaloCustomer(
                $user['zalo_id'],
                $user['name'],
                $user['phone'],
                $user['avatar_url']
            );

            return $user;
        }

        // 2. Chế độ Zalo OAuth2 thực tế
        $appId = $this->config['app_id'];
        $secretKey = $this->config['secret_key'];

        if ($appId === '' || $secretKey === '') {
            throw new RuntimeException('Thiếu cấu hình Zalo App ID hoặc Secret Key.');
        }

        // Bước 2.1: Đổi Authorization Code lấy Access Token
        $tokenPostData = http_build_query([
            'app_id' => $appId,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ]);

        $tokenContext = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                            "secret_key: {$secretKey}\r\n",
                'content' => $tokenPostData,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $tokenResponseRaw = @file_get_contents('https://oauth.zaloapp.com/v4/access_token', false, $tokenContext);
        if ($tokenResponseRaw === false) {
            throw new RuntimeException('Không thể kết nối đến máy chủ xác thực Zalo.');
        }

        $tokenData = json_decode($tokenResponseRaw, true) ?: [];
        $accessToken = (string)($tokenData['access_token'] ?? '');
        if ($accessToken === '') {
            $errorDesc = $tokenData['error_description'] ?? $tokenData['message'] ?? 'Không lấy được access token từ Zalo';
            throw new RuntimeException((string)$errorDesc);
        }

        // Bước 2.2: Lấy thông tin người dùng từ Zalo Graph API
        $userContext = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "access_token: {$accessToken}\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $userResponseRaw = @file_get_contents('https://graph.zalo.me/v2.0/me?fields=id,name,picture', false, $userContext);
        if ($userResponseRaw === false) {
            throw new RuntimeException('Không thể lấy thông tin người dùng từ Zalo Graph API.');
        }

        $userData = json_decode($userResponseRaw, true) ?: [];
        $zaloId = (string)($userData['id'] ?? '');
        $name = (string)($userData['name'] ?? 'Khách Zalo');
        $avatarUrl = (string)($userData['picture']['data']['url'] ?? '');

        if ($zaloId === '') {
            throw new RuntimeException('Dữ liệu người dùng Zalo không hợp lệ.');
        }

        // Tìm xem khách hàng đã từng lưu SĐT với zalo_id này chưa
        $existingCustomer = $this->customerService->findByZaloId($zaloId);
        $phone = $existingCustomer['phone'] ?? '';

        // Upsert thông tin khách hàng
        $saved = $this->customerService->upsertZaloCustomer($zaloId, $name, $phone, $avatarUrl);

        return [
            'zalo_id' => $zaloId,
            'name' => $name,
            'phone' => $saved['phone'] ?? $phone,
            'avatar_url' => $avatarUrl,
        ];
    }

    /**
     * Sinh HTML callback lưu dữ liệu vào localStorage và chuyển về trang index
     */
    public function renderCallbackHtml(array $user, string $redirectUrl = 'index.php'): string
    {
        $userJson = json_encode([
            'name' => $user['name'] ?? '',
            'phone' => $user['phone'] ?? '',
            'zalo_id' => $user['zalo_id'] ?? '',
            'avatar_url' => $user['avatar_url'] ?? '',
        ], JSON_UNESCAPED_UNICODE);

        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đang kết nối Zalo...</title>
</head>
<body style="background: #FAF7F2; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: sans-serif; color: #5B7065;">
    <div style="text-align: center;">
        <p style="font-size: 1.1rem; font-weight: bold; margin-bottom: 8px;">🌿 Đã kết nối Zalo thành công!</p>
        <p style="font-size: 0.85rem; color: #7D7871;">Đang chuyển hướng về Bếp An Yên...</p>
    </div>
    <script>
        const user = {$userJson};
        localStorage.setItem("public_customer_info", JSON.stringify(user));
        window.location.href = "{$redirectUrl}";
    </script>
</body>
</html>
HTML;
    }
}
