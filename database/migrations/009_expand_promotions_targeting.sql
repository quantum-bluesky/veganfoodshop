-- Migration 009: Bổ sung thiết lập khuyến mại theo từng khách hàng, nhóm khách hàng, nhóm đơn hàng và đơn hàng (Issue #26)

ALTER TABLE promotions ADD COLUMN target_customer_phone TEXT NULL;
ALTER TABLE promotions ADD COLUMN target_customer_group TEXT NOT NULL DEFAULT 'all';
ALTER TABLE promotions ADD COLUMN target_order_type TEXT NOT NULL DEFAULT 'all';
ALTER TABLE promotions ADD COLUMN first_order_only INTEGER NOT NULL DEFAULT 0;
ALTER TABLE promotions ADD COLUMN target_order_code TEXT NULL;

CREATE INDEX IF NOT EXISTS idx_promotions_cust_phone ON promotions(target_customer_phone);
CREATE INDEX IF NOT EXISTS idx_promotions_cust_group ON promotions(target_customer_group);
CREATE INDEX IF NOT EXISTS idx_promotions_order_type ON promotions(target_order_type);
