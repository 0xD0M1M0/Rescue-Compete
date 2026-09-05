/**
 * Skript zur Anzeige von Login-Fehlermeldungen und zur Formularvalidierung
 */
document.addEventListener('DOMContentLoaded', function() {
    // URL-Parameter auslesen
    const urlParams = new URLSearchParams(window.location.search);
    const errorCode = urlParams.get('f');

    // Container für Fehlermeldungen finden
    let errorContainer = document.getElementById('error-message');

    // Falls Container nicht existiert, erstellen und unter der Loginbox platzieren
    if (!errorContainer) {
        errorContainer = document.createElement('div');
        errorContainer.id = 'error-message';
        errorContainer.className = 'message-container';

        // Nach der Login-Form einfügen
        const loginWrapper = document.querySelector('.login-wrapper');
        const loginForm = document.querySelector('.form-section');

        if (loginWrapper && loginForm) {
            loginWrapper.appendChild(errorContainer);
        } else {
            // Fallback: nach dem body_container
            const bodyContainer = document.getElementById('body_container');
            if (bodyContainer) {
                bodyContainer.appendChild(errorContainer);
            }
        }
    }

    // Funktion zum Anzeigen einer Fehlermeldung
    function showErrorMessage(message, type = 'error') {
        // Vorhandene Meldungen entfernen
        clearMessages();

        // Neue Meldung erstellen
        const messageDiv = document.createElement('div');
        messageDiv.className = type === 'error' ? 'error-message' : 'notice-message';
        messageDiv.textContent = message;

        // Zur Container hinzufügen
        errorContainer.appendChild(messageDiv);
    }

    // Funktion zum Entfernen aller Meldungen
    function clearMessages() {
        const existingMessages = errorContainer.querySelectorAll('.error-message, .notice-message');
        existingMessages.forEach(msg => msg.remove());
    }

    // Funktion zum Markieren ungültiger Felder
    function markInvalidFields(fieldIds) {
        fieldIds.forEach(function(fieldId) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.classList.remove('valid');
                field.classList.add('invalid');

                // Event-Listener hinzufügen, um Klasse zu entfernen, wenn Benutzer erneut eintippt
                field.addEventListener('input', function() {
                    this.classList.remove('invalid');
                    this.classList.add('valid');

                    // Fehlermeldung entfernen, wenn alle Felder wieder gültig sind
                    if (document.querySelectorAll('.invalid').length === 0) {
                        clearMessages();
                    }
                });
            }
        });
    }

    // Je nach Fehlercode entsprechende Meldung anzeigen
    if (errorCode) {
        switch (errorCode) {
            case '1':
                showErrorMessage('Bitte geben Sie einen Benutzernamen und ein Passwort ein.');
                markInvalidFields(['username', 'password']);
                break;
            case '2':
                showErrorMessage('Dieser Benutzername ist nicht registriert.');
                markInvalidFields(['username']);
                break;
            case '3':
                showErrorMessage('Falsches Passwort.');
                markInvalidFields(['password']);
                break;
            case 'sso_only':
                showErrorMessage('Dieser Account ist mit SSO verknüpft. Bitte über SSO anmelden.');
                break;
            case 'sso_disabled':
                showErrorMessage('SSO-Anmeldung ist nicht konfiguriert.');
                break;
            case 'sso_state':
                showErrorMessage('SSO-Anmeldung ungültig. Bitte erneut versuchen.');
                break;
            case 'sso_email':
                showErrorMessage('SSO-Anmeldung erfordert eine verifizierte E-Mail. Anmeldung nicht möglich.');
                break;
            case 'sso_error':
                showErrorMessage('Anmeldung über SSO fehlgeschlagen. Bitte erneut versuchen.');
                break;
            case '4':
                showErrorMessage('Sitzung abgelaufen. Bitte erneut anmelden.');
                break;
            case '999':
                showErrorMessage('Ein technischer Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
                break;
            default:
                showErrorMessage('Ein unbekannter Fehler ist aufgetreten.');
        }
    }

    // Formularvalidierung vor dem Absenden
    const loginForm = document.getElementById('login_form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            // Prüfe, ob beide Felder ausgefüllt sind
            if (!username || !password) {
                e.preventDefault();

                showErrorMessage('Bitte füllen Sie alle Felder aus.');

                if (!username) markInvalidFields(['username']);
                if (!password) markInvalidFields(['password']);
            }
        });
    }
});