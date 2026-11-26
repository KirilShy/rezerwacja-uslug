<?php
declare(strict_types=1);

// godziny rezerwacji liczymy w czasie polskim, niezaleznie od ustawien serwera
date_default_timezone_set('Europe/Warsaw');

$configLocal = __DIR__ . '/config.local.php';

if (is_file($configLocal)) {
    require_once $configLocal;
} else {
    // Fallback tylko dla srodowiska lokalnego/deweloperskiego
    defined('DB_HOST') || define('DB_HOST', 'localhost');
    defined('DB_NAME') || define('DB_NAME', 'rezerwacja_uslug');
    defined('DB_USER') || define('DB_USER', 'root');
    defined('DB_PASS') || define('DB_PASS', '');
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // MySQL (CURDATE, NOW) dostaje te sama strefe co PHP, z czasem letnim/zimowym;
    // zwykle zapytanie zamiast PDO::MYSQL_ATTR_INIT_COMMAND, ktora w PHP 8.5 jest przestarzala
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Blad polaczenia z baza danych. Sprobuj ponownie pozniej.');
}