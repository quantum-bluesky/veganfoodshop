<?php
declare(strict_types=1);

/**
 * Cấu hình tổng thể hệ thống VeganFoodShop (Bếp Chay An Yên)
 * Chuẩn PHP 8.4+, mã hóa UTF-8 No BOM
 */

return [
    'app' => [
        'name' => 'Bếp Chay An Yên',
        'env' => 'production',
        'debug' => false,
        'timezone' => 'Asia/Ho_Chi_Minh',
        'version' => '1.0.0',
    ],
    'database' => [
        'path' => __DIR__ . '/../database/bep_an_yen.db',
        'timeout' => 5000, // miliseconds
    ],
    'admin' => [
        'session_key' => 'bep_an_yen_admin_auth',
        'default_password' => 'AnYen@2026',
    ],
    'vietqr' => [
        'api_url' => 'https://img.vietqr.io/image',
        'template' => 'compact2', // Giao diện QR nhỏ gọn, hiện logo và số tiền
    ],
];
