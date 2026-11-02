<?php
$config = require __DIR__ . '/../src/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name($config["app"]["session_name"]);
    session_start();
}
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

setcookie("remember_user", "", time() - 3600, "/");
setcookie("remember_token", "", time() - 3600, "/");

header("Location: login.php");
exit;
