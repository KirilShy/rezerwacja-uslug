<?php
require __DIR__ . '/../includes/wspolne.php';
require __DIR__ . '/../includes/statusy.php';

wymagajLogowania();

$rola = $_SESSION['user_rola'];

if ($rola !== 'pracownik' && $rola !== 'administrator') {
    http_response_code(403);
    exit('Brak dostepu do tej strony.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sprawdzCsrf();
}

$id = liczba($_GET['id'] ?? '');

// pracownik moze otworzyc tylko swoja wizyte, administrator kazda
$warunek = '1';
$parametry = [];

if ($rola === 'pracownik') {
    $warunek = 'pracownik_id = (SELECT id FROM pracownicy WHERE uzytkownik_id = ?)';
    $parametry = [$_SESSION['user_id']];
}

if (postTekst('akcja') === 'status') {
    $blad = zmienStatus($pdo, $id, postTekst('status'), $warunek, $parametry);

    ustawKomunikat($blad ? 'blad' : 'sukces', $blad ?? 'Zmieniono status wizyty.');
    przekieruj('wizyta.php?id=' . $id);
}

$stmt = $pdo->prepare(
    "SELECT r.*, u.nazwa AS usluga, u.czas_trwania, u.cena, k.nazwa AS kategoria,
            CONCAT(kl.imie, ' ', kl.nazwisko) AS klient, kl.email, kl.telefon,
            CONCAT(pu.imie, ' ', pu.nazwisko) AS pracownik
     FROM rezerwacje r
     JOIN uslugi u ON u.id = r.usluga_id
     JOIN kategorie_uslug k ON k.id = u.kategoria_id
     JOIN uzytkownicy kl ON kl.id = r.uzytkownik_id
     JOIN pracownicy p ON p.id = r.pracownik_id
     JOIN uzytkownicy pu ON pu.id = p.uzytkownik_id
     WHERE r.id = ? AND ($warunek)"
);
$stmt->execute(array_merge([$id], $parametry));
$w = $stmt->fetch(PDO::FETCH_ASSOC);

$linki = $rola === 'administrator'
    ? linkiAdmina()
    : ['panel_pracownika.php' => 'Moje wizyty'];

if (!$w) {
    http_response_code(404);
    naglowekStrony('Nie znaleziono wizyty', $linki);
    echo '<p>Ta wizyta nie istnieje albo nie masz do niej dostepu.</p>';
    stopkaStrony();
    exit;
}

naglowekStrony('Wizyta #' . $w['id'], $linki);
?>
<div class="karta">
    <dl>
        <dt>Status</dt>
        <dd><span class="status status-<?= e($w['status']) ?>"><?= e(nazwaStatusu($w['status'])) ?></span></dd>
        <dt>Termin</dt>
        <dd><?= data($w['data_rezerwacji']) ?>, <?= godzina($w['godzina_od']) ?>-<?= godzina($w['godzina_do']) ?></dd>
        <dt>Usluga</dt>
        <dd><?= e($w['usluga']) ?> (<?= e($w['kategoria']) ?>), <?= (int) $w['czas_trwania'] ?> min, <?= cena($w['cena']) ?></dd>
        <dt>Pracownik</dt>
        <dd><?= e($w['pracownik']) ?></dd>
        <dt>Klient</dt>
        <dd><?= e($w['klient']) ?></dd>
        <dt>Email</dt>
        <dd><?= e($w['email']) ?></dd>
        <dt>Telefon</dt>
        <dd><?= e($w['telefon']) ?: '-' ?></dd>
        <dt>Komentarz</dt>
        <dd><?= e($w['komentarz']) ?: '-' ?></dd>
        <dt>Utworzono</dt>
        <dd><?= e($w['data_utworzenia']) ?></dd>
    </dl>
</div>

<h2>Zmien status</h2>
<form method="post" class="edycja">
    <?= csrfPole() ?>
    <input type="hidden" name="akcja" value="status">
    <label>Nowy status:
        <select name="status">
            <?php foreach (STATUSY_REZERWACJI as $kod => $nazwa): ?>
                <option value="<?= e($kod) ?>"<?= $kod === $w['status'] ? ' selected' : '' ?>><?= e($nazwa) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Zapisz status</button>
</form>

<h2>Historia statusow</h2>
<?php $historia = historiaStatusow($pdo, (int) $w['id']); ?>
<?php if (!$historia): ?>
    <p>Brak zapisanych zmian.</p>
<?php else: ?>
    <div class="tabela">
        <table>
            <tr><th>Kiedy</th><th>Zmiana</th><th>Kto</th></tr>
            <?php foreach ($historia as $h): ?>
                <tr>
                    <td><?= e($h['data_zmiany']) ?></td>
                    <td><?= $h['status_przed'] ? e(nazwaStatusu($h['status_przed'])) . ' &rarr; ' : 'Utworzono: ' ?><?= e(nazwaStatusu($h['status_po'])) ?></td>
                    <td><?= e($h['kto'] ?? '-') ?><?= $h['rola'] ? ' (' . e($h['rola']) . ')' : '' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>
<?php
stopkaStrony();
