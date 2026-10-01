<?php

session_start();

function zalogowany(): bool
{
    return isset($_SESSION['user_id']);
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
