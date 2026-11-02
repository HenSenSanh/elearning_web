# BÁO CÁO BÀI THỰC HÀNH
## HƯỚNG DẪN XÂY DỰNG TRANG LOGIN / REGISTER BẰNG PHP, MYSQL VÀ DOCKER

---

## 1. MỤC TIÊU BÀI THỰC HÀNH
Xây dựng một hệ thống hoàn chỉnh cho việc đăng ký, đăng nhập và xác nhận tài khoản qua email gồm:
* Giao diện đăng ký và đăng nhập chuẩn HTML, CSS, JavaScript.
* Các trường nhập liệu: Username, Password, Confirm password, Remember password, Email confirmation.
* Validate dữ liệu phía client bằng JavaScript.
* Thiết kế cơ sở dữ liệu MySQL (bảng `users`).
* Xử lý backend bằng PHP:
  * Mã hóa mật khẩu bằng SHA256 (theo yêu cầu bài thực hành).
  * Gửi email xác nhận tài khoản qua giao thức SMTP (sử dụng thư viện PHPMailer).
  * Lưu phiên đăng nhập (Session) và xử lý Remember Me (Cookie).
  * Chuyển hướng người dùng vào trang Dashboard.
* Đóng gói toàn bộ hệ thống bằng Docker Compose (`php-app`, `mysql`, `phpmyadmin`).
* Cấu hình biến môi trường qua file `.env`.
* Build, chạy, kiểm thử và hướng dẫn push Docker image lên Docker Hub.

---

## 2. GHI CHÚ BẢO MẬT QUAN TRỌNG
* **Bài thực hành:** Mật khẩu được băm (hash) bằng SHA256:
  ```php
  hash('sha256', $password);
  ```
* **Môi trường thực tế (Khuyến nghị nâng cấp):** SHA256 không an toàn để lưu trữ mật khẩu vì tốc độ tính toán quá nhanh, dễ bị tấn công Brute-force hoặc Dictionary attack khi lộ database. Trong thực tế, nên dùng thuật toán mã hóa mật khẩu thích ứng:
  ```php
  // Băm mật khẩu
  password_hash($password, PASSWORD_ARGON2ID);
  // hoặc
  password_hash($password, PASSWORD_BCRYPT);

  // Xác minh mật khẩu
  password_verify($password, $hash);
  ```

---

## 3. KIẾN TRÚC VÀ LUỒNG HOẠT ĐỘNG CỦA HỆ THỐNG

```
User
  │ (1) Nhập form register/login
  ▼
HTML + CSS + JavaScript
  │ (2) Validate dữ liệu client-side
  ▼
PHP Backend
  │ (3) Kết nối MySQL, kiểm tra dữ liệu, hash SHA256, tạo token xác thực
  ├── Gửi mail SMTP ──────────────► Email xác nhận
  ▼                                      │ (4) Click link verify
MySQL Database                           ▼
  │ (Lưu user, password_hash,    PHP verify_email.php
  │  verification_token)                 │ (5) Cập nhật email_verified = 1
  ▼                                      ▼
Dashboard ◄────── Lưu session ◄──── Login thành công
```

---

## 4. CẤU TRÚC THƯ MỤC DỰ ÁN

```text
php-login-system/
├── app/
│   ├── public/
│   │   ├── css/
│   │   │   └── style.css
│   │   ├── js/
│   │   │   └── validate.js
│   │   ├── dashboard.php
│   │   ├── index.php
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── register.php
│   │   └── verify_email.php
│   └── src/
│       ├── auth.php
│       ├── config.php
│       ├── db.php
│       └── mailer.php
├── database/
│   └── init.sql
├── docker/
│   └── php/
│       └── Dockerfile
├── .env
├── composer.json
├── docker-compose.yml
└── README.md
```

---

## 5. MÃ NGUỒN FRONTEND VÀ GIAO DIỆN

