<?php
declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\OrderService;
use App\Services\NotificationService;
use App\Services\EmailService;
use PDO;

class NotificationOutboxTest extends TestCase
{
    public function runAllTests(): void
    {
        $this->setUp();
        $this->testEnqueueOrderNotificationsInTransaction();
        $this->tearDown();

        $this->setUp();
        $this->testNotificationWorkerProcessingSuccess();
        $this->tearDown();

        $this->setUp();
        $this->testNotificationRetryBackoffPolicy();
        $this->tearDown();

        $this->setUp();
        $this->testNotificationCrashRecovery();
        $this->tearDown();

        $this->setUp();
        $this->testNotificationAdminRetry();
        $this->tearDown();
    }

    /**
     * TC-NOTIF-01: Kiểm tra đưa thông báo vào hàng đợi cùng transaction khi tạo đơn hàng
     */
    public function testEnqueueOrderNotificationsInTransaction(): void
    {
        $orderService = new OrderService($this->db);

        // Tạo đơn hàng thành công
        $order = $orderService->createOrder([
            'customer_name' => 'Chị Hoa',
            'customer_phone' => '0933445566',
            'slot_id' => 1,
            'order_type' => 'dine_in',
            'payment_method' => 'vietqr',
            'items' => [1 => 2],
        ]);

        $orderId = (int)$order['id'];
        $this->assertTrue($orderId > 0, 'Đơn hàng phải được tạo với ID hợp lệ');

        // Kiểm tra trong bảng notification_outbox
        $stmt = $this->db->prepare('SELECT * FROM notification_outbox WHERE order_id = :order_id ORDER BY id ASC');
        $stmt->execute([':order_id' => $orderId]);
        $notifications = $stmt->fetchAll();

        $this->assertEquals(2, count($notifications), 'Phải có đúng 2 thông báo được tạo cho đơn hàng (email và web)');

        $emailNotif = $notifications[0];
        $this->assertEquals('email', $emailNotif['channel']);
        $this->assertEquals('pending', $emailNotif['status']);
        $this->assertEquals("new_order:{$orderId}:email", $emailNotif['event_key']);
        $this->assertEquals(0, (int)$emailNotif['attempt_count']);

        $webNotif = $notifications[1];
        $this->assertEquals('web', $webNotif['channel']);
        $this->assertEquals('pending', $webNotif['status']);
        $this->assertEquals("new_order:{$orderId}:web", $webNotif['event_key']);
    }

    /**
     * TC-NOTIF-02: Kiểm tra worker xử lý thành công thông báo và chuyển status sang 'sent'
     */
    public function testNotificationWorkerProcessingSuccess(): void
    {
        $orderService = new OrderService($this->db);
        $notifService = new NotificationService($this->db);

        $order = $orderService->createOrder([
            'customer_name' => 'Chị Lan',
            'customer_phone' => '0912345678',
            'slot_id' => 1,
            'order_type' => 'takeaway',
            'payment_method' => 'pay_later',
            'items' => [2 => 1],
        ]);

        $orderId = (int)$order['id'];

        // Lấy danh sách pending
        $pending = $notifService->getPendingItems(10);
        $this->assertTrue(count($pending) >= 2, 'Phải có ít nhất 2 thông báo đang pending');

        // Chạy worker xử lý toàn bộ hàng đợi
        $processed = $notifService->processQueue(10);
        $this->assertTrue($processed >= 2, 'Worker phải xử lý các thông báo trong hàng đợi');

        // Kiểm tra trạng thái sau khi xử lý
        $stmt = $this->db->prepare('SELECT * FROM notification_outbox WHERE order_id = :order_id');
        $stmt->execute([':order_id' => $orderId]);
        $records = $stmt->fetchAll();

        foreach ($records as $rec) {
            $this->assertEquals('sent', $rec['status'], 'Thông báo phải chuyển sang trạng thái sent');
            $this->assertNotNull($rec['sent_at'], 'Thời gian sent_at phải được ghi nhận');
        }
    }

