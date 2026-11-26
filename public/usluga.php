<?php
require __DIR__ . '/../includes/wspolne.php';
require __DIR__ . '/../includes/terminy.php';

$usluga = aktywnaUsluga($pdo, liczba($_GET['id'] ?? ''));

if (!$usluga) {
    http_response_code(404);
    naglowekStrony('Nie znaleziono uslugi', menuGlowne());
    echo '<p>Ta usluga nie istnieje albo nie jest juz dostepna.</p>';
    stopkaStrony();
    exit;
}

$pracownicy = pracownicyUslugi($pdo, (int) $usluga['id']);

naglowekStrony($usluga['nazwa'], menuGlowne());
?>
<div class="karta">
    <dl>
        <dt>Kategoria</dt>
        <dd><?= e($usluga['kategoria']) ?></dd>
        <dt>Cena</dt>
        <dd><?= cena($usluga['cena']) ?></dd>
        <dt>Czas trwania</dt>
        <dd><?= (int) $usluga['czas_trwania'] ?> min</dd>
        <dt>Opis</dt>
        <dd><?= e($usluga['opis']) ?: '-' ?></dd>
    </dl>
</div>

<h2>Dostepnosc</h2>

<?php if (!$pracownicy): ?>
    <p>Obecnie zaden pracownik nie wykonuje tej uslugi, wiec nie mozna jej zarezerwowac.</p>
<?php else: ?>
    <div class="tabela">
        <table>
            <tr>
                <th>Pracownik</th>
                <th>Dni i godziny pracy</th>
            </tr>
            <?php foreach ($pracownicy as $p): ?>
                <tr>
                    <td><?= e($p['nazwa']) ?></td>
                    <td>
                        <?php foreach (dniPracy($pdo, (int) $p['id']) as $d): ?>
                            <?= DNI_TYGODNIA[$d['dzien_tygodnia']] ?> <?= godzina($d['godzina_od']) ?>-<?= godzina($d['godzina_do']) ?><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <p><a class="przycisk" href="rezerwacja.php?usluga=<?= (int) $usluga['id'] ?>">Zarezerwuj termin</a></p>
<?php endif; ?>
<?php
stopkaStrony();
