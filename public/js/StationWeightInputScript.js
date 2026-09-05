/**
 * StationWeightInputScript.js
 * Wertungs-abhängige Stationsgewichtung.
 *
 * Pro Wertung ergeben die Stationen zusammen immer exakt 100 %. Beim Ändern
 * eines Wertes gleichen sich die nicht gesperrten Stationen automatisch an;
 * gesperrte Felder sind deaktiviert und behalten ihren Wert. Eingaben werden
 * so begrenzt, dass eine gültige 100%-Aufteilung erhalten bleibt.
 *
 * Die Verteil-Logik (Gleichverteilung mit vorne aufgefülltem Rest) ist 1:1
 * identisch mit WeightDistribution.php – Reihenfolge ist die DOM-Order (Nr, name).
 */

document.addEventListener('DOMContentLoaded', function () {
    // Falls eine Wertung vorausgewählt ist (z. B. nach dem Speichern): direkt laden.
    const select = document.getElementById('weightWertung');
    if (select && select.value) {
        loadStationWeights();
    }
});

/**
 * Lädt die Stationen + wirksame Startgewichte der gewählten Wertung per AJAX
 * und baut die Eingabezeilen auf.
 */
function loadStationWeights() {
    const select = document.getElementById('weightWertung');
    const container = document.getElementById('weightContainer');
    const empty = document.getElementById('weightEmpty');
    const list = document.getElementById('weightStationList');
    const hiddenWertung = document.getElementById('weightFormWertung');
    if (!select || !container || !empty || !list || !hiddenWertung) {
        return;
    }

    const wertungId = select.value;
    hiddenWertung.value = wertungId || '';
    container.style.display = 'none';
    empty.style.display = 'none';

    if (!wertungId) {
        return;
    }

    list.innerHTML = '<tr><td colspan="4">Lade Stationen…</td></tr>';
    container.style.display = 'block';

    const url = `StationWeightInputView.php?action=getWeightsForWertung&wertung=${encodeURIComponent(wertungId)}`;
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                list.innerHTML = `<tr><td colspan="4" class="warning">${escapeHtml(data.error || 'Fehler beim Laden.')}</td></tr>`;
                return;
            }

            const stationen = data.stationen || [];
            if (stationen.length === 0) {
                container.style.display = 'none';
                empty.style.display = 'block';
                return;
            }

            list.innerHTML = '';
            stationen.forEach(station => {
                const id = Number(station.ID);
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${escapeHtml(station.name)}</strong></td>
                    <td>${escapeHtml(String(station.Nr))}</td>
                    <td>
                        <input type="number" class="weight-input" min="0" max="100" step="1"
                               name="weights[${id}]" id="weight-${id}" data-station="${id}"
                               value="${Number(station.weight)}">
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" class="weight-lock" data-station="${id}" id="lock-${id}">
                    </td>
                `;
                list.appendChild(row);
            });

            // Event-Listener verbinden
            list.querySelectorAll('.weight-input').forEach(input => {
                input.addEventListener('input', function () {
                    onWeightInput(Number(this.dataset.station));
                });
            });
            list.querySelectorAll('.weight-lock').forEach(cb => {
                cb.addEventListener('change', function () {
                    onLockToggle(Number(this.dataset.station));
                });
            });

            updateSumAndHint();
        })
        .catch(error => {
            list.innerHTML = '<tr><td colspan="4" class="warning">Fehler beim Laden der Stationen.</td></tr>';
            console.error('loadStationWeights:', error);
        });
}

/**
 * Liefert den Zustand aller Stationszeilen in DOM-Reihenfolge.
 * @returns {{id:number,input:HTMLInputElement,locked:boolean,value:number}[]}
 */
function getRows() {
    const inputs = document.querySelectorAll('#weightStationList .weight-input');
    return Array.from(inputs).map(input => {
        const id = Number(input.dataset.station);
        const lock = document.getElementById('lock-' + id);
        return {
            id: id,
            input: input,
            locked: !!(lock && lock.checked),
            value: parseInt(input.value, 10) || 0,
        };
    });
}

/**
 * Ausgleich beim Ändern einer (nicht gesperrten) Station auf ihren neuen Wert.
 * @param {number} editedId
 */
function onWeightInput(editedId) {
    const rows = getRows();
    const edited = rows.find(r => r.id === editedId);
    if (!edited || edited.locked) {
        return;
    }

    const lockedSum = rows.filter(r => r.locked).reduce((s, r) => s + r.value, 0);
    const maxForEdited = Math.max(0, 100 - lockedSum);

    // Eingabe begrenzen, sodass eine gültige 100%-Aufteilung möglich bleibt.
    let v = parseInt(edited.input.value, 10);
    if (isNaN(v) || v < 0) v = 0;
    if (v > maxForEdited) v = maxForEdited;

    const others = rows.filter(r => !r.locked && r.id !== editedId);
    const u = others.length;

    if (u === 0) {
        // Einziger Freiheitsgrad: auf den Restwert fixieren.
        v = maxForEdited;
    } else {
        // Restbudget gleichmäßig (Rest vorne) auf die übrigen entsperrten verteilen.
        const remaining = Math.max(0, 100 - lockedSum - v);
        const base = Math.floor(remaining / u);
        const rest = remaining - base * u;
        others.forEach((r, idx) => {
            r.input.value = idx < rest ? base + 1 : base;
        });
    }

    edited.input.value = v;
    updateSumAndHint();
}

/**
 * Beim Sperren/Entsperren: Feld (de)aktivieren. Die Werte selbst bleiben gültig
 * (Summe == 100), daher kein Neuausgleich nötig.
 * @param {number} stationId
 */
function onLockToggle(stationId) {
    const input = document.getElementById('weight-' + stationId);
    const lock = document.getElementById('lock-' + stationId);
    if (input && lock) {
        // readonly statt disabled: gesperrte Werte werden weiterhin mit abgesendet,
        // sind aber nicht editierbar. Der Ausgleich überspringt gesperrte Felder ohnehin.
        input.readOnly = lock.checked;
        input.classList.toggle('locked', lock.checked);
    }
    updateSumAndHint();
}

/**
 * Aktualisiert die Summenanzeige und den Hinweistext.
 */
function updateSumAndHint() {
    const rows = getRows();
    const sum = rows.reduce((s, r) => s + r.value, 0);

    const sumEl = document.getElementById('weightSum');
    if (sumEl) {
        sumEl.textContent = String(sum);
        sumEl.style.color = (sum === 100) ? '' : 'var(--color-danger, #c0392b)';
    }

    const hint = document.getElementById('weightHint');
    if (hint) {
        const unlocked = rows.filter(r => !r.locked).length;
        if (rows.length > 0 && unlocked === 0) {
            hint.textContent = 'Alle Stationen sind gesperrt – entsperren Sie mindestens eine, um Werte zu ändern.';
        } else {
            hint.textContent = '';
        }
    }

    const saveBtn = document.querySelector('#weightForm button[name="save_weights"]');
    if (saveBtn) {
        saveBtn.disabled = (sum !== 100);
    }
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

// Globale Funktionen für HTML-Inline-Events
window.loadStationWeights = loadStationWeights;
