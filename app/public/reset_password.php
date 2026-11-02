<?php
require_once __DIR__ . '/../src/auth.php';

$token     = $_GET["token"] ?? ($_POST["token"] ?? "");
$message   = "";
$isSuccess = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken       = $_POST["csrf_token"] ?? null;
    $newPassword     = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (!verifyCsrfToken($csrfToken)) {
        $message = "Phiên làm việc không hợp lệ (CSRF Token sai).";
    } else {
        $result    = resetPasswordWithToken($token, $newPassword, $confirmPassword);
        $isSuccess = $result["success"];
        $message   = $result["message"];
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu mới</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="form-container">
        <h2>Đặt lại mật khẩu mới</h2>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $isSuccess ? '' : 'error-box'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$isSuccess): ?>
            <form method="POST" action="reset_password.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="form-group">
                    <label for="password">Mật khẩu mới</label>
                    <input type="password" id="password" name="password" placeholder="Nhập mật khẩu mới (>= 6 ký tự)" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Xác nhận mật khẩu mới</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Nhập lại mật khẩu mới" required>
                </div>

                <button type="submit" class="btn-full">Cập nhật mật khẩu</button>
            </form>
        <?php endif; ?>

        <p class="link-text">
            <a href="login.php">Quay lại trang đăng nhập</a>
        </p>
    </div>
</body>
</html>
