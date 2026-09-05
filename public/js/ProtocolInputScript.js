/**
 * ProtocolInputScript.js
 * JavaScript-Funktionalität für die Protokoll-Verwaltung
 */

// Globale Variablen
let currentTab = 'overview';
let protocolCounter = 1;
let deleteProtocolNr = null;

// DOM-Ready
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
    populateDropdowns();
    initializeEventListeners();
    updateProtocolManagementButtons();
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

    // Duplicate-AlertBox aktivieren falls vorhanden
    const duplicateAlert = document.getElementById('confirmDuplicateProtocol');
    if (duplicateAlert) {
        showModal('confirmDuplicateProtocol');
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
        case 'overview':
            // Sicherstellen, dass Filter-Dropdown gefüllt ist
            populateDropdowns();
            // Event-Listener für Filter erneut setzen, falls nötig
            setTimeout(() => {
                const stationFilter = document.getElementById('stationFilter');
                if (stationFilter && !stationFilter.hasAttribute('data-listener-added')) {
                    stationFilter.addEventListener('change', filterByStation);
                    stationFilter.setAttribute('data-listener-added', 'true');
                }
            }, 100);
            break;
        case 'create':
            // Sicherstellen, dass Dropdowns gefüllt sind
            populateDropdowns();
            updateProtocolManagementButtons();
            break;
    }
}

/**
 * Füllt die Dropdown-Menüs mit verfügbaren Daten
 */
function populateDropdowns() {
    // Stationen-Dropdown füllen (Create-Tab)
    const stationSelect = document.getElementById('stationName');
    if (stationSelect && typeof stationen !== 'undefined') {
        // Bestehende Optionen entfernen (außer der ersten)
        while (stationSelect.children.length > 1) {
            stationSelect.removeChild(stationSelect.lastChild);
        }

        // Neue Optionen hinzufügen
        stationen.forEach(station => {
            const option = document.createElement('option');
            option.value = station.ID;
            option.textContent = station.name;
            stationSelect.appendChild(option);
        });
    }

    // Stationen-Filter-Dropdown füllen (Overview-Tab)
    const stationFilter = document.getElementById('stationFilter');
    if (stationFilter && typeof stationen !== 'undefined') {
        // Bestehende Optionen entfernen (außer der ersten)
        while (stationFilter.children.length > 1) {
            stationFilter.removeChild(stationFilter.lastChild);
        }

        // Neue Optionen hinzufügen
        stationen.forEach(station => {
            const option = document.createElement('option');
            option.value = station.name;
            option.textContent = station.name;
            stationFilter.appendChild(option);
        });
    }
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
    const createForm = document.getElementById('createProtocolForm');
    if (createForm) {
        createForm.addEventListener('submit', function(event) {
            if (!validateCreateForm()) {
                event.preventDefault();
            }
        });
    }

    // Filter Event-Listener
    const stationFilter = document.getElementById('stationFilter');
    if (stationFilter) {
        stationFilter.addEventListener('change', filterByStation);
    }

    // Längenbeschränkung für bestehende Eingabefelder
    addInputLengthListeners();

    // Tastatur-Navigation für Accessibility
    document.addEventListener('keydown', handleKeyboardNavigation);
}

/**
 * Fügt Event-Listener für Längenbeschränkungen hinzu
 */
