# 🍽️ Trạm Thực Restaurant - Website Quảng Bá & Đặt Bàn Trực Tuyến

---

## 📖 Giới Thiệu Dự Án

Hệ thống website **Nhà hàng Trạm Thực** được xây dựng nhằm số hóa quy trình tiếp nhận và quản lý đặt bàn trực tuyến, quảng bá thực đơn và không gian nhà hàng đến thực khách. Dự án giải quyết các bài toán vận hành thực tế: loại bỏ sai sót ghi nhận đặt bàn thủ công, hạn chế trùng bàn, kiểm soát trạng thái bàn theo thời gian thực và quản lý thực đơn linh hoạt.

---

## ✨ Tính Năng Nổi Bật

### 1. Phân Hệ Khách Hàng (Customer)
* **Khám phá không gian & Thực đơn:** Khách vãng lai có thể tra cứu thông tin nhà hàng, giờ mở cửa và duyệt danh sách món ăn theo danh mục kèm hình ảnh và giá niêm yết mà không cần đăng nhập.
* **Xác thực & Bảo mật:**
  * Đăng ký tài khoản, đăng nhập phân quyền và quản lý phiên làm việc bằng Session.
  * Mật khẩu lưu trữ được băm bảo mật bằng thuật toán **Bcrypt**.
  * **Quên mật khẩu:** Khôi phục tài khoản thông qua xác thực **mã OTP 6 số gửi tự động qua Email** (sử dụng giao thức SMTP của thư viện PHPMailer, thời hạn mã 10 phút).
* **Đặt bàn trực tuyến (Booking):**
  * Giao diện hai cột: Form điền thông tin đặt lịch bên trái và Bảng theo dõi lịch sử đặt bàn bên phải.
  * Ràng buộc nghiệp vụ kiểm tra số lượng khách (tối đa 20 người/đơn), thời gian đặt hợp lệ.
  * Gửi email xác nhận đặt lịch tự động sau khi tạo đơn thành công.
* **Nghiệp vụ Hủy bàn thông minh:**
  * Khách hàng được phép hủy đơn đặt bàn trực tiếp trên giao diện cá nhân.
  * Áp dụng điều kiện nghiệp vụ: Chỉ cho phép hủy khi đơn ở trạng thái *Chờ xác nhận* hoặc *Đã xác nhận nhưng thời gian hiện tại cách giờ đến trên 2 tiếng*.
* **Quản lý hồ sơ cá nhân (Profile):** Cập nhật thông tin liên hệ và chủ động thay đổi mật khẩu tài khoản.

### 2. Phân Hệ Quản Trị Viên (Admin)
* **Dashboard điều hành:** Tổng quan tình hình hoạt động của nhà hàng.
* **Quản lý Thực đơn (Menu CRUD):**
  * Phân loại theo nhóm danh mục: *Khai vị, Món chính, Tráng miệng, Đồ uống*.
  * Thêm món mới: Tự động sinh mã khóa chính dạng chuẩn `MIxxxx`, băm tên file ảnh chống trùng lặp `time()_uniqid()`.
  * Chỉnh sửa thông tin, giá bán và cập nhật trạng thái kinh doanh (*Đang bán* / *Ngừng bán*) trực tiếp qua cửa sổ nổi (Modal Popup).
  * Bộ lọc danh mục nhanh và tìm kiếm món ăn tức thời.
* **Quản lý Đặt bàn (Reservation Operations):**
  * Lọc danh sách lịch hẹn theo ngày và theo trạng thái.
  * Phê duyệt trạng thái đơn: *Chờ xác nhận ➜ Đã xác nhận ➜ Đang phục vụ ➜ Hoàn thành / Đã hủy*.
  * **Gán bàn thực tế:** Chỉ định bàn trống cho khách; hệ thống tự động khóa bàn tương ứng và trả lại trạng thái bàn trống khi đơn kết thúc.
