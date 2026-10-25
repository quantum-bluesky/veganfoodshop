# VeganFoodShop - Bếp Chay An Yên 🍵

Hệ thống web cá nhân phục vụ việc **Đặt Chỗ & Đặt Món Trước (Slot & Batch Pre-order)** cho quán ẩm thực chay, hoạt động linh hoạt theo từng khung giờ và số lượng suất giới hạn trong ngày/tuần.

Dự án mang phong cách giao diện **"Hơi thở Ghibli & Thiền Vị (Zen UI/UX)"** — tạo cảm giác mộc mạc, bình an, ấm áp như một chốn quay về tĩnh lặng.

---

## 🌟 Điểm nổi bật & Tính năng cốt lõi

1. **Mô hình Khung giờ & Suất ăn giới hạn (Slot & Batch Pre-order):**
   - Mở bán theo từng ca phục vụ (ví dụ: Ca 1: `11:30 - 12:15`, Ca 2: `12:30 - 13:15`).
   - Tự động đóng/khóa ca khi đã đủ số suất phục vụ tối đa (`max_capacity`), ngăn chặn tình trạng quá tải (overbooking).
   - Hỗ trợ 2 hình thức: **Ghé bếp ăn tại chỗ (`dine_in`)** & **Bếp gửi ship về (`takeaway`)**.
2. **Nhận diện Khách Quen & Thanh toán linh hoạt:**
   - **Khách mới:** Quét mã **VietQR** chuyển khoản tức thì để giữ suất.
   - **Khách quen (`is_trusted = 1`):** Tự động nhận diện qua số điện thoại ngay khi nhập xong (`onblur`), hiển thị lời chào thân thương và mở tùy chọn **"Thanh toán khi dùng bữa tại quán" (`pay_later`)**.
3. **Kiến trúc tinh gọn & nhẹ tênh (Lightweight):**
   - Chạy mượt mà trên VPS/máy chủ Linux Debian cấu hình khiêm tốn.
   - Stack: **PHP 8.4+ (hoặc PHP 8.3+) + SQLite3 (WAL mode) + Vanilla CSS/JS**.
   - Không phụ thuộc framework cồng kềnh, không tốn RAM.
4. **An toàn giao dịch & Chống Race Condition:**
   - Cơ chế Transaction `IMMEDIATE` trong SQLite khóa kiểm tra số suất tức thời khi nhiều khách cùng đặt ca cuối cùng.
   - 100% Prepared Statements qua PDO, phòng chống SQL Injection và XSS.
5. **Trang Quản Trị Bếp Toàn Diện (`admin.php`):**
   - **Bảng chuẩn bị bếp (Kitchen Batch Summary):** Thống kê số lượng từng tô phở/bún cần chuẩn bị cho ca hiện tại.
   - **Quản lý Lịch ca:** Mở ca mới, điều chỉnh số suất tối đa, bật/tắt ca.
   - **Quản lý Đơn hàng:** Cập nhật trạng thái thanh toán và trạng thái đơn.
   - **Quản lý Khách quen:** Bật/tắt cờ `is_trusted`.
   - **Quản lý Thực đơn & Cấu hình:** Chỉnh giá món, bật/tắt món, cấu hình tài khoản VietQR và Zalo Login.
6. **Đăng nhập Zalo (Tùy chọn) & Tự động điền đơn (v1.1.1):**
   - Tích hợp chuẩn **Zalo OAuth2 PKCE** bảo mật cao, đi kèm chế độ **Mock Test Mode** cho môi trường dev/local.
   - Chuẩn hóa **Clean URL** cho Callback và Login không có đuôi file (ví dụ: `/api/public/zalo-login`, `/api/public/zalo-callback`), tương thích cả khi triển khai thư mục con (ví dụ: `/chaytt/...`).
   - Tự động nhận diện `callback_url` thông minh theo domain/base path, hỗ trợ cấu hình tường minh qua Admin hoặc `config/config.php`.
   - Tự động trích xuất thông tin Họ tên, SĐT, Avatar và đồng bộ vào `localStorage` (`public_customer_info`).
   - Tự động kích hoạt kiểm tra khách quen khi nhận diện SĐT từ Zalo.
   - Sau khi gửi đơn: Nút mở Zalo Bếp (`seller_zalo_url`) kèm nút sao chép nhanh mẫu tin nhắn xác nhận đơn (`zalo_confirm_template`).
   - Hiển thị ghi chú vận chuyển (`order_note_shipping`) rõ ràng, minh bạch.
