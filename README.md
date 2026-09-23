# System rezerwacji usług - serwis komputerowy

Prosta aplikacja internetowa do rezerwacji usług serwisu komputerowego.

## Autorzy

- Kyrylo Shynkarenko
- Sandro Radomski

## Technologie

- PHP
- HTML5
- CSS3
- JavaScript
- MySQL

## Uruchomienie projektu

1. Zaimportuj baze danych:
   ```
   mysql -u root < database/database.sql
   ```
2. Ustaw dane polaczenia z baza w `config/database.php` (host, nazwa bazy, uzytkownik, haslo).
3. Uruchom wbudowany serwer PHP z katalogu glownego projektu:
   ```
   php -S localhost:8000 -t public
   ```
4. Otworz w przegladarce `http://localhost:8000`.

## Testowe konta

Haslo dla wszystkich kont testowych: `haslo123`

| Rola          | Email                       |
|---------------|------------------------------|
| Administrator | admin@serwis.pl              |
| Pracownik     | jan.nowak@serwis.pl           |
| Pracownik     | piotr.zielinski@serwis.pl     |
| Klient        | ewa@klient.pl                 |
