<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

const ROLE_UZYTKOWNIKA = ['administrator', 'pracownik', 'klient'];

const SPRAWDZANIE_CO = 0;

set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo 'Wystapil blad. Sprobuj ponownie pozniej.';
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

function zalogowany(): bool
{
    return isset($_SESSION['user_id'])
        && is_int($_SESSION['user_id'])
        && $_SESSION['user_id'] > 0;
}

function zalogujUzytkownika(int $id, string $imie, string $rola): void
{
    if ($id <= 0) {
        throw new InvalidArgumentException('Nieprawidłowe ID użytkownika.');
    }

    if (!in_array($rola, ROLE_UZYTKOWNIKA, true)) {
        throw new InvalidArgumentException('Nieprawidłowa rola użytkownika.');
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Nie udało się odświeżyć sesji.');
    }

    $_SESSION['user_id'] = $id;
    $_SESSION['user_imie'] = $imie;
    $_SESSION['user_rola'] = $rola;
    $_SESSION['sprawdzono_o'] = time();
}

function wyczyscSesje(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parametry = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parametry['path'],
            'domain' => $parametry['domain'],
            'secure' => $parametry['secure'],
            'httponly' => $parametry['httponly'],
            'samesite' => $parametry['samesite'] ?? 'Lax',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function przekierujDoLogowania(): void
{
    header('Location: logowanie.php');
    exit;
}

function wymagajLogowania(): void
{
    static $sprawdzono = false;

    if ($sprawdzono) {
        return;
    }

    if (!zalogowany()) {
        przekierujDoLogowania();
    }

    header('Cache-Control: no-store');

    $teraz = time();

    if (
        SPRAWDZANIE_CO > 0
        && $teraz - (int) ($_SESSION['sprawdzono_o'] ?? 0) < SPRAWDZANIE_CO
    ) {
        $sprawdzono = true;
        return;
    }

    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT aktywny, rola, imie FROM uzytkownicy WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $uzytkownik = $stmt->fetch();

    if (
        !$uzytkownik
        || !(bool) $uzytkownik['aktywny']
        || !in_array($uzytkownik['rola'], ROLE_UZYTKOWNIKA, true)
    ) {
        wyczyscSesje();
        przekierujDoLogowania();
    }

    $_SESSION['user_rola'] = $uzytkownik['rola'];
    $_SESSION['user_imie'] = $uzytkownik['imie'];
    $_SESSION['sprawdzono_o'] = $teraz;

    $sprawdzono = true;
}

function wymagajRoli(string $rola): void
{
    wymagajLogowania();

    if (($_SESSION['user_rola'] ?? null) !== $rola) {
        http_response_code(403);
        exit('Brak dostępu do tej strony.');
    }
}

function adresPanelu(string $rola): string
{
    return match ($rola) {
        'administrator' => 'panel_admina.php',
        'pracownik' => 'panel_pracownika.php',
        'klient' => 'panel_klienta.php',
        default => 'logowanie.php',
    };
}