<?php
session_start();
require __DIR__ . '/../config/database.php';

$kategorie = $pdo->query('SELECT * FROM kategorie_uslug ORDER BY nazwa')->fetchAll();

$stmt = $pdo->query('SELECT * FROM uslugi WHERE aktywna = 1 ORDER BY kategoria_id');
$uslugi = $stmt->fetchAll();

$uslugiWgKategorii = [];
foreach ($uslugi as $usluga) {
    $uslugiWgKategorii[$usluga['kategoria_id']][] = $usluga;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Serwis komputerowy - rezerwacja usług</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <h1>System rezerwacji usług - serwis komputerowy</h1>

    <nav>
        <?php if (isset($_SESSION['user_id'])): ?>
            <span>Witaj, <?= htmlspecialchars($_SESSION['user_imie']) ?></span>
            | <a href="wyloguj.php">Wyloguj</a>
        <?php else: ?>
            <a href="logowanie.php">Zaloguj</a>
            | <a href="rejestracja.php">Zarejestruj sie</a>
        <?php endif; ?>
    </nav>

    <div class="uslugi">
        <?php foreach ($kategorie as $kategoria): ?>
            <div class="kategoria">
                <h2><?= htmlspecialchars($kategoria['nazwa']) ?></h2>
                <p><?= htmlspecialchars($kategoria['opis']) ?></p>

                <ul>
                    <?php foreach ($uslugiWgKategorii[$kategoria['id']] ?? [] as $usluga): ?>
                        <li>
                            <strong><?= htmlspecialchars($usluga['nazwa']) ?></strong>
                            - <?= (int) $usluga['czas_trwania'] ?> min
                            - <?= number_format((float) $usluga['cena'], 2) ?> zl
                            <p><?= htmlspecialchars($usluga['opis']) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
