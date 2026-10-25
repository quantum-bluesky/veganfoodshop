-- ====================================================================
-- SEED DATA: VeganFoodShop (Bếp Chay An Lành)
-- Character Encoding: UTF-8
-- ====================================================================
-- 1. Khách Hàng Mẫu
INSERT
    OR IGNORE INTO customers (phone, name, is_trusted, note)
VALUES (
        '0901234567',
        'Chị Lan',
        1,
        'Khách quen từ những ngày đầu, ăn thanh đạm ít cay'
    ),
    (
        '0987654321',
        'Anh Minh',
        0,
        'Khách mới đặt thử món'
    );
-- 2. Thực Đơn Món Thanh Tịnh
INSERT
    OR IGNORE INTO menu_items (
        id,
        name,
        description,
        price,
        category,
        is_available,
        sort_order
    )
VALUES (
        1,
        'Phở Nấm Hương Cổ Truyền',
        'Nước dùng quả lê, mía lau & nấm đông cô tươi ngọt thanh',
        45000,
        'food',
        1,
        1
    ),
    (
        2,
        'Bún Huế Chay An Điền',
        'Nước dùng sả nồng ấm, chả nấm bào ngư, đậu hũ chiên mềm',
        45000,
        'food',
        1,
        2
    ),
    (
        3,
        'Bún Riêu Chay Thanh Vị',
        'Riêu đậu xanh cà chua thanh mát, mộc nhĩ, nấm rơm giòn ngọt',
        45000,
        'food',
        1,
        3
    ),
    (
        4,
        'Trà Sen Tuyết Búp Nhẹ',
        'Hương hoa sen ướp lạnh, thanh giọng, ngọt dịu đầu lưỡi',
        25000,
        'drink',
        1,
        4
    ),
    (
        5,
        'Nước Mía Lau Rễ Tranh',
        'Nấu thủ công từ mía lau, rễ tranh và bắp nếp giải nhiệt',
        20000,
        'drink',
        1,
        5
    );
-- 3. Cấu Hình Hệ Thống Runtime
INSERT
    OR REPLACE INTO system_config (config_key, config_value, description)
VALUES ('shop_name', 'Bếp Chay An Lành', 'Tên quán chay'),
    (
        'shop_tagline',
        'Chốn dừng chân thanh tĩnh',
        'Khẩu hiệu thương hiệu'
    ),
    (
        'shop_quote',
        'Một bát canh thanh ngọt, vỗ về những bộn bề ngày thường',
        'Châm ngôn an yên'
    ),
    (
        'notice_text',
        'Lịch bếp hôm nay: Ca trưa 11:30 - 13:30 | Ca tối 18:00 - 19:45 (Tối đa 12 suất/ca)',
        'Thông báo mở ca'
    ),
    (
        'bank_id',
        '970422',
        'Mã ngân hàng VietQR (MBBank: 970422 hoặc MB)'
    ),
    (
        'bank_name',
        'MBBank (Quân Đội)',
        'Tên ngân hàng hiển thị'
    ),
    (
        'account_no',
        '0987654321',
        'Số tài khoản nhận chuyển khoản'
    ),
    (
        'account_name',
        'BEP CHAY AN YEN',
        'Tên chủ tài khoản thụ hưởng'
    ),
    (
        'admin_password_hash',
        '$2y$10$w4rY.qF8w4x44Q4j9u7dCe3XF/bXWb3Qp8r6f5r8Z7hY3K0K7p7tq',
        'Mật khẩu quản trị mặc định: AnYen@2026'
    );
-- 4. Khung Giờ Mẫu Cho Hôm Nay & Ngày Mai
INSERT
    OR IGNORE INTO time_slots (slot_date, slot_time, max_capacity, is_active)
VALUES (
        strftime('%Y-%m-%d', 'now', 'localtime'),
        '11:30 - 12:15',
        12,
        1
    ),
    (
        strftime('%Y-%m-%d', 'now', 'localtime'),
        '12:30 - 13:15',
        12,
        1
    ),
    (
        strftime('%Y-%m-%d', 'now', 'localtime'),
        '18:00 - 18:45',
        12,
        1
    ),
    (
        strftime('%Y-%m-%d', 'now', 'localtime'),
        '19:00 - 19:45',
        12,
        1
    ),
    (
        strftime('%Y-%m-%d', 'now', '+1 day', 'localtime'),
        '11:30 - 12:15',
        12,
        1
    ),
    (
        strftime('%Y-%m-%d', 'now', '+1 day', 'localtime'),
        '12:30 - 13:15',
        12,
        1
    ),
    (
        strftime('%Y-%m-%d', 'now', '+1 day', 'localtime'),
        '18:00 - 18:45',
        12,
        1
    ),
    (
        strftime('%Y-%m-%d', 'now', '+1 day', 'localtime'),
        '19:00 - 19:45',
        12,
        1
    );