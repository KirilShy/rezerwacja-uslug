<?php
require __DIR__ . '/../includes/admin.php';

$liczniki = [
    'Klienci' => "SELECT COUNT(*) FROM uzytkownicy WHERE rola = 'klient'",
    'Pracownicy' => 'SELECT COUNT(*) FROM pracownicy',
    'Uslugi' => 'SELECT COUNT(*) FROM uslugi',
    'Wszystkie rezerwacje' => 'SELECT COUNT(*) FROM rezerwacje',
    'Rezerwacje na dzis' => 'SELECT COUNT(*) FROM rezerwacje WHERE data_rezerwacji = CURDATE()',
];

foreach ($liczniki as $nazwa => $sql) {
    $liczniki[$nazwa] = (int) $pdo->query($sql)->fetchColumn();
}

$dzisiejsze = $pdo->query(
    "SELECT r.godzina_od, r.godzina_do, r.status,
            CONCAT(k.imie, ' ', k.nazwisko) AS klient,
            CONCAT(p.imie, ' ', p.nazwisko) AS pracownik,
            u.nazwa AS usluga
     FROM rezerwacje r
     JOIN uzytkownicy k ON k.id = r.uzytkownik_id
     JOIN pracownicy pr ON pr.id = r.pracownik_id
     JOIN uzytkownicy p ON p.id = pr.uzytkownik_id
     JOIN uslugi u ON u.id = r.usluga_id
     WHERE r.data_rezerwacji = CURDATE()
     ORDER BY r.godzina_od"
)->fetchAll();

naglowekAdmina('Panel administratora');
?>
<div class="liczniki">
    <?php foreach ($liczniki as $nazwa => $liczba): ?>
        <div class="licznik">
            <strong><?= $liczba ?></strong>
            <span><?= e($nazwa) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<h2>Rezerwacje na dzis</h2>

<?php if (!$dzisiejsze): ?>
    <p>Brak rezerwacji na dzis.</p>
<?php else: ?>
    <div class="tabela">
        <table>
            <tr>
                <th>Godzina</th>
                <th>Klient</th>
                <th>Usluga</th>
                <th>Pracownik</th>
                <th>Status</th>
            </tr>
            <?php foreach ($dzisiejsze as $r): ?>
                <tr>
                    <td><?= substr($r['godzina_od'], 0, 5) ?>-<?= substr($r['godzina_do'], 0, 5) ?></td>
                    <td><?= e($r['klient']) ?></td>
                    <td><?= e($r['usluga']) ?></td>
                    <td><?= e($r['pracownik']) ?></td>
                    <td><span class="status"><?= e(nazwaStatusu($r['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>
<?php
stopkaAdmina();
