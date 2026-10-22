// formularz z atrybutem data-potwierdz pyta o potwierdzenie przed wyslaniem (np. usuwanie)
document.addEventListener('submit', function (zdarzenie) {
    const pytanie = zdarzenie.target.dataset.potwierdz;

    if (pytanie && !confirm(pytanie)) {
        zdarzenie.preventDefault();
    }
});
