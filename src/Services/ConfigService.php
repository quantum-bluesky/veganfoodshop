<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Database\Connection;

/**
 * Service Quản Lý Cấu Hình Hệ Thống (ConfigService)
 * Hỗ trợ 3 tầng phân giải cấu hình linh hoạt (Issue #34 & Issue #35):
 * 1. Tầng 1: Cơ sở dữ liệu SQLite (bảng system_config) - Cho phép Admin chỉnh sửa trực tiếp.
 * 2. Tầng 2: Tệp cấu hình config/config.local.php & config/config.php - Cho phép cấu hình qua file code.
 * 3. Tầng 3: Giá trị mặc định (Fallback).
 */
class ConfigService
{
    private ?array $fileConfig = null;

    public function __construct(
        private ?PDO $db = null,
        ?array $fileConfig = null
    ) {
        $this->db = $db ?? Connection::getInstance();
        if ($fileConfig !== null) {
            $this->fileConfig = $fileConfig;
        } else {
            $configPath = __DIR__ . '/../../config/config.php';
            if (file_exists($configPath)) {
                $loaded = require $configPath;
                if (is_array($loaded)) {
                    $this->fileConfig = $loaded;
                }
            }
        }
    }

    /**
     * Lấy giá trị cấu hình theo key với cơ chế fallback 3 tầng
     */
    public function get(string $key, string $default = ''): string
    {
        // 1. Tầng 1: Tra cứu trong CSDL SQLite
        try {
            $stmt = $this->db->prepare('SELECT config_value FROM system_config WHERE config_key = :key LIMIT 1');
            $stmt->execute([':key' => $key]);
            $val = $stmt->fetchColumn();
            if ($val !== false && trim((string)$val) !== '') {
                return (string)$val;
            }
        } catch (\Throwable $e) {
            // Trường hợp DB chưa sẵn sàng hoặc bảng chưa tạo
        }

        // 2. Tầng 2: Tra cứu trong tệp cấu hình config.php / config.local.php
        $fileVal = $this->getFromConfigFile($key);
        if ($fileVal !== null && trim((string)$fileVal) !== '') {
            return (string)$fileVal;
        }

        // 3. Tầng 3: Trả về giá trị mặc định truyền vào
        return $default;
    }

    /**
     * Tra cứu giá trị từ mảng cấu hình tệp tin (hỗ trợ alias và dot-notation)
     */
    public function getFromConfigFile(string $key): ?string
    {
        if (empty($this->fileConfig)) {
            return null;
        }

        // 1. Danh sách alias ánh xạ các key thường dùng giữa DB và tệp config
        $aliasMap = [
            'seller_phone' => ['seller_phone', 'zalo_login.seller_phone', 'app.phone', 'phone'],
            'seller_zalo_url' => ['seller_zalo_url', 'zalo_seller_url', 'zalo_login.seller_zalo_url', 'zalo_login.zalo_seller_url'],
            'shop_name' => ['shop_name', 'app.name', 'brand_name', 'name'],
            'brand_name' => ['brand_name', 'shop_name', 'app.name', 'name'],
            'shop_address' => ['shop_address', 'seller_address', 'app.address', 'address'],
            'seller_address' => ['seller_address', 'shop_address', 'app.address', 'address'],
            'seller_email' => ['seller_email', 'shop_email', 'app.email', 'email', 'notification_email_to'],
            'shop_email' => ['shop_email', 'seller_email', 'app.email', 'email', 'notification_email_to'],
            'shop_quote' => ['shop_quote', 'app.quote', 'quote'],
            'min_lead_time_hours' => ['min_lead_time_hours', 'booking.min_lead_time_hours'],
            'advance_booking_days' => ['advance_booking_days', 'booking.advance_booking_days'],
            'order_note_shipping' => ['order_note_shipping', 'zalo_login.order_note_shipping'],
            'zalo_confirm_template' => ['zalo_confirm_template', 'zalo_login.zalo_confirm_template'],
            'zalo_app_id' => ['zalo_app_id', 'zalo_login.app_id'],
            'zalo_secret_key' => ['zalo_secret_key', 'zalo_login.secret_key'],
            'zalo_mock_test_mode' => ['zalo_mock_test_mode', 'zalo_login.mock_test_mode'],
            'zalo_require_login' => ['zalo_require_login', 'zalo_login.require_zalo_login'],
            'zalo_require_group' => ['zalo_require_group', 'zalo_login.require_zalo_group_to_order'],
            'zalo_callback_url' => ['zalo_callback_url', 'zalo_login.callback_url'],
        ];

        $keysToTry = $aliasMap[$key] ?? [$key];

        foreach ($keysToTry as $k) {
            $val = $this->extractByDotPath($this->fileConfig, $k);
            if ($val !== null && trim((string)$val) !== '') {
                return (string)$val;
            }
        }

        // Tìm kiếm đệ quy vét cạn theo tên key đơn
        $searchKey = $key;
        if (str_contains($key, '.')) {
            $parts = explode('.', $key);
            $searchKey = end($parts);
        }
        $deepVal = $this->findKeyRecursive($this->fileConfig, $searchKey);
        if ($deepVal !== null && trim((string)$deepVal) !== '') {
            return (string)$deepVal;
        }

        return null;
    }

