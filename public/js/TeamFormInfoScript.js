/**
 * TeamFormInfoScript.js
 * Erweiterte JavaScript-Funktionalität für die TeamFormInfoView
 */

document.addEventListener('DOMContentLoaded', function() {
    // Tab-Funktionalität initialisieren
    initTabs();

    // Event-Listener für den "Formular erstellen"-Button
    const createFormBtn = document.getElementById('createFormBtn');
    if (createFormBtn) {
        createFormBtn.addEventListener('click', function() {
            document.getElementById('createFormModal').style.display = 'block';
        });
    }

    // Event Listener für Escape-Taste zum Schließen von Modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });

    // Alle Modals beim Klicken außerhalb schließen
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
});

/**
 * Initialisiert die Tab-Funktionalität
 */
function initTabs() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    // Standardmäßig den ersten Tab anzeigen
    if (tabContents.length > 0) {
        tabContents[0].style.display = 'block';
    }

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Alle Tab-Inhalte ausblenden
            tabContents.forEach(tab => tab.style.display = 'none');

            // Alle Tab-Buttons deaktivieren
            tabButtons.forEach(btn => btn.classList.remove('active'));

            // Ausgewählten Tab anzeigen und Button aktivieren
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId).style.display = 'block';
            this.classList.add('active');
        });
    });
}

/**
 * Lädt die Details einer Mannschaft per AJAX
 * @param {number} teamId - Die ID des Teams
 */
function loadTeamDetails(teamId) {
    const modalContent = document.getElementById('teamDetailsContent');
    if (!modalContent) return;

    // Lade-Animation anzeigen
    modalContent.innerHTML = '<div class="loading-spinner"></div>';

    // Modal anzeigen
    const modal = document.getElementById('teamDetailsModal');
    if (modal) {
        modal.style.display = 'block';
    }

    // AJAX-Anfrage
    fetch(`../controller/TeamFormController.php?action=get_team_forms&team_id=${teamId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Netzwerkantwort war nicht in Ordnung');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const teamName = document.querySelector(`[data-team-id="${teamId}"]`).closest('tr').querySelector('td:first-child').textContent;
                renderTeamDetails(data.forms, teamName);
            } else {
                modalContent.innerHTML = `<p class="error-message">${escapeHtml(data.message || 'Ein Fehler ist aufgetreten')}</p>`;
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Daten:', error);
            modalContent.innerHTML = '<p class="error-message">Fehler beim Laden der Daten.</p>';
        });
}

/**
 * Lädt die Details eines Formulars per AJAX
 * @param {number} formId - Die ID des Formulars
 */
function showFormDetails(formId) {
    const modalContent = document.getElementById('formDetailsContent');
    if (!modalContent) return;

    // Lade-Animation anzeigen
    modalContent.innerHTML = '<div class="loading-spinner"></div>';

    // Modal anzeigen
    const modal = document.getElementById('formDetailsModal');
    if (modal) {
        modal.style.display = 'block';
    }

    // AJAX-Anfrage
    fetch(`../controller/TeamFormController.php?action=get_form_statistics&form_id=${formId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Netzwerkantwort war nicht in Ordnung');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                renderFormDetails(data.statistics);
            } else {
                modalContent.innerHTML = `<p class="error-message">${escapeHtml(data.message || 'Ein Fehler ist aufgetreten')}</p>`;
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Daten:', error);
            modalContent.innerHTML = '<p class="error-message">Fehler beim Laden der Daten.</p>';
        });
}

/**
 * Rendert die Team-Details in das Modal
 * @param {Array} forms - Die Formulare des Teams
 * @param {string} teamName - Der Name des Teams
 */
function renderTeamDetails(forms, teamName) {
    const modalContent = document.getElementById('teamDetailsContent');
    const modalTitle = document.querySelector('#teamDetailsModal .modal-title');

    if (modalTitle) {
        modalTitle.textContent = `Formularergebnisse: ${teamName}`;
    }

    if (!forms || forms.length === 0) {
        modalContent.innerHTML = '<p class="info-message">Keine Formulare gefunden oder noch keine Formulare ausgefüllt.</p>';
        return;
    }

    // Tabelle mit den Details erstellen
    let html = `
        <div class="team-details-summary">
            <div class="summary-item">
                <span class="summary-label">Bearbeitete Formulare:</span>
                <span class="summary-value">${forms.length}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Abgeschlossene Formulare:</span>
                <span class="summary-value">${forms.filter(form => form.completed === 1).length}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Gesamtpunkte:</span>
                <span class="summary-value">${calculateTotalPoints(forms)}</span>
            </div>
        </div>

        <table class="details-table">
            <thead>
                <tr>
                    <th>Formular</th>
                    <th>Station</th>
                    <th>Status</th>
                    <th>Punkte</th>
                    <th>Abgeschlossen am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
    `;

    forms.forEach(form => {
        const isCompleted = form.completed === 1;
        const statusBadgeClass = isCompleted ? 'completed' : 'pending';
        const statusText = isCompleted ? 'Abgeschlossen' : 'Ausstehend';
        const questionCount = form.question_count || '?';
        const pointsDisplay = isCompleted ? `${form.points}/${questionCount}` : '-';
        const dateDisplay = isCompleted && form.completion_date ? formatDate(form.completion_date) : '-';

        html += `
            <tr>
                <td>${escapeHtml(form.Titel || 'Unbekanntes Formular')}</td>
                <td>${escapeHtml(form.station_name || '-')}</td>
                <td><span class="status-badge ${statusBadgeClass}">${statusText}</span></td>
                <td>${escapeHtml(String(pointsDisplay))}</td>
                <td>${escapeHtml(String(dateDisplay))}</td>
                <td>
                    <a href="../view/FormView.php?token=${encodeURIComponent(form.token || '')}" target="_blank" class="details-button small">
                        Öffnen
                    </a>
                </td>
            </tr>
        `;
    });

    html += `
            </tbody>
        </table>
    `;

    modalContent.innerHTML = html;
}

