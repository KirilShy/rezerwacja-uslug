<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/wspolne.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';

wymagajLogowania();


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
    sprawdzCsrf();

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

naglowekStrony('Moje konto', menuGlowne());
?>
<?php if ($blad): ?>
    <p class="blad" role="alert"><?= e($blad) ?></p>
<?php endif; ?>

<?php if ($sukces): ?>
    <p class="sukces" role="alert"><?= e($sukces) ?></p>
<?php endif; ?>

<form method="post" class="edycja">
    <?= csrfPole() ?>
    <label>Email: <input type="email" value="<?= e($uzytkownik['email']) ?>" disabled></label>
    <label>Imię: <input type="text" name="imie" maxlength="50" required value="<?= e($imie) ?>"></label>
    <label>Nazwisko: <input type="text" name="nazwisko" maxlength="50" required value="<?= e($nazwisko) ?>"></label>
    <label>Telefon: <input type="tel" name="telefon" maxlength="20" value="<?= e($telefon) ?>"></label>
    <button type="submit">Zapisz</button>
</form>

<p class="pod-formularzem"><a href="zmiana_hasla.php">Zmień hasło</a></p>
<?php
stopkaStrony();
