/**
 * StationInputScript.js
 * JavaScript-Funktionalität für die Stations-Verwaltung
 */

// Globale Variablen
let currentTab = 'overview';
let deleteStationId = null;

// DOM-Ready
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
    initializeEventListeners();
});

/**
 * Initialisiert die Seite und setzt den aktiven Tab
 */
function initializePage() {
    // Aktuellen Tab aus der URL oder PHP-Variable ermitteln
    const urlParams = new URLSearchParams(window.location.search);
    const viewParam = urlParams.get('view');

    if (viewParam) {
        currentTab = viewParam;
    }

    // Tab anzeigen
    showTab(currentTab);

    // Alert-Modals aktivieren falls vorhanden
    const duplicateNameAlert = document.getElementById('duplicateNameAlert');
    if (duplicateNameAlert) {
        showModal('duplicateNameAlert');
    }

    const duplicateNumberAlert = document.getElementById('duplicateNumberAlert');
    if (duplicateNumberAlert) {
        showModal('duplicateNumberAlert');
    }
}

/**
 * Zeigt den angegebenen Tab an
 * @param {string} tabName - Name des anzuzeigenden Tabs
 */
function showTab(tabName) {
    // Alle Tab-Inhalte verstecken
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.classList.remove('active');
    });

    // Alle Tab-Buttons deaktivieren
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });

    // Gewählten Tab anzeigen
    const selectedTab = document.getElementById(tabName);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }

    // Entsprechenden Button aktivieren
    const selectedButton = document.querySelector(`[data-tab="${tabName}"]`);
    if (selectedButton) {
        selectedButton.classList.add('active');
    }

    // Aktuellen Tab speichern
    currentTab = tabName;

    // URL aktualisieren (ohne Reload)
    const url = new URL(window.location);
    url.searchParams.set('view', tabName);
    window.history.replaceState(null, '', url);

    // Tab-spezifische Aktionen
    handleTabSpecificActions(tabName);
}

/**
 * Behandelt tab-spezifische Aktionen
 * @param {string} tabName - Name des aktiven Tabs
 */
function handleTabSpecificActions(tabName) {
    switch (tabName) {
        case 'create':
            // Fokus auf das Name-Feld setzen
            const nameInput = document.getElementById('name');
            if (nameInput) {
                setTimeout(() => nameInput.focus(), 100);
            }
            break;
    }
}

/**
 * Lädt für die im Dropdown gewählte Wertung alle Stationen als Checkboxen
 * und hakt die bereits zugeordneten vor.
 */
function loadStationCheckboxes() {
    const select = document.getElementById('assignWertung');
    const container = document.getElementById('stationCheckboxContainer');
    const list = document.getElementById('stationCheckboxList');
    if (!select || !container || !list) {
        return;
    }

    const wertungId = select.value;
    if (!wertungId) {
        container.style.display = 'none';
        return;
    }

    list.innerHTML = '<p>Lade Stationen…</p>';
    container.style.display = 'block';

    const url = `StationInputView.php?action=getStationsForWertung&wertung=${encodeURIComponent(wertungId)}`;
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                list.innerHTML = `<p class="warning">${escapeHtml(data.error || 'Fehler beim Laden der Stationen.')}</p>`;
                return;
            }

            const assigned = (data.zugeordneteIds || []).map(Number);
            const stationen = data.alleStationen || [];

            if (stationen.length === 0) {
                list.innerHTML = '<p>Keine Stationen vorhanden.</p>';
                return;
            }

            list.innerHTML = '';
            stationen.forEach(station => {
                const id = Number(station.ID);
                const isChecked = assigned.includes(id) ? 'checked' : '';
                const item = document.createElement('div');
                item.className = 'team-checkbox-item';
                item.innerHTML = `
                    <input type="checkbox" id="assign_station_${id}" name="stationen[]" value="${id}" ${isChecked}>
                    <label for="assign_station_${id}"><strong>${escapeHtml(station.name)}</strong></label>
                `;
                list.appendChild(item);
            });
        })
        .catch(error => {
            list.innerHTML = '<p class="warning">Fehler beim Laden der Stationen.</p>';
            console.error('loadStationCheckboxes:', error);
        });
}

/**
 * Hakt alle Stations-Checkboxen an.
 */
function selectAllStationen() {
    document.querySelectorAll('#stationCheckboxList input[type="checkbox"]').forEach(cb => cb.checked = true);
}