    /**
     * TC-NOTIF-03: Kiểm tra chính sách thử lại (backoff retry policy) khi kênh gửi gặp lỗi
     */
    public function testNotificationRetryBackoffPolicy(): void
    {
        // Mock EmailService luôn ném ngoại lệ mô phỏng mất kết nối SMTP
        $failingEmailService = new class extends EmailService {
            public function sendNewOrderEmail(array $order, ?string $recipientEmail = null): bool
            {
                throw new \RuntimeException('Mất kết nối SMTP timeout');
            }
        };

        $notifService = new NotificationService($this->db, $failingEmailService);

        // Tạo 1 thông báo thủ công
        $stmt = $this->db->prepare("
            INSERT INTO notification_outbox (event_key, event_type, order_id, channel, status)
            VALUES ('test:fail:1', 'NEW_ORDER', 999, 'email', 'pending')
        ");
        $stmt->execute();
        $notifId = (int)$this->db->lastInsertId();

        // Lần 1: Thử xử lý -> Thất bại
        $ok1 = $notifService->processItem($notifId);
        $this->assertFalse($ok1, 'Lần 1 phải thất bại');

        $rec1 = $this->fetchNotification($notifId);
        $this->assertEquals('pending', $rec1['status']);
        $this->assertEquals(1, (int)$rec1['attempt_count']);
        $this->assertNotNull($rec1['next_retry_at']);
        $this->assertStringContainsString('Mất kết nối SMTP', (string)$rec1['last_error']);

        // Lần 2, 3, 4: Thử tiếp
        $notifService->processItem($notifId);
        $rec2 = $this->fetchNotification($notifId);
        $this->assertEquals(2, (int)$rec2['attempt_count']);

        $notifService->processItem($notifId);
        $rec3 = $this->fetchNotification($notifId);
        $this->assertEquals(3, (int)$rec3['attempt_count']);

        $notifService->processItem($notifId);
        $rec4 = $this->fetchNotification($notifId);
        $this->assertEquals(4, (int)$rec4['attempt_count']);
        $this->assertEquals('pending', $rec4['status']);

        // Lần 5: Quá 5 lần thử -> Phải chuyển sang 'failed'
        $notifService->processItem($notifId);
        $rec5 = $this->fetchNotification($notifId);
        $this->assertEquals(5, (int)$rec5['attempt_count']);
        $this->assertEquals('failed', $rec5['status'], 'Sau 5 lần lỗi phải chuyển thành failed');
        $this->assertEquals(null, $rec5['next_retry_at'], 'Khi failed thì next_retry_at là null');
    }

    /**
     * TC-NOTIF-04: Phục hồi khi worker/server khởi động lại bị kẹt 'processing'
     */
    public function testNotificationCrashRecovery(): void
    {
        $notifService = new NotificationService($this->db);

        // Giả lập 1 job bị kẹt ở trạng thái processing từ 10 phút trước do worker crash
        $this->db->exec("
            INSERT INTO notification_outbox 
            (event_key, event_type, order_id, channel, status, created_at)
            VALUES ('test:crash:1', 'NEW_ORDER', 888, 'web', 'processing', datetime('now', '-10 minutes'))
        ");
        $crashId = (int)$this->db->lastInsertId();

        // Worker quét hàng đợi
        $pending = $notifService->getPendingItems(10);
        $found = false;
        foreach ($pending as $p) {
            if ((int)$p['id'] === $crashId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Job kẹt processing từ trước phải được tự động phục hồi để xử lý lại');
    }

    /**
     * TC-NOTIF-05: Admin bấm gửi lại thủ công
     */
    public function testNotificationAdminRetry(): void
    {
        $notifService = new NotificationService($this->db);

        // Tạo 1 thông báo ở trạng thái failed
        $this->db->exec("
            INSERT INTO notification_outbox 
            (event_key, event_type, order_id, channel, status, attempt_count, last_error)
            VALUES ('test:failed:1', 'NEW_ORDER', 777, 'web', 'failed', 5, 'Lỗi trước đó')
        ");
        $failedId = (int)$this->db->lastInsertId();

        // Admin bấm retry
        $ok = $notifService->retryNotification($failedId);
        $this->assertTrue($ok, 'Retry thành công đối với kênh web');

        $rec = $this->fetchNotification($failedId);
        $this->assertEquals('sent', $rec['status']);
        $this->assertNotNull($rec['sent_at']);
    }

    private function fetchNotification(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM notification_outbox WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: [];
    }
}
