<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';

wymagajRoli('administrator');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sprawdzCsrf();
}

function e(?string $wartosc): string
{
    return htmlspecialchars($wartosc ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// pole z formularza jako tekst; tablica lub brak pola daje pusty tekst zamiast bledu
function postTekst(string $klucz): string
{
    $wartosc = $_POST[$klucz] ?? '';

    return is_string($wartosc) ? trim($wartosc) : '';
}

// liczba calkowita z formularza lub adresu; cokolwiek innego daje 0
function liczba(mixed $wartosc): int
{
    return is_string($wartosc) && ctype_digit($wartosc) ? (int) $wartosc : 0;
}

// komunikat przetrwa przekierowanie po zapisie formularza i pokaze sie na nastepnej stronie
function ustawKomunikat(string $typ, string $tekst): void
{
    $_SESSION['komunikat'] = ['typ' => $typ, 'tekst' => $tekst];
}

function przekieruj(string $adres): void
{
    header('Location: ' . $adres);
    exit;
}

const STATUSY_REZERWACJI = [
    'oczekujaca' => 'Oczekujaca',
    'potwierdzona' => 'Potwierdzona',
    'zrealizowana' => 'Zrealizowana',
    'anulowana' => 'Anulowana',
];

function nazwaStatusu(string $status): string
{
    return STATUSY_REZERWACJI[$status] ?? $status;
}

function naglowekAdmina(string $tytul): void
{
    $linki = [
        'panel_admina.php' => 'Dashboard',
        'admin_kategorie.php' => 'Kategorie',
        'admin_uslugi.php' => 'Uslugi',
        'admin_pracownicy.php' => 'Pracownicy',
        'admin_uzytkownicy.php' => 'Uzytkownicy',
        'admin_rezerwacje.php' => 'Rezerwacje',
    ];
    $biezaca = basename($_SERVER['SCRIPT_NAME']);
    $komunikat = $_SESSION['komunikat'] ?? null;
    unset($_SESSION['komunikat']);
    ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tytul) ?> - panel administratora</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/script.js" defer></script>
</head>
<body class="admin">
    <h1><?= e($tytul) ?></h1>

    <nav>
        <?php foreach ($linki as $plik => $nazwa): ?>
            <?php // modul pojawia sie w menu dopiero, gdy jego strona istnieje ?>
            <?php if (file_exists(__DIR__ . '/../public/' . $plik)): ?>
                <a href="<?= e($plik) ?>"<?= $plik === $biezaca ? ' aria-current="page"' : '' ?>><?= e($nazwa) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <span class="odstep"></span>
        <a href="profil.php">Moje konto</a>
        <a href="wyloguj.php">Wyloguj</a>
    </nav>

    <main>
        <?php if ($komunikat): ?>
            <p class="<?= $komunikat['typ'] === 'blad' ? 'blad' : 'sukces' ?>" role="alert"><?= e($komunikat['tekst']) ?></p>
        <?php endif; ?>
    <?php
}

function stopkaAdmina(): void
{
    ?>
    </main>
</body>
</html>
    <?php
}