/**
 * Berechnet die Gesamtpunktzahl aus einer Liste von Formularen
 * @param {Array} forms - Liste der Formulare mit Punktestand
 * @returns {number} Gesamtpunktzahl
 */
function calculateTotalPoints(forms) {
    return forms.reduce((total, form) => {
        return total + (form.completed === 1 ? parseInt(form.points) || 0 : 0);
    }, 0);
}

/**
 * Formatiert ein Datum für die Anzeige
 * @param {string} dateString - Das zu formatierende Datum
 * @returns {string} Das formatierte Datum
 */
function formatDate(dateString) {
    if (!dateString) return '-';

    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    return date.toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Schließt das angegebene Modal
 * @param {string} modalId - Die ID des zu schließenden Modals
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Schließt alle Modals
 */
function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
}

/**
 * Zeigt eine Bestätigung an, bevor ein Formular gelöscht wird
 * @param {number} formId - Die ID des zu löschenden Formulars
 * @returns {boolean} False um das Form-Submit zu verhindern
 */
function confirmDelete(formId) {
    document.getElementById('delete_form_id').value = formId;
    document.getElementById('confirmDeleteModal').classList.add('active');
    return false;
}

/**
 * Rendert die Formular-Details in das Modal
 * @param {Object} stats - Die Statistikdaten des Formulars
 */
function renderFormDetails(stats) {
    const modalContent = document.getElementById('formDetailsContent');
    const modalTitle = document.querySelector('#formDetailsModal .modal-title');

    if (modalTitle) {
        modalTitle.textContent = `Formular: ${stats.form_title}`;
    }

    // Zusammenfassung der Formular-Statistiken
    let html = `
        <div class="form-details-summary">
            <div class="summary-item">
                <span class="summary-label">Anzahl Fragen:</span>
                <span class="summary-value">${stats.question_count}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Zugewiesene Teams:</span>
                <span class="summary-value">${stats.total_teams}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Abgeschlossen:</span>
                <span class="summary-value">${stats.completed_count} / ${stats.total_teams}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Durchschnitt:</span>
                <span class="summary-value">${stats.average_points} Punkte</span>
            </div>
        </div>

        <h4 class="section-title">Team-Ergebnisse</h4>
        <table class="details-table">
            <thead>
                <tr>
                    <th>Mannschaft</th>
                    <th>Kreisverband</th>
                    <th>Status</th>
                    <th>Punkte</th>
                    <th>Abschlussdatum</th>
                </tr>
            </thead>
            <tbody>
    `;

    if (!stats.teams || stats.teams.length === 0) {
        html += `
            <tr>
                <td colspan="5" class="no-data">Noch keine Ergebnisse vorhanden.</td>
            </tr>
        `;
    } else {
        stats.teams.forEach(team => {
            const isCompleted = team.completed === 1;
            const statusBadgeClass = isCompleted ? 'completed' : 'pending';
            const statusText = isCompleted ? 'Abgeschlossen' : 'Ausstehend';
            const points = isCompleted ? team.points : '-';
            const date = isCompleted && team.completion_date ? formatDate(team.completion_date) : '-';

            html += `
                <tr>
                    <td>${escapeHtml(team.Teamname || 'Unbekannt')}</td>
                    <td>${escapeHtml(team.Kreisverband || '-')}</td>
                    <td><span class="status-badge ${statusBadgeClass}">${statusText}</span></td>
                    <td>${escapeHtml(String(points))}</td>
                    <td>${escapeHtml(String(date))}</td>
                </tr>
            `;
        });
    }

    html += `
            </tbody>
        </table>
    `;

    modalContent.innerHTML = html;
}

/**
 * Escape HTML for safe insertion into innerHTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}