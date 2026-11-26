<?php
require __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/wspolne.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';

if (zalogowany()) {
    header('Location: ' . adresPanelu($_SESSION['user_rola']));
    exit;
}

$blad = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sprawdzCsrf();

    $imie = postTekst('imie');
    $nazwisko = postTekst('nazwisko');
    $email = postTekst('email');
    $telefon = postTekst('telefon');
    $haslo = is_string($_POST['haslo'] ?? null) ? $_POST['haslo'] : '';
    $haslo2 = is_string($_POST['haslo2'] ?? null) ? $_POST['haslo2'] : '';

    if ($imie === '' || $nazwisko === '' || $email === '' || $telefon === '' || $haslo === '') {
        $blad = 'Wypelnij wszystkie pola.';
    } elseif (mb_strlen($imie) > 50 || mb_strlen($nazwisko) > 50) {
        $blad = 'Imie i nazwisko moga miec maksymalnie 50 znakow.';
    } elseif (mb_strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $blad = 'Podaj poprawny adres email.';
    } elseif (!preg_match('/^[0-9 +-]{6,20}$/', $telefon)) {
        $blad = 'Podaj poprawny numer telefonu.';
    } elseif (strlen($haslo) < 6) {
        $blad = 'Haslo musi miec co najmniej 6 znakow.';
    } elseif ($haslo !== $haslo2) {
        $blad = 'Hasla nie sa takie same.';
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

            zalogujUzytkownika((int) $pdo->lastInsertId(), $imie, 'klient');

            header('Location: panel_klienta.php');
            exit;
        }
    }
}

naglowekStrony('Rejestracja', menuGlowne());
?>
<?php if ($blad): ?>
    <p class="blad" role="alert"><?= e($blad) ?></p>
<?php endif; ?>

<form method="post">
    <?= csrfPole() ?>
    <label>Imię: <input type="text" name="imie" maxlength="50" required value="<?= e(postTekst('imie')) ?>"></label>
    <label>Nazwisko: <input type="text" name="nazwisko" maxlength="50" required value="<?= e(postTekst('nazwisko')) ?>"></label>
    <label>Email: <input type="email" name="email" maxlength="100" autocomplete="email" required value="<?= e(postTekst('email')) ?>"></label>
    <label>Telefon: <input type="tel" name="telefon" maxlength="20" required value="<?= e(postTekst('telefon')) ?>"></label>
    <label>Hasło: <input type="password" name="haslo" minlength="6" autocomplete="new-password" required></label>
    <label>Powtórz hasło: <input type="password" name="haslo2" minlength="6" autocomplete="new-password" required></label>
    <button type="submit">Zarejestruj się</button>
</form>

<p class="pod-formularzem">Masz już konto? <a href="logowanie.php">Zaloguj się</a></p>
<?php
stopkaStrony();
