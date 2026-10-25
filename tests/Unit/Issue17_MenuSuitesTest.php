<?php
declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\MenuService;
use App\Services\ConfigService;
use App\Services\NotificationService;
use App\Services\SlotService;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Services\ZaloAuthService;

class Issue17_MenuSuitesTest extends TestCase
{
    private MenuService $menuService;
    private ConfigService $configService;
    private NotificationService $notificationService;
    private OrderService $orderService;
    private SlotService $slotService;

    public function runAllTests(): void
    {
        $this->setUp();

        $this->configService = new ConfigService($this->db);
        $this->notificationService = new NotificationService($this->db);
        $customerService = new CustomerService($this->db);
        $this->slotService = new SlotService($this->db, $this->configService);
        $this->menuService = new MenuService($this->db, $this->configService, $this->notificationService);

        $zaloService = new ZaloAuthService($this->db, $this->configService, $customerService, [
            'app_id' => '123',
            'secret_key' => '123',
            'mock_test_mode' => true,
            'require_zalo_login' => false,
        ]);

        $this->orderService = new OrderService(
            $this->db,
            $customerService,
            $this->slotService,
            $this->configService,
            $this->notificationService,
            $zaloService
        );

        $this->testItemCreationWithServiceScopeAndFeastCombo();
        $this->testMenuSuitesManagement();
        $this->testMenuSchedulingAndRules();
        $this->testMenuFilteringByServiceScope();
        $this->testItemImpactCheckAndCustomerNotification();

        $this->tearDown();
    }

    private function testItemCreationWithServiceScopeAndFeastCombo(): void
    {
        // Tạo món chỉ dùng tại quán
        $id1 = $this->menuService->createItem(
            'Lẩu Nấm An Nhiên',
            'Phục vụ tại bàn với nước lẩu riêu cua chay',
            180000,
            'food',
            'dine_in_only',
            0
        );
        $this->assert($id1 > 0, 'Phải tạo được món dine_in_only');

        // Tạo mâm cỗ chay trọn vị chỉ mang về
        $id2 = $this->menuService->createItem(
            'Mâm Cỗ Chay Tinh Hoa (7 món)',
            'Giao tận nơi đóng hộp giấy Kraft mộc mạc',
            650000,
            'food',
            'takeaway_only',
            1
        );
        $this->assert($id2 > 0, 'Phải tạo được mâm cỗ chay takeaway_only');

        $item1 = $this->menuService->getItemById($id1);
        $this->assert($item1['service_scope'] === 'dine_in_only', 'service_scope phải là dine_in_only');
        $this->assert((int)$item1['is_feast_combo'] === 0, 'is_feast_combo phải là 0');

        $item2 = $this->menuService->getItemById($id2);
        $this->assert($item2['service_scope'] === 'takeaway_only', 'service_scope phải là takeaway_only');
        $this->assert((int)$item2['is_feast_combo'] === 1, 'is_feast_combo phải là 1');
    }

    private function testMenuSuitesManagement(): void
    {
        // 1. Tạo suite mới
        $suiteId = $this->menuService->createSuite(
            'Thực Đơn Mùa Vu Lan',
            'Các món thanh tịnh dâng cúng tổ tiên',
            0,
            [1, 2] // Giả định ID 1, 2 từ seed
        );
        $this->assert($suiteId > 0, 'Phải tạo được menu suite mới');

        $suite = $this->menuService->getSuiteById($suiteId);
        $this->assert($suite['name'] === 'Thực Đơn Mùa Vu Lan', 'Tên suite phải khớp');
        $this->assert((int)$suite['is_default'] === 0, 'is_default ban đầu là 0');

        $items = $this->menuService->getSuiteItems($suiteId);
        $this->assert(count($items) === 2, 'Suite phải chứa 2 món ăn');

        // 2. Cập nhật suite thành mặc định
        $this->menuService->updateSuite($suiteId, 'Thực Đơn Mùa Vu Lan 2026', 'Mô tả cập nhật', 1, [1]);
        $updatedSuite = $this->menuService->getSuiteById($suiteId);
        $this->assert($updatedSuite['name'] === 'Thực Đơn Mùa Vu Lan 2026', 'Tên suite phải được cập nhật');
        $this->assert((int)$updatedSuite['is_default'] === 1, 'Suite phải trở thành mặc định');

        $updatedItems = $this->menuService->getSuiteItems($suiteId);
        $this->assert(count($updatedItems) === 1, 'Suite cập nhật phải chỉ còn 1 món');
    }