### 5.1. File `app/public/index.php`
```php
<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>PHP Login System</h1>
        <p>Hệ thống đăng ký, đăng nhập và xác nhận email.</p>
        <div class="menu">
            <a href="register.php" class="btn">Đăng ký</a>
            <a href="login.php" class="btn btn-secondary">Đăng nhập</a>
        </div>
    </div>
</body>
</html>
```

---

### 5.2. File `app/public/register.php`
```php
<?php
require_once __DIR__ . '/../src/auth.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username        = trim($_POST["username"] ?? "");
    $email           = trim($_POST["email"] ?? "");
    $password        = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    $result  = registerUser($username, $email, $password, $confirmPassword);
    $message = $result["message"];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký tài khoản</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="form-container">
        <h2>Đăng ký tài khoản</h2>

        <?php if (!empty($message)): ?>
            <div class="message">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form id="registerForm" method="POST" action="register.php" novalidate>
            <div class="form-group">
                <label for="username">User name</label>
                <input type="text" id="username" name="username" placeholder="Nhập user name">
                <small class="error" id="usernameError"></small>
            </div>

            <div class="form-group">
                <label for="email">Email xác nhận</label>
                <input type="email" id="email" name="email" placeholder="Nhập email">
                <small class="error" id="emailError"></small>
            </div>

            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" placeholder="Nhập mật khẩu">
                <small class="error" id="passwordError"></small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Xác nhận mật khẩu</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Nhập lại mật khẩu">
                <small class="error" id="confirmPasswordError"></small>
            </div>

            <button type="submit" class="btn-full">Đăng ký</button>
        </form>

        <p class="link-text">
            Đã có tài khoản? <a href="login.php">Đăng nhập</a>
        </p>
    </div>

    <script src="js/validate.js"></script>
</body>
</html>
```

---

### 5.3. File `app/public/login.php`
```php
<?php
require_once __DIR__ . '/../src/auth.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usernameOrEmail = trim($_POST["username_or_email"] ?? "");
    $password        = $_POST["password"] ?? "";
    $remember        = isset($_POST["remember"]);

    $result = loginUser($usernameOrEmail, $password, $remember);

    if ($result["success"]) {
        header("Location: dashboard.php");
        exit;
    }

    $message = $result["message"];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="form-container">
        <h2>Đăng nhập</h2>

        <?php if (!empty($message)): ?>
            <div class="message error-box">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="login.php" novalidate>
            <div class="form-group">
                <label for="username_or_email">User name hoặc email</label>
                <input
                    type="text"
                    id="username_or_email"
                    name="username_or_email"
                    placeholder="Nhập user name hoặc email"
                    value="<?php echo htmlspecialchars($_COOKIE['remember_user'] ?? ''); ?>"
                >
                <small class="error" id="loginUsernameError"></small>
            </div>

            <div class="form-group">
                <label for="login_password">Mật khẩu</label>
                <input type="password" id="login_password" name="password" placeholder="Nhập mật khẩu">
                <small class="error" id="loginPasswordError"></small>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember" <?php echo isset($_COOKIE['remember_user']) ? 'checked' : ''; ?>>
                <label for="remember">Nhớ mật khẩu</label>
            </div>

            <button type="submit" class="btn-full">Đăng nhập</button>
        </form>

        <p class="link-text">
            Chưa có tài khoản? <a href="register.php">Đăng ký</a>
        </p>
    </div>

    <script src="js/validate.js"></script>
</body>
</html>
```

---

