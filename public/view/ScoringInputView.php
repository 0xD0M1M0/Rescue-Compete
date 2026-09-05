<?php

// Einbinden der notwendigen Dateien für die Datenbankverbindung, das Model, den Controller und den Mannschaft-Model
require_once '../db/DbConnection.php';
require_once '../model/ScoringModel.php';
require_once '../controller/ScoringInputController.php';
require_once '../model/TeamModel.php';
require_once '../php_assets/CustomAlertBox.php';
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';

use Mannschaft\TeamModel;
use model\ScoringModel;
use Scoring\ScoringInputController;

require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

// Weiterleitung, wenn Berechtigungen nicht korrekt sind
$allowedAccountTypes = ['Wettkampfleitung', 'Admin'];
if(!isset($_SESSION["acc_typ"]) || !in_array($_SESSION["acc_typ"], $allowedAccountTypes)){
    header("Location: ../index.php");
    exit;
}

// Überprüfen, ob die Datenbankverbindung ($conn) verfügbar ist
if (!isset($conn)) {
    require __DIR__ . '/../php_assets/DbErrorPage.php'; die();
}

// Instanzierung des Models und des Controllers
$model = new ScoringModel($conn);
$controller = new ScoringInputController($model);

// Verarbeitung des aktuellen POST-Requests (z. B. Hinzufügen, Löschen, Zuweisen)
$controller->handleRequest();

// Erfolgsmeldungen aus der Session holen
$successMessage = "";
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Ausgewählte Wertung aus Session holen (für Remove-Tab)
$selectedWertung = "";
if (isset($_SESSION['selected_wertung'])) {
    $selectedWertung = $_SESSION['selected_wertung'];
    unset($_SESSION['selected_wertung']);
}

// Ergebnisse aus dem Controller abrufen
$modalData = $controller->modalData;
$message = $controller->message;

// Wertungsklassen aus der Datenbank lesen und Mannschaften abrufen
$wertungsklassen = $model->read();
$mannschaften = (new TeamModel($conn))->getAllMannschaften();

// Falls ein Name per POST übermittelt wurde, wird er bereinigt übernommen
$name = isset($_POST['name']) ? trim($_POST['name']) : "";

// Aktuelle Ansicht bestimmen
$currentView = sanitize_view_param($_GET['view'] ?? null, ['overview', 'create', 'assign', 'remove'], 'overview');

// Seiten-Titel
$pageTitle = "Verwaltung der Wertungsklassen";
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Dynamischer Seitentitel -->
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logos/ww-favicon.ico">
    <!-- CSS-Dateien einbinden -->
    <link rel="stylesheet" href="../css/Colors.css">
    <link rel="stylesheet" href="../css/GlobalLayout.css">
    <link rel="stylesheet" href="../css/Navbar.css">
    <link rel="stylesheet" href="../css/Sidebar.css">
    <link rel="stylesheet" href="../css/Footer.css">
    <link rel="stylesheet" href="../css/Components.css">
    <link rel="stylesheet" href="../css/ScoringInputViewStyling.css">

    <!-- JavaScript-Konstanten übergeben -->
    <script>
        const mannschaften = <?php echo json_encode_for_js($mannschaften); ?>;
        const wertungsklassen = <?php echo json_encode_for_js($wertungsklassen); ?>;
        const selectedWertung = <?php echo json_encode_for_js($selectedWertung); ?>;
    </script>
</head>
<body class="has-navbar">

<!-- Navbar wird eingebunden -->
<?php include '../php_assets/Navbar.php'; ?>

