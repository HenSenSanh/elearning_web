<?php
require_once __DIR__ . '/../src/auth.php';

if (isset($_SESSION['user_id'])) {
    if (isAdmin()) {
        header("Location: admin.php");
    } else {
        header("Location: dashboard.php");
    }
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
