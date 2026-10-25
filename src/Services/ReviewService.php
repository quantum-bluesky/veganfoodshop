<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Exceptions\ValidationException;

class ReviewService
{
    private PDO $db;
    private ConfigService $configService;

    public function __construct(PDO $db, ConfigService $configService)
    {
        $this->db = $db;
        $this->configService = $configService;
    }

    /**
     * Kiểm tra tính năng đánh giá có đang được bật hay không
     */
    public function isReviewsEnabled(): bool
    {
        return (int)$this->configService->get('reviews_enabled', '0') === 1;
    }

    /**
     * Bật hoặc tắt tính năng đánh giá (Admin)
     */
    public function setReviewsEnabled(bool $enabled): void
    {
        $this->configService->set('reviews_enabled', $enabled ? '1' : '0');
    }

    /**
     * Kiểm tra có yêu cầu duyệt đánh giá trước khi hiện hay không
     */
    public function isApprovalRequired(): bool
    {
        return (int)$this->configService->get('reviews_require_approval', '1') === 1;
    }

    /**
     * Kiểm tra có cho phép khách chưa có đơn hàng đánh giá hay không
     */
    public function isAnonymousAllowed(): bool
    {
        return (int)$this->configService->get('reviews_allow_anonymous', '1') === 1;
    }

