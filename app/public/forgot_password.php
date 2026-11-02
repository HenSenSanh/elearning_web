<?php
require_once __DIR__ . '/../src/auth.php';

$message    = "";
$isSuccess  = false;
$resetLink  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? null;
    $email     = trim($_POST["email"] ?? "");

    if (!verifyCsrfToken($csrfToken)) {
        $message = "Phiên làm việc không hợp lệ (CSRF Token sai).";
    } else {
        $result    = requestPasswordReset($email);
        $isSuccess = $result["success"];
        $message   = $result["message"];

        if (!empty($result["reset_token"]) && empty($result["mail_sent"])) {
            $resetLink = "reset_password.php?token=" . urlencode($result["reset_token"]);
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="form-container">
        <h2>Quên mật khẩu</h2>
        <p style="font-size: 14px; color: #546e7a;">Nhập email đã đăng ký để nhận liên kết đặt lại mật khẩu (hiệu lực 15 phút).</p>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $isSuccess ? '' : 'error-box'; ?>">
                <?php echo htmlspecialchars($message); ?>
                <?php if (!empty($resetLink)): ?>
                    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed #90caf9;">
                        <strong>Link đặt lại mật khẩu (Local Demo):</strong><br>
                        <a href="<?php echo htmlspecialchars($resetLink); ?>" style="color: #0d47a1; font-weight: bold; word-break: break-all;">
                            Bấm vào đây để đặt lại mật khẩu mới
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="form-group">
                <label for="email">Địa chỉ Email</label>
                <input type="email" id="email" name="email" placeholder="Nhập email của bạn" required>
            </div>

            <button type="submit" class="btn-full">Gửi yêu cầu đặt lại mật khẩu</button>
        </form>

        <p class="link-text">
            <a href="login.php">Quay lại trang đăng nhập</a>
        </p>
    </div>
</body>
</html>
