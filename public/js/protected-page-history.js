(() => {
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) {
            return;
        }

        // A browser may restore a page from its back-forward cache without a
        // request. Hide it immediately, then reload so authentication is checked.
        document.documentElement.style.visibility = 'hidden';
        window.location.reload();
    });
})();