<div class="container">
    <!-- Sidebar wird eingebunden -->
    <?php include '../php_assets/Sidebar.php'; ?>

    <!-- Hauptinhalt -->
    <div class="main-content vertical">

        <!-- Navigation Tabs -->
        <div class="tab-navigation">
            <button class="tab-button <?php echo $currentView === 'overview' ? 'active' : ''; ?>"
                    data-tab="overview"
                    onclick="showTab('overview')">Übersicht</button>
            <button class="tab-button <?php echo $currentView === 'create' ? 'active' : ''; ?>"
                    data-tab="create"
                    onclick="showTab('create')">Neue Wertungsklasse</button>
            <button class="tab-button <?php echo $currentView === 'assign' ? 'active' : ''; ?>"
                    data-tab="assign"
                    onclick="showTab('assign')">Teams zuweisen</button>
            <button class="tab-button <?php echo $currentView === 'remove' ? 'active' : ''; ?>"
                    data-tab="remove"
                    onclick="showTab('remove')">Teams entfernen</button>
        </div>

        <!-- Erfolgsmeldungen -->
        <?php if (!empty($successMessage)): ?>
            <div class="message-box success">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Fehlermeldungen -->
        <?php if (!empty($message)): ?>
            <div class="message-box error">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Tab: Übersicht -->
        <div id="overview" class="tab-content <?php echo $currentView === 'overview' ? 'active' : ''; ?>">
            <div class="data-container">
                <div class="actions-bar">
                    <button class="btn primary-btn" onclick="showTab('create')">
                        Neue Wertungsklasse erstellen
                    </button>
                    <!--   <button class="btn secondary-btn" onclick="showTab('assign')">
                        Teams zuweisen
                    </button>  -->
                    <button class="btn warning-btn" onclick="showTab('remove')">
                        Teams entfernen
                    </button>
                </div>

                <?php if (empty($wertungsklassen)): ?>
                    <div class="no-data">
                        <p>Keine Wertungsklassen vorhanden.</p>
                        <p><a href="#" onclick="showTab('create')">Erstellen Sie Ihre erste Wertungsklasse</a></p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th data-sort-key="name" width="20%">Name</th>
                            <th width="40%">Zugehörige Mannschaften</th>
                            <th data-sort-key="count" data-sort-type="number" width="15%">Anzahl Teams</th>
                            <th width="25%">Aktionen</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($wertungsklassen as $wertung): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($wertung['wertung_name'] ?? "nicht gefunden"); ?></strong>
                                </td>
                                <td><?php echo nl2br(htmlspecialchars(str_replace(', ', "\n", $wertung['teams'] ?? "keine Teams"))); ?></td>
                                <td class="numeric-cell">
                                    <?php
                                    $teamsCount = !empty($wertung['teams']) && $wertung['teams'] !== 'keine Teams'
                                        ? count(explode(', ', $wertung['teams']))
                                        : 0;
                                    echo $teamsCount;
                                    ?>
                                </td>
                                <td class="action-cell">
                                    <div class="button-group">
                                        <button class="btn small" onclick="showAssignmentForWertung(<?php echo json_encode_for_js($wertung['wertung_name']); ?>)">
                                            Teams bearbeiten
                                        </button>
                                        <button class="btn warning-btn small" onclick="showRemovalForWertung(<?php echo json_encode_for_js($wertung['wertung_name']); ?>)">
                                            Teams entfernen
                                        </button>
                                        <button class="btn warning-btn small" onclick="confirmDeleteWertung(<?php echo (int)$wertung['wertung_id']; ?>, <?php echo json_encode_for_js($wertung['wertung_name']); ?>)">
                                            Löschen
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Neue Wertungsklasse -->
        <div id="create" class="tab-content <?php echo $currentView === 'create' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Neue Wertungsklasse erstellen</h3>

                <form method="POST" id="createScoringForm">
                    <input type="hidden" name="add_scoring" value="1">

                    <div class="form-group">
                        <label for="name">Name der Wertungsklasse *</label>
                        <input type="text" id="name" name="name" required
                               placeholder="z.B. TH Damen, SN Gemischt"
                               value="<?= htmlspecialchars($name); ?>">
                        <small>Geben Sie einen eindeutigen Namen für die Wertungsklasse ein</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn primary-btn">Wertungsklasse erstellen</button>
                        <button type="button" class="btn" onclick="showTab('overview')">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab: Teams zuweisen -->
        <div id="assign" class="tab-content <?php echo $currentView === 'assign' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Teams zu einer Wertungsklasse zuweisen</h3>

                <form id="assignTeamsForm" method="POST">
                    <div class="form-group">
                        <label for="wertung">Zugehörige Wertungsklasse *</label>
                        <select id="wertung" name="wertung" required onchange="loadTeamCheckboxes()">
                            <option value="" disabled selected hidden>Bitte auswählen</option>
                        </select>
                        <small>Wählen Sie die Wertungsklasse aus, der Sie Teams zuweisen möchten</small>
                    </div>

                    <!-- Container für Team-Checkboxen -->
                    <div id="teamCheckboxContainer" style="display: none;">
                        <div class="form-group">
                            <label>Mannschaften auswählen</label>
                            <div class="team-selection-actions">
                                <button type="button" class="btn secondary-btn small" onclick="selectAllAssignTeams()">Alle auswählen</button>
                                <button type="button" class="btn secondary-btn small" onclick="deselectAllAssignTeams()">Alle abwählen</button>
                            </div>
                            <div id="teamCheckboxList" class="assigned-teams-list">
                                <!-- Dynamisch geladen -->
                            </div>
                        </div>

                        <!-- Aktionen -->
                        <div class="form-actions">
                            <button type="submit" name="add_team" class="btn primary-btn">Teams zuweisen</button>
                            <button type="button" class="btn" onclick="showTab('overview')">Abbrechen</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab: Teams entfernen -->
        <div id="remove" class="tab-content <?php echo $currentView === 'remove' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Teams aus Wertungsklassen entfernen</h3>
                <p class="tab-description">Wählen Sie eine Wertungsklasse aus, um einzelne Teams zu entfernen.</p>

                <form id="removeTeamsForm" method="POST">
                    <div class="form-group">
                        <label for="removeWertung">Wertungsklasse *</label>
                        <select id="removeWertung" name="wertung" required onchange="loadAssignedTeams()">
                            <option value="" disabled selected hidden>Bitte auswählen</option>
                        </select>
                        <small>Wählen Sie die Wertungsklasse aus, aus der Sie Teams entfernen möchten</small>
                    </div>

                    <!-- Container für zugewiesene Teams -->
                    <div id="assignedTeamsContainer" style="display: none;">
                        <div class="form-group">
                            <label>Zugewiesene Teams</label>
                            <div id="assignedTeamsList" class="assigned-teams-list">
                                <!-- Teams werden hier dynamisch geladen -->
                            </div>
                            <small>Wählen Sie die Teams aus, die Sie aus der Wertungsklasse entfernen möchten</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="remove_selected_teams" class="btn warning-btn" disabled>
                                Ausgewählte Teams entfernen
                            </button>
                            <button type="button" class="btn secondary-btn" onclick="selectAllTeams()">
                                Alle auswählen
                            </button>
                            <button type="button" class="btn secondary-btn" onclick="deselectAllTeams()">
                                Alle abwählen
                            </button>
                            <button type="button" class="btn" onclick="showTab('overview')">Abbrechen</button>
                        </div>
                    </div>

                    <div id="noTeamsMessage" class="no-teams-message" style="display: none;">
                        <p>Dieser Wertungsklasse sind keine Teams zugewiesen.</p>
                        <p><a href="#" onclick="showAssignmentForWertung(document.getElementById('removeWertung').value)">Teams zu dieser Wertungsklasse hinzufügen</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Modals für Alerts
