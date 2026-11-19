<?php

function zapiszHistorie(PDO $pdo, int $rezerwacjaId, ?string $przed, string $po): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO historia_statusow (rezerwacja_id, status_przed, status_po, zmienil_id) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$rezerwacjaId, $przed, $po, $_SESSION['user_id'] ?? null]);
}

// zmienia status i zapisuje wpis w historii; zwraca opis bledu albo null, gdy sie udalo.
// $warunek to dodatkowy warunek SQL z kodu strony (np. wlasnosc rezerwacji), nigdy z danych
// od uzytkownika - wartosci ida w $parametry
function zmienStatus(PDO $pdo, int $id, string $nowy, string $warunek = '1', array $parametry = []): ?string
{
    if (!isset(STATUSY_REZERWACJI[$nowy])) {
        return 'Niepoprawny status.';
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM rezerwacje WHERE id = ? AND ($warunek) FOR UPDATE");
    $stmt->execute(array_merge([$id], $parametry));
    $r = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$r || $r['status'] === $nowy) {
        $pdo->rollBack();

        return $r ? 'Rezerwacja ma juz ten status.' : 'Nie znaleziono rezerwacji.';
    }

    $aktywne = ['oczekujaca', 'potwierdzona'];

    // przywrocenie anulowanej wizyty nie moze nalozyc jej na termin zajety w miedzyczasie
    if (in_array($nowy, $aktywne) && !in_array($r['status'], $aktywne)) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM rezerwacje
             WHERE pracownik_id = ? AND data_rezerwacji = ? AND id <> ?
               AND status IN ('oczekujaca', 'potwierdzona')
               AND godzina_od < ? AND godzina_do > ?"
        );
        $stmt->execute([$r['pracownik_id'], $r['data_rezerwacji'], $id, $r['godzina_do'], $r['godzina_od']]);

        if ($stmt->fetchColumn() > 0) {
            $pdo->rollBack();

            return 'Ten termin jest juz zajety przez inna rezerwacje.';
        }
    }

    $stmt = $pdo->prepare('UPDATE rezerwacje SET status = ? WHERE id = ?');
    $stmt->execute([$nowy, $id]);
    zapiszHistorie($pdo, $id, $r['status'], $nowy);

    $pdo->commit();

    return null;
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
