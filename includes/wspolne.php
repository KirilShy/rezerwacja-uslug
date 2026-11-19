<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';

// sprawdza role, a kazdy wyslany formularz musi miec poprawny token CSRF
function chronStrone(string $rola): void
{
    wymagajRoli($rola);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        sprawdzCsrf();
    }
}

if (!function_exists('e')) {
    function e(?string $wartosc): string
    {
        return htmlspecialchars($wartosc ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
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

function godzina(string $czas): string
{
    return substr($czas, 0, 5);
}

function data(string $data): string
{
    return date('d.m.Y', strtotime($data));
}

function cena(string|float $cena): string
{
    return number_format((float) $cena, 2, ',', ' ') . ' zl';
}

// wspolny poczatek strony: tytul, menu (link => nazwa) i komunikat po przekierowaniu
function naglowekStrony(string $tytul, array $linki): void
{
    $biezaca = basename($_SERVER['SCRIPT_NAME']);
    $komunikat = $_SESSION['komunikat'] ?? null;
    unset($_SESSION['komunikat']);
    ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tytul) ?> - serwis komputerowy</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/script.js" defer></script>
</head>
<body class="panel">
    <h1><?= e($tytul) ?></h1>

    <nav>
        <?php foreach ($linki as $plik => $nazwa): ?>
            <a href="<?= e($plik) ?>"<?= $plik === $biezaca ? ' aria-current="page"' : '' ?>><?= e($nazwa) ?></a>
        <?php endforeach; ?>
        <span class="odstep"></span>
        <?php if (zalogowany()): ?>
            <a href="profil.php">Moje konto</a>
            <a href="wyloguj.php">Wyloguj</a>
        <?php else: ?>
            <a href="logowanie.php">Zaloguj</a>
            <a href="rejestracja.php">Zarejestruj sie</a>
        <?php endif; ?>
    </nav>

    <main>
        <?php if ($komunikat): ?>
            <p class="<?= $komunikat['typ'] === 'blad' ? 'blad' : 'sukces' ?>" role="alert"><?= e($komunikat['tekst']) ?></p>
        <?php endif; ?>
    <?php
}

function linkiAdmina(): array
{
    $linki = [
        'panel_admina.php' => 'Dashboard',
        'admin_kategorie.php' => 'Kategorie',
        'admin_uslugi.php' => 'Uslugi',
        'admin_pracownicy.php' => 'Pracownicy',
        'admin_uzytkownicy.php' => 'Uzytkownicy',
        'admin_rezerwacje.php' => 'Rezerwacje',
    ];

    // modul pojawia sie w menu dopiero, gdy jego strona istnieje
    return array_filter(
        $linki,
        fn ($plik) => file_exists(__DIR__ . '/../public/' . $plik),
        ARRAY_FILTER_USE_KEY
    );
}

function stopkaStrony(): void
{
    ?>
    </main>
</body>
</html>
    <?php
}
