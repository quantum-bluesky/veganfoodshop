> From: https://gemini.google.com/u/3/app/b75c752282f587a6

# you asked

message time: 2026-10-24 17:20:50

Tôi mở cửa hàng bán thức ăn thuần chay tại chỗ & ship cho khách, có cả đồ uống.
Do chưa hoàn thiện cửa hàng nên trong thời gian đầu, tôi chưa hoạt động cả ngày hay cả tuần, mà lịch hoạt động luôn thay đổi trong ngày, trong tuần.
Các món ăn như bún, phở, ...
Tìm cho tôi một mô hình phù hợp để trước khi mở cửa hàng chính thức hoạt động full time, tôi có thể giúp khách hàng xem & đặt trước thười gian phục vụ mà ko phải liên lạc hẹn mất thời gian & ko thuận tiện.

---

# gemini response

Mô hình phù hợp nhất cho giai đoạn thử nghiệm (soft-launch) này là **Mô hình Khung giờ & Suất ăn giới hạn (Slot & Batch Pre-order)**. 

Thay vì duy trì một quán ăn mở cửa thả nổi, bạn vận hành theo cơ chế: mở lịch phục vụ theo từng khung giờ cụ thể trong ngày/tuần, giới hạn số lượng suất cho mỗi khung giờ để đảm bảo nước dùng và topping của bún, phở luôn tươi ngon, không bị tồn dư hay quá tải khi chưa đủ nhân sự.

---

### 1. Cơ chế vận hành mô hình

* **Lên lịch theo tuần (Weekly Droplist):** Cố định một thời điểm (ví dụ: tối Chủ nhật) công bố lịch mở bán của tuần tới hoặc cập nhật trước 24h – 48h.
* **Chia ca phục vụ rõ ràng (Time Slots):**
  * *Ăn tại chỗ:* Phục vụ theo khung 45 – 60 phút (ví dụ: Ca 1: 11h00 – 12h00; Ca 2: 12h15 – 13h15), giới hạn tối đa số khách mỗi ca đúng theo số bàn ghế hiện có.
  * *Giao hàng (Ship):* Khách chọn khung giờ nhận món (ví dụ: Nhận lúc 11h30 – 12h00). Bún, phở giao đi cần đóng gói tách riêng nước dùng nóng và bánh/rau để tránh bị nát.
* **Cơ chế khoá suất tự động:** Đặt ngưỡng tối đa mỗi khung giờ (ví dụ: 15 tô phở/ca). Đạt đủ số lượng thì hệ thống tự đóng khung giờ đó để khách chọn giờ khác.

---

### 2. Các giải pháp công nghệ tự động hoá (Khách tự xem lịch & đặt, không cần chat hẹn)

Tùy theo mức độ đầu tư kỹ thuật, bạn có thể triển khai một trong các phương án sau:

| Giải pháp | Nền tảng gợi ý | Ưu điểm | Nhược điểm |
| :--- | :--- | :--- | :--- |
| **Trang Booking chuyên dụng** | **Cal.com** / **Calendly** / **Setmore** | Cực kỳ trực quan, bật/tắt ngày giờ linh hoạt theo từng tuần, tích hợp sẵn trường chọn món ăn kèm | Cần gắn kèm link chuyển khoản/menu bên ngoài nếu dùng bản miễn phí |
| **Mini-Web Order / Form thông minh** | **Tally.so** / **Google Forms + Sheet** | Tùy biến thực đơn, khách tick chọn món + chọn khung giờ còn trống, hiển thị mã QR chuyển khoản | Google Form không tự động khóa khung giờ khi đủ suất nếu không cài add-on (như *FormLimiter*) |
| **Website F&B Mini (Thương mại điện tử tinh gọn)** | **CukCuk** / **iPOS Web Order** / **Sapo FnB** | Quản lý đồng bộ thực đơn bún phở, đồ uống, đơn ship và bàn ăn tại chỗ | Tốn chi phí thuê bao tháng, phù hợp hơn khi đã chuẩn bị mở full-time |

---

### 3. Gợi ý thiết lập thực tế & tiết kiệm nhất (Nên áp dụng ngay)

