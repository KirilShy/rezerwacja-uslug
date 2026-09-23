<?php

$configLocal = __DIR__ . '/config.local.php';

if (file_exists($configLocal)) {
    require $configLocal;
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'rezerwacja_uslug');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Blad polaczenia z baza danych: ' . $e->getMessage());
}
