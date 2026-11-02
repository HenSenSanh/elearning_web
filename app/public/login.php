<?php
require_once __DIR__ . '/../src/auth.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken       = $_POST["csrf_token"] ?? null;
    $usernameOrEmail = trim($_POST["username_or_email"] ?? "");
    $password        = $_POST["password"] ?? "";
    $remember        = isset($_POST["remember"]);

    if (!verifyCsrfToken($csrfToken)) {
        $message = "Phiên làm việc không hợp lệ (CSRF Token sai). Vui lòng tải lại trang.";
    } else {
        $result = loginUser($usernameOrEmail, $password, $remember);

        if ($result["success"]) {
            if (!empty($result["requires_otp"])) {
                header("Location: verify_otp.php");
                exit;
            }
            if (($result["role"] ?? "user") === "admin") {
                header("Location: admin.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        }

        $message = $result["message"];
    }
}

$csrfToken = generateCsrfToken();
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
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

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

            <div class="checkbox-group" style="justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="remember" name="remember" <?php echo isset($_COOKIE['remember_user']) ? 'checked' : ''; ?>>
                    <label for="remember">Nhớ mật khẩu</label>
                </div>
                <a href="forgot_password.php" style="font-size: 13px; color: #1976d2; text-decoration: none; font-weight: 600;">Quên mật khẩu?</a>
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
