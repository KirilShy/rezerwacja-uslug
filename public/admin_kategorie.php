<?php
require __DIR__ . '/../includes/admin.php';

$blad = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && postTekst('akcja') === 'dodaj') {
    $nazwa = postTekst('nazwa');
    $opis = postTekst('opis');

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM kategorie_uslug WHERE nazwa = ?');
    $stmt->execute([$nazwa]);

    if ($nazwa === '') {
        $blad = 'Podaj nazwe kategorii.';
    } elseif (mb_strlen($nazwa) > 100) {
        $blad = 'Nazwa moze miec maksymalnie 100 znakow.';
    } elseif (mb_strlen($opis) > 1000) {
        $blad = 'Opis moze miec maksymalnie 1000 znakow.';
    } elseif ($stmt->fetchColumn() > 0) {
        $blad = 'Kategoria o takiej nazwie juz istnieje.';
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
        </tr>
        <?php foreach ($kategorie as $k): ?>
            <tr>
                <td><?= e($k['nazwa']) ?></td>
                <td><?= e($k['opis']) ?></td>
                <td><?= (int) $k['liczba_uslug'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<h2>Dodaj kategorie</h2>
<form method="post" class="edycja">
    <?= csrfPole() ?>
    <input type="hidden" name="akcja" value="dodaj">
    <label>Nazwa: <input type="text" name="nazwa" maxlength="100" required value="<?= e(postTekst('nazwa')) ?>"></label>
    <label>Opis: <textarea name="opis" maxlength="1000"><?= e(postTekst('opis')) ?></textarea></label>
    <button type="submit">Dodaj</button>
</form>
<?php
stopkaAdmina();
