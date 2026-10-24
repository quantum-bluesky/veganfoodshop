# TESTING.md - Chiến Lược & Hướng Dẫn Kiểm Thử VeganFoodShop

Tài liệu này quy chuẩn toàn bộ chiến lược kiểm thử, cấu trúc test suite, nguyên tắc cô lập môi trường và hướng dẫn thực thi kiểm thử tự động cho hệ thống **VeganFoodShop (Bếp Chay An Yên)**.

---

## 1. Nguyên tắc cốt lõi & Quy định Cô lập Môi trường Test

1. **Tuyệt đối không đụng vào CSDL runtime (`bep_an_yen.db`):**
   - Mọi bài kiểm thử tự động (Unit, Integration, E2E) **bắt buộc phải chạy trên CSDL tạm**:
     - Hoặc dùng SQLite In-Memory: `sqlite::memory:`
     - Hoặc dùng file tạm do hệ thống cấp phát: `sys_get_temp_dir() . '/test_' . uniqid() . '.db'`
   - Trước mỗi bài test (`setUp`), nạp lại toàn bộ schema sạch và seed dữ liệu tối thiểu.
   - Sau mỗi bài test (`tearDown`), đóng kết nối PDO và xóa hoàn toàn file database tạm.
2. **Không phụ thuộc thứ tự chạy:**
   - Mỗi test case phải tự khởi tạo dữ liệu cần thiết và tự dọn dẹp.
3. **Mocking các dịch vụ bên ngoài:**
   - Mocking trình sinh mã VietQR (không gọi internet khi chạy unit test).
   - Mocking dịch vụ thời gian để test các tình huống đặt đơn trước giờ / ngoài giờ mở ca.

---

## 2. Công cụ Kiểm thử (Test Stack)

* **Unit & Integration Test:** **PHPUnit 11+**
  * Chạy trên CLI bằng PHP 8.4+.
  * Cấu hình qua tệp `phpunit.xml`.
* **Mobile UI & E2E Acceptance Test:** **Playwright**
  * Giả lập thiết bị di động (Mobile Chrome / Safari trên viewport iPhone 14/15: 390x844).
  * Kiểm tra hiển thị bàn phím, tương tác chạm (tap), phản hồi AJAX và hiển thị mã VietQR.

---

## 3. Cấu trúc thư mục Tests

```text
tests/
├── phpunit.xml                                 # File cấu hình PHPUnit
├── TestCase.php                                # Lớp Base TestCase (khởi tạo SQLite :memory:)
├── Unit/                                       # Kiểm thử đơn vị các hàm nghiệp vụ
│   ├── CustomerServiceTest.php                 # Nhận diện khách quen qua SĐT
│   ├── SlotCapacityTest.php                    # Tính toán suất còn trống và khóa ca
│   └── OrderCalculationTest.php                # Tính tiền đơn hàng & snapshot giá
├── Integration/                                # Kiểm thử tích hợp luồng API & Database
│   ├── ApiCheckPhoneTest.php                   # Test endpoint AJAX kiểm tra SĐT
│   ├── CreateOrderTransactionTest.php          # Test tạo đơn & Transaction IMMEDIATE
│   └── SlotConcurrencyTest.php                 # Test race condition khi đặt suất cuối cùng
└── E2E/                                        # Kiểm thử giao diện di động bằng Playwright
    ├── mobile_booking_flow.spec.js             # Luồng đặt món & chọn ca trên Mobile
    └── trusted_customer_ui.spec.js             # Luồng tự mở tùy chọn trả sau cho khách quen
```

---

## 4. Danh mục Test Cases mẫu (Test Matrix)

### 4.1. Unit Tests
* **TC-UNIT-01 (Nhận diện khách quen):**
  * Input: Số điện thoại `0912345678` đã có trong bảng `customers` với `is_trusted = 1`.
  * Expected: Service trả về `is_trusted == true`, kèm tên khách hàng.
