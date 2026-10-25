# AGENTS.md - Quy Tắc Vận Hành & Phát Triển VeganFoodShop

## Quy tắc bắt buộc cho AI Agent

Trước khi sửa code hoặc chạy test, Agent phải đọc kỹ [AGENT_RULES.md](AGENT_RULES.md) và tuân thủ các quy tắc kiến trúc, quy tắc kiểm thử và checklist tại đó. Tài liệu này đóng vai trò bổ sung quy trình vận hành và tiêu chuẩn chi tiết cho repo. Ngoài ra, Agent phải tham khảo tài liệu thiết kế nghiệp vụ [Mô-hình-và-giải-pháp-đặt-chỗ-quán-chay.md](Mô-hình-và-giải-pháp-đặt-chỗ-quán-chay.md).

---

## 1. Mục tiêu & Định vị dự án

**VeganFoodShop (Bếp Chay An Yên)** là hệ thống web cá nhân phục vụ việc **Đặt Chỗ & Đặt Món Trước (Slot & Batch Pre-order)** cho quán ẩm thực chay, hoạt động linh hoạt theo từng khung giờ và số lượng suất giới hạn trong ngày/tuần.

### Các mục tiêu cốt lõi:
1. **Tinh gọn & Nhẹ tênh (Lightweight):**
   - Chạy ổn định, mượt mà trên môi trường máy chủ Linux Debian (hoặc VPS cấu hình khiêm tốn).
   - Triển khai bằng stack: **Nginx + PHP-FPM (PHP 8.4+) + SQLite3 + Mobile-First Frontend**.
   - Hạn chế tối đa các dependency cồng kềnh, không dùng ORM nặng nề hay framework phức tạp khi không cần thiết.
2. **Trải nghiệm di động (Mobile-First) mang phong cách "Hơi thở Ghibli & Thiền Vị (Zen)":**
   - Mang lại cảm giác mộc mạc, bình an, ấm áp như một "chốn quay về an yên tĩnh lặng", không tạo áp lực hối hả như các sàn thương mại điện tử công nghiệp.
   - Luồng đặt món cuộn dọc trực quan, nhẹ nhàng theo từng bước (Story-telling Flow).
3. **Mô hình Khung giờ & Khóa suất tự động (Time Slot & Capacity Management):**
   - Mở lịch bán theo ca (ví dụ: Ca trưa 11:30 - 12:15, 12:30 - 13:15).
   - Tự động đóng/khóa khung giờ khi đã nhận đủ số suất phục vụ tối đa (`max_capacity`).
   - Hỗ trợ 2 hình thức: **Dùng tại quán (`dine_in`)** & **Gửi ship mang về (`takeaway`)**.
4. **Cơ chế thanh toán linh hoạt & Nhận diện khách quen:**
   - Khách mới: Quét mã **VietQR** chuyển khoản thanh toán hoặc đặt cọc giữ chỗ.
   - Khách quen (`is_trusted = 1`): Tự động nhận diện qua số điện thoại để chào đón thân thiện và cho phép tùy chọn **"Thanh toán sau tại bếp" (`pay_later`)** mà không bắt buộc thanh toán trước.

---

## 2. Nguồn sự thật của nghiệp vụ (Sources of Truth)

Khi cần tìm hiểu hoặc làm rõ logic nghiệp vụ, đọc theo thứ tự ưu tiên:
1. [Mô-hình-và-giải-pháp-đặt-chỗ-quán-chay.md](Mô-hình-và-giải-pháp-đặt-chỗ-quán-chay.md) (Tài liệu kiến trúc, concept và luồng nghiệp vụ gốc).
2. `README.md` (Hướng dẫn tổng quan và cấu trúc repo hiện hành).
3. `docs/DB_DESIGN.md` (Thiết kế cơ sở dữ liệu và bảng biểu SQLite chi tiết).
4. `docs/BUSINESS_FLOW.md` (Luồng đặt chỗ, khóa ca, xử lý đơn, xác nhận khách quen).
5. Mã nguồn backend (`index.php`, `process_order.php`, `admin.php` hoặc các service trong `src/`).
6. Cơ sở dữ liệu SQLite thực tế (`bep_an_yen.db`).

