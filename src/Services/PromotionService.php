<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;
use InvalidArgumentException;

/**
 * PromotionService
 * 
 * Quản lý các chương trình Khuyến Mại & Giảm Giá linh hoạt theo thời gian, 
 * ngày Âm Lịch (Mùng 1 & Rằm), theo món ăn hoặc mâm cỗ chay (Issue #20).
 */
class PromotionService
{
    private PDO $db;
    private CustomerService $customerService;

    public function __construct(?PDO $db = null, mixed $customerService = null)
    {
        $this->db = $db ?? Connection::getInstance();
        if ($customerService instanceof CustomerService) {
            $this->customerService = $customerService;
        } else {
            $this->customerService = new CustomerService($this->db);
        }
    }

    /**
     * Lấy danh sách tất cả các chương trình khuyến mại
     */
    public function getPromotions(?bool $activeOnly = null): array
    {
        $sql = "SELECT * FROM promotions";
        if ($activeOnly !== null) {
            $sql .= " WHERE is_active = " . ($activeOnly ? '1' : '0');
        }
        $sql .= " ORDER BY created_at DESC, id DESC";

        $stmt = $this->db->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Lấy thông tin chi tiết khuyến mại theo ID
     */
    public function getPromotionById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM promotions WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Tạo mới chương trình khuyến mại
     */
    public function createPromotion(array $data): int
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Tên chương trình khuyến mại không được để trống.');
        }

        $discountType = (string)($data['discount_type'] ?? 'percentage');
        if (!in_array($discountType, ['percentage', 'fixed_amount'], true)) {
            $discountType = 'percentage';
        }

        $discountValue = (int)($data['discount_value'] ?? 0);
        if ($discountValue <= 0) {
            throw new InvalidArgumentException('Giá trị giảm giá phải lớn hơn 0.');
        }

        if ($discountType === 'percentage' && $discountValue > 100) {
            throw new InvalidArgumentException('Giảm giá phần trăm không được vượt quá 100%.');
        }

        $startDate = (string)($data['start_date'] ?? date('Y-m-d'));
        $endDate = (string)($data['end_date'] ?? date('Y-m-d', strtotime('+30 days')));

        $code = !empty($data['code']) ? trim((string)$data['code']) : null;
        $description = !empty($data['description']) ? trim((string)$data['description']) : null;
        $minOrderAmount = max(0, (int)($data['min_order_amount'] ?? 0));
        $appliesTo = (string)($data['applies_to'] ?? 'all');
        if (!in_array($appliesTo, ['all', 'category', 'item', 'feast_combo'], true)) {
            $appliesTo = 'all';
        }

        $targetId = !empty($data['target_id']) ? (int)$data['target_id'] : null;
        $targetCategory = !empty($data['target_category']) ? trim((string)$data['target_category']) : null;
        $applyOnLunarDays = !empty($data['apply_on_lunar_days']) ? trim((string)$data['apply_on_lunar_days']) : null;
        $badgeText = !empty($data['badge_text']) ? trim((string)$data['badge_text']) : null;
        if ($badgeText === null) {
            $badgeText = ($discountType === 'percentage') ? "🔥 Giảm {$discountValue}%" : "🔥 Giảm " . number_format($discountValue, 0, ',', '.') . "đ";
        }
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        $targetCustomerPhone = !empty($data['target_customer_phone']) ? trim((string)$data['target_customer_phone']) : null;
        $targetCustomerGroup = (string)($data['target_customer_group'] ?? 'all');
        if (!in_array($targetCustomerGroup, ['all', 'trusted', 'new', 'loyal'], true)) {
            $targetCustomerGroup = 'all';
        }
        $targetOrderType = (string)($data['target_order_type'] ?? 'all');
        if (!in_array($targetOrderType, ['all', 'dine_in', 'takeaway'], true)) {
            $targetOrderType = 'all';
        }
        $firstOrderOnly = isset($data['first_order_only']) ? (int)(bool)$data['first_order_only'] : 0;
        $targetOrderCode = !empty($data['target_order_code']) ? trim((string)$data['target_order_code']) : null;

