-- ====================================================================
-- MIGRATION 002: Add Zalo OAuth fields to customers
-- VeganFoodShop (Bếp Chay An Lành)
-- ====================================================================
ALTER TABLE customers
ADD COLUMN zalo_id TEXT;
ALTER TABLE customers
ADD COLUMN avatar_url TEXT;
CREATE INDEX IF NOT EXISTS idx_customers_zalo_id ON customers(zalo_id);