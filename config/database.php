<?php

$host = 'localhost';
$dbname = 'rezerwacja_uslug';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Blad polaczenia z baza danych: ' . $e->getMessage());
}
