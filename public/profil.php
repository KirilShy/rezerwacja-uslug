<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
wymagajLogowania();

$blad = '';
$sukces = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imie = trim($_POST['imie'] ?? '');
    $nazwisko = trim($_POST['nazwisko'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');

    if ($imie === '' || $nazwisko === '') {
        $blad = 'Imie i nazwisko sa wymagane.';
    } elseif (mb_strlen($imie) > 50 || mb_strlen($nazwisko) > 50) {
        $blad = 'Imie i nazwisko moga miec maksymalnie 50 znakow.';
    } elseif ($telefon !== '' && !preg_match('/^[0-9 +-]{6,20}$/', $telefon)) {
        $blad = 'Podaj poprawny numer telefonu.';
    } else {
        $stmt = $pdo->prepare('UPDATE uzytkownicy SET imie = ?, nazwisko = ?, telefon = ? WHERE id = ?');
        $stmt->execute([$imie, $nazwisko, $telefon, $_SESSION['user_id']]);

        $_SESSION['user_imie'] = $imie;
        $sukces = 'Dane zostaly zapisane.';
    }
}

$stmt = $pdo->prepare('SELECT imie, nazwisko, email, telefon FROM uzytkownicy WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$uzytkownik = $stmt->fetch();
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
        <a href="<?= adresPanelu($_SESSION['user_rola']) ?>">Moj panel</a>
        | <a href="index.php">Strona glowna</a>
        | <a href="wyloguj.php">Wyloguj</a>
    </nav>

    <?php if ($blad): ?>
        <p class="blad"><?= htmlspecialchars($blad) ?></p>
    <?php endif; ?>
    <?php if ($sukces): ?>
        <p class="sukces"><?= htmlspecialchars($sukces) ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Email: <input type="email" value="<?= htmlspecialchars($uzytkownik['email']) ?>" disabled></label><br>
        <label>Imie: <input type="text" name="imie" value="<?= htmlspecialchars($_POST['imie'] ?? $uzytkownik['imie']) ?>"></label><br>
        <label>Nazwisko: <input type="text" name="nazwisko" value="<?= htmlspecialchars($_POST['nazwisko'] ?? $uzytkownik['nazwisko']) ?>"></label><br>
        <label>Telefon: <input type="text" name="telefon" value="<?= htmlspecialchars($_POST['telefon'] ?? (string) $uzytkownik['telefon']) ?>"></label><br>
        <button type="submit">Zapisz</button>
    </form>

    <p><a href="zmiana_hasla.php">Zmien haslo</a></p>
</body>
</html>
