# System rezerwacji usług - serwis komputerowy

Aplikacja internetowa do rezerwacji wizyt w serwisie komputerowym. Klient wybiera usługę, pracownika, datę i wolną godzinę, a pracownik i administrator obsługują rezerwacje w swoich panelach.

## Autorzy

- Kyrylo Shynkarenko
- Sandro Radomski

## Technologie

- PHP 8 (PDO)
- HTML5
- CSS3
- JavaScript
- MySQL 8

## Uruchomienie projektu

1. Zaimportuj bazę danych (skrypt tworzy bazę `rezerwacja_uslug` od nowa razem z danymi testowymi):
   ```
   mysql -u root < database/database.sql
   ```
2. Jeśli łączysz się z bazą innymi danymi niż `root` bez hasła na `localhost`, skopiuj `config/config.local.example.php` jako `config/config.local.php` i wpisz w nim swoje dane. Ten plik nie trafia do repozytorium.
3. Uruchom wbudowany serwer PHP z katalogu głównego projektu:
   ```
   php -S localhost:8000
   ```
4. Otwórz w przeglądarce `http://localhost:8000`.
5. Opcjonalnie uruchom testy: `php tests/testy.php`.

## Testowe konta

Hasło dla wszystkich kont testowych: `haslo123`

| Rola          | Email                     |
|---------------|---------------------------|
| Administrator | admin@serwis.pl           |
| Pracownik     | jan.nowak@serwis.pl       |
| Pracownik     | piotr.zielinski@serwis.pl |
| Klient        | ewa@klient.pl             |
| Klient        | tomasz@klient.pl          |

Daty rezerwacji testowych są liczone od dnia importu, więc zawsze są wizyty dzisiejsze, przyszłe i zakończone.

## Funkcje

- **Klient**: rejestracja, logowanie, oferta z filtrem kategorii i szczegółami usługi, rezerwacja w kolejności usługa → pracownik → data → godzina → potwierdzenie, lista przyszłych i zakończonych rezerwacji, anulowanie własnej przyszłej rezerwacji, edycja profilu.
- **Pracownik**: wizyty na dziś, przyszłe i historia, szczegóły wizyty z danymi klienta, zmiana statusu. Widzi tylko swoje wizyty.
- **Administrator**: dashboard, kategorie, usługi, pracownicy (dane, aktywność, usługi, godziny pracy), użytkownicy, rezerwacje z filtrami, szczegóły i zmiana statusu.

Wolne godziny są wyliczane z godzin pracy pracownika, czasu trwania usługi i istniejących rezerwacji. Przed zapisem serwer sprawdza termin jeszcze raz w transakcji z blokadą wiersza pracownika, więc dwie równoczesne próby zajęcia tego samego terminu nie przejdą obie.

## Funkcje dodatkowe

- **Historia statusów**: każda zmiana statusu rezerwacji (klient, pracownik, administrator) jest zapisywana w tabeli `historia_statusow` i widoczna w szczegółach wizyty.
- **Dni wolne i urlopy**: administrator dodaje pracownikowi dni wolne (tabela `dni_wolne`). W te dni nie ma wolnych godzin, a serwer odrzuca rezerwację. Przy dodawaniu urlopu panel ostrzega o istniejących rezerwacjach w tym okresie.
- **Automatyczne wolne godziny i AJAX**: przy wyborze daty widać liczbę wolnych terminów w najbliższych dniach pracy. Po wybraniu innej daty w kalendarzu godziny są pobierane z `terminy_json.php` bez przeładowania strony (bez JavaScriptu formularz działa jak zwykły formularz GET).
- **Statystyki** na dashboardzie: przychód ze zrealizowanych wizyt, rezerwacje według statusu, najpopularniejsze usługi i obłożenie pracowników w najbliższym tygodniu.
- **Eksport CSV** listy rezerwacji z aktualnymi filtrami (otwiera się poprawnie w polskim Excelu, tekst zaczynający się od `=` nie jest traktowany jak formuła).
- **Testy automatyczne** logiki rezerwacji: `php tests/testy.php` (reguła nakładania przedziałów z dokumentu, godziny pracy, dni wolne, przeszłość). Testy działają w transakcji wycofywanej na końcu, więc nie zmieniają danych.
- **Limit prób logowania**: po 5 nieudanych próbach na ten sam email logowanie jest blokowane na 15 minut.
- **Zmiana hasła** po podaniu obecnego hasła.
- **Tokeny CSRF** we wszystkich formularzach i bezpieczna sesja (`HttpOnly`, `SameSite`, nowe id sesji po zalogowaniu, wylogowanie zablokowanego konta).
- **Wyszukiwanie i stronicowanie** listy rezerwacji i użytkowników w panelu administratora.
- **Ochrona przed kolizją przy zmianie statusu**: anulowanej wizyty nie da się przywrócić, jeśli jej termin zajęła już inna rezerwacja.

## Struktura

```
config/     połączenie z bazą (dane lokalne w config.local.php)
css/        style
database/   database.sql i diagram ERD (erd.md)
includes/   wspólny kod: sesja i role, CSRF, wolne terminy, statusy, szablon stron
js/         potwierdzanie usuwania i anulowania, pobieranie wolnych godzin (AJAX)
public/     strony aplikacji
tests/      testy automatyczne (php tests/testy.php)
```
