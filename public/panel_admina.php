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
<?php
stopkaAdmina();