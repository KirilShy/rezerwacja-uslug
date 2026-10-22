<?php
require __DIR__ . '/../includes/admin.php';

$blad = '';
$akcja = $_SERVER['REQUEST_METHOD'] === 'POST' ? postTekst('akcja') : '';

$kategorie = $pdo->query('SELECT id, nazwa FROM kategorie_uslug ORDER BY nazwa')->fetchAll();
$idKategorii = array_column($kategorie, 'id');

$edytowanaId = $akcja === 'zapisz' ? liczba($_POST['id'] ?? '') : liczba($_GET['edytuj'] ?? '');
$edytowana = null;

if ($edytowanaId > 0) {
    $stmt = $pdo->prepare('SELECT id, kategoria_id, nazwa, opis, czas_trwania, cena FROM uslugi WHERE id = ?');
    $stmt->execute([$edytowanaId]);
    $edytowana = $stmt->fetch() ?: null;

    if (!$edytowana) {
        ustawKomunikat('blad', 'Nie znaleziono uslugi.');
        przekieruj('admin_uslugi.php');
    }
}

if ($akcja === 'dodaj' || $akcja === 'zapisz') {
    $nazwa = postTekst('nazwa');
    $opis = postTekst('opis');
    $kategoriaId = liczba($_POST['kategoria_id'] ?? '');
    $czas = liczba($_POST['czas_trwania'] ?? '');
    // przecinek tez jest akceptowany: 49,99 -> 49.99
    $cena = str_replace(',', '.', postTekst('cena'));

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM uslugi WHERE nazwa = ? AND id <> ?');
    $stmt->execute([$nazwa, $edytowanaId]);

    if ($nazwa === '') {
        $blad = 'Podaj nazwe uslugi.';
    } elseif (mb_strlen($nazwa) > 100) {
        $blad = 'Nazwa moze miec maksymalnie 100 znakow.';
    } elseif ($stmt->fetchColumn() > 0) {
        $blad = 'Usluga o takiej nazwie juz istnieje.';
    } elseif (!in_array($kategoriaId, $idKategorii)) {
        $blad = 'Wybierz kategorie z listy.';
    } elseif ($czas < 5 || $czas > 480) {
        $blad = 'Czas trwania musi byc liczba minut od 5 do 480.';
    } elseif (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $cena)) {
        $blad = 'Podaj cene jako liczbe, np. 49.99.';
    } elseif (mb_strlen($opis) > 1000) {
        $blad = 'Opis moze miec maksymalnie 1000 znakow.';
    } elseif ($akcja === 'zapisz') {
        $stmt = $pdo->prepare(
            'UPDATE uslugi SET kategoria_id = ?, nazwa = ?, opis = ?, czas_trwania = ?, cena = ? WHERE id = ?'
        );
        $stmt->execute([$kategoriaId, $nazwa, $opis, $czas, $cena, $edytowanaId]);

        ustawKomunikat('sukces', 'Zapisano zmiany w usludze.');
        przekieruj('admin_uslugi.php');
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO uslugi (kategoria_id, nazwa, opis, czas_trwania, cena) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$kategoriaId, $nazwa, $opis, $czas, $cena]);

        ustawKomunikat('sukces', 'Dodano usluge.');
        przekieruj('admin_uslugi.php');
    }
}

$uslugi = $pdo->query(
    'SELECT u.id, u.nazwa, u.czas_trwania, u.cena, u.aktywna, k.nazwa AS kategoria,
            (SELECT COUNT(*) FROM rezerwacje r WHERE r.usluga_id = u.id) AS liczba_rezerwacji
     FROM uslugi u
     JOIN kategorie_uslug k ON k.id = u.kategoria_id
     ORDER BY k.nazwa, u.nazwa'
)->fetchAll();

naglowekAdmina('Uslugi');
?>
<?php if ($blad): ?>
    <p class="blad" role="alert"><?= e($blad) ?></p>
<?php endif; ?>

<div class="tabela">
    <table>
        <tr>
            <th>Nazwa</th>
            <th>Kategoria</th>
            <th>Czas</th>
            <th>Cena</th>
            <th>Rezerwacje</th>
            <th>Status</th>
            <th>Akcje</th>
        </tr>
        <?php foreach ($uslugi as $u): ?>
            <tr<?= $u['aktywna'] ? '' : ' class="nieaktywny"' ?>>
                <td><?= e($u['nazwa']) ?></td>
                <td><?= e($u['kategoria']) ?></td>
                <td><?= (int) $u['czas_trwania'] ?> min</td>
                <td><?= number_format((float) $u['cena'], 2, ',', ' ') ?> zl</td>
                <td><?= (int) $u['liczba_rezerwacji'] ?></td>
                <td><span class="status"><?= $u['aktywna'] ? 'Aktywna' : 'Nieaktywna' ?></span></td>
                <td>
                    <div class="akcje">
                        <a class="przycisk" href="admin_uslugi.php?edytuj=<?= (int) $u['id'] ?>">Edytuj</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php
$pola = $blad
    ? [
        'nazwa' => postTekst('nazwa'),
        'opis' => postTekst('opis'),
        'kategoria_id' => liczba($_POST['kategoria_id'] ?? ''),
        'czas_trwania' => postTekst('czas_trwania'),
        'cena' => postTekst('cena'),
    ]
    : ($edytowana ?? ['nazwa' => '', 'opis' => '', 'kategoria_id' => 0, 'czas_trwania' => '30', 'cena' => '']);
?>
<h2><?= $edytowana ? 'Edytuj usluge' : 'Dodaj usluge' ?></h2>
<?php if (!$kategorie): ?>
    <p>Najpierw dodaj kategorie w zakladce <a href="admin_kategorie.php">Kategorie</a>.</p>
<?php else: ?>
    <form method="post" class="edycja">
        <?= csrfPole() ?>
        <?php if ($edytowana): ?>
            <input type="hidden" name="akcja" value="zapisz">
            <input type="hidden" name="id" value="<?= (int) $edytowana['id'] ?>">
        <?php else: ?>
            <input type="hidden" name="akcja" value="dodaj">
        <?php endif; ?>
        <label>Nazwa: <input type="text" name="nazwa" maxlength="100" required value="<?= e($pola['nazwa']) ?>"></label>
        <label>Kategoria:
            <select name="kategoria_id" required>
                <option value="">-- wybierz --</option>
                <?php foreach ($kategorie as $k): ?>
                    <option value="<?= (int) $k['id'] ?>"<?= (int) $k['id'] === (int) $pola['kategoria_id'] ? ' selected' : '' ?>><?= e($k['nazwa']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Czas trwania (min): <input type="number" name="czas_trwania" min="5" max="480" step="5" required value="<?= e($pola['czas_trwania']) ?>"></label>
        <label>Cena (zl): <input type="text" name="cena" inputmode="decimal" required value="<?= e($pola['cena']) ?>"></label>
        <label>Opis: <textarea name="opis" maxlength="1000"><?= e($pola['opis']) ?></textarea></label>
        <button type="submit"><?= $edytowana ? 'Zapisz zmiany' : 'Dodaj' ?></button>
    </form>
    <?php if ($edytowana): ?>
        <p><a href="admin_uslugi.php">Anuluj edycje</a></p>
    <?php endif; ?>
<?php endif; ?>
<?php
stopkaAdmina();