7. **Hệ thống Auto-Migration Tinh Gọn (Zero-Dependency v1.2.0):**
   - Tự động quét và áp dụng mọi thay đổi schema SQL (`database/migrations/*.sql`) ngay khi ứng dụng khởi động hoặc kết nối CSDL lần đầu.
   - Quản lý phiên bản chặt chẽ qua bảng `migrations` trong SQLite, đảm bảo tính lũy kế (idempotent), không bao giờ chạy lại file đã áp dụng.
   - Hỗ trợ công cụ dòng lệnh (CLI): `php bin/migrate.php` (thực thi) và `php bin/migrate.php status` (kiểm tra trạng thái).
   - Đảm bảo đồng bộ cấu trúc CSDL 100% tự động trên mọi server (Local, WSL, VPS DuckDNS, staging, production) mà không cần framework cồng kềnh.
8. **Khóa Ca Cận Giờ, Giới Hạn Tuần & Liên Lạc Zalo Khách Hàng (v1.3.0):**
   - **Khóa ca cận giờ (Cut-off Lead Time):** Tự động khóa đặt món trực tuyến trước $N$ giờ (mặc định 3h, cấu hình tùy ý) để bếp kịp ninh nước dùng; cung cấp liên hệ Zalo trực tiếp nếu khách cần đặt gấp.
   - **Giới hạn thời gian đặt trước:** Giới hạn mở lịch đặt trong vòng $N$ ngày (mặc định 7 ngày / 1 tuần trở lại).
   - **Bảng tổng hợp chuẩn bị bếp cả ngày (`today_all`):** Quản lý/chủ bếp xem toàn bộ số lượng từng món cần chuẩn bị trong ngày, phân tách ca sắp tới, hôm nay và lịch sử ca.
   - **Chăm sóc khách qua Zalo trực tiếp:** Bấm 1 chạm mở Zalo chat với khách theo SĐT (`https://zalo.me/{phone}`) kèm nút sao chép mẫu tin nhắn chăm sóc đơn.
