-- ====================================================================
-- SCHEMA DEFINITION: VeganFoodShop (Bếp Chay An Yên)
-- Database Engine: SQLite 3
-- Character Encoding: UTF-8
-- ====================================================================

PRAGMA foreign_keys = ON;

-- 1. Bảng Khách Hàng (Nhận diện khách quen, Zalo OAuth & trả sau)
CREATE TABLE IF NOT EXISTS customers (
    phone TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    is_trusted INTEGER NOT NULL DEFAULT 0,
    note TEXT NULL,
    zalo_id TEXT NULL,
    avatar_url TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Bảng Khung Giờ Phục Vụ (Time Slots & Capacity)
CREATE TABLE IF NOT EXISTS time_slots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slot_date TEXT NOT NULL,
    slot_time TEXT NOT NULL,
    max_capacity INTEGER NOT NULL DEFAULT 12,
    is_active INTEGER NOT NULL DEFAULT 1,
    is_closed INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 3. Bảng Thực Đơn Món Ăn & Thức Uống
CREATE TABLE IF NOT EXISTS menu_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT NULL,
    price INTEGER NOT NULL CHECK(price >= 0),
    category TEXT NOT NULL CHECK(category IN ('food', 'drink', 'extra', 'feast_combo')),
    image_url TEXT NULL,
    service_scope TEXT NOT NULL DEFAULT 'both' CHECK(service_scope IN ('both', 'dine_in_only', 'takeaway_only')),
    is_feast_combo INTEGER NOT NULL DEFAULT 0,
    is_available INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0
);

-- 3.1. Bảng Bộ Thực Đơn Mẫu (Menu Suites)
CREATE TABLE IF NOT EXISTS menu_suites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT NULL,
    is_default INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 3.2. Bảng Món thuộc Bộ Thực Đơn (Suite Items)
CREATE TABLE IF NOT EXISTS menu_suite_items (
    suite_id INTEGER NOT NULL,
    menu_item_id INTEGER NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (suite_id, menu_item_id),
    FOREIGN KEY (suite_id) REFERENCES menu_suites(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
);

-- 3.3. Bảng Phân Bổ Lịch Thực Đơn Theo Ngày (Menu Schedules)
CREATE TABLE IF NOT EXISTS menu_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    schedule_date TEXT NOT NULL UNIQUE,
    suite_id INTEGER NOT NULL,
    note TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (suite_id) REFERENCES menu_suites(id) ON DELETE CASCADE
);

-- 4. Bảng Đơn Hàng & Đặt Chỗ
CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_code TEXT NOT NULL UNIQUE,
    customer_phone TEXT NOT NULL,
    slot_id INTEGER NOT NULL,
    order_type TEXT NOT NULL CHECK(order_type IN ('dine_in', 'takeaway')),
    total_amount INTEGER NOT NULL DEFAULT 0,
    payment_method TEXT NOT NULL CHECK(payment_method IN ('vietqr', 'pay_later', 'cash')),
    payment_status TEXT NOT NULL DEFAULT 'pending' CHECK(payment_status IN ('pending', 'paid', 'pay_later', 'refunded')),
    order_status TEXT NOT NULL DEFAULT 'confirmed' CHECK(order_status IN ('pending', 'confirmed', 'completed', 'cancelled')),
    customer_note TEXT NULL,
    admin_note TEXT NULL,
    paid_at DATETIME NULL,
    transaction_ref TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_phone) REFERENCES customers(phone) ON UPDATE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES time_slots(id) ON DELETE RESTRICT
);

-- 5. Bảng Chi Tiết Món Ăn Của Đơn Hàng (Order Items)
CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    menu_item_id INTEGER NOT NULL,
    item_name TEXT NOT NULL,
    unit_price INTEGER NOT NULL,
    quantity INTEGER NOT NULL CHECK(quantity > 0),
    subtotal INTEGER NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE RESTRICT
);

