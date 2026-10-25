<?php
declare(strict_types=1);

/**
 * Worker Xử Lý Hàng Đợi Thông Báo SQLite (Notification Outbox Worker)
 * Ngôn ngữ: PHP 8.4+ CLI (Zero external dependencies)
 * 
 * Cách chạy:
 * 1. Chạy 1 lượt (Crontab / Test):
 *    php bin/notification_worker.php --run-once
 * 
 * 2. Chạy thường trực (Systemd Service / Daemon):
 *    php bin/notification_worker.php --daemon --sleep=5
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Services\NotificationService;
use App\Database\Connection;

// Chỉ cho phép chạy từ Command Line (CLI)
if (php_sapi_name() !== 'cli') {
    echo "Lỗi: Script này chỉ có thể thực thi từ dòng lệnh CLI.\n";
    exit(1);
}

// Phân tích tham số CLI
$options = getopt('', ['run-once', 'daemon', 'sleep::', 'limit::', 'verbose']);
$runOnce = isset($options['run-once']);
$isDaemon = isset($options['daemon']);
$sleepSeconds = max(1, (int)($options['sleep'] ?? 5));
$limit = max(1, (int)($options['limit'] ?? 20));
$verbose = isset($options['verbose']);

if (!$runOnce && !$isDaemon) {
    // Mặc định chạy 1 lượt nếu không chỉ định cờ
    $runOnce = true;
}

$db = Connection::getInstance();
$service = new NotificationService($db);

$log = function (string $msg) use ($verbose) {
    $time = date('Y-m-d H:i:s');
    $line = "[$time] $msg\n";
    echo $line;

    // Ghi ra file logs/worker.log
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logDir . '/worker.log', $line, FILE_APPEND);
};

$log(sprintf('Khởi động Notification Worker (%s)...', $isDaemon ? "Daemon, chu kỳ {$sleepSeconds}s" : 'Chế độ Run-Once'));

$keepRunning = true;
if (function_exists('pcntl_signal')) {
    declare(ticks = 1);
    pcntl_signal(SIGINT, function () use (&$keepRunning, $log) {
        $log('Nhận tín hiệu dừng (SIGINT). Đang dừng worker an toàn...');
        $keepRunning = false;
    });
    pcntl_signal(SIGTERM, function () use (&$keepRunning, $log) {
        $log('Nhận tín hiệu kết thúc (SIGTERM). Đang dừng worker an toàn...');
        $keepRunning = false;
    });
}

do {
    try {
        $pendingItems = $service->getPendingItems($limit);
        $total = count($pendingItems);

        if ($total > 0) {
            $log("Tìm thấy {$total} thông báo đang chờ xử lý.");
            foreach ($pendingItems as $item) {
                $id = (int)$item['id'];
                $channel = (string)$item['channel'];
                $eventKey = (string)$item['event_key'];
                $attempt = (int)$item['attempt_count'] + 1;

                $log("-> Xử lý job #{$id} [{$eventKey}] qua kênh [{$channel}] (Lần thử: {$attempt})...");

                $ok = $service->processItem($id);
                if ($ok) {
                    $log("   ✓ Job #{$id} gửi THÀNH CÔNG.");
                } else {
                    $log("   ✗ Job #{$id} THẤT BẠI. Đã lên lịch thử lại theo backoff.");
                }
            }
        } elseif ($verbose) {
            $log('Hàng đợi trống, không có thông báo nào cần gửi.');
        }
    } catch (\Throwable $e) {
        $log('LỖI KHÔNG MONG MUỐN TRONG WORKER: ' . $e->getMessage());
    }

    if ($runOnce || !$keepRunning) {
        break;
    }

    sleep($sleepSeconds);
} while ($keepRunning);

$log('Worker đã kết thúc an toàn. Tạm biệt! 🍃');
exit(0);
