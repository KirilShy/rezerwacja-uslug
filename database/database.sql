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
    FOREIGN KEY (kategoria_id) REFERENCES kategorie_uslug(id)
);

CREATE TABLE pracownicy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uzytkownik_id INT NOT NULL UNIQUE,
    opis TEXT,
    aktywny TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (uzytkownik_id) REFERENCES uzytkownicy(id)
);

CREATE TABLE pracownicy_uslugi (
    pracownik_id INT NOT NULL,
    usluga_id INT NOT NULL,
    PRIMARY KEY (pracownik_id, usluga_id),
    FOREIGN KEY (pracownik_id) REFERENCES pracownicy(id),
    FOREIGN KEY (usluga_id) REFERENCES uslugi(id)
);

CREATE TABLE dostepnosc_pracownikow (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pracownik_id INT NOT NULL,
    dzien_tygodnia TINYINT NOT NULL COMMENT '1-poniedzialek ... 7-niedziela',
    godzina_od TIME NOT NULL,
    godzina_do TIME NOT NULL,
    FOREIGN KEY (pracownik_id) REFERENCES pracownicy(id)
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
    FOREIGN KEY (uzytkownik_id) REFERENCES uzytkownicy(id),
    FOREIGN KEY (pracownik_id) REFERENCES pracownicy(id),
    FOREIGN KEY (usluga_id) REFERENCES uslugi(id),
    INDEX idx_pracownik_data (pracownik_id, data_rezerwacji)
);