-- 6. Bảng Cấu Hình Hệ Thống Runtime (System Config)
CREATE TABLE IF NOT EXISTS system_config (
    config_key TEXT PRIMARY KEY,
    config_value TEXT NOT NULL,
    description TEXT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 7. Bảng Hàng Đợi Thông Báo (Notification Outbox)
CREATE TABLE IF NOT EXISTS notification_outbox (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_key TEXT NOT NULL UNIQUE,
    event_type TEXT NOT NULL,
    order_id INTEGER NOT NULL,
    channel TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    attempt_count INTEGER NOT NULL DEFAULT 0,
    next_retry_at TEXT,
    last_error TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at TEXT,
    payload_json TEXT
);

-- 8. Bảng Đánh Giá & Cảm Nhận Của Khách Hàng (Customer Reviews - Issue #18)
CREATE TABLE IF NOT EXISTS customer_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_phone TEXT NULL,
    customer_name TEXT NOT NULL,
    order_id INTEGER NULL,
    rating INTEGER NOT NULL CHECK(rating >= 1 AND rating <= 5),
    title TEXT NULL,
    comment TEXT NOT NULL,
    kitchen_reply TEXT NULL,
    status TEXT NOT NULL DEFAULT 'pending', -- 'pending', 'approved', 'hidden'
    is_verified_buyer INTEGER NOT NULL DEFAULT 0,
    is_featured INTEGER NOT NULL DEFAULT 0,
    testimonial_note TEXT NULL,
    type TEXT NOT NULL DEFAULT 'review', -- 'review', 'testimonial'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    replied_at DATETIME NULL,
    FOREIGN KEY (customer_phone) REFERENCES customers(phone) ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_customer_reviews_featured ON customer_reviews(is_featured);

-- 9. Bảng Chương Trình Khuyến Mại & Giảm Giá (Promotions - Issue #20)
CREATE TABLE IF NOT EXISTS promotions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NULL,
    title TEXT NOT NULL,
    description TEXT NULL,
    discount_type TEXT NOT NULL CHECK(discount_type IN ('percentage', 'fixed_amount')),
    discount_value INTEGER NOT NULL CHECK(discount_value > 0),
    min_order_amount INTEGER NOT NULL DEFAULT 0,
    applies_to TEXT NOT NULL DEFAULT 'all' CHECK(applies_to IN ('all', 'category', 'item', 'feast_combo')),
    target_id INTEGER NULL,
    target_category TEXT NULL,
    start_date TEXT NOT NULL,
    end_date TEXT NOT NULL,
    apply_on_lunar_days TEXT NULL,
    badge_text TEXT NULL,
    target_customer_phone TEXT NULL,
    target_customer_group TEXT NOT NULL DEFAULT 'all',
    target_order_type TEXT NOT NULL DEFAULT 'all',
    first_order_only INTEGER NOT NULL DEFAULT 0,
    target_order_code TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ====================================================================
-- INDEXES TỐI ƯU HIỆU NĂNG TRUY VẤN
-- ====================================================================

CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers(phone);
CREATE INDEX IF NOT EXISTS idx_customers_zalo_id ON customers(zalo_id);
CREATE INDEX IF NOT EXISTS idx_time_slots_date_active ON time_slots(slot_date, is_active);
CREATE INDEX IF NOT EXISTS idx_orders_slot_status ON orders(slot_id, order_status);
CREATE INDEX IF NOT EXISTS idx_orders_customer_phone ON orders(customer_phone);
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_notification_pending ON notification_outbox(status, next_retry_at);
CREATE INDEX IF NOT EXISTS idx_notification_order ON notification_outbox(order_id);
CREATE INDEX IF NOT EXISTS idx_customer_reviews_status ON customer_reviews(status);
CREATE INDEX IF NOT EXISTS idx_customer_reviews_phone ON customer_reviews(customer_phone);
CREATE INDEX IF NOT EXISTS idx_customer_reviews_order ON customer_reviews(order_id);
CREATE INDEX IF NOT EXISTS idx_promotions_dates ON promotions(start_date, end_date, is_active);


