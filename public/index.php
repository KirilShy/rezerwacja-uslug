<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Pobranie kategorii i aktywnych usług w jednym zapytaniu.
$sql = '
    SELECT
        k.id AS kategoria_id,
        k.nazwa AS kategoria_nazwa,
        k.opis AS kategoria_opis,
        u.id AS usluga_id,
        u.nazwa AS usluga_nazwa,
        u.opis AS usluga_opis,
        u.czas_trwania,
        u.cena
    FROM kategorie_uslug k
    LEFT JOIN uslugi u
        ON u.kategoria_id = k.id
        AND u.aktywna = 1
    ORDER BY k.nazwa, u.nazwa
';

$stmt = $pdo->query($sql);
$wyniki = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grupowanie usług według kategorii.
$kategorie = [];

foreach ($wyniki as $wiersz) {
    $id = (int) $wiersz['kategoria_id'];

    if (!isset($kategorie[$id])) {
        $kategorie[$id] = [
            'nazwa' => $wiersz['kategoria_nazwa'],
            'opis' => $wiersz['kategoria_opis'],
            'uslugi' => [],
        ];
    }

    // LEFT JOIN zwraca NULL, gdy kategoria nie ma aktywnych usług.
    if ($wiersz['usluga_id'] !== null) {
        $kategorie[$id]['uslugi'][] = [
            'nazwa' => $wiersz['usluga_nazwa'],
            'opis' => $wiersz['usluga_opis'],
            'czas_trwania' => (int) $wiersz['czas_trwania'],
            'cena' => (float) $wiersz['cena'],
        ];
    }
}

// Bezpieczne wyświetlanie tekstu HTML.
function e(?string $wartosc): string
{
    return htmlspecialchars(
        $wartosc ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Serwis komputerowy - rezerwacja usług</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <h1>System rezerwacji usług - serwis komputerowy</h1>

    <nav>
        <?php if (zalogowany()): ?>
            <span>Witaj, <?= e($_SESSION['user_imie'] ?? '') ?></span>
            |
            <a href="<?= e(adresPanelu($_SESSION['user_rola'])) ?>">
                Mój panel
            </a>
            |
            <a href="wyloguj.php">Wyloguj</a>
        <?php else: ?>
            <a href="logowanie.php">Zaloguj</a>
            |
            <a href="rejestracja.php">Zarejestruj się</a>
        <?php endif; ?>
    </nav>

    <main class="uslugi">
        <?php foreach ($kategorie as $kategoria): ?>
            <section class="kategoria">
                <h2><?= e($kategoria['nazwa']) ?></h2>

                <p><?= e($kategoria['opis']) ?></p>

                <?php if (!empty($kategoria['uslugi'])): ?>
                    <ul>
                        <?php foreach ($kategoria['uslugi'] as $usluga): ?>
                            <li>
                                <strong>
                                    <?= e($usluga['nazwa']) ?>
                                </strong>
                                -
                                <?= $usluga['czas_trwania'] ?> min
                                -
                                <?= number_format(
                                    $usluga['cena'],
                                    2,
                                    ',',
                                    ' '
                                ) ?> zł

                                <p><?= e($usluga['opis']) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Brak aktywnych usług w tej kategorii.</p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </main>

</body>
</html>