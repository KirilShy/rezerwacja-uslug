<?php
require __DIR__ . '/../includes/admin.php';

$wiersz = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM uzytkownicy WHERE rola = 'klient')                 AS klienci,
        (SELECT COUNT(*) FROM pracownicy)                                        AS pracownicy,
        (SELECT COUNT(*) FROM uslugi)                                            AS uslugi,
        (SELECT COUNT(*) FROM rezerwacje)                                        AS rezerwacje_wszystkie,
        (SELECT COUNT(*) FROM rezerwacje WHERE data_rezerwacji = CURDATE())      AS rezerwacje_dzis"
)->fetch(PDO::FETCH_ASSOC);

$etykiety = [
    'klienci'              => 'Klienci',
    'pracownicy'           => 'Pracownicy',
    'uslugi'               => 'Usługi',
    'rezerwacje_wszystkie' => 'Wszystkie rezerwacje',
    'rezerwacje_dzis'      => 'Rezerwacje na dziś',
];

$dzisiejsze = $pdo->query(
    "SELECT TIME_FORMAT(r.godzina_od, '%H:%i') AS godzina_od,
            TIME_FORMAT(r.godzina_do, '%H:%i') AS godzina_do,
            r.status,
            CONCAT(k.imie, ' ', k.nazwisko) AS klient,
            CONCAT(p.imie, ' ', p.nazwisko) AS pracownik,
            u.nazwa AS usluga
     FROM rezerwacje r
     JOIN uzytkownicy k  ON k.id  = r.uzytkownik_id
     JOIN pracownicy pr  ON pr.id = r.pracownik_id
     JOIN uzytkownicy p  ON p.id  = pr.uzytkownik_id
     JOIN uslugi u       ON u.id  = r.usluga_id
     WHERE r.data_rezerwacji = CURDATE()
     ORDER BY r.godzina_od"
)->fetchAll(PDO::FETCH_ASSOC);

// statystyki: rezerwacje wg statusu, przychod, popularne uslugi i oblozenie pracownikow
$wgStatusu = $pdo->query('SELECT status, COUNT(*) AS ile FROM rezerwacje GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);

$przychod = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN r.data_rezerwacji >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN u.cena END), 0) AS miesiac,
        COALESCE(SUM(u.cena), 0) AS razem
     FROM rezerwacje r JOIN uslugi u ON u.id = r.usluga_id
     WHERE r.status = 'zrealizowana'"
)->fetch(PDO::FETCH_ASSOC);

$popularne = $pdo->query(
    "SELECT u.nazwa, COUNT(*) AS ile
     FROM rezerwacje r JOIN uslugi u ON u.id = r.usluga_id
     WHERE r.status <> 'anulowana'
     GROUP BY u.id, u.nazwa
     ORDER BY ile DESC, u.nazwa
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$oblozenie = $pdo->query(
    "SELECT CONCAT(u.imie, ' ', u.nazwisko) AS nazwa, COUNT(r.id) AS ile
     FROM pracownicy p
     JOIN uzytkownicy u ON u.id = p.uzytkownik_id
     LEFT JOIN rezerwacje r ON r.pracownik_id = p.id
        AND r.status IN ('oczekujaca', 'potwierdzona')
        AND r.data_rezerwacji BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY
     WHERE p.aktywny = 1
     GROUP BY p.id, u.imie, u.nazwisko
     ORDER BY ile DESC, u.nazwisko"
)->fetchAll(PDO::FETCH_ASSOC);

// pasek wykresu: szerokosc w procentach wzgledem najwiekszej wartosci
function wykres(array $wartosci): void
{
    $max = max(array_merge([1], array_values($wartosci)));
    ?>
    <div class="wykres">
        <?php foreach ($wartosci as $etykieta => $ile): ?>
            <div class="wykres-wiersz">
                <span><?= e((string) $etykieta) ?></span>
                <div class="wykres-tlo"><div class="wykres-pasek" style="width: <?= round($ile / $max * 100) ?>%"></div></div>
                <strong><?= (int) $ile ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

naglowekAdmina('Panel administratora');
?>
<div class="liczniki">
    <?php foreach ($etykiety as $klucz => $nazwa): ?>
        <div class="licznik">
            <strong><?= (int) $wiersz[$klucz] ?></strong>
            <span><?= e($nazwa) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<h2>Rezerwacje na dziś</h2>

<?php if (!$dzisiejsze): ?>
    <p>Brak rezerwacji na dziś.</p>
<?php else: ?>
    <div class="tabela">
        <table>
            <thead>
                <tr>
                    <th>Godzina</th>
                    <th>Klient</th>
                    <th>Usługa</th>
                    <th>Pracownik</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dzisiejsze as $r): ?>
                    <tr>
                        <td><?= e($r['godzina_od']) ?>-<?= e($r['godzina_do']) ?></td>
                        <td><?= e($r['klient']) ?></td>
                        <td><?= e($r['usluga']) ?></td>
                        <td><?= e($r['pracownik']) ?></td>
                        <td><span class="status status-<?= e($r['status']) ?>"><?= e(nazwaStatusu($r['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<h2>Statystyki</h2>

<div class="liczniki">
    <div class="licznik">
        <strong><?= cena($przychod['miesiac']) ?></strong>
        <span>Przychod w tym miesiacu (zrealizowane)</span>
    </div>
    <div class="licznik">
        <strong><?= cena($przychod['razem']) ?></strong>
        <span>Przychod lacznie (zrealizowane)</span>
    </div>
</div>

<div class="statystyki">
    <section class="karta">
        <h3>Rezerwacje wedlug statusu</h3>
        <?php
        $statusy = [];
        foreach (STATUSY_REZERWACJI as $kod => $nazwa) {
            $statusy[$nazwa] = (int) ($wgStatusu[$kod] ?? 0);
        }
        wykres($statusy);
        ?>
    </section>
    <section class="karta">
        <h3>Najpopularniejsze uslugi</h3>
        <?php wykres(array_column($popularne, 'ile', 'nazwa')); ?>
    </section>
    <section class="karta">
        <h3>Wizyty pracownikow w najblizszych 7 dniach</h3>
        <?php wykres(array_column($oblozenie, 'ile', 'nazwa')); ?>
    </section>
</div>
<?php
stopkaAdmina();