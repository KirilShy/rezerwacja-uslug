# System rezerwacji usług - serwis komputerowy

Aplikacja do rezerwacji wizyt w serwisie komputerowym. Klient wybiera usługę, pracownika, datę i wolną godzinę, a pracownik i administrator obsługują rezerwacje w swoich panelach.

Wersja online: http://rezerwacja-uslug.infy.click

**Autorzy:** Kyrylo Shynkarenko, Sandro Radomski

**Technologie:** PHP 8 (PDO), MySQL, HTML5, CSS3, JavaScript

## Uruchomienie

```
mysql -u root < database/database.sql
php -S localhost:8000
```

Potem otwórz `http://localhost:8000`. Inne dane do bazy niż `root` bez hasła wpisz w `config/config.local.php` (wzór: `config/config.local.example.php`). Testy: `php tests/testy.php`.

## Konta testowe

Hasło: `haslo123`

- Administrator: `admin@serwis.pl`
- Pracownik: `jan.nowak@serwis.pl`, `piotr.zielinski@serwis.pl`
- Klient: `ewa@klient.pl`, `tomasz@klient.pl`

## Funkcje dodatkowe

- historia zmian statusów rezerwacji
- dni wolne i urlopy pracowników
- wolne godziny pobierane przez AJAX
- statystyki na dashboardzie admina
- eksport rezerwacji do CSV
- testy automatyczne logiki rezerwacji
- limit prób logowania, zmiana hasła, tokeny CSRF
- wyszukiwanie i stronicowanie w panelu admina
