<?php
require __DIR__ . '/../includes/admin.php';
require __DIR__ . '/../includes/terminy.php';

$blad = '';
$akcja = $_SERVER['REQUEST_METHOD'] === 'POST' ? postTekst('akcja') : '';

// edytowany pracownik: z adresu (?edytuj=3) albo z wyslanego formularza
$edytowanyId = in_array($akcja, ['dane', 'uslugi', 'godziny', 'wolne_dodaj', 'wolne_usun'], true) ? liczba($_POST['id'] ?? '') : liczba($_GET['edytuj'] ?? '');
$edytowany = null;

if ($edytowanyId > 0) {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.opis, p.uzytkownik_id, u.imie, u.nazwisko, u.email, u.telefon
         FROM pracownicy p JOIN uzytkownicy u ON u.id = p.uzytkownik_id
         WHERE p.id = ?'
    );
    $stmt->execute([$edytowanyId]);
    $edytowany = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$edytowany) {
        ustawKomunikat('blad', 'Nie znaleziono pracownika.');
        przekieruj('admin_pracownicy.php');
    }
}

$powrot = 'admin_pracownicy.php?edytuj=' . $edytowanyId;

if ($akcja === 'dane') {
    $imie = postTekst('imie');
    $nazwisko = postTekst('nazwisko');
    $telefon = postTekst('telefon');
    $opis = postTekst('opis');

    if ($imie === '' || $nazwisko === '' || mb_strlen($imie) > 50 || mb_strlen($nazwisko) > 50) {
        $blad = 'Imie i nazwisko sa wymagane (maksymalnie 50 znakow).';
    } elseif ($telefon !== '' && !preg_match('/^[0-9 +-]{6,20}$/', $telefon)) {
        $blad = 'Podaj poprawny numer telefonu.';
    } elseif (mb_strlen($opis) > 1000) {
        $blad = 'Opis moze miec maksymalnie 1000 znakow.';
    } else {
        $stmt = $pdo->prepare('UPDATE uzytkownicy SET imie = ?, nazwisko = ?, telefon = ? WHERE id = ?');
        $stmt->execute([$imie, $nazwisko, $telefon, $edytowany['uzytkownik_id']]);
        $stmt = $pdo->prepare('UPDATE pracownicy SET opis = ? WHERE id = ?');
        $stmt->execute([$opis, $edytowanyId]);

        ustawKomunikat('sukces', 'Zapisano dane pracownika.');
        przekieruj($powrot);
    }
}

if ($akcja === 'uslugi') {
    $wybrane = is_array($_POST['uslugi'] ?? null) ? array_map('liczba', $_POST['uslugi']) : [];
    $istniejace = $pdo->query('SELECT id FROM uslugi')->fetchAll(PDO::FETCH_COLUMN);
    // zapisujemy tylko id uslug, ktore naprawde istnieja
    $wybrane = array_intersect(array_unique($wybrane), $istniejace);

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('DELETE FROM pracownicy_uslugi WHERE pracownik_id = ?');
    $stmt->execute([$edytowanyId]);
    $stmt = $pdo->prepare('INSERT INTO pracownicy_uslugi (pracownik_id, usluga_id) VALUES (?, ?)');
    foreach ($wybrane as $uslugaId) {
        $stmt->execute([$edytowanyId, $uslugaId]);
    }
    $pdo->commit();

    ustawKomunikat('sukces', 'Zapisano uslugi pracownika (' . count($wybrane) . ').');
    przekieruj($powrot);
}

if ($akcja === 'godziny') {
    $godziny = [];

    foreach (DNI_TYGODNIA as $nr => $nazwa) {
        $od = is_string($_POST['od'][$nr] ?? null) ? $_POST['od'][$nr] : '';
        $do = is_string($_POST['do'][$nr] ?? null) ? $_POST['do'][$nr] : '';

        if ($od === '' && $do === '') {
            continue;
        }

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $od) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $do)) {
            $blad = $nazwa . ': podaj obie godziny w formacie GG:MM albo zostaw oba pola puste.';
            break;
        }

        if (minuty($od) >= minuty($do)) {
            $blad = $nazwa . ': koniec pracy musi byc pozniej niz poczatek.';
            break;
        }

        $godziny[$nr] = [$od, $do];
    }

    if (!$blad) {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('DELETE FROM dostepnosc_pracownikow WHERE pracownik_id = ?');
        $stmt->execute([$edytowanyId]);
        $stmt = $pdo->prepare(
            'INSERT INTO dostepnosc_pracownikow (pracownik_id, dzien_tygodnia, godzina_od, godzina_do) VALUES (?, ?, ?, ?)'
        );
        foreach ($godziny as $nr => [$od, $do]) {
            $stmt->execute([$edytowanyId, $nr, $od, $do]);
        }
        $pdo->commit();

        ustawKomunikat('sukces', 'Zapisano godziny pracy.');
        przekieruj($powrot);
    }
}

