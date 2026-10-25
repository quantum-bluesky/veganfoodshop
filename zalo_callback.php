<?php
declare(strict_types=1);

/**
 * Endpoint tiếp nhận Zalo OAuth Callback trực tiếp
 */

require_once __DIR__ . '/src/autoload.php';

use App\Services\ZaloAuthService;

header('Content-Type: text/html; charset=UTF-8');

try {
    $code = (string)($_GET['code'] ?? '');
    $codeVerifier = (string)($_COOKIE['zalo_code_verifier'] ?? '');

    $zaloService = new ZaloAuthService();
    $user = $zaloService->handleCallback($code, $codeVerifier);

    echo $zaloService->renderCallbackHtml($user, 'index.php');
} catch (\Throwable $e) {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <title>Lỗi Đăng Nhập Zalo</title>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body style="display: flex; align-items: center; justify-content: center; min-height: 100vh;">
        <div class="zen-card" style="text-align: center; max-width: 400px; padding: 28px;">
            <div style="font-size: 2.2rem; margin-bottom: 8px;">🌱</div>
            <h2 style="font-size: 1.1rem; color: var(--brand-tea-dark); margin-bottom: 8px;">Không thể kết nối Zalo</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted-warm); margin-bottom: 18px;">
                <?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') ?>
            </p>
            <a href="index.php" class="zen-btn-primary" style="text-decoration: none;">Quay về Bếp An Yên</a>
        </div>
    </body>
    </html>
    <?php
}
