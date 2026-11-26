<?php
require __DIR__ . '/../includes/wspolne.php';
require __DIR__ . '/../includes/terminy.php';

// wolne godziny w formacie JSON dla formularza rezerwacji (pobierane przez JavaScript bez przeladowania strony)
header('Content-Type: application/json; charset=utf-8');

$usluga = aktywnaUsluga($pdo, liczba($_GET['usluga'] ?? ''));
$pracownikId = liczba($_GET['pracownik'] ?? '');
$data = poprawnaData(is_string($_GET['data'] ?? null) ? $_GET['data'] : '');

if (!$usluga || !pracownikWykonujeUsluge($pdo, $pracownikId, (int) $usluga['id']) || !$data) {
    http_response_code(400);
    echo json_encode(['blad' => 'Niepoprawna usluga, pracownik lub data.']);
    exit;
}

echo json_encode([
    'data' => $data,
    'terminy' => wolneTerminy($pdo, $pracownikId, $data, (int) $usluga['czas_trwania']),
]);