*Lưu ý:* Tuyệt đối không tự suy diễn hoặc thay đổi hành vi nghiệp vụ đã được document nếu chưa có sự thống nhất và xác nhận từ người dùng.

---

## 3. Kiến trúc kỹ thuật & Cấu trúc dự án

### Stack kỹ thuật chuẩn
- **Hệ điều hành máy chủ:** Linux Debian (tương thích Debian 11/12).
- **Web Server:** Nginx (đóng vai trò Reverse Proxy, phục vụ static asset và cấu hình bảo vệ file `.db`).
- **Backend Runtime:** PHP 8.4+ (chạy qua PHP-FPM).
- **Cơ sở dữ liệu:** SQLite3 (lưu trữ dưới dạng file đơn lẻ, không cần daemon MySQL).
- **Frontend:** HTML5 chuẩn Semantic, CSS (Tailwind CSS hoặc Vanilla CSS theo bảng màu Ghibli/Washi), JavaScript Vanilla (xử lý AJAX kiểm tra khách quen, tính toán đơn hàng, cập nhật slot tức thời).

### Cấu trúc thư mục định hướng
```text
D:\Quan\VeganFoodShop\
├── AGENTS.md                                   # Bộ quy tắc dành cho AI Agent (File này)
├── Mô-hình-và-giải-pháp-đặt-chỗ-quán-chay.md  # Tài liệu giải pháp & thiết kế gốc
├── README.md                                   # Giới thiệu & hướng dẫn cài đặt/chạy
├── index.php                                   # Màn hình đặt món / booking chính cho khách
├── process_order.php                           # Xử lý tiếp nhận đơn & sinh mã thanh toán VietQR
├── admin.php                                   # Trang quản trị: lịch ca, thực đơn, khách quen, đơn hàng
├── config/                                     # Cấu hình hệ thống (VietQR, cấu hình chung)
│   └── config.php
├── database/                                   # File CSDL SQLite & scripts migration/seed
│   ├── bep_an_yen.db                           # CSDL SQLite chính (bảo mật, không public web)
│   ├── schema.sql                              # Cấu trúc bảng CSDL
│   └── seed.sql                                # Dữ liệu mẫu (món ăn, ca mẫu)
├── assets/                                     # Tài nguyên tĩnh
│   ├── css/                                    # Stylesheet (màu Washi, typography, hiệu ứng Ghibli)
│   ├── js/                                     # Scripts client-side (AJAX, validation)
│   └── images/                                 # Hình ảnh vẽ tay, icon, logo mộc
├── docs/                                       # Tài liệu bổ trợ (DB, hướng dẫn triển khai Nginx...)
└── logs/                                       # Thư mục lưu trữ log vận hành (nếu có)
```

---

## 4. Chuẩn phong cách thiết kế Giao diện (Ghibli & Zen UI/UX)

Giao diện của quán chay mang tính cá nhân, định vị là **"chốn quay về an yên tĩnh lặng"**, phải tuân thủ nghiêm ngặt các nguyên tắc thị giác:

### 4.1. Bảng màu chủ đạo (Color Palette)
- **Nền chính (Washi / Kem ấm):** `#FAF7F2` hoặc `#F4EFEA` (tạo cảm giác dịu mắt như trang giấy thủ công, tuyệt đối không dùng màu trắng tinh `#FFFFFF` chói chang làm nền cả trang).
- **Màu thiên nhiên / Điểm nhấn:**
  - Xanh lá trà dịu: `#5B7065` hoặc `#606C5D` (dùng cho nút bấm chính, tiêu đề phụ, viền thẻ active).
  - Nền phụ / Thẻ trạng thái nhẹ: `#EEF2E6` hoặc `#DDE5D4`.
  - Nâu gỗ nhạt: `#8C7A6B`.
- **Màu chữ:** Nâu than mờ `#2C2A29` hoặc `#344037` (như nét mực mộc trên nền giấy, không dùng màu đen tuyền `#000000`).
- **Thẻ Card:** Màu nền trắng mềm `#FFFFFF`, bo góc lớn mềm mại (`border-radius: 20px - 24px`), đổ bóng cực nhẹ `rgba(91, 112, 101, 0.08)`.