    private function testMenuSchedulingAndRules(): void
    {
        // Tạo 2 suite riêng biệt
        $evenSuiteId = $this->menuService->createSuite('Suite Ngày Chẵn', 'T2, T4, T6', 0, [1]);
        $oddSuiteId = $this->menuService->createSuite('Suite Ngày Lẻ', 'T3, T5, T7, CN', 0, [2]);
        $feastSuiteId = $this->menuService->createSuite('Suite Mâm Cỗ Rằm', 'Chỉ riêng ngày Rằm', 0, [1, 2, 3]);

        // Cài đặt quy tắc xoay tua Chẵn - Lẻ
        $this->configService->set('menu_schedule_rule', 'even_odd');
        $this->configService->set('menu_even_suite_id', (string)$evenSuiteId);
        $this->configService->set('menu_odd_suite_id', (string)$oddSuiteId);

        // Ngày 2026-10-26 là Thứ Hai (Monday = ngày chẵn 2)
        $mondaySuite = $this->menuService->getScheduledSuiteForDate('2026-10-26');
        $this->assert((int)$mondaySuite['id'] === $evenSuiteId, 'Thứ Hai phải áp dụng Suite Ngày Chẵn');

        // Ngày 2026-10-27 là Thứ Ba (Tuesday = ngày lẻ 3)
        $tuesdaySuite = $this->menuService->getScheduledSuiteForDate('2026-10-27');
        $this->assert((int)$tuesdaySuite['id'] === $oddSuiteId, 'Thứ Ba phải áp dụng Suite Ngày Lẻ');

        // Chỉ định riêng ngày 2026-10-26 bằng Suite Mâm Cỗ Rằm (Override)
        $this->menuService->assignSuiteToDate('2026-10-26', $feastSuiteId, 'Rằm tháng 9 Âm lịch');
        $overrideSuite = $this->menuService->getScheduledSuiteForDate('2026-10-26');
        $this->assert((int)$overrideSuite['id'] === $feastSuiteId, 'Ngày có lịch riêng phải ghi đè quy tắc chẵn lẻ');

        // Xóa lịch riêng, quay lại quy tắc chẵn lẻ
        $this->menuService->removeScheduleForDate('2026-10-26');
        $revertedSuite = $this->menuService->getScheduledSuiteForDate('2026-10-26');
        $this->assert((int)$revertedSuite['id'] === $evenSuiteId, 'Sau khi hủy chỉ định, phải phục hồi về Suite Ngày Chẵn');

        // Thử nghiệm nhân bản hàng loạt (Replicate Schedule)
        $count = $this->menuService->replicateSchedule($feastSuiteId, '2026-11-01', '2026-11-07', [0, 6]); // Chỉ T7, CN
        $this->assert($count === 2, 'Trong khoảng 1 tuần chỉ có 2 ngày T7 và CN được nhân bản');
    }

    private function testMenuFilteringByServiceScope(): void
    {
        // Tạo suite gồm cả 3 loại: both, dine_in_only, takeaway_only
        $itemIdBoth = $this->menuService->createItem('Món Cả Hai', '', 30000, 'food', 'both', 0);
        $itemIdDine = $this->menuService->createItem('Món Tại Quán', '', 40000, 'food', 'dine_in_only', 0);
        $itemIdShip = $this->menuService->createItem('Món Mang Về', '', 50000, 'food', 'takeaway_only', 0);

        $suiteId = $this->menuService->createSuite('Suite Đa Dạng', '', 0, [$itemIdBoth, $itemIdDine, $itemIdShip]);
        $this->menuService->assignSuiteToDate('2026-12-01', $suiteId, 'Test');

        // Khách chọn Ăn Tại Quán (dine_in) -> Phải thấy món 'both' và 'dine_in_only', KHÔNG thấy 'takeaway_only'
        $dineInItems = $this->menuService->getMenuItemsForDate('2026-12-01', 'dine_in');
        $dineInIds = array_column($dineInItems, 'id');
        $this->assert(in_array($itemIdBoth, $dineInIds, true), 'Khách ăn tại quán phải thấy món both');
        $this->assert(in_array($itemIdDine, $dineInIds, true), 'Khách ăn tại quán phải thấy món dine_in_only');
        $this->assert(!in_array($itemIdShip, $dineInIds, true), 'Khách ăn tại quán KHÔNG được thấy món takeaway_only');

        // Khách chọn Mang Về (takeaway) -> Phải thấy món 'both' và 'takeaway_only', KHÔNG thấy 'dine_in_only'
        $takeawayItems = $this->menuService->getMenuItemsForDate('2026-12-01', 'takeaway');
        $takeawayIds = array_column($takeawayItems, 'id');
        $this->assert(in_array($itemIdBoth, $takeawayIds, true), 'Khách mang về phải thấy món both');
        $this->assert(in_array($itemIdShip, $takeawayIds, true), 'Khách mang về phải thấy món takeaway_only');
        $this->assert(!in_array($itemIdDine, $takeawayIds, true), 'Khách mang về KHÔNG được thấy món dine_in_only');
    }

