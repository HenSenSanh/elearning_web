<?php
require_once __DIR__ . '/../src/auth.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken       = $_POST["csrf_token"] ?? null;
    $username        = trim($_POST["username"] ?? "");
    $email           = trim($_POST["email"] ?? "");
    $password        = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (!verifyCsrfToken($csrfToken)) {
        $message = "Phiên làm việc không hợp lệ (CSRF Token sai). Vui lòng tải lại trang.";
    } else {
        $result  = registerUser($username, $email, $password, $confirmPassword);
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
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

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
