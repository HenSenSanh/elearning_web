<?php

if (!function_exists("envValue")) {
    function envValue(string $key, string $default = ""): string
    {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}

return [
    "app" => [
        "name"         => envValue("APP_NAME", "PHP Login System"),
        "url"          => envValue("APP_URL", "http://localhost:8080"),
        "session_name" => envValue("SESSION_NAME", "LOGIN_SYSTEM_SESSION"),
    ],
    "db" => [
        "host"     => envValue("DB_HOST", "mysql"),
        "port"     => envValue("DB_PORT", "3306"),
        "database" => envValue("DB_DATABASE", "login_system"),
        "username" => envValue("DB_USERNAME", "login_user"),
        "password" => envValue("DB_PASSWORD", "login_password"),
    ],
    "smtp" => [
        "host"       => envValue("SMTP_HOST", "smtp.gmail.com"),
        "port"       => (int) envValue("SMTP_PORT", "587"),
        "username"   => envValue("SMTP_USERNAME", ""),
        "password"   => envValue("SMTP_PASSWORD", ""),
        "from_email" => envValue("SMTP_FROM_EMAIL", ""),
        "from_name"  => envValue("SMTP_FROM_NAME", "PHP Login System"),
    ],
];
