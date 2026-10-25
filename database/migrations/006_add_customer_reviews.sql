-- Migration 006: Thêm bảng customer_reviews và cấu hình tính năng đánh giá người dùng (Issue #18)
-- UTF-8 No BOM

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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    replied_at DATETIME NULL,
    FOREIGN KEY (customer_phone) REFERENCES customers(phone) ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_customer_reviews_status ON customer_reviews(status);
CREATE INDEX IF NOT EXISTS idx_customer_reviews_phone ON customer_reviews(customer_phone);
CREATE INDEX IF NOT EXISTS idx_customer_reviews_order ON customer_reviews(order_id);

-- Cấu hình mặc định: Tính năng đánh giá CHƯA BẬT (reviews_enabled = '0')
INSERT OR IGNORE INTO system_config (config_key, config_value, description) 
VALUES ('reviews_enabled', '0', 'Bật/tắt trang đánh giá của khách hàng (1=Bật, 0=Tắt)');

INSERT OR IGNORE INTO system_config (config_key, config_value, description) 
VALUES ('reviews_require_approval', '1', 'Kiểm duyệt đánh giá trước khi hiển thị (1=Cần duyệt, 0=Hiện ngay)');

INSERT OR IGNORE INTO system_config (config_key, config_value, description) 
VALUES ('reviews_allow_anonymous', '1', 'Cho phép khách chưa có đơn đặt gửi đánh giá/góp ý (1=Cho phép, 0=Chỉ khách có đơn)');
