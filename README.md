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
6. **Đăng nhập Zalo (Tùy chọn) & Tự động điền đơn (v1.1.0):**
   - Tích hợp chuẩn **Zalo OAuth2 PKCE** bảo mật cao, đi kèm chế độ **Mock Test Mode** cho môi trường dev/local.
   - Tự động trích xuất thông tin Họ tên, SĐT, Avatar và đồng bộ vào `localStorage` (`public_customer_info`).
   - Tự động kích hoạt kiểm tra khách quen khi nhận diện SĐT từ Zalo.
   - Sau khi gửi đơn: Nút mở Zalo Bếp (`seller_zalo_url`) kèm nút sao chép nhanh mẫu tin nhắn xác nhận đơn (`zalo_confirm_template`).
   - Hiển thị ghi chú vận chuyển (`order_note_shipping`) rõ ràng, minh bạch.

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
├── config/
│   └── config.php                              # Cấu hình hệ thống (VietQR, Zalo Login, admin, CSDL)
├── database/
│   ├── bep_an_yen.db                           # CSDL SQLite3 chính (chế độ WAL)
│   ├── schema.sql                              # Cấu trúc 6 bảng CSDL & chỉ mục
│   └── seed.sql                                # Dữ liệu mẫu (món ăn, ca mẫu, khách mẫu)
├── src/
│   ├── autoload.php                            # Bộ nạp lớp tự động PSR-4 thuần
│   ├── Database/
│   │   └── Connection.php                      # Quản lý kết nối PDO SQLite singleton
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
│       └── ZaloAuthService.php
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
    │   └── OrderCalculationTest.php
    └── Integration/                            # Kiểm thử tích hợp
        ├── CreateOrderTransactionTest.php
        └── SlotConcurrencyTest.php
```

---

## 🚀 Hướng Dẫn Chạy Cục Bộ (Local Development)

### 1. Khởi chạy Web Server tích hợp
Mở terminal tại thư mục dự án và chạy:
```powershell
php -S localhost:8008
```
Mở trình duyệt truy cập:
- **Trang Đặt Món Khách Hàng:** `http://localhost:8008` (Bật DevTools giả lập màn hình di động 390x844 để có trải nghiệm tốt nhất).
- **Trang Quản Trị Bếp:** `http://localhost:8008/admin.php`
  - Mật khẩu quản trị mặc định: `AnYen@2026`

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