/**
 * Entfernt alle Häkchen der Stations-Checkboxen.
 */
function deselectAllStationen() {
    document.querySelectorAll('#stationCheckboxList input[type="checkbox"]').forEach(cb => cb.checked = false);
}

/**
 * Einfaches HTML-Escaping für dynamisch eingefügte Texte.
 * @param {string} value
 * @returns {string}
 */
function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

/**
 * Initialisiert Event-Listener
 */
function initializeEventListeners() {
    // Tab-Button Event-Listener
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            if (tabName) {
                showTab(tabName);
            }
        });
    });

    // Form-Validierung für Create-Tab
    const createForm = document.getElementById('createStationForm');
    if (createForm) {
        createForm.addEventListener('submit', function(event) {
            if (!validateCreateForm()) {
                event.preventDefault();
            }
        });
    }

    // Längenbeschränkung für Eingabefelder
    const nameInput = document.getElementById('name');
    if (nameInput) {
        nameInput.addEventListener('input', function() {
            enforceMaxLength(this, 32);
        });
    }

    const nrInput = document.getElementById('Nr');
    if (nrInput) {
        nrInput.addEventListener('input', function() {
            enforceIntegerMaxLength(this, 2147483647); // INT(11) Maximum
        });
    }

    // Tastatur-Navigation für Accessibility
    document.addEventListener('keydown', handleKeyboardNavigation);
}

/**
 * Stellt sicher, dass die maximale Zeichenlänge nicht überschritten wird
 * @param {HTMLElement} element - Das Eingabeelement
 * @param {number} maxLength - Maximale Anzahl Zeichen
 */
function enforceMaxLength(element, maxLength) {
    if (element.value.length > maxLength) {
        element.value = element.value.substring(0, maxLength);
    }
}

/**
 * Stellt sicher, dass nur Zahlen eingegeben werden und der INT-Bereich nicht überschritten wird
 * @param {HTMLElement} element - Das Eingabeelement
 * @param {number} maxValue - Maximaler Integer-Wert
 */
function enforceIntegerMaxLength(element, maxValue) {
    // Nur Zahlen erlauben
    element.value = element.value.replace(/[^0-9]/g, '');

    // Prüfen ob der Wert den maximalen Integer-Wert überschreitet
    const value = parseInt(element.value);
    if (value > maxValue) {
        element.value = maxValue.toString();
    }
}

/**
 * Validiert das Create-Formular
 * @returns {boolean} True wenn valid, false wenn nicht
 */
function validateCreateForm() {
    const nameInput = document.getElementById('name');
    const nrInput = document.getElementById('Nr');
    let isValid = true;

    // Name-Validierung
    if (!nameInput.value.trim()) {
        showValidationError(nameInput, 'Bitte geben Sie einen Namen für die Station ein.');
        isValid = false;
    } else if (nameInput.value.trim().length > 32) {
        showValidationError(nameInput, 'Der Name darf maximal 32 Zeichen lang sein.');
        isValid = false;
    } else {
        clearValidationError(nameInput);
    }

    // Stationsnummer-Validierung
    if (!nrInput.value.trim()) {
        showValidationError(nrInput, 'Bitte geben Sie eine Stationsnummer ein.');
        isValid = false;
    } else if (isNaN(nrInput.value.trim()) || parseInt(nrInput.value.trim()) <= 0) {
        showValidationError(nrInput, 'Die Stationsnummer muss eine positive Zahl sein.');
        isValid = false;
    } else if (parseInt(nrInput.value.trim()) > 2147483647) {
        showValidationError(nrInput, 'Die Stationsnummer ist zu groß.');
        isValid = false;
    } else {
        clearValidationError(nrInput);
    }

    return isValid;
}

/**
 * Zeigt eine Validierungsfehlermeldung an
 * @param {HTMLElement} element - Das Eingabeelement
 * @param {string} message - Die Fehlermeldung
 */
function showValidationError(element, message) {
    // Element als fehlerhaft markieren
    element.classList.add('error');

    // Bestehende Fehlermeldung entfernen
    clearValidationError(element);

    // Neue Fehlermeldung erstellen
    const errorDiv = document.createElement('div');
    errorDiv.className = 'validation-message show';
    errorDiv.textContent = message;
    errorDiv.id = element.id + '_error';

    // Fehlermeldung nach dem Element einfügen
    element.parentNode.insertBefore(errorDiv, element.nextSibling);
}

