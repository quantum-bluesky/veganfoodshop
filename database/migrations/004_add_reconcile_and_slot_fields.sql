-- Migration 004: Thêm trường hỗ trợ đối soát thanh toán và chốt ca/ngày
-- Bếp Chay An Lành
ALTER TABLE orders
ADD COLUMN paid_at DATETIME NULL;
ALTER TABLE orders
ADD COLUMN transaction_ref TEXT NULL;
ALTER TABLE time_slots
ADD COLUMN is_closed INTEGER NOT NULL DEFAULT 0;