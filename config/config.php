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
        'version' => '1.1.1',
    ],
    'database' => [
        'path' => __DIR__ . '/../database/bep_an_yen.db',
        'timeout' => 5000, // miliseconds
    ],
    'admin' => [
        'session_key' => 'bep_an_yen_admin_auth',
        'default_password' => 'mai@2026',
    ],
    'vietqr' => [
        'api_url' => 'https://img.vietqr.io/image',
        'template' => 'compact2', // Giao diện QR nhỏ gọn, hiện logo và số tiền
    ],
    'zalo_login' => [
        'app_id' => '123',
        'secret_key' => '123',
        'mock_test_mode' => false,
        'require_zalo_login' => true, // Option cho User đăng nhập Zalo để tự động điền
        'require_zalo_group_to_order' => false,
        'callback_url' => '', // Để trống sẽ tự động nhận diện clean URL (vd: https://qts-home.duckdns.org/chaytt/api/public/zalo-callback)
        'seller_zalo_url' => 'https://zalo.me/0987862241',
        'order_note_shipping' => 'Chưa bao gồm chi phí vận chuyển',
        'zalo_confirm_template' => 'Chào shop, mình vừa đặt đơn trên web. Mã đơn: {order_id}. Nhờ bếp kiểm tra và xác nhận giúp mình nhé!',
    ],
];