**Sử dụng Cal.com hoặc Tally.so kết hợp mã VietQR:**
1. **Lịch mở:** Tạo sự kiện đặt bàn/đặt suất với các khung giờ bạn chắc chắn phục vụ được trong tuần.
2. **Chọn loại hình:** Thêm tùy chọn ngay đầu form: *“Ăn tại chỗ”* hoặc *“Giao hàng tận nơi”*.
3. **Chọn món:** Tạo danh sách tick chọn món (Phở chay, Bún Huế chay, Bún riêu chay kèm ghi chú cay/không cay) và đồ uống.
4. **Thanh toán giữ chỗ:** Hiển thị mã QR ngân hàng thanh toán trước (toàn bộ hoặc đặt cọc 50k) đối với đơn ship hoặc nhóm đông để tránh tình trạng "bùng" đơn khi bạn đã nấu nước dùng.
5. **Kênh tiếp cận:** Đặt link đặt món duy nhất này vào phần Tiểu sử (Bio) của Fanpage, Zalo OA hoặc gửi tự động qua tin nhắn chào mừng khi khách nhắn tin.

Bạn dự định ưu tiên triển khai giải pháp hoàn toàn miễn phí, dễ làm trước trên điện thoại (như form/link booking), hay muốn một trang web đặt món bài bản tích hợp thanh toán tự động?

---

# you asked

message time: 2026-10-24 17:32:22

Tôi muốn tự phát triển 1 trang web dùng cho cá nhân, chạy trên linux debian. hướng cho user dùng mobile, cẩn thận chau chuốt, đặt món đơn giản nhưng vẫn đảm bảo thanh toán tùy chọn đặt chỗ theo yêu cầu, thanh toán linh hoạt: ví dụ đối với khách quen thì ko cần thanh toán trước.
Tư vấn phong cách giao diện (vd như style ghibli,...), cần toát lên chất thuần tịnh thanh khiết, mang màu sắc gần gũi với mọi người, có chiều sâu tâm hồn, là chỗ quay về an yên tĩnh lặng.

---

# gemini response

Một trang web dành cho quán chay mang tính cá nhân, tự host trên Debian và tối ưu cho di động là một hướng đi rất tinh tế. Với định vị là **“chốn quay về an yên tĩnh lặng”**, giao diện web không nên tạo cảm giác một ứng dụng thương mại điện tử hối hả, mà nên giống như một **lá thư mời nhẹ nhàng, ấm áp**.

Dưới đây là tư vấn chi tiết về phong cách giao diện (UI/UX) và kiến trúc tính năng phù hợp cho bạn:

---

### 1. Định hướng phong cách giao diện: "Hơi thở Ghibli & Thiền Vị (Zen)"

Phong cách Ghibli khi hòa quyện với tinh thần thuần chay sẽ tạo ra cảm giác rất trong lành, giàu tính hoài niệm và bình an.

* **Bảng màu chủ đạo (Color Palette):**
  * **Nền chính:** Màu giấy Washi / Kem ấm (`#FAF7F2` hoặc `#F4EFEA`), tạo cảm giác ấm áp, dịu mắt, tránh màu trắng tinh khiết kỹ thuật số quá gắt.
  * **Màu thiên nhiên (Điểm nhấn):** Xanh lá trà dịu (`#606C5D` hoặc `#5B7065`), xanh rêu mờ, hoặc màu đất son/nâu gỗ nhạt (`#8C7A6B`).
  * **Màu chữ:** Nâu than mờ (`#2C2A29` thay vì màu đen tuyền `#000000`), giúp văn bản mềm mại, dễ đọc như mực in trên giấy thủ công.
* **Hình ảnh & Hoạ tiết:**
  * Minh họa món ăn vẽ tay màu nước (Watercolor/Hand-drawn style) theo nét vẽ mộc mạc của Ghibli: làn khói bốc lên từ tô phở chay, cọng ngò gai, lát nấm đông cô mềm mại.
  * Các góc bo tròn mềm mại (`rounded-2xl` hoặc `rounded-3xl`), đường viền mờ nhạt tự nhiên như nét chì mộc.
* **Kiểu chữ (Typography):**
  * Tiêu đề: Font có chân cổ điển, thanh thoát nhưng ấm (như *Playfair Display*, *Lora*, hoặc *Merriweather* hỗ trợ tiếng Việt).
  * Thân bài & Nút bấm: Font không chân tròn trịa, mộc mạc và dễ nhìn trên mobile (như *Be Vietnam Pro* hoặc *Quicksand*).
* **Trải nghiệm thị giác & Âm thanh (Micro-interactions):**
  * Hiệu ứng chuyển cảnh chậm (fade-in êm ái), không dùng hiệu ứng nhảy nhót giật cục.
  * Có thể thêm một tùy chọn nhỏ ở góc: tiếng chuông gió khẽ ngân hoặc tiếng nước chảy róc rách rất nhỏ (cho khách bật/tắt tùy ý).

