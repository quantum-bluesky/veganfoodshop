<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;
use App\Database\Connection;

/**
 * Dịch Vụ Quản Lý Hàng Đợi Thông Báo SQLite (Notification Outbox)
 * Issue #13: Triển khai hàng đợi thông báo SQLite + Dashboard + Email dự phòng
 */
class NotificationService
{
    private PDO $db;
    private EmailService $emailService;

    public function __construct(?PDO $db = null, ?EmailService $emailService = null)
    {
        $this->db = $db ?? Connection::getInstance();
        $this->emailService = $emailService ?? new EmailService();
    }

    /**
     * Đưa thông báo đơn hàng mới vào hàng đợi (Outbox Pattern)
     * Gọi bên trong cùng Transaction với lưu đơn hàng
     */
    public function enqueueNewOrder(int $orderId, array $orderData): void
    {
        $payload = json_encode($orderData, JSON_UNESCAPED_UNICODE);

        // 1. Kênh Email (gửi email cho chủ bếp)
        $emailKey = sprintf('new_order:%d:email', $orderId);
        $stmtEmail = $this->db->prepare('
            INSERT OR IGNORE INTO notification_outbox 
            (event_key, event_type, order_id, channel, status, payload_json, created_at)
            VALUES (:key, \'NEW_ORDER\', :order_id, \'email\', \'pending\', :payload, CURRENT_TIMESTAMP)
        ');
        $stmtEmail->execute([
            ':key' => $emailKey,
            ':order_id' => $orderId,
            ':payload' => $payload,
        ]);

        // 2. Kênh Web Dashboard (thông báo trên web cho quản trị viên)
        $webKey = sprintf('new_order:%d:web', $orderId);
        $stmtWeb = $this->db->prepare('
            INSERT OR IGNORE INTO notification_outbox 
            (event_key, event_type, order_id, channel, status, payload_json, created_at)
            VALUES (:key, \'NEW_ORDER\', :order_id, \'web\', \'pending\', :payload, CURRENT_TIMESTAMP)
        ');
        $stmtWeb->execute([
            ':key' => $webKey,
            ':order_id' => $orderId,
            ':payload' => $payload,
        ]);
    }

    /**
     * Lấy các bản ghi cần xử lý trong hàng đợi
     * Hỗ trợ tự động phục hồi các job kẹt 'processing' quá 5 phút
     */
    public function getPendingItems(int $limit = 20): array
    {
        $sql = "
            SELECT * FROM notification_outbox
            WHERE (
                status = 'pending' 
                AND (next_retry_at IS NULL OR next_retry_at <= datetime('now'))
            )
            OR (
                status = 'processing' 
                AND created_at <= datetime('now', '-5 minutes')
            )
            ORDER BY id ASC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Xử lý một bản ghi thông báo trong Outbox
     */
    public function processItem(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT * FROM notification_outbox WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();

        if (!$item) {
            return false;
        }

        // Đánh dấu sang trạng thái processing
        $stmtLock = $this->db->prepare("UPDATE notification_outbox SET status = 'processing' WHERE id = :id");
        $stmtLock->execute([':id' => $id]);

        $channel = (string)$item['channel'];
        $payload = !empty($item['payload_json']) ? json_decode((string)$item['payload_json'], true) : [];
        $currentAttempt = (int)$item['attempt_count'] + 1;

        try {
            $success = false;

            if ($channel === 'email') {
                $success = $this->emailService->sendNewOrderEmail($payload);
            } elseif ($channel === 'web') {
                // Kênh Web tự động chuyển sent để hiển thị trên dashboard
                $success = true;
            } else {
                // Kênh khác nếu mở rộng trong tương lai
                $success = true;
            }

            if ($success) {
                $stmtSuccess = $this->db->prepare("
                    UPDATE notification_outbox 
                    SET status = 'sent', sent_at = CURRENT_TIMESTAMP, last_error = NULL 
                    WHERE id = :id
                ");
                $stmtSuccess->execute([':id' => $id]);
                return true;
            } else {
                throw new \RuntimeException('Kênh ' . $channel . ' trả về không thành công.');
            }
        } catch (Throwable $e) {
            $rawMsg = $e->getMessage();
            $errorMsg = function_exists('mb_substr') ? mb_substr($rawMsg, 0, 500) : substr($rawMsg, 0, 500);
            $this->handleFailure($id, $currentAttempt, $errorMsg);
            return false;
        }
    }

    /**
     * Tính toán thời gian backoff retry khi gặp lỗi
     * - Lần 1: sau 1 phút
     * - Lần 2: sau 5 phút
     * - Lần 3: sau 15 phút
     * - Lần 4: sau 1 giờ
     * - Quá 5 lần: chuyển status = 'failed'
     */
    private function handleFailure(int $id, int $attemptCount, string $errorMsg): void
    {
        if ($attemptCount >= 5) {
            $stmt = $this->db->prepare("
                UPDATE notification_outbox 
                SET status = 'failed', attempt_count = :attempt, next_retry_at = NULL, last_error = :error 
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':attempt' => $attemptCount,
                ':error' => $errorMsg,
            ]);
            return;
        }

        $intervals = [
            1 => '+1 minute',
            2 => '+5 minutes',
            3 => '+15 minutes',
            4 => '+1 hour',
        ];
        $interval = $intervals[$attemptCount] ?? '+1 hour';

        $stmt = $this->db->prepare("
            UPDATE notification_outbox 
            SET status = 'pending', attempt_count = :attempt, next_retry_at = datetime('now', :interval), last_error = :error 
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $id,
            ':attempt' => $attemptCount,
            ':interval' => $interval,
            ':error' => $errorMsg,
        ]);
    }

    /**
     * Xử lý toàn bộ hàng đợi đang chờ
     * @return int Số bản ghi đã xử lý
     */
    public function processQueue(int $limit = 20): int
    {
        $items = $this->getPendingItems($limit);
        $count = 0;
        foreach ($items as $item) {
            $this->processItem((int)$item['id']);
            $count++;
        }
        return $count;
    }

    /**
     * Thử gửi lại thủ công một thông báo (từ giao diện Admin)
     */
    public function retryNotification(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notification_outbox 
            SET status = 'pending', next_retry_at = datetime('now')
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);

        return $this->processItem($id);
    }

    /**
     * Lấy danh sách thông báo hiển thị cho Admin Dashboard
     */
    public function getNotificationsForAdmin(int $limit = 50, ?string $channel = null, ?string $status = null): array
    {
        $sql = '
            SELECT n.*, o.order_code, o.customer_phone, o.total_amount, o.order_type
            FROM notification_outbox n
            LEFT JOIN orders o ON n.order_id = o.id
            WHERE 1=1
        ';
        $params = [];

        if ($channel) {
            $sql .= ' AND n.channel = :channel';
            $params[':channel'] = $channel;
        }
        if ($status) {
            $sql .= ' AND n.status = :status';
            $params[':status'] = $status;
        }

        $sql .= ' ORDER BY n.id DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Thống kê trạng thái hàng đợi
     */
    public function getStats(): array
    {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) AS processing_count
            FROM notification_outbox
        ");
        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'pending' => (int)($row['pending_count'] ?? 0),
            'sent' => (int)($row['sent_count'] ?? 0),
            'failed' => (int)($row['failed_count'] ?? 0),
            'processing' => (int)($row['processing_count'] ?? 0),
        ];
    }
}
