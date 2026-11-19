<?php
require __DIR__ . '/../includes/admin.php';
require __DIR__ . '/../includes/terminy.php';

const NA_STRONIE = 20;

// parametr z adresu jako tekst; tablica lub brak daje pusty tekst
$get = fn (string $klucz): string => is_string($_GET[$klucz] ?? null) ? trim($_GET[$klucz]) : '';

$filtr = [
    'data_od' => poprawnaData($get('data_od')) ?? '',
    'data_do' => poprawnaData($get('data_do')) ?? '',
    'status' => isset(STATUSY_REZERWACJI[$get('status')]) ? $get('status') : '',
    'pracownik' => liczba($get('pracownik')),
    'szukaj' => mb_substr($get('szukaj'), 0, 100),
];

// warunki skladamy z gotowych fragmentow SQL, a wartosci z formularza ida tylko jako parametry
$warunki = ['1'];
$parametry = [];

if ($filtr['data_od']) {
    $warunki[] = 'r.data_rezerwacji >= ?';
    $parametry[] = $filtr['data_od'];
}
if ($filtr['data_do']) {
    $warunki[] = 'r.data_rezerwacji <= ?';
    $parametry[] = $filtr['data_do'];
}
if ($filtr['status']) {
    $warunki[] = 'r.status = ?';
    $parametry[] = $filtr['status'];
}
if ($filtr['pracownik']) {
    $warunki[] = 'r.pracownik_id = ?';
    $parametry[] = $filtr['pracownik'];
}
if ($filtr['szukaj'] !== '') {
    $warunki[] = "(CONCAT(kl.imie, ' ', kl.nazwisko) LIKE ? OR kl.email LIKE ?)";
    $parametry[] = '%' . $filtr['szukaj'] . '%';
    $parametry[] = '%' . $filtr['szukaj'] . '%';
}

$zrodlo = 'FROM rezerwacje r
     JOIN uzytkownicy kl ON kl.id = r.uzytkownik_id
     JOIN pracownicy p ON p.id = r.pracownik_id
     JOIN uzytkownicy pu ON pu.id = p.uzytkownik_id
     JOIN uslugi u ON u.id = r.usluga_id
     WHERE ' . implode(' AND ', $warunki);

$stmt = $pdo->prepare("SELECT COUNT(*) $zrodlo");
$stmt->execute($parametry);
$wszystkich = (int) $stmt->fetchColumn();

$stron = max(1, (int) ceil($wszystkich / NA_STRONIE));
$strona = min(max(1, liczba($_GET['strona'] ?? '1')), $stron);

$stmt = $pdo->prepare(
    "SELECT r.id, r.data_rezerwacji, r.godzina_od, r.godzina_do, r.status,
            CONCAT(kl.imie, ' ', kl.nazwisko) AS klient,
            CONCAT(pu.imie, ' ', pu.nazwisko) AS pracownik,
            u.nazwa AS usluga, u.cena
     $zrodlo
     ORDER BY r.data_rezerwacji DESC, r.godzina_od DESC
     LIMIT " . NA_STRONIE . ' OFFSET ' . (($strona - 1) * NA_STRONIE)
);
$stmt->execute($parametry);
$rezerwacje = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pracownicy = $pdo->query(
    "SELECT p.id, CONCAT(u.imie, ' ', u.nazwisko) AS nazwa
     FROM pracownicy p JOIN uzytkownicy u ON u.id = p.uzytkownik_id
     ORDER BY u.nazwisko"
)->fetchAll(PDO::FETCH_ASSOC);

// adres z biezacymi filtrami, uzywany w linkach stronicowania
function adresFiltra(array $filtr, array $zmiany = []): string
{
    return 'admin_rezerwacje.php?' . http_build_query(array_filter($zmiany + $filtr));
}

naglowekAdmina('Rezerwacje');
?>
<form method="get" class="filtry">
    <label>Od: <input type="date" name="data_od" value="<?= e($filtr['data_od']) ?>"></label>
    <label>Do: <input type="date" name="data_do" value="<?= e($filtr['data_do']) ?>"></label>
    <label>Status:
        <select name="status">
            <option value="">wszystkie</option>
            <?php foreach (STATUSY_REZERWACJI as $kod => $nazwa): ?>
                <option value="<?= e($kod) ?>"<?= $kod === $filtr['status'] ? ' selected' : '' ?>><?= e($nazwa) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Pracownik:
        <select name="pracownik">
            <option value="">wszyscy</option>
            <?php foreach ($pracownicy as $p): ?>
                <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === $filtr['pracownik'] ? ' selected' : '' ?>><?= e($p['nazwa']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Klient: <input type="search" name="szukaj" placeholder="imie, nazwisko, email" value="<?= e($filtr['szukaj']) ?>"></label>
    <button type="submit">Filtruj</button>
</form>

<p>Znaleziono: <?= $wszystkich ?>. <a href="admin_rezerwacje.php">Wyczysc filtry</a></p>

<?php if ($rezerwacje): ?>
    <div class="tabela">
        <table>
            <tr>
                <th>Data</th>
                <th>Godzina</th>
                <th>Klient</th>
                <th>Usluga</th>
                <th>Pracownik</th>
                <th>Cena</th>
                <th>Status</th>
                <th></th>
            </tr>
            <?php foreach ($rezerwacje as $r): ?>
                <tr>
                    <td><?= data($r['data_rezerwacji']) ?></td>
                    <td><?= godzina($r['godzina_od']) ?>-<?= godzina($r['godzina_do']) ?></td>
                    <td><?= e($r['klient']) ?></td>
                    <td><?= e($r['usluga']) ?></td>
                    <td><?= e($r['pracownik']) ?></td>
                    <td><?= cena($r['cena']) ?></td>
                    <td><span class="status status-<?= e($r['status']) ?>"><?= e(nazwaStatusu($r['status'])) ?></span></td>
                    <td><a class="przycisk" href="wizyta.php?id=<?= (int) $r['id'] ?>">Szczegoly</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>

<?php if ($stron > 1): ?>
    <p class="stronicowanie">
        <?php for ($i = 1; $i <= $stron; $i++): ?>
            <?php if ($i === $strona): ?>
                <strong><?= $i ?></strong>
            <?php else: ?>
                <a href="<?= e(adresFiltra($filtr, ['strona' => $i])) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </p>
<?php endif; ?>
<?php
stopkaAdmina();
