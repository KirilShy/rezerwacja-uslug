<?php
declare(strict_types=1);

require_once __DIR__ . '/wspolne.php';

function naglowekAdmina(string $tytul): void
{
    naglowekStrony($tytul, linkiAdmina());
}

function stopkaAdmina(): void
{
    stopkaStrony();
}

chronStrone('administrator');