function addInputLengthListeners() {
    // Für das erste Name-Feld (statisch)
    const mainNameInput = document.getElementById('Name');
    if (mainNameInput) {
        mainNameInput.addEventListener('input', function() {
            enforceMaxLength(this, 64);
        });
    }

    // Für das erste Punkte-Feld (statisch)
    const mainPointsInput = document.getElementById('max_Punkte');
    if (mainPointsInput) {
        mainPointsInput.addEventListener('input', function() {
            enforceIntegerMaxLength(this, 2147483647);
        });
    }

    // Für alle dynamisch hinzugefügten Protokoll-Name-Felder
    const nameInputs = document.querySelectorAll('input[name*="[name]"]');
    nameInputs.forEach(input => {
        input.addEventListener('input', function() {
            enforceMaxLength(this, 64);
        });
    });

    // Für alle dynamisch hinzugefügten Punkte-Felder
    const pointsInputs = document.querySelectorAll('input[name*="[points]"]');
    pointsInputs.forEach(input => {
        input.addEventListener('input', function() {
            enforceIntegerMaxLength(this, 2147483647);
        });
    });
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
    const stationSelect = document.getElementById('stationName');
    const protocolEntries = document.querySelectorAll('.protocol-entry');
    let isValid = true;

    // Station-Validierung
    if (!stationSelect.value) {
        showValidationError(stationSelect, 'Bitte wählen Sie eine Station aus.');
        isValid = false;
    } else {
        clearValidationError(stationSelect);
    }

    // Protokoll-Einträge validieren
    protocolEntries.forEach((entry, index) => {
        const nameInput = entry.querySelector('input[type="text"]');
        const punkteInput = entry.querySelector('input[type="number"]');

        // Name-Validierung
        if (!nameInput.value.trim()) {
            showValidationError(nameInput, 'Bitte geben Sie einen Namen für das Protokoll ein.');
            isValid = false;
        } else if (nameInput.value.trim().length > 64) {
            showValidationError(nameInput, 'Der Protokollname darf maximal 64 Zeichen lang sein.');
            isValid = false;
        } else {
            clearValidationError(nameInput);
        }

        // Punkte-Validierung
        if (!punkteInput.value || parseInt(punkteInput.value) <= 0) {
            showValidationError(punkteInput, 'Die Punktzahl muss eine positive Zahl sein.');
            isValid = false;
        } else if (parseInt(punkteInput.value) > 2147483647) {
            showValidationError(punkteInput, 'Die Punktzahl ist zu groß.');
            isValid = false;
        } else {
            clearValidationError(punkteInput);
        }
    });

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
 * Fügt ein neues Protokoll-Eingabefeld hinzu
 */
function addProtocol() {
    const container = document.getElementById('protocolsContainer');
    if (!container) return;

    protocolCounter++;

    // Neues Protokoll-Entry-Element erstellen
    const protocolEntry = document.createElement('div');
    protocolEntry.className = 'protocol-entry';
    protocolEntry.innerHTML = `
        <h4>${protocolCounter}. Protokoll:</h4>
        <div class="form-group">
            <label for="name_${protocolCounter}">Protokollname:</label>
            <input type="text" id="name_${protocolCounter}" name="protocols[${protocolCounter}][name]" required
                   placeholder="z.B. Erste Hilfe Maßnahmen">
        </div>
        <div class="form-group">
            <label for="points_${protocolCounter}">Maximale Punktzahl:</label>
            <input type="number" id="points_${protocolCounter}" name="protocols[${protocolCounter}][points]" required min="1"
                   placeholder="z.B. 100">
        </div>
    `;

    // Element zum Container hinzufügen
    container.appendChild(protocolEntry);

    // Event-Listener für Längenbeschränkung zu den neuen Feldern hinzufügen
    const newNameInput = protocolEntry.querySelector('input[type="text"]');
    const newPointsInput = protocolEntry.querySelector('input[type="number"]');

    if (newNameInput) {
        newNameInput.addEventListener('input', function() {
            enforceMaxLength(this, 64);
        });
    }

    if (newPointsInput) {
        newPointsInput.addEventListener('input', function() {
            enforceIntegerMaxLength(this, 2147483647);
        });
    }

    // Button-Status aktualisieren
    updateProtocolManagementButtons();

    // Animation hinzufügen
    setTimeout(() => {
        protocolEntry.style.opacity = '1';
        protocolEntry.style.transform = 'translateY(0)';
    }, 10);
}

/**
 * Entfernt das letzte Protokoll-Eingabefeld
 */
function removeProtocol() {
    const container = document.getElementById('protocolsContainer');
    if (!container) return;

    const protocolEntries = container.querySelectorAll('.protocol-entry');

    // Mindestens ein Protokoll muss bleiben
    if (protocolEntries.length > 1) {
        const lastEntry = protocolEntries[protocolEntries.length - 1];

        // Animation für das Entfernen
        lastEntry.style.opacity = '0';
        lastEntry.style.transform = 'translateY(-10px)';

        setTimeout(() => {
            lastEntry.remove();
            protocolCounter--;
            updateProtocolManagementButtons();
        }, 300);
    }
}

/**
 * Aktualisiert die Verfügbarkeit der Protokoll-Management-Buttons
 */
function updateProtocolManagementButtons() {
    const addBtn = document.getElementById('addProtocolBtn');
    const removeBtn = document.getElementById('removeProtocolBtn');
    const container = document.getElementById('protocolsContainer');

    if (!container) return;

    const protocolCount = container.querySelectorAll('.protocol-entry').length;

    // Remove-Button nur aktivieren, wenn mehr als ein Protokoll vorhanden
    if (removeBtn) {
        if (protocolCount <= 1) {
            removeBtn.disabled = true;
            removeBtn.classList.add('disabled');
        } else {
            removeBtn.disabled = false;
            removeBtn.classList.remove('disabled');
        }
    }

    // Add-Button Text aktualisieren
    if (addBtn) {
        addBtn.textContent = `${protocolCount + 1}. Protokoll hinzufügen`;
    }
}

/**
 * Bestätigt das Löschen eines Protokolls
 * @param {number} nr - Nummer des zu löschenden Protokolls
 * @param {string} name - Name des Protokolls
 */
function confirmDeleteProtocol(nr, name) {
    deleteProtocolNr = nr;

    // Modal-Text aktualisieren
    const modal = document.getElementById('confirmDeleteModal');
    if (modal) {
        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) {
            modalBody.innerHTML = `
                <p>Möchten Sie das Protokoll <strong>"${escapeHtml(name)}"</strong> wirklich löschen?</p>
                <p><em>Alle zugehörigen Daten werden ebenfalls gelöscht.</em></p>
            `;
        }

        // Modal anzeigen
        showModal('confirmDeleteModal');
    }
}