if (!empty($modalData) && isset($modalData['duplicate'])):
    echo CustomAlertBox::renderSimpleAlert(
        "duplicateScoringAlert",
        "Wertung existiert bereits",
        "Die eingegebene Wertungsklasse existiert bereits."
    );
endif;

echo CustomAlertBox::renderSimpleConfirm(
    "confirmDeleteModal",
    "Löschen bestätigen",
    "Möchten Sie diese Wertungsklasse wirklich löschen? (Die Mannschaften bleiben erhalten.)",
    "deleteWertung()",
    "closeModal('confirmDeleteModal')"
);

echo CustomAlertBox::renderSimpleConfirm(
    "confirmRemoveTeamsModal",
    "Teams entfernen bestätigen",
    "Möchten Sie die ausgewählten Teams wirklich aus der Wertungsklasse entfernen?",
    "confirmTeamRemoval()",
    "closeModal('confirmRemoveTeamsModal')"
);
?>

<!-- JavaScript einbinden -->
<script src="../js/TableSortUtils.js"></script>
<script src="../js/ScoringInputScript.js"></script>

<!-- Tab-Initialisierung sicherstellen -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sicherstellen, dass der korrekte Tab angezeigt wird
        const currentView = <?php echo json_encode_for_js($currentView); ?>;
        showTab(currentView);

        initSortableTable(document.querySelector('.data-table'));

        // Wenn eine Wertung nach dem Entfernen ausgewählt bleiben soll
        if (selectedWertung && currentView === 'remove') {
            setTimeout(function() {
                const removeWertungSelect = document.getElementById('removeWertung');
                if (removeWertungSelect) {
                    removeWertungSelect.value = selectedWertung;
                    loadAssignedTeams();
                }
            }, 100);
        }
    });
</script>

<?php include '../php_assets/Footer.php'; ?>

</body>
</html>