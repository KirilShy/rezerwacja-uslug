<?php
require __DIR__ . '/../includes/admin.php';
require __DIR__ . '/../includes/terminy.php';

$blad = '';
$akcja = $_SERVER['REQUEST_METHOD'] === 'POST' ? postTekst('akcja') : '';

// nieaktywny pracownik znika z wyboru przy rezerwacji, ale jego wizyty zostaja
if ($akcja === 'przelacz') {
    $stmt = $pdo->prepare('UPDATE pracownicy SET aktywny = NOT aktywny WHERE id = ?');
    $stmt->execute([liczba($_POST['id'] ?? '')]);

    ustawKomunikat(
        $stmt->rowCount() ? 'sukces' : 'blad',
        $stmt->rowCount() ? 'Zmieniono status pracownika.' : 'Nie znaleziono pracownika.'
    );
    przekieruj('admin_pracownicy.php');
}

if ($akcja === 'dodaj') {
    $imie = postTekst('imie');
    $nazwisko = postTekst('nazwisko');
    $email = postTekst('email');
    $telefon = postTekst('telefon');
    $haslo = $_POST['haslo'] ?? '';
    $opis = postTekst('opis');

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM uzytkownicy WHERE email = ?');
    $stmt->execute([$email]);

    if ($imie === '' || $nazwisko === '' || $email === '') {
        $blad = 'Imie, nazwisko i email sa wymagane.';
    } elseif (mb_strlen($imie) > 50 || mb_strlen($nazwisko) > 50 || mb_strlen($email) > 100) {
        $blad = 'Imie i nazwisko moga miec maksymalnie 50 znakow, email 100.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $blad = 'Podaj poprawny adres email.';
    } elseif ($stmt->fetchColumn() > 0) {
        $blad = 'Ten email jest juz zajety.';
    } elseif ($telefon !== '' && !preg_match('/^[0-9 +-]{6,20}$/', $telefon)) {
        $blad = 'Podaj poprawny numer telefonu.';
    } elseif (!is_string($haslo) || strlen($haslo) < 6) {
        $blad = 'Haslo musi miec co najmniej 6 znakow.';
    } elseif (mb_strlen($opis) > 1000) {
        $blad = 'Opis moze miec maksymalnie 1000 znakow.';
    } else {
        // konto i rekord pracownika powstaja razem albo wcale
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, telefon, rola) VALUES (?, ?, ?, ?, ?, 'pracownik')"
        );
        $stmt->execute([$imie, $nazwisko, $email, password_hash($haslo, PASSWORD_DEFAULT), $telefon]);

        $stmt = $pdo->prepare('INSERT INTO pracownicy (uzytkownik_id, opis) VALUES (?, ?)');
        $stmt->execute([$pdo->lastInsertId(), $opis]);
        $nowyId = (int) $pdo->lastInsertId();

        $pdo->commit();

        ustawKomunikat('sukces', 'Dodano pracownika. Ustaw teraz jego uslugi i godziny pracy.');
        przekieruj('admin_pracownicy.php?edytuj=' . $nowyId);
    }
}

$pracownicy = $pdo->query(
    "SELECT p.id, p.opis, p.aktywny, u.imie, u.nazwisko, u.email, u.telefon, u.aktywny AS konto_aktywne,
            (SELECT COUNT(*) FROM pracownicy_uslugi pu WHERE pu.pracownik_id = p.id) AS liczba_uslug
     FROM pracownicy p
     JOIN uzytkownicy u ON u.id = p.uzytkownik_id
     ORDER BY u.nazwisko, u.imie"
)->fetchAll(PDO::FETCH_ASSOC);

naglowekAdmina('Pracownicy');
?>
<?php if ($blad): ?>
    <p class="blad" role="alert"><?= e($blad) ?></p>
<?php endif; ?>

<div class="tabela">
    <table>
        <tr>
            <th>Pracownik</th>
            <th>Kontakt</th>
            <th>Uslugi</th>
            <th>Godziny pracy</th>
            <th>Status</th>
            <th>Akcje</th>
        </tr>
        <?php foreach ($pracownicy as $p): ?>
            <tr<?= $p['aktywny'] ? '' : ' class="nieaktywny"' ?>>
                <td><?= e($p['imie'] . ' ' . $p['nazwisko']) ?><br><small><?= e($p['opis']) ?></small></td>
                <td><?= e($p['email']) ?><br><?= e($p['telefon']) ?></td>
                <td><?= (int) $p['liczba_uslug'] ?></td>
                <td>
                    <?php foreach (dniPracy($pdo, (int) $p['id']) as $d): ?>
                        <?= mb_substr(DNI_TYGODNIA[$d['dzien_tygodnia']], 0, 3) ?> <?= godzina($d['godzina_od']) ?>-<?= godzina($d['godzina_do']) ?><br>
                    <?php endforeach; ?>
                </td>
                <td>
                    <span class="status"><?= $p['aktywny'] ? 'Aktywny' : 'Nieaktywny' ?></span>
                    <?php if (!$p['konto_aktywne']): ?>
                        <br><small>konto zablokowane</small>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="akcje">
                        <a class="przycisk" href="admin_pracownicy.php?edytuj=<?= (int) $p['id'] ?>">Edytuj</a>
                        <form method="post" class="w-linii">
                            <?= csrfPole() ?>
                            <input type="hidden" name="akcja" value="przelacz">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="maly"><?= $p['aktywny'] ? 'Dezaktywuj' : 'Aktywuj' ?></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<h2>Dodaj pracownika</h2>
<form method="post" class="edycja">
    <?= csrfPole() ?>
    <input type="hidden" name="akcja" value="dodaj">
    <label>Imie: <input type="text" name="imie" maxlength="50" required value="<?= e(postTekst('imie')) ?>"></label>
    <label>Nazwisko: <input type="text" name="nazwisko" maxlength="50" required value="<?= e(postTekst('nazwisko')) ?>"></label>
    <label>Email (login): <input type="email" name="email" maxlength="100" required value="<?= e(postTekst('email')) ?>"></label>
    <label>Telefon: <input type="tel" name="telefon" maxlength="20" value="<?= e(postTekst('telefon')) ?>"></label>
    <label>Haslo startowe: <input type="password" name="haslo" minlength="6" required></label>
    <label>Opis (specjalizacja): <textarea name="opis" maxlength="1000"><?= e(postTekst('opis')) ?></textarea></label>
    <button type="submit">Dodaj</button>
</form>
<?php
stopkaAdmina();
