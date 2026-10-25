<?php
declare(strict_types=1);

/**
 * Cấu hình riêng cho môi trường kiểm thử tự động (Unit / Integration Tests)
 * Đảm bảo 100% độc lập, không bị ảnh hưởng bởi config.local.php của máy trạm.
 */

return [
    'app' => [
        'name' => 'Bếp Chay An Lành',
        'phone' => '0987862241',
        'email' => 'bepchayan@gmail.com',
        'address' => 'Số 12 ngõ An Yên, phố Chùa Láng, Đống Đa, Hà Nội',
        'quote' => 'Một bát canh thanh ngọt, vỗ về những bộn bề ngày thường',
        'env' => 'testing',
    ],
    'zalo_login' => [
        'app_id' => '123',
        'secret_key' => '123',
        'mock_test_mode' => true,
        'require_zalo_login' => false,
        'require_zalo_group_to_order' => false,
        'seller_phone' => '0987862241',
        'seller_zalo_url' => 'https://zalo.me/0987862241',
    ],
    'booking' => [
        'min_lead_time_hours' => 3,
        'advance_booking_days' => 7,
    ],
];
