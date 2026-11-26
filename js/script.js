// formularz z atrybutem data-potwierdz pyta o potwierdzenie przed wyslaniem (np. usuwanie)
document.addEventListener('submit', function (zdarzenie) {
    const pytanie = zdarzenie.target.dataset.potwierdz;

    if (pytanie && !confirm(pytanie)) {
        zdarzenie.preventDefault();
    }
});

// formularz wyboru daty z atrybutem data-terminy: po zmianie daty pobiera wolne godziny (AJAX)
// i pokazuje je od razu pod formularzem; bez JavaScriptu formularz dziala jak zwykly GET
document.addEventListener('change', async function (zdarzenie) {
    const pole = zdarzenie.target;
    const formularz = pole.form;

    if (pole.name !== 'data' || !formularz || !formularz.dataset.terminy || !pole.value) {
        return;
    }

    const lista = document.getElementById('godziny-ajax');
    lista.textContent = 'Ladowanie...';

    try {
        const odpowiedz = await fetch(formularz.dataset.terminy + '&data=' + encodeURIComponent(pole.value));
        const wynik = await odpowiedz.json();

        lista.textContent = '';

        if (!odpowiedz.ok) {
            lista.textContent = wynik.blad;
            return;
        }

        if (wynik.terminy.length === 0) {
            lista.textContent = 'Brak wolnych terminow w tym dniu.';
            return;
        }

        const adres = new URL(formularz.action || location.href);
        new FormData(formularz).forEach((wartosc, klucz) => adres.searchParams.set(klucz, wartosc));

        for (const godzina of wynik.terminy) {
            adres.searchParams.set('godzina', godzina);
            const link = document.createElement('a');
            link.href = adres.toString();
            link.textContent = godzina;
            lista.append(link);
        }
    } catch (blad) {
        lista.textContent = 'Nie udalo sie pobrac godzin. Uzyj przycisku "Pokaz godziny".';
    }
});