---

### 2. Trải nghiệm luồng đặt món & Đặt chỗ (Mobile-First UX)

Giao diện mobile cần được thiết kế dạng **cuộn dọc theo dòng chảy cảm xúc (Story-telling Flow)** gồm 4 bước tối giản:

1. **Lời ngỏ & Lịch phục vụ hôm nay:**
   * Ngay đầu trang là một thông điệp an lành nhỏ kèm bảng trạng thái rõ ràng: *“Hôm nay bếp mở ca trưa: 11h00 - 13h00 (Còn 8 suất)”*.
2. **Chọn hình thức & Món:**
   * Nút gạt nhẹ nhàng: *“Dùng tại quán”* hoặc *“Gửi mang về tận nơi”*.
   * Danh mục thực đơn giới hạn: Bún, Phở, Trà/Nước thanh nhiệt. Mỗi món chỉ cần tên thân thuộc, mô tả ngắn gọn về nguyên liệu và nút `+` đơn giản.
3. **Chọn khung giờ & Số lượng:**
   * Chọn giờ phục vụ theo các ô tròn (slots) có hiển thị trạng thái còn chỗ hay đã đầy.
4. **Xác nhận & Tùy chọn thanh toán linh hoạt:**
   * Nhập số điện thoại/tên. 
   * Cơ chế nhận diện khách quen & chọn thanh toán:
     * *Thanh toán ngay qua mã VietQR:* Hiện mã QR động kèm số tiền chính xác và lời nhắn cảm ơn.
     * *“Thanh toán khi ghé quán / Khách quen” (Pay later):* Khách tick chọn tùy chọn này kèm một ghi chú thân mật (ví dụ: *“Mình là khách quen / Xin thanh toán khi nhận món”*). Đơn vẫn được ghi nhận ngay lập tức mà không cần qua cổng thanh toán cứng nhắc.

---

### 3. Đề xuất Kiến trúc Kỹ thuật nhẹ tênh trên Debian Linux

Để phục vụ cá nhân, chạy mượt mà trên VPS/Server Debian cấu hình khiêm tốn mà vẫn bảo mật và ổn định:

* **Frontend:** **Vue 3** (hoặc Svelte) kết hợp với **Tailwind CSS**. 
  * Dung lượng cực nhẹ, tải trang tức thì trên mạng 3G/4G di động.
  * Dễ dàng tinh chỉnh các lớp màu Ghibli/Wabi-sabi bằng Tailwind.
* **Backend:** **Node.js (Express / Fastify)** hoặc **Go / Python (FastAPI)**.
  * Viết API đơn giản quản lý: Quản lý khung giờ (bật/tắt ca trong tuần), danh sách món ăn, danh sách khách quen (dựa trên SĐT đã lưu để tự động đề xuất tùy chọn không cần cọc).
* **Cơ sở dữ liệu:** **SQLite** hoặc **PostgreSQL**.
  * Với quy mô quán cá nhân, SQLite là lựa chọn tuyệt vời: không tốn RAM, lưu gọn trong 1 file, sao lưu dễ dàng.
