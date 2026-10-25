<?php
declare(strict_types=1);

/**
 * File mẫu cấu hình môi trường (Template / Example Configuration)
 * 
 * Hướng dẫn sử dụng:
 * 1. Sao chép file này thành `config/config.local.php`:
 *    cp config/config.example.php config/config.local.php
 * 2. Cập nhật các thông số bảo mật thực tế (Zalo App ID, Secret Key, Mật khẩu Admin,...)
 * 3. File `config/config.local.php` được bảo mật và tự động bỏ qua khỏi Git (.gitignore).
 */

return [
    'app' => [
        'env' => 'production', // 'development' hoặc 'production'
        'debug' => false,
    ],
    'admin' => [
        'default_password' => 'abc@2026', // Mật khẩu quản trị Admin trên môi trường này
    ],
    'vietqr' => [
        'api_url' => 'https://img.vietqr.io/image',
        'template' => 'compact2',
    ],
    'zalo_login' => [
        // Thông tin Zalo App chính thức từ Zalo Developer Console
        'app_id' => '123',
        'secret_key' => '123',
        // Tắt chế độ mock khi chạy thật trên Production
        'mock_test_mode' => false,
        // Bắt buộc khách đăng nhập Zalo để đặt món (true/false)
        'require_zalo_login' => true,
        'require_zalo_group_to_order' => false,
        // Callback URL (để trống để tự động nhận diện clean URL, hoặc điền URL đăng ký trên Zalo Console)
        'callback_url' => '', // Ví dụ: 'https://qts-home.duckdns.org/chaytt/api/public/zalo-callback'
        'seller_zalo_url' => 'https://zalo.me/0987862241',
        'order_note_shipping' => 'Chưa bao gồm chi phí vận chuyển',
        'zalo_confirm_template' => 'Chào shop, mình vừa đặt đơn trên web. Mã đơn: {order_id}. Nhờ bếp kiểm tra và xác nhận giúp mình nhé!',
    ],
];
