# Hệ Thống Đăng Ký & Đăng Nhập E-Learning (PHP, MySQL & Docker)

Bài thực hành xây dựng hệ thống Đăng ký (Register), Đăng nhập (Login), Xác thực tài khoản qua Email (SMTP PHPMailer) và Phân quyền người dùng, đóng gói hoàn chỉnh bằng Docker Compose kết hợp **Nginx Reverse Proxy**.

---

## I. CÁC CHỨC NĂNG CƠ BẢN (Hoàn thành 100% theo tài liệu hướng dẫn)

1. **Giao diện người dùng (Frontend HTML, CSS, JavaScript):**
   - Xây dựng đầy đủ các trang: `index.php`, `register.php`, `login.php`, `verify_email.php`, `dashboard.php`, `logout.php`.
   - Giao diện đồng bộ qua `css/style.css`.
   - Kiểm tra dữ liệu đầu vào phía Client (`js/validate.js`): kiểm tra độ dài username ($\ge 3$ ký tự), định dạng email, độ mạnh mật khẩu ($\ge 6$ ký tự, gồm chữ hoa, chữ thường, chữ số) và xác nhận mật khẩu.
2. **Thiết kế Cơ sở dữ liệu MySQL (`database/init.sql`):**
   - Khởi tạo tự động CSDL `login_system` (`utf8mb4_unicode_ci`) và bảng `users` khi chạy container MySQL.
3. **Xử lý Backend PHP (`app/src/`):**
   - Kết nối MySQL an toàn bằng **PDO** (`db.php`) với Prepared Statements chống SQL Injection.
   - Băm mật khẩu bằng thuật toán **SHA256** (`hash('sha256', $password)`) theo đúng yêu cầu bài thực hành.
   - Gửi email xác nhận tài khoản qua giao thức **SMTP STARTTLS** sử dụng thư viện **PHPMailer** (`mailer.php`).
   - Xác thực tài khoản qua `verification_token` (`verify_email.php`), chặn đăng nhập nếu `email_verified = 0`.
   - Quản lý phiên đăng nhập (**Session**) và tính năng **"Nhớ mật khẩu"** (**Cookie** `remember_user`, `remember_token`).
4. **Đóng gói và triển khai Docker (`Dockerfile` & `docker-compose.yml`):**
   - Đóng gói đầy đủ các dịch vụ: `nginx` (Reverse Proxy), `php-app` (PHP 8.2 Apache), `mysql` (MySQL 8.0), và `phpmyadmin`.
   - Quản lý biến môi trường tập trung qua file `.env`.

---

## II. CÁC CHỨC NĂNG ĐÃ ĐƯỢC NÂNG CẤP (11 Hạng mục từ Mục 46 - Trang 49 tài liệu)

Để hệ thống hoàn thiện, bảo mật và sát thực tế ứng dụng **E-Learning** hơn, bài làm đã nâng cấp và bổ sung **11 tính năng từ Mục 46**:

| STT | Hạng mục nâng cấp (Mục 46) | Chi tiết triển khai trong bài làm |
| :-: | :--- | :--- |
| **1** | **Thêm Nginx reverse proxy** | • Cấu hình service `nginx` (`docker/nginx/default.conf`) lắng nghe cổng `8080`, nén gzip và proxy ngầm vào container `php-app`.<br>• Trả về header `Server: nginx` và `X-Reverse-Proxy: Nginx-Reverse-Proxy-Elearning`. |
| **2** | **Thêm role `user` / `admin`** | • Thêm cột `role ENUM('admin', 'user') NOT NULL DEFAULT 'user'` trong bảng `users`.<br>• Tạo sẵn 1 tài khoản Quản trị viên chính thức (`admin` / `Admin@123`).<br>• Mọi tài khoản đăng ký mới qua `register.php` mặc định là Học viên / Khách (`role = 'user'`). |
| **3** | **Thêm Middleware kiểm tra quyền truy cập** | • Bổ sung hàm `isAdmin()` và `requireAdmin()` trong `app/src/auth.php`.<br>• Tự động điều hướng Admin vào `admin.php` và Học viên vào `dashboard.php`.<br>• Chặn hoàn toàn tài khoản khách (`user`) nếu cố tình truy cập trái phép vào `/admin.php`. |
| **4** | **Thêm CSRF Token cho toàn bộ Form** | • Bổ sung hàm `generateCsrfToken()` và `verifyCsrfToken()` trong `app/src/auth.php`.<br>• Tích hợp thẻ ẩn `<input type="hidden" name="csrf_token">` vào toàn bộ form POST (`login.php`, `register.php`, `forgot_password.php`, `reset_password.php`, `verify_otp.php`, `dashboard.php`, `admin.php`). |
| **5** | **Thêm giới hạn số lần Login sai (Rate Limiting)** | • Bổ sung cột `failed_attempts` và `locked_until` trong bảng `users`.<br>• Khi nhập sai mật khẩu **5 lần liên tiếp**, hệ thống tự động **tạm khóa tài khoản trong 15 phút** chống tấn công Brute-force.<br>• Admin có thể bấm nút **"Mở khóa"** trực tiếp trên trang `admin.php`. |
| **6** | **Thêm Token hết hạn sau 15 đến 30 phút** | • Bổ sung cột `token_expires_at` (30 phút cho xác nhận email đăng ký) và `reset_expires_at` (15 phút cho đặt lại mật khẩu).<br>• Tự động từ chối nếu token đã quá thời hạn hiệu lực. |
| **7** | **Thêm Logging lịch sử đăng nhập** | • Tạo thêm bảng `login_logs` trong `database/init.sql` và hàm `logLoginAttempt()` trong `app/src/auth.php`.<br>• Ghi lại mọi lượt đăng nhập (`success`, `failed`, `locked`, `unverified`) kèm IP, User-Agent, thời gian và hiển thị trực tiếp trên trang `admin.php`. |
| **8** | **Thêm Reset Password qua Email** | • Xây dựng trang `forgot_password.php` và `reset_password.php` kèm hàm `requestPasswordReset()`, `resetPasswordWithToken()`.<br>• Gửi link đặt lại mật khẩu qua SMTP (kèm hiển thị link trực tiếp trên giao diện khi chạy môi trường Local Demo). |
| **9** | **Dùng `password_hash()` kết hợp `SHA256` (Hybrid)** | • Bổ sung hàm `hashPasswordSecure()` (`PASSWORD_ARGON2ID` / `PASSWORD_BCRYPT`) và `verifyPasswordHybrid()` trong `app/src/auth.php`.<br>• Hệ thống hỗ trợ song song cả chuẩn `SHA256` (yêu cầu cơ bản) lẫn chuẩn `password_hash()` hiện đại khi người dùng đặt lại mật khẩu mới. |
| **10** | **Thêm xác thực 2 lớp OTP (2FA)** | • Xây dựng trang `verify_otp.php` và nút **Bật/Tắt 2FA OTP** trong `dashboard.php`.<br>• Khi bật 2FA, sau khi nhập đúng mật khẩu, hệ thống sinh mã OTP 6 chữ số (hiệu lực 5 phút) gửi qua Email và yêu cầu xác nhận trước khi vào hệ thống. |
| **11** | **Thêm CI/CD với GitHub Actions** | • Thiết lập pipeline `.github/workflows/ci.yml` tự động kiểm tra `composer.json`, kiểm tra cú pháp toàn bộ file PHP (`php -l`) và kiểm thử build Docker Compose khi push code lên GitHub. |

