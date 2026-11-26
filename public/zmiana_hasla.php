<?php
require __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/wspolne.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';
wymagajLogowania();

$blad = '';
$sukces = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sprawdzCsrf();

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

naglowekStrony('Zmiana hasła', ['profil.php' => 'Moje konto'] + menuGlowne());
?>
<?php if ($blad): ?>
    <p class="blad" role="alert"><?= e($blad) ?></p>
<?php endif; ?>
<?php if ($sukces): ?>
    <p class="sukces" role="alert"><?= e($sukces) ?></p>
<?php endif; ?>

<form method="post" class="edycja">
    <?= csrfPole() ?>
    <label>Obecne hasło: <input type="password" name="stare_haslo" autocomplete="current-password" required></label>
    <label>Nowe hasło: <input type="password" name="nowe_haslo" minlength="6" autocomplete="new-password" required></label>
    <label>Powtórz nowe hasło: <input type="password" name="nowe_haslo2" minlength="6" autocomplete="new-password" required></label>
    <button type="submit">Zmień hasło</button>
</form>
<?php
stopkaStrony();
