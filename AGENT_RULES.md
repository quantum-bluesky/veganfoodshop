# Quy tắc bắt buộc cho AI Agent (AGENT_RULES.md)

Các quy tắc này là tiêu chuẩn kỹ thuật bắt buộc áp dụng trước khi Agent đọc sâu, thiết kế, sửa code hoặc chạy bất kỳ bài test nào trong repository **VeganFoodShop** (`D:\Quan\VeganFoodShop`). Không tự suy diễn kiến trúc ngoài các quy chuẩn đã định sẵn. Khi code, cấu hình và tài liệu mâu thuẫn, hãy xác minh nguồn hiện hành và trao đổi trước khi thay đổi hành vi.

---

## 1. Đọc và xác minh bối cảnh

- **Đọc trước khi làm:** Luôn đọc `AGENTS.md`, `README.md`, `Mô-hình-và-giải-pháp-đặt-chỗ-quán-chay.md` cùng tài liệu thiết kế nghiệp vụ liên quan trong thư mục `docs/` (`docs/DB_DESIGN.md`, `docs/BUSINESS_FLOW.md`, `docs/SCREEN_DESIGN.md`, `docs/TESTING.md`).
- **Xác định phạm vi ảnh hưởng:** Xác minh rõ thay đổi thuộc tầng nào (Giao diện Mobile, Route/Controller, Service nghiệp vụ hay Schema SQLite).
- **Kiểm tra phụ thuộc (Dependencies):** Repo sử dụng **PHP 8.4+** trên Debian Linux, ưu tiên sự tinh giản, nhẹ tênh (Lightweight). Không tự ý cài đặt thêm các package Composer hoặc framework nặng nề (như Laravel, Symfony ORM, Doctrine) nếu chưa có sự đồng thuận từ người dùng. Mọi dependency bổ sung phải có lý do kỹ thuật rõ ràng.

---

## 2. Tiêu chuẩn Coding & Kiến trúc Backend PHP 8.4+

### 2.1. Phân tầng kiến trúc tinh gọn
Hệ thống tuân thủ kiến trúc phân tầng rõ ràng, không viết code kiểu Spaghetti (không lồng trực tiếp logic tính tiền hoặc truy vấn SQL thô trong các file hiển thị HTML):
1. **Entrypoints / Controllers mỏng (`index.php`, `process_order.php`, `admin.php` hoặc Action Controllers):**
   - Tiếp nhận HTTP Request (GET/POST/AJAX).
   - Xác thực dữ liệu đầu vào (Validation & Sanitize).
   - Gọi Service tương ứng để xử lý nghiệp vụ.
   - Trả về HTML view hoặc JSON response.
2. **Service Layer (`src/Services/`):**
   - Đảm nhận toàn bộ nghiệp vụ: kiểm tra số lượng suất còn lại của ca (`SlotService`), kiểm tra khách quen (`CustomerService`), tạo đơn và sinh thông tin VietQR (`OrderService`).
3. **Data Access Layer (`src/Database/` hoặc Repository/Store):**
   - Thực thi các câu lệnh truy vấn SQLite qua PDO.
   - Đóng gói logic transaction và lock an toàn.

### 2.2. Tiêu chuẩn viết code PHP 8.4
- Luôn bật chế độ kiểm tra kiểu nghiêm ngặt ở đầu mọi file `.php`:
  ```php
  declare(strict_types=1);
  ```
- Sử dụng các tính năng hiện đại, an toàn của PHP 8.4: Typed properties, Constructor property promotion, Match expressions, Nullsafe operator `?->`, Asymmetric visibility hoặc Property Hooks khi thích hợp.
- **Phòng chống SQL Injection:** Bắt buộc 100% câu truy vấn có tham số phải dùng PDO Prepared Statements với `bindValue()` hoặc `execute([':param' => $value])`. Tuyệt đối cấm nối chuỗi SQL.
- **Phòng chống XSS:** Mọi dữ liệu do người dùng nhập hoặc lấy từ DB ra để hiển thị trên HTML bắt buộc phải được bọc qua:
  ```php
  htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  ```
- **Xử lý Exception & Ghi log:**
  - Không bắt exception bừa bãi rồi trả về HTTP 200 hoặc nuốt lỗi.
  - Sử dụng custom domain exceptions (ví dụ: `SlotFullException`, `InvalidCustomerException`).
  - Ghi log lỗi có đầy đủ timestamp, ngữ cảnh và stack trace vào `logs/app.log` hoặc PHP error log.
  - Tuyệt đối không để lộ thông tin nhạy cảm (stack trace, path server, câu lệnh SQL lỗi) ra màn hình client.

