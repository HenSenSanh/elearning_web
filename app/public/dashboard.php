<?php
require_once __DIR__ . '/../src/auth.php';

requireLogin();

$pdo        = getDatabaseConnection();
$userId     = (int) $_SESSION["user_id"];
$role       = $_SESSION["role"] ?? "user";
$statusMsg  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? null;
    $action    = $_POST["action"] ?? "";

    if (!verifyCsrfToken($csrfToken)) {
        $statusMsg = "CSRF Token không hợp lệ.";
    } elseif ($action === "toggle_otp") {
        $stmt = $pdo->prepare("UPDATE users SET otp_enabled = IF(otp_enabled = 1, 0, 1) WHERE id = :id");
        $stmt->execute(["id" => $userId]);
        $statusMsg = "Đã cập nhật trạng thái Xác thực 2 lớp (2FA OTP).";
    }
}

$stmt = $pdo->prepare("SELECT otp_enabled, password_hash FROM users WHERE id = :id LIMIT 1");
$stmt->execute(["id" => $userId]);
$currentUser = $stmt->fetch();
$otpEnabled  = (int) ($currentUser["otp_enabled"] ?? 0) === 1;
$isModernHash = str_starts_with($currentUser["password_hash"] ?? "", '$2y$') || str_starts_with($currentUser["password_hash"] ?? "", '$argon2');
$csrfToken   = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cổng Học Viên - E-Learning Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="dashboard-container elearning-card">
        <div class="role-header">
            <span class="badge <?php echo $role === 'admin' ? 'badge-admin' : 'badge-student'; ?>">
                <?php echo $role === 'admin' ? 'Quản trị viên (Admin)' : 'Tài khoản Học viên / Khách'; ?>
            </span>
        </div>

        <h2>Cổng Học Tập E-Learning</h2>
        <p>Xin chào, <strong><?php echo htmlspecialchars($_SESSION["username"]); ?></strong></p>
        <p class="sub-info">Email: <?php echo htmlspecialchars($_SESSION["email"]); ?></p>

        <?php if (!empty($statusMsg)): ?>
            <div class="message">
                <?php echo htmlspecialchars($statusMsg); ?>
            </div>
        <?php endif; ?>

        <div class="course-section">
            <h3>Khóa học của bạn</h3>
            <div class="course-list">
                <div class="course-item">
                    <div>
                        <strong>Lập trình Backend PHP & MySQL</strong>
                        <small>Tiến độ: 100% • Hoàn thành</small>
                    </div>
                    <span class="course-tag">Hoàn thành</span>
                </div>
                <div class="course-item">
                    <div>
                        <strong>Triển khai ứng dụng với Docker Compose</strong>
                        <small>Tiến độ: 100% • Hoàn thành</small>
                    </div>
                    <span class="course-tag done">Hoàn thành</span>
                </div>
                <div class="course-item">
                    <div>
                        <strong>Bảo mật ứng dụng Web & Xác thực SMTP</strong>
                        <small>Tiến độ: 100% • Hoàn thành</small>
                    </div>
                    <span class="course-tag done">Hoàn thành</span>
                </div>
            </div>
        </div>

        <div class="course-section">
            <h3>Cài đặt Bảo mật Nâng cao (Mục 46)</h3>
            <div class="course-list">
                <div class="course-item">
                    <div>
                        <strong>Xác thực 2 lớp (2FA OTP qua Email)</strong>
                        <small>Trạng thái: <?php echo $otpEnabled ? 'Đang BẬT (Yêu cầu mã 6 số khi login)' : 'Đang TẮT'; ?></small>
                    </div>
                    <form method="POST" action="dashboard.php" style="margin: 0;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="toggle_otp">
                        <button type="submit" class="btn-sm <?php echo $otpEnabled ? 'btn-delete' : 'btn-verify'; ?>">
                            <?php echo $otpEnabled ? 'Tắt 2FA' : 'Bật 2FA'; ?>
                        </button>
                    </form>
                </div>
                <div class="course-item">
                    <div>
                        <strong>Chuẩn mã hóa mật khẩu hiện tại</strong>
                        <small><?php echo $isModernHash ? 'password_hash (Argon2id/Bcrypt)' : 'SHA256 (Chuẩn bài thực hành)'; ?></small>
                    </div>
                    <a href="forgot_password.php" class="course-tag" style="text-decoration: none;">Đổi MK</a>
                </div>
            </div>
        </div>

        <div class="menu" style="margin-top: 20px;">
            <?php if (isAdmin()): ?>
                <a href="admin.php" class="btn">Vào Trang Quản Trị (Admin)</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-danger">Đăng xuất</a>
        </div>
    </div>
</body>
</html>
