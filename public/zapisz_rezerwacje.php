<?php
require __DIR__ . '/../includes/wspolne.php';
require __DIR__ . '/../includes/terminy.php';
require __DIR__ . '/../includes/statusy.php';

chronStrone('klient');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    przekieruj('rezerwacja.php');
}

$usluga = aktywnaUsluga($pdo, liczba($_POST['usluga'] ?? ''));
$pracownikId = liczba($_POST['pracownik'] ?? '');
$data = postTekst('data');
$godzina = postTekst('godzina');
$komentarz = postTekst('komentarz');

if (!$usluga) {
    ustawKomunikat('blad', 'Wybrana usluga nie jest juz dostepna.');
    przekieruj('rezerwacja.php');
}

$powrot = 'rezerwacja.php?usluga=' . $usluga['id'] . '&pracownik=' . $pracownikId;

if (mb_strlen($komentarz) > 500) {
    ustawKomunikat('blad', 'Komentarz moze miec maksymalnie 500 znakow.');
    przekieruj($powrot);
}

// READ COMMITTED: po zdjeciu blokady widzimy rezerwacje zapisana przez drugie zadanie
$pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
$pdo->beginTransaction();

// blokada wiersza pracownika: dwa rownoczesne zapisy do tego samego pracownika
// wykonuja sie po kolei, wiec drugi zobaczy termin zajety przez pierwszy
$stmt = $pdo->prepare('SELECT id FROM pracownicy WHERE id = ? FOR UPDATE');
$stmt->execute([$pracownikId]);

$blad = bladTerminu($pdo, $usluga, $pracownikId, $data, $godzina);

if ($blad) {
    $pdo->rollBack();
    ustawKomunikat('blad', $blad);
    przekieruj($powrot . (poprawnaData($data) ? '&data=' . $data : ''));
}

$stmt = $pdo->prepare(
    "INSERT INTO rezerwacje (uzytkownik_id, pracownik_id, usluga_id, data_rezerwacji, godzina_od, godzina_do, status, komentarz)
     VALUES (?, ?, ?, ?, ?, ?, 'oczekujaca', ?)"
);
$stmt->execute([
    $_SESSION['user_id'],
    $pracownikId,
    $usluga['id'],
    $data,
    $godzina,
    czasZMinut(minuty($godzina) + (int) $usluga['czas_trwania']),
    $komentarz !== '' ? $komentarz : null,
]);
zapiszHistorie($pdo, (int) $pdo->lastInsertId(), null, 'oczekujaca');

$pdo->commit();

ustawKomunikat('sukces', 'Rezerwacja zostala zapisana i czeka na potwierdzenie.');
przekieruj('panel_klienta.php');
