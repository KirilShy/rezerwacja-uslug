<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

wymagajRoli('klient');

if (!function_exists('e')) {
    function e(?string $wartosc): string
    {
        return htmlspecialchars($wartosc ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$imie = $_SESSION['user_imie'] ?? '';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel klienta - serwis komputerowy</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <h1>Panel klienta</h1>

    <nav>
        <span>Zalogowany: <?= e($imie) ?></span>
        | <a href="profil.php">Moje konto</a>
        | <a href="index.php">Strona główna</a>
        | <a href="wyloguj.php">Wyloguj</a>
    </nav>

</body>
</html>