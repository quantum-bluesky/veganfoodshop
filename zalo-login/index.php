<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use App\Services\ZaloAuthService;

try {
    $zaloService = new ZaloAuthService();
    $authData = $zaloService->generateAuthUrl();

    if (!$authData['is_mock']) {
        setcookie('zalo_code_verifier', $authData['code_verifier'], [
            'expires' => time() + 300,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    header('Location: ' . $authData['auth_url']);
    exit;
} catch (\Throwable $e) {
    header('Content-Type: text/html; charset=UTF-8');
    http_response_code(400);
    $errMsg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo - Bếp An Yên</title>
    <style>
        body { background: #FAF7F2; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: #FFFFFF; border-radius: 20px; padding: 32px 24px; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 4px 20px rgba(91, 112, 101, 0.08); }
        .icon { font-size: 2.5rem; margin-bottom: 12px; }
        h2 { color: #344037; font-size: 1.25rem; margin: 0 0 10px 0; }
        p { color: #5B7065; font-size: 0.9rem; line-height: 1.5; margin: 0 0 24px 0; }
        a { display: inline-block; background: #5B7065; color: #FFFFFF; text-decoration: none; padding: 11px 24px; border-radius: 12px; font-weight: 600; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🌱</div>
        <h2>Thông báo từ Bếp</h2>
        <p>{$errMsg}</p>
        <a href="../index.php">Quay lại Bếp An Yên</a>
    </div>
</body>
</html>
HTML;
    exit;
}