### 4.2. Kiểu chữ (Typography)
- **Tiêu đề & Châm ngôn:** Sử dụng font Serif thanh thoát, ấm cúng có hỗ trợ tiếng Việt (ví dụ: *Lora*, *Playfair Display*).
- **Thân bài, Mô tả món & Nút bấm:** Sử dụng font Sans-serif mộc mạc, bo tròn, dễ đọc trên màn hình điện thoại (ví dụ: *Quicksand*, *Be Vietnam Pro*).

### 4.3. Luồng trải nghiệm di động (Story-telling Flow)
1. **Header & Lời ngỏ an lành:** Câu chào ấm áp, thông điệp nhẹ nhàng kèm bảng thông báo lịch phục vụ hôm nay.
2. **Bước 1 - Chọn hình thức:** Nút gạt nhẹ nhàng giữa *“Ghé bếp ăn tại chỗ”* (`dine_in`) và *“Bếp gửi ship về”* (`takeaway`).
3. **Bước 2 - Chọn khung giờ (Time Slots):** Các ô chọn khung giờ hiển thị rõ số suất còn lại. Tự động mờ đi và vô hiệu hóa nếu ca đã đầy.
4. **Bước 3 - Món lành hôm nay:** Danh mục món tinh tuyển (Phở nấm hương, Bún Huế chay, Bún riêu chay, Trà sen tuyết...). Nút tăng giảm số lượng gọn gàng.
5. **Bước 4 - Thông tin & Thanh toán:**
   - Nhập Tên & Số điện thoại.
   - Khi nhập xong SĐT (sự kiện `blur`), tự động gọi AJAX kiểm tra danh sách `customers`.
   - Nếu `is_trusted == 1`: Hiển thị badge chào đón *"Chào bạn quen trở lại! Bạn có thể thanh toán sau khi ghé quán"* và mở radio *"Thanh toán khi dùng bữa / nhận món"*.
   - Khách mới: Mặc định chọn *"Quét mã VietQR chuyển khoản"* để giữ suất.

---

## 5. Quy ước Cơ sở dữ liệu SQLite

### 5.1. Bảng dữ liệu cốt lõi
Hệ thống sử dụng SQLite3 với 4 bảng dữ liệu chính theo thiết kế:
1. `customers (phone TEXT PRIMARY KEY, name TEXT, is_trusted INTEGER DEFAULT 0, created_at DATETIME)`
2. `time_slots (id INTEGER PRIMARY KEY AUTOINCREMENT, slot_date TEXT, slot_time TEXT, max_capacity INTEGER, is_active INTEGER DEFAULT 1)`
3. `menu_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, description TEXT, price INTEGER, category TEXT, is_available INTEGER DEFAULT 1)`
4. `orders (id INTEGER PRIMARY KEY AUTOINCREMENT, customer_phone TEXT, slot_id INTEGER, order_type TEXT, items_json TEXT, total_amount INTEGER, payment_method TEXT, payment_status TEXT, note TEXT, created_at DATETIME)`

### 5.2. Quy tắc thao tác CSDL
- **Kiểm tra Schema thực tế trước khi code:**
  - Không tự đoán tên cột (ví dụ: `phone` vs `customer_phone`, `slot_time` vs `time_slot`).
  - Luôn kiểm tra `PRAGMA table_info(table_name)` hoặc file `schema.sql` trước khi viết câu truy vấn.
- **Bảo mật tuyệt đối (Prepared Statements):**
  - Mọi thao tác truy vấn có chứa tham số do người dùng gửi lên bắt buộc phải dùng Prepared Statements qua `PDO` hoặc `SQLite3Stmt` với `bindValue`/`bindParam`. Tuyệt đối cấm nối chuỗi SQL trực tiếp.
- **Bảo vệ file CSDL:**
  - File database SQLite (`.db`) phải đặt trong thư mục có quyền hạn phù hợp và phải được Nginx chặn truy cập trực tiếp từ trình duyệt (`location ~ \.db$ { deny all; }`).