### 2.3. Không hard-code các thông tin nhận diện riêng của quán (No Hardcoded Identifiers)
Để đảm bảo khả năng cá nhân hóa linh hoạt cho các chủ bếp khác nhau sử dụng lại mã nguồn:
1. **Tuyệt đối không gán cứng (hard-code) các thông tin định danh cá nhân / quán:**
   - Bao gồm: Tên quán (`shop_name` / `brand_name`), Địa chỉ (`shop_address` / `seller_address`), Số điện thoại / Hotline (`seller_phone`), Email liên hệ (`seller_email`), Câu châm ngôn (`shop_quote`), Đường link Zalo (`seller_zalo_url`), Thông tin tài khoản ngân hàng VietQR...
   - Mọi thông tin này phải được truy xuất động qua `ConfigService::get($key, $default)`.
2. **Cơ chế 3 tầng phân giải cấu hình (3-Tier Config Resolution):**
   - **Tầng 1 (Ưu tiên cao nhất):** CSDL SQLite (`system_config`). Chủ bếp có thể tùy chỉnh trực tiếp qua giao diện Quản trị `admin.php` và lưu vào DB.
   - **Tầng 2 (Dự phòng từ tệp tin):** Tệp cấu hình `config/config.local.php` hoặc `config/config.php`. `ConfigService` bắt buộc tự động nạp và tra cứu từ các tệp này khi DB chưa có giá trị.
   - **Tầng 3 (Fallback cuối cùng):** Giá trị mặc định chung chung (placeholder) mang tính mẫu chuẩn, không được gán số điện thoại hay địa chỉ ngẫu nhiên.
3. **Cung cấp form cấu hình đầy đủ trong Trang Quản Trị (`admin.php`):**
   - Tab Cấu hình hệ thống phải có đầy đủ các trường nhận diện: Tên quán, Địa chỉ, Hotline, Email, Zalo URL... để bất kỳ người dùng nào khi tải mã nguồn về cũng có thể vận hành quán của mình một cách trọn vẹn.

---

## 3. Quản lý Cơ sở dữ liệu SQLite & Xử lý đồng thời (Concurrency)

### 3.1. Đối chiếu Schema trước khi code
- Trước khi viết bất kỳ câu lệnh SQL nào, phải đối chiếu với `docs/DB_DESIGN.md` và kiểm tra bảng thực tế qua `PRAGMA table_info(table_name)`. Không tự ý suy đoán tên cột.
- Bật hỗ trợ Foreign Keys cho mọi kết nối SQLite:
  ```php
  $pdo->exec('PRAGMA foreign_keys = ON;');
  ```

### 3.2. Cơ chế chống Race Condition khi đặt ca cận ngưỡng
- Vì quán có giới hạn số suất tối đa mỗi ca (`max_capacity`), khi có nhiều khách cùng đặt ca cuối cùng tại cùng một thời điểm:
  - Bắt buộc phải sử dụng SQLite Transaction với cờ `IMMEDIATE`:
    ```php
    $pdo->beginTransaction(); // Hoặc $pdo->exec('BEGIN IMMEDIATE TRANSACTION;');
    ```
  - Khóa dòng và kiểm tra số suất đã đặt trong ca trước khi thêm đơn hàng mới.
  - Nếu đã đủ suất (`current_orders >= max_capacity`), rollback transaction và ném `SlotFullException` để thông báo cho khách chọn ca khác.

### 3.3. Quy trình Migration
- Không chỉnh sửa trực tiếp schema trên database production mà không có kịch bản migration.
- Quản lý phiên bản CSDL thông qua `PRAGMA user_version` hoặc bảng `schema_migrations`.
- Mọi câu lệnh migration cột/bảng mới phải được thử nghiệm trên bản sao DB test trước khi áp dụng.

---

## 4. Quy tắc Kiểm thử & Cô lập Môi trường Test

### 4.1. Bộ công cụ kiểm thử (Test Runner)
- **Unit & Integration Test:** Sử dụng **PHPUnit 11+**.
  - File cấu hình: `phpunit.xml`.
  - Đường dẫn chạy: `vendor/bin/phpunit` hoặc script chạy tóm tắt.
