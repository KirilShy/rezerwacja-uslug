<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
wymagajLogowania();

$blad = '';
$sukces = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stare = $_POST['stare_haslo'] ?? '';
    $nowe = $_POST['nowe_haslo'] ?? '';
    $nowe2 = $_POST['nowe_haslo2'] ?? '';

    $stmt = $pdo->prepare('SELECT haslo FROM uzytkownicy WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();

    if ($stare === '' || $nowe === '' || $nowe2 === '') {
        $blad = 'Wypelnij wszystkie pola.';
    } elseif (!password_verify($stare, $hash)) {
        $blad = 'Obecne haslo jest niepoprawne.';
    } elseif (strlen($nowe) < 6) {
        $blad = 'Nowe haslo musi miec co najmniej 6 znakow.';
    } elseif ($nowe !== $nowe2) {
        $blad = 'Nowe hasla nie sa takie same.';
    } elseif ($nowe === $stare) {
        $blad = 'Nowe haslo musi sie roznic od obecnego.';
    } else {
        $stmt = $pdo->prepare('UPDATE uzytkownicy SET haslo = ? WHERE id = ?');
        $stmt->execute([password_hash($nowe, PASSWORD_DEFAULT), $_SESSION['user_id']]);

        session_regenerate_id(true);
        $sukces = 'Haslo zostalo zmienione.';
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zmiana hasla - serwis komputerowy</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <h1>Zmiana hasla</h1>

    <nav>
        <a href="profil.php">Moje konto</a>
        | <a href="<?= adresPanelu($_SESSION['user_rola']) ?>">Moj panel</a>
        | <a href="wyloguj.php">Wyloguj</a>
    </nav>

    <?php if ($blad): ?>
        <p class="blad"><?= htmlspecialchars($blad) ?></p>
    <?php endif; ?>
    <?php if ($sukces): ?>
        <p class="sukces"><?= htmlspecialchars($sukces) ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Obecne haslo: <input type="password" name="stare_haslo"></label>
        <label>Nowe haslo: <input type="password" name="nowe_haslo"></label>
        <label>Powtorz nowe haslo: <input type="password" name="nowe_haslo2"></label>
        <button type="submit">Zmien haslo</button>
    </form>
</body>
</html>
