(() => {
    const isLogoutForm = (form) => {
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') {
            return false;
        }

        const action = new URL(form.action, window.location.href);

        return /\/logout\/?$/.test(action.pathname);
    };

    document.addEventListener('submit', async (event) => {
        const form = event.target;

        if (!isLogoutForm(form) || form.dataset.logoutConfirmed === 'true') {
            return;
        }

        event.preventDefault();
        const confirmed = typeof window.showConfirmationModal === 'function'
            ? await window.showConfirmationModal({
                title: 'Confirm Logout',
                message: 'Are you sure you want to log out of your account?',
                tone: 'danger',
                confirmText: 'Yes, Log Out',
                cancelText: 'Cancel',
            })
            : window.confirm('Are you sure you want to log out of your account?');

        if (confirmed) {
            form.dataset.logoutConfirmed = 'true';
            form.requestSubmit();
        }
    }, true);
})();