### 5.4. File `app/public/css/style.css`
```css
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: Arial, Helvetica, sans-serif;
}

body {
    min-height: 100vh;
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    display: flex;
    align-items: center;
    justify-content: center;
}

.container,
.form-container,
.dashboard-container {
    width: 420px;
    background: #ffffff;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 10px 35px rgba(0, 0, 0, 0.12);
}

h1,
h2 {
    text-align: center;
    margin-bottom: 16px;
    color: #0d47a1;
}

p {
    text-align: center;
    margin-bottom: 16px;
    color: #333333;
}

.menu {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.btn,
.btn-full {
    display: inline-block;
    text-decoration: none;
    border: none;
    cursor: pointer;
    text-align: center;
    background: #1976d2;
    color: white;
    padding: 12px 18px;
    border-radius: 8px;
    font-size: 15px;
    transition: 0.2s;
}

.btn:hover,
.btn-full:hover {
    background: #0d47a1;
}

.btn-secondary {
    background: #455a64;
}

.btn-secondary:hover {
    background: #263238;
}

.btn-danger {
    background: #d32f2f;
}

.btn-danger:hover {
    background: #b71c1c;
}

.btn-full {
    width: 100%;
    margin-top: 12px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    color: #333333;
    font-weight: 600;
}

.form-group input {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #b0bec5;
    border-radius: 8px;
    outline: none;
    font-size: 15px;
}

.form-group input:focus {
    border-color: #1976d2;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}

.error {
    display: block;
    margin-top: 5px;
    color: #d32f2f;
    font-size: 13px;
}

.message {
    background: #e3f2fd;
    border-left: 4px solid #1976d2;
    padding: 10px;
    margin-bottom: 16px;
    color: #0d47a1;
    border-radius: 6px;
}

.error-box {
    background: #ffebee;
    border-left: 4px solid #d32f2f;
    color: #b71c1c;
}

.link-text {
    margin-top: 16px;
    font-size: 14px;
}

.link-text a {
    color: #1976d2;
    text-decoration: none;
    font-weight: bold;
}
```

---

### 5.5. File `app/public/js/validate.js`
```javascript
function setError(id, message) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = message;
    }
}

function clearErrors() {
    const errors = document.querySelectorAll(".error");
    errors.forEach(function (error) {
        error.textContent = "";
    });
}

function isValidEmail(email) {
    const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return pattern.test(email);
}

function validatePassword(password) {
    if (password.length < 6) {
        return "Mật khẩu phải có ít nhất 6 ký tự.";
    }
    if (!/[A-Z]/.test(password)) {
        return "Mật khẩu nên có ít nhất 1 chữ hoa.";
    }
    if (!/[a-z]/.test(password)) {
        return "Mật khẩu nên có ít nhất 1 chữ thường.";
    }
    if (!/[0-9]/.test(password)) {
        return "Mật khẩu nên có ít nhất 1 chữ số.";
    }
    return "";
}

// Form đăng ký
const registerForm = document.getElementById("registerForm");
if (registerForm) {
    registerForm.addEventListener("submit", function (event) {
        clearErrors();
        let isValid = true;

        const username        = document.getElementById("username").value.trim();
        const email           = document.getElementById("email").value.trim();
        const password        = document.getElementById("password").value;
        const confirmPassword = document.getElementById("confirm_password").value;

        if (username.length < 3) {
            setError("usernameError", "User name phải có ít nhất 3 ký tự.");
            isValid = false;
        }

        if (!isValidEmail(email)) {
            setError("emailError", "Email không hợp lệ.");
            isValid = false;
        }

        const passwordError = validatePassword(password);
        if (passwordError !== "") {
            setError("passwordError", passwordError);
            isValid = false;
        }

        if (confirmPassword !== password) {
            setError("confirmPasswordError", "Mật khẩu xác nhận không khớp.");
            isValid = false;
        }

        if (!isValid) {
            event.preventDefault();
        }
    });
}

// Form đăng nhập
const loginForm = document.getElementById("loginForm");
if (loginForm) {
    loginForm.addEventListener("submit", function (event) {
        clearErrors();
        let isValid = true;

        const usernameOrEmail = document.getElementById("username_or_email").value.trim();
        const password        = document.getElementById("login_password").value;

        if (usernameOrEmail.length === 0) {
            setError("loginUsernameError", "Vui lòng nhập user name hoặc email.");
            isValid = false;
        }

        if (password.length === 0) {
            setError("loginPasswordError", "Vui lòng nhập mật khẩu.");
            isValid = false;
        }

        if (!isValid) {
            event.preventDefault();
        }
    });
}
```

