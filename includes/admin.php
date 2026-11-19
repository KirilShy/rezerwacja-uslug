<?php

require_once __DIR__ . '/wspolne.php';

chronStrone('administrator');

function naglowekAdmina(string $tytul): void
{
    $linki = [
        'panel_admina.php' => 'Dashboard',
        'admin_kategorie.php' => 'Kategorie',
        'admin_uslugi.php' => 'Uslugi',
        'admin_pracownicy.php' => 'Pracownicy',
        'admin_uzytkownicy.php' => 'Uzytkownicy',
        'admin_rezerwacje.php' => 'Rezerwacje',
    ];
    // modul pojawia sie w menu dopiero, gdy jego strona istnieje
    $linki = array_filter(
        $linki,
        fn ($plik) => file_exists(__DIR__ . '/../public/' . $plik),
        ARRAY_FILTER_USE_KEY
    );

    naglowekStrony($tytul, $linki);
}

function stopkaAdmina(): void
{
    stopkaStrony();
}
