<?php
require __DIR__ . '/../includes/wspolne.php';
require __DIR__ . '/../includes/statusy.php';

chronStrone('klient');

if (postTekst('akcja') === 'anuluj') {
    // warunek: wlasna rezerwacja, jeszcze aktywna i w przyszlosci,
    // wiec podmiana id w formularzu na cudza rezerwacje niczego nie zmieni
    $blad = zmienStatus(
        $pdo,
        liczba($_POST['id'] ?? ''),
        'anulowana',
        "uzytkownik_id = ? AND status IN ('oczekujaca', 'potwierdzona') AND TIMESTAMP(data_rezerwacji, godzina_od) > NOW()",
        [$_SESSION['user_id']]
    );

    ustawKomunikat(
        $blad ? 'blad' : 'sukces',
        $blad ? 'Tej rezerwacji nie mozna anulowac.' : 'Rezerwacja zostala anulowana.'
    );
    przekieruj('panel_klienta.php');
}

// klient widzi wylacznie rezerwacje ze swoim id z sesji
$stmt = $pdo->prepare(
    "SELECT r.id, r.data_rezerwacji, r.godzina_od, r.godzina_do, r.status, r.komentarz,
            u.nazwa AS usluga, u.cena,
            CONCAT(pu.imie, ' ', pu.nazwisko) AS pracownik,
            (r.status IN ('oczekujaca', 'potwierdzona') AND TIMESTAMP(r.data_rezerwacji, r.godzina_od) > NOW()) AS przyszla
     FROM rezerwacje r
     JOIN uslugi u ON u.id = r.usluga_id
     JOIN pracownicy p ON p.id = r.pracownik_id
     JOIN uzytkownicy pu ON pu.id = p.uzytkownik_id
     WHERE r.uzytkownik_id = ?
     ORDER BY r.data_rezerwacji, r.godzina_od"
);
$stmt->execute([$_SESSION['user_id']]);
$rezerwacje = $stmt->fetchAll(PDO::FETCH_ASSOC);

$przyszle = array_filter($rezerwacje, fn ($r) => $r['przyszla']);
$pozostale = array_reverse(array_filter($rezerwacje, fn ($r) => !$r['przyszla']));

naglowekStrony('Panel klienta', [
    'panel_klienta.php' => 'Moje rezerwacje',
    'rezerwacja.php' => 'Nowa rezerwacja',
    'index.php' => 'Oferta',
]);

function tabelaRezerwacji(array $lista, bool $przyszle): void
{
    ?>
    <div class="tabela">
        <table>
            <tr>
                <th>Usluga</th>
                <th>Pracownik</th>
                <th>Data</th>
                <th>Godzina</th>
                <th>Cena</th>
                <th>Status</th>
                <?php if ($przyszle): ?>
                    <th></th>
                <?php endif; ?>
            </tr>
            <?php foreach ($lista as $r): ?>
                <tr>
                    <td><?= e($r['usluga']) ?><?= $r['komentarz'] ? '<br><small>' . e($r['komentarz']) . '</small>' : '' ?></td>
                    <td><?= e($r['pracownik']) ?></td>
                    <td><?= data($r['data_rezerwacji']) ?></td>
                    <td><?= godzina($r['godzina_od']) ?>-<?= godzina($r['godzina_do']) ?></td>
                    <td><?= cena($r['cena']) ?></td>
                    <td><span class="status status-<?= e($r['status']) ?>"><?= e(nazwaStatusu($r['status'])) ?></span></td>
                    <?php if ($przyszle): ?>
                        <td>
                            <form method="post" class="w-linii" data-potwierdz="<?= e('Anulowac rezerwacje ' . $r['usluga'] . ' z ' . data($r['data_rezerwacji']) . '?') ?>">
                                <?= csrfPole() ?>
                                <input type="hidden" name="akcja" value="anuluj">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="maly niebezpieczny">Anuluj</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
}
?>
<p>Witaj, <?= e($_SESSION['user_imie']) ?>. <a class="przycisk" href="rezerwacja.php">Zarezerwuj termin</a></p>

<h2>Przyszle rezerwacje</h2>
<?php if ($przyszle): ?>
    <?php tabelaRezerwacji($przyszle, true); ?>
<?php else: ?>
    <p>Nie masz zaplanowanych wizyt.</p>
<?php endif; ?>

<h2>Zakonczone i anulowane</h2>
<?php if ($pozostale): ?>
    <?php tabelaRezerwacji($pozostale, false); ?>
<?php else: ?>
    <p>Brak wczesniejszych rezerwacji.</p>
<?php endif; ?>
<?php
stopkaStrony();
