document.addEventListener("DOMContentLoaded", function() {
    const submittedTeamsJSON = document.body.getAttribute("data-submitted-teams");
    const submittedTeams = submittedTeamsJSON ? JSON.parse(submittedTeamsJSON) : [];

    // Originalwerte beim Laden merken
    const originalValues = {};
    document.querySelectorAll("input.time-input").forEach(function(input) {
        originalValues[input.name] = input.value.trim();
    });

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) { modal.classList.remove("active"); }
    };

    function containsLetters(str) {
        return /[a-zA-ZäöüÄÖÜß]/.test(str);
    }

    function showInvalidTimeAlert() {
        const alertBox = document.getElementById("invalidTimeAlert");
        if (alertBox) { alertBox.classList.add("active"); }
    }

    function closeInvalidTimeAlert() {
        const alertBox = document.getElementById("invalidTimeAlert");
        if (alertBox) { alertBox.classList.remove("active"); }
    }

    function showOverwriteConfirmation(teamsToOverwrite, onConfirm) {
        const teamNames = teamsToOverwrite.map(team => team.name).join(", ");
        const message = teamsToOverwrite.length === 1
            ? `Für die Mannschaft "${teamNames}" wurden bereits Ergebnisse eingetragen. Möchten Sie die bestehenden Ergebnisse überschreiben?`
            : `Für folgende Mannschaften wurden bereits Ergebnisse eingetragen: ${teamNames}. Möchten Sie die bestehenden Ergebnisse überschreiben?`;

        const existingModal = document.getElementById("overwriteConfirmModal");
        if (existingModal) { existingModal.remove(); }

        const modal = document.createElement("div");
        modal.id = "overwriteConfirmModal";
        modal.className = "modal active";

        const content = document.createElement("div");
        content.className = "modal-content";

        const heading = document.createElement("h2");
        heading.textContent = "Ergebnisse überschreiben?";

        const paragraph = document.createElement("p");
        paragraph.textContent = message;

        const confirmBtn = document.createElement("button");
        confirmBtn.type = "button";
        confirmBtn.className = "btn primary-btn";
        confirmBtn.textContent = "Ja, überschreiben";
        confirmBtn.addEventListener("click", function() {
            closeModal("overwriteConfirmModal");
            modal.remove();
            onConfirm();
        });

        const cancelBtn = document.createElement("button");
        cancelBtn.type = "button";
        cancelBtn.className = "btn";
        cancelBtn.textContent = "Abbrechen";
        cancelBtn.addEventListener("click", function() {
            closeModal("overwriteConfirmModal");
            modal.remove();
        });

        content.appendChild(heading);
        content.appendChild(paragraph);
        content.appendChild(confirmBtn);
        content.appendChild(cancelBtn);
        modal.appendChild(content);
        document.body.appendChild(modal);

        window.confirmOverwrite = function() {
            closeModal("overwriteConfirmModal");
            const el = document.getElementById("overwriteConfirmModal");
            if (el) el.remove();
            onConfirm();
        };

        window.cancelOverwrite = function() {
            closeModal("overwriteConfirmModal");
            const el = document.getElementById("overwriteConfirmModal");
            if (el) el.remove();
        };
    }

    function isValidTime(timeStr) {
        let minutes, seconds;
        timeStr = timeStr.trim();
        if (timeStr === "") return true;
        if (containsLetters(timeStr)) return false;
        timeStr = timeStr.replace(",", ".");
        if (timeStr.indexOf(":") === -1) {
            seconds = parseFloat(timeStr);
            return !isNaN(seconds) && seconds >= 0;
        }
        const parts = timeStr.split(":");
        if (parts.length === 2) {
            minutes = parseInt(parts[0], 10);
            seconds = parseFloat(parts[1]);
            if (isNaN(minutes) || isNaN(seconds)) return false;
            if (minutes < 0 || minutes >= 60 || seconds < 0 || seconds >= 60) return false;
            return true;
        } else if (parts.length === 3) {
            const hours = parseInt(parts[0], 10);
            minutes = parseInt(parts[1], 10);
            seconds = parseFloat(parts[2]);
            if (isNaN(hours) || isNaN(minutes) || isNaN(seconds)) return false;
            if (minutes < 0 || minutes >= 60 || seconds < 0 || seconds >= 60) return false;
            return true;
        }
        return false;
    }

    const alertCloseButton = document.querySelector("#invalidTimeAlert .close-btn");
    if (alertCloseButton) {
        alertCloseButton.addEventListener("click", closeInvalidTimeAlert);
    }

    const form = document.querySelector("form");
    if (!form) { console.error("Formular nicht gefunden."); return; }

    form.addEventListener("submit", function(event) {
        let formIsValid = true;
        let containsInvalidChars = false;
        let hasTeamsWithChanges = false;
        let teamsToOverwrite = [];

        const teamRows = document.querySelectorAll(".team-row");
        teamRows.forEach(row => {
            if (row.classList.contains("header-row")) return;

            const teamNameElement = row.querySelector(".team-name");
            if (!teamNameElement) return;

            const teamID = teamNameElement.getAttribute("data-team-id");
            const teamName = teamNameElement.textContent.trim().replace(/\s+/g, " ");
            const inputs = row.querySelectorAll("input.time-input");

            let rowHasChanges = false;
            let rowActuallyChanged = false;

            inputs.forEach(input => {
                const value = input.value.trim();
                const original = (originalValues[input.name] || "").trim();

                if (value !== "") {
                    rowHasChanges = true;
                    if (containsLetters(value)) {
                        containsInvalidChars = true;
                        formIsValid = false;
                    } else if (!isValidTime(value)) {
                        formIsValid = false;
                    }
                }

                if (value !== original) {
                    rowActuallyChanged = true;
                }
            });

            if (rowHasChanges) { hasTeamsWithChanges = true; }

            if (rowActuallyChanged && submittedTeams.includes(teamID)) {
                teamsToOverwrite.push({ id: teamID, name: teamName });
            }
        });

        if (containsInvalidChars) {
            event.preventDefault();
            showInvalidTimeAlert();
            return;
        }

        if (!formIsValid) {
            event.preventDefault();
            return;
        }

        if (teamsToOverwrite.length > 0) {
            event.preventDefault();
            showOverwriteConfirmation(teamsToOverwrite, function() {
                form.submit();
            });
            return;
        }
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get("show_alert") === "invalid_time") {
        showInvalidTimeAlert();
    }
});
