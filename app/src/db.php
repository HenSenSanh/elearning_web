<?php

function getDatabaseConnection(): PDO
{
    $config = require __DIR__ . "/config.php";
    $db = $config["db"];

    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
        $db["host"],
        $db["port"],
        $db["database"]
    );

    try {
        $pdo = new PDO($dsn, $db["username"], $db["password"], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
