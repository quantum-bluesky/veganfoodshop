# Kế Hoạch Triển Khai Hệ Thống VeganFoodShop (Bếp Chay An Lành)

Hệ thống được phát triển theo mô hình **Khung giờ & Suất ăn giới hạn (Slot & Batch Pre-order)**, tuân thủ nghiêm ngặt các quy định tại [AGENTS.md](../AGENTS.md), [AGENT_RULES.md](../AGENT_RULES.md), [DB_DESIGN.md](DB_DESIGN.md), [BUSINESS_FLOW.md](BUSINESS_FLOW.md), và [SCREEN_DESIGN.md](SCREEN_DESIGN.md).

---

## 1. Mục tiêu & Phiên bản
- **Mã Issue:** Issue #01
- **Tên nhánh Git:** `codex/01_Trien_khai_he_thong_dat_cho_Bep_An_Yen`
- **Phiên bản:** `1.0.0`

---

## 2. Các giai đoạn thực hiện (Implementation Roadmap)

### Giai đoạn 1: Thiết lập CSDL SQLite & Cấu trúc mã nguồn Backend
1. Tạo thư mục `database/`, viết `schema.sql` (6 bảng: `customers`, `time_slots`, `menu_items`, `orders`, `order_items`, `system_config` cùng toàn bộ chỉ mục index).
2. Viết `seed.sql` với dữ liệu món ăn thanh tịnh, các ca phục vụ trưa/tối, dữ liệu khách quen mẫu (`is_trusted = 1`), và cấu hình VietQR.
3. Tạo file database SQLite `database/bep_an_yen.db` với WAL mode và Foreign keys ON.
4. Xây dựng tầng truy cập dữ liệu và nghiệp vụ trong `src/`:
   - `src/autoload.php`: Bộ nạp lớp tự động chuẩn PSR-4 không phụ thuộc package ngoài.
   - `src/Database/Connection.php`: Kết nối PDO SQLite singleton an toàn.
   - `src/Services/CustomerService.php`: Tra cứu & cập nhật khách quen.
   - `src/Services/SlotService.php`: Quản lý ca, tính số suất còn lại của ca.
   - `src/Services/OrderService.php`: Tạo đơn với Transaction `IMMEDIATE`, khóa kiểm tra capacity chống race-condition.
   - `src/Services/VietQrService.php`: Sinh link VietQR động chuẩn ngân hàng.

### Giai đoạn 2: Phát triển Giao diện Đặt Món Mobile-First (Ghibli & Zen Style)
1. Xây dựng tài nguyên giao diện:
   - `assets/css/style.css`: Màu Washi `#FAF7F2`, xanh trà `#5B7065`, bo góc `rounded-3xl`, bóng đổ mờ sương.
   - `assets/js/app.js`: Tương tác chọn ca, tăng/giảm số lượng món, tính tiền thời gian thực, AJAX kiểm tra SĐT khách quen trên sự kiện `blur`.
2. Tạo màn hình chính `index.php`:
   - Header lời ngỏ an yên, khung thông báo lịch bếp hôm nay.
   - Bước 1: Ghé bếp ăn tại chỗ (`dine_in`) / Bếp gửi ship về (`takeaway`).
   - Bước 2: Chọn khung giờ (Time Slots) trực quan kèm số chỗ còn lại.
   - Bước 3: Danh mục món lành tinh tuyển (Phở Nấm, Bún Huế chay, Bún riêu chay, Trà sen tuyết...).
   - Bước 4: Nhập thông tin & tự động hiện Badge khách quen, mở tùy chọn Trả sau nếu `is_trusted = 1`.
   - Nút gửi đơn to bản hiển thị tổng tiền tạm tính.
3. Tạo trang xử lý đơn `process_order.php`:
   - Tiếp nhận dữ liệu, kiểm tra hợp lệ, gọi `OrderService`.
   - Hiển thị màn hình xác nhận đơn thành công & mã VietQR động đối với khách mới.

### Giai đoạn 3: Phát triển Trang Quản Trị Bếp (`admin.php`)
1. Cơ chế đăng nhập bảo mật bằng Session mật khẩu admin.
2. Giao diện quản trị phong cách Zen với 5 tab chức năng:
   - **Lịch Ca (Time Slots):** Thêm ca mới theo ngày, bật/tắt ca, điều chỉnh số suất tối đa `max_capacity`.
   - **Bếp Chuẩn Bị & Đơn Hàng (Kitchen & Orders):** Bảng tổng hợp số tô/suất cần nấu trong ca, lọc theo ca, đổi trạng thái đơn (`confirmed`, `paid`, `completed`, `cancelled`).
   - **Khách Quen (Customers):** Danh sách khách hàng, tìm kiếm theo SĐT, công tắc bật/tắt `is_trusted`.
   - **Thực Đơn (Menu Items):** Cập nhật giá món, ẩn món tạm hết hoặc bổ sung món mới.
   - **Cấu hình Quán (Settings):** Thông tin tài khoản VietQR, lời ngỏ chào mừng, thông báo ca hôm nay.

### Giai đoạn 4: Bộ Kiểm Thử Tự Động (Testing Suite) & Checklist Bàn Giao
1. Xây dựng bộ test trong `tests/`:
   - Tuân thủ 100% nguyên tắc cô lập: Sử dụng SQLite `:memory:`, nạp lại schema trước mỗi test.
   - `CustomerServiceTest`: Nhận diện khách quen qua SĐT.
   - `SlotCapacityTest`: Kiểm tra tính toán suất còn lại và ném ngoại lệ khi hết suất.
   - `OrderCalculationTest`: Tính tiền và snapshot giá chính xác.
   - `CreateOrderTransactionTest`: Kiểm tra rollback khi xảy ra lỗi.
   - `SlotConcurrencyTest`: Kiểm tra transaction an toàn khi đặt suất cận ngưỡng.
2. Xây dựng runner `tests/run_tests.php` để chạy toàn bộ test tự động và báo cáo kết quả chi tiết.
3. Rà soát checklist bảo mật XSS, SQL Injection Prepared Statements, UTF-8 No BOM, `declare(strict_types=1);`.
4. Cập nhật `README.md`, commit theo quy ước Git của dự án.