- **An toàn giao dịch (Transactions):**
  - Khi tạo đơn và trừ/tính suất ca, sử dụng Transaction (`BEGIN TRANSACTION` / `COMMIT`) để tránh race condition khi nhiều khách cùng đặt một ca vào thời điểm cận ngưỡng `max_capacity`.

---

## 6. Quy tắc mã hóa ký tự (UTF-8 Encoding Standard)

- Dự án hoàn toàn sử dụng tiếng Việt có dấu. Bắt buộc 100% file mã nguồn (`.php`, `.html`, `.css`, `.js`, `.sql`, `.md`, `.json`) phải được lưu ở định dạng **UTF-8 không BOM** (No BOM).
- Các response HTTP trả về từ PHP bắt buộc phải có header UTF-8:
  - Trang HTML: `header('Content-Type: text/html; charset=UTF-8');` hoặc thẻ `<meta charset="UTF-8">`.
  - API JSON: `header('Content-Type: application/json; charset=UTF-8');` và dùng `json_encode($data, JSON_UNESCAPED_UNICODE)`.
- Khi thao tác chuỗi tiếng Việt trong PHP, ưu tiên các hàm `mb_*` (ví dụ `mb_strlen`, `mb_substr`, `mb_strtolower`).

---

## 7. Quy tắc lập trình & Bảo mật

1. **Clean Code & Đơn giản:**
   - Ưu tiên cấu trúc code rõ ràng, hàm ngắn gọn, có chú thích giải thích lý do xử lý.
   - Tách biệt logic xử lý dữ liệu với giao diện HTML khi mở rộng.
2. **Phòng chống XSS:**
   - Mọi dữ liệu in ra màn hình từ người dùng hoặc CSDL phải được bọc qua `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
3. **Quản trị Admin an toàn:**
   - Màn hình quản lý (`admin.php`) cần có cơ chế bảo vệ bằng mật khẩu phiên làm việc (Session) hoặc HTTP Basic Auth.
   - Cho phép quản trị viên: bật/tắt ca, điều chỉnh số suất tối đa, đánh dấu khách quen (`is_trusted = 1`), xem danh sách đơn theo từng ca.
4. **Không làm rò rỉ thông tin nhạy cảm:**
   - Không in chi tiết lỗi hệ thống (traceback, SQL error) ra giao diện người dùng. Log lỗi vào file hoặc màn hình console/syslog.
   - Không hiển thị số điện thoại đầy đủ của khách hàng khác ra giao diện công khai.
5. **Không hard-code các thông tin nhận diện riêng của quán (Cá nhân hóa dự án):**
   - Tuyệt đối không gán cứng tên quán, địa chỉ, hotline, email liên hệ, slogan... trong mã nguồn hoặc HTML.
   - Mọi thông tin nhận diện phải truy xuất qua `ConfigService` với cơ chế 3 tầng: CSDL SQLite (`system_config`) -> File cấu hình (`config/config.local.php` / `config/config.php`) -> Fallback mặc định chung.
   - Luôn cung cấp đầy đủ các trường nhập liệu trong trang Quản trị (`admin.php` tab Cấu hình) để chủ bếp dễ dàng cá nhân hóa.

---

## 8. Hướng dẫn Chạy & Triển khai

### 8.1. Chạy thử nghiệm Local (Development)
Sử dụng PHP Built-in Server:
```powershell
# Chạy tại thư mục gốc của project VeganFoodShop:
php -S localhost:8008
```
Mở trình duyệt truy cập: `http://localhost:8008` (dùng DevTools giả lập màn hình Mobile iPhone/Android để xem đúng trải nghiệm).

### 8.4. Cấu hình triển khai trên Linux Debian (Production)
Cài đặt các gói cần thiết trên Debian:
```bash
sudo apt update
sudo apt install nginx php-fpm php-sqlite3 -y
```

