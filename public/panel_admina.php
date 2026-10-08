<?php
require __DIR__ . '/../includes/auth.php';
wymagajRoli('administrator');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel administratora - serwis komputerowy</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <h1>Panel administratora</h1>

    <nav>
        <span>Zalogowany: <?= htmlspecialchars($_SESSION['user_imie']) ?></span>
        | <a href="profil.php">Moje konto</a>
        | <a href="index.php">Strona glowna</a>
        | <a href="wyloguj.php">Wyloguj</a>
    </nav>
</body>
</html>