if ($akcja === 'wolne_dodaj') {
    $od = poprawnaData(postTekst('data_od'));
    $do = poprawnaData(postTekst('data_do'));
    $powod = postTekst('powod');

    if (!$od || !$do) {
        $blad = 'Podaj poprawne daty dnia wolnego.';
    } elseif ($do < $od) {
        $blad = 'Data konca nie moze byc wczesniejsza niz data poczatku.';
    } elseif (mb_strlen($powod) > 255) {
        $blad = 'Powod moze miec maksymalnie 255 znakow.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO dni_wolne (pracownik_id, data_od, data_do, powod) VALUES (?, ?, ?, ?)');
        $stmt->execute([$edytowanyId, $od, $do, $powod !== '' ? $powod : null]);

        // istniejacych wizyt nie ruszamy, ale admin musi wiedziec, ze trzeba je przelozyc lub anulowac
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM rezerwacje
             WHERE pracownik_id = ? AND data_rezerwacji BETWEEN ? AND ?
               AND status IN ('oczekujaca', 'potwierdzona')"
        );
        $stmt->execute([$edytowanyId, $od, $do]);
        $kolizje = (int) $stmt->fetchColumn();

        ustawKomunikat(
            $kolizje ? 'blad' : 'sukces',
            $kolizje
                ? "Dodano dzien wolny, ale w tym okresie pracownik ma $kolizje aktywnych rezerwacji. Sprawdz je w zakladce Rezerwacje."
                : 'Dodano dzien wolny.'
        );
        przekieruj($powrot);
    }
}

if ($akcja === 'wolne_usun') {
    // warunek na pracownika: nie da sie usunac cudzego dnia wolnego podmieniajac id
    $stmt = $pdo->prepare('DELETE FROM dni_wolne WHERE id = ? AND pracownik_id = ?');
    $stmt->execute([liczba($_POST['wolne_id'] ?? ''), $edytowanyId]);

    ustawKomunikat(
        $stmt->rowCount() ? 'sukces' : 'blad',
        $stmt->rowCount() ? 'Usunieto dzien wolny.' : 'Nie znaleziono dnia wolnego.'
    );
    przekieruj($powrot);
}

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

naglowekAdmina($edytowany ? 'Pracownik: ' . $edytowany['imie'] . ' ' . $edytowany['nazwisko'] : 'Pracownicy');
?>
<?php if ($blad): ?>
    <p class="blad" role="alert"><?= e($blad) ?></p>
<?php endif; ?>

