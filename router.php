<?php
declare(strict_types=1);

/**
 * Development Router for PHP Built-in Server
 * Hỗ trợ Clean URLs (truy cập /admin, /index không cần đuôi .php)
 * 
 * Cách chạy:
 * php -S localhost:8008 router.php
 */

$uri = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// 1. Bảo vệ các file cơ sở dữ liệu và cấu hình nhạy cảm
if (preg_match('/\.(db|sqlite|sqlite3|sql|log|env)$/i', $uri)) {
    http_response_code(404);
    echo "404 Not Found";
    exit;
}

// 2. Nếu là file tĩnh có thật trong assets hoặc thư mục gốc, để PHP built-in server tự xử lý
$filePath = __DIR__ . $uri;
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false;
}

// 3. Nếu đường dẫn tương ứng với một file .php (ví dụ /admin -> admin.php)
$phpFile = __DIR__ . rtrim($uri, '/') . '.php';
if (file_exists($phpFile) && is_file($phpFile)) {
    require $phpFile;
    exit;
}

// 4. Các endpoint Zalo clean route
if (preg_match('#^/(api/public/)?zalo-(login|callback)/?$#', $uri)) {
    require __DIR__ . '/index.php';
    exit;
}

// 5. Mặc định phục vụ index.php
require __DIR__ . '/index.php';
