<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Przekierowanie zalogowanego użytkownika do odpowiedniego panelu.
if (zalogowany()) {
    header('Location: ' . adresPanelu($_SESSION['user_rola']));
    exit;
}

// Inicjalizacja sesyjnego tokenu CSRF.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$blad = '';
$email = '';
$haslo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $haslo = $_POST['haslo'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    // Weryfikacja tokenu CSRF.
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $blad = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (
        !is_string($email) ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        !is_string($haslo) ||
        $haslo === ''
    ) {
        $blad = 'Podaj poprawny adres email i hasło.';
    } elseif (zaDuzoProb($pdo, $email)) {
        $blad = 'Za duzo nieudanych prob logowania. Sprobuj ponownie za 15 minut.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, imie, email, haslo, rola, aktywny
             FROM uzytkownicy
             WHERE email = ?
             LIMIT 1'
        );

        $stmt->execute([$email]);
        $uzytkownik = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$uzytkownik ||
            !password_verify($haslo, $uzytkownik['haslo'])
        ) {
            $stmt = $pdo->prepare('INSERT INTO proby_logowania (email, adres_ip) VALUES (?, ?)');
            $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? '']);

            $blad = 'Niepoprawny email lub hasło.';
        } elseif (!(bool) $uzytkownik['aktywny']) {
            $blad = 'To konto zostało dezaktywowane.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM proby_logowania WHERE email = ?');
            $stmt->execute([$email]);

            // Zakończenie starej sesji i utworzenie nowego identyfikatora
            // powinno być obsługiwane przez funkcję logowania.
            zalogujUzytkownika(
                (int) $uzytkownik['id'],
                $uzytkownik['imie'],
                $uzytkownik['rola']
            );

            // Odświeżenie tokenu formularza po udanym logowaniu.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: ' . adresPanelu($uzytkownik['rola']));
            exit;
        }
    }
}

// 5 nieudanych prob na ten sam email w ciagu 15 minut blokuje logowanie
function zaDuzoProb(PDO $pdo, string $email): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM proby_logowania WHERE email = ? AND data_proby > NOW() - INTERVAL 15 MINUTE'
    );
    $stmt->execute([$email]);

    return (int) $stmt->fetchColumn() >= 5;
}

// Kodowanie danych przed umieszczeniem ich w HTML.
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
    <title>Logowanie - serwis komputerowy</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <main>
        <h1>Logowanie</h1>

        <?php if ($blad !== ''): ?>
            <p class="blad" role="alert">
                <?= e($blad) ?>
            </p>
        <?php endif; ?>

        <form method="post" action="logowanie.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <label for="email">Adres email:</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= e(is_string($email) ? $email : '') ?>"
                autocomplete="username"
                maxlength="254"
                required
                autofocus
            >

            <label for="haslo">Hasło:</label>
            <input
                type="password"
                id="haslo"
                name="haslo"
                autocomplete="current-password"
                required
            >

            <button type="submit">Zaloguj się</button>
        </form>

        <p>
            Nie masz konta?
            <a href="rejestracja.php">Zarejestruj się</a>
        </p>
    </main>

</body>
</html>