> **Ghi chú kỹ thuật bổ sung:** Bài làm cũng đã khắc phục 2 lỗi tiềm ẩn từ code mẫu tài liệu:
> - Bọc `if (!function_exists("envValue"))` tại `app/src/config.php` để tránh lỗi `Cannot redeclare envValue()`.
> - Gọi đúng `session_name($config["app"]["session_name"])` trước `session_start()` tại `app/public/logout.php` để hủy triệt để phiên `LOGIN_SYSTEM_SESSION` khi đăng xuất.

---

## III. THÔNG TIN TÀI KHOẢN TEST DÀNH CHO GIẢNG VIÊN

### 1. Tài khoản Quản trị viên (Admin chính thức - Khởi tạo sẵn)
- **URL Đăng nhập:** http://localhost:8080/login.php
- **Username:** `admin` *(hoặc Email: `admin@elearning.edu.vn`)*
- **Password:** `Admin@123`
- **Tính năng tại `http://localhost:8080/admin.php`:**
  - Xem thống kê tổng quan hệ thống.
  - **Duyệt Email trực tiếp:** Bấm nút **"Duyệt Email"** để kích hoạt ngay tài khoản học viên vừa đăng ký.
  - **Mở khóa tài khoản:** Bấm nút **"Mở khóa"** cho tài khoản bị khóa do nhập sai mật khẩu quá 5 lần.
  - Xem bảng **Nhật ký đăng nhập (Login Audit Logs)** theo thời gian thực.

### 2. Tài khoản Học viên / Khách (User)
- Đăng ký mới tại: http://localhost:8080/register.php
- *Quy tắc mật khẩu (theo hàm JS validate):* Tối thiểu 6 ký tự, có ít nhất 1 chữ hoa, 1 chữ thường và 1 chữ số (Ví dụ: `Abc12345`).
- Sau khi đăng nhập vào `dashboard.php`, học viên có thể bấm **"Bật 2FA"** để trải nghiệm tính năng Xác thực 2 lớp OTP hoặc bấm **"Đổi MK"** (`forgot_password.php`) để nâng cấp mật khẩu sang chuẩn `password_hash()`.

---

## IV. HƯỚNG DẪN KHỞI CHẠY VỚI DOCKER COMPOSE

Tại thư mục gốc dự án, chạy lệnh:

```bash
docker compose up -d --build
```

*(Lưu ý: Nếu trên máy đã có volume MySQL cũ từ trước khiến `database/init.sql` không tự chạy lại, vui lòng chạy `docker compose down -v` trước rồi chạy `docker compose up -d --build`).*

### Địa chỉ truy cập
- **Trang chủ (qua Nginx Reverse Proxy):** http://localhost:8080
- **Trang Đăng ký:** http://localhost:8080/register.php
- **Trang Đăng nhập:** http://localhost:8080/login.php
- **Trang Quên mật khẩu:** http://localhost:8080/forgot_password.php
- **Trang Quản trị (Admin):** http://localhost:8080/admin.php
- **Trang Học viên (Dashboard):** http://localhost:8080/dashboard.php
- **phpMyAdmin:** http://localhost:8081 (`mysql` / `login_user` / `login_password`)
