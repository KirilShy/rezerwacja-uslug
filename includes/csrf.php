<?php

declare(strict_types=1);

function csrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('Sesja musi byc uruchomiona przed uzyciem CSRF.');
    }

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        csrfOdswiez();
    }

    return $_SESSION['csrf_token'];
}

function csrfOdswiez(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfPole(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function sprawdzCsrf(): void
{
    $zSesji = $_SESSION['csrf_token'] ?? null;

    $przeslany = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';

    if (
        !is_string($zSesji)
        || !is_string($przeslany)
        || $zSesji === ''
        || !hash_equals($zSesji, $przeslany)
    ) {
        http_response_code(400);
        exit('Sesja formularza wygasla. Odswiez strone i sprobuj ponownie.');
    }
}