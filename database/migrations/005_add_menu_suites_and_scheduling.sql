-- Migration 005: Bộ Thực Đơn Mẫu (Menu Suites), Lên Lịch Thực Đơn Theo Ngày & Phân Loại Lớn (Đồ Ăn, Đồ Uống, Mâm Cỗ Chay, Tại Chỗ & Mang Về)
-- Bếp Chay An Lành
-- 1. Bổ sung service_scope và cờ mâm cỗ chay vào menu_items
ALTER TABLE menu_items
ADD COLUMN service_scope TEXT NOT NULL DEFAULT 'both';
ALTER TABLE menu_items
ADD COLUMN is_feast_combo INTEGER NOT NULL DEFAULT 0;
-- 2. Bảng Bộ Thực Đơn Mẫu (Menu Suites)
CREATE TABLE IF NOT EXISTS menu_suites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT NULL,
    is_default INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
-- 3. Bảng Món thuộc Bộ Thực Đơn (Suite Items)
CREATE TABLE IF NOT EXISTS menu_suite_items (
    suite_id INTEGER NOT NULL,
    menu_item_id INTEGER NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (suite_id, menu_item_id),
    FOREIGN KEY (suite_id) REFERENCES menu_suites(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
);
-- 4. Bảng Phân Bổ Lịch Thực Đơn Theo Ngày (Menu Schedules)
CREATE TABLE IF NOT EXISTS menu_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    schedule_date TEXT NOT NULL UNIQUE,
    suite_id INTEGER NOT NULL,
    note TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (suite_id) REFERENCES menu_suites(id) ON DELETE CASCADE
);
-- 5. Tạo bộ thực đơn mặc định & mẫu ban đầu
INSERT
    OR IGNORE INTO menu_suites (id, name, description, is_default)
VALUES (
        1,
        'Thực Đơn Thường Ngày (Mặc Định)',
        'Các món chay thanh đạm quen thuộc',
        1
    ),
    (
        2,
        'Thực Đơn Ngày Chẵn (T2, T4, T6)',
        'Phở nấm, bún riêu chay & trà thanh nhiệt',
        0
    ),
    (
        3,
        'Thực Đơn Ngày Lẻ (T3, T5, T7)',
        'Bún bò Huế chay, mì tiềm nấm & nước hạt sen',
        0
    ),
    (
        4,
        'Mâm Cỗ Chay An Lạc (Rằm & Tiệc Gia Đình)',
        'Mâm cỗ chay trọn vị 5-7 món thịnh soạn',
        0
    );
-- Gán tất cả món hiện có vào Suite 1 (Mặc định)
INSERT
    OR IGNORE INTO menu_suite_items (suite_id, menu_item_id, sort_order)
SELECT 1,
    id,
    sort_order
FROM menu_items;
-- Thêm món mẫu Mâm cỗ chay vào thực đơn
INSERT
    OR IGNORE INTO menu_items (
        name,
        description,
        price,
        category,
        service_scope,
        is_feast_combo,
        is_available,
        sort_order
    )
VALUES (
        'Mâm Cỗ Chay An Lạc (5 Món)',
        'Gồm: Giò lụa chay ngũ vị, Nem rán hoàng kim, Nấm đùi gà kho tiêu xanh, Canh nấm hạt sen & Xôi gấc hạt sen (Phục vụ 4-6 người)',
        450000,
        'food',
        'both',
        1,
        1,
        10
    ),
    (
        'Mâm Cỗ Chay Cát Tường (7 Món)',
        'Mâm cỗ chay cao cấp: Giò lụa, nem nấm, chả nấm rong biển, súp yến mạch hạt sen, canh sen đại bổ, đậu phụ hũ ky sốt cay & xôi hạt sen',
        680000,
        'food',
        'both',
        1,
        1,
        11
    );
-- Gán mâm cỗ chay vào Suite 4
INSERT
    OR IGNORE INTO menu_suite_items (suite_id, menu_item_id, sort_order)
SELECT 4,
    id,
    sort_order
FROM menu_items
WHERE is_feast_combo = 1;
-- Gán các món vào Suite 2 và Suite 3 mẫu
INSERT
    OR IGNORE INTO menu_suite_items (suite_id, menu_item_id, sort_order)
SELECT 2,
    id,
    sort_order
FROM menu_items
WHERE id IN (1, 3);
INSERT
    OR IGNORE INTO menu_suite_items (suite_id, menu_item_id, sort_order)
SELECT 3,
    id,
    sort_order
FROM menu_items
WHERE id IN (2, 3);