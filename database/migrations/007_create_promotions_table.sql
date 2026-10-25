-- Migration 007: Tạo bảng Khuyến mại (Promotions) & Bổ sung cột chiết khấu vào Orders
-- Phục vụ Issue #20: Khuyến mại theo lịch, món ăn, mâm cỗ & ngày âm lịch

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
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_promotions_dates ON promotions(start_date, end_date, is_active);