/**
 * Löscht das bestätigte Protokoll
 */
function deleteProtocol() {
    if (deleteProtocolNr) {
        // Formular erstellen und absenden
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const deleteNrInput = document.createElement('input');
        deleteNrInput.type = 'hidden';
        deleteNrInput.name = 'delete_Nr';
        deleteNrInput.value = deleteProtocolNr;

        const deleteProtocolInput = document.createElement('input');
        deleteProtocolInput.type = 'hidden';
        deleteProtocolInput.name = 'delete_protocol';
        deleteProtocolInput.value = '1';

        form.appendChild(deleteNrInput);
        form.appendChild(deleteProtocolInput);
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
        deleteProtocolNr = null;
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
    if (event.key >= '1' && event.key <= '2' && event.ctrlKey) {
        event.preventDefault();
        const tabs = ['overview', 'create'];
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

/**
 * Filtert die Protokoll-Tabelle nach der ausgewählten Station
 */
function filterByStation() {
    console.log('filterByStation called'); // Debug-Log

    const filterSelect = document.getElementById('stationFilter');
    const table = document.querySelector('#overview .data-table');

    if (!filterSelect) {
        console.error('Filter select not found');
        return;
    }

    if (!table) {
        console.error('Table not found');
        return;
    }

    const selectedStation = filterSelect.value;
    console.log('Selected station:', selectedStation); // Debug-Log

    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;

    rows.forEach(row => {
        const stationCell = row.querySelector('td:nth-child(3)'); // Station ist die 3. Spalte

        if (!stationCell) return;

        const stationName = stationCell.textContent.trim();
        console.log('Row station:', stationName); // Debug-Log

        if (selectedStation === '' || stationName === selectedStation) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    console.log('Visible rows:', visibleCount); // Debug-Log

    // Falls keine Protokolle sichtbar sind, eine Meldung anzeigen
    updateNoDataMessage(visibleCount, selectedStation);
}

/**
 * Aktualisiert die "Keine Daten"-Meldung basierend auf dem Filter
 * @param {number} visibleCount - Anzahl der sichtbaren Zeilen
 * @param {string} selectedStation - Ausgewählte Station
 */
function updateNoDataMessage(visibleCount, selectedStation) {
    const dataContainer = document.querySelector('#overview .data-container');
    const table = dataContainer.querySelector('.data-table');
    const originalNoData = dataContainer.querySelector('.no-data:not(.no-data-filtered)');
    let filteredNoDataDiv = dataContainer.querySelector('.no-data-filtered');

    if (visibleCount === 0 && table) {
        // Originale "Keine Daten"-Meldung verstecken
        if (originalNoData) {
            originalNoData.style.display = 'none';
        }

        // Gefilterte "Keine Daten"-Meldung anzeigen
        if (!filteredNoDataDiv) {
            filteredNoDataDiv = document.createElement('div');
            filteredNoDataDiv.className = 'no-data no-data-filtered';
            table.parentNode.insertBefore(filteredNoDataDiv, table.nextSibling);
        }

        if (selectedStation) {
            filteredNoDataDiv.innerHTML = `
                <p>Keine Protokolle für Station "${selectedStation}" gefunden.</p>
                <p><a href="#" onclick="document.getElementById('stationFilter').value = ''; filterByStation();">Alle Stationen anzeigen</a></p>
            `;
        } else {
            filteredNoDataDiv.innerHTML = `
                <p>Keine Protokolle vorhanden.</p>
                <p><a href="#" onclick="showTab('create')">Erstellen Sie Ihr erstes Protokoll</a></p>
            `;
        }

        filteredNoDataDiv.style.display = 'block';
        table.style.display = 'none';
    } else {
        // Tabelle anzeigen, gefilterte Meldung verstecken
        if (filteredNoDataDiv) {
            filteredNoDataDiv.style.display = 'none';
        }
        if (originalNoData) {
            originalNoData.style.display = 'none'; // Original bleibt versteckt wenn Tabelle sichtbar
        }
        if (table) {
            table.style.display = 'table';
        }
    }
}

/**
 * Escape HTML for safe insertion into innerHTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

// Globale Funktionen für HTML-Inline-Events
window.showTab = showTab;
window.addProtocol = addProtocol;
window.removeProtocol = removeProtocol;
window.confirmDeleteProtocol = confirmDeleteProtocol;
window.deleteProtocol = deleteProtocol;
window.closeModal = closeModal;
window.filterByStation = filterByStation;