# SCREEN_DESIGN.md - Thiết Kế Giao Diện & Trải Nghiệm Người Dùng (Ghibli & Zen UI/UX)

Tài liệu này quy chuẩn hệ thống Design System, bảng màu, font chữ, bố cục màn hình và các thành phần giao diện di động (Mobile-First) cho hệ thống **VeganFoodShop (Bếp Chay An Lành)**.

---

## 1. Triết lý Thiết kế: "Hơi thở Ghibli & Thiền Vị (Zen)"

Giao diện của quán chay được định vị là **“chốn quay về an yên tĩnh lặng”**. Mọi chi tiết thiết kế đều hướng tới sự ấm áp, mộc mạc, bình an, không hối hả thương mại hóa.

### 1.1. Bảng màu chuẩn (Design Tokens)

| Token | Mã màu Hex | Ý nghĩa & Vị trí áp dụng |
| :--- | :--- | :--- |
| **`bg-washi-primary`** | `#FAF7F2` | Nền chính toàn bộ trang web (màu giấy Washi Nhật Bản ấm áp, dịu mắt). |
| **`bg-washi-card`** | `#FFFFFF` | Nền thẻ (Card) nổi bật nhẹ nhàng trên nền giấy. |
| **`brand-tea-green`** | `#5B7065` | Xanh lá trà dịu (Màu nhận diện chính: Nút bấm, viền thẻ được chọn, icon, tiêu đề phụ). |
| **`brand-tea-dark`** | `#344037` | Xanh rêu đậm (Tiêu đề chính H1/H2). |
| **`brand-sage-light`** | `#EEF2E6` | Xanh xô thơm nhạt (Nền thông báo lịch hôm nay, badge khách quen, thẻ được tick). |
| **`brand-border-soft`** | `#DDE5D4` | Viền nhẹ nhàng của các khung thông báo. |
| **`text-charcoal`** | `#2C2A29` | Màu chữ chính (Nâu than mờ, tự nhiên như nét mực trên giấy thủ công). |
| **`text-muted-warm`** | `#7D7871` | Màu chữ phụ, câu châm ngôn, mô tả nguyên liệu món ăn. |
| **`accent-wood`** | `#8C7A6B` | Màu đất son / nâu gỗ nhạt (nhấn giá tiền hoặc chi tiết phụ). |

### 1.2. Kiểu chữ (Typography)

* **Font Tiêu đề & Lời ngỏ:** **`Lora`** (Google Fonts Serif)
  * Cảm xúc: Cổ điển, thanh tao, ấm áp, sâu lắng.
  * Trọng số (Weight): Regular 400, Medium 500.
* **Font Thân bài, Nút bấm & Form:** **`Quicksand`** hoặc **`Be Vietnam Pro`** (Google Fonts Sans-Serif)
  * Cảm xúc: Thân thiện, bo tròn nhẹ nhàng, cực kỳ dễ đọc trên màn hình điện thoại 375px - 430px.
  * Trọng số (Weight): Regular 400, Medium 500, Semi-bold 600.

### 1.3. Hiệu ứng thị giác & Bo góc (Surfaces & Shadows)
* **Bo góc (Border Radius):**
  * Thẻ Card lớn: `border-radius: 24px` (`rounded-3xl`).
  * Nút bấm & Ô nhập liệu: `border-radius: 14px - 16px` (`rounded-2xl`).
* **Đổ bóng mộc (Soft Shadow):**
  * `box-shadow: 0 4px 20px -2px rgba(91, 112, 101, 0.08);`
  * Đổ bóng màu rêu trà mờ nhạt, tuyệt đối không dùng bóng đen sắc cạnh.
* **Chuyển cảnh (Transitions):**
  * Chuyển động nhẹ nhàng (`transition: all 0.25s ease-in-out`), tạo cảm giác êm ả khi người dùng chạm vào các ô chọn.

---

## 2. Chi tiết Màn hình Đặt Món Mobile (`index.php`)

Giao diện được bố trí theo dạng **dòng chảy cuộn dọc (Story-telling Flow)** trên một cột duy nhất (tối ưu tối đa chiều rộng `max-w-md` 448px):