        $stmt = $this->db->prepare("
            INSERT INTO promotions (
                code, title, description, discount_type, discount_value, min_order_amount,
                applies_to, target_id, target_category, start_date, end_date,
                apply_on_lunar_days, badge_text, target_customer_phone, target_customer_group,
                target_order_type, first_order_only, target_order_code, is_active
            ) VALUES (
                :code, :title, :description, :discount_type, :discount_value, :min_order_amount,
                :applies_to, :target_id, :target_category, :start_date, :end_date,
                :apply_on_lunar_days, :badge_text, :target_customer_phone, :target_customer_group,
                :target_order_type, :first_order_only, :target_order_code, :is_active
            )
        ");

        $stmt->bindValue(':code', $code, $code !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, $description !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':discount_type', $discountType, PDO::PARAM_STR);
        $stmt->bindValue(':discount_value', $discountValue, PDO::PARAM_INT);
        $stmt->bindValue(':min_order_amount', $minOrderAmount, PDO::PARAM_INT);
        $stmt->bindValue(':applies_to', $appliesTo, PDO::PARAM_STR);
        $stmt->bindValue(':target_id', $targetId, $targetId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':target_category', $targetCategory, $targetCategory !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':end_date', $endDate, PDO::PARAM_STR);
        $stmt->bindValue(':apply_on_lunar_days', $applyOnLunarDays, $applyOnLunarDays !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':badge_text', $badgeText, PDO::PARAM_STR);
        $stmt->bindValue(':target_customer_phone', $targetCustomerPhone, $targetCustomerPhone !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':target_customer_group', $targetCustomerGroup, PDO::PARAM_STR);
        $stmt->bindValue(':target_order_type', $targetOrderType, PDO::PARAM_STR);
        $stmt->bindValue(':first_order_only', $firstOrderOnly, PDO::PARAM_INT);
        $stmt->bindValue(':target_order_code', $targetOrderCode, $targetOrderCode !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':is_active', $isActive, PDO::PARAM_INT);

        $stmt->execute();
        return (int)$this->db->lastInsertId();
    }