/**
 * Entfernt Validierungsfehlermeldungen
 * @param {HTMLElement} element - Das Eingabeelement
 */
function clearValidationError(element) {
    element.classList.remove('error');
    const errorDiv = document.getElementById(element.id + '_error');
    if (errorDiv) {
        errorDiv.remove();
    }
}

/**
 * Bestätigt das Löschen einer Station
 * @param {number} id - ID der zu löschenden Station
 * @param {string} name - Name der Station
 */
function confirmDeleteStation(id, name) {
    deleteStationId = id;

    // Modal-Text aktualisieren
    const modal = document.getElementById('confirmDeleteModal');
    if (modal) {
        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) {
            modalBody.innerHTML = `
                <p>Möchten Sie die Station <strong>"${name}"</strong> wirklich löschen?</p>
                <p><em>Alle dazugehörigen Protokolle mit den jeweiligen Werten werden ebenfalls gelöscht.</em></p>
            `;
        }

        // Modal anzeigen
        showModal('confirmDeleteModal');
    }
}

/**
 * Löscht die bestätigte Station
 */
function deleteStation() {
    if (deleteStationId) {
        // Formular erstellen und absenden
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const deleteIdInput = document.createElement('input');
        deleteIdInput.type = 'hidden';
        deleteIdInput.name = 'delete_ID';
        deleteIdInput.value = deleteStationId;

        const deleteStationInput = document.createElement('input');
        deleteStationInput.type = 'hidden';
        deleteStationInput.name = 'delete_station';
        deleteStationInput.value = '1';

        form.appendChild(deleteIdInput);
        form.appendChild(deleteStationInput);
        document.body.appendChild(form);
        form.submit();
    }

    closeModal('confirmDeleteModal');
}

/**
 * Zeigt ein Modal an
 * @param {string} modalId - ID des Modals
 */
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Fokus auf das erste fokussierbare Element setzen
        const focusableElement = modal.querySelector('button, input, select');
        if (focusableElement) {
            setTimeout(() => focusableElement.focus(), 100);
        }
    }
}

/**
 * Schließt ein Modal
 * @param {string} modalId - ID des Modals
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        deleteStationId = null;
    }
}

/**
 * Behandelt Tastatur-Navigation
 * @param {KeyboardEvent} event - Das Tastatur-Event
 */
function handleKeyboardNavigation(event) {
    // ESC schließt Modals
    if (event.key === 'Escape') {
        const activeModal = document.querySelector('.modal.active');
        if (activeModal) {
            closeModal(activeModal.id);
        }
    }

    // Tab-Navigation zwischen Tabs
    if (event.key >= '1' && event.key <= '4' && event.ctrlKey) {
        event.preventDefault();
        const tabs = ['overview', 'create', 'assign', 'assignment-overview'];
        const tabIndex = parseInt(event.key) - 1;
        if (tabs[tabIndex]) {
            showTab(tabs[tabIndex]);
        }
    }
}

/**
 * Utility-Funktion für das Anzeigen von Nachrichten
 * @param {string} message - Die anzuzeigende Nachricht
 * @param {string} type - Typ der Nachricht (success, error, info)
 */
function showMessage(message, type = 'info') {
    // Bestehende Nachrichten entfernen
    const existingMessages = document.querySelectorAll('.message-box');
    existingMessages.forEach(msg => msg.remove());

    // Neue Nachricht erstellen
    const messageDiv = document.createElement('div');
    messageDiv.className = `message-box ${type}`;
    messageDiv.textContent = message;

    // Nachricht am Anfang des main-content einfügen
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.insertBefore(messageDiv, mainContent.firstChild);

        // Nachricht nach 5 Sekunden automatisch entfernen
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }
}

/**
 * Event-Listener für Modal-Schließen beim Klick außerhalb
 */
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal(event.target.id);
    }
});

// Globale Funktionen für HTML-Inline-Events
window.showTab = showTab;
window.confirmDeleteStation = confirmDeleteStation;
window.deleteStation = deleteStation;
window.closeModal = closeModal;
window.loadStationCheckboxes = loadStationCheckboxes;
window.selectAllStationen = selectAllStationen;
window.deselectAllStationen = deselectAllStationen;