```text
+------------------------------------------+
|          [Bếp Chay An Lành]               |
|      Chốn dừng chân thanh tĩnh           |
| "Một bát canh thanh ngọt, vỗ về bộn bề"  |
+------------------------------------------+
| [●] Lịch bếp hôm nay (Ca trưa)           |
|     11:30 - 13:30 (Chỉ nhận 12 suất)     |
+------------------------------------------+
| 1. BẠN MUỐN DÙNG MÓN NHƯ THẾ NÀO?        |
| [ (*) Ghé bếp ăn tại chỗ ] [ ( ) Ship ]  |
+------------------------------------------+
| 2. KHUNG GIỜ BẠN GHÉ / NHẬN MÓN          |
| [ Ca 1: 11:30 - 12:15 ] [ Ca 2: 12:30.. ]|
| (Còn 4 chỗ)             (Còn 6 chỗ)      |
+------------------------------------------+
| 3. MÓN LÀNH HÔM NAY                      |
| • Phở Nấm Hương Cổ Truyền       [ - 1 + ]|
|   Nước dùng quả lê, mía lau...   45.000đ |
| • Trà Sen Tuyết Búp Nhẹ         [ - 0 + ]|
|   Hương hoa sen ướp lạnh...      25.000đ |
+------------------------------------------+
| 4. NGƯỜI THƯƠNG GỬI ĐƠN                  |
| [ Tên hoặc biệt danh của bạn           ] |
| [ Số điện thoại                        ] |
| (AJAX tự hiện badge Khách quen nếu đúng) |
| Phương thức thanh toán:                  |
| (•) Quét mã VietQR chuyển khoản          |
| ( ) Thanh toán khi dùng bữa (Khách quen) |
+------------------------------------------+
|     [ GỬI ĐẶT Bếp An Lành (45.000đ) ]     |
+------------------------------------------+
```

### Các thành phần chi tiết:
1. **Header & Câu chào an yên:** Tạo cảm giác nhẹ lòng ngay khi mở ứng dụng.
2. **Khung thông báo lịch mở bếp:** Sử dụng chấm tròn có hiệu ứng lan tỏa nhẹ (`animate-ping`) màu xanh trà, thông báo rõ giờ mở ca và số suất tối đa.
3. **Thẻ 1 - Hình thức:** Hai nút radio dạng card lớn, khi click sẽ sáng màu xanh xô thơm `#EEF2E6` kèm viền xanh trà `#5B7065`.
4. **Thẻ 2 - Chọn ca:** Mỗi ô khung giờ thể hiện rõ giờ bắt đầu - kết thúc và số suất còn lại. Nếu ca đã đầy: hiển thị nhãn *"Đã hết suất"*, mờ mờ (`opacity-50`) và khóa click (`pointer-events-none`).
5. **Thẻ 3 - Món ăn:** Thiết kế rõ ràng với tên món, mô tả nguyên liệu mộc mạc và nút cộng/trừ số lượng to bản, dễ bấm bằng ngón tay cái.
6. **Thẻ 4 - Thông tin & Khách quen:**
   - Trường nhập số điện thoại tự động kiểm tra khách quen khi người dùng kết thúc gõ (`onblur`).
   - Nếu là khách quen: Hiện bảng chào mừng mộc mạc màu xanh ngọc nhạt và hiển thị thêm nút radio *"Thanh toán khi dùng bữa (Dành cho khách quen)"*.
   - Khách mới: Chỉ hiển thị thanh toán VietQR chuyển khoản.
7. **Nút gửi đơn:** Nút to bản, nền màu xanh trà `#5B7065`, cố định hoặc nằm cuối form, thể hiện tổng tiền tạm tính.

---

## 3. Màn hình Xác nhận Đơn & Mã VietQR

Sau khi gửi đơn thành công, khách được chuyển tới màn hình xác nhận hoặc mở modal an lành:

* **Đối với đơn VietQR:**
  * Hiển thị mã QR động chuẩn VietQR được sinh tự động:
    `https://img.vietqr.io/image/<BANK_ID>-<ACCOUNT_NO>-compact2.png?amount=<TOTAL>&addInfo=<ORDER_CODE>`
  * Nút "Sao chép số tiền" và "Sao chép số tài khoản".
  * Thông điệp: *"Bếp đã tạm giữ suất ăn cho bạn. Khi nhận được chuyển khoản, bếp sẽ chuẩn bị món chu đáo."*
* **Đối với đơn Khách Quen (Trả sau):**
  * Không hiển thị mã QR thanh toán.
  * Hiển thị mã đơn hàng, ngày giờ ca và thông điệp: *"Bếp đã ghi nhận đơn. Bạn ghé đúng giờ để thưởng thức bát bún/phở nóng hổi nhé!"*.

---

## 4. Màn hình Quản trị Bếp (`admin.php`)

Giao diện Admin tối giản, gọn gàng, có mật khẩu bảo vệ:
* **Tab 1 - Quản lý Ca (Slots):** Xem danh sách ca trong tuần, bật/tắt nhận khách, chỉnh số suất tối đa `max_capacity`.
* **Tab 2 - Quản lý Đơn (Orders):** Lọc theo ca phục vụ, xem danh sách số lượng từng món cần nấu, đổi trạng thái đơn (`confirmed`, `paid`, `completed`, `cancelled`).
* **Tab 3 - Khách Quen (Customers):** Tìm kiếm theo SĐT, bật/tắt công tắc Khách quen (`is_trusted`).
* **Tab 4 - Thực đơn (Menu):** Cập nhật giá món, ẩn món tạm hết.
