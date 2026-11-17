// La Ferme — interactions front simples
document.addEventListener('click', function (e) {
    // Ouverture / fermeture de la navigation mobile
    if (e.target.closest('[data-toggle-nav]')) {
        document.body.classList.toggle('nav-open');
    }
    if (e.target.closest('[data-close-nav]')) {
        document.body.classList.remove('nav-open');
    }
    // Confirmation avant suppression
    const del = e.target.closest('[data-confirm]');
    if (del) {
        if (!window.confirm(del.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    }
});