9. **Chế Độ Tạm Nghỉ & Thông Báo Khi Bếp Đi Vắng (v1.3.0 - Issue #12):**
   - Chủ quán dễ dàng bật chế độ **Tạm Nghỉ / Đi Vắng** trong trang quản trị (`admin.php` tab Cấu Hình).
   - Hỗ trợ cài đặt thời gian tạm nghỉ linh hoạt: nghỉ vô thời hạn hoặc theo khoảng ngày cụ thể (`closed_from_date` đến `closed_to_date`).
   - Tùy chỉnh lời nhắn an lành gửi khách thương (`closed_reason`).
   - Giao diện khách (`index.php`) tự động hiển thị Banner thông báo mang phong cách Ghibli/Zen ấm áp, ẩn các ca trong ngày nghỉ, khóa nút gửi đơn và dẫn link Zalo để khách liên hệ trực tiếp.
   - Quản trị viên luôn thấy Huy hiệu cảnh báo nổi bật ở đầu trang Admin khi đang bật chế độ tạm nghỉ.
10. **Hàng Đợi Thông Báo SQLite + Dashboard + Email Dự Phòng (v1.4.0 - Issue #13):**
    - **Lưu trữ Outbox cùng Transaction:** Đơn hàng và thông báo được ghi đồng thời trong SQLite Transaction `IMMEDIATE`, chống thất lạc thông báo khi server khởi động lại. Lỗi gửi thông báo hoàn toàn tách biệt, không bao giờ rollback đơn hàng đã tạo.
    - **Kênh thông báo đa dạng:** Hỗ trợ đa kênh (`email`, `web`, mở rộng `zalo` trong tương lai) với khóa sự kiện duy nhất (`event_key`) chống spam và trùng lặp.
    - **Email Dự Phòng (Zero-Dependency):** Bộ gửi email thuần PHP SMTP socket (hỗ trợ TLS/SSL/App Password) kèm fallback `mail()` native hoặc mock log, định dạng HTML ấm áp phong cách Zen/Washi gửi ngay chi tiết món, số lượng, tiền và link quản trị cho chủ bếp.
    - **Cơ chế Backoff Retry tự động:** Thử lại tự động sau 1 phút, 5 phút, 15 phút, 1 giờ. Quá 5 lần chuyển trạng thái `failed` và hiển thị cảnh báo trên Admin Dashboard.
    - **Tự động phục hồi sự cố (Crash Recovery):** Tự động phát hiện và nhặt lại các job kẹt ở `processing` quá 5 phút sau khi máy chủ khởi động lại.
    - **Trang Quản Trị Hàng Đợi (`admin.php?tab=notifications`):**
      - Thẻ thống kê: Đang chờ (`pending`), Đang gửi (`processing`), Đã gửi (`sent`), Thất bại (`failed`).
      - Nút bấm *"🚀 Xử Lý Hàng Đợi Ngay"* để gửi ngay trên web không cần đợi cron.
      - Nút *"🔄 Gửi lại"* thủ công cho từng thông báo bị lỗi.
    - **Worker linh hoạt:** Cung cấp cả Worker PHP CLI (`bin/notification_worker.php`) và Worker Python 3 (`bin/notification_worker.py`) hỗ trợ cả chế độ `--run-once` (Crontab) lẫn `--daemon --sleep=5` (Systemd service).
11. **Lịch Đặt Zalo, Nhân Bản Ca Hàng Loạt & Tự Động Đối Soát Thanh Toán (v1.5.0 - Issues #14, #15, #16):**
    - **Fix Bug Bắt Buộc Đăng Nhập Zalo:** Kiểm tra đa tầng (Backend `OrderService` + Server-side `process_order.php` + Client `app.js`), ngăn chặn hoàn toàn việc gửi đơn nếu bật `require_zalo_login` mà khách chưa đăng nhập Zalo.
    - **Lịch Đặt & Nhắc Nhở Cá Nhân Hóa (Issue #14):** Khách đăng nhập Zalo có thể xem lại lịch sử đặt chỗ kèm trạng thái thanh toán và mã đơn; trang chủ tự động hiển thị Banner nhắc nhở nhẹ nhàng về đơn đặt sắp tới gần nhất.
    - **Nhân Bản Lịch Ca Hàng Loạt & Đồng Bộ Tuần (Issue #15):** Chọn một ngày mẫu có sẵn để nhân bản nhanh cho 1 tuần hoặc 1 tháng; bộ lọc chọn thứ trong tuần mở ca linh hoạt (T2-T6, T7-CN, cả tuần); nút bấm đồng bộ các ca mở trong tuần theo ngày chỉnh sửa.
    - **Báo Cáo Chốt Sổ Ngày & Đóng Ca Lịch Sử (Issue #16 - Phần 1):** Tách biệt các ca đang phục vụ với các ca đã qua (`is_closed`); hỗ trợ chốt ca và chốt sổ toàn bộ ngày, chuyển đơn đã xác nhận sang hoàn tất; bảng báo cáo thống kê doanh thu thực tế, doanh thu chờ thu và tổng suất món đã nấu.
    - **Tự Động Xác Nhận Thanh Toán & Đối Soát Tài Chính (Issue #16 - Phần 2):**
      - Tự động nhận diện thanh toán thành công theo thời gian thực (Polling JS chu kỳ 3.5s + API Webhook `api.php?action=bank_webhook`).
12. **Quản Lý Bộ Thực Đơn Mẫu, Xoay Tua Lịch Ngày & Cảnh Báo Sửa Món (v1.6.0 - Issue #17):**
    - **Phân Loại Thực Đơn Toàn Diện:**
      - Phân loại lớn: Đồ ăn (`food`), Đồ uống (`drink`), Món thêm/tráng miệng (`extra`).
      - Phạm vi phục vụ: Cả hai (`both`), Chỉ tại quán (`dine_in_only`), Chỉ mang về (`takeaway_only`). Giao diện khách hàng tự động lọc và hiển thị đúng món theo hình thức đặt.
      - Hỗ trợ **Mâm Cỗ Chay Trọn Vị** (`is_feast_combo = 1`): Phục vụ khách đặt tiệc gia đình, cúng Rằm, giỗ chạp với danh mục món và giá mâm trọn gói.
    - **Bộ Thực Đơn Mẫu (Menu Template Suites):**
      - Quản lý các bộ thực đơn chuẩn bị sẵn: *Thực Đơn Thường Ngày (Mặc Định)*, *Thực Đơn Ngày Chẵn (T2, T4, T6)*, *Thực Đơn Ngày Lẻ (T3, T5, T7, CN)*, *Mâm Cỗ Chay An Lạc (Rằm & Tiệc)*.
      - Dễ dàng tạo mới/chỉnh sửa bộ thực đơn với bộ chọn món dạng checklist trực quan.
    - **Cơ Chế Lên Lịch Thực Đơn Linh Hoạt (Scheduling & Automation):**
      - **Quy tắc xoay tua tự động (Zero-maintenance):** Tự động đổi thực đơn theo ngày chẵn/lẻ mà không cần can thiệp thủ công.
      - **Chỉ định ngày đặc biệt (Date Override):** Đặt riêng bộ thực đơn cho ngày Rằm, Mùng 1 hoặc ngày lễ.
      - **Nhân bản lịch hàng loạt:** Áp dụng nhanh một bộ thực đơn cho chuỗi ngày liên tiếp theo các thứ trong tuần.
    - **Cảnh Báo Thay Đổi Món & Bảo Vệ Đơn Đã Đặt:**
      - Khi sửa giá, hết món hoặc đổi món: Bếp dễ dàng tra cứu danh sách toàn bộ các đơn đặt trước từ hôm nay trở đi bị ảnh hưởng.
      - Ngăn chặn xóa vĩnh viễn món ăn đã có trong lịch sử đơn hàng để bảo toàn dữ liệu đối soát.
      - Tự động sinh mẫu tin nhắn Zalo gửi tay kèm link trực tiếp `https://zalo.me/{sdt}` và hỗ trợ xếp lịch thông báo đồng loạt vào hàng đợi Outbox (`MENU_CHANGE_ALERT`).
13. **Trang Đánh Giá & Cảm Nhận Người Dùng - Có Cơ Chế Feature Flag Tạm Khóa (v1.7.0 - Issue #18):**
    - **Cơ chế Bật/Tắt chủ động (Feature Flag):** Mặc định tính năng ở trạng thái **TẮT** (`reviews_enabled = '0'`). Khi chưa cần, trang `reviews.php` hiển thị thông điệp nhẹ nhàng hẹn mở cửa trong thời gian tới và tạm khóa form gửi để tránh nhận spam. Khi bếp cần lắng nghe khách, chỉ cần 1 click trên Admin để **BẬT** ngay lập tức.
    - **Góc Cảm Nhận Khách Hàng (`reviews.php`):**
      - Thang điểm đánh giá 1-5 sao trực quan kèm thống kê điểm số trung bình và phân bổ số sao.
      - Tự động gắn cờ **✓ Đã dùng bữa** (`is_verified_buyer = 1`) khi khách nhập đúng mã đơn hàng đã đặt.
      - Chống spam gửi nhiều lần cho cùng một mã đơn.
    - **Kiểm Duyệt & Phản Hồi Từ Bếp (`admin.php?tab=reviews`):**
      - Thống kê thời gian thực: Chờ duyệt (`pending`), Đang hiển thị (`approved`), Đã ẩn (`hidden`).
      - Nút Duyệt hiển thị, Ẩn đánh giá hoặc Xóa nhanh gọn.
      - Khung phản hồi trực tiếp: Bếp có thể gửi lời cảm ơn hoặc giải đáp thắc mắc, hiển thị ngay bên dưới đánh giá của khách trên web.

---

## 📁 Cấu trúc Thư mục

```text
D:\Quan\VeganFoodShop\
├── AGENTS.md                                   # Quy tắc vận hành & tiêu chuẩn AI Agent
├── AGENT_RULES.md                              # Quy chuẩn kỹ thuật, bảo mật & checklist
├── Mô-hình-và-giải-pháp-đặt-chỗ-quán-chay.md  # Tài liệu giải pháp & thiết kế gốc
├── README.md                                   # Tài liệu hướng dẫn hệ thống
├── index.php                                   # Màn hình đặt món / booking chính cho khách
├── process_order.php                           # Xử lý tiếp nhận đơn & sinh mã VietQR
├── admin.php                                   # Trang quản trị: lịch ca, bếp, đơn hàng, khách quen
├── api.php                                     # AJAX endpoint: kiểm tra SĐT khách quen, lấy ca
├── zalo_callback.php                           # Endpoint tiếp nhận Zalo OAuth callback & đồng bộ
├── phpunit.xml                                 # Cấu hình test PHPUnit
├── bin/
│   ├── migrate.php                             # CLI runner áp dụng & xem trạng thái Migration
│   ├── notification_worker.php                 # PHP CLI worker xử lý hàng đợi thông báo
│   └── notification_worker.py                  # Python 3 worker xử lý hàng đợi thông báo (zero dependencies)
├── config/
│   ├── config.php                              # Cấu hình cơ sở mặc định (môi trường dev/test)
│   ├── config.example.php                      # File mẫu cấu hình môi trường Production
│   └── config.local.php                        # Cấu hình môi trường thực tế (bảo mật, không commit Git)
├── database/
│   ├── bep_an_yen.db                           # CSDL SQLite3 chính (chế độ WAL)
│   ├── schema.sql                              # Cấu trúc 7 bảng CSDL & chỉ mục
│   ├── seed.sql                                # Dữ liệu mẫu (món ăn, ca mẫu, khách mẫu)
│   └── migrations/                             # Thư mục lưu trữ các file migration tuần tự
│       ├── 001_initial_schema.sql
│       ├── 002_add_zalo_to_customers.sql
│       └── 003_create_notification_outbox.sql
├── src/
│   ├── autoload.php                            # Bộ nạp lớp tự động PSR-4 thuần
│   ├── Database/
│   │   └── Connection.php                      # Quản lý kết nối PDO SQLite singleton & auto-migration
│   ├── Exceptions/                             # Custom Domain Exceptions
│   │   ├── DomainException.php
│   │   ├── SlotFullException.php
│   │   └── ValidationException.php
│   └── Services/                               # Tầng nghiệp vụ (Service Layer)
│       ├── CustomerService.php
│       ├── SlotService.php
│       ├── MenuService.php
│       ├── OrderService.php
│       ├── VietQrService.php
│       ├── ConfigService.php
│       ├── ZaloAuthService.php
│       ├── MigrationService.php                # Quản lý & tự động thực thi Auto-Migration CSDL
│       ├── EmailService.php                    # Gửi email thông báo đơn mới qua SMTP / mail()
│       └── NotificationService.php             # Quản lý hàng đợi Outbox, thử lại & backoff
├── assets/
│   ├── css/
│   │   └── style.css                           # Bảng màu Washi, xanh trà, bo góc Ghibli
│   └── js/
│       └── app.js                              # Giỏ hàng, tính tiền thời gian thực, AJAX SĐT
├── docs/                                       # Tài liệu đặc tả kỹ thuật chi tiết
│   ├── BUSINESS_FLOW.md
│   ├── DB_DESIGN.md
│   ├── SCREEN_DESIGN.md
│   ├── TESTING.md
│   └── IMPLEMENTATION_PLAN.md
└── tests/                                      # Bộ kiểm thử tự động cô lập 100% CSDL
    ├── TestCase.php                            # Base test case (SQLite :memory:)
    ├── run_tests.php                           # Test runner độc lập
    ├── Unit/                                   # Kiểm thử đơn vị
    │   ├── CustomerServiceTest.php
    │   ├── SlotCapacityTest.php
    │   ├── OrderCalculationTest.php
    │   ├── ZaloAuthServiceTest.php
    │   └── MigrationServiceTest.php
    └── Integration/                            # Kiểm thử tích hợp
        ├── CreateOrderTransactionTest.php
        └── SlotConcurrencyTest.php
```

---

## 🚀 Hướng Dẫn Chạy Cục Bộ (Local Development)

### 1. Khởi chạy Web Server tích hợp
Mở terminal tại thư mục dự án và chạy (hỗ trợ Clean URLs không cần đuôi `.php`):
```powershell
php -S localhost:8008 router.php
```
Mở trình duyệt truy cập:
- **Trang Đặt Món Khách Hàng:** `http://localhost:8008` (Bật DevTools giả lập màn hình di động 390x844 để có trải nghiệm tốt nhất).
- **Trang Quản Trị Bếp:** `http://localhost:8008/admin` (hoặc `http://localhost:8008/admin.php`)
  - Mật khẩu quản trị mặc định: `AnYen@2026`
- **Hướng dẫn cấu hình Nginx/Apache Rewrite chi tiết:** Xem tại [docs/URL_REWRITE_GUIDE.md](docs/URL_REWRITE_GUIDE.md).

---

## 🧪 Chạy Bộ Kiểm Thử Tự Động (Automated Testing)

Toàn bộ test suite được cô lập 100% trên **SQLite In-Memory (`:memory:`)**, hoàn toàn không ghi dữ liệu vào CSDL runtime (`bep_an_yen.db`):

```powershell
php tests/run_tests.php
```

Hoặc qua PHPUnit:
```powershell
vendor/bin/phpunit
```

---

## 🌐 Hướng Dẫn Triển Khai Trên Linux Debian (Production)

### 1. Cài đặt các gói cần thiết
```bash
sudo apt update
sudo apt install nginx php-fpm php-sqlite3 sqlite3 -y
```

### 2. Cấu hình VirtualHost Nginx
Tạo file `/etc/nginx/sites-available/veganfoodshop`:
```nginx
server {
    listen 80;
    server_name yourdomain.com; # Thay bằng domain hoặc IP của bạn
    root /var/www/veganfoodshop;

    index index.php index.html;

    # Bảo vệ file database SQLite tuyệt đối không cho tải về từ web
    location ~ \.(db|sqlite|sqlite3|sql)$ {
        deny all;
        return 404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }
}
```

Kích hoạt và nạp lại Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/veganfoodshop /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 3. Phân quyền thư mục CSDL
```bash
sudo chown -R www-data:www-data /var/www/veganfoodshop/database
sudo chmod 750 /var/www/veganfoodshop/database
```

---

## 🌿 Bản Quyền & Định Vị

Hệ thống được thiết kế với tâm niệm mang lại sự thanh thản, chu đáo và tiện lợi cho cả thực khách lẫn người đứng bếp.
*"Một bát canh thanh ngọt, vỗ về những bộn bề ngày thường"*
