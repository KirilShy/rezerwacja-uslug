<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

wymagajLogowania();

if (!function_exists('e')) {
    function e(?string $wartosc): string
    {
        return htmlspecialchars($wartosc ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$userId = (int) $_SESSION['user_id'];
$rola   = $_SESSION['user_rola'] ?? '';

$blad   = '';
$sukces = '';

$stmt = $pdo->prepare('SELECT imie, nazwisko, email, telefon FROM uzytkownicy WHERE id = ?');
$stmt->execute([$userId]);
$uzytkownik = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$uzytkownik) {
    http_response_code(404);
    exit('Nie znaleziono użytkownika.');
}

$imie     = $uzytkownik['imie'];
$nazwisko = $uzytkownik['nazwisko'];
$telefon  = (string) $uzytkownik['telefon'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imie     = trim((string) ($_POST['imie'] ?? ''));
    $nazwisko = trim((string) ($_POST['nazwisko'] ?? ''));
    $telefon  = trim((string) ($_POST['telefon'] ?? ''));

    if ($imie === '' || $nazwisko === '') {
        $blad = 'Imię i nazwisko są wymagane.';
    } elseif (mb_strlen($imie) > 50 || mb_strlen($nazwisko) > 50) {
        $blad = 'Imię i nazwisko mogą mieć maksymalnie 50 znaków.';
    } elseif ($telefon !== '' && !preg_match('/^[0-9 +-]{6,20}$/', $telefon)) {
        $blad = 'Podaj poprawny numer telefonu.';
    } else {
        $stmt = $pdo->prepare(
            'UPDATE uzytkownicy SET imie = ?, nazwisko = ?, telefon = ? WHERE id = ?'
        );
        $stmt->execute([$imie, $nazwisko, $telefon, $userId]);

        $_SESSION['user_imie'] = $imie;
        $sukces = 'Dane zostały zapisane.';
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moje konto - serwis komputerowy</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <h1>Moje konto</h1>

    <nav>
        <a href="<?= e(adresPanelu($rola)) ?>">Mój panel</a>
        | <a href="index.php">Strona główna</a>
        | <a href="wyloguj.php">Wyloguj</a>
    </nav>

    <?php if ($blad): ?>
        <p class="blad"><?= e($blad) ?></p>
    <?php endif; ?>

    <?php if ($sukces): ?>
        <p class="sukces"><?= e($sukces) ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Email:
            <input type="email" value="<?= e($uzytkownik['email']) ?>" disabled>
        </label><br>

        <label>Imię:
            <input type="text" name="imie" maxlength="50" required value="<?= e($imie) ?>">
        </label><br>

        <label>Nazwisko:
            <input type="text" name="nazwisko" maxlength="50" required value="<?= e($nazwisko) ?>">
        </label><br>

        <label>Telefon:
            <input type="tel" name="telefon" maxlength="20" value="<?= e($telefon) ?>">
        </label><br>

        <button type="submit">Zapisz</button>
    </form>

    <p><a href="zmiana_hasla.php">Zmień hasło</a></p>

</body>
</html>