CREATE DATABASE IF NOT EXISTS rezerwacja_uslug CHARACTER SET utf8mb4;
USE rezerwacja_uslug;

CREATE TABLE uzytkownicy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    imie VARCHAR(50) NOT NULL,
    nazwisko VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    haslo VARCHAR(255) NOT NULL,
    telefon VARCHAR(20),
    rola ENUM('klient', 'pracownik', 'administrator') NOT NULL DEFAULT 'klient',
    aktywny TINYINT(1) NOT NULL DEFAULT 1,
    data_utworzenia TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE kategorie_uslug (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nazwa VARCHAR(100) NOT NULL,
    opis TEXT
);

CREATE TABLE uslugi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategoria_id INT NOT NULL,
    nazwa VARCHAR(100) NOT NULL,
    opis TEXT,
    czas_trwania INT NOT NULL,
    cena DECIMAL(8,2) NOT NULL,
    aktywna TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (kategoria_id) REFERENCES kategorie_uslug(id) ON DELETE RESTRICT,
    CHECK (czas_trwania > 0),
    CHECK (cena >= 0)
);

CREATE TABLE pracownicy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uzytkownik_id INT NOT NULL UNIQUE,
    opis TEXT,
    aktywny TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (uzytkownik_id) REFERENCES uzytkownicy(id) ON DELETE RESTRICT
);

CREATE TABLE pracownicy_uslugi (
    pracownik_id INT NOT NULL,
    usluga_id INT NOT NULL,
    PRIMARY KEY (pracownik_id, usluga_id),
    FOREIGN KEY (pracownik_id) REFERENCES pracownicy(id) ON DELETE CASCADE,
    FOREIGN KEY (usluga_id) REFERENCES uslugi(id) ON DELETE CASCADE
);

CREATE TABLE dostepnosc_pracownikow (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pracownik_id INT NOT NULL,
    dzien_tygodnia TINYINT NOT NULL COMMENT '1-poniedzialek ... 7-niedziela',
    godzina_od TIME NOT NULL,
    godzina_do TIME NOT NULL,
    FOREIGN KEY (pracownik_id) REFERENCES pracownicy(id) ON DELETE CASCADE,
    UNIQUE KEY uq_pracownik_dzien (pracownik_id, dzien_tygodnia),
    CHECK (dzien_tygodnia BETWEEN 1 AND 7),
    CHECK (godzina_do > godzina_od)
);

CREATE TABLE rezerwacje (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uzytkownik_id INT NOT NULL,
    pracownik_id INT NOT NULL,
    usluga_id INT NOT NULL,
    data_rezerwacji DATE NOT NULL,
    godzina_od TIME NOT NULL,
    godzina_do TIME NOT NULL,
    status ENUM('oczekujaca', 'potwierdzona', 'zrealizowana', 'anulowana') NOT NULL DEFAULT 'oczekujaca',
    komentarz TEXT,
    data_utworzenia TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uzytkownik_id) REFERENCES uzytkownicy(id) ON DELETE RESTRICT,
    FOREIGN KEY (pracownik_id) REFERENCES pracownicy(id) ON DELETE RESTRICT,
    FOREIGN KEY (usluga_id) REFERENCES uslugi(id) ON DELETE RESTRICT,
    CHECK (godzina_do > godzina_od),
    INDEX idx_pracownik_data (pracownik_id, data_rezerwacji),
    INDEX idx_uzytkownik (uzytkownik_id)
);

-- Dane testowe (haslo dla wszystkich kont: haslo123)

INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, telefon, rola) VALUES
('Anna', 'Kowalska', 'admin@serwis.pl', '$2y$12$E.BY5eN5wUDxkKujGJcu6eP8m4kaExo4UwlXwyNTwYEGg5ZE2AuES', '600100100', 'administrator'),
('Jan', 'Nowak', 'jan.nowak@serwis.pl', '$2y$12$E.BY5eN5wUDxkKujGJcu6eP8m4kaExo4UwlXwyNTwYEGg5ZE2AuES', '600200200', 'pracownik'),
('Piotr', 'Zielinski', 'piotr.zielinski@serwis.pl', '$2y$12$E.BY5eN5wUDxkKujGJcu6eP8m4kaExo4UwlXwyNTwYEGg5ZE2AuES', '600300300', 'pracownik'),
('Ewa', 'Wisniewska', 'ewa@klient.pl', '$2y$12$E.BY5eN5wUDxkKujGJcu6eP8m4kaExo4UwlXwyNTwYEGg5ZE2AuES', '600400400', 'klient');

INSERT INTO pracownicy (uzytkownik_id, opis) VALUES
(2, 'Naprawa laptopow i komputerow stacjonarnych'),
(3, 'Serwis sprzetu sieciowego i instalacja oprogramowania');

INSERT INTO kategorie_uslug (nazwa, opis) VALUES
('Naprawa sprzetu', 'Diagnostyka i naprawa komputerow oraz laptopow'),
('Oprogramowanie', 'Instalacja i konfiguracja oprogramowania');

INSERT INTO uslugi (kategoria_id, nazwa, opis, czas_trwania, cena) VALUES
(1, 'Diagnostyka komputera', 'Sprawdzenie sprzetu i wykrycie usterki', 30, 50.00),
(1, 'Wymiana dysku SSD', 'Wymiana dysku i migracja systemu', 60, 150.00),
(2, 'Instalacja systemu Windows', 'Czysta instalacja systemu operacyjnego', 90, 120.00);

INSERT INTO pracownicy_uslugi (pracownik_id, usluga_id) VALUES
(1, 1), (1, 2), (2, 1), (2, 3);

INSERT INTO dostepnosc_pracownikow (pracownik_id, dzien_tygodnia, godzina_od, godzina_do) VALUES
(1, 1, '09:00:00', '17:00:00'),
(1, 2, '09:00:00', '17:00:00'),
(2, 1, '10:00:00', '18:00:00'),
(2, 3, '10:00:00', '18:00:00');

INSERT INTO rezerwacje (uzytkownik_id, pracownik_id, usluga_id, data_rezerwacji, godzina_od, godzina_do, status) VALUES
(4, 1, 1, '2026-09-15', '10:00:00', '10:30:00', 'potwierdzona');
