<?php

// nieobsluzony blad trafia do logu serwera, a uzytkownik widzi tylko ogolny komunikat
set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    http_response_code(500);
    echo 'Wystapil blad. Sprobuj ponownie pozniej.';
});

// httponly: JavaScript nie odczyta ciasteczka sesji, samesite: przegladarka nie wysle go z obcych stron
// strict_mode: serwer nie przyjmie identyfikatora sesji, ktorego sam nie utworzyl
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

function zalogowany(): bool
{
    return isset($_SESSION['user_id']);
}

function zalogujUzytkownika(int $id, string $imie, string $rola): void
{
    // nowe id sesji po zalogowaniu chroni przed przejeciem starej sesji
    session_regenerate_id(true);

    $_SESSION['user_id'] = $id;
    $_SESSION['user_imie'] = $imie;
    $_SESSION['user_rola'] = $rola;
}

function wymagajLogowania(): void
{
    if (!zalogowany()) {
        header('Location: logowanie.php');
        exit;
    }
}

function wymagajRoli(string $rola): void
{
    wymagajLogowania();

    if ($_SESSION['user_rola'] !== $rola) {
        http_response_code(403);
        exit('Brak dostepu do tej strony.');
    }
}

function adresPanelu(string $rola): string
{
    if ($rola === 'administrator') {
        return 'panel_admina.php';
    }

    if ($rola === 'pracownik') {
        return 'panel_pracownika.php';
    }

    return 'panel_klienta.php';
}
