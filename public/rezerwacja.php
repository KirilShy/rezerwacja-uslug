<?php
require __DIR__ . '/../includes/wspolne.php';
require __DIR__ . '/../includes/terminy.php';

// kazdy krok bierze wybory z adresu i sprawdza je od nowa na serwerze
$usluga = aktywnaUsluga($pdo, liczba($_GET['usluga'] ?? ''));
$pracownicy = $usluga ? pracownicyUslugi($pdo, (int) $usluga['id']) : [];

$pracownikId = liczba($_GET['pracownik'] ?? '');
$pracownik = null;
foreach ($pracownicy as $p) {
    if ((int) $p['id'] === $pracownikId) {
        $pracownik = $p;
    }
}

$data = $pracownik && is_string($_GET['data'] ?? null) ? poprawnaData($_GET['data']) : null;
if ($data && $data < date('Y-m-d')) {
    $data = null;
}

$wolne = $data ? wolneTerminy($pdo, $pracownikId, $data, (int) $usluga['czas_trwania']) : [];
$godzina = $data && in_array($_GET['godzina'] ?? null, $wolne, true) ? $_GET['godzina'] : null;

$krok = match (true) {
    !$usluga => 1,
    !$pracownik => 2,
    !$data => 3,
    !$godzina => 4,
    default => 5,
};

$bazowyAdres = 'rezerwacja.php?usluga=' . ($usluga['id'] ?? 0) . '&pracownik=' . $pracownikId;

naglowekStrony('Rezerwacja terminu', [
    'index.php' => 'Oferta',
    'rezerwacja.php' => 'Rezerwacja',
] + (zalogowany() ? [adresPanelu($_SESSION['user_rola']) => 'Moj panel'] : []));
?>
<ol class="kroki">
    <?php foreach ([1 => 'Usluga', 2 => 'Pracownik', 3 => 'Data', 4 => 'Godzina', 5 => 'Potwierdzenie'] as $nr => $nazwa): ?>
        <li<?= $nr === $krok ? ' class="aktywny"' : '' ?>><?= $nr ?>. <?= $nazwa ?></li>
    <?php endforeach; ?>
</ol>

<?php if ($usluga): ?>
    <div class="karta">
        <dl>
            <dt>Usluga</dt>
            <dd><?= e($usluga['nazwa']) ?> (<?= (int) $usluga['czas_trwania'] ?> min, <?= cena($usluga['cena']) ?>) <a href="rezerwacja.php">zmien</a></dd>
            <?php if ($pracownik): ?>
                <dt>Pracownik</dt>
                <dd><?= e($pracownik['nazwa']) ?> <a href="rezerwacja.php?usluga=<?= (int) $usluga['id'] ?>">zmien</a></dd>
            <?php endif; ?>
            <?php if ($data): ?>
                <dt>Data</dt>
                <dd><?= data($data) ?>, <?= DNI_TYGODNIA[(int) date('N', strtotime($data))] ?> <a href="<?= e($bazowyAdres) ?>">zmien</a></dd>
            <?php endif; ?>
            <?php if ($godzina): ?>
                <dt>Godzina</dt>
                <dd><?= e($godzina) ?>-<?= czasZMinut(minuty($godzina) + (int) $usluga['czas_trwania']) ?> <a href="<?= e($bazowyAdres . '&data=' . $data) ?>">zmien</a></dd>
            <?php endif; ?>
        </dl>
    </div>
<?php endif; ?>

<?php if ($krok === 1): ?>
    <h2>Wybierz usluge</h2>
    <?php $uslugi = $pdo->query(
        'SELECT u.id, u.nazwa, u.czas_trwania, u.cena, k.nazwa AS kategoria
         FROM uslugi u JOIN kategorie_uslug k ON k.id = u.kategoria_id
         WHERE u.aktywna = 1 ORDER BY k.nazwa, u.nazwa'
    )->fetchAll(); ?>
    <div class="tabela">
        <table>
            <tr><th>Usluga</th><th>Kategoria</th><th>Czas</th><th>Cena</th><th></th></tr>
            <?php foreach ($uslugi as $u): ?>
                <tr>
                    <td><a href="usluga.php?id=<?= (int) $u['id'] ?>"><?= e($u['nazwa']) ?></a></td>
                    <td><?= e($u['kategoria']) ?></td>
                    <td><?= (int) $u['czas_trwania'] ?> min</td>
                    <td><?= cena($u['cena']) ?></td>
                    <td><a class="przycisk" href="rezerwacja.php?usluga=<?= (int) $u['id'] ?>">Wybierz</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