* **TC-UNIT-02 (Khách mới chưa tin cậy):**
  * Input: Số điện thoại chưa từng đặt hoặc `is_trusted = 0`.
  * Expected: Service trả về `is_trusted == false`.
* **TC-UNIT-03 (Tính tiền đơn hàng):**
  * Input: 2 Phở Nấm (45.000đ/tô) + 1 Trà Sen (25.000đ).
  * Expected: Tổng tiền tính đúng = 115.000đ.
* **TC-UNIT-04 (Kiểm tra giới hạn ca `max_capacity`):**
  * Given: Ca 1 có `max_capacity = 10`. Hiện đã có 9 suất ăn chính được đặt.
  * When: Khách mới đặt 2 tô phở vào ca 1.
  * Expected: Ném ra ngoại lệ `SlotCapacityExceededException` ("Ca phục vụ đã nhận đủ suất").

### 4.2. Integration Tests (Giao dịch & Concurrency)
* **TC-INT-01 (An toàn Transaction khi tạo đơn):**
  * Khi tạo đơn hàng thành công, cả bản ghi `orders` và các bản ghi `order_items` phải được ghi đồng thời. Nếu có lỗi giữa chừng, toàn bộ đơn hàng phải được rollback sạch sẽ.
* **TC-INT-02 (Chống Race Condition - Đặt đồng thời ca cuối):**
  * Given: Ca chỉ còn đúng 1 suất khả dụng.
  * When: Mô phỏng 2 kết nối đồng thời gửi đơn đặt 1 suất.
  * Expected: Đúng 1 đơn thành công, 1 đơn thất bại với thông báo ca đã đầy; số suất không bao giờ vượt quá `max_capacity`.

### 4.3. E2E Mobile Tests (Playwright)
* **TC-E2E-01 (Luồng đặt món Khách mới):**
  * Mở trang chủ với viewport 390x844.
  * Chọn "Ăn tại chỗ" $\rightarrow$ Chọn Ca 1 $\rightarrow$ Chọn 1 Phở Nấm $\rightarrow$ Nhập Tên & SĐT mới.
  * Kiểm tra: Badge khách quen KHÔNG hiển thị, tùy chọn trả sau bị ẩn.
  * Bấm gửi đơn $\rightarrow$ Chuyển sang màn hình xác nhận hiển thị mã QR VietQR.
* **TC-E2E-02 (Luồng đặt món Khách quen):**
  * Mở trang chủ $\rightarrow$ Chọn món $\rightarrow$ Nhập SĐT khách quen.
  * Kiểm tra: Xuất hiện badge *"🌿 Chào bạn quen trở lại!"*, tùy chọn *"Thanh toán khi dùng bữa"* xuất hiện và được chọn sẵn.

---

## 5. Hướng dẫn chạy Test

### 5.1. Chạy Unit & Integration Test với PHPUnit
```powershell
# Chạy toàn bộ test suite
vendor/bin/phpunit

# Chỉ chạy các bài Unit test
vendor/bin/phpunit --testsuite Unit

# Chỉ chạy một file test cụ thể
vendor/bin/phpunit tests/Unit/CustomerServiceTest.php

# Chạy kèm báo cáo chi tiết
vendor/bin/phpunit --testdox
```

### 5.2. Chạy Mobile E2E Test với Playwright
```powershell
# Cài đặt Playwright (lần đầu)
npm install
npx playwright install chromium

# Chạy test E2E trên giả lập Mobile
npx playwright test
```

---

## 6. Tiêu chí Đạt (Pass Criteria) trước khi Bàn giao
1. 100% các bài test trong suite PHPUnit phải đạt trạng thái **PASS** (OK).
2. Không có bất kỳ cảnh báo deprecation hoặc notice nào của PHP 8.4 (`0 Deprecations, 0 Notices`).
3. Database `bep_an_yen.db` không bị ảnh hưởng, không chứa dữ liệu rác sau khi chạy test.
