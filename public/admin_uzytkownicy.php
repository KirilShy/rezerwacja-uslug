<?php
require __DIR__ . '/../includes/admin.php';

const NA_STRONIE = 20;

if (postTekst('akcja') === 'przelacz') {
    $id = liczba($_POST['id'] ?? '');

    // admin nie moze zablokowac sam siebie, bo stracilby dostep do panelu
    if ($id === (int) $_SESSION['user_id']) {
        ustawKomunikat('blad', 'Nie mozesz zablokowac wlasnego konta.');
    } else {
        $stmt = $pdo->prepare('UPDATE uzytkownicy SET aktywny = NOT aktywny WHERE id = ?');
        $stmt->execute([$id]);

        ustawKomunikat(
            $stmt->rowCount() ? 'sukces' : 'blad',
            $stmt->rowCount() ? 'Zmieniono status konta.' : 'Nie znaleziono uzytkownika.'
        );
    }

    przekieruj('admin_uzytkownicy.php?' . http_build_query(array_filter([
        'szukaj' => postTekst('szukaj'),
        'rola' => postTekst('rola'),
        'strona' => liczba($_POST['strona'] ?? ''),
    ])));
}

$get = fn (string $klucz): string => is_string($_GET[$klucz] ?? null) ? trim($_GET[$klucz]) : '';
$role = ['klient' => 'Klient', 'pracownik' => 'Pracownik', 'administrator' => 'Administrator'];

$szukaj = mb_substr($get('szukaj'), 0, 100);
$rola = isset($role[$get('rola')]) ? $get('rola') : '';

$warunki = ['1'];
$parametry = [];

if ($szukaj !== '') {
    $warunki[] = "(CONCAT(imie, ' ', nazwisko) LIKE ? OR email LIKE ? OR telefon LIKE ?)";
    array_push($parametry, "%$szukaj%", "%$szukaj%", "%$szukaj%");
}
if ($rola) {
    $warunki[] = 'rola = ?';
    $parametry[] = $rola;
}

$where = implode(' AND ', $warunki);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM uzytkownicy WHERE $where");
$stmt->execute($parametry);
$wszystkich = (int) $stmt->fetchColumn();

$stron = max(1, (int) ceil($wszystkich / NA_STRONIE));
$strona = min(max(1, liczba($get('strona') ?: '1')), $stron);

$stmt = $pdo->prepare(
    "SELECT u.id, u.imie, u.nazwisko, u.email, u.telefon, u.rola, u.aktywny, u.data_utworzenia,
            (SELECT COUNT(*) FROM rezerwacje r WHERE r.uzytkownik_id = u.id) AS liczba_rezerwacji
     FROM uzytkownicy u
     WHERE $where
     ORDER BY u.nazwisko, u.imie
     LIMIT " . NA_STRONIE . ' OFFSET ' . (($strona - 1) * NA_STRONIE)
);
$stmt->execute($parametry);
$uzytkownicy = $stmt->fetchAll(PDO::FETCH_ASSOC);

naglowekAdmina('Uzytkownicy');
?>
<form method="get" class="filtry">
    <label>Szukaj: <input type="search" name="szukaj" placeholder="imie, nazwisko, email, telefon" value="<?= e($szukaj) ?>"></label>
    <label>Rola:
        <select name="rola">
            <option value="">wszystkie</option>
            <?php foreach ($role as $kod => $nazwa): ?>
                <option value="<?= e($kod) ?>"<?= $kod === $rola ? ' selected' : '' ?>><?= e($nazwa) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Szukaj</button>
</form>

<p>Znaleziono: <?= $wszystkich ?>. Nowych pracownikow dodajesz w zakladce <a href="admin_pracownicy.php">Pracownicy</a>.</p>

<?php if ($uzytkownicy): ?>
    <div class="tabela">
        <table>
            <tr>
                <th>Imie i nazwisko</th>
                <th>Email</th>
                <th>Telefon</th>
                <th>Rola</th>
                <th>Rezerwacje</th>
                <th>Konto</th>
                <th></th>
            </tr>
            <?php foreach ($uzytkownicy as $u): ?>
                <tr<?= $u['aktywny'] ? '' : ' class="nieaktywny"' ?>>
                    <td><?= e($u['imie'] . ' ' . $u['nazwisko']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e($u['telefon']) ?></td>
                    <td><?= e($role[$u['rola']] ?? $u['rola']) ?></td>
                    <td>
                        <?php if ($u['liczba_rezerwacji']): ?>
                            <a href="admin_rezerwacje.php?<?= e(http_build_query(['szukaj' => $u['email']])) ?>"><?= (int) $u['liczba_rezerwacji'] ?></a>
                        <?php else: ?>
                            0
                        <?php endif; ?>
                    </td>
                    <td><span class="status"><?= $u['aktywny'] ? 'Aktywne' : 'Zablokowane' ?></span></td>
                    <td>
                        <?php if ((int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                            <form method="post" class="w-linii"<?= $u['aktywny'] ? ' data-potwierdz="' . e('Zablokowac konto ' . $u['email'] . '?') . '"' : '' ?>>
                                <?= csrfPole() ?>
                                <input type="hidden" name="akcja" value="przelacz">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <input type="hidden" name="szukaj" value="<?= e($szukaj) ?>">
                                <input type="hidden" name="rola" value="<?= e($rola) ?>">
                                <input type="hidden" name="strona" value="<?= $strona ?>">
                                <button type="submit" class="maly<?= $u['aktywny'] ? ' niebezpieczny' : '' ?>"><?= $u['aktywny'] ? 'Zablokuj' : 'Odblokuj' ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
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
                <a href="admin_uzytkownicy.php?<?= e(http_build_query(array_filter(['szukaj' => $szukaj, 'rola' => $rola, 'strona' => $i]))) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </p>
<?php endif; ?>
<?php
stopkaAdmina();
