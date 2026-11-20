<?php

declare(strict_types=1);

const DNI_TYGODNIA = [
    1 => 'Poniedzialek',
    2 => 'Wtorek',
    3 => 'Sroda',
    4 => 'Czwartek',
    5 => 'Piatek',
    6 => 'Sobota',
    7 => 'Niedziela',
];

const KROK_MINUT = 15;

function minuty(string $czas): int
{
    [$h, $m] = explode(':', $czas);

    return (int) $h * 60 + (int) $m;
}

function czasZMinut(int $minuty): string
{
    return sprintf('%02d:%02d', intdiv($minuty, 60), $minuty % 60);
}

function poprawnaData(string $data): ?string
{
    $d = DateTime::createFromFormat('!Y-m-d', $data);

    return $d && $d->format('Y-m-d') === $data ? $data : null;
}

function aktywnaUsluga(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.nazwa, u.opis, u.czas_trwania, u.cena, k.nazwa AS kategoria
         FROM uslugi u
         JOIN kategorie_uslug k ON k.id = u.kategoria_id
         WHERE u.id = ? AND u.aktywna = 1'
    );
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

function pracownicyUslugi(PDO $pdo, int $uslugaId): array
{
    $stmt = $pdo->prepare(
        "SELECT p.id, CONCAT(u.imie, ' ', u.nazwisko) AS nazwa, p.opis
         FROM pracownicy_uslugi pu
         JOIN pracownicy p ON p.id = pu.pracownik_id
         JOIN uzytkownicy u ON u.id = p.uzytkownik_id
         WHERE pu.usluga_id = ? AND p.aktywny = 1 AND u.aktywny = 1
         ORDER BY u.nazwisko, u.imie"
    );
    $stmt->execute([$uslugaId]);

    return $stmt->fetchAll();
}

function pracownikWykonujeUsluge(PDO $pdo, int $pracownikId, int $uslugaId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM pracownicy_uslugi pu
         JOIN pracownicy p ON p.id = pu.pracownik_id
         JOIN uzytkownicy u ON u.id = p.uzytkownik_id
         WHERE pu.usluga_id = ? AND pu.pracownik_id = ?
           AND p.aktywny = 1 AND u.aktywny = 1
         LIMIT 1'
    );
    $stmt->execute([$uslugaId, $pracownikId]);

    return (bool) $stmt->fetchColumn();
}

function dniPracy(PDO $pdo, int $pracownikId): array
{
    $stmt = $pdo->prepare(
        'SELECT dzien_tygodnia, godzina_od, godzina_do
         FROM dostepnosc_pracownikow
         WHERE pracownik_id = ?
         ORDER BY dzien_tygodnia'
    );
    $stmt->execute([$pracownikId]);

    return $stmt->fetchAll();
}

function godzinyPracy(PDO $pdo, int $pracownikId, string $data): ?array
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

    if (!$d) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT godzina_od, godzina_do
         FROM dostepnosc_pracownikow
         WHERE pracownik_id = ? AND dzien_tygodnia = ?'
    );
    $stmt->execute([$pracownikId, (int) $d->format('N')]);

    return $stmt->fetch() ?: null;
}

function zajeteTerminy(PDO $pdo, int $pracownikId, string $data): array
{
    $stmt = $pdo->prepare(
        "SELECT godzina_od, godzina_do
         FROM rezerwacje
         WHERE pracownik_id = ? AND data_rezerwacji = ?
           AND status IN ('oczekujaca', 'potwierdzona')"
    );
    $stmt->execute([$pracownikId, $data]);

    return $stmt->fetchAll();
}

function koliduje(int $od, int $do, array $zajete): bool
{
    foreach ($zajete as $z) {
        if ($od < minuty($z['godzina_do']) && $do > minuty($z['godzina_od'])) {
            return true;
        }
    }

    return false;
}

function wolneTerminy(PDO $pdo, int $pracownikId, string $data, int $czasTrwania): array
{
    if (!poprawnaData($data) || $data < date('Y-m-d')) {
        return [];
    }

    $praca = godzinyPracy($pdo, $pracownikId, $data);

    if (!$praca) {
        return [];
    }

    $zajete = zajeteTerminy($pdo, $pracownikId, $data);
    $teraz = $data === date('Y-m-d') ? minuty(date('H:i')) : -1;
    $koniecPracy = minuty($praca['godzina_do']);
    $wolne = [];

    for ($od = minuty($praca['godzina_od']); $od + $czasTrwania <= $koniecPracy; $od += KROK_MINUT) {
        if ($od > $teraz && !koliduje($od, $od + $czasTrwania, $zajete)) {
            $wolne[] = czasZMinut($od);
        }
    }

    return $wolne;
}

function bladTerminu(PDO $pdo, array $usluga, int $pracownikId, string $data, string $godzina): ?string
{
    if (!poprawnaData($data) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $godzina)) {
        return 'Niepoprawna data lub godzina.';
    }

    if (strtotime($data . ' ' . $godzina) <= time()) {
        return 'Nie mozna zarezerwowac terminu w przeszlosci.';
    }

    if (!pracownikWykonujeUsluge($pdo, $pracownikId, (int) $usluga['id'])) {
        return 'Wybrany pracownik nie wykonuje tej uslugi.';
    }

    $praca = godzinyPracy($pdo, $pracownikId, $data);
    $od = minuty($godzina);
    $do = $od + (int) $usluga['czas_trwania'];

    if (!$praca || $od < minuty($praca['godzina_od']) || $do > minuty($praca['godzina_do'])) {
        return 'Ten termin wypada poza godzinami pracy pracownika.';
    }

    if (($od - minuty($praca['godzina_od'])) % KROK_MINUT !== 0) {
        return 'Niepoprawna godzina rozpoczecia.';
    }

    if (koliduje($od, $do, zajeteTerminy($pdo, $pracownikId, $data))) {
        return 'Ten termin jest juz zajety. Wybierz inna godzine.';
    }

    return null;
}