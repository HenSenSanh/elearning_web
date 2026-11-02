<?php
require_once __DIR__ . '/../src/auth.php';

if (empty($_SESSION["pending_otp_user_id"])) {
    header("Location: login.php");
    exit;
}

$pdo  = getDatabaseConnection();
$stmt = $pdo->prepare("SELECT username, email, otp_code FROM users WHERE id = :id LIMIT 1");
$stmt->execute(["id" => $_SESSION["pending_otp_user_id"]]);
$pendingUser = $stmt->fetch();

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? null;
    $otpInput  = $_POST["otp_code"] ?? "";

    if (!verifyCsrfToken($csrfToken)) {
        $message = "Phiên làm việc không hợp lệ (CSRF Token sai).";
    } else {
        $result = verifyOtpLogin($otpInput);
        if ($result["success"]) {
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
    <title>Xác thực 2 lớp (2FA OTP)</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="form-container">
        <h2>Xác thực 2 lớp (2FA OTP)</h2>
        <p style="font-size: 14px; color: #546e7a;">
            Tài khoản <strong><?php echo htmlspecialchars($pendingUser["username"] ?? ""); ?></strong> đang bật bảo mật 2 lớp.
            Vui lòng nhập mã OTP 6 chữ số (hiệu lực 5 phút).
        </p>

        <?php if (!empty($pendingUser["otp_code"])): ?>
            <div class="message" style="text-align: center;">
                Mã OTP đã gửi qua SMTP.<br>
                <small>(Local Demo OTP: <strong style="font-size: 16px; letter-spacing: 2px;"><?php echo htmlspecialchars($pendingUser["otp_code"]); ?></strong>)</small>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="message error-box">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="verify_otp.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="form-group">
                <label for="otp_code">Mã OTP (6 chữ số)</label>
                <input type="text" id="otp_code" name="otp_code" maxlength="6" placeholder="Nhập 6 chữ số OTP" style="text-align: center; font-size: 18px; letter-spacing: 4px;" required>
            </div>

            <button type="submit" class="btn-full">Xác nhận OTP</button>
        </form>

        <p class="link-text">
            <a href="login.php">Quay lại trang đăng nhập</a>
        </p>
    </div>
</body>
</html>