---

## 6. THIẾT KẾ CƠ SỞ DỮ LIỆU MYSQL

### 6.1. Cấu trúc bảng `users`
| Tên cột | Kiểu dữ liệu | Mô tả chi tiết |
| :--- | :--- | :--- |
| `id` | `INT AUTO_INCREMENT` | Khóa chính tự tăng |
| `username` | `VARCHAR(100) UNIQUE NOT NULL` | Tên đăng nhập duy nhất |
| `email` | `VARCHAR(150) UNIQUE NOT NULL` | Địa chỉ email duy nhất |
| `password_hash` | `VARCHAR(64) NOT NULL` | Chuỗi mã hóa SHA256 (64 hex characters) |
| `email_verified` | `TINYINT(1) DEFAULT 0` | Trạng thái xác minh: 0 = Chưa, 1 = Đã xác minh |
| `verification_token` | `VARCHAR(255) DEFAULT NULL` | Token ngẫu nhiên để kích hoạt qua link email |
| `remember_token` | `VARCHAR(255) DEFAULT NULL` | Token ghi nhớ đăng nhập lâu dài |
| `created_at` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | Thời điểm tạo tài khoản |
| `updated_at` | `TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Thời điểm cập nhật cuối cùng |

### 6.2. File `database/init.sql`
```sql
CREATE DATABASE IF NOT EXISTS login_system
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE login_system;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(64) NOT NULL,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    verification_token VARCHAR(255) DEFAULT NULL,
    remember_token VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_verification_token (verification_token)
);
```

---

## 7. MÃ NGUỒN XỬ LÝ BACKEND PHP

### 7.1. Cài đặt thư viện gửi mail (`composer.json`)
```json
{
    "require": {
        "phpmailer/phpmailer": "^6.9"
    }
}
```

---

### 7.2. Cấu hình biến môi trường (`.env`)
```env
APP_NAME="PHP Login System"
APP_ENV=local
APP_URL=http://localhost:8080
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=login_system
DB_USERNAME=login_user
DB_PASSWORD=login_password
DB_ROOT_PASSWORD=root_password

SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_app_password
SMTP_FROM_EMAIL=your_email@gmail.com
SMTP_FROM_NAME="PHP Login System"

SESSION_NAME=LOGIN_SYSTEM_SESSION
```
*Lưu ý với Gmail: Phải bật xác thực 2 bước (2-Step Verification) và tạo Mật khẩu ứng dụng (App Password) để điền vào `SMTP_PASSWORD`.*

---

### 7.3. File `app/src/config.php`
```php
<?php

