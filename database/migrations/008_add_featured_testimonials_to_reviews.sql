-- Migration 008: Thêm trường nổi bật và lời đề tặng kỷ niệm cho đánh giá (Issue #21)
-- UTF-8 No BOM
ALTER TABLE customer_reviews
ADD COLUMN is_featured INTEGER NOT NULL DEFAULT 0;
ALTER TABLE customer_reviews
ADD COLUMN testimonial_note TEXT NULL;
ALTER TABLE customer_reviews
ADD COLUMN type TEXT NOT NULL DEFAULT 'review';
CREATE INDEX IF NOT EXISTS idx_customer_reviews_featured ON customer_reviews(is_featured);
-- Thêm khách vào bảng customers để thỏa mãn ràng buộc khóa ngoại (nếu có phone)
INSERT
    OR IGNORE INTO customers (phone, name, is_trusted)
VALUES ('0908123456', 'Bác Minh Tuệ', 1);
INSERT
    OR IGNORE INTO customers (phone, name, is_trusted)
VALUES ('0912345678', 'Bạn Diệu Hương', 1);
-- Thêm một số lời đề tặng, kỷ niệm sâu lắng mẫu
INSERT INTO customer_reviews (
        customer_name,
        customer_phone,
        rating,
        title,
        comment,
        kitchen_reply,
        status,
        is_verified_buyer,
        is_featured,
        testimonial_note,
        type,
        created_at
    )
VALUES (
        'Bác Minh Tuệ & Gia đình',
        '0908123456',
        5,
        'Một chiều mưa tìm lại sự an yên',
        'Ghé Bếp vào một chiều mưa giăng kín ngõ nhỏ. Bát phở nấm hương nghi ngút khói, nước dùng ngọt thanh từ củ cải và mía lau khiến cả nhà như được trở về gian bếp tuổi thơ của mẹ. Không gian tĩnh lặng, tiếng chuông gió khẽ ngân làm bao bộn bề ngoài phố dường như dừng lại sau cánh cửa gỗ.',
        'Bếp nhớ mãi nụ cười hiền của Bác và gia đình chiều hôm ấy. Chúc Bác luôn thân tâm an lạc!',
        'approved',
        1,
        1,
        'Lưu bút bàn trà số 3 • Ngày Rằm tháng Bảy',
        'testimonial',
        '2026-08-28 17:30:00'
    ),
    (
        'Bạn Diệu Hương',
        '0912345678',
        5,
        'Mâm cỗ chay thanh tịnh ngày giỗ Mẹ',
        'Đặt mâm cỗ 5 món của Bếp cho ngày giỗ Mẹ. Từng đĩa xôi hạt sen, nem nấm giòn rụm đến bát canh sen tuyết đều được chăm chút tỉ mỉ, đong đầy tâm ý. Cả họ ai cũng khen món chay mà tròn vị, nhẹ bụng và thanh tao đến lạ.',
        'Biết ơn Bạn đã tin tưởng trao gửi bữa cơm sum vầy thiêng liêng của gia đình cho Bếp.',
        'approved',
        1,
        1,
        'Lời cảm ơn từ phương xa gửi về Bếp',
        'testimonial',
        '2026-09-15 11:20:00'
    ),
    (
        'Cư sĩ Thanh Phong',
        NULL,
        5,
        'Bát canh vỗ về những mỏi mệt',
        'Ăn chay không chỉ là dưỡng thân, mà còn là nuôi dưỡng lòng từ ái. Cảm ơn Bếp An Lành đã giữ trọn ngọn lửa mộc mạc, không chạy theo gia vị công nghiệp, để mỗi người ghé qua đều tìm thấy một chốn nương tựa bình yên.',
        'Bếp xin lắng nghe và nguyện giữ vẹn nguyên sơ tâm này.',
        'approved',
        0,
        1,
        'Lời đề tặng trên cuốn sổ tay mộc',
        'testimonial',
        '2026-10-01 19:45:00'
    );