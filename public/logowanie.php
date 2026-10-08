<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';

const MAX_PROB_LOGOWANIA = 5;

$blad = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $haslo = $_POST['haslo'] ?? '';

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM proby_logowania WHERE email = ? AND data_proby > NOW() - INTERVAL 15 MINUTE'
    );
    $stmt->execute([$email]);
    $nieudaneProby = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT * FROM uzytkownicy WHERE email = ?');
    $stmt->execute([$email]);
    $uzytkownik = $stmt->fetch();

    if ($nieudaneProby >= MAX_PROB_LOGOWANIA) {
        $blad = 'Za duzo nieudanych prob logowania. Sprobuj ponownie za 15 minut.';
    } elseif (!$uzytkownik || !password_verify($haslo, $uzytkownik['haslo'])) {
        $stmt = $pdo->prepare('INSERT INTO proby_logowania (email, adres_ip) VALUES (?, ?)');
        $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? '']);

        $blad = 'Niepoprawny email lub haslo.';
    } elseif (!$uzytkownik['aktywny']) {
        $blad = 'To konto zostalo zdezaktywowane.';
    } else {
        $stmt = $pdo->prepare('DELETE FROM proby_logowania WHERE email = ?');
        $stmt->execute([$email]);

        zalogujUzytkownika((int) $uzytkownik['id'], $uzytkownik['imie'], $uzytkownik['rola']);

        header('Location: ' . adresPanelu($uzytkownik['rola']));
        exit;
    }
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
    <h1>Logowanie</h1>

    <?php if ($blad): ?>
        <p class="blad"><?= htmlspecialchars($blad) ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Email: <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></label><br>
        <label>Haslo: <input type="password" name="haslo"></label><br>
        <button type="submit">Zaloguj sie</button>
    </form>

    <p>Nie masz konta? <a href="rejestracja.php">Zarejestruj sie</a></p>
</body>
</html>