<?php elseif ($krok === 2): ?>
    <h2>Wybierz pracownika</h2>
    <?php if (!$pracownicy): ?>
        <p>Obecnie zaden pracownik nie wykonuje tej uslugi. Wybierz <a href="rezerwacja.php">inna usluge</a>.</p>
    <?php else: ?>
        <div class="wybor">
            <?php foreach ($pracownicy as $p): ?>
                <a href="rezerwacja.php?usluga=<?= (int) $usluga['id'] ?>&amp;pracownik=<?= (int) $p['id'] ?>"><?= e($p['nazwa']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($krok === 3): ?>
    <h2>Wybierz date</h2>
    <p>Najblizsze dni pracy pracownika. Liczba w nawiasie to wolne godziny.</p>
    <div class="wybor">
        <?php for ($i = 0; $i < 21; $i++): ?>
            <?php
            $dzien = date('Y-m-d', strtotime("+$i days"));
            if (!godzinyPracy($pdo, $pracownikId, $dzien)) {
                continue;
            }
            $ile = count(wolneTerminy($pdo, $pracownikId, $dzien, (int) $usluga['czas_trwania']));
            ?>
            <?php if ($ile): ?>
                <a href="<?= e($bazowyAdres . '&data=' . $dzien) ?>"><?= data($dzien) ?> (<?= $ile ?>)</a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <form method="get" class="filtry">
        <input type="hidden" name="usluga" value="<?= (int) $usluga['id'] ?>">
        <input type="hidden" name="pracownik" value="<?= $pracownikId ?>">
        <label>Inna data: <input type="date" name="data" min="<?= date('Y-m-d') ?>" required></label>
        <button type="submit">Pokaz godziny</button>
    </form>

<?php elseif ($krok === 4): ?>
    <h2>Wybierz godzine</h2>
    <?php if (!$wolne): ?>
        <p>Brak wolnych terminow w tym dniu (pracownik nie pracuje albo wszystko jest zajete). <a href="<?= e($bazowyAdres) ?>">Wybierz inna date</a>.</p>
    <?php else: ?>
        <div class="wybor">
            <?php foreach ($wolne as $g): ?>
                <a href="<?= e($bazowyAdres . '&data=' . $data . '&godzina=' . $g) ?>"><?= $g ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <h2>Potwierdzenie</h2>
    <?php if (($_SESSION['user_rola'] ?? '') === 'klient'): ?>
        <form method="post" action="zapisz_rezerwacje.php" class="edycja">
            <?= csrfPole() ?>
            <input type="hidden" name="usluga" value="<?= (int) $usluga['id'] ?>">
            <input type="hidden" name="pracownik" value="<?= $pracownikId ?>">
            <input type="hidden" name="data" value="<?= e($data) ?>">
            <input type="hidden" name="godzina" value="<?= e($godzina) ?>">
            <label>Komentarz (opcjonalnie): <textarea name="komentarz" maxlength="500" placeholder="np. opis usterki"></textarea></label>
            <button type="submit">Potwierdzam rezerwacje</button>
        </form>
    <?php elseif (zalogowany()): ?>
        <p>Rezerwacje moze zlozyc tylko konto klienta.</p>
    <?php else: ?>
        <p>Aby potwierdzic rezerwacje, <a href="logowanie.php">zaloguj sie</a> albo <a href="rejestracja.php">zaloz konto</a>.</p>
    <?php endif; ?>
<?php endif; ?>
<?php
stopkaStrony();
