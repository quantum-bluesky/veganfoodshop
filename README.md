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
14. **Lịch Âm Mùng 1 & Rằm, Trung Tâm Thông Báo & Tối Ưu Bố Cục Frontend (v1.8.0 - Issue #19):**
    - **Thuật Toán Âm Lịch Thuần PHP (Zero-Dependency):**
      - Tích hợp `LunarCalendarService` dựa trên thuật toán thiên văn Hồ Ngọc Đức, tự động tính chính xác ngày âm, tháng âm, tháng nhuận và các ngày sóc (Mùng 1), vọng (Rằm 15) và đại lễ chay (Vu Lan, Phật Đản, Nguyên Tiêu, Hạ Nguyên...).
    - **Notice & Event Hub (Trung Tâm Thông Báo & Sự Kiện Chay):**
      - Thẻ thông tin nổi bật đầu trang hiển thị ngày âm hôm nay kèm huy hiệu trăng non (`🌑 Mùng 1`) hoặc trăng tròn (`🌕 Rằm 15`).
      - Nhắc nhở đếm ngược các ngày Sóc/Vọng kế tiếp (còn bao nhiêu ngày) để khách hàng chủ động lên kế hoạch đặt mâm cỗ hoặc ghé quán dùng bữa chay.
    - **Thanh Lịch Ngang Trượt (Horizontal Date Strip):**
      - Thay thế hoàn toàn dropdown chọn ngày ẩn hẹp bằng thanh ngày trượt ngang trực quan (Thứ, Ngày dương, Ngày âm, Huy hiệu M1/Rằm), chạm chọn tức thì.
    - **Tối Ưu Trải Nghiệm Di Động & Chống Dàn Trải Dọc:**
      - **Category Pills (Bộ lọc danh mục):** Dải nút bấm lọc nhanh `Tất cả`, `Món chính`, `Đồ uống`, `Mâm cỗ` giúp khách không phải cuộn trang dài.
      - **Floating Bottom Summary Bar:** Thanh đáy nổi mềm mại hiển thị số lượng món đã chọn, tạm tính tiền và nút "Xem đơn & Đặt ngay ➔" tự động trượt mượt xuống Bước 4, giải phóng hoàn toàn trải nghiệm cuộn mỏi tay trên điện thoại.
15. **Thiết Lập Khuyến Mại Giảm Giá Theo Lịch, Món Ăn & Ngày Âm Lịch (v1.9.0 - Issue #20):**
    - **Thiết lập Khuyến mại Linh hoạt & Đa dạng:**
      - Quản lý theo khung thời gian hiệu lực (`start_date` đến `end_date`).
      - Hình thức giảm giá: Giảm theo phần trăm (`percentage` ví dụ: 10%, 15%) hoặc Giảm số tiền trực tiếp (`fixed_amount` ví dụ: 20.000đ).
      - Ngưỡng đơn tối thiểu (`min_order_amount`) kích hoạt khuyến mại.
      - Phạm vi áp dụng phong phú: Áp dụng toàn bộ menu (`all`), chỉ áp dụng mâm cỗ (`feast_combo`), áp dụng theo danh mục (`category`) hoặc chỉ định một món cụ thể (`menu_item`).
    - **Cơ Chế Khuyến Mại Ngày Âm Lịch Tự Động (Mùng 1 & Rằm):**
      - Tùy chọn độc đáo tự động áp dụng vào ngày Sóc (Mùng 1) và ngày Vọng (Rằm 15) hàng tháng (`apply_on_lunar_days = '1,15'`).
      - Chủ bếp chỉ cần tạo chương trình ưu đãi một lần, hệ thống tự động nhận diện theo thuật toán Âm lịch và kích hoạt chính xác vào mọi ngày Mùng 1 & Rằm mà không cần lên lịch lặp lại thủ công.
    - **Hình thức Gây Chú Ý Nổi Bật Trên Web Public:**
      - **Notice Hub Banner:** Hiển thị thẻ thông báo khuyến mại nổi bật ngay đầu trang với huy hiệu `🔥 ƯU ĐÃI ĐANG DIỄN RA`, mô tả chương trình và điều kiện áp dụng.
      - **Date Strip Badge:** Ngày nào trên thanh lịch có khuyến mại sẽ tự động gắn huy hiệu `🔥 KM` rực rỡ để khách dễ dàng nhận diện và chọn ngày hưởng ưu đãi.
      - **Category Pill KM:** Nút lọc nhanh `🔥 Khuyến mại` giúp khách lọc xem ngay danh sách các món đang được giảm giá.
      - **Thẻ món ăn:** Hiển thị huy hiệu giảm giá (ví dụ: `-15%`, `-20k`), giá bán sau giảm nổi bật và giá gốc gạch ngang minh bạch.
    - **Trang Quản Trị Khuyến Mại Toàn Diện (`admin.php?tab=promotions`):**
      - Thống kê tổng số chương trình, số chương trình đang chạy và đã lên lịch.
      - Form tạo mới chương trình trực quan với đầy đủ tùy chọn điều kiện và phạm vi.
      - Thao tác 1-chạm bật/tắt kích hoạt (`toggle`) hoặc xóa bỏ chương trình.
    - **Tích Hợp Chặt Chẽ Vào Đơn Hàng:**
      - `PromotionService` và `OrderService` tự động tính toán chiết khấu đơn, ưu tiên chương trình có lợi nhất cho khách khi có nhiều khuyến mại cùng thỏa mãn, trừ tiền chuẩn xác và ghi nhận chi tiết chương trình vào dữ liệu đơn hàng.
16. **Cảm Nhận Đặc Biệt, Lời Đề Tặng & Kỷ Niệm (Testimonials) với UI Trầm Mặc Sâu Lắng (v1.10.0 - Issue #21):**
    - **Không Gian Lưu Bút An Lành (Testimonials Gallery):**
      - Hiển thị những dòng lưu bút, cảm nhận sâu sắc, lời cảm ơn và kỷ niệm thiêng liêng từ thực khách ghé thăm quán.
      - Thiết kế giao diện đậm chất **Thiền Vị & Hơi thở Ghibli**: thẻ Washi ngà ấm, dấu ngoặc kép cổ điển lớn `“ ... ”`, font chữ *Lora* in nghiêng thanh thoát, khoảng cách dòng thênh thang tạo cảm giác bình yên và lắng đọng.
      - Huy hiệu bối cảnh mộc mạc: *`📌 Lưu bút bàn trà số 3 • Ngày Rằm tháng Bảy`*, *`Kỷ niệm mùa Vu Lan`*, cùng lời tri ân chân thành từ người đứng bếp.
      - Khu vực Testimonials luôn mở để khách ghé đọc kể cả khi tính năng nhận đánh giá mới đang ở chế độ tạm đóng.
    - **Công Cụ Quản Trị Đề Tặng & Kỷ Niệm (`admin.php?tab=reviews`):**
      - Nút bấm 1-chạm ⭐ Ghim Kỷ Niệm / Testimonial để tuyển chọn những cảm nhận hay nhất lên trang chủ.
      - Form ghi lại lời đề tặng, lưu bút kỷ niệm chép trực tiếp từ sổ tay quán.
      - Tùy chỉnh lời đề tựa bối cảnh cho từng bài viết.
17. **Trang Giới Thiệu Nguồn Gốc & Bốn Tôn Chỉ Thuần Chay (`/about` - Issue #22):**
    - **Khởi Nguồn & Sự Hình Thành:**
      - Câu chuyện ra đời từ một góc bếp nhỏ ấm cúng, hành trình tìm về vị ngọt nguyên bản từ thảo mộc để vỗ về những bộn bề sau cánh cửa gỗ.
    - **Bốn Tôn Chỉ Cốt Lõi (The 4 Zen Principles):**
      1. 🌿 *Thuần khiết tự nhiên (Pure & Wholesome):* 100% nguyên liệu thảo mộc tươi trong ngày. Tuyệt đối không mì chính/bột ngọt hóa học, chất bảo quản. Nước dùng ninh ngọt lành từ củ cải trắng, bắp ngọt, mía lau và nấm rừng.
      2. ⏱️ *Nấu theo ca, giới hạn số suất (Slot & Batch Pre-order):* Không nhận đơn quá tải, nấu đúng số suất đặt trước để từng bát phở, tô bún luôn nóng hổi và trọn vẹn tâm ý.
      3. 🕊️ *Nuôi dưỡng lòng từ & An yên (Compassion & Mindful Living):* Mỗi bữa ăn chay là một nhịp thở chậm, tri ân đất mẹ và thương yêu muôn loài.
      4. 🍵 *Đón tiếp như người thân quen trở về (Warm & Welcoming):* Tạo cảm giác thân thuộc, không gian trà thơm chuông gió, tin cậy tuyệt đối.
    - **Điều Hướng Xuyên Suốt Hệ Thống:**
      - Thanh điều hướng tối giản kết nối mượt mà giữa **Đặt Chỗ (`index.php`)** • **Về Bếp An Yên (`about.php`)** • **Cảm Nhận (`reviews.php`)**.
      - Hỗ trợ Clean URL chuẩn (`/about`, `/reviews`, `/admin`).
18. **Giải Pháp Thanh Toán Mobile VietQR - Mở Trực Tiếp App & Quét Từ Thư Viện Ảnh (v1.11.0):**
    - **Giải quyết Rào cản Mobile Web:**
      - Khắc phục triệt để vấn đề thực khách giao dịch trên điện thoại di động không thể dùng camera của máy để quét mã QR hiển thị trên chính màn hình đó.
    - **Giải Pháp 1 - Deep Link Mở Thẳng App Ngân Hàng:**
      - Nút hành động nổi bật: *"🚀 Mở Ứng Dụng Ngân Hàng Thanh Toán"* tích hợp chuẩn **VietQR / Napas247 Universal Deeplink** (`https://dl.vietqr.io/pay?...`), tự động điền sẵn số tài khoản, số tiền và mã đơn hàng. Khách chỉ cần xác thực FaceID/vân tay trong app ngân hàng để chuyển khoản.
      - **Khay phím tắt mở trực tiếp:** Tích hợp bộ custom app schemes dẫn thẳng vào các app ngân hàng hàng đầu: Vietcombank (`vcb://`), MBBank (`mbmobile://`), Techcombank (`tcb://`), BIDV (`bidvsmartbanking://`), Ví MoMo (`momo://`).
    - **Giải Pháp 2 - Lưu Ảnh QR & Quét Từ Thư Viện Ảnh (Album):**
      - Nút bấm 1-chạm: *"📥 Lưu Ảnh QR Vào Máy (Để Quét Từ Thư Viện)"* tự động lưu ảnh mã QR có sẵn số tiền và nội dung đơn về cuộn ảnh điện thoại.
      - Hướng dẫn 2 bước mộc mạc và trực quan giúp bất kỳ khách hàng nào cũng thao tác dễ dàng: *Bấm Lưu ảnh ➜ Mở App ngân hàng chọn Quét QR từ Album*.
    - **Tự Động Cập Nhật Đơn Hàng (Zero-Friction):**
      - Hệ thống polling nền chu kỳ 3.5s và API webhook vẫn tiếp tục lắng nghe biến động số dư ngân hàng, tự động xác nhận đơn thành công ngay khi khách hoàn tất giao dịch trên app ngân hàng mà không cần khách phải chụp màn hình gửi lại.
19. **Kiểm Thử Nghiệp Vụ Toàn Diện (Acceptance Tests) & Khắc Phục Lỗi Trang Cảm Nhận (v1.12.0 - Issue #24 & #25):**
    - **Bộ Kiểm Thử Nghiệp Vụ Chấp Nhận (Customer Flow Acceptance Testing):**
      - Bổ sung `Issue24_CustomerFlowAcceptanceTest` với 32 assertions độc lập kiểm tra toàn diện 9 hành trình cốt lõi của khách hàng:
        1. Khách mới đặt ăn tại quán (Dine-in) qua VietQR & Deeplink.
        2. Khách quen đặt ship mang về (Takeaway) với đặc quyền Trả sau (Pay Later).
        3. Tự động tính toán và áp dụng khuyến mại chiết khấu theo lịch.
        4. Kiểm soát giới hạn công suất ca (Capacity Boundary) và tự động giải phóng suất khi đơn bị hủy.
        5. Thực thi bắt buộc đăng nhập Zalo khi bật cấu hình và đồng bộ tức thời ở runtime.
        6. Hiển thị thông báo & nhắc nhở lịch đặt cá nhân cho khách hàng sắp đến ca.
        7. Đối soát thanh toán tức thời (Reconcile / Polling) và cập nhật trạng thái đơn hàng.
        8. Khách gửi cảm nhận, xác minh người mua thực tế (`is_verified_buyer`) và chống gửi trùng lặp.
        9. Kiểm tra tính toàn vẹn và mượt mà của luồng điều hướng giữa các trang.
    - **Khắc Phục Lỗi Trang Cảm Nhận (`reviews.php` - Issue #25):**
      - Sửa lỗi Fatal Error `Call to undefined method ZaloAuthService::getCurrentCustomer()` bằng cách bổ sung hàm `getCurrentCustomer(?string $phone)` hỗ trợ truy xuất linh hoạt qua SĐT, session hoặc cookie.
      - Tích hợp JavaScript tự động điền (autofill) thông tin tên và số điện thoại từ `localStorage` khi khách đã từng đăng nhập Zalo hoặc đặt hàng trước đó.
      - Bổ sung nút liên kết từ màn hình xác nhận đơn (`process_order.php`) chuyển sang gửi cảm nhận/lưu bút đến Bếp An Yên.
20. **Thiết Lập Khuyến Mại Đa Chiều Theo Khách Hàng & Đơn Hàng (v1.13.0 - Issue #26):**
    - **Phân Khúc Khuyến Mại Linh Hoạt (Targeting Engine):**
      - **Theo từng khách hàng cụ thể (`target_customer_phone`):** Đặt mã ưu đãi hoặc quà tặng giảm giá tri ân riêng cho từng số điện thoại thực khách.
      - **Theo nhóm khách hàng (`target_customer_group`):**
        - `all`: Toàn bộ khách hàng.
        - `trusted`: Đặc quyền dành riêng cho khách quen đã xác thực (`is_trusted = 1`).
        - `new`: Khuyến mại "Chào bạn mới" dành cho khách chưa từng có đơn hàng trước đó.
        - `loyal`: Ưu đãi khách thân thiết (thực khách đã có từ 3 đơn hoàn tất trở lên hoặc khách quen).
      - **Theo nhóm đơn hàng (`target_order_type`):**
        - `all`: Cả dùng tại quán và ship mang về.
        - `dine_in`: Chỉ áp dụng cho khách ghé ăn tại bếp.
        - `takeaway`: Trợ giá phí ship hoặc giảm giá riêng cho đơn mang về.
      - **Theo đơn hàng đầu tiên (`first_order_only`):** Tự động nhận diện mỗi số điện thoại chỉ được nhận khuyến mại này đúng 1 lần duy nhất trong đời.
      - **Theo mã đơn hàng cụ thể (`target_order_code`):** Áp dụng chiết khấu khi đơn khớp đúng mã đơn chỉ định.
    - **Giao Diện Quản Trị Khuyến Mại Mở Rộng (`admin.php?tab=promotions`):**
      - Bổ sung bảng điều khiển Targeting Options với dropdown nhóm khách, nhóm đơn, ô nhập SĐT và checkbox đơn đầu tiên.
      - Bảng danh sách hiển thị các huy hiệu phân loại trực quan: `👤 Khách: 090...`, `⭐️ Khách quen`, `🌱 Khách mới`, `💎 Thân thiết`, `🍽 Tại quán`, `🛵 Ship mang về`, `1️⃣ Chỉ đơn đầu`.
21. **Thanh Trạng Thái Zalo Header, Đăng Xuất & Xem Lịch Đặt Của Tôi Thời Gian Thực (v1.13.0 - Issue #27):**
    - **Thanh Trạng Thái Zalo Nhỏ Gọn Trên Navigation (`#topZaloPill`):**
      - Khi đã đăng nhập Zalo: Hiển thị avatar tròn thu nhỏ + Tên thực khách + Huy hiệu `✓ Đã nối` + Nút *"📅 Đơn của tôi"* + Nút *"Thoát"* (Logout) tinh tế ngay trên đầu trang.
      - Khi chưa kết nối: Hiển thị lời mời mộc mạc kèm nút *"Nối Zalo"* màu xanh dịu dàng.
      - Thao tác đăng xuất 1-chạm xóa session an toàn và cập nhật giao diện tức thời mà không cần reload trang.
    - **Làm Mới Lịch Đặt Của Tôi (Realtime Order Status Synchronization):**
      - Modal *"🌿 Lịch Đặt Của Tôi"* bổ sung nút *"🔄 Làm mới"* ở góc trên giúp khách chủ động cập nhật trạng thái đơn tức thì.
      - Tự động gọi API `get_my_orders` lấy dữ liệu mới nhất mỗi khi khách bấm mở modal từ thanh điều hướng, banner hoặc form.
      - Trang chi tiết đơn (`process_order.php`) hiển thị trạng thái chính xác, tương thích đầy đủ khi đơn chuyển sang `completed` ("✨ Đơn Hàng Đã Hoàn Tất") hoặc `cancelled` ("🍃 Đơn Hàng Đã Được Huỷ").
22. **Khắc Phục Lỗi Khởi Tạo Biến JavaScript Client-Side (v1.13.1 - Issue #28):**
    - **Khắc phục lỗi:** `ReferenceError: Cannot access 'currentCategoryFilter' before initialization` tại `app.js:159`.
    - **Nguyên nhân:** Hàm `filterMenuByOrderType(initialOrderType)` được kích hoạt khi tải trang gọi hàm `applyCategoryAndOrderTypeFilter()`, trong khi biến trạng thái `currentCategoryFilter` và các phần tử DOM của bộ lọc danh mục / thanh nổi (Floating Bar) được khai báo ở phía dưới trong block `DOMContentLoaded`.
    - **Giải pháp:** Tái cấu trúc toàn bộ khai báo biến state và phần tử DOM lên đầu block `DOMContentLoaded` trước khi định nghĩa và thực thi các hàm helper, đảm bảo 100% luồng tương tác trên web hoạt động mượt mà, không còn lỗi console hay bị ngắt flow trên cả local và production.
23. **Thông Báo Lịch Sự Quá Giờ Đặt 3 Phút & Chế Độ Điều Chỉnh Cỡ Chữ A+/A- Dễ Đọc (v1.14.0 - Issues #29 & #30):**
    - **Thông Báo Lịch Sự Quá Hạn Đặt Lịch (Issue #29):**
      - Khi thời gian thực tế vượt quá khoảng thời gian đặt lịch từ 3 phút trở lên (`$isExceeded3M = true`), hệ thống tự động gắn nhãn ca *"Quá giờ đặt (>3p)"*.
      - Nếu khách nhấn chọn hoặc gửi đơn, hệ thống không chỉ từ chối khô khan mà hiển thị thông báo ấm áp, lịch sự từ bếp (nêu rõ lý do cần thời gian ninh nước dùng tươi mới) và chủ động đưa ra 2 giải pháp tiện lợi:
        1. **Lựa chọn đặt vào ca tiếp theo** (tự động gợi ý chính xác khung giờ ca tiếp theo còn chỗ trong ngày hoặc ngày tới).
        2. **Liên hệ trực tiếp với chủ bếp** qua số Hotline hoặc Zalo một chạm.
      - Giao diện báo lỗi trên `process_order.php` cung cấp sẵn nút *"🌿 Lựa chọn đặt vào ca tiếp theo"*, *"💬 Liên hệ trực tiếp chủ bếp qua Zalo"* và *"📞 Gọi Hotline chủ bếp"*.
    - **Chế Độ Phóng To Cỡ Chữ Hiển Thị A+/A- (Issue #30):**
      - Bổ sung nút bấm điều chỉnh cỡ chữ thanh nhã `[ A+ Chữ lớn ]` ngay trên thanh điều hướng đầu trang.
      - Khi bấm kích hoạt `A+`: Chuyển sang class `.zen-font-large`, toàn bộ các vùng chú ý, ghi chú món ăn, thông báo âm lịch, lưu ý khung giờ và hướng dẫn nhận đơn được tăng kích thước rõ ràng (+25% đến +30%), tăng độ tương phản màu mực sắc nét, chống mỏi mắt cho người lớn tuổi hoặc người mắt kém.
      - Khi bấm lại `[ A- Cỡ vừa ]`: Tự động khôi phục về kích thước chuẩn ban đầu.
      - Tự động ghi nhớ lựa chọn của khách qua `localStorage` (`vegan_font_size_large`), đảm bảo trải nghiệm xuyên suốt khi chuyển trang.

---

## 📁 Cấu trúc Thư mục

```text
D:\Quan\VeganFoodShop\
├── AGENTS.md                                   # Quy tắc vận hành & tiêu chuẩn AI Agent
├── AGENT_RULES.md                              # Quy chuẩn kỹ thuật, bảo mật & checklist
├── Mô-hình-và-giải-pháp-đặt-chỗ-quán-chay.md  # Tài liệu giải pháp & thiết kế gốc
├── README.md                                   # Tài liệu hướng dẫn hệ thống
├── index.php                                   # Màn hình đặt món / booking chính cho khách
├── about.php                                   # Trang giới thiệu nguồn gốc & 4 tôn chỉ bếp
├── reviews.php                                 # Trang cảm nhận & góc lưu bút kỷ niệm (Testimonials)
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
