<?php
// testy automatyczne logiki rezerwacji; uruchomienie: php tests/testy.php
// czesc testow pisze do bazy, ale wszystko dzieje sie w transakcji, ktora na koncu jest wycofywana

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Testy uruchamia sie z wiersza polecen.');
}

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/terminy.php';

$zaliczone = 0;
$niezaliczone = 0;

function sprawdz(string $opis, mixed $wynik, mixed $oczekiwany): void
{
    global $zaliczone, $niezaliczone;

    if ($wynik === $oczekiwany) {
        $zaliczone++;
        echo "  OK    $opis\n";
    } else {
        $niezaliczone++;
        echo "  BLAD  $opis\n        oczekiwano: " . var_export($oczekiwany, true) . "\n        otrzymano:  " . var_export($wynik, true) . "\n";
    }
}

echo "Regula nakladania przedzialow (istniejaca wizyta 10:00-11:00)\n";
$zajete = [['godzina_od' => '10:00:00', 'godzina_do' => '11:00:00']];
$przypadki = [
    '09:30-10:30' => true,
    '10:00-11:00' => true,
    '10:15-10:45' => true,
    '10:30-11:30' => true,
    '09:30-11:30' => true,
    '09:00-10:00' => false,
    '11:00-12:00' => false,
];
foreach ($przypadki as $przedzial => $koliduje) {
    [$od, $do] = explode('-', $przedzial);
    sprawdz("$przedzial " . ($koliduje ? 'blokowany' : 'dozwolony'), koliduje(minuty($od), minuty($do), $zajete), $koliduje);
}

echo "\nFunkcje pomocnicze\n";
sprawdz('minuty("09:30")', minuty('09:30'), 570);
sprawdz('czasZMinut(570)', czasZMinut(570), '09:30');
sprawdz('poprawnaData("2026-11-30")', poprawnaData('2026-11-30'), '2026-11-30');
sprawdz('poprawnaData("2026-02-30") odrzucona', poprawnaData('2026-02-30'), null);
sprawdz('poprawnaData("abc") odrzucona', poprawnaData('abc'), null);

echo "\nDostepnosc na danych z bazy (transakcja wycofywana na koncu)\n";
$pdo->beginTransaction();

try {
    $pdo->exec("INSERT INTO kategorie_uslug (nazwa) VALUES ('Test')");
    $kategoria = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, rola) VALUES ('Test', 'Pracownik', 'test.pracownik@test.pl', 'x', 'pracownik')");
    $pdo->exec('INSERT INTO pracownicy (uzytkownik_id) VALUES (' . (int) $pdo->lastInsertId() . ')');
    $pracownik = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, rola) VALUES ('Test', 'Klient', 'test.klient@test.pl', 'x', 'klient')");
    $klient = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO uslugi (kategoria_id, nazwa, czas_trwania, cena) VALUES ($kategoria, 'Test 60 min', 60, 10)");
    $uslugaId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO pracownicy_uslugi VALUES ($pracownik, $uslugaId)");

    // pracownik pracuje w poniedzialki 09:00-12:00; test na najblizszy poniedzialek za co najmniej tydzien
    $pdo->exec("INSERT INTO dostepnosc_pracownikow (pracownik_id, dzien_tygodnia, godzina_od, godzina_do) VALUES ($pracownik, 1, '09:00', '12:00')");
    $poniedzialek = date('Y-m-d', strtotime('monday +1 week'));
    $wtorek = date('Y-m-d', strtotime($poniedzialek . ' +1 day'));
    $usluga = aktywnaUsluga($pdo, $uslugaId);

    sprawdz('wolne godziny w pustym dniu', wolneTerminy($pdo, $pracownik, $poniedzialek, 60), [
        '09:00', '09:15', '09:30', '09:45', '10:00', '10:15', '10:30', '10:45', '11:00',
    ]);
    sprawdz('brak godzin w dniu bez pracy', wolneTerminy($pdo, $pracownik, $wtorek, 60), []);

    $pdo->exec("INSERT INTO rezerwacje (uzytkownik_id, pracownik_id, usluga_id, data_rezerwacji, godzina_od, godzina_do)
                VALUES ($klient, $pracownik, $uslugaId, '$poniedzialek', '10:00', '11:00')");

    sprawdz('wolne godziny przy wizycie 10:00-11:00', wolneTerminy($pdo, $pracownik, $poniedzialek, 60), ['09:00', '11:00']);
    sprawdz('termin 10:30 zajety', bladTerminu($pdo, $usluga, $pracownik, $poniedzialek, '10:30'), 'Ten termin jest juz zajety. Wybierz inna godzine.');
    sprawdz('termin 09:00 wolny', bladTerminu($pdo, $usluga, $pracownik, $poniedzialek, '09:00'), null);
    sprawdz('termin 11:30 poza godzinami pracy', bladTerminu($pdo, $usluga, $pracownik, $poniedzialek, '11:30'), 'Ten termin wypada poza godzinami pracy pracownika.');
    sprawdz('termin w przeszlosci', bladTerminu($pdo, $usluga, $pracownik, '2020-01-06', '09:00'), 'Nie mozna zarezerwowac terminu w przeszlosci.');

    $pdo->exec("UPDATE rezerwacje SET status = 'anulowana' WHERE pracownik_id = $pracownik");
    sprawdz('anulowana wizyta zwalnia termin', bladTerminu($pdo, $usluga, $pracownik, $poniedzialek, '10:30'), null);

    $pdo->exec("INSERT INTO dni_wolne (pracownik_id, data_od, data_do) VALUES ($pracownik, '$poniedzialek', '$poniedzialek')");
    sprawdz('dzien wolny blokuje rezerwacje', bladTerminu($pdo, $usluga, $pracownik, $poniedzialek, '09:00'), 'Pracownik ma w tym dniu wolne.');
    sprawdz('dzien wolny nie ma wolnych godzin', wolneTerminy($pdo, $pracownik, $poniedzialek, 60), []);

    $pdo->exec("UPDATE pracownicy SET aktywny = 0 WHERE id = $pracownik");
    sprawdz('nieaktywny pracownik nie przyjmuje rezerwacji', bladTerminu($pdo, $usluga, $pracownik, $wtorek, '09:00'), 'Wybrany pracownik nie wykonuje tej uslugi.');
} finally {
    $pdo->rollBack();
}

echo "\nWynik: $zaliczone zaliczonych, $niezaliczone niezaliczonych\n";
exit($niezaliczone ? 1 : 0);
