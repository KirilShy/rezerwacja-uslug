<?php

require_once __DIR__ . '/wspolne.php';

chronStrone('administrator');

function naglowekAdmina(string $tytul): void
{
    naglowekStrony($tytul, linkiAdmina());
}

function stopkaAdmina(): void
{
    stopkaStrony();
}
