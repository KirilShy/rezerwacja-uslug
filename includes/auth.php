<?php

declare(strict_types=1);

// nieobsluzony blad trafia do logu serwera, a uzytkownik widzi tylko ogolny komunikat
set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    http_response_code(500);
    echo 'Wystapil blad. Sprobuj ponownie pozniej.';
});


if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
    ]);
}


function zalogowany(): bool
{
    return isset($_SESSION['user_id'])
        && is_int($_SESSION['user_id'])
        && $_SESSION['user_id'] > 0;
}


function zalogujUzytkownika(
    int $id,
    string $imie,
    string $rola
): void {
    if ($id <= 0) {
        throw new InvalidArgumentException('Nieprawidłowe ID użytkownika.');
    }

    $dozwoloneRole = [
        'administrator',
        'pracownik',
        'klient',
    ];

    if (!in_array($rola, $dozwoloneRole, true)) {
        throw new InvalidArgumentException('Nieprawidłowa rola użytkownika.');
    }

    // Ochrona przed session fixation.
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Nie udało się odświeżyć sesji.');
    }

    $_SESSION['user_id'] = $id;
    $_SESSION['user_imie'] = $imie;
    $_SESSION['user_rola'] = $rola;
}


function wyczyscSesje(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parametry = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $parametry['path'],
                'domain' => $parametry['domain'],
                'secure' => $parametry['secure'],
                'httponly' => $parametry['httponly'],
                'samesite' => $parametry['samesite'] ?? 'Lax',
            ]
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}


function wymagajLogowania(): void
{
    if (!zalogowany()) {
        header('Location: logowanie.php');
        exit;
    }

    global $pdo;

    require_once __DIR__ . '/../config/database.php';

    $stmt = $pdo->prepare(
        'SELECT aktywny, rola, imie
         FROM uzytkownicy
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$_SESSION['user_id']]);
    $uzytkownik = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$uzytkownik ||
        !(bool) $uzytkownik['aktywny']
    ) {
        wyczyscSesje();

        header('Location: logowanie.php');
        exit;
    }


    $dozwoloneRole = [
        'administrator',
        'pracownik',
        'klient',
    ];

    if (!in_array($uzytkownik['rola'], $dozwoloneRole, true)) {
        wyczyscSesje();

        header('Location: logowanie.php');
        exit;
    }

    $_SESSION['user_rola'] = $uzytkownik['rola'];
    $_SESSION['user_imie'] = $uzytkownik['imie'];
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