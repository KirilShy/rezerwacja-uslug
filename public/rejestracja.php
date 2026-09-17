<?php
session_start();
require __DIR__ . '/../config/database.php';

$blad = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imie = trim($_POST['imie'] ?? '');
    $nazwisko = trim($_POST['nazwisko'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $haslo = $_POST['haslo'] ?? '';

    if ($imie === '' || $nazwisko === '' || $email === '' || $haslo === '') {
        $blad = 'Wszystkie pola oprocz telefonu sa wymagane.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $blad = 'Podaj poprawny adres email.';
    } elseif (strlen($haslo) < 6) {
        $blad = 'Haslo musi miec co najmniej 6 znakow.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM uzytkownicy WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $blad = 'Ten email jest juz zajety.';
        } else {
            $hash = password_hash($haslo, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, telefon, rola) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$imie, $nazwisko, $email, $hash, $telefon, 'klient']);

            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_imie'] = $imie;
            $_SESSION['user_rola'] = 'klient';

            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Rejestracja - serwis komputerowy</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <h1>Rejestracja</h1>

    <?php if ($blad): ?>
        <p class="blad"><?= htmlspecialchars($blad) ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Imie: <input type="text" name="imie" value="<?= htmlspecialchars($_POST['imie'] ?? '') ?>"></label><br>
        <label>Nazwisko: <input type="text" name="nazwisko" value="<?= htmlspecialchars($_POST['nazwisko'] ?? '') ?>"></label><br>
        <label>Email: <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></label><br>
        <label>Telefon: <input type="text" name="telefon" value="<?= htmlspecialchars($_POST['telefon'] ?? '') ?>"></label><br>
        <label>Haslo: <input type="password" name="haslo"></label><br>
        <button type="submit">Zarejestruj sie</button>
    </form>

    <p>Masz juz konto? <a href="logowanie.php">Zaloguj sie</a></p>
</body>
</html>