    /**
     * Cập nhật chương trình khuyến mại
     */
    public function updatePromotion(int $id, array $data): bool
    {
        $existing = $this->getPromotionById($id);
        if (!$existing) {
            return false;
        }

        $title = trim((string)($data['title'] ?? $existing['title']));
        if ($title === '') {
            throw new InvalidArgumentException('Tên chương trình khuyến mại không được để trống.');
        }

        $discountType = (string)($data['discount_type'] ?? $existing['discount_type']);
        $discountValue = (int)($data['discount_value'] ?? $existing['discount_value']);
        if ($discountValue <= 0) {
            throw new InvalidArgumentException('Giá trị giảm giá phải lớn hơn 0.');
        }

        $startDate = (string)($data['start_date'] ?? $existing['start_date']);
        $endDate = (string)($data['end_date'] ?? $existing['end_date']);
        $code = isset($data['code']) ? (!empty($data['code']) ? trim((string)$data['code']) : null) : $existing['code'];
        $description = isset($data['description']) ? (!empty($data['description']) ? trim((string)$data['description']) : null) : $existing['description'];
        $minOrderAmount = max(0, (int)($data['min_order_amount'] ?? $existing['min_order_amount']));
        $appliesTo = (string)($data['applies_to'] ?? $existing['applies_to']);
        $targetId = isset($data['target_id']) ? (!empty($data['target_id']) ? (int)$data['target_id'] : null) : $existing['target_id'];
        $targetCategory = isset($data['target_category']) ? (!empty($data['target_category']) ? trim((string)$data['target_category']) : null) : $existing['target_category'];
        $applyOnLunarDays = isset($data['apply_on_lunar_days']) ? (!empty($data['apply_on_lunar_days']) ? trim((string)$data['apply_on_lunar_days']) : null) : $existing['apply_on_lunar_days'];
        $badgeText = isset($data['badge_text']) ? (!empty($data['badge_text']) ? trim((string)$data['badge_text']) : null) : $existing['badge_text'];
        $targetCustomerPhone = isset($data['target_customer_phone']) ? (!empty($data['target_customer_phone']) ? trim((string)$data['target_customer_phone']) : null) : ($existing['target_customer_phone'] ?? null);
        $targetCustomerGroup = (string)($data['target_customer_group'] ?? ($existing['target_customer_group'] ?? 'all'));
        if (!in_array($targetCustomerGroup, ['all', 'trusted', 'new', 'loyal'], true)) {
            $targetCustomerGroup = 'all';
        }
        $targetOrderType = (string)($data['target_order_type'] ?? ($existing['target_order_type'] ?? 'all'));
        if (!in_array($targetOrderType, ['all', 'dine_in', 'takeaway'], true)) {
            $targetOrderType = 'all';
        }
        $firstOrderOnly = isset($data['first_order_only']) ? (int)(bool)$data['first_order_only'] : (int)($existing['first_order_only'] ?? 0);
        $targetOrderCode = isset($data['target_order_code']) ? (!empty($data['target_order_code']) ? trim((string)$data['target_order_code']) : null) : ($existing['target_order_code'] ?? null);
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : (int)$existing['is_active'];

        $stmt = $this->db->prepare("
            UPDATE promotions SET
                code = :code,
                title = :title,
                description = :description,
                discount_type = :discount_type,
                discount_value = :discount_value,
                min_order_amount = :min_order_amount,
                applies_to = :applies_to,
                target_id = :target_id,
                target_category = :target_category,
                start_date = :start_date,
                end_date = :end_date,
                apply_on_lunar_days = :apply_on_lunar_days,
                badge_text = :badge_text,
                target_customer_phone = :target_customer_phone,
                target_customer_group = :target_customer_group,
                target_order_type = :target_order_type,
                first_order_only = :first_order_only,
                target_order_code = :target_order_code,
                is_active = :is_active
            WHERE id = :id
        ");

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':code', $code, $code !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, $description !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':discount_type', $discountType, PDO::PARAM_STR);
        $stmt->bindValue(':discount_value', $discountValue, PDO::PARAM_INT);
        $stmt->bindValue(':min_order_amount', $minOrderAmount, PDO::PARAM_INT);
        $stmt->bindValue(':applies_to', $appliesTo, PDO::PARAM_STR);
        $stmt->bindValue(':target_id', $targetId, $targetId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':target_category', $targetCategory, $targetCategory !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':end_date', $endDate, PDO::PARAM_STR);
        $stmt->bindValue(':apply_on_lunar_days', $applyOnLunarDays, $applyOnLunarDays !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':badge_text', $badgeText, PDO::PARAM_STR);
        $stmt->bindValue(':target_customer_phone', $targetCustomerPhone, $targetCustomerPhone !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':target_customer_group', $targetCustomerGroup, PDO::PARAM_STR);
        $stmt->bindValue(':target_order_type', $targetOrderType, PDO::PARAM_STR);
        $stmt->bindValue(':first_order_only', $firstOrderOnly, PDO::PARAM_INT);
        $stmt->bindValue(':target_order_code', $targetOrderCode, $targetOrderCode !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':is_active', $isActive, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Bật/Tắt trạng thái hoạt động của khuyến mại
     */
    public function togglePromotionStatus(int $id, bool $isActive): bool
    {
        $stmt = $this->db->prepare("UPDATE promotions SET is_active = :active WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':active', $isActive ? 1 : 0, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Xóa chương trình khuyến mại
     */
    public function deletePromotion(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM promotions WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Lấy các chương trình khuyến mại có hiệu lực cho một ngày cụ thể (Dương lịch & Âm lịch)
     */
    public function getActivePromotionsForDate(string $dateString): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM promotions 
            WHERE is_active = 1 
              AND :date BETWEEN start_date AND end_date
            ORDER BY discount_value DESC, id ASC
        ");
        $stmt->bindValue(':date', $dateString, PDO::PARAM_STR);
        $stmt->execute();
        $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($promotions)) {
            return [];
        }

        // Lấy ngày âm lịch để kiểm tra điều kiện Mùng 1 & Rằm
        $lunarInfo = LunarCalendarService::getLunarInfoForDate($dateString);
        $lunarDay = $lunarInfo['lunar_day'];

        $validPromotions = [];
        foreach ($promotions as $promo) {
            $lunarDaysStr = $promo['apply_on_lunar_days'];
            if (!empty($lunarDaysStr)) {
                $allowedDays = array_map('intval', explode(',', $lunarDaysStr));
                if (!in_array($lunarDay, $allowedDays, true)) {
                    continue; // Bỏ qua nếu không đúng ngày âm lịch chỉ định
                }
            }
            $validPromotions[] = $promo;
        }

        return $validPromotions;
    }

    /**
     * Tính toán giá khuyến mại và gán badge cho danh sách món ăn trên giao diện public
     */
    public function enrichMenuItemsWithPromotions(string $dateString, array $menuItems): array
    {
        $activePromos = $this->getActivePromotionsForDate($dateString);
        if (empty($activePromos)) {
            return array_map(function ($item) {
                $item['original_price'] = (int)$item['price'];
                $item['final_price'] = (int)$item['price'];
                $item['has_promo'] = false;
                $item['promo_badge'] = null;
                return $item;
            }, $menuItems);
        }

        $enriched = [];
        foreach ($menuItems as $item) {
            $itemId = (int)$item['id'];
            $price = (int)$item['price'];
            $category = (string)($item['category'] ?? 'food');
            $isCombo = !empty($item['is_feast_combo']);

            $bestDiscount = 0;
            $bestBadge = null;

            foreach ($activePromos as $promo) {
                $appliesTo = $promo['applies_to'];
                $isMatch = false;

                if ($appliesTo === 'all') {
                    $isMatch = true;
                } elseif ($appliesTo === 'category' && $promo['target_category'] === $category) {
                    $isMatch = true;
                } elseif ($appliesTo === 'item' && (int)$promo['target_id'] === $itemId) {
                    $isMatch = true;
                } elseif ($appliesTo === 'feast_combo' && $isCombo) {
                    $isMatch = true;
                }

                if ($isMatch) {
                    $discount = 0;
                    if ($promo['discount_type'] === 'percentage') {
                        $discount = (int)round(($price * (int)$promo['discount_value']) / 100);
                    } else {
                        $discount = min($price, (int)$promo['discount_value']);
                    }

                    if ($discount > $bestDiscount) {
                        $bestDiscount = $discount;
                        $bestBadge = $promo['badge_text'] ?: "🔥 Giảm " . ($promo['discount_type'] === 'percentage' ? "{$promo['discount_value']}%" : number_format($promo['discount_value'], 0, ',', '.') . "đ");
                    }
                }
            }

            $finalPrice = max(0, $price - $bestDiscount);
            $item['original_price'] = $price;
            $item['final_price'] = $finalPrice;
            $item['discount_amount'] = $bestDiscount;
            $item['has_promo'] = ($bestDiscount > 0);
            $item['promo_badge'] = $bestBadge;

            $enriched[] = $item;
        }

        return $enriched;
    }

    /**
     * Đếm số đơn đã thanh toán hoặc hoàn thành của một số điện thoại
     */
    public function getCompletedOrderCountByPhone(string $phone): int
    {
        $cleanPhone = trim($phone);
        if ($cleanPhone === '') {
            return 0;
        }
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM orders 
            WHERE customer_phone = :phone 
              AND payment_status IN ('paid', 'completed')
        ");
        $stmt->bindValue(':phone', $cleanPhone, PDO::PARAM_STR);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Đếm tổng số đơn của một số điện thoại (trừ đơn huỷ)
     */
    public function getTotalOrderCountByPhone(string $phone): int
    {
        $cleanPhone = trim($phone);
        if ($cleanPhone === '') {
            return 0;
        }
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM orders 
            WHERE customer_phone = :phone 
              AND payment_status != 'cancelled'
        ");
        $stmt->bindValue(':phone', $cleanPhone, PDO::PARAM_STR);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Kiểm tra khách hàng có thuộc nhóm khách mục tiêu hay không
     */
    public function isCustomerEligibleForGroup(string $phone, string $group): bool
    {
        if ($group === 'all' || empty($group)) {
            return true;
        }

        $cleanPhone = trim($phone);
        if ($cleanPhone === '') {
            return false;
        }

        $customer = $this->customerService ? $this->customerService->findByPhone($cleanPhone) : null;
        $isTrusted = !empty($customer['is_trusted']);
        $completedOrders = $this->getCompletedOrderCountByPhone($cleanPhone);
        $totalOrders = $this->getTotalOrderCountByPhone($cleanPhone);

        return match ($group) {
            'trusted' => $isTrusted,
            'new' => !$isTrusted && ($totalOrders === 0),
            'loyal' => $isTrusted || ($completedOrders >= 3),
            default => true,
        };
    }

    /**
     * Tính toán tổng tiền chiết khấu cho đơn hàng (Order Calculation)
     * 
     * @param string $dateString Ngày nhận món
     * @param array $itemsWithDetails Danh sách món trong giỏ kèm giá: [['item' => $itemData, 'quantity' => $qty]]
     * @param int $subtotal Tổng tiền món trước giảm
     * @param array|null $context Thông tin ngữ cảnh [customer_phone, order_type, order_code, coupon_code]
     * @return array [discount_amount, final_total, primary_promotion]
     */
    public function calculateOrderDiscount(string $dateString, array $itemsWithDetails, int $subtotal, ?array $context = null): array
    {
        $activePromos = $this->getActivePromotionsForDate($dateString);
        if (empty($activePromos) || $subtotal <= 0) {
            return [
                'discount_amount' => 0,
                'final_total' => $subtotal,
                'promotion_id' => null,
                'promotion_title' => null,
            ];
        }

        $customerPhone = trim((string)($context['customer_phone'] ?? ''));
        $orderType = (string)($context['order_type'] ?? 'all');
        $orderCode = trim((string)($context['order_code'] ?? ''));

        $maxDiscount = 0;
        $appliedPromo = null;

        foreach ($activePromos as $promo) {
            // 1. Kiểm tra giới hạn theo từng khách hàng cụ thể
            $targetPhone = trim((string)($promo['target_customer_phone'] ?? ''));
            if ($targetPhone !== '') {
                if ($customerPhone === '' || $targetPhone !== $customerPhone) {
                    continue;
                }
            }

            // 2. Kiểm tra nhóm khách hàng (all, trusted, new, loyal)
            $targetGroup = (string)($promo['target_customer_group'] ?? 'all');
            if ($targetGroup !== 'all' && $targetGroup !== '') {
                if (!$this->isCustomerEligibleForGroup($customerPhone, $targetGroup)) {
                    continue;
                }
            }

            // 3. Kiểm tra nhóm đơn hàng (all, dine_in, takeaway)
            $targetOrderType = (string)($promo['target_order_type'] ?? 'all');
            if ($targetOrderType !== 'all' && $targetOrderType !== '') {
                if ($orderType !== $targetOrderType) {
                    continue;
                }
            }

            // 4. Kiểm tra chỉ đơn đầu tiên
            if (!empty($promo['first_order_only'])) {
                if ($customerPhone === '' || $this->getTotalOrderCountByPhone($customerPhone) > 0) {
                    continue;
                }
            }

            // 5. Kiểm tra theo mã đơn cụ thể (nếu có chỉ định)
            $targetOrderCode = trim((string)($promo['target_order_code'] ?? ''));
            if ($targetOrderCode !== '') {
                if ($orderCode === '' || $targetOrderCode !== $orderCode) {
                    continue;
                }
            }

            $minOrder = (int)$promo['min_order_amount'];
            if ($subtotal < $minOrder) {
                continue; // Chưa đạt giá trị đơn tối thiểu
            }

            $discountForThisPromo = 0;
            $appliesTo = $promo['applies_to'];

            if ($appliesTo === 'all') {
                if ($promo['discount_type'] === 'percentage') {
                    $discountForThisPromo = (int)round(($subtotal * (int)$promo['discount_value']) / 100);
                } else {
                    $discountForThisPromo = min($subtotal, (int)$promo['discount_value']);
                }
            } elseif ($appliesTo === 'category') {
                $targetCat = $promo['target_category'];
                $eligibleSubtotal = 0;
                foreach ($itemsWithDetails as $line) {
                    if (($line['item']['category'] ?? '') === $targetCat) {
                        $eligibleSubtotal += ((int)$line['item']['price']) * ((int)$line['quantity']);
                    }
                }
                if ($eligibleSubtotal > 0) {
                    if ($promo['discount_type'] === 'percentage') {
                        $discountForThisPromo = (int)round(($eligibleSubtotal * (int)$promo['discount_value']) / 100);
                    } else {
                        $discountForThisPromo = min($eligibleSubtotal, (int)$promo['discount_value']);
                    }
                }
            } elseif ($appliesTo === 'item') {
                $targetItemId = (int)$promo['target_id'];
                $eligibleSubtotal = 0;
                foreach ($itemsWithDetails as $line) {
                    if ((int)$line['item']['id'] === $targetItemId) {
                        $eligibleSubtotal += ((int)$line['item']['price']) * ((int)$line['quantity']);
                    }
                }
                if ($eligibleSubtotal > 0) {
                    if ($promo['discount_type'] === 'percentage') {
                        $discountForThisPromo = (int)round(($eligibleSubtotal * (int)$promo['discount_value']) / 100);
                    } else {
                        $discountForThisPromo = min($eligibleSubtotal, (int)$promo['discount_value']);
                    }
                }
            } elseif ($appliesTo === 'feast_combo') {
                $eligibleSubtotal = 0;
                foreach ($itemsWithDetails as $line) {
                    if (!empty($line['item']['is_feast_combo'])) {
                        $eligibleSubtotal += ((int)$line['item']['price']) * ((int)$line['quantity']);
                    }
                }
                if ($eligibleSubtotal > 0) {
                    if ($promo['discount_type'] === 'percentage') {
                        $discountForThisPromo = (int)round(($eligibleSubtotal * (int)$promo['discount_value']) / 100);
                    } else {
                        $discountForThisPromo = min($eligibleSubtotal, (int)$promo['discount_value']);
                    }
                }
            }

            if ($discountForThisPromo > $maxDiscount) {
                $maxDiscount = $discountForThisPromo;
                $appliedPromo = $promo;
            }
        }

        $finalDiscount = min($subtotal, $maxDiscount);
        $finalTotal = max(0, $subtotal - $finalDiscount);

        return [
            'discount_amount' => $finalDiscount,
            'final_total' => $finalTotal,
            'promotion_id' => $appliedPromo ? (int)$appliedPromo['id'] : null,
            'promotion_title' => $appliedPromo ? (string)$appliedPromo['title'] : null,
        ];
    }
}