- **Mobile UI & E2E Test:** Sử dụng **Playwright** (Node.js) để giả lập trình duyệt di động (viewport iPhone/Pixel) kiểm tra trọn vẹn luồng từ đặt món, kiểm tra AJAX số điện thoại, đến hiển thị mã VietQR.

### 4.2. Nguyên tắc cô lập Database Test (BẮT BUỘC)
- **TUYỆT ĐỐI KHÔNG CHẠY TEST ĐÈ VÀO DATABASE THẬT (`bep_an_yen.db`)**.
- Mọi bài test tự động phải được cô lập 100%:
  - Sử dụng SQLite In-Memory: `sqlite::memory:` hoặc tạo một file database tạm thời trong thư mục tạm (`sys_get_temp_dir() . '/test_' . uniqid() . '.db'`).
  - Trong phương thức `setUp()`, khởi tạo schema và seed dữ liệu tối thiểu cho bài test.
  - Trong phương thức `tearDown()`, đóng kết nối và dọn dẹp sạch sẽ file DB tạm, bảo đảm các test case hoàn toàn độc lập, không phụ thuộc thứ tự chạy.

### 4.3. Mocking & Fake các External Boundaries
- **VietQR Generator:** Mock interface sinh mã QR chuyển khoản để kiểm tra payload, số tiền, nội dung chuyển khoản mà không phụ thuộc vào kết nối mạng bên ngoài.
- **Thời gian (Clock / Time-freezing):** Sử dụng `ClockInterface` hoặc dependency tiêm thời gian để test các ca bán hàng theo ngày/giờ cụ thể (ví dụ test hành vi khi ngoài giờ bán hoặc khi ca đã quá hạn).
- **Thông báo / SMS:** Mock toàn bộ các cổng gửi tin nhắn, notification (nếu có).

---

## 5. Tiêu chuẩn Giao diện Mobile & Phong cách Zen/Ghibli

- Mọi thay đổi giao diện phải đặt ưu tiên di động (Mobile-First) lên hàng đầu (viewport chuẩn 375px - 430px).
- Tuyệt đối tuân thủ bảng màu và typographic rules trong `docs/SCREEN_DESIGN.md`:
  - Nền Washi kem ấm (`#FAF7F2`), màu xanh trà dịu (`#5B7065`), chữ nâu than mờ (`#2C2A29`).
  - Không dùng các hiệu ứng giật cục, pop-up quảng cáo gây khó chịu. Trải nghiệm nhẹ nhàng, an tĩnh.
- Kiểm tra bàn phím ảo trên điện thoại: đảm bảo khi người dùng gõ số điện thoại hoặc tên, bàn phím không che lấp nút "Gửi Đặt Bếp An Lành".

---

## 6. Checklist trước khi bàn giao

Trước khi commit và bàn giao mã nguồn cho bất kỳ task/issue nào, Agent phải tự kiểm tra danh sách sau:

- [ ] **1. Đọc và hiểu:** Đã đọc `AGENTS.md`, `AGENT_RULES.md` và các tài liệu thiết kế liên quan trong `docs/`.
- [ ] **2. Kiểm tra cú pháp PHP:** Chạy `php -l` trên toàn bộ các file PHP đã sửa đổi, không có lỗi cú pháp (`No syntax errors detected`).
- [ ] **3. Strict types & UTF-8:** Tất cả file PHP đều có `declare(strict_types=1);`, lưu định dạng UTF-8 không BOM.
- [ ] **4. Bảo mật SQL:** 100% câu truy vấn dùng Prepared Statements qua PDO, không nối chuỗi.
- [ ] **5. Bảo mật XSS:** Dữ liệu in ra view đều được bọc `htmlspecialchars()`.
- [ ] **6. Transaction & Concurrency:** Các thao tác đặt đơn/khóa ca đều được bao bọc trong transaction `IMMEDIATE`.
- [ ] **7. Cô lập Test:** Đã chạy PHPUnit, 100% test case pass; test chỉ chạy trên DB in-memory hoặc DB tạm, không đụng vào DB runtime.
- [ ] **8. Mobile UX:** Kiểm tra giao diện hiển thị đúng trên màn hình mobile, giữ đúng phong cách Ghibli/Zen (màu sắc, font chữ).
- [ ] **9. Quy ước Git:** Tạo đúng nhánh `codex/{IssueNo}_{IssueName}`, commit riêng biệt theo đúng quy định.
- [ ] **10. Tăng phiên bản:** Cập nhật đúng số version hệ thống theo quy ước Semantic Versioning trong `AGENTS.md`.