    private function testItemImpactCheckAndCustomerNotification(): void
    {
        $futureDate = date('Y-m-d', strtotime('+3 days'));

        // 1. Tạo slot trong tương lai
        $slotId = $this->slotService->createSlot($futureDate, '11:30 - 12:15', 20);

        // 2. Tạo món đặc biệt
        $specialItemId = $this->menuService->createItem('Lẩu Nấm Trâm Vàng', '', 120000, 'food', 'both', 0);

        // 3. Khách quen đặt đơn có chứa món này
        $order = $this->orderService->createOrder([
            'customer_name' => 'Chị Lan',
            'customer_phone' => '0912345678',
            'slot_id' => $slotId,
            'order_type' => 'dine_in',
            'payment_method' => 'pay_later',
            'items' => [
                $specialItemId => 3,
            ],
        ]);
        $this->assert(!empty($order['id']), 'Tạo đơn thành công');

        // 4. Kiểm tra ảnh hưởng (Impact check)
        $impact = $this->menuService->checkItemBookedImpact($specialItemId, date('Y-m-d'));
        $this->assert($impact['item']['name'] === 'Lẩu Nấm Trâm Vàng', 'Tên món ảnh hưởng phải khớp');
        $this->assert(count($impact['affected_orders']) === 1, 'Phải phát hiện chính xác 1 đơn đặt trước bị ảnh hưởng');
        $this->assert((int)$impact['total_quantity'] === 3, 'Tổng số lượng suất đặt trước phải là 3');

        // 5. Thử xóa món -> Phải bị từ chối do có ràng buộc đơn đặt
        $deleted = $this->menuService->deleteItem($specialItemId);
        $this->assert($deleted === false, 'Không được xóa món khi đã có trong đơn đặt của khách');

        // 6. Gửi thông báo thay đổi món cho khách vào hàng đợi Outbox
        $notifyResult = $this->menuService->notifyCustomersOnItemChange(
            $specialItemId,
            'Hết nấm trâm vàng, bếp đổi nấm đùi gà',
            'Bếp xin phép đổi nấm đùi gà tươi ngon hoặc bù tiền cho bạn nhé!',
            date('Y-m-d')
        );

        $this->assert($notifyResult['enqueued_count'] === 1, 'Phải xếp lịch thành công cho 1 khách hàng');
        $this->assert(!empty($notifyResult['zalo_templates']), 'Phải sinh ra mẫu Zalo nhắn gửi trực tiếp');
        $this->assert(str_contains($notifyResult['zalo_templates'][0]['zalo_link'], 'zalo.me/0912345678'), 'Link Zalo phải đúng số điện thoại khách');

        // 7. Kiểm tra trong Outbox
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM notification_outbox 
            WHERE event_type = 'MENU_CHANGE_ALERT' AND order_id = :order_id
        ");
        $stmt->execute([':order_id' => $order['id']]);
        $outboxCount = (int)$stmt->fetchColumn();
        $this->assert($outboxCount === 1, 'Phải có đúng 1 bản ghi MENU_CHANGE_ALERT trong notification_outbox');
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException("Kiểm thử thất bại: {$message}");
        }
    }
}
