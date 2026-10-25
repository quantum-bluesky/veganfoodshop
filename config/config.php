<?php
declare(strict_types=1);

/**
 * Cấu hình tổng thể hệ thống VeganFoodShop (Bếp Chay An Yên)
 * Chuẩn PHP 8.4+, mã hóa UTF-8 No BOM
 */

$baseConfig = [
    'app' => [
        'name' => 'Bếp Chay An Yên',
        'env' => 'development',
        'debug' => true,
        'timezone' => 'Asia/Ho_Chi_Minh',
        'version' => '1.14.0',
    ],
    'database' => [
        'path' => __DIR__ . '/../database/bep_an_yen.db',
        'timeout' => 5000, // miliseconds
    ],
    'admin' => [
        'session_key' => 'bep_an_yen_admin_auth',
        'default_password' => 'abc123',
    ],
    'vietqr' => [
        'api_url' => 'https://img.vietqr.io/image',
        'template' => 'compact2', // Giao diện QR nhỏ gọn, hiện logo và số tiền
    ],
    'zalo_login' => [
        'app_id' => '123',
        'secret_key' => '123',
        'mock_test_mode' => true,
        'require_zalo_login' => false, // Mặc định dev/test: false, cấu hình thật trong config.local.php
        'require_zalo_group_to_order' => false,
        'callback_url' => '', // Để trống sẽ tự động nhận diện clean URL (vd: https://qts-home.duckdns.org/chaytt/api/public/zalo-callback)
        'seller_zalo_url' => 'https://zalo.me/0987862241',
        'order_note_shipping' => 'Chưa bao gồm chi phí vận chuyển',
        'zalo_confirm_template' => 'Chào Bếp, mình vừa đặt đơn trên web. Mã đơn: {order_id}. Nhờ bếp kiểm tra và xác nhận giúp mình nhé!',
    ],
    'booking' => [
        'min_lead_time_hours' => 3,     // Đặt trước tối thiểu N giờ trong ngày (mặc định 3h)
        'advance_booking_days' => 7,    // Cho phép đặt trước tối đa 1 tuần trở lại (mặc định 7 ngày)
    ],
];

// Nạp cấu hình môi trường ghi đè (config.local.php hoặc file chỉ định qua APP_CONFIG_FILE)
$envConfigFile = getenv('APP_CONFIG_FILE') ?: (__DIR__ . '/config.local.php');
if (file_exists($envConfigFile)) {
    $localConfig = require $envConfigFile;
    if (is_array($localConfig)) {
        $baseConfig = array_replace_recursive($baseConfig, $localConfig);
    }
}

return $baseConfig;