function envValue(string $key, string $default = ""): string
{
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

return [
    "app" => [
        "name"         => envValue("APP_NAME", "PHP Login System"),
        "url"          => envValue("APP_URL", "http://localhost:8080"),
        "session_name" => envValue("SESSION_NAME", "LOGIN_SYSTEM_SESSION"),
    ],
    "db" => [
        "host"     => envValue("DB_HOST", "mysql"),
        "port"     => envValue("DB_PORT", "3306"),
        "database" => envValue("DB_DATABASE", "login_system"),
        "username" => envValue("DB_USERNAME", "login_user"),
        "password" => envValue("DB_PASSWORD", "login_password"),
    ],
    "smtp" => [
        "host"       => envValue("SMTP_HOST", "smtp.gmail.com"),
        "port"       => (int) envValue("SMTP_PORT", "587"),
        "username"   => envValue("SMTP_USERNAME", ""),
        "password"   => envValue("SMTP_PASSWORD", ""),
        "from_email" => envValue("SMTP_FROM_EMAIL", ""),
        "from_name"  => envValue("SMTP_FROM_NAME", "PHP Login System"),
    ],
];
```

---

### 7.4. File `app/src/db.php`
```php
<?php

function getDatabaseConnection(): PDO
{
    $config = require __DIR__ . "/config.php";
    $db = $config["db"];

    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
        $db["host"],
        $db["port"],
        $db["database"]
    );

    try {
        $pdo = new PDO($dsn, $db["username"], $db["password"], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
```

---

### 7.5. File `app/src/mailer.php`
```php
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/../vendor/autoload.php";

function sendVerificationEmail(string $toEmail, string $username, string $token): bool
{
    $config = require __DIR__ . "/config.php";

    $appUrl    = $config["app"]["url"];
    $verifyUrl = $appUrl . "/verify_email.php?token=" . urlencode($token);
    $smtp      = $config["smtp"];

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $smtp["host"];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp["username"];
        $mail->Password   = $smtp["password"];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtp["port"];
        $mail->CharSet    = "UTF-8";

        $mail->setFrom($smtp["from_email"], $smtp["from_name"]);
        $mail->addAddress($toEmail, $username);

        $mail->isHTML(true);
        $mail->Subject = "Xác nhận tài khoản của bạn";
        $mail->Body    = "
            <h2>Xin chào {$username}</h2>
            <p>Bạn vừa đăng ký tài khoản tại hệ thống.</p>
            <p>Vui lòng click vào liên kết dưới đây để xác nhận email:</p>
            <p><a href='{$verifyUrl}'>Xác nhận email</a></p>
            <p>Nếu bạn không đăng ký tài khoản này, vui lòng bỏ qua email.</p>
        ";
        $mail->AltBody = "Vui lòng mở link sau để xác nhận email: " . $verifyUrl;

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
```

---

### 7.6. File `app/src/auth.php`
```php
<?php

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/mailer.php";

$config = require __DIR__ . "/config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_name($config["app"]["session_name"]);
    session_start();
}

function hashPasswordSha256(string $password): string
{
    return hash("sha256", $password);
}

function registerUser(
    string $username,
    string $email,
    string $password,
    string $confirmPassword
): array {
    if ($username === "" || $email === "" || $password === "" || $confirmPassword === "") {
        return [
            "success" => false,
            "message" => "Vui lòng nhập đầy đủ thông tin."
        ];
    }

    if (strlen($username) < 3) {
        return [
            "success" => false,
            "message" => "User name phải có ít nhất 3 ký tự."
        ];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            "success" => false,
            "message" => "Email không hợp lệ."
        ];
    }

    if (strlen($password) < 6) {
        return [
            "success" => false,
            "message" => "Mật khẩu phải có ít nhất 6 ký tự."
        ];
    }

    if ($password !== $confirmPassword) {
        return [
            "success" => false,
            "message" => "Mật khẩu xác nhận không khớp."
        ];
    }

    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE username = :username OR email = :email
        LIMIT 1
    ");
    $stmt->execute([
        "username" => $username,
        "email"    => $email
    ]);

    if ($stmt->fetch()) {
        return [
            "success" => false,
            "message" => "User name hoặc email đã tồn tại."
        ];
    }

    $passwordHash      = hashPasswordSha256($password);
    $verificationToken = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("
        INSERT INTO users (
            username,
            email,
            password_hash,
            email_verified,
            verification_token
        ) VALUES (
            :username,
            :email,
            :password_hash,
            0,
            :verification_token
        )
    ");

    $stmt->execute([
        "username"           => $username,
        "email"              => $email,
        "password_hash"      => $passwordHash,
        "verification_token" => $verificationToken
    ]);

    $mailSent = sendVerificationEmail($email, $username, $verificationToken);

    if (!$mailSent) {
        return [
            "success" => true,
            "message" => "Đăng ký thành công nhưng gửi email xác nhận thất bại. Vui lòng kiểm tra cấu hình SMTP."
        ];
    }

    return [
        "success" => true,
        "message" => "Đăng ký thành công. Vui lòng kiểm tra email để xác nhận tài khoản."
    ];
}

function loginUser(string $usernameOrEmail, string $password, bool $remember): array
{
    if ($usernameOrEmail === "" || $password === "") {
        return [
            "success" => false,
            "message" => "Vui lòng nhập user name/email và mật khẩu."
        ];
    }

    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE username = :input OR email = :input
        LIMIT 1
    ");
    $stmt->execute([
        "input" => $usernameOrEmail
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        return [
            "success" => false,
            "message" => "Tài khoản không tồn tại."
        ];
    }

    $passwordHash = hashPasswordSha256($password);

    if (!hash_equals($user["password_hash"], $passwordHash)) {
        return [
            "success" => false,
            "message" => "Mật khẩu không chính xác."
        ];
    }

    if ((int) $user["email_verified"] !== 1) {
        return [
            "success" => false,
            "message" => "Tài khoản chưa xác nhận email. Vui lòng kiểm tra email."
        ];
    }

    $_SESSION["user_id"]  = $user["id"];
    $_SESSION["username"] = $user["username"];
    $_SESSION["email"]    = $user["email"];

    if ($remember) {
        $rememberToken = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("
            UPDATE users
            SET remember_token = :remember_token
            WHERE id = :id
        ");
        $stmt->execute([
            "remember_token" => $rememberToken,
            "id"             => $user["id"]
        ]);

        setcookie("remember_user", $usernameOrEmail, time() + 86400 * 30, "/");
        setcookie("remember_token", $rememberToken, time() + 86400 * 30, "/", "", false, true);
    }

    return [
        "success" => true,
        "message" => "Đăng nhập thành công."
    ];
}

function verifyEmailToken(string $token): array
{
    if ($token === "") {
        return [
            "success" => false,
            "message" => "Token xác nhận không hợp lệ."
        ];
    }

    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE verification_token = :token
        LIMIT 1
    ");
    $stmt->execute([
        "token" => $token
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        return [
            "success" => false,
            "message" => "Token xác nhận không tồn tại hoặc đã được sử dụng."
        ];
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET email_verified = 1,
            verification_token = NULL
        WHERE id = :id
    ");
    $stmt->execute([
        "id" => $user["id"]
    ]);

    return [
        "success" => true,
        "message" => "Xác nhận email thành công. Bạn có thể đăng nhập."
    ];
}

function requireLogin(): void
{
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }
}
```

---

### 7.7. File `app/public/verify_email.php`
```php
<?php
require_once __DIR__ . '/../src/auth.php';

$token  = $_GET["token"] ?? "";
$result = verifyEmailToken($token);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác nhận email</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="form-container">
        <h2>Xác nhận email</h2>

        <div class="message <?php echo $result["success"] ? "" : "error-box"; ?>">
            <?php echo htmlspecialchars($result["message"]); ?>
        </div>

        <p class="link-text">
            <a href="login.php">Quay lại trang đăng nhập</a>
        </p>
    </div>
</body>
</html>
```

---

### 7.8. File `app/public/dashboard.php`
```php
<?php
require_once __DIR__ . '/../src/auth.php';

requireLogin();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <h2>Dashboard</h2>
        <p>Xin chào, <strong><?php echo htmlspecialchars($_SESSION["username"]); ?></strong></p>
        <p>Email: <?php echo htmlspecialchars($_SESSION["email"]); ?></p>
        <a href="logout.php" class="btn btn-danger">Đăng xuất</a>
    </div>
</body>
</html>
```

---

### 7.9. File `app/public/logout.php`
```php
<?php
require_once __DIR__ . '/../src/config.php';

session_start();
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

setcookie("remember_user", "", time() - 3600, "/");
setcookie("remember_token", "", time() - 3600, "/");

header("Location: login.php");
exit;
```

---

## 8. CẤU HÌNH DOCKER & DOCKER COMPOSE

### 8.1. File `docker/php/Dockerfile`
```dockerfile
FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y \
    unzip \
    git \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY app/ /var/www/html/
COPY composer.json /var/www/html/composer.json

RUN composer install --no-interaction --prefer-dist

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN chown -R www-data:www-data /var/www/html
RUN a2enmod rewrite

EXPOSE 80
```

---

### 8.2. File `docker-compose.yml`
```yaml
services:
  php-app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: php_login_app
    ports:
      - "8080:80"
    volumes:
      - ./app:/var/www/html
    env_file:
      - .env
    depends_on:
      - mysql
    networks:
      - login_network

  mysql:
    image: mysql:8.0
    container_name: php_login_mysql
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    ports:
      - "3307:3306"
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database/init.sql:/docker-entrypoint-initdb.d/init.sql
    networks:
      - login_network

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    container_name: php_login_phpmyadmin
    restart: always
    environment:
      PMA_HOST: mysql
      PMA_PORT: 3306
    ports:
      - "8081:80"
    depends_on:
      - mysql
    networks:
      - login_network

volumes:
  mysql_data:

networks:
  login_network:
    driver: bridge
```

---

## 9. HƯỚNG DẪN TRIỂN KHAI VÀ VẬN HÀNH DOCKER

### 9.1. Khởi chạy ứng dụng
1. Build images:
   ```bash
   docker compose build
   ```
2. Khởi chạy dịch vụ ở chế độ background:
   ```bash
   docker compose up -d
   ```
3. Kiểm tra trạng thái hoạt động của 3 container (`php_login_app`, `php_login_mysql`, `php_login_phpmyadmin`):
   ```bash
   docker ps
   ```

### 9.2. Địa chỉ truy cập
* **Website:** [http://localhost:8080](http://localhost:8080)
* **phpMyAdmin:** [http://localhost:8081](http://localhost:8081)
  * Server: `mysql`
  * Username: `login_user`
  * Password: `login_password`
  * Database: `login_system`

### 9.3. Quản lý container & xem logs
* Xem toàn bộ log: `docker compose logs`
* Xem log PHP: `docker compose logs php-app`
* Dừng hệ thống: `docker compose down`
* Dừng và reset database: `docker compose down -v`

### 9.4. Đóng gói & Push Docker Hub
```bash
# 1. Đăng nhập Docker Hub
docker login

# 2. Build và đặt tag
docker build -t yourdockerhub/php-login-system:1.0 -f docker/php/Dockerfile .
docker tag yourdockerhub/php-login-system:1.0 yourdockerhub/php-login-system:latest

# 3. Push image lên repository
docker push yourdockerhub/php-login-system:1.0
docker push yourdockerhub/php-login-system:latest
```

---

## 10. KỊCH BẢN KIỂM THỬ HỆ THỐNG (TEST CASES)

### 10.1. Kiểm thử Đăng ký (Register)
| STT | Kịch bản kiểm thử | Dữ liệu đầu vào | Kết quả mong đợi |
| :---: | :--- | :--- | :--- |
| 1 | Bỏ trống Username | `username = ""` | Báo lỗi: "User name phải có ít nhất 3 ký tự." |
| 2 | Username ngắn | `username = "ab"` | Báo lỗi client-side |
| 3 | Email sai định dạng | `email = "abc.com"` | Báo lỗi: "Email không hợp lệ." |
| 4 | Password ngắn | `password = "123"` | Báo lỗi: "Mật khẩu phải có ít nhất 6 ký tự." |
| 5 | Xác nhận mật khẩu không khớp | Pass: `abc123`, Confirm: `abc456` | Báo lỗi: "Mật khẩu xác nhận không khớp." |
| 6 | Trùng Email/Username | Nhập email đã có trong database | Báo lỗi: "User name hoặc email đã tồn tại." |
| 7 | Đăng ký thành công | Dữ liệu hợp lệ | Lưu DB (`email_verified = 0`), gửi mail xác nhận |

### 10.2. Kiểm thử Xác nhận Email (Verify Email)
| STT | Kịch bản kiểm thử | Dữ liệu đầu vào | Kết quả mong đợi |
| :---: | :--- | :--- | :--- |
| 1 | Token rỗng | `verify_email.php?token=` | Báo lỗi: "Token xác nhận không hợp lệ." |
| 2 | Token sai | `token = "invalid_token_123"` | Báo lỗi: "Token không tồn tại hoặc đã sử dụng." |
| 3 | Token chính xác | Token lấy từ email | Cập nhật `email_verified = 1`, xóa token, thông báo thành công |
| 4 | Click lại link đã kích hoạt | Token vừa dùng | Báo lỗi token không tồn tại/đã sử dụng |

### 10.3. Kiểm thử Đăng nhập (Login)
| STT | Kịch bản kiểm thử | Dữ liệu đầu vào | Kết quả mong đợi |
| :---: | :--- | :--- | :--- |
| 1 | Bỏ trống username/email | Để trống | Báo lỗi client/server |
| 2 | Bỏ trống password | Để trống | Báo lỗi client/server |
| 3 | Tài khoản không tồn tại | User không có trong hệ thống | Báo lỗi: "Tài khoản không tồn tại." |
| 4 | Sai mật khẩu | Nhập sai pass | Báo lỗi: "Mật khẩu không chính xác." |
| 5 | Chưa xác nhận email | User có `email_verified = 0` | Báo lỗi: "Tài khoản chưa xác nhận email." |
| 6 | Đăng nhập hợp lệ | User và pass đúng, đã verify | Tạo Session, chuyển vào trang `dashboard.php` |
| 7 | Ghi nhớ đăng nhập | Tích chọn "Nhớ mật khẩu" | Tạo Cookie `remember_user` và `remember_token` |

---

## 11. KHẮC PHỤC CÁC SỰ CỐ THƯỜNG GẶP (TROUBLESHOOTING)

1. **Lỗi `Database connection failed`:**
   * Trong file `.env`, giá trị `DB_HOST` phải là tên service trong Docker Compose: `DB_HOST=mysql` (không dùng `localhost`).
2. **Lỗi gửi email thất bại:**
   * Kiểm tra port và SSL/TLS: Cổng `587` đi với giao thức `PHPMailer::ENCRYPTION_STARTTLS`.
   * Đối với Gmail: Tài khoản bắt buộc bật 2-Step Verification và tạo App Password.
3. **Lỗi 404 Not Found hoặc không vào được `public/index.php`:**
   * Cấu hình DocumentRoot của Apache phải trỏ đúng vào thư mục con: `ENV APACHE_DOCUMENT_ROOT=/var/www/html/public`.
4. **Lỗi Database không chạy file `init.sql`:**
   * File script trong `/docker-entrypoint-initdb.d/` chỉ chạy ở lần tạo volume đầu tiên. Nếu thay đổi cấu trúc, cần chạy lệnh:
     ```bash
     docker compose down -v
     docker compose up -d
     ```

---

## 12. KẾT LUẬN & ĐỀ XUẤT CẢI TIẾN
Hệ thống đã hoàn thiện toàn bộ yêu cầu của bài thực hành: Xây dựng form chuẩn, validate client/server, lưu trữ mật khẩu, xác thực qua email SMTP, quản lý phiên và đóng gói bằng Docker Container.

**Hướng phát triển nâng cao trong tương lai:**
* Chuyển đổi mã hóa sang `password_hash()` với thuật toán `Argon2id` hoặc `Bcrypt`.
* Tích hợp CSRF Token trong toàn bộ form POST để phòng tránh tấn công Cross-Site Request Forgery.
* Bổ sung tính năng Rate Limiting (giới hạn số lần thử login) chống brute-force.
* Tích hợp tính năng Reset Password qua token có thời hạn (15-30 phút).
* Triển khai Nginx Reverse Proxy và cấu hình SSL/TLS (HTTPS).