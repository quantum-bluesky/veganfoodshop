# DB_DESIGN.md - Thiết Kế Cơ Sở Dữ Liệu VeganFoodShop

Tài liệu này định nghĩa chi tiết cấu trúc bảng, kiểu dữ liệu, ràng buộc, chỉ mục (indexes) và quy trình giao dịch cho cơ sở dữ liệu **SQLite3** của hệ thống **VeganFoodShop (Bếp Chay An Lành)**.

---

## 1. Nguyên tắc & Cấu hình CSDL SQLite

1. **Lưu trữ đơn tệp:** Cơ sở dữ liệu lưu tại `database/bep_an_yen.db`. Không public tệp này ra ngoài web (Nginx chặn truy cập trực tiếp).
2. **Kích hoạt chế độ WAL (Write-Ahead Logging):**
   - Hỗ trợ đa luồng đọc (concurrency read) trong khi ghi không bị nghẽn:
     ```sql
     PRAGMA journal_mode = WAL;
     PRAGMA synchronous = NORMAL;
     ```
3. **Bắt buộc kích hoạt Foreign Keys:**
   - SQLite mặc định tắt ràng buộc khóa ngoại, do đó mỗi kết nối PDO đều phải chạy:
     ```sql
     PRAGMA foreign_keys = ON;
     ```
4. **Bảng mã ký tự:** Toàn bộ bảng, cột chuỗi đều lưu trữ chuẩn **UTF-8**.

---

## 2. Chi tiết Lược đồ CSDL (Schema Details)

### 2.1. Bảng `customers` (Khách hàng & Khách quen)
Lưu thông tin khách hàng để nhận diện khách quen và cấp quyền thanh toán linh hoạt:

```sql
CREATE TABLE IF NOT EXISTS customers (
    phone TEXT PRIMARY KEY,                       -- Số điện thoại (định danh duy nhất, 10 chữ số)
    name TEXT NOT NULL,                           -- Tên hoặc biệt danh khách hàng
    is_trusted INTEGER NOT NULL DEFAULT 0,        -- 0: Khách thông thường/mới; 1: Khách quen tin cậy (được trả sau)
    note TEXT NULL,                               -- Ghi chú nội bộ của quán về khách
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### 2.2. Bảng `time_slots` (Khung giờ phục vụ theo ngày/tuần)
Quản lý lịch ca phục vụ linh hoạt và giới hạn suất tối đa của từng ca:

```sql
CREATE TABLE IF NOT EXISTS time_slots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slot_date TEXT NOT NULL,                      -- Ngày phục vụ định dạng 'YYYY-MM-DD'
    slot_time TEXT NOT NULL,                      -- Nhãn khung giờ, ví dụ: '11:30 - 12:15'
    max_capacity INTEGER NOT NULL DEFAULT 12,     -- Số lượng suất phục vụ tối đa của ca
    is_active INTEGER NOT NULL DEFAULT 1,         -- 1: Mở nhận đặt; 0: Đóng ca thủ công
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### 2.3. Bảng `menu_items` (Thực đơn món ăn & đồ uống)
Danh mục món thanh tịnh tinh tuyển mỗi ngày:

```sql
CREATE TABLE IF NOT EXISTS menu_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,                           -- Tên món (ví dụ: 'Phở Nấm Hương Cổ Truyền')
    description TEXT NULL,                        -- Mô tả thành phần (ví dụ: 'Nước dùng quả lê, mía lau...')
    price INTEGER NOT NULL CHECK(price >= 0),     -- Đơn giá (VND)
    category TEXT NOT NULL CHECK(category IN ('food', 'drink', 'extra')), -- Phân loại món
    image_url TEXT NULL,                          -- Đường dẫn ảnh minh họa mộc mạc phong cách Ghibli
    is_available INTEGER NOT NULL DEFAULT 1,      -- 1: Đang phục vụ; 0: Tạm hết món
    sort_order INTEGER NOT NULL DEFAULT 0         -- Thứ tự ưu tiên hiển thị trên menu
);
```

### 2.4. Bảng `orders` (Đơn đặt chỗ & đặt món)
Quản lý thông tin đơn đặt chỗ, hình thức dùng bữa và trạng thái thanh toán:

```sql
CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_code TEXT NOT NULL UNIQUE,              -- Mã đơn hàng thân thiện (ví dụ: 'AY-20261025-001')
    customer_phone TEXT NOT NULL,                 -- SĐT khách đặt
    slot_id INTEGER NOT NULL,                     -- Ca đặt món
    order_type TEXT NOT NULL CHECK(order_type IN ('dine_in', 'takeaway')), -- 'dine_in': Tại chỗ; 'takeaway': Ship
    total_amount INTEGER NOT NULL DEFAULT 0,      -- Tổng giá trị đơn hàng (VND)
    payment_method TEXT NOT NULL CHECK(payment_method IN ('vietqr', 'pay_later', 'cash')),
    payment_status TEXT NOT NULL DEFAULT 'pending' CHECK(payment_status IN ('pending', 'paid', 'pay_later', 'refunded')),
    order_status TEXT NOT NULL DEFAULT 'confirmed' CHECK(order_status IN ('pending', 'confirmed', 'completed', 'cancelled')),
    customer_note TEXT NULL,                      -- Ghi chú của khách (ví dụ: 'ít cay, không rau răm')
    admin_note TEXT NULL,                         -- Ghi chú nội bộ bếp
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_phone) REFERENCES customers(phone) ON UPDATE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES time_slots(id) ON DELETE RESTRICT
);
```

