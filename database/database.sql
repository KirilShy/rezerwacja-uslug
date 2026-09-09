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