* **Quản lý Người dùng (User Management):**
  * Tra cứu tài khoản theo số điện thoại hoặc email.
  * Khóa tài khoản đối với các trường hợp nghi ngờ spam hoặc tạo tài khoản ảo.

---

## 🛠️ Công Nghệ Sử Dụng

| Thành phần | Công nghệ / Thư viện | Vai trò |
| :--- | :--- | :--- |
| **Frontend** | HTML5, CSS3, JavaScript | Cấu trúc giao diện và các tương tác động |
| | Bootstrap 5, Font Awesome 6 | Thiết kế Responsive tương thích máy tính và thiết bị di động |
| | AJAX | Làm mới dữ liệu lịch sử và bộ lọc thực đơn không cần tải lại trang |
| **Backend** | PHP (PDO / Native) | Xử lý logic nghiệp vụ, bảo mật phiên (Session), mã hóa mật khẩu |
| | PHPMailer | Tích hợp gửi mã OTP xác thực tài khoản qua giao thức SMTP Gmail |
| **Database** | MySQL (Chuẩn hóa 3NF) | Lưu trữ dữ liệu quan hệ, đảm bảo toàn vẹn dữ liệu |
| **Môi trường** | XAMPP (Apache, MySQL) | Web Server cục bộ (Localhost) |

---

## 🗄️ Thiết Kế Cơ Sở Dữ Liệu

Hệ thống được chuẩn hóa đạt chuẩn **3NF (Dạng chuẩn 3)** gồm 5 bảng dữ liệu quan hệ chặt chẽ:

1. **`Users`**: Lưu thông tin người dùng (`userId`, `fullName`, `phone`, `email`, `password`, `role`, `status`, `otp_code`, `otp_expiry`...).
2. **`MenuCategories`**: Danh mục phân loại món ăn (`categoryId`, `categoryName`, `categoryStatus`...).
3. **`MenuItems`**: Chi tiết món ăn (`menuItemId`, `categoryId`, `menuItemName`, `image`, `price`, `description`, `menuItemStatus`...).
4. **`Tables`**: Quản lý cơ sở vật chất bàn ăn (`tableId`, `tableNumber`, `capacity`, `tableType`, `tableStatus`...).
5. **`Reservations`**: Ghi nhận đơn đặt bàn liên kết giữa khách hàng và bàn ăn (`reservationId`, `userId`, `tableId`, `numberOfGuests`, `reservationTime`, `bookingStatus`...).

---

## 📁 Cấu Trúc Thư Mục Dự Án

```text
first_project/
├── PHPMailer/              # Thư viện hỗ trợ gửi mail qua SMTP
├── images/                 # Tài nguyên hình ảnh tĩnh giao diện
├── uploads/                # Thư mục lưu trữ hình ảnh món ăn tải lên từ Admin
├── db.php                  # Kết nối cơ sở dữ liệu MySQL (PDO/MySQLi)
├── mailer.php              # Cấu hình tài khoản gửi mail OTP
├── index.php               # Trang chủ giới thiệu & hiển thị thực đơn
├── login.php               # Giao diện & xử lý đăng nhập
├── register.php            # Giao diện & xử lý đăng ký
├── logout.php              # Đăng xuất và hủy phiên làm việc
├── forgot_password.php     # Nhập email yêu cầu mã xác thực OTP
├── verify_otp.php          # Xác thực mã OTP và đổi mật khẩu mới
├── booking.php             # Đặt bàn trực tuyến & theo dõi lịch sử
├── profile.php             # Quản lý thông tin cá nhân & đổi mật khẩu
├── admin_dashboard.php     # Trang tổng quan dành cho Quản trị viên
├── admin_menu.php          # Quản lý danh mục & món ăn (CRUD)
├── admin_reservations.php  # Quản trị đơn đặt bàn & gán bàn
├── admin_users.php         # Quản lý danh sách tài khoản người dùng
└── TramThucRestaurant.sql  # Tệp sao lưu cơ sở dữ liệu
