<?php
require_once __DIR__ . '/../src/auth.php';

// Middleware kiểm tra quyền truy cập: Chặn toàn bộ tài khoản khách (role = 'user')
requireAdmin();

$pdo = getDatabaseConnection();
$actionMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? null;
    $action    = $_POST["action"] ?? "";
    $targetId  = (int) ($_POST["user_id"] ?? 0);

    if (!verifyCsrfToken($csrfToken)) {
        $actionMessage = "CSRF Token không hợp lệ. Vui lòng thử lại.";
    } elseif ($targetId > 0 && $targetId !== (int) $_SESSION["user_id"]) {
        if ($action === "verify") {
            $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE id = :id");
            $stmt->execute(["id" => $targetId]);
            $actionMessage = "Đã kích hoạt xác nhận email cho học viên #$targetId.";
        } elseif ($action === "unlock") {
            $stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = :id");
            $stmt->execute(["id" => $targetId]);
            $actionMessage = "Đã mở khóa đăng nhập cho tài khoản #$targetId.";
        } elseif ($action === "delete") {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role != 'admin'");
            $stmt->execute(["id" => $targetId]);
            $actionMessage = "Đã xóa tài khoản khách #$targetId.";
        }
    }
}

$csrfToken = generateCsrfToken();

$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS total_admins,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) AS total_students,
        SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) AS verified_users
    FROM users
");
$stats = $statsStmt->fetch();

$usersStmt = $pdo->query("SELECT id, username, email, role, email_verified, failed_attempts, locked_until, last_login_at, created_at FROM users ORDER BY id ASC");
$users = $usersStmt->fetchAll();

$logsStmt = $pdo->query("SELECT id, username_input, ip_address, status, message, created_at FROM login_logs ORDER BY id DESC LIMIT 10");
$logs = $logsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Trị Hệ Thống E-Learning (Admin)</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-top-bar">
            <div>
                <span class="badge badge-admin">QUẢN TRỊ VIÊN CHÍNH THỨC (ADMIN)</span>
                <h2 style="text-align: left; margin-top: 8px; margin-bottom: 4px;">Bảng Điều Khiển Quản Trị E-Learning</h2>
                <p style="text-align: left; margin-bottom: 0;">Xin chào Admin: <strong><?php echo htmlspecialchars($_SESSION["username"]); ?></strong> (<?php echo htmlspecialchars($_SESSION["email"]); ?>)</p>
            </div>
            <div class="menu">
                <a href="dashboard.php" class="btn btn-secondary">Xem Giao Diện Học Viên</a>
                <a href="logout.php" class="btn btn-danger">Đăng xuất</a>
            </div>
        </div>

        <?php if (!empty($actionMessage)): ?>
            <div class="message">
                <?php echo htmlspecialchars($actionMessage); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo (int) $stats["total_users"]; ?></span>
                <span class="stat-label">Tổng tài khoản</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo (int) $stats["total_students"]; ?></span>
                <span class="stat-label">Học viên / Khách (User)</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo (int) $stats["verified_users"]; ?></span>
                <span class="stat-label">Đã xác thực Email</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo (int) $stats["total_admins"]; ?></span>
                <span class="stat-label">Quản trị viên (Admin)</span>
            </div>
        </div>

        <h3 style="color: #0d47a1; margin-bottom: 12px;">1. Danh sách tài khoản trong hệ thống</h3>
        <div class="table-wrapper" style="margin-bottom: 28px;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phân quyền</th>
                        <th>Trạng thái Email</th>
                        <th>Bảo mật / Đăng nhập</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php $isLocked = !empty($u["locked_until"]) && strtotime($u["locked_until"]) > time(); ?>
                        <tr>
                            <td>#<?php echo (int) $u["id"]; ?></td>
                            <td><strong><?php echo htmlspecialchars($u["username"]); ?></strong></td>
                            <td><?php echo htmlspecialchars($u["email"]); ?></td>
                            <td>
                                <?php if ($u["role"] === "admin"): ?>
                                    <span class="badge badge-admin">Admin</span>
                                <?php else: ?>
                                    <span class="badge badge-student">Khách / Học viên</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $u["email_verified"] === 1): ?>
                                    <span class="status-verified">Đã xác nhận</span>
                                <?php else: ?>
                                    <span class="status-unverified">Chờ xác nhận</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isLocked): ?>
                                    <span class="status-unverified">Đang khóa (Sai <?php echo (int) $u["failed_attempts"]; ?>/5)</span>
                                <?php elseif ((int) $u["failed_attempts"] > 0): ?>
                                    <small style="color: #ef6c00;">Sai MK: <?php echo (int) $u["failed_attempts"]; ?>/5</small>
                                <?php else: ?>
                                    <small style="color: #546e7a;">An toàn</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u["role"] !== "admin"): ?>
                                    <div class="action-buttons">
                                        <?php if ((int) $u["email_verified"] === 0): ?>
                                            <form method="POST" action="admin.php" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $u["id"]; ?>">
                                                <input type="hidden" name="action" value="verify">
                                                <button type="submit" class="btn-sm btn-verify">Duyệt Email</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($isLocked || (int) $u["failed_attempts"] > 0): ?>
                                            <form method="POST" action="admin.php" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $u["id"]; ?>">
                                                <input type="hidden" name="action" value="unlock">
                                                <button type="submit" class="btn-sm btn-verify" style="background:#1976d2;">Mở khóa</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa tài khoản khách này?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $u["id"]; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn-sm btn-delete">Xóa</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <small style="color: #78909c;">Mặc định</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 style="color: #0d47a1; margin-bottom: 12px;">2. Nhật ký đăng nhập gần đây (Login Audit Logs)</h3>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tài khoản nhập</th>
                        <th>Địa chỉ IP</th>
                        <th>Trạng thái</th>
                        <th>Chi tiết</th>
                        <th>Thời gian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #78909c;">Chưa có lịch sử đăng nhập nào được ghi nhận.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>#<?php echo (int) $log["id"]; ?></td>
                                <td><strong><?php echo htmlspecialchars($log["username_input"]); ?></strong></td>
                                <td><?php echo htmlspecialchars($log["ip_address"]); ?></td>
                                <td>
                                    <?php if ($log["status"] === "success"): ?>
                                        <span class="status-verified">Thành công</span>
                                    <?php else: ?>
                                        <span class="status-unverified"><?php echo htmlspecialchars(strtoupper($log["status"])); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($log["message"] ?? ""); ?></td>
                                <td><?php echo htmlspecialchars($log["created_at"]); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