    /**
     * Khách gửi đánh giá mới
     */
    public function createReview(array $data): array
    {
        // 1. Kiểm tra trạng thái bật/tắt của tính năng
        if (!$this->isReviewsEnabled()) {
            throw new ValidationException('Tính năng Đánh giá & Cảm nhận hiện đang tạm đóng. Bếp rất cảm ơn sự quan tâm của bạn! 🍃');
        }

        // 2. Validate dữ liệu đầu vào
        $customerName = trim((string)($data['customer_name'] ?? ''));
        if ($customerName === '' || $this->strLen($customerName) < 2) {
            throw new ValidationException('Vui lòng nhập tên của bạn (tối thiểu 2 ký tự).');
        }

        $customerPhone = preg_replace('/[^0-9]/', '', (string)($data['customer_phone'] ?? ''));
        if ($customerPhone !== '' && (strlen($customerPhone) < 9 || strlen($customerPhone) > 11)) {
            throw new ValidationException('Số điện thoại không hợp lệ.');
        }

        $rating = (int)($data['rating'] ?? 5);
        if ($rating < 1 || $rating > 5) {
            throw new ValidationException('Đánh giá phải từ 1 đến 5 sao.');
        }

        $comment = trim((string)($data['comment'] ?? ''));
        if ($comment === '' || $this->strLen($comment) < 5) {
            throw new ValidationException('Vui lòng viết đôi dòng cảm nhận (tối thiểu 5 ký tự).');
        }

        if ($this->strLen($comment) > 1000) {
            throw new ValidationException('Nội dung đánh giá không quá 1000 ký tự.');
        }

        $title = trim((string)($data['title'] ?? ''));

        // 3. Xử lý gắn đơn hàng nếu có
        $orderId = !empty($data['order_id']) ? (int)$data['order_id'] : null;
        $orderCode = trim((string)($data['order_code'] ?? ''));
        $isVerifiedBuyer = 0;

        if ($orderCode !== '') {
            $stmtOrder = $this->db->prepare('SELECT id, customer_phone FROM orders WHERE order_code = :code LIMIT 1');
            $stmtOrder->execute([':code' => $orderCode]);
            $ord = $stmtOrder->fetch();
            if ($ord) {
                $orderId = (int)$ord['id'];
                $isVerifiedBuyer = 1;
                if ($customerPhone === '' && !empty($ord['customer_phone'])) {
                    $customerPhone = $ord['customer_phone'];
                }
            }
        } elseif ($orderId !== null && $orderId > 0) {
            $stmtOrder = $this->db->prepare('SELECT id, customer_phone FROM orders WHERE id = :id LIMIT 1');
            $stmtOrder->execute([':id' => $orderId]);
            $ord = $stmtOrder->fetch();
            if ($ord) {
                $isVerifiedBuyer = 1;
                if ($customerPhone === '' && !empty($ord['customer_phone'])) {
                    $customerPhone = $ord['customer_phone'];
                }
            }
        }

        // Nếu không cho phép khách vãng lai và không có đơn đã đặt
        if (!$this->isAnonymousAllowed() && $isVerifiedBuyer === 0) {
            throw new ValidationException('Bếp An Yên chỉ nhận đánh giá từ những bạn đã có đơn đặt trước. Xin bạn vui lòng nhập mã đơn hàng của mình nhé!');
        }

        // Chống gửi trùng đánh giá cho cùng 1 đơn hàng
        if ($orderId !== null) {
            $checkDup = $this->db->prepare('SELECT id FROM customer_reviews WHERE order_id = :order_id LIMIT 1');
            $checkDup->execute([':order_id' => $orderId]);
            if ($checkDup->fetch()) {
                throw new ValidationException('Đơn hàng này đã được gửi cảm nhận trước đó. Bếp xin chân thành cảm ơn bạn! 🍃');
            }
        }

        // Xác định trạng thái ban đầu: pending (cần duyệt) hoặc approved (hiện ngay)
        $status = $this->isApprovalRequired() ? 'pending' : 'approved';

        $stmt = $this->db->prepare('
            INSERT INTO customer_reviews (
                customer_phone, customer_name, order_id, rating, title, comment, 
                status, is_verified_buyer, created_at
            ) VALUES (
                :phone, :name, :order_id, :rating, :title, :comment,
                :status, :is_verified, CURRENT_TIMESTAMP
            )
        ');
        $stmt->execute([
            ':phone' => $customerPhone !== '' ? $customerPhone : null,
            ':name' => $customerName,
            ':order_id' => $orderId,
            ':rating' => $rating,
            ':title' => $title !== '' ? $title : null,
            ':comment' => $comment,
            ':status' => $status,
            ':is_verified' => $isVerifiedBuyer,
        ]);

        $newId = (int)$this->db->lastInsertId();

        return [
            'success' => true,
            'id' => $newId,
            'status' => $status,
            'is_verified_buyer' => $isVerifiedBuyer,
            'message' => $status === 'pending'
                ? 'Bếp Chay An Yên đã nhận lời nhắn gửi yêu thương của bạn. Bếp sẽ kiểm duyệt và sớm hiển thị lên trang nhé! 🌸'
                : 'Cảm ơn bạn đã gửi đánh giá! Cảm nhận của bạn đã được hiển thị trên trang Bếp An Yên. 🌸',
        ];
    }

    /**
     * Lấy danh sách đánh giá đã duyệt hiển thị ra trang công khai
     */
    public function getApprovedReviews(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare('
            SELECT r.*, o.order_code
            FROM customer_reviews r
            LEFT JOIN orders o ON r.order_id = o.id
            WHERE r.status = \'approved\'
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy danh sách các cảm nhận đặc biệt, lời đề tặng, kỷ niệm (Testimonials)
     * Dùng cho khu vực hiển thị trầm mặc sâu lắng trên trang công khai
     */
    public function getFeaturedTestimonials(int $limit = 10): array
    {
        $stmt = $this->db->prepare('
            SELECT r.*, o.order_code
            FROM customer_reviews r
            LEFT JOIN orders o ON r.order_id = o.id
            WHERE r.status = \'approved\' AND r.is_featured = 1
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Bật/tắt ghim cảm nhận đặc biệt (Testimonial / Featured)
     */
    public function toggleFeatured(int $id, ?int $forceStatus = null): bool
    {
        if ($forceStatus !== null) {
            $stmt = $this->db->prepare('UPDATE customer_reviews SET is_featured = :feat WHERE id = :id');
            return $stmt->execute([':feat' => $forceStatus ? 1 : 0, ':id' => $id]);
        }

        $stmt = $this->db->prepare('
            UPDATE customer_reviews 
            SET is_featured = CASE WHEN is_featured = 1 THEN 0 ELSE 1 END 
            WHERE id = :id
        ');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Cập nhật lời đề tựa / ghi chú kỷ niệm hoặc kiểu đánh giá
     */
    public function updateTestimonialNote(int $id, ?string $note, ?string $type = null): bool
    {
        $fields = ['testimonial_note = :note'];
        $params = [
            ':note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            ':id' => $id,
        ];

        if ($type !== null && in_array($type, ['review', 'testimonial'], true)) {
            $fields[] = 'type = :type';
            $params[':type'] = $type;
        }

        $sql = 'UPDATE customer_reviews SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Tạo một lời đề tặng, kỷ niệm mới trực tiếp từ quản trị (Ví dụ chép lại từ sổ lưu bút)
     */
    public function createTestimonial(array $data): int
    {
        $customerName = trim((string)($data['customer_name'] ?? ''));
        if ($customerName === '' || $this->strLen($customerName) < 2) {
            throw new ValidationException('Vui lòng nhập tên người gửi đề tặng (tối thiểu 2 ký tự).');
        }

        $comment = trim((string)($data['comment'] ?? ''));
        if ($comment === '' || $this->strLen($comment) < 5) {
            throw new ValidationException('Vui lòng nhập nội dung cảm nhận / lời đề tặng (tối thiểu 5 ký tự).');
        }

        $title = trim((string)($data['title'] ?? ''));
        $note = trim((string)($data['testimonial_note'] ?? ''));
        $kitchenReply = trim((string)($data['kitchen_reply'] ?? ''));
        $rating = (int)($data['rating'] ?? 5);
        if ($rating < 1 || $rating > 5) {
            $rating = 5;
        }

        $stmt = $this->db->prepare('
            INSERT INTO customer_reviews (
                customer_name, customer_phone, rating, title, comment, kitchen_reply,
                status, is_verified_buyer, is_featured, testimonial_note, type, created_at
            ) VALUES (
                :name, NULL, :rating, :title, :comment, :kitchen_reply,
                \'approved\', 0, 1, :note, \'testimonial\', CURRENT_TIMESTAMP
            )
        ');
        $stmt->execute([
            ':name' => $customerName,
            ':rating' => $rating,
            ':title' => $title !== '' ? $title : null,
            ':comment' => $comment,
            ':kitchen_reply' => $kitchenReply !== '' ? $kitchenReply : null,
            ':note' => $note !== '' ? $note : null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Tính điểm đánh giá trung bình và cơ cấu số sao
     */
    public function getReviewSummary(): array
    {
        $stmt = $this->db->query('
            SELECT rating, COUNT(*) as count
            FROM customer_reviews
            WHERE status = \'approved\'
            GROUP BY rating
        ');
        $rows = $stmt->fetchAll() ?: [];

        $breakdown = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $totalStars = 0;
        $totalCount = 0;

        foreach ($rows as $r) {
            $star = (int)$r['rating'];
            $cnt = (int)$r['count'];
            if (isset($breakdown[$star])) {
                $breakdown[$star] = $cnt;
            }
            $totalStars += ($star * $cnt);
            $totalCount += $cnt;
        }

        $average = $totalCount > 0 ? round($totalStars / $totalCount, 1) : 5.0;

        return [
            'total_count' => $totalCount,
            'average_rating' => $average,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Lấy toàn bộ đánh giá cho trang Admin
     */
    public function getAllReviewsForAdmin(?string $status = null, int $limit = 100, int $offset = 0): array
    {
        $sql = '
            SELECT r.*, o.order_code, o.slot_id, ts.slot_date, ts.slot_time
            FROM customer_reviews r
            LEFT JOIN orders o ON r.order_id = o.id
            LEFT JOIN time_slots ts ON o.slot_id = ts.id
        ';
        $params = [];

        if ($status !== null && in_array($status, ['pending', 'approved', 'hidden'], true)) {
            $sql .= ' WHERE r.status = :status';
            $params[':status'] = $status;
        }

        $sql .= ' ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Thống kê đánh giá cho Admin
     */
    public function getAdminStats(): array
    {
        $stmt = $this->db->query('
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = \'approved\' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = \'hidden\' THEN 1 ELSE 0 END) as hidden,
                AVG(CASE WHEN status = \'approved\' THEN rating ELSE NULL END) as avg_rating
            FROM customer_reviews
        ');
        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'pending' => (int)($row['pending'] ?? 0),
            'approved' => (int)($row['approved'] ?? 0),
            'hidden' => (int)($row['hidden'] ?? 0),
            'avg_rating' => !empty($row['avg_rating']) ? round((float)$row['avg_rating'], 1) : 5.0,
        ];
    }

    /**
     * Duyệt / Ẩn đánh giá (Admin)
     */
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['approved', 'hidden', 'pending'], true)) {
            throw new ValidationException('Trạng thái đánh giá không hợp lệ.');
        }

        $stmt = $this->db->prepare('UPDATE customer_reviews SET status = :status WHERE id = :id');
        return $stmt->execute([
            ':status' => $status,
            ':id' => $id,
        ]);
    }

    /**
     * Bếp phản hồi lời cảm nhận (Admin)
     */
    public function replyToReview(int $id, string $replyText): bool
    {
        $cleanReply = trim($replyText);
        $stmt = $this->db->prepare('
            UPDATE customer_reviews 
            SET kitchen_reply = :reply, 
                replied_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ');
        return $stmt->execute([
            ':reply' => $cleanReply !== '' ? $cleanReply : null,
            ':id' => $id,
        ]);
    }

    /**
     * Xóa đánh giá (Admin)
     */
    public function deleteReview(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM customer_reviews WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Đo độ dài chuỗi an toàn hỗ trợ cả khi môi trường chưa cài ext-mbstring
     */
    private function strLen(string $str): int
    {
        return function_exists('mb_strlen') ? mb_strlen($str, 'UTF-8') : strlen($str);
    }
}
