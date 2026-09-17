<?php
session_start();
require __DIR__ . '/../config/database.php';

$blad = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $haslo = $_POST['haslo'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM uzytkownicy WHERE email = ?');
    $stmt->execute([$email]);
    $uzytkownik = $stmt->fetch();

    if (!$uzytkownik || !password_verify($haslo, $uzytkownik['haslo'])) {
        $blad = 'Niepoprawny email lub haslo.';
    } elseif (!$uzytkownik['aktywny']) {
        $blad = 'To konto zostalo zdezaktywowane.';
    } else {
        $_SESSION['user_id'] = $uzytkownik['id'];
        $_SESSION['user_imie'] = $uzytkownik['imie'];
        $_SESSION['user_rola'] = $uzytkownik['rola'];

        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
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
