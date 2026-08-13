<?php

$config = require __DIR__ . '/../config/database.php';

$dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";

try {
    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "<h1>VyaapaarOS</h1>";
    echo "<p>PostgreSQL Database Connected Successfully ✅</p>";
    echo "<p>Database: " . htmlspecialchars($config['database']) . "</p>";

} catch (PDOException $e) {

    http_response_code(500);

    echo "<h1>Database Connection Failed ❌</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}