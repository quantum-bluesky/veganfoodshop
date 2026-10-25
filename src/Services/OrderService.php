<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;
use App\Database\Connection;
use App\Exceptions\DomainException;
use App\Exceptions\SlotFullException;
use App\Exceptions\ValidationException;

class OrderService
{
    private ConfigService $configService;
    private NotificationService $notificationService;
    private ZaloAuthService $zaloAuthService;
    private PromotionService $promotionService;

    public function __construct(
        private ?PDO $db = null,
        private ?CustomerService $customerService = null,
        private ?SlotService $slotService = null,
        ?ConfigService $configService = null,
        ?NotificationService $notificationService = null,
        ?ZaloAuthService $zaloAuthService = null,
        ?PromotionService $promotionService = null
    ) {
        $this->db = $db ?? Connection::getInstance();
        $this->customerService = $customerService ?? new CustomerService($this->db);
        $this->slotService = $slotService ?? new SlotService($this->db);
        $this->configService = $configService ?? new ConfigService($this->db);
        $this->notificationService = $notificationService ?? new NotificationService($this->db);
        $this->zaloAuthService = $zaloAuthService ?? new ZaloAuthService($this->db, $this->configService, $this->customerService);
        $this->promotionService = $promotionService ?? new PromotionService($this->db);
    }

    /**
     * Sinh mã đơn hàng thân thiện (ví dụ: AY-20261025-7K9P)
     */
    public function generateOrderCode(): string
    {
        $datePrefix = date('Ymd');
        $randomPart = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        return sprintf('AY-%s-%s', $datePrefix, $randomPart);
    }