<?php if ($edytowany): ?>
    <?php
    $stmt = $pdo->prepare('SELECT usluga_id FROM pracownicy_uslugi WHERE pracownik_id = ?');
    $stmt->execute([$edytowany['id']]);
    $przypisane = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $uslugi = $pdo->query(
        'SELECT u.id, u.nazwa, u.aktywna, k.nazwa AS kategoria
         FROM uslugi u JOIN kategorie_uslug k ON k.id = u.kategoria_id
         ORDER BY k.nazwa, u.nazwa'
    )->fetchAll(PDO::FETCH_ASSOC);
    $praca = array_column(dniPracy($pdo, (int) $edytowany['id']), null, 'dzien_tygodnia');
    $dane = $akcja === 'dane' ? array_merge($edytowany, array_map('postTekst', ['imie' => 'imie', 'nazwisko' => 'nazwisko', 'telefon' => 'telefon', 'opis' => 'opis'])) : $edytowany;
    ?>
    <p><a href="admin_pracownicy.php">&larr; Wszyscy pracownicy</a></p>

    <h2>Dane</h2>
    <form method="post" class="edycja">
        <?= csrfPole() ?>
        <input type="hidden" name="akcja" value="dane">
        <input type="hidden" name="id" value="<?= (int) $edytowany['id'] ?>">
        <label>Email (login): <input type="email" value="<?= e($edytowany['email']) ?>" disabled></label>
        <label>Imie: <input type="text" name="imie" maxlength="50" required value="<?= e($dane['imie']) ?>"></label>
        <label>Nazwisko: <input type="text" name="nazwisko" maxlength="50" required value="<?= e($dane['nazwisko']) ?>"></label>
        <label>Telefon: <input type="tel" name="telefon" maxlength="20" value="<?= e($dane['telefon']) ?>"></label>
        <label>Opis: <textarea name="opis" maxlength="1000"><?= e($dane['opis']) ?></textarea></label>
        <button type="submit">Zapisz dane</button>
    </form>

    <h2>Wykonywane uslugi</h2>
    <form method="post" class="edycja">
        <?= csrfPole() ?>
        <input type="hidden" name="akcja" value="uslugi">
        <input type="hidden" name="id" value="<?= (int) $edytowany['id'] ?>">
        <?php foreach ($uslugi as $u): ?>
            <label class="pole-wyboru">
                <input type="checkbox" name="uslugi[]" value="<?= (int) $u['id'] ?>"<?= in_array($u['id'], $przypisane) ? ' checked' : '' ?>>
                <?= e($u['nazwa']) ?> <small>(<?= e($u['kategoria']) ?><?= $u['aktywna'] ? '' : ', nieaktywna' ?>)</small>
            </label>
        <?php endforeach; ?>
        <button type="submit">Zapisz uslugi</button>
    </form>

    <h2>Godziny pracy</h2>
    <form method="post" class="edycja">
        <?= csrfPole() ?>
        <input type="hidden" name="akcja" value="godziny">
        <input type="hidden" name="id" value="<?= (int) $edytowany['id'] ?>">
        <p><small>Puste pola oznaczaja dzien wolny.</small></p>
        <?php foreach (DNI_TYGODNIA as $nr => $nazwa): ?>
            <?php
            $od = $akcja === 'godziny' ? ($_POST['od'][$nr] ?? '') : godzina($praca[$nr]['godzina_od'] ?? '');
            $do = $akcja === 'godziny' ? ($_POST['do'][$nr] ?? '') : godzina($praca[$nr]['godzina_do'] ?? '');
            ?>
            <div class="godziny-dnia">
                <span><?= e($nazwa) ?></span>
                <input type="time" name="od[<?= $nr ?>]" aria-label="<?= e($nazwa) ?> od" value="<?= e(is_string($od) ? $od : '') ?>">
                <input type="time" name="do[<?= $nr ?>]" aria-label="<?= e($nazwa) ?> do" value="<?= e(is_string($do) ? $do : '') ?>">
            </div>
        <?php endforeach; ?>
        <button type="submit">Zapisz godziny</button>
    </form>

    <h2>Dni wolne i urlopy</h2>
    <?php
    $stmt = $pdo->prepare('SELECT id, data_od, data_do, powod FROM dni_wolne WHERE pracownik_id = ? AND data_do >= CURDATE() ORDER BY data_od');
    $stmt->execute([$edytowany['id']]);
    $wolne = $stmt->fetchAll();
    ?>
    <?php if (!$wolne): ?>
        <p>Brak zaplanowanych dni wolnych.</p>
    <?php else: ?>
        <div class="tabela">
            <table>
                <tr><th>Od</th><th>Do</th><th>Powod</th><th></th></tr>
                <?php foreach ($wolne as $w): ?>
                    <tr>
                        <td><?= data($w['data_od']) ?></td>
                        <td><?= data($w['data_do']) ?></td>
                        <td><?= e($w['powod']) ?></td>
                        <td>
                            <form method="post" class="w-linii" data-potwierdz="Usunac ten dzien wolny?">
                                <?= csrfPole() ?>
                                <input type="hidden" name="akcja" value="wolne_usun">
                                <input type="hidden" name="id" value="<?= (int) $edytowany['id'] ?>">
                                <input type="hidden" name="wolne_id" value="<?= (int) $w['id'] ?>">
                                <button type="submit" class="maly niebezpieczny">Usun</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" class="edycja">
        <?= csrfPole() ?>
        <input type="hidden" name="akcja" value="wolne_dodaj">
        <input type="hidden" name="id" value="<?= (int) $edytowany['id'] ?>">
        <label>Od: <input type="date" name="data_od" required value="<?= e($akcja === 'wolne_dodaj' ? postTekst('data_od') : '') ?>"></label>
        <label>Do: <input type="date" name="data_do" required value="<?= e($akcja === 'wolne_dodaj' ? postTekst('data_do') : '') ?>"></label>
        <label>Powod (opcjonalnie): <input type="text" name="powod" maxlength="255" value="<?= e($akcja === 'wolne_dodaj' ? postTekst('powod') : '') ?>"></label>
        <button type="submit">Dodaj dzien wolny</button>
    </form>
<?php else: ?>

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
<?php endif; ?>
<?php
stopkaAdmina();
