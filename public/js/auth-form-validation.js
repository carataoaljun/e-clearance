(() => {
    const fieldLabel = field => field.dataset.validationLabel
        || field.closest('.field')?.querySelector('label')?.textContent.trim()
        || 'This field';

    const validationMessageFor = field => {
        const label = fieldLabel(field);

        if (field.validity.customError) return field.validationMessage;
        if (field.validity.valueMissing) return `${label} is required.`;
        if (field.validity.typeMismatch) return `Enter a valid ${label.toLowerCase()}.`;
        if (field.validity.tooShort) return `${label} must contain at least ${field.minLength} characters.`;
        if (field.validity.tooLong) return `${label} must not exceed ${field.maxLength} characters.`;
        if (field.validity.patternMismatch && field.dataset.validationRule === 'strong-password') {
            return 'Use at least 8 characters with uppercase, lowercase, a number, and a special character.';
        }
        if (field.validity.patternMismatch && field.dataset.validationRule === 'verification-code') {
            return 'The verification code must contain exactly six digits.';
        }

        return `Check the value entered for ${label.toLowerCase()}.`;
    };

    const errorElementFor = field => {
        const describedBy = field.getAttribute('aria-describedby');
        return describedBy ? document.getElementById(describedBy) : null;
    };

    const validateField = (field, showSuccess = true) => {
        const error = errorElementFor(field);
        const hasValue = String(field.value || '').trim() !== '';

        field.classList.remove('field-invalid', 'field-valid');
        field.removeAttribute('aria-invalid');
        if (error) {
            error.classList.remove('show');
            error.textContent = '';
        }

        if (!field.checkValidity()) {
            field.classList.add('field-invalid');
            field.setAttribute('aria-invalid', 'true');
            if (error) {
                error.textContent = validationMessageFor(field);
                error.classList.add('show');
            }
            return false;
        }

        if (showSuccess && hasValue) field.classList.add('field-valid');
        return true;
    };

    const preparePasswordConfirmation = form => {
        const password = form.querySelector('[data-password-primary]');
        const confirmation = form.querySelector('[data-password-confirmation]');
        if (!password || !confirmation) return;

        confirmation.setCustomValidity(
            confirmation.value !== '' && confirmation.value !== password.value
                ? 'Passwords do not match.'
                : ''
        );
    };

    document.querySelectorAll('.auth-panel form').forEach((form, formIndex) => {
        const fields = Array.from(form.querySelectorAll('.field input:not([type="hidden"]), .field select'));
        if (fields.length === 0) return;

        form.noValidate = true;
        fields.forEach((field, fieldIndex) => {
            const group = field.closest('.field');
            if (!group) return;

            if (!field.id) field.id = `auth-field-${formIndex}-${fieldIndex}`;

            const error = document.createElement('div');
            error.className = 'field-error';
            error.id = `${field.id}-error`;
            error.setAttribute('aria-live', 'polite');
            group.insertAdjacentElement('afterend', error);
            field.setAttribute('aria-describedby', error.id);

            field.addEventListener('blur', () => {
                preparePasswordConfirmation(form);
                validateField(field);
            });
            field.addEventListener('input', () => {
                preparePasswordConfirmation(form);
                if (field.classList.contains('field-invalid')) validateField(field, false);

                const confirmation = form.querySelector('[data-password-confirmation]');
                if (field.hasAttribute('data-password-primary') && confirmation?.classList.contains('field-invalid')) {
                    validateField(confirmation, false);
                }
            });
            field.addEventListener('change', () => {
                preparePasswordConfirmation(form);
                validateField(field);
            });
        });

        preparePasswordConfirmation(form);
        form.addEventListener('submit', event => {
            preparePasswordConfirmation(form);
            let firstInvalid = null;

            fields.forEach(field => {
                if (!validateField(field) && firstInvalid === null) firstInvalid = field;
            });

            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
            }
        });
    });
})();