    /**
     * Trích xuất giá trị theo đường dẫn phân tách dấu chấm (dot notation)
     */
    private function extractByDotPath(array $array, string $path): ?string
    {
        if (array_key_exists($path, $array) && !is_array($array[$path])) {
            if (is_bool($array[$path])) {
                return $array[$path] ? '1' : '0';
            }
            return (string)$array[$path];
        }

        $segments = explode('.', $path);
        $curr = $array;
        foreach ($segments as $seg) {
            if (!is_array($curr) || !array_key_exists($seg, $curr)) {
                return null;
            }
            $curr = $curr[$seg];
        }

        if (is_bool($curr)) {
            return $curr ? '1' : '0';
        }

        if (is_scalar($curr)) {
            return (string)$curr;
        }

        return null;
    }

    /**
     * Tìm kiếm đệ quy giá trị theo tên key trong mảng lồng nhau
     */
    private function findKeyRecursive(array $array, string $key): ?string
    {
        if (array_key_exists($key, $array) && !is_array($array[$key])) {
            if (is_bool($array[$key])) {
                return $array[$key] ? '1' : '0';
            }
            if (is_scalar($array[$key])) {
                return (string)$array[$key];
            }
        }

        foreach ($array as $subKey => $val) {
            if (is_array($val)) {
                $res = $this->findKeyRecursive($val, $key);
                if ($res !== null) {
                    return $res;
                }
            }
        }

        return null;
    }

    public function getAll(): array
    {
        $result = [];

        // 1. Nạp các giá trị cơ bản từ file cấu hình làm mặc định nền tảng
        if (!empty($this->fileConfig)) {
            $commonKeys = [
                'shop_name',
                'seller_phone',
                'seller_email',
                'shop_address',
                'shop_quote',
                'seller_zalo_url',
                'order_note_shipping',
                'zalo_confirm_template',
                'min_lead_time_hours',
                'advance_booking_days',
                'zalo_app_id',
                'zalo_secret_key',
                'zalo_mock_test_mode',
                'zalo_require_login',
                'zalo_require_group',
                'zalo_callback_url',
            ];
            foreach ($commonKeys as $k) {
                $v = $this->getFromConfigFile($k);
                if ($v !== null && trim((string)$v) !== '') {
                    $result[$k] = (string)$v;
                }
            }
        }

        // 2. Tra cứu CSDL SQLite (các giá trị trong CSDL sẽ ghi đè lên file cấu hình)
        try {
            $stmt = $this->db->query('SELECT config_key, config_value, description FROM system_config');
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                if ($row['config_value'] !== null && trim((string)$row['config_value']) !== '') {
                    $result[$row['config_key']] = (string)$row['config_value'];
                }
            }
        } catch (\Throwable $e) {
            // Trường hợp DB chưa tạo bảng
        }

        return $result;
    }

    public function set(string $key, string $value, ?string $description = null): bool
    {
        $stmt = $this->db->prepare('
            INSERT INTO system_config (config_key, config_value, description, updated_at) 
            VALUES (:key, :value, :desc, CURRENT_TIMESTAMP)
            ON CONFLICT(config_key) DO UPDATE SET 
                config_value = excluded.config_value, 
                description = COALESCE(excluded.description, system_config.description),
                updated_at = CURRENT_TIMESTAMP
        ');
        return $stmt->execute([
            ':key' => $key,
            ':value' => $value,
            ':desc' => $description,
        ]);
    }

    /**
     * Kiểm tra trạng thái tạm nghỉ / đi vắng của Bếp
     * 
     * @param ?string $date Ngày cần kiểm tra (Y-m-d), null = hôm nay
     * @return array{is_closed: bool, reason: string, from_date: ?string, to_date: ?string}
     */
    public function getShopClosedStatus(?string $date = null): array
    {
        $isClosed = ($this->get('is_shop_closed', '0') === '1');
        if (!$isClosed) {
            return [
                'is_closed' => false,
                'reason' => '',
                'from_date' => null,
                'to_date' => null,
            ];
        }

        $targetDate = $date ?? date('Y-m-d');
        $fromDate = $this->get('closed_from_date', '');
        $toDate = $this->get('closed_to_date', '');
        $reason = $this->get('closed_reason', 'Bếp An Lành xin phép tạm nghỉ đi hái trà & chuẩn bị nguyên liệu mới. Hẹn sớm gặp lại bạn thương nhé! 🍃');

        // Nếu không cài ngày cụ thể -> Tạm nghỉ vô thời hạn
        if ($fromDate === '' && $toDate === '') {
            return [
                'is_closed' => true,
                'reason' => $reason,
                'from_date' => null,
                'to_date' => null,
            ];
        }

        // Kiểm tra trong khoảng ngày nghỉ
        $inRange = true;
        if ($fromDate !== '' && $targetDate < $fromDate) {
            $inRange = false;
        }
        if ($toDate !== '' && $targetDate > $toDate) {
            $inRange = false;
        }

        return [
            'is_closed' => $inRange,
            'reason' => $reason,
            'from_date' => $fromDate ?: null,
            'to_date' => $toDate ?: null,
        ];
    }
}