* **Hạ tầng Debian:**
  * **Nginx** làm Reverse Proxy & phục vụ file tĩnh.
  * **Certbot (Let's Encrypt)** để cài chứng chỉ SSL/HTTPS miễn phí.
  * **PM2** để giữ backend chạy nền liên tục và tự khởi động lại khi reboot.

---

### 4. Gợi ý cấu trúc bảng dữ liệu đơn giản cho tính năng "Khách quen"

* **Bảng `Customers`:** `id`, `phone` (khóa định danh), `name`, `is_trusted` (cờ đánh dấu khách quen: `true/false`).
* **Logic thanh toán:**
  * Khi khách nhập số điện thoại:
    * Nếu `is_trusted == true`: Mặc định hiển thị nút chọn *"Thanh toán sau tại bếp"* kèm lời chào riêng (*"Chào mừng bạn quay lại"*).
    * Nếu là khách mới: Khuyến khích quét mã VietQR đặt cọc hoặc thanh toán trước để giữ suất bún/phở.

Bạn đã có kinh nghiệm lập trình với ngôn ngữ/framework nào (Node.js, Python, PHP hay Go) để có thể phác thảo sẵn mẫu cấu trúc code hoặc cấu hình Nginx mẫu cho Debian?

---

# you asked

message time: 2026-10-24 17:34:48

tôi có dùng php

---

# gemini response

PHP là một lựa chọn cực kỳ phù hợp cho môi trường Debian Linux: nhẹ, ổn định lâu dài, triển khai nhanh và tiết kiệm tài nguyên RAM tối đa.

Dưới đây là thiết kế chi tiết kiến trúc, cơ sở dữ liệu và mã nguồn mẫu chuẩn phong cách **Ghibli / An yên** tối ưu cho thiết bị di động.

---

### 1. Kiến trúc hệ thống trên Debian Linux

* **Web server & Runtime:** Nginx + PHP-FPM (PHP 8.2/8.3).
* **Cơ sở dữ liệu:** **SQLite3** (lưu dạng file đơn lẻ, không cần cài đặt MySQL nặng nề, sao lưu chỉ cần copy 1 file).
* **Giao diện:** HTML5 + Tailwind CSS (dùng qua CDN hoặc build sẵn) với bảng màu Washi/Ghibli.

Cài đặt nhanh trên Debian:
```bash
sudo apt update
sudo apt install nginx php-fpm php-sqlite3 -y
```

---

### 2. Thiết kế Cơ sở dữ liệu (SQLite)

Hệ thống chỉ cần 4 bảng tinh gọn:

```sql
-- 1. Khách hàng (Nhận diện khách quen / tin cậy)
CREATE TABLE customers (
    phone TEXT PRIMARY KEY,
    name TEXT,
    is_trusted INTEGER DEFAULT 0 -- 1: Khách quen (cho phép trả sau)
);

-- 2. Khung giờ phục vụ (Quản lý lịch mở đóng linh hoạt)
CREATE TABLE time_slots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slot_date TEXT,            -- Định dạng YYYY-MM-DD
    slot_time TEXT,            -- Ví dụ: "11:00 - 12:00"
    max_capacity INTEGER,      -- Số suất tối đa của ca
    is_active INTEGER DEFAULT 1
);

-- 3. Món ăn & Đồ uống
CREATE TABLE menu_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,                 -- Ví dụ: Phở chay nấm hương, Trà sen tuyết
    price INTEGER,
    category TEXT              -- 'food' hoặc 'drink'
);

-- 4. Đơn đặt chỗ & Đặt món
CREATE TABLE orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_phone TEXT,
    slot_id INTEGER,
    order_type TEXT,           -- 'dine_in' (tại chỗ) hoặc 'takeaway' (mang về)
    items_json TEXT,           -- Danh sách món dạng JSON
    total_amount INTEGER,
    payment_status TEXT,       -- 'paid' (đã chuyển khoản) hoặc 'pay_later' (khách quen)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

### 3. Mã nguồn PHP + Giao diện di động phong cách Ghibli

Tạo file `index.php` với giao diện tông màu kem giấy cổ (`#FAF7F2`), màu xanh trà dịu (`#5B7065`), chữ than mờ (`#2C2A29`):

```php
<?php
// Khởi tạo CSDL SQLite
$db = new SQLite3(__DIR__ . '/bep_an_yen.db');

// Xử lý kiểm tra số điện thoại có phải khách quen hay không qua AJAX
if (isset($_GET['action']) && $_GET['action'] === 'check_phone') {
    header('Content-Type: application/json');
    $phone = trim($_GET['phone'] ?? '');
    $stmt = $db->prepare('SELECT name, is_trusted FROM customers WHERE phone = :phone');
    $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
    $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    echo json_encode($res ?: ['is_trusted' => 0]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bếp Chay An Yên</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;1,400&family=Quicksand:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Quicksand', sans-serif;
            background-color: #FAF7F2; /* Tông màu giấy Washi */
            color: #2C2A29;
        }
        .font-serif-soft {
            font-family: 'Lora', serif;
        }
        /* Hiệu ứng bo góc và đổ bóng mộc mạc phong cách Ghibli */
        .ghibli-card {
            background-color: #FFFFFF;
            border-radius: 24px;
            box-shadow: 0 4px 20px -2px rgba(91, 112, 101, 0.08);
            border: 1px solid rgba(229, 231, 235, 0.6);
        }
    </style>
</head>
<body class="min-h-screen pb-12 flex justify-center">

    <div class="w-full max-w-md px-5 pt-8 space-y-6">
        
        <!-- Header: Không gian an yên -->
        <header class="text-center space-y-2">
            <span class="text-xs uppercase tracking-widest text-[#5B7065] font-semibold">Chốn dừng chân thanh tĩnh</span>
            <h1 class="text-3xl font-serif-soft text-[#344037] font-medium">Bếp Chay An Yên</h1>
            <p class="text-xs text-[#7D7871] italic">"Một bát canh thanh ngọt, vỗ về những bộn bề ngày thường"</p>
        </header>

        <!-- Thông báo lịch hôm nay -->
        <div class="bg-[#EEF2E6] text-[#3E4E43] p-4 rounded-2xl text-sm flex items-center space-x-3 border border-[#DDE5D4]">
            <div class="w-3 h-3 rounded-full bg-[#5B7065] animate-ping"></div>
            <div>
                <p class="font-medium">Lịch bếp hôm nay (Ca trưa):</p>
                <p class="text-xs text-[#5B7065]">Mở phục vụ từ 11:30 - 13:30 (Chỉ nhận tối đa 12 suất)</p>
            </div>
        </div>

        <form id="orderForm" action="process_order.php" method="POST" class="space-y-6">

            <!-- 1. Chọn hình thức -->
            <div class="ghibli-card p-5 space-y-3">
                <label class="text-sm font-semibold text-[#5B7065] block">1. Bạn muốn dùng món như thế nào?</label>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <label class="flex items-center justify-center p-3 rounded-xl border border-gray-200 cursor-pointer hover:border-[#5B7065] has-[:checked]:bg-[#EEF2E6] has-[:checked]:border-[#5B7065] has-[:checked]:text-[#3E4E43] transition">
                        <input type="radio" name="order_type" value="dine_in" checked class="hidden">
                        <span>Ghé bếp ăn tại chỗ</span>
                    </label>
                    <label class="flex items-center justify-center p-3 rounded-xl border border-gray-200 cursor-pointer hover:border-[#5B7065] has-[:checked]:bg-[#EEF2E6] has-[:checked]:border-[#5B7065] has-[:checked]:text-[#3E4E43] transition">
                        <input type="radio" name="order_type" value="takeaway" class="hidden">
                        <span>Bếp gửi ship về</span>
                    </label>
                </div>
            </div>

            <!-- 2. Khung giờ đặt trước -->
            <div class="ghibli-card p-5 space-y-3">
                <label class="text-sm font-semibold text-[#5B7065] block">2. Khung giờ bạn ghé / nhận món</label>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <label class="p-3 border rounded-xl cursor-pointer text-center has-[:checked]:bg-[#5B7065] has-[:checked]:text-white transition">
                        <input type="radio" name="time_slot" value="1" checked class="hidden">
                        <p class="font-medium">11:30 - 12:15</p>
                        <p class="text-[10px] opacity-75">Còn 4 chỗ</p>
                    </label>
                    <label class="p-3 border rounded-xl cursor-pointer text-center has-[:checked]:bg-[#5B7065] has-[:checked]:text-white transition">
                        <input type="radio" name="time_slot" value="2" class="hidden">
                        <p class="font-medium">12:30 - 13:15</p>
                        <p class="text-[10px] opacity-75">Còn 6 chỗ</p>
                    </label>
                </div>
            </div>

            <!-- 3. Món ăn tinh tuyển -->
            <div class="ghibli-card p-5 space-y-4">
                <label class="text-sm font-semibold text-[#5B7065] block">3. Món lành hôm nay</label>
                
                <div class="flex items-center justify-between border-b pb-3">
                    <div>
                        <h4 class="font-medium text-sm">Phở Nấm Hương Cổ Truyền</h4>
                        <p class="text-[11px] text-gray-500">Nước dùng quả lê, mía lau & nấm đông cô</p>
                        <span class="text-xs font-semibold text-[#5B7065]">45.000đ</span>
                    </div>
                    <input type="number" name="item_1" value="1" min="0" max="5" class="w-12 h-9 text-center border rounded-lg bg-gray-50 focus:outline-[#5B7065]">
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-medium text-sm">Trà Sen Tuyết Búp Nhẹ</h4>
                        <p class="text-[11px] text-gray-500">Hương hoa sen ướp lạnh, thanh giọng</p>
                        <span class="text-xs font-semibold text-[#5B7065]">25.000đ</span>
                    </div>
                    <input type="number" name="item_2" value="0" min="0" max="5" class="w-12 h-9 text-center border rounded-lg bg-gray-50 focus:outline-[#5B7065]">
                </div>
            </div>

            <!-- 4. Thông tin & Thanh toán linh hoạt -->
            <div class="ghibli-card p-5 space-y-4">
                <label class="text-sm font-semibold text-[#5B7065] block">4. Người thương gửi đơn</label>
                
                <input type="text" name="customer_name" id="custName" placeholder="Tên hoặc biệt danh của bạn" required
                       class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[#5B7065]">
                
                <input type="tel" name="customer_phone" id="custPhone" placeholder="Số điện thoại" required
                       class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[#5B7065]">

                <!-- Khu vực nhận diện khách quen -->
                <div id="trustedBadge" class="hidden p-3 bg-[#EEF2E6] text-[#3E4E43] rounded-xl text-xs space-y-1">
                    <p class="font-semibold">🌿 Chào bạn quen trở lại!</p>
                    <p>Bếp ghi nhận đơn ngay, bạn có thể thanh toán sau khi ghé quán nhé.</p>
                </div>

                <!-- Tùy chọn thanh toán -->
                <div class="space-y-2 pt-2">
                    <p class="text-xs text-gray-500">Phương thức thanh toán:</p>
                    <label class="flex items-center space-x-2 text-sm cursor-pointer">
                        <input type="radio" name="payment_method" value="vietqr" checked class="text-[#5B7065]">
                        <span>Quét mã VietQR chuyển khoản</span>
                    </label>
                    <label id="payLaterOption" class="flex items-center space-x-2 text-sm cursor-pointer hidden">
                        <input type="radio" name="payment_method" value="pay_later" class="text-[#5B7065]">
                        <span>Thanh toán khi dùng bữa (Dành cho khách quen)</span>
                    </label>
                </div>
            </div>

            <!-- Nút gửi đơn -->
            <button type="submit" class="w-full py-4 bg-[#5B7065] hover:bg-[#4D5C4C] text-white font-medium rounded-2xl shadow-lg transition">
                Gửi Đặt Bếp An Yên
            </button>
        </form>
    </div>

    <!-- Script nhận diện khách quen khi gõ số điện thoại -->
    <script>
        const phoneInput = document.getElementById('custPhone');
        const trustedBadge = document.getElementById('trustedBadge');
        const payLaterOption = document.getElementById('payLaterOption');
        const nameInput = document.getElementById('custName');

        phoneInput.addEventListener('blur', function() {
            const phone = this.value.trim();
            if (phone.length >= 9) {
                fetch(`?action=check_phone&phone=${phone}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.is_trusted == 1) {
                            trustedBadge.classList.remove('hidden');
                            payLaterOption.classList.remove('hidden');
                            if (data.name && !nameInput.value) {
                                nameInput.value = data.name;
                            }
                        } else {
                            trustedBadge.classList.add('hidden');
                            payLaterOption.classList.add('hidden');
                        }
                    });
            }
        });
    </script>
