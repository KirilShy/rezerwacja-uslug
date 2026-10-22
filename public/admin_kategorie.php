<?php
require __DIR__ . '/../includes/admin.php';

$blad = '';
$akcja = $_SERVER['REQUEST_METHOD'] === 'POST' ? postTekst('akcja') : '';

// edytowana kategoria: z adresu (?edytuj=3) albo z wyslanego formularza edycji
$edytowanaId = $akcja === 'zapisz' ? liczba($_POST['id'] ?? '') : liczba($_GET['edytuj'] ?? '');
$edytowana = null;

if ($edytowanaId > 0) {
    $stmt = $pdo->prepare('SELECT id, nazwa, opis FROM kategorie_uslug WHERE id = ?');
    $stmt->execute([$edytowanaId]);
    $edytowana = $stmt->fetch() ?: null;

    if (!$edytowana) {
        ustawKomunikat('blad', 'Nie znaleziono kategorii.');
        przekieruj('admin_kategorie.php');
    }
}

if ($akcja === 'dodaj' || $akcja === 'zapisz') {
    $nazwa = postTekst('nazwa');
    $opis = postTekst('opis');

    // przy edycji nazwa moze zostac taka sama jak dotychczas
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM kategorie_uslug WHERE nazwa = ? AND id <> ?');
    $stmt->execute([$nazwa, $edytowanaId]);

    if ($nazwa === '') {
        $blad = 'Podaj nazwe kategorii.';
    } elseif (mb_strlen($nazwa) > 100) {
        $blad = 'Nazwa moze miec maksymalnie 100 znakow.';
    } elseif (mb_strlen($opis) > 1000) {
        $blad = 'Opis moze miec maksymalnie 1000 znakow.';
    } elseif ($stmt->fetchColumn() > 0) {
        $blad = 'Kategoria o takiej nazwie juz istnieje.';
    } elseif ($akcja === 'zapisz') {
        $stmt = $pdo->prepare('UPDATE kategorie_uslug SET nazwa = ?, opis = ? WHERE id = ?');
        $stmt->execute([$nazwa, $opis, $edytowanaId]);

        ustawKomunikat('sukces', 'Zapisano zmiany w kategorii.');
        przekieruj('admin_kategorie.php');
    } else {
        $stmt = $pdo->prepare('INSERT INTO kategorie_uslug (nazwa, opis) VALUES (?, ?)');
        $stmt->execute([$nazwa, $opis]);

        ustawKomunikat('sukces', 'Dodano kategorie.');
        przekieruj('admin_kategorie.php');
    }
}

$kategorie = $pdo->query(
    'SELECT k.id, k.nazwa, k.opis, COUNT(u.id) AS liczba_uslug
     FROM kategorie_uslug k
     LEFT JOIN uslugi u ON u.kategoria_id = k.id
     GROUP BY k.id, k.nazwa, k.opis
     ORDER BY k.nazwa'
)->fetchAll();

naglowekAdmina('Kategorie uslug');
?>
<?php if ($blad): ?>
    <p class="blad" role="alert"><?= e($blad) ?></p>
<?php endif; ?>

<div class="tabela">
    <table>
        <tr>
            <th>Nazwa</th>
            <th>Opis</th>
            <th>Uslugi</th>
            <th>Akcje</th>
        </tr>
        <?php foreach ($kategorie as $k): ?>
            <tr>
                <td><?= e($k['nazwa']) ?></td>
                <td><?= e($k['opis']) ?></td>
                <td><?= (int) $k['liczba_uslug'] ?></td>
                <td class="akcje">
                    <a class="przycisk" href="admin_kategorie.php?edytuj=<?= (int) $k['id'] ?>">Edytuj</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php
// po bledzie pokazujemy to, co admin wpisal; inaczej dane z bazy (edycja) albo puste pola (dodawanie)
$pola = $blad ? ['nazwa' => postTekst('nazwa'), 'opis' => postTekst('opis')] : ($edytowana ?? ['nazwa' => '', 'opis' => '']);
?>
<h2><?= $edytowana ? 'Edytuj kategorie' : 'Dodaj kategorie' ?></h2>
<form method="post" class="edycja">
    <?= csrfPole() ?>
    <?php if ($edytowana): ?>
        <input type="hidden" name="akcja" value="zapisz">
        <input type="hidden" name="id" value="<?= (int) $edytowana['id'] ?>">
    <?php else: ?>
        <input type="hidden" name="akcja" value="dodaj">
    <?php endif; ?>
    <label>Nazwa: <input type="text" name="nazwa" maxlength="100" required value="<?= e($pola['nazwa']) ?>"></label>
    <label>Opis: <textarea name="opis" maxlength="1000"><?= e($pola['opis']) ?></textarea></label>
    <button type="submit"><?= $edytowana ? 'Zapisz zmiany' : 'Dodaj' ?></button>
</form>
<?php if ($edytowana): ?>
    <p><a href="admin_kategorie.php">Anuluj edycje</a></p>
<?php endif; ?>
<?php
stopkaAdmina();
