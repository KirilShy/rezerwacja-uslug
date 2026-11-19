<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!function_exists('e')) {
    function e(?string $wartosc): string
    {
        return htmlspecialchars($wartosc ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ?kategoria=ID pokazuje tylko jedna kategorie
$wybranaKategoria = isset($_GET['kategoria']) && is_string($_GET['kategoria']) && ctype_digit($_GET['kategoria'])
    ? (int) $_GET['kategoria']
    : 0;

$wszystkieKategorie = $pdo->query('SELECT id, nazwa FROM kategorie_uslug ORDER BY nazwa')->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    'SELECT k.id AS kategoria_id,
            k.nazwa AS kategoria_nazwa,
            k.opis AS kategoria_opis,
            u.id AS usluga_id,
            u.nazwa AS usluga_nazwa,
            u.opis AS usluga_opis,
            u.czas_trwania,
            u.cena
     FROM kategorie_uslug k
     LEFT JOIN uslugi u ON u.kategoria_id = k.id AND u.aktywna = 1
     WHERE ? = 0 OR k.id = ?
     ORDER BY k.nazwa, u.nazwa'
);
$stmt->execute([$wybranaKategoria, $wybranaKategoria]);
$wyniki = $stmt->fetchAll(PDO::FETCH_ASSOC);

$kategorie = [];

foreach ($wyniki as $wiersz) {
    $id = (int) $wiersz['kategoria_id'];

    $kategorie[$id] ??= [
        'nazwa'  => $wiersz['kategoria_nazwa'],
        'opis'   => $wiersz['kategoria_opis'],
        'uslugi' => [],
    ];

    if ($wiersz['usluga_nazwa'] !== null) {
        $kategorie[$id]['uslugi'][] = [
            'id'           => (int) $wiersz['usluga_id'],
            'nazwa'        => $wiersz['usluga_nazwa'],
            'opis'         => $wiersz['usluga_opis'],
            'czas_trwania' => (int) $wiersz['czas_trwania'],
            'cena'         => number_format((float) $wiersz['cena'], 2, ',', ' '),
        ];
    }
}

unset($wyniki);

$zalogowany = zalogowany();
$imie       = $_SESSION['user_imie'] ?? '';
$rola       = $_SESSION['user_rola'] ?? '';
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
        <?php if ($zalogowany): ?>
            <span>Witaj, <?= e($imie) ?></span>
            | <a href="<?= e(adresPanelu($rola)) ?>">Mój panel</a>
            | <a href="wyloguj.php">Wyloguj</a>
        <?php else: ?>
            <a href="logowanie.php">Zaloguj</a>
            | <a href="rejestracja.php">Zarejestruj się</a>
        <?php endif; ?>
    </nav>

    <p class="filtr-kategorii">
        Kategoria:
        <a href="index.php"<?= $wybranaKategoria === 0 ? ' aria-current="page"' : '' ?>>Wszystkie</a>
        <?php foreach ($wszystkieKategorie as $k): ?>
            | <a href="index.php?kategoria=<?= (int) $k['id'] ?>"<?= (int) $k['id'] === $wybranaKategoria ? ' aria-current="page"' : '' ?>><?= e($k['nazwa']) ?></a>
        <?php endforeach; ?>
    </p>

    <main class="uslugi">
        <?php foreach ($kategorie as $kategoria): ?>
            <section class="kategoria">
                <h2><?= e($kategoria['nazwa']) ?></h2>

                <?php if ($kategoria['opis']): ?>
                    <p><?= e($kategoria['opis']) ?></p>
                <?php endif; ?>

                <?php if ($kategoria['uslugi']): ?>
                    <ul>
                        <?php foreach ($kategoria['uslugi'] as $usluga): ?>
                            <li>
                                <strong><a href="usluga.php?id=<?= $usluga['id'] ?>"><?= e($usluga['nazwa']) ?></a></strong>
                                - <?= $usluga['czas_trwania'] ?> min
                                - <?= $usluga['cena'] ?> zł

                                <?php if ($usluga['opis']): ?>
                                    <p><?= e($usluga['opis']) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Brak aktywnych usług w tej kategorii.</p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <?php if (!$kategorie): ?>
            <p>Nie znaleziono kategorii.</p>
        <?php endif; ?>
    </main>

</body>
</html>