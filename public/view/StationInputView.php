<?php

require_once '../db/DbConnection.php';
require_once '../model/StationModel.php';
require_once '../controller/StationInputController.php';
require_once '../php_assets/CustomAlertBox.php';
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';

use Station\StationModel;
use Station\StationInputController;

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
$model = new StationModel($conn);
$controller = new StationInputController($model);
$controller->handleRequest();

// Ergebnisse aus dem Controller abrufen
$modalData = $controller->modalData;
$message = $controller->message;
$stationen = $model->read();

// Daten für die Zuordnungs-Tabs
$wertungen = $model->getAllWertungen();
$assignmentOverview = $model->getAssignmentOverview();

// Erfolgsmeldung aus der Session holen (z. B. nach erfolgreicher Zuordnung)
$successMessage = "";
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Werte aus dem POST-Request (falls vorhanden)
$name = isset($_POST['name']) ? trim($_POST['name']) : "";
$nr = isset($_POST['Nr']) ? trim($_POST['Nr']) : "";

// Aktuelle Ansicht bestimmen
$currentView = sanitize_view_param($_GET['view'] ?? null, ['overview', 'create', 'assign', 'assignment-overview'], 'overview');

// Seiten-Titel
$pageTitle = "Verwaltung der Stationen";
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
    <link rel="stylesheet" href="../css/StationInputStyling.css">
    <!-- Checkbox-/Zuweisungs-Styles werden aus der Wertungs-Ansicht wiederverwendet -->
    <link rel="stylesheet" href="../css/ScoringInputViewStyling.css">
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
                    onclick="showTab('create')">Neue Station</button>
            <button class="tab-button <?php echo $currentView === 'assign' ? 'active' : ''; ?>"
                    data-tab="assign"
                    onclick="showTab('assign')">Wertung zuordnen</button>
            <button class="tab-button <?php echo $currentView === 'assignment-overview' ? 'active' : ''; ?>"
                    data-tab="assignment-overview"
                    onclick="showTab('assignment-overview')">Zuordnungs-Übersicht</button>
        </div>

        <!-- Statusmeldungen -->
        <?php if (!empty($successMessage)): ?>
            <div class="message-box success">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>
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
                        Neue Station erstellen
                    </button>
                </div>

                <?php if (empty($stationen)): ?>
                    <div class="no-data">
                        <p>Keine Stationen vorhanden.</p>
                        <p><a href="#" onclick="showTab('create')">Erstellen Sie Ihre erste Station</a></p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th data-sort-key="name" width="50%">Name</th>
                            <th data-sort-key="nr" data-sort-type="number" width="30%">Stationsnummer</th>
                            <th width="20%">Aktionen</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stationen as $station): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($station['name'] ?? "nicht gefunden"); ?></strong>
                                </td>
                                <td class="numeric-cell"><?php echo htmlspecialchars($station['Nr'] ?? "nicht gefunden"); ?></td>
                                <td class="action-cell">
                                    <div class="button-group">
                                        <button class="btn warning-btn small"
                                                onclick="confirmDeleteStation(<?php echo (int)$station['ID']; ?>, <?php echo json_encode_for_js($station['name']); ?>)">
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

        <!-- Tab: Neue Station -->
        <div id="create" class="tab-content <?php echo $currentView === 'create' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Neue Station erstellen</h3>

                <form method="POST" id="createStationForm">
                    <input type="hidden" name="add_station" value="1">

                    <div class="form-group">
                        <label for="name">Stationsname *</label>
                        <input type="text" id="name" name="name" required
                               placeholder="z.B. Erste Hilfe, Transport"
                               value="<?= htmlspecialchars($name); ?>">
                        <small>Geben Sie einen eindeutigen Namen für die Station ein</small>
                    </div>

                    <div class="form-group">
                        <label for="Nr">Stationsnummer *</label>
                        <input type="text" id="Nr" name="Nr" required
                               placeholder="z.B. 1, 2, 3"
                               value="<?= htmlspecialchars($nr); ?>">
                        <small>Eine Nummer zur Identifizierung der Stationen</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn primary-btn">Station erstellen</button>
                        <button type="button" class="btn" onclick="showTab('overview')">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab: Wertung zuordnen -->
        <div id="assign" class="tab-content <?php echo $currentView === 'assign' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Stationen einer Wertung zuordnen</h3>
                <p class="tab-description">Wählen Sie eine Wertung und haken Sie die Stationen an, die in dieser Wertung gewertet werden. Nur die angehakten Stationen zählen in die Parcours-Punkte dieser Wertung.</p>

                <form id="assignStationenForm" method="POST">
                    <div class="form-group">
                        <label for="assignWertung">Wertung *</label>
                        <select id="assignWertung" name="wertung" required onchange="loadStationCheckboxes()">
                            <option value="" disabled selected hidden>Bitte auswählen</option>
                            <?php foreach ($wertungen as $wertung): ?>
                                <option value="<?php echo htmlspecialchars($wertung['ID']); ?>">
                                    <?php echo htmlspecialchars($wertung['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Wählen Sie die Wertung aus, der Sie Stationen zuordnen möchten</small>
                    </div>

                    <?php if (empty($wertungen)): ?>
                        <div class="no-data">
                            <p>Keine Wertungen vorhanden. Bitte legen Sie zuerst eine Wertung an.</p>
                        </div>
                    <?php elseif (empty($stationen)): ?>
                        <div class="no-data">
                            <p>Keine Stationen vorhanden.</p>
                            <p><a href="#" onclick="showTab('create')">Erstellen Sie Ihre erste Station</a></p>
                        </div>
                    <?php endif; ?>

                    <!-- Container für Stations-Checkboxen -->
                    <div id="stationCheckboxContainer" style="display: none;">
                        <div class="form-group">
                            <label>Stationen auswählen</label>
                            <div class="team-selection-actions">
                                <button type="button" class="btn secondary-btn small" onclick="selectAllStationen()">Alle auswählen</button>
                                <button type="button" class="btn secondary-btn small" onclick="deselectAllStationen()">Alle abwählen</button>
                            </div>
                            <div id="stationCheckboxList" class="assigned-teams-list">
                                <!-- Dynamisch geladen -->
                            </div>
                            <small>Ohne angehakte Station erhält die Wertung keine Parcours-Punkte.</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="assign_stationen" value="1" class="btn primary-btn">Zuordnung speichern</button>
                            <button type="button" class="btn" onclick="showTab('assignment-overview')">Abbrechen</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab: Zuordnungs-Übersicht -->
        <div id="assignment-overview" class="tab-content <?php echo $currentView === 'assignment-overview' ? 'active' : ''; ?>">
            <div class="data-container">
                <div class="actions-bar">
                    <button class="btn primary-btn" onclick="showTab('assign')">
                        Stationen zuordnen
                    </button>
                </div>

                <?php if (empty($assignmentOverview)): ?>
                    <div class="no-data">
                        <p>Keine Wertungen vorhanden.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th data-sort-key="name" width="30%">Wertung</th>
                            <th width="55%">Zugeordnete Stationen</th>
                            <th data-sort-key="count" data-sort-type="number" width="15%">Anzahl</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($assignmentOverview as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['wertung_name']); ?></strong></td>
                                <td>
                                    <?php
                                    echo empty($row['stationen'])
                                        ? '<em>keine Stationen – keine Parcours-Punkte</em>'
                                        : htmlspecialchars(implode(', ', $row['stationen']));
                                    ?>
                                </td>
                                <td class="numeric-cell"><?php echo count($row['stationen']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Modals für Alerts
if (!empty($modalData)):
    if (isset($modalData['duplicateName'])):
        echo CustomAlertBox::renderSimpleAlert(
            "duplicateNameAlert",
            "Station existiert bereits",
            "Eine Station mit diesem Namen existiert bereits."
        );
    endif;

    if (isset($modalData['duplicateNumber'])):
        $number = $modalData['number'] ?? 'unbekannt';
        echo CustomAlertBox::renderSimpleAlert(
            "duplicateNumberAlert",
            "Stationsnummer bereits vergeben",
            "Die Stationsnummer {$number} ist bereits vergeben. Bitte wählen Sie eine andere Nummer."
        );
    endif;
endif;

echo CustomAlertBox::renderSimpleConfirm(
    "confirmDeleteModal",
    "Löschen bestätigen",
    "Möchten Sie diese Station und alle dazugehörigen Protokolle mit den jeweiligen Werten wirklich löschen?",
    "deleteStation()",
    "closeModal('confirmDeleteModal')"
);
?>

<!-- JavaScript einbinden -->
<script src="../js/TableSortUtils.js"></script>
<script src="../js/StationInputScript.js"></script>

<!-- Tab-Initialisierung sicherstellen -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sicherstellen, dass der korrekte Tab angezeigt wird
        const currentView = <?php echo json_encode_for_js($currentView); ?>;
        showTab(currentView);

        initSortableTable(document.querySelector('.data-table'));
    });
</script>

<?php include '../php_assets/Footer.php'; ?>

</body>
</html>