Cấu hình VirtualHost Nginx tại `/etc/nginx/sites-available/veganfoodshop`:
```nginx
server {
    listen 80;
    server_name yourdomain.com; # Thay bằng domain hoặc IP máy chủ
    root /var/www/veganfoodshop;

    index index.php index.html;

    # Bảo vệ file database SQLite tuyệt đối không cho tải về
    location ~ \.(db|sqlite|sqlite3|sql)$ {
        deny all;
        return 404;
    }

    # Hỗ trợ Clean URLs (truy cập /admin thay vì /admin.php)
    location / {
        try_files $uri $uri/ $uri.php?$query_string /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php-fpm.sock; # Chỉnh đúng socket phiên bản PHP trên Debian
    }
}
```
Kích hoạt cấu hình:
```bash
sudo ln -s /etc/nginx/sites-available/veganfoodshop /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 9. Kiểm tra bắt buộc sau khi sửa code

Trước khi bàn giao hoặc commit bất kỳ thay đổi nào:
1. **Kiểm tra cú pháp PHP (PHP Syntax Lint):**
   ```powershell
   php -l index.php
   php -l process_order.php
   # hoặc quét toàn bộ file .php
   Get-ChildItem -Filter *.php -Recurse | ForEach-Object { php -l $_.FullName }
   ```
2. **Kiểm tra cú pháp JavaScript (nếu có file .js riêng):**
   ```powershell
   node --check assets/js/app.js
   ```
3. **Chạy bộ kiểm thử tự động (Unit / Integration Test):**
   ```powershell
   vendor/bin/phpunit
   ```
   *Lưu ý quan trọng:* Tuân thủ quy định tại [AGENT_RULES.md](AGENT_RULES.md) - toàn bộ test phải chạy trên CSDL tạm hoặc `:memory:`, **tuyệt đối không chạy test ghi đè vào `bep_an_yen.db`**.
4. **Kiểm tra trải nghiệm di động:**
   - Thao tác thử trên viewport mobile (375px - 430px).
   - Kiểm tra hiển thị bàn phím ảo không che khuất nút gửi đơn.
   - Kiểm tra luồng AJAX kiểm tra số điện thoại khách quen phản hồi nhanh, mượt mà.
   - Kiểm tra mã VietQR hiển thị rõ ràng, quét được, đúng số tiền và nội dung đơn.

---

## 10. Quy ước Git theo Issue & Quản lý phiên bản

### 10.1. Quy ước Branch & Commit theo Issue
- Mỗi nhiệm vụ hoặc tính năng phải được quản lý theo mã Issue riêng.
- Format tên branch: `codex/{IssueNo}_{IssueName}`
  - Thay dấu cách, dấu phẩy, dấu hai chấm bằng dấu gạch dưới `_`.
  - Giữ nguyên tiếng Việt có dấu nếu Git hỗ trợ.
  - Ví dụ: `codex/01_Khoi_tao_giao_dien_dat_cho_Ghibli`
- Mỗi Issue có commit riêng, thông điệp commit rõ ràng: `Issue #{IssueNo}: {Nội dung thực hiện}`.
- Không tự ý đưa các file ngoài luồng hoặc file untracked lạ vào commit.

### 10.2. Quy ước Tăng Version (Semantic Versioning)
Phiên bản hệ thống có định dạng `x.y.z`:
- **Sửa lỗi nhỏ / tinh chỉnh giao diện / sửa text (Issue nhỏ):** Tăng `z` (Ví dụ: `1.0.0` -> `1.0.1`).
- **Thêm tính năng mới / thay đổi luồng nghiệp vụ / thêm bảng DB (Issue trung bình):** Tăng `y`, reset `z` về 0 (Ví dụ: `1.0.1` -> `1.1.0`).
- **Nâng cấp kiến trúc lớn / thay đổi toàn bộ hệ thống (Issue lớn):** Tăng `x`, reset `y` và `z` về 0 (Ví dụ: `1.1.0` -> `2.0.0`).

---

## 11. Prompt khởi động cho AI Agent trong repo này

Khi bắt đầu một phiên làm việc mới trên repository này, AI Agent nên được khởi động với prompt:

```text
Read AGENTS.md, Mô-hình-và-giải-pháp-đặt-chỗ-quán-chay.md, and README.md first.
Respect the Ghibli/Zen aesthetic (Washi paper, tea green, warm muted brown) and lightweight PHP/SQLite architecture on Debian Linux.
Ensure mobile-first UX, slot capacity limits, and flexible customer recognition (trusted phone numbers).
Run php -l on modified PHP files before finishing.
```
