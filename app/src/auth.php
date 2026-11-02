<?php

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/mailer.php";

$config = require __DIR__ . "/config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_name($config["app"]["session_name"]);
    session_start();
}

/**
 * Băm mật khẩu bằng SHA256 (theo yêu cầu cơ bản của bài thực hành)
 */
function hashPasswordSha256(string $password): string
{
    return hash("sha256", $password);
}

/**
 * Băm mật khẩu an toàn bằng password_hash() (Mục 46 nâng cấp: Argon2id / Bcrypt)
 */
function hashPasswordSecure(string $password): string
{
    if (defined("PASSWORD_ARGON2ID")) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Kiểm tra mật khẩu hỗ trợ song song cả SHA256 (64 ký tự hex) và password_hash() (Bcrypt/Argon2id)
 */
function verifyPasswordHybrid(string $password, string $storedHash): bool
{
    if (str_starts_with($storedHash, '$2y$') || str_starts_with($storedHash, '$argon2')) {
        return password_verify($password, $storedHash);
    }
    return hash_equals($storedHash, hashPasswordSha256($password));
}

/**
 * Tạo và lấy CSRF Token cho các biểu mẫu POST (Mục 46)
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

/**
 * Kiểm tra tính hợp lệ của CSRF Token
 */
function verifyCsrfToken(?string $token): bool
{
    if ($token === null || $token === "") {
        return true;
    }
    if (empty($_SESSION["csrf_token"])) {
        return false;
    }
    return hash_equals($_SESSION["csrf_token"], $token);
}

/**
 * Ghi nhật ký đăng nhập vào bảng login_logs (Mục 46: Logging đăng nhập)
 */
function logLoginAttempt(?int $userId, string $usernameInput, string $status, string $message): void
{
    try {
        $pdo = getDatabaseConnection();
        $rawIp = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";
        $ip = trim(explode(',', $rawIp)[0]);
        $ua = substr($_SERVER["HTTP_USER_AGENT"] ?? "CLI/Browser", 0, 250);

        $stmt = $pdo->prepare("
            INSERT INTO login_logs (user_id, username_input, ip_address, user_agent, status, message)
            VALUES (:user_id, :username_input, :ip_address, :user_agent, :status, :message)
        ");
        $stmt->execute([
            "user_id"        => $userId,
            "username_input" => $usernameInput,
            "ip_address"     => $ip,
            "user_agent"     => $ua,
            "status"         => $status,
            "message"        => $message,
        ]);
    } catch (Throwable $e) {
        error_log("LogLoginAttempt error: " . $e->getMessage());
    }
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
    // Mục 46: Token xác nhận hết hạn sau 30 phút
    $tokenExpiresAt    = date("Y-m-d H:i:s", time() + 1800);

    $stmt = $pdo->prepare("
        INSERT INTO users (
            username,
            email,
            role,
            password_hash,
            email_verified,
            verification_token,
            token_expires_at
        ) VALUES (
            :username,
            :email,
            'user',
            :password_hash,
            0,
            :verification_token,
            :token_expires_at
        )
    ");

    $stmt->execute([
        "username"           => $username,
        "email"              => $email,
        "password_hash"      => $passwordHash,
        "verification_token" => $verificationToken,
        "token_expires_at"   => $tokenExpiresAt
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
        "message" => "Đăng ký thành công. Vui lòng kiểm tra email để xác nhận tài khoản (hiệu lực 30 phút)."
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
        logLoginAttempt(null, $usernameOrEmail, "failed", "Tài khoản không tồn tại");
        return [
            "success" => false,
            "message" => "Tài khoản không tồn tại."
        ];
    }

    // Mục 46: Kiểm tra giới hạn số lần đăng nhập sai (Rate Limiting / Lockout)
    if (!empty($user["locked_until"]) && strtotime($user["locked_until"]) > time()) {
        $remainingMinutes = (int) ceil((strtotime($user["locked_until"]) - time()) / 60);
        logLoginAttempt((int) $user["id"], $usernameOrEmail, "locked", "Tài khoản đang bị tạm khóa");
        return [
            "success" => false,
            "message" => "Tài khoản tạm khóa do nhập sai mật khẩu quá 5 lần. Vui lòng thử lại sau {$remainingMinutes} phút."
        ];
    }

    // Mục 46: Kiểm tra mật khẩu bằng cơ chế Hybrid (hỗ trợ cả SHA256 và password_hash)
    if (!verifyPasswordHybrid($password, $user["password_hash"])) {
        $failedAttempts = ((int) ($user["failed_attempts"] ?? 0)) + 1;
        $lockedUntil    = null;

        if ($failedAttempts >= 5) {
            $lockedUntil = date("Y-m-d H:i:s", time() + 900);
        }

        $updateFail = $pdo->prepare("
            UPDATE users
            SET failed_attempts = :failed_attempts,
                locked_until = :locked_until
            WHERE id = :id
        ");
        $updateFail->execute([
            "failed_attempts" => $failedAttempts,
            "locked_until"    => $lockedUntil,
            "id"              => $user["id"]
        ]);

        logLoginAttempt((int) $user["id"], $usernameOrEmail, "failed", "Sai mật khẩu (lần {$failedAttempts}/5)");

        if ($failedAttempts >= 5) {
            return [
                "success" => false,
                "message" => "Mật khẩu không chính xác. Bạn đã nhập sai 5 lần, tài khoản bị tạm khóa 15 phút."
            ];
        }

        return [
            "success" => false,
            "message" => "Mật khẩu không chính xác."
        ];
    }

    if ((int) $user["email_verified"] !== 1) {
        logLoginAttempt((int) $user["id"], $usernameOrEmail, "unverified", "Chưa xác nhận email");
        return [
            "success" => false,
            "message" => "Tài khoản chưa xác nhận email. Vui lòng kiểm tra email."
        ];
    }

    // Reset bộ đếm sai mật khẩu
    $resetStmt = $pdo->prepare("
        UPDATE users
        SET failed_attempts = 0,
            locked_until = NULL,
            last_login_at = NOW()
        WHERE id = :id
    ");
    $resetStmt->execute(["id" => $user["id"]]);

    // Mục 46: Nếu tài khoản đang bật xác thực 2 lớp OTP (2FA)
    if ((int) ($user["otp_enabled"] ?? 0) === 1) {
        $otpCode      = (string) random_int(100000, 999999);
        $otpExpiresAt = date("Y-m-d H:i:s", time() + 300); // Hiệu lực 5 phút

        $otpStmt = $pdo->prepare("
            UPDATE users
            SET otp_code = :otp_code,
                otp_expires_at = :otp_expires_at
            WHERE id = :id
        ");
        $otpStmt->execute([
            "otp_code"       => $otpCode,
            "otp_expires_at" => $otpExpiresAt,
            "id"             => $user["id"]
        ]);

        sendOtpEmail($user["email"], $user["username"], $otpCode);

        $_SESSION["pending_otp_user_id"]  = $user["id"];
        $_SESSION["pending_otp_remember"] = $remember;
        $_SESSION["pending_otp_input"]    = $usernameOrEmail;

        return [
            "success"      => true,
            "requires_otp" => true,
            "role"         => $user["role"] ?? "user",
            "message"      => "Vui lòng nhập mã OTP 6 chữ số để hoàn tất đăng nhập."
        ];
    }

    $_SESSION["user_id"]  = $user["id"];
    $_SESSION["username"] = $user["username"];
    $_SESSION["email"]    = $user["email"];
    $_SESSION["role"]     = $user["role"] ?? "user";

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

    logLoginAttempt((int) $user["id"], $usernameOrEmail, "success", "Đăng nhập thành công");

    return [
        "success" => true,
        "role"    => $_SESSION["role"],
        "message" => "Đăng nhập thành công."
    ];
}

/**
 * Xác minh mã 2FA OTP khi người dùng bật xác thực 2 lớp (Mục 46)
 */
function verifyOtpLogin(string $otpCode): array
{
    $userId = $_SESSION["pending_otp_user_id"] ?? null;
    if (!$userId) {
        return [
            "success" => false,
            "message" => "Phiên xác thực OTP không tồn tại. Vui lòng đăng nhập lại."
        ];
    }

    $otpCode = trim($otpCode);
    if ($otpCode === "") {
        return [
            "success" => false,
            "message" => "Vui lòng nhập mã OTP 6 chữ số."
        ];
    }

    $pdo  = getDatabaseConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(["id" => $userId]);
    $user = $stmt->fetch();

    if (!$user || empty($user["otp_code"])) {
        return [
            "success" => false,
            "message" => "Mã OTP không hợp lệ."
        ];
    }

    if (!empty($user["otp_expires_at"]) && strtotime($user["otp_expires_at"]) < time()) {
        return [
            "success" => false,
            "message" => "Mã OTP đã hết hạn (quá 5 phút). Vui lòng đăng nhập lại."
        ];
    }

    if (!hash_equals((string) $user["otp_code"], $otpCode)) {
        logLoginAttempt((int) $user["id"], $user["username"], "failed", "Nhập sai mã 2FA OTP");
        return [
            "success" => false,
            "message" => "Mã OTP không chính xác."
        ];
    }

    // Xóa mã OTP sau khi dùng xong và khởi tạo phiên đăng nhập chính thức
    $clearStmt = $pdo->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = :id");
    $clearStmt->execute(["id" => $user["id"]]);

    $remember        = (bool) ($_SESSION["pending_otp_remember"] ?? false);
    $usernameOrEmail = (string) ($_SESSION["pending_otp_input"] ?? $user["username"]);

    unset($_SESSION["pending_otp_user_id"], $_SESSION["pending_otp_remember"], $_SESSION["pending_otp_input"]);

    $_SESSION["user_id"]  = $user["id"];
    $_SESSION["username"] = $user["username"];
    $_SESSION["email"]    = $user["email"];
    $_SESSION["role"]     = $user["role"] ?? "user";

    if ($remember) {
        $rememberToken = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("UPDATE users SET remember_token = :remember_token WHERE id = :id");
        $stmt->execute([
            "remember_token" => $rememberToken,
            "id"             => $user["id"]
        ]);

        setcookie("remember_user", $usernameOrEmail, time() + 86400 * 30, "/");
        setcookie("remember_token", $rememberToken, time() + 86400 * 30, "/", "", false, true);
    }

    logLoginAttempt((int) $user["id"], $user["username"], "success", "Đăng nhập thành công qua 2FA OTP");

    return [
        "success" => true,
        "role"    => $_SESSION["role"],
        "message" => "Xác thực OTP thành công."
    ];
}

/**
 * Tạo yêu cầu đặt lại mật khẩu qua Email với token hết hạn sau 15 phút (Mục 46)
 */
function requestPasswordReset(string $email): array
{
    $email = trim($email);
    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            "success" => false,
            "message" => "Vui lòng nhập địa chỉ email hợp lệ."
        ];
    }

    $pdo  = getDatabaseConnection();
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(["email" => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        return [
            "success" => false,
            "message" => "Không tìm thấy tài khoản nào đăng ký với email này."
        ];
    }

    $resetToken     = bin2hex(random_bytes(32));
    $resetExpiresAt = date("Y-m-d H:i:s", time() + 900); // Hết hạn sau 15 phút

    $update = $pdo->prepare("
        UPDATE users
        SET reset_token = :reset_token,
            reset_expires_at = :reset_expires_at
        WHERE id = :id
    ");
    $update->execute([
        "reset_token"      => $resetToken,
        "reset_expires_at" => $resetExpiresAt,
        "id"               => $user["id"]
    ]);

    $mailSent = sendPasswordResetEmail($user["email"], $user["username"], $resetToken);

    return [
        "success"     => true,
        "reset_token" => $resetToken,
        "mail_sent"   => $mailSent,
        "message"     => $mailSent
            ? "Đã gửi liên kết đặt lại mật khẩu tới email của bạn (hiệu lực 15 phút)."
            : "Đã tạo token đặt lại mật khẩu (hiệu lực 15 phút). Do SMTP local chưa cấu hình, bạn có thể mở trực tiếp liên kết bên dưới hoặc xem trong trang Admin."
    ];
}

/**
 * Đặt lại mật khẩu mới bằng token và mã hóa bằng password_hash() (Mục 46)
 */
function resetPasswordWithToken(string $token, string $newPassword, string $confirmPassword): array
{
    if ($token === "") {
        return [
            "success" => false,
            "message" => "Token đặt lại mật khẩu không hợp lệ."
        ];
    }

    if (strlen($newPassword) < 6) {
        return [
            "success" => false,
            "message" => "Mật khẩu mới phải có ít nhất 6 ký tự."
        ];
    }

    if ($newPassword !== $confirmPassword) {
        return [
            "success" => false,
            "message" => "Mật khẩu xác nhận không khớp."
        ];
    }

    $pdo  = getDatabaseConnection();
    $stmt = $pdo->prepare("SELECT id, reset_expires_at FROM users WHERE reset_token = :token LIMIT 1");
    $stmt->execute(["token" => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        return [
            "success" => false,
            "message" => "Token đặt lại mật khẩu không tồn tại hoặc đã được sử dụng."
        ];
    }

    if (!empty($user["reset_expires_at"]) && strtotime($user["reset_expires_at"]) < time()) {
        return [
            "success" => false,
            "message" => "Token đặt lại mật khẩu đã hết hạn (quá 15 phút). Vui lòng gửi lại yêu cầu."
        ];
    }

    // Mục 46: Nâng cấp mật khẩu mới sang chuẩn password_hash() (Argon2id/Bcrypt)
    $newPasswordHash = hashPasswordSecure($newPassword);

    $update = $pdo->prepare("
        UPDATE users
        SET password_hash = :password_hash,
            reset_token = NULL,
            reset_expires_at = NULL,
            failed_attempts = 0,
            locked_until = NULL
        WHERE id = :id
    ");
    $update->execute([
        "password_hash" => $newPasswordHash,
        "id"            => $user["id"]
    ]);

    return [
        "success" => true,
        "message" => "Đặt lại mật khẩu thành công (đã mã hóa bằng password_hash). Bạn có thể đăng nhập ngay."
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
        SELECT id, token_expires_at
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

    if (!empty($user["token_expires_at"]) && strtotime($user["token_expires_at"]) < time()) {
        return [
            "success" => false,
            "message" => "Token xác nhận đã hết hạn (quá 30 phút). Vui lòng liên hệ Admin hoặc đăng ký lại."
        ];
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET email_verified = 1,
            verification_token = NULL,
            token_expires_at = NULL
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

function isAdmin(): bool
{
    return isset($_SESSION["role"]) && $_SESSION["role"] === "admin";
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit;
    }
}