### 2.5. Bảng `order_items` (Chi tiết món của đơn hàng)
Tách riêng từng món đặt để hỗ trợ bếp thống kê số lượng tô bún/phở cần chuẩn bị theo từng ca:

```sql
CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,                    -- Mã đơn hàng
    menu_item_id INTEGER NOT NULL,                -- Mã món ăn
    item_name TEXT NOT NULL,                      -- Snapshot tên món tại thời điểm đặt
    unit_price INTEGER NOT NULL,                  -- Snapshot đơn giá tại thời điểm đặt
    quantity INTEGER NOT NULL CHECK(quantity > 0),-- Số lượng đặt
    subtotal INTEGER NOT NULL,                    -- Thành tiền = unit_price * quantity
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE RESTRICT
);
```

### 2.6. Bảng `system_config` (Cấu hình hệ thống runtime)
Lưu trữ thông tin tài khoản ngân hàng VietQR, mật khẩu admin, thông điệp chào mừng:

```sql
CREATE TABLE IF NOT EXISTS system_config (
    config_key TEXT PRIMARY KEY,
    config_value TEXT NOT NULL,
    description TEXT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

## 3. Chỉ mục tối ưu truy vấn (Indexes)

Để tăng tốc độ kiểm tra khách quen qua AJAX và tính toán số suất còn trống của từng ca:

```sql
-- Tìm kiếm khách hàng qua số điện thoại tức thời
CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers(phone);

-- Lấy danh sách ca đang hoạt động theo ngày
CREATE INDEX IF NOT EXISTS idx_time_slots_date_active ON time_slots(slot_date, is_active);

-- Kiểm tra số đơn và số suất đã đặt của một ca
CREATE INDEX IF NOT EXISTS idx_orders_slot_status ON orders(slot_id, order_status);
CREATE INDEX IF NOT EXISTS idx_orders_customer_phone ON orders(customer_phone);

-- Truy vấn danh sách món theo đơn hàng
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);
```

---

## 4. Xử lý đồng thời & Khóa suất an toàn (Concurrency & Capacity Lock)

### 4.1. Cách tính số suất đã đặt trong ca
Một suất ăn được tính dựa trên tổng số lượng món ăn chính (`category = 'food'`) của các đơn không bị hủy (`order_status != 'cancelled'`) trong ca đó:

```sql
SELECT COALESCE(SUM(oi.quantity), 0) AS booked_count
FROM orders o
JOIN order_items oi ON o.id = oi.order_id
JOIN menu_items mi ON oi.menu_item_id = mi.id
WHERE o.slot_id = :slot_id 
  AND o.order_status != 'cancelled'
  AND mi.category = 'food';
```

### 4.2. Luồng Transaction `IMMEDIATE` khi tạo đơn
Nhằm ngăn chặn tình trạng quá tải (overbooking) khi 2 khách hàng cùng bấm đặt chỗ ca cuối cùng tại cùng một tích tắc:

```php
// Bắt đầu giao dịch khẩn cấp (khóa ghi tạm thời)
$pdo->exec('BEGIN IMMEDIATE TRANSACTION;');

try {
    // 1. Lấy thông tin ca và khóa dòng
    $stmtSlot = $pdo->prepare('SELECT max_capacity, is_active FROM time_slots WHERE id = :slot_id');
    $stmtSlot->execute([':slot_id' => $slotId]);
    $slot = $stmtSlot->fetch(PDO::FETCH_ASSOC);

    if (!$slot || $slot['is_active'] != 1) {
        throw new DomainException("Ca phục vụ này hiện không khả dụng.");
    }

    // 2. Tính số suất ăn chính đã đặt
    $stmtCount = $pdo->prepare('
        SELECT COALESCE(SUM(oi.quantity), 0) AS booked_count
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN menu_items mi ON oi.menu_item_id = mi.id
        WHERE o.slot_id = :slot_id 
          AND o.order_status != \'cancelled\'
          AND mi.category = \'food\'
    ');
    $stmtCount->execute([':slot_id' => $slotId]);
    $bookedCount = (int)$stmtCount->fetchColumn();

    // 3. Kiểm tra ngưỡng
    if (($bookedCount + $newFoodCount) > (int)$slot['max_capacity']) {
        throw new DomainException("Ca phục vụ đã nhận đủ suất, xin bạn chọn ca khác.");
    }

    // 4. Thêm khách hàng (INSERT OR IGNORE / UPDATE tên)
    // 5. Thêm bản ghi orders
    // 6. Thêm bản ghi order_items

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
```

---

## 5. Quy trình Migration CSDL

- Phiên bản CSDL được quản lý thông qua `PRAGMA user_version`.
- Mỗi bản cập nhật Schema phải được viết thành hàm migration riêng biệt theo thứ tự phiên bản tăng dần:
  - Version 1: Khởi tạo 6 bảng cơ bản (`customers`, `time_slots`, `menu_items`, `orders`, `order_items`, `system_config`).
  - Version 2+: Thêm cột hoặc bảng mới (có kiểm tra `ALTER TABLE`).
- Trước khi chạy migration trên production, bắt buộc phải sao lưu file `bep_an_yen.db` sang `bep_an_yen.db.bak`.
