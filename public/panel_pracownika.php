<?php
require __DIR__ . '/../includes/wspolne.php';

chronStrone('pracownik');

$stmt = $pdo->prepare('SELECT id FROM pracownicy WHERE uzytkownik_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$pracownikId = (int) $stmt->fetchColumn();

// pracownik widzi wylacznie wizyty przypisane do siebie
function wizyty(PDO $pdo, int $pracownikId, string $warunek, string $kolejnosc): array
{
    $stmt = $pdo->prepare(
        "SELECT r.id, r.data_rezerwacji, r.godzina_od, r.godzina_do, r.status,
                CONCAT(k.imie, ' ', k.nazwisko) AS klient, k.telefon, u.nazwa AS usluga
         FROM rezerwacje r
         JOIN uzytkownicy k ON k.id = r.uzytkownik_id
         JOIN uslugi u ON u.id = r.usluga_id
         WHERE r.pracownik_id = ? AND $warunek
         ORDER BY $kolejnosc
         LIMIT 100"
    );
    $stmt->execute([$pracownikId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$sekcje = [
    'Wizyty na dzis' => wizyty($pdo, $pracownikId, 'r.data_rezerwacji = CURDATE()', 'r.godzina_od'),
    'Przyszle wizyty' => wizyty($pdo, $pracownikId, 'r.data_rezerwacji > CURDATE()', 'r.data_rezerwacji, r.godzina_od'),
    'Historia' => wizyty($pdo, $pracownikId, 'r.data_rezerwacji < CURDATE()', 'r.data_rezerwacji DESC, r.godzina_od DESC'),
];

naglowekStrony('Panel pracownika', ['panel_pracownika.php' => 'Moje wizyty']);
?>
<p>Witaj, <?= e($_SESSION['user_imie']) ?>.</p>

<?php if (!$pracownikId): ?>
    <p class="blad">To konto nie jest jeszcze przypisane do pracownika. Skontaktuj sie z administratorem.</p>
<?php endif; ?>

<?php foreach ($sekcje as $tytul => $lista): ?>
    <h2><?= e($tytul) ?></h2>
    <?php if (!$lista): ?>
        <p>Brak wizyt.</p>
    <?php else: ?>
        <div class="tabela">
            <table>
                <tr>
                    <th>Data</th>
                    <th>Godzina</th>
                    <th>Klient</th>
                    <th>Telefon</th>
                    <th>Usluga</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                <?php foreach ($lista as $w): ?>
                    <tr>
                        <td><?= data($w['data_rezerwacji']) ?></td>
                        <td><?= godzina($w['godzina_od']) ?>-<?= godzina($w['godzina_do']) ?></td>
                        <td><?= e($w['klient']) ?></td>
                        <td><?= e($w['telefon']) ?></td>
                        <td><?= e($w['usluga']) ?></td>
                        <td><span class="status status-<?= e($w['status']) ?>"><?= e(nazwaStatusu($w['status'])) ?></span></td>
                        <td><a class="przycisk" href="wizyta.php?id=<?= (int) $w['id'] ?>">Szczegoly</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
<?php
stopkaStrony();
