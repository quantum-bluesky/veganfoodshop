<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Database\Connection;
use App\Exceptions\ValidationException;

class MenuService
{
    private ConfigService $configService;
    private ?NotificationService $notificationService;

    public function __construct(
        private ?PDO $db = null,
        ?ConfigService $configService = null,
        ?NotificationService $notificationService = null
    ) {
        $this->db = $db ?? Connection::getInstance();
        $this->configService = $configService ?? new ConfigService($this->db);
        $this->notificationService = $notificationService;
    }

    // ====================================================================
    // 1. QUẢN LÝ MÓN ĂN (MENU ITEMS)
    // ====================================================================

    /**
     * Lấy danh sách món đang phục vụ chung
     */
    public function getActiveMenu(?string $orderType = null): array
    {
        $sql = '
            SELECT id, name, description, price, category, image_url, service_scope, is_feast_combo, is_available, sort_order 
            FROM menu_items 
            WHERE is_available = 1 
        ';
        $params = [];

        if ($orderType === 'dine_in') {
            $sql .= " AND service_scope IN ('both', 'dine_in_only') ";
        } elseif ($orderType === 'takeaway') {
            $sql .= " AND service_scope IN ('both', 'takeaway_only') ";
        }

        $sql .= ' ORDER BY is_feast_combo DESC, sort_order ASC, id ASC ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Lấy toàn bộ thực đơn cho admin
     */
    public function getAllMenu(): array
    {
        $stmt = $this->db->query('
            SELECT id, name, description, price, category, image_url, service_scope, is_feast_combo, is_available, sort_order 
            FROM menu_items 
            ORDER BY is_feast_combo DESC, sort_order ASC, id ASC
        ');
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy thông tin một món theo ID
     */
    public function getItemById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id, name, description, price, category, image_url, service_scope, is_feast_combo, is_available, sort_order 
            FROM menu_items 
            WHERE id = :id 
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Thêm món mới vào thực đơn (Admin)
     */
    public function createItem(
        string $name,
        ?string $description,
        int $price,
        string $category = 'food',
        string $serviceScope = 'both',
        bool|int $isFeastCombo = false,
        int $sortOrder = 0
    ): int {
        $cleanName = trim($name);
        if ($cleanName === '') {
            throw new ValidationException('Tên món không được để trống.');
        }

        $validCat = in_array($category, ['food', 'drink', 'extra', 'feast_combo'], true) ? $category : 'food';
        $validScope = in_array($serviceScope, ['both', 'dine_in_only', 'takeaway_only'], true) ? $serviceScope : 'both';

        $stmt = $this->db->prepare('
            INSERT INTO menu_items (name, description, price, category, service_scope, is_feast_combo, is_available, sort_order) 
            VALUES (:name, :desc, :price, :cat, :scope, :is_combo, 1, :sort)
        ');
        $stmt->execute([
            ':name' => $cleanName,
            ':desc' => $description !== null ? trim($description) : null,
            ':price' => max(0, $price),
            ':cat' => $validCat,
            ':scope' => $validScope,
            ':is_combo' => !empty($isFeastCombo) ? 1 : 0,
            ':sort' => $sortOrder,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Cập nhật thông tin món (Admin)
     */
    public function updateItem(
        int $id,
        string $name,
        ?string $description,
        int $price,
        string $category = 'food',
        string $serviceScope = 'both',
        bool|int $isFeastCombo = false,
        int $sortOrder = 0,
        bool|int $isAvailable = true
    ): bool {
        $cleanName = trim($name);
        if ($cleanName === '') {
            throw new ValidationException('Tên món không được để trống.');
        }

        $validCat = in_array($category, ['food', 'drink', 'extra', 'feast_combo'], true) ? $category : 'food';
        $validScope = in_array($serviceScope, ['both', 'dine_in_only', 'takeaway_only'], true) ? $serviceScope : 'both';

        $stmt = $this->db->prepare('
            UPDATE menu_items 
            SET name = :name,
                description = :desc,
                price = :price,
                category = :cat,
                service_scope = :scope,
                is_feast_combo = :is_combo,
                sort_order = :sort,
                is_available = :is_avail
            WHERE id = :id
        ');
        return $stmt->execute([
            ':id' => $id,
            ':name' => $cleanName,
            ':desc' => $description !== null ? trim($description) : null,
            ':price' => max(0, $price),
            ':cat' => $validCat,
            ':scope' => $validScope,
            ':is_combo' => $isFeastCombo ? 1 : 0,
            ':sort' => $sortOrder,
            ':is_avail' => $isAvailable ? 1 : 0,
        ]);
    }

    /**
     * Bật / tắt khả dụng của món (Admin)
     */
    public function toggleAvailability(int $id, bool $isAvailable): bool
    {
        $stmt = $this->db->prepare('UPDATE menu_items SET is_available = :avail WHERE id = :id');
        return $stmt->execute([
            ':avail' => $isAvailable ? 1 : 0,
            ':id' => $id,
        ]);
    }

    /**
     * Cập nhật giá món (Admin)
     */
    public function updatePrice(int $id, int $price): bool
    {
        $stmt = $this->db->prepare('UPDATE menu_items SET price = :price WHERE id = :id');
        return $stmt->execute([
            ':price' => max(0, $price),
            ':id' => $id,
        ]);
    }

    /**
     * Xóa món an toàn (Admin)
     */
    public function deleteItem(int $id): bool
    {
        // Kiểm tra xem món đã từng có trong đơn hàng nào chưa
        $checkStmt = $this->db->prepare('SELECT COUNT(*) FROM order_items WHERE menu_item_id = :id');
        $checkStmt->execute([':id' => $id]);
        $orderCount = (int)$checkStmt->fetchColumn();

        if ($orderCount > 0) {
            // Đã có lịch sử đặt -> Không được xóa cứng khỏi DB để bảo toàn lịch sử đơn hàng
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM menu_items WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    // ====================================================================
    // 2. QUẢN LÝ BỘ THỰC ĐƠN MẪU (MENU SUITES / PRESETS)
    // ====================================================================

    /**
     * Lấy toàn bộ danh sách các bộ thực đơn
     */
    public function getAllSuites(): array
    {
        $stmt = $this->db->query('
            SELECT s.*, 
                   COUNT(si.menu_item_id) as items_count
            FROM menu_suites s
            LEFT JOIN menu_suite_items si ON s.id = si.suite_id
            GROUP BY s.id
            ORDER BY s.is_default DESC, s.id ASC
        ');
        $suites = $stmt->fetchAll() ?: [];

        foreach ($suites as &$st) {
            $st['items'] = $this->getSuiteItems((int)$st['id']);
        }
        unset($st);

        return $suites;
    }

    /**
     * Lấy chi tiết một bộ thực đơn theo ID
     */
    public function getSuiteById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM menu_suites WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $suite = $stmt->fetch();

        if (!$suite) {
            return null;
        }

        $suite['items'] = $this->getSuiteItems($id);
        return $suite;
    }

    /**
     * Lấy danh sách món trong một bộ thực đơn
     */
    public function getSuiteItems(int $suiteId): array
    {
        $stmt = $this->db->prepare('
            SELECT m.*, si.sort_order as suite_sort_order
            FROM menu_items m
            JOIN menu_suite_items si ON m.id = si.menu_item_id
            WHERE si.suite_id = :suite_id
            ORDER BY m.is_feast_combo DESC, si.sort_order ASC, m.sort_order ASC
        ');
        $stmt->execute([':suite_id' => $suiteId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lấy bộ thực đơn mặc định (Default Suite)
     */
    public function getDefaultSuite(): ?array
    {
        $stmt = $this->db->query('SELECT * FROM menu_suites WHERE is_default = 1 ORDER BY id ASC LIMIT 1');
        $suite = $stmt->fetch();
        if ($suite) {
            $suite['items'] = $this->getSuiteItems((int)$suite['id']);
            return $suite;
        }

        // Fallback lấy suite đầu tiên nếu chưa có cờ is_default
        $fallbackStmt = $this->db->query('SELECT * FROM menu_suites ORDER BY id ASC LIMIT 1');
        $fallback = $fallbackStmt->fetch();
        if ($fallback) {
            $fallback['items'] = $this->getSuiteItems((int)$fallback['id']);
            return $fallback;
        }

        return null;
    }

    /**
     * Tạo bộ thực đơn mới
     */
    public function createSuite(
        string $name,
        ?string $description = null,
        bool|int|array $isDefaultOrItemIds = false,
        array|bool|int $itemIdsOrIsDefault = []
    ): int {
        $cleanName = trim($name);
        if ($cleanName === '') {
            throw new ValidationException('Tên bộ thực đơn không được để trống.');
        }

        if (is_array($isDefaultOrItemIds)) {
            $itemIds = $isDefaultOrItemIds;
            $isDefault = !empty($itemIdsOrIsDefault);
        } else {
            $isDefault = !empty($isDefaultOrItemIds);
            $itemIds = is_array($itemIdsOrIsDefault) ? $itemIdsOrIsDefault : [];
        }

        $this->db->exec('BEGIN TRANSACTION;');
        try {
            if ($isDefault) {
                $this->db->exec('UPDATE menu_suites SET is_default = 0');
            }

            $stmt = $this->db->prepare('
                INSERT INTO menu_suites (name, description, is_default, created_at) 
                VALUES (:name, :desc, :is_default, CURRENT_TIMESTAMP)
            ');
            $stmt->execute([
                ':name' => $cleanName,
                ':desc' => $description !== null ? trim($description) : null,
                ':is_default' => $isDefault ? 1 : 0,
            ]);
            $suiteId = (int)$this->db->lastInsertId();

            if (!empty($itemIds)) {
                $stmtItem = $this->db->prepare('INSERT INTO menu_suite_items (suite_id, menu_item_id, sort_order) VALUES (:suite_id, :item_id, :sort)');
                $sort = 1;
                foreach ($itemIds as $itemId) {
                    $stmtItem->execute([
                        ':suite_id' => $suiteId,
                        ':item_id' => (int)$itemId,
                        ':sort' => $sort++,
                    ]);
                }
            }

            $this->db->exec('COMMIT;');
            return $suiteId;
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK;');
            throw $e;
        }
    }

    /**
     * Cập nhật bộ thực đơn
     */
    public function updateSuite(
        int $id,
        string $name,
        ?string $description = null,
        bool|int|array $isDefaultOrItemIds = false,
        array|bool|int $itemIdsOrIsDefault = []
    ): bool {
        $cleanName = trim($name);
        if ($cleanName === '') {
            throw new ValidationException('Tên bộ thực đơn không được để trống.');
        }

        if (is_array($isDefaultOrItemIds)) {
            $itemIds = $isDefaultOrItemIds;
            $isDefault = !empty($itemIdsOrIsDefault);
        } else {
            $isDefault = !empty($isDefaultOrItemIds);
            $itemIds = is_array($itemIdsOrIsDefault) ? $itemIdsOrIsDefault : [];
        }

        $this->db->exec('BEGIN TRANSACTION;');
        try {
            if ($isDefault) {
                $this->db->exec('UPDATE menu_suites SET is_default = 0');
            }

            $stmt = $this->db->prepare('
                UPDATE menu_suites 
                SET name = :name, description = :desc, is_default = :is_default 
                WHERE id = :id
            ');
            $stmt->execute([
                ':id' => $id,
                ':name' => $cleanName,
                ':desc' => $description !== null ? trim($description) : null,
                ':is_default' => $isDefault ? 1 : 0,
            ]);

            // Cập nhật danh sách món
            $deleteStmt = $this->db->prepare('DELETE FROM menu_suite_items WHERE suite_id = :id');
            $deleteStmt->execute([':id' => $id]);

            if (!empty($itemIds)) {
                $stmtItem = $this->db->prepare('INSERT INTO menu_suite_items (suite_id, menu_item_id, sort_order) VALUES (:suite_id, :item_id, :sort)');
                $sort = 1;
                foreach ($itemIds as $itemId) {
                    $stmtItem->execute([
                        ':suite_id' => $id,
                        ':item_id' => (int)$itemId,
                        ':sort' => $sort++,
                    ]);
                }
            }

            $this->db->exec('COMMIT;');
            return true;
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK;');
            throw $e;
        }
    }

    /**
     * Xóa bộ thực đơn
     */
    public function deleteSuite(int $id): bool
    {
        $suite = $this->getSuiteById($id);
        if (!$suite) {
            return false;
        }
        if ((int)$suite['is_default'] === 1) {
            throw new ValidationException('Không thể xóa bộ thực đơn mặc định của quán.');
        }

        $stmt = $this->db->prepare('DELETE FROM menu_suites WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    // ====================================================================
    // 3. LÊN LỊCH THỰC ĐƠN THEO NGÀY (MENU SCHEDULING & AUTOMATION)
    // ====================================================================

    /**
     * Xác định bộ thực đơn áp dụng cho một ngày cụ thể (theo lịch hoặc quy tắc tự động)
     */
    public function getScheduledSuiteForDate(string $date): ?array
    {
        // 1. Kiểm tra xem ngày này có được chỉ định lịch riêng trong menu_schedules không
        $stmt = $this->db->prepare('
            SELECT s.*, sch.note as schedule_note
            FROM menu_schedules sch
            JOIN menu_suites s ON sch.suite_id = s.id
            WHERE sch.schedule_date = :date
            LIMIT 1
        ');
        $stmt->execute([':date' => $date]);
        $custom = $stmt->fetch();
        if ($custom) {
            $custom['items'] = $this->getSuiteItems((int)$custom['id']);
            $custom['is_custom_schedule'] = true;
            return $custom;
        }

        // 2. Không có lịch riêng -> Áp dụng quy tắc tự động (system_config)
        $rule = $this->configService->get('menu_schedule_rule', 'default'); // 'default', 'even_odd', 'weekday'

        if ($rule === 'even_odd') {
            $dayNum = (int)date('j', strtotime($date));
            $isEven = ($dayNum % 2 === 0);
            $suiteId = $isEven 
                ? (int)$this->configService->get('menu_even_suite_id', '2') 
                : (int)$this->configService->get('menu_odd_suite_id', '3');

            $suite = $this->getSuiteById($suiteId);
            if ($suite) {
                $suite['is_custom_schedule'] = false;
                $suite['schedule_rule'] = $isEven ? 'Quy tắc Ngày Chẵn' : 'Quy tắc Ngày Lẻ';
                return $suite;
            }
        } elseif ($rule === 'weekday') {
            $dayOfWeek = (int)date('w', strtotime($date)); // 0: CN, 1: T2...
            // T2, T4, T6 -> Suite 2; T3, T5, T7 -> Suite 3; CN -> Suite 1
            $suiteId = in_array($dayOfWeek, [1, 3, 5], true) ? 2 : (in_array($dayOfWeek, [2, 4, 6], true) ? 3 : 1);
            $suite = $this->getSuiteById($suiteId);
            if ($suite) {
                $suite['is_custom_schedule'] = false;
                $suite['schedule_rule'] = 'Quy tắc theo Thứ trong tuần';
                return $suite;
            }
        }

        // 3. Fallback: Bộ thực đơn mặc định
        $defaultSuite = $this->getDefaultSuite();
        if ($defaultSuite) {
            $defaultSuite['is_custom_schedule'] = false;
            $defaultSuite['schedule_rule'] = 'Thực đơn Mặc Định';
            return $defaultSuite;
        }

        return null;
    }

    /**
     * Lấy danh sách món ăn cho khách theo ngày và hình thức nhận món (tại chỗ / mang về)
     */
    public function getMenuItemsForDate(string $date, ?string $orderType = null): array
    {
        $suite = $this->getScheduledSuiteForDate($date);

        if ($suite && !empty($suite['items'])) {
            $items = $suite['items'];

            // Lọc theo trạng thái is_available = 1
            $items = array_filter($items, fn($it) => (int)$it['is_available'] === 1);

            // Lọc theo service_scope nếu có orderType
            if ($orderType === 'dine_in') {
                $items = array_filter($items, fn($it) => in_array($it['service_scope'], ['both', 'dine_in_only'], true));
            } elseif ($orderType === 'takeaway') {
                $items = array_filter($items, fn($it) => in_array($it['service_scope'], ['both', 'takeaway_only'], true));
            }

            return array_values($items);
        }

        // Nếu suite chưa có món, fallback về thực đơn chung
        return $this->getActiveMenu($orderType);
    }

    /**
     * Gán bộ thực đơn cho một ngày cụ thể
     */
    public function assignSuiteToDate(string $date, int $suiteId, ?string $note = null): bool
    {
        $stmt = $this->db->prepare('
            INSERT INTO menu_schedules (schedule_date, suite_id, note, created_at)
            VALUES (:date, :suite_id, :note, CURRENT_TIMESTAMP)
            ON CONFLICT(schedule_date) DO UPDATE SET 
                suite_id = excluded.suite_id,
                note = excluded.note
        ');
        return $stmt->execute([
            ':date' => $date,
            ':suite_id' => $suiteId,
            ':note' => $note !== null ? trim($note) : null,
        ]);
    }

    /**
     * Xóa chỉ định lịch riêng cho một ngày (trở về quy tắc tự động)
     */
    public function removeScheduleForDate(string $date): bool
    {
        $stmt = $this->db->prepare('DELETE FROM menu_schedules WHERE schedule_date = :date');
        return $stmt->execute([':date' => $date]);
    }

    /**
     * Nhân bản lịch thực đơn cho một khoảng ngày theo các thứ trong tuần
     */
    public function replicateSchedule(int $suiteId, string $startDate, string $endDate, array $daysOfWeek = []): int
    {
        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);

        if ($startTs === false || $endTs === false || $startTs > $endTs) {
            throw new ValidationException('Khoảng ngày nhân bản không hợp lệ.');
        }

        if (empty($daysOfWeek)) {
            $daysOfWeek = [1, 2, 3, 4, 5, 6, 0];
        }

        $appliedCount = 0;
        $currentTs = $startTs;

        $stmt = $this->db->prepare('
            INSERT INTO menu_schedules (schedule_date, suite_id, note, created_at)
            VALUES (:date, :suite_id, :note, CURRENT_TIMESTAMP)
            ON CONFLICT(schedule_date) DO UPDATE SET suite_id = excluded.suite_id
        ');

        while ($currentTs <= $endTs) {
            $curDate = date('Y-m-d', $currentTs);
            $w = (int)date('w', $currentTs);

            if (in_array($w, $daysOfWeek, true)) {
                $stmt->execute([
                    ':date' => $curDate,
                    ':suite_id' => $suiteId,
                    ':note' => 'Nhân bản hàng loạt',
                ]);
                $appliedCount++;
            }

            $currentTs = strtotime('+1 day', $currentTs);
        }

        return $appliedCount;
    }

    /**
     * Lấy danh sách lịch phân bổ thực đơn trong khoảng ngày (cho Admin Calendar/Timeline)
     */
    public function getSchedulesBetween(string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare('
            SELECT sch.*, s.name as suite_name, s.is_default
            FROM menu_schedules sch
            JOIN menu_suites s ON sch.suite_id = s.id
            WHERE sch.schedule_date BETWEEN :start AND :end
            ORDER BY sch.schedule_date ASC
        ');
        $stmt->execute([
            ':start' => $startDate,
            ':end' => $endDate,
        ]);
        return $stmt->fetchAll() ?: [];
    }

    // ====================================================================
    // 4. KIỂM TRA ẢNH HƯỞNG & THÔNG BÁO KHÁCH KHI SỬA THỰC ĐƠN (Issue #17)
    // ====================================================================

    /**
     * Kiểm tra xem món ăn hiện tại đã có khách nào đặt trước trong các ca chưa hoàn tất
     */
    public function checkItemBookedImpact(int $itemId, ?string $fromDate = null): array
    {
        $fromDate = $fromDate ?? date('Y-m-d');

        $stmt = $this->db->prepare('
            SELECT o.id as order_id, o.order_code, o.customer_phone, o.order_type, o.payment_status,
                   c.name as customer_name, c.zalo_id,
                   ts.slot_date, ts.slot_time,
                   oi.quantity, oi.unit_price, oi.item_name
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN customers c ON o.customer_phone = c.phone
            JOIN time_slots ts ON o.slot_id = ts.id
            WHERE oi.menu_item_id = :item_id
              AND o.order_status = \'confirmed\'
              AND ts.slot_date >= :from_date
            ORDER BY ts.slot_date ASC, ts.slot_time ASC
        ');
        $stmt->execute([
            ':item_id' => $itemId,
            ':from_date' => $fromDate,
        ]);
        $orders = $stmt->fetchAll() ?: [];

        $totalQty = 0;
        foreach ($orders as $o) {
            $totalQty += (int)$o['quantity'];
        }

        $item = $this->getItemById($itemId);

        return [
            'item_id' => $itemId,
            'item' => $item,
            'orders_count' => count($orders),
            'total_qty' => $totalQty,
            'total_quantity' => $totalQty,
            'orders' => $orders,
            'affected_orders' => $orders,
        ];
    }

    /**
     * Gửi thông báo cho khách khi thực đơn có thay đổi (Issue #17)
     * Đưa vào Outbox & trả về mẫu tin nhắn Zalo gửi tay nếu cần
     */
    public function notifyCustomersOnItemChange(
        int $itemId,
        string $changeTitle,
        string $message,
        ?string $fromDate = null
    ): array {
        $impact = $this->checkItemBookedImpact($itemId, $fromDate);
        $item = $this->getItemById($itemId);
        $itemName = $item['name'] ?? 'Món ăn';

        $notifiedOrders = [];
        $zaloTemplates = [];

        foreach ($impact['orders'] as $ord) {
            $orderId = (int)$ord['order_id'];
            $orderCode = (string)$ord['order_code'];
            $custName = (string)$ord['customer_name'];
            $slotDate = (string)$ord['slot_date'];
            $slotTime = (string)$ord['slot_time'];
            $cleanPhone = preg_replace('/[^0-9]/', '', (string)$ord['customer_phone']);

            // Mẫu tin nhắn Zalo thân thương gửi khách
            $zaloText = sprintf(
                "Chào bạn %s, Bếp Chay An Yên xin phép nhắn bạn về đơn %s (Khung giờ %s ngày %s): %s. Bếp mong bạn thông cảm cho sự bất tiện này nhé! 🍃",
                $custName,
                $orderCode,
                $slotTime,
                date('d/m', strtotime($slotDate)),
                $message
            );

            $zaloTemplates[] = [
                'order_code' => $orderCode,
                'customer_name' => $custName,
                'customer_phone' => $ord['customer_phone'],
                'zalo_link' => 'https://zalo.me/' . $cleanPhone,
                'message_text' => $zaloText,
            ];

            // Nếu có NotificationService, đẩy sự kiện vào Outbox
            if ($this->notificationService !== null) {
                $eventKey = sprintf('menu_change:%d:%d:web', $itemId, $orderId);
                $this->notificationService->enqueue(
                    $eventKey,
                    'MENU_CHANGE_ALERT',
                    $orderId,
                    'web',
                    [
                        'item_id' => $itemId,
                        'item_name' => $itemName,
                        'change_title' => $changeTitle,
                        'message' => $message,
                        'order_code' => $orderCode,
                        'customer_name' => $custName,
                    ]
                );
            }

            $notifiedOrders[] = $orderCode;
        }

        return [
            'item_id' => $itemId,
            'item_name' => $itemName,
            'enqueued_count' => count($notifiedOrders),
            'affected_orders_count' => count($notifiedOrders),
            'notified_orders' => $notifiedOrders,
            'zalo_templates' => $zaloTemplates,
        ];
    }
}