    /**
     * Tạo đơn đặt chỗ & đặt món với cơ chế Transaction IMMEDIATE khóa suất an toàn
     * 
     * @param array{
     *    customer_name: string,
     *    customer_phone: string,
     *    slot_id: int,
     *    order_type: string,
     *    payment_method: string,
     *    items: array<int, int>, // [menu_item_id => quantity]
     *    customer_note?: ?string,
     *    current_time?: ?int,
     *    zalo_id?: ?string,
     *    avatar_url?: ?string
     * } $data
     * @return array
     * @throws SlotFullException
     * @throws ValidationException
     * @throws DomainException
     */
    public function createOrder(array $data): array
    {
        // 1. Validation dữ liệu đầu vào
        $customerName = trim((string)($data['customer_name'] ?? ''));
        $rawPhone = (string)($data['customer_phone'] ?? '');
        $customerPhone = $this->customerService->normalizePhone($rawPhone);
        $slotId = (int)($data['slot_id'] ?? 0);
        $orderType = (string)($data['order_type'] ?? 'dine_in');
        $paymentMethod = (string)($data['payment_method'] ?? 'vietqr');
        $customerNote = isset($data['customer_note']) ? trim((string)$data['customer_note']) : null;
        $items = (array)($data['items'] ?? []);

        // Kiểm tra yêu cầu đăng nhập Zalo bắt buộc (Fix bug: bắt buộc Zalo login)
        if ($this->zaloAuthService->isRequireLogin()) {
            $zaloId = isset($data['zalo_id']) ? trim((string)$data['zalo_id']) : '';
            if ($zaloId === '') {
                throw new ValidationException('Bếp An Yên yêu cầu đăng nhập tài khoản Zalo trước khi gửi đơn để đảm bảo kết nối giữ chỗ.');
            }
        }

        if ($customerName === '') {
            throw new ValidationException('Xin vui lòng nhập tên của bạn.');
        }

        if (strlen($customerPhone) < 9 || strlen($customerPhone) > 11) {
            throw new ValidationException('Số điện thoại không hợp lệ.');
        }

        if (!in_array($orderType, ['dine_in', 'takeaway'], true)) {
            throw new ValidationException('Hình thức nhận món không hợp lệ.');
        }

        if (!in_array($paymentMethod, ['vietqr', 'pay_later', 'cash'], true)) {
            throw new ValidationException('Phương thức thanh toán không hợp lệ.');
        }

        // Lọc danh sách món có số lượng > 0
        $validItems = [];
        foreach ($items as $itemId => $qty) {
            $itemId = (int)$itemId;
            $qty = (int)$qty;
            if ($qty > 0) {
                if ($qty > 10) {
                    throw new ValidationException('Số lượng mỗi món không vượt quá 10 suất.');
                }
                $validItems[$itemId] = $qty;
            }
        }

        if (empty($validItems)) {
            throw new ValidationException('Xin vui lòng chọn ít nhất một món ăn hoặc thức uống.');
        }

        // Nếu chọn pay_later, bắt buộc khách phải là khách quen (is_trusted == 1)
        if ($paymentMethod === 'pay_later' && !$this->customerService->isTrusted($customerPhone)) {
            throw new ValidationException('Phương thức thanh toán sau chỉ dành riêng cho khách quen đã được bếp ghi nhận.');
        }

        // 2. Bắt đầu Transaction IMMEDIATE để khóa ghi, chống Race Condition
        $this->db->exec('BEGIN IMMEDIATE TRANSACTION;');

        try {
            // Lấy thông tin ca
            $stmtSlot = $this->db->prepare('SELECT id, slot_date, slot_time, max_capacity, is_active FROM time_slots WHERE id = :id');
            $stmtSlot->execute([':id' => $slotId]);
            $slot = $stmtSlot->fetch();

            if (!$slot || (int)$slot['is_active'] !== 1) {
                throw new DomainException('Khung giờ phục vụ này hiện không khả dụng.');
            }

            // Kiểm tra trạng thái Bếp tạm nghỉ (Issue #12)
            $closedStatus = $this->configService->getShopClosedStatus((string)$slot['slot_date']);
            if ($closedStatus['is_closed']) {
                throw new ValidationException($closedStatus['reason'] ?: 'Bếp An Yên hiện đang tạm nghỉ vào ngày này. Mong bạn thông cảm nhé.');
            }

            // Kiểm tra thời gian đặt trước tối thiểu (Cut-off lead time)
            $currentTime = isset($data['current_time']) ? (int)$data['current_time'] : time();
            $minLeadTimeHours = (int)$this->configService->get('min_lead_time_hours', '3');
            $slotStart = $this->slotService->parseSlotStartTime((string)$slot['slot_date'], (string)$slot['slot_time']);
            $cutoff = $slotStart - ($minLeadTimeHours * 3600);

            if ($currentTime > $cutoff) {
                if ($currentTime >= $slotStart) {
                    throw new ValidationException(sprintf('Khung giờ %s (%s) đã qua rồi. Xin bạn vui lòng chọn ca khác nhé.', $slot['slot_time'], $slot['slot_date']));
                }
                throw new ValidationException(sprintf('Khung giờ %s cần đặt trước ít nhất %d tiếng để bếp kịp ninh nước dùng. Nếu bạn cần đặt gấp, xin vui lòng nhắn trực tiếp Zalo với Bếp nhé.', $slot['slot_time'], $minLeadTimeHours));
            }

            $maxCapacity = (int)$slot['max_capacity'];

            // Lấy thông tin các món trong DB để snapshot và tính tiền
            $itemIds = array_keys($validItems);
            $inPlaceholders = implode(',', array_fill(0, count($itemIds), '?'));
            $stmtMenu = $this->db->prepare("SELECT id, name, price, category, is_available FROM menu_items WHERE id IN ($inPlaceholders)");
            $stmtMenu->execute($itemIds);
            $menuRows = $stmtMenu->fetchAll();

            $menuMap = [];
            foreach ($menuRows as $row) {
                $menuMap[(int)$row['id']] = $row;
            }

            // Đếm số món chính ('food') trong đơn mới
            $newFoodCount = 0;
            $orderLines = [];
            $totalAmount = 0;

            foreach ($validItems as $itemId => $qty) {
                if (!isset($menuMap[$itemId])) {
                    throw new ValidationException('Món ăn bạn chọn không tồn tại trong thực đơn.');
                }
                $itemInfo = $menuMap[$itemId];
                if ((int)$itemInfo['is_available'] !== 1) {
                    throw new DomainException(sprintf('Món "%s" hôm nay đã tạm hết.', $itemInfo['name']));
                }

                if ($itemInfo['category'] === 'food') {
                    $newFoodCount += $qty;
                }

                $unitPrice = (int)$itemInfo['price'];
                $subtotal = $unitPrice * $qty;
                $totalAmount += $subtotal;

                $orderLines[] = [
                    'menu_item_id' => $itemId,
                    'item_name' => (string)$itemInfo['name'],
                    'unit_price' => $unitPrice,
                    'quantity' => $qty,
                    'subtotal' => $subtotal,
                ];
            }

            // Kiểm tra số suất món ăn chính đã đặt trong ca
            $stmtCount = $this->db->prepare('
                SELECT COALESCE(SUM(oi.quantity), 0) AS booked_count
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN menu_items mi ON oi.menu_item_id = mi.id
                WHERE o.slot_id = :slot_id 
                  AND o.order_status != \'cancelled\'
                  AND mi.category = \'food\'
            ');
            $stmtCount->execute([':slot_id' => $slotId]);
            $currentBookedFood = (int)$stmtCount->fetchColumn();

            // Nếu vượt quá công suất
            if ($newFoodCount > 0 && ($currentBookedFood + $newFoodCount) > $maxCapacity) {
                $remaining = max(0, $maxCapacity - $currentBookedFood);
                throw new SlotFullException(
                    sprintf('Ca %s chỉ còn %d suất món ăn chính khả dụng. Xin bạn chọn ca khác hoặc giảm số lượng.', $slot['slot_time'], $remaining)
                );
            }

            // 3. Thêm hoặc cập nhật khách hàng (kèm Zalo ID nếu có)
            $zaloId = !empty($data['zalo_id']) ? trim((string)$data['zalo_id']) : null;
            $avatarUrl = !empty($data['avatar_url']) ? trim((string)$data['avatar_url']) : null;
            $this->customerService->saveOrUpdate($customerPhone, $customerName, null, $zaloId, $avatarUrl);

            // 3.1. Tính toán khuyến mại & chiết khấu giảm giá (Issue #20)
            $itemsForPromo = [];
            foreach ($validItems as $itemId => $qty) {
                $itemsForPromo[] = [
                    'item' => $menuMap[$itemId],
                    'quantity' => $qty,
                ];
            }
            $promoContext = [
                'customer_phone' => $customerPhone,
                'order_type' => $orderType,
                'customer_name' => $customerName,
                'zalo_id' => $zaloId,
            ];
            $promoCalc = $this->promotionService->calculateOrderDiscount((string)$slot['slot_date'], $itemsForPromo, $totalAmount, $promoContext);
            $subtotalBeforeDiscount = $totalAmount;
            $discountAmount = (int)$promoCalc['discount_amount'];
            $finalTotalAmount = (int)$promoCalc['final_total'];
            $appliedPromoTitle = $promoCalc['promotion_title'];

            if ($discountAmount > 0 && !empty($appliedPromoTitle)) {
                $promoNote = sprintf('[Ưu đãi: %s - Giảm %sđ]', $appliedPromoTitle, number_format($discountAmount, 0, ',', '.'));
                $customerNote = $customerNote !== null ? ($promoNote . ' ' . $customerNote) : $promoNote;
            }

            // 4. Sinh mã đơn hàng và chèn vào bảng orders
            $orderCode = $this->generateOrderCode();
            $paymentStatus = ($paymentMethod === 'pay_later') ? 'pay_later' : 'pending';

            $stmtOrder = $this->db->prepare('
                INSERT INTO orders (
                    order_code, customer_phone, slot_id, order_type, 
                    total_amount, payment_method, payment_status, order_status, 
                    customer_note, created_at
                ) VALUES (
                    :order_code, :customer_phone, :slot_id, :order_type, 
                    :total_amount, :payment_method, :payment_status, \'confirmed\', 
                    :customer_note, CURRENT_TIMESTAMP
                )
            ');
            $stmtOrder->execute([
                ':order_code' => $orderCode,
                ':customer_phone' => $customerPhone,
                ':slot_id' => $slotId,
                ':order_type' => $orderType,
                ':total_amount' => $finalTotalAmount,
                ':payment_method' => $paymentMethod,
                ':payment_status' => $paymentStatus,
                ':customer_note' => $customerNote,
            ]);

            $orderId = (int)$this->db->lastInsertId();

            // 5. Chèn chi tiết các món vào bảng order_items
            $stmtItem = $this->db->prepare('
                INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity, subtotal) 
                VALUES (:order_id, :menu_item_id, :item_name, :unit_price, :quantity, :subtotal)
            ');

            foreach ($orderLines as $line) {
                $stmtItem->execute([
                    ':order_id' => $orderId,
                    ':menu_item_id' => $line['menu_item_id'],
                    ':item_name' => $line['item_name'],
                    ':unit_price' => $line['unit_price'],
                    ':quantity' => $line['quantity'],
                    ':subtotal' => $line['subtotal'],
                ]);
            }

            // 6. Đưa thông báo vào hàng đợi Outbox trong cùng Transaction (Issue #13)
            $orderPayload = [
                'id' => $orderId,
                'order_code' => $orderCode,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'slot_id' => $slotId,
                'slot_date' => $slot['slot_date'],
                'slot_time' => $slot['slot_time'],
                'order_type' => $orderType,
                'subtotal' => $subtotalBeforeDiscount,
                'discount_amount' => $discountAmount,
                'total_amount' => $finalTotalAmount,
                'promotion_title' => $appliedPromoTitle,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'customer_note' => $customerNote,
                'items' => $orderLines,
            ];
            $this->notificationService->enqueueNewOrder($orderId, $orderPayload);

            // Cam kết giao dịch
            $this->db->exec('COMMIT;');

            return [
                'id' => $orderId,
                'order_code' => $orderCode,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'slot_id' => $slotId,
                'slot_date' => $slot['slot_date'],
                'slot_time' => $slot['slot_time'],
                'order_type' => $orderType,
                'subtotal' => $subtotalBeforeDiscount,
                'discount_amount' => $discountAmount,
                'total_amount' => $finalTotalAmount,
                'promotion_title' => $appliedPromoTitle,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'order_status' => 'confirmed',
                'customer_note' => $customerNote,
                'zalo_id' => $zaloId,
                'items' => $orderLines,
            ];
        } catch (Throwable $e) {
            try {
                $this->db->exec('ROLLBACK;');
            } catch (Throwable) {
                // Nuốt lỗi nếu transaction đã được rollback trước đó
            }
            throw $e;
        }
    }

    /**
     * Lấy chi tiết đơn hàng theo mã đơn
     */
    public function getOrderByCode(string $orderCode): ?array
    {
        $stmt = $this->db->prepare('
            SELECT o.*, c.name as customer_name, ts.slot_date, ts.slot_time
            FROM orders o
            JOIN customers c ON o.customer_phone = c.phone
            JOIN time_slots ts ON o.slot_id = ts.id
            WHERE o.order_code = :code 
            LIMIT 1
        ');
        $stmt->execute([':code' => trim($orderCode)]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }

        // Lấy chi tiết món
        $stmtItems = $this->db->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
        $stmtItems->execute([':order_id' => (int)$order['id']]);
        $order['items'] = $stmtItems->fetchAll();

        return $order;
    }

    /**
     * Lấy danh sách đơn hàng lọc theo ca (Admin)
     */
    public function getOrdersBySlot(int $slotId): array
    {
        $stmt = $this->db->prepare('
            SELECT o.*, c.name as customer_name, c.is_trusted, c.zalo_id, ts.slot_time, ts.slot_date
            FROM orders o
            JOIN customers c ON o.customer_phone = c.phone
            JOIN time_slots ts ON o.slot_id = ts.id
            WHERE o.slot_id = :slot_id
            ORDER BY o.created_at DESC
        ');
        $stmt->execute([':slot_id' => $slotId]);
        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $stmtItems = $this->db->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
            $stmtItems->execute([':order_id' => (int)$order['id']]);
            $order['items'] = $stmtItems->fetchAll();
        }
        unset($order);

        return $orders;
    }

    /**
     * Lấy toàn bộ đơn hàng của một ngày cụ thể (Admin xem tổng ngày)
     */
    public function getOrdersByDate(string $date): array
    {
        $stmt = $this->db->prepare('
            SELECT o.*, c.name as customer_name, c.is_trusted, c.zalo_id, ts.slot_time, ts.slot_date
            FROM orders o
            JOIN customers c ON o.customer_phone = c.phone
            JOIN time_slots ts ON o.slot_id = ts.id
            WHERE ts.slot_date = :date
            ORDER BY ts.slot_time ASC, o.created_at DESC
        ');
        $stmt->execute([':date' => $date]);
        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $stmtItems = $this->db->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
            $stmtItems->execute([':order_id' => (int)$order['id']]);
            $order['items'] = $stmtItems->fetchAll();
        }
        unset($order);

        return $orders;
    }

    /**
     * Thống kê bếp theo từng ca (tổng hợp số lượng từng món cần nấu trong ca)
     */
    public function getKitchenSummaryBySlot(int $slotId): array
    {
        $stmt = $this->db->prepare('
            SELECT mi.id, mi.name, mi.category, SUM(oi.quantity) as total_qty
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN menu_items mi ON oi.menu_item_id = mi.id
            WHERE o.slot_id = :slot_id 
              AND o.order_status != \'cancelled\'
            GROUP BY mi.id, mi.name, mi.category
            ORDER BY mi.category ASC, total_qty DESC
        ');
        $stmt->execute([':slot_id' => $slotId]);
        return $stmt->fetchAll();
    }

    /**
     * Thống kê bếp tổng hợp cho cả ngày (Kitchen Prep Sheet - toàn bộ các ca trong ngày)
     */
    public function getKitchenSummaryByDate(string $date): array
    {
        $stmt = $this->db->prepare('
            SELECT mi.id, mi.name, mi.category, SUM(oi.quantity) as total_qty
            FROM orders o
            JOIN time_slots ts ON o.slot_id = ts.id
            JOIN order_items oi ON o.id = oi.order_id
            JOIN menu_items mi ON oi.menu_item_id = mi.id
            WHERE ts.slot_date = :date 
              AND o.order_status != \'cancelled\'
            GROUP BY mi.id, mi.name, mi.category
            ORDER BY mi.category ASC, total_qty DESC
        ');
        $stmt->execute([':date' => $date]);
        return $stmt->fetchAll();
    }

    /**
     * Cập nhật trạng thái đơn hàng (Admin)
     */
    public function updateOrderStatus(int $orderId, string $status): bool
    {
        if (!in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE orders SET order_status = :status WHERE id = :id');
        return $stmt->execute([':status' => $status, ':id' => $orderId]);
    }

    /**
     * Cập nhật trạng thái thanh toán (Admin)
     */
    public function updatePaymentStatus(int $orderId, string $status): bool
    {
        if (!in_array($status, ['pending', 'paid', 'pay_later', 'refunded'], true)) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE orders SET payment_status = :status WHERE id = :id');
        return $stmt->execute([':status' => $status, ':id' => $orderId]);
    }

    /**
     * Lấy danh sách đơn hàng của khách theo Zalo ID hoặc Số điện thoại (Issue #14)
     */
    public function getOrdersByCustomer(string $identifier, int $limit = 10): array
    {
        $cleanPhone = $this->customerService->normalizePhone($identifier);
        $stmt = $this->db->prepare('
            SELECT o.*, c.name as customer_name, c.is_trusted, c.zalo_id, ts.slot_time, ts.slot_date
            FROM orders o
            JOIN customers c ON o.customer_phone = c.phone
            JOIN time_slots ts ON o.slot_id = ts.id
            WHERE c.zalo_id = :identifier OR o.customer_phone = :identifier OR o.customer_phone = :clean_phone
            ORDER BY ts.slot_date DESC, ts.slot_time DESC, o.id DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':identifier', trim($identifier));
        $stmt->bindValue(':clean_phone', $cleanPhone);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $stmtItems = $this->db->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
            $stmtItems->execute([':order_id' => (int)$order['id']]);
            $order['items'] = $stmtItems->fetchAll();
        }
        unset($order);

        return $orders;
    }

    /**
     * Lấy đơn hàng active gần nhất để hiển thị banner nhắc nhở khách trên website (Issue #14)
     */
    public function getUpcomingActiveOrder(string $identifier): ?array
    {
        $today = date('Y-m-d');
        $cleanPhone = $this->customerService->normalizePhone($identifier);
        $stmt = $this->db->prepare('
            SELECT o.*, c.name as customer_name, c.is_trusted, c.zalo_id, ts.slot_time, ts.slot_date
            FROM orders o
            JOIN customers c ON o.customer_phone = c.phone
            JOIN time_slots ts ON o.slot_id = ts.id
            WHERE (c.zalo_id = :identifier OR o.customer_phone = :identifier OR o.customer_phone = :clean_phone)
              AND o.order_status != \'cancelled\'
              AND ts.slot_date >= :today
            ORDER BY ts.slot_date ASC, ts.slot_time ASC
            LIMIT 1
        ');
        $stmt->execute([
            ':identifier' => trim($identifier),
            ':clean_phone' => $cleanPhone,
            ':today' => $today,
        ]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }

        $stmtItems = $this->db->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
        $stmtItems->execute([':order_id' => (int)$order['id']]);
        $order['items'] = $stmtItems->fetchAll();
        return $order;
    }

    /**
     * Xác nhận thanh toán tự động hoặc đối soát (Issue #16)
     */
    public function reconcilePayment(string $orderCode, ?string $transactionRef = null, ?int $paidAmount = null): array
    {
        $order = $this->getOrderByCode($orderCode);
        if (!$order) {
            throw new ValidationException('Không tìm thấy đơn hàng với mã: ' . $orderCode);
        }

        if ($paidAmount !== null && $paidAmount < (int)$order['total_amount']) {
            throw new ValidationException(sprintf(
                'Số tiền nhận (%sđ) nhỏ hơn tổng đơn (%sđ). Cần kiểm tra lại.',
                number_format($paidAmount, 0, ',', '.'),
                number_format((int)$order['total_amount'], 0, ',', '.')
            ));
        }

        $stmt = $this->db->prepare('
            UPDATE orders 
            SET payment_status = \'paid\', 
                paid_at = CURRENT_TIMESTAMP, 
                transaction_ref = :ref 
            WHERE id = :id
        ');
        $stmt->execute([
            ':ref' => $transactionRef,
            ':id' => (int)$order['id'],
        ]);

        // Đẩy sự kiện thông báo xác nhận thanh toán vào Outbox
        $this->notificationService->enqueue(
            sprintf('payment_paid:%d:web', (int)$order['id']),
            'PAYMENT_CONFIRMED',
            (int)$order['id'],
            'web',
            [
                'order_code' => $order['order_code'],
                'amount' => $order['total_amount'],
                'ref' => $transactionRef,
            ]
        );

        $order['payment_status'] = 'paid';
        $order['transaction_ref'] = $transactionRef;
        return $order;
    }

    /**
     * Chốt ca phục vụ (Issue #16): Đóng ca và chuyển các đơn đã qua sang completed
     */
    public function closeSlot(int $slotId, bool $completeOrders = true): array
    {
        // Đóng ca
        $stmtClose = $this->db->prepare('UPDATE time_slots SET is_closed = 1 WHERE id = :id');
        $stmtClose->execute([':id' => $slotId]);

        if ($completeOrders) {
            $stmtOrders = $this->db->prepare('
                UPDATE orders 
                SET order_status = \'completed\' 
                WHERE slot_id = :slot_id AND order_status = \'confirmed\'
            ');
            $stmtOrders->execute([':slot_id' => $slotId]);
        }

        // Lấy thống kê chốt ca
        $stmtStats = $this->db->prepare('
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN order_status = \'completed\' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN order_status = \'cancelled\' THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(total_amount) as total_amount,
                SUM(CASE WHEN payment_status = \'paid\' THEN total_amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN payment_status IN (\'pending\', \'pay_later\') AND order_status != \'cancelled\' THEN total_amount ELSE 0 END) as pending_amount
            FROM orders 
            WHERE slot_id = :slot_id
        ');
        $stmtStats->execute([':slot_id' => $slotId]);
        return $stmtStats->fetch() ?: [];
    }

    /**
     * Chốt toàn bộ kết quả trong ngày (End-of-day closing - Issue #16)
     */
    public function closeDay(string $date, bool $completeOrders = true): array
    {
        $stmtSlots = $this->db->prepare('SELECT id FROM time_slots WHERE slot_date = :date');
        $stmtSlots->execute([':date' => $date]);
        $slotIds = $stmtSlots->fetchAll(PDO::FETCH_COLUMN) ?: [];

        foreach ($slotIds as $sId) {
            $this->closeSlot((int)$sId, $completeOrders);
        }

        return $this->getClosingReport($date);
    }

    /**
     * Báo cáo tổng kết ngày / ca đã qua (Issue #16)
     */
    public function getClosingReport(string $date): array
    {
        $stmt = $this->db->prepare('
            SELECT 
                COUNT(o.id) as total_orders,
                SUM(CASE WHEN o.order_status = \'completed\' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN o.order_status = \'confirmed\' THEN 1 ELSE 0 END) as active_orders,
                SUM(CASE WHEN o.order_status = \'cancelled\' THEN 1 ELSE 0 END) as cancelled_orders,
                COALESCE(SUM(CASE WHEN o.order_status != \'cancelled\' THEN o.total_amount ELSE 0 END), 0) as total_revenue,
                COALESCE(SUM(CASE WHEN o.payment_status = \'paid\' THEN o.total_amount ELSE 0 END), 0) as paid_revenue,
                COALESCE(SUM(CASE WHEN o.payment_status IN (\'pending\', \'pay_later\') AND o.order_status != \'cancelled\' THEN o.total_amount ELSE 0 END), 0) as pending_revenue,
                SUM(CASE WHEN o.payment_status IN (\'pending\', \'pay_later\') AND o.order_status != \'cancelled\' THEN 1 ELSE 0 END) as pending_payment_count
            FROM orders o
            JOIN time_slots ts ON o.slot_id = ts.id
            WHERE ts.slot_date = :date
        ');
        $stmt->execute([':date' => $date]);
        $report = $stmt->fetch() ?: [];

        // Lấy danh sách các ca trong ngày và trạng thái đóng
        $stmtSlots = $this->db->prepare('
            SELECT id, slot_time, max_capacity, is_active, is_closed 
            FROM time_slots 
            WHERE slot_date = :date 
            ORDER BY slot_time ASC
        ');
        $stmtSlots->execute([':date' => $date]);
        $report['slots'] = $stmtSlots->fetchAll() ?: [];

        // Tính tổng số suất món đã phục vụ trong ngày
        $stmtItems = $this->db->prepare('
            SELECT COALESCE(SUM(oi.quantity), 0)
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN time_slots ts ON o.slot_id = ts.id
            WHERE ts.slot_date = :date AND o.order_status != \'cancelled\'
        ');
        $stmtItems->execute([':date' => $date]);
        $report['total_items_served'] = (int)$stmtItems->fetchColumn();

        // Thêm aliases để tương thích view và test
        $report['paid_amount'] = (int)($report['paid_revenue'] ?? 0);
        $report['pending_amount'] = (int)($report['pending_revenue'] ?? 0);

        return $report;
    }

    /**
     * Lấy danh sách các đơn hàng còn tồn đọng thanh toán cần đối soát (Issue #16)
     */
    public function getPendingReconcileOrders(int $limit = 50): array
    {
        $stmt = $this->db->prepare('
            SELECT o.*, c.name as customer_name, c.is_trusted, ts.slot_date, ts.slot_time
            FROM orders o
            JOIN customers c ON o.customer_phone = c.phone
            JOIN time_slots ts ON o.slot_id = ts.id
            WHERE o.payment_status IN (\'pending\', \'pay_later\')
              AND o.order_status != \'cancelled\'
            ORDER BY ts.slot_date ASC, ts.slot_time ASC, o.id ASC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll() ?: [];

        foreach ($orders as &$o) {
            $stmtItems = $this->db->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
            $stmtItems->execute([':order_id' => (int)$o['id']]);
            $o['items'] = $stmtItems->fetchAll();
        }
        unset($o);

        return $orders;
    }
}
