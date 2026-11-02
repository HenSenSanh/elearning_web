<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/../vendor/autoload.php";

function createConfiguredMailer(): PHPMailer
{
    $config = require __DIR__ . "/config.php";
    $smtp   = $config["smtp"];

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $smtp["host"];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp["username"];
    $mail->Password   = $smtp["password"];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $smtp["port"];
    $mail->CharSet    = "UTF-8";
    $mail->setFrom($smtp["from_email"], $smtp["from_name"]);

    return $mail;
}

function sendVerificationEmail(string $toEmail, string $username, string $token): bool
{
    $config    = require __DIR__ . "/config.php";
    $appUrl    = $config["app"]["url"];
    $verifyUrl = $appUrl . "/verify_email.php?token=" . urlencode($token);

    try {
        $mail = createConfiguredMailer();
        $mail->addAddress($toEmail, $username);

        $mail->isHTML(true);
        $mail->Subject = "Xác nhận tài khoản của bạn";
        $mail->Body    = "
            <h2>Xin chào {$username}</h2>
            <p>Bạn vừa đăng ký tài khoản tại hệ thống.</p>
            <p>Vui lòng click vào liên kết dưới đây để xác nhận email (hiệu lực 30 phút):</p>
            <p><a href='{$verifyUrl}'>Xác nhận email</a></p>
            <p>Nếu bạn không đăng ký tài khoản này, vui lòng bỏ qua email.</p>
        ";
        $mail->AltBody = "Vui lòng mở link sau để xác nhận email: " . $verifyUrl;

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: " . $e->getMessage());
        return false;
    }
}

function sendPasswordResetEmail(string $toEmail, string $username, string $token): bool
{
    $config   = require __DIR__ . "/config.php";
    $appUrl   = $config["app"]["url"];
    $resetUrl = $appUrl . "/reset_password.php?token=" . urlencode($token);

    try {
        $mail = createConfiguredMailer();
        $mail->addAddress($toEmail, $username);

        $mail->isHTML(true);
        $mail->Subject = "Đặt lại mật khẩu E-Learning";
        $mail->Body    = "
            <h2>Xin chào {$username}</h2>
            <p>Hệ thống nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.</p>
            <p>Vui lòng click vào liên kết dưới đây để đặt mật khẩu mới (hiệu lực 15 phút):</p>
            <p><a href='{$resetUrl}'>Đặt lại mật khẩu</a></p>
            <p>Nếu bạn không yêu cầu, vui lòng bỏ qua email này.</p>
        ";
        $mail->AltBody = "Mở link sau để đặt lại mật khẩu (hiệu lực 15 phút): " . $resetUrl;

        return $mail->send();
    } catch (Exception $e) {
        error_log("Reset Mailer Error: " . $e->getMessage());
        return false;
    }
}

function sendOtpEmail(string $toEmail, string $username, string $otpCode): bool
{
    try {
        $mail = createConfiguredMailer();
        $mail->addAddress($toEmail, $username);

        $mail->isHTML(true);
        $mail->Subject = "Mã xác thực 2 lớp (OTP) - E-Learning";
        $mail->Body    = "
            <h2>Xin chào {$username}</h2>
            <p>Mã xác thực 2 lớp (2FA OTP) để đăng nhập của bạn là:</p>
            <h1 style='letter-spacing: 4px; color: #0d47a1;'>{$otpCode}</h1>
            <p>Mã này có hiệu lực trong vòng 5 phút.</p>
        ";
        $mail->AltBody = "Mã OTP đăng nhập của bạn là: {$otpCode} (hiệu lực 5 phút).";

        return $mail->send();
    } catch (Exception $e) {
        error_log("OTP Mailer Error: " . $e->getMessage());
        return false;
    }
}
