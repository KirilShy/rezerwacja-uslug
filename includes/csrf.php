<?php

// jeden losowy token na sesje; kazdy formularz POST wysyla go w ukrytym polu,
// wiec obca strona nie moze wyslac formularza w imieniu zalogowanego admina
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfPole(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function sprawdzCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(400);
        exit('Sesja formularza wygasla. Odswiez strone i sprobuj ponownie.');
    }
}
