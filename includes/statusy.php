<?php

function zapiszHistorie(PDO $pdo, int $rezerwacjaId, ?string $przed, string $po): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO historia_statusow (rezerwacja_id, status_przed, status_po, zmienil_id) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$rezerwacjaId, $przed, $po, $_SESSION['user_id'] ?? null]);
}

// zmienia status i zapisuje wpis w historii; $warunek to dodatkowy warunek SQL z kodu strony
// (np. wlasnosc rezerwacji), nigdy z danych od uzytkownika - wartosci ida w $parametry
function zmienStatus(PDO $pdo, int $id, string $nowy, string $warunek = '1', array $parametry = []): bool
{
    if (!isset(STATUSY_REZERWACJI[$nowy])) {
        return false;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT status FROM rezerwacje WHERE id = ? AND ($warunek) FOR UPDATE");
    $stmt->execute(array_merge([$id], $parametry));
    $przed = $stmt->fetchColumn();

    if ($przed === false || $przed === $nowy) {
        $pdo->rollBack();

        return false;
    }

    $stmt = $pdo->prepare('UPDATE rezerwacje SET status = ? WHERE id = ?');
    $stmt->execute([$nowy, $id]);
    zapiszHistorie($pdo, $id, $przed, $nowy);

    $pdo->commit();

    return true;
}

function historiaStatusow(PDO $pdo, int $rezerwacjaId): array
{
    $stmt = $pdo->prepare(
        "SELECT h.status_przed, h.status_po, h.data_zmiany, CONCAT(u.imie, ' ', u.nazwisko) AS kto, u.rola
         FROM historia_statusow h
         LEFT JOIN uzytkownicy u ON u.id = h.zmienil_id
         WHERE h.rezerwacja_id = ?
         ORDER BY h.data_zmiany, h.id"
    );
    $stmt->execute([$rezerwacjaId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
