-- ====================================================================
-- MIGRATION 001: Initial Database Schema
-- VeganFoodShop (Bếp Chay An Lành)
-- ====================================================================
PRAGMA foreign_keys = ON;
CREATE TABLE IF NOT EXISTS customers (
    phone TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    is_trusted INTEGER NOT NULL DEFAULT 0,
    note TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS time_slots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slot_date TEXT NOT NULL,
    slot_time TEXT NOT NULL,
    max_capacity INTEGER NOT NULL DEFAULT 12,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS menu_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT NULL,
    price INTEGER NOT NULL CHECK(price >= 0),
    category TEXT NOT NULL CHECK(category IN ('food', 'drink', 'extra')),
    image_url TEXT NULL,
    is_available INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_code TEXT NOT NULL UNIQUE,
    customer_phone TEXT NOT NULL,
    slot_id INTEGER NOT NULL,
    order_type TEXT NOT NULL CHECK(order_type IN ('dine_in', 'takeaway')),
    total_amount INTEGER NOT NULL DEFAULT 0,
    payment_method TEXT NOT NULL CHECK(
        payment_method IN ('vietqr', 'pay_later', 'cash')
    ),
    payment_status TEXT NOT NULL DEFAULT 'pending' CHECK(
        payment_status IN ('pending', 'paid', 'pay_later', 'refunded')
    ),
    order_status TEXT NOT NULL DEFAULT 'confirmed' CHECK(
        order_status IN ('pending', 'confirmed', 'completed', 'cancelled')
    ),
    customer_note TEXT NULL,
    admin_note TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_phone) REFERENCES customers(phone) ON UPDATE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES time_slots(id) ON DELETE RESTRICT
);
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
CREATE TABLE IF NOT EXISTS system_config (
    config_key TEXT PRIMARY KEY,
    config_value TEXT NOT NULL,
    description TEXT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers(phone);
CREATE INDEX IF NOT EXISTS idx_time_slots_date_active ON time_slots(slot_date, is_active);
CREATE INDEX IF NOT EXISTS idx_orders_slot_status ON orders(slot_id, order_status);
CREATE INDEX IF NOT EXISTS idx_orders_customer_phone ON orders(customer_phone);
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);