</body>
</html>
```

---

### 4. Cấu hình Nginx mẫu trên Debian

Tạo file cấu hình virtual host tại `/etc/nginx/sites-available/bepchay`:

```nginx
server {
    listen 80;
    server_name yourdomain.com; # Hoặc IP máy chủ Debian
    root /var/www/bepchay;

    index index.php index.html;

    # Bảo vệ file database SQLite không bị tải lậu từ trình duyệt
    location ~ \.db$ {
        deny all;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php-fpm.sock; # Kiểm tra đúng phiên bản PHP trên Debian
    }
}
```

Kích hoạt site:
```bash
sudo ln -s /etc/nginx/sites-available/bepchay /etc/nginx/sites-enabled/
sudo systemctl reload nginx
```

### 5. Quản lý trạng thái Khách Quen

Khi bạn có khách hàng thân thiết, bạn chỉ cần gán cờ `is_trusted = 1` trong bảng `customers` (có thể gõ lệnh trực tiếp trong SQLite hoặc làm một trang `/admin.php` đơn giản có mật khẩu):

```bash
sqlite3 /var/www/bepchay/bep_an_yen.db "INSERT OR REPLACE INTO customers (phone, name, is_trusted) VALUES ('0912345678', 'Chị Lan', 1);"
```

Khi chị Lan nhập số điện thoại trên điện thoại, hệ thống sẽ tự động chào thân thiện và mở tùy chọn **"Thanh toán sau"** mà không yêu cầu thanh toán hay cọc trước. Khách mới vẫn sẽ thấy mã QR chuyển khoản để xác nhận đơn.

---
Powered by [AI Exporter](https://saveai.net)