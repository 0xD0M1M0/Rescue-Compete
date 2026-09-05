<?php

require_once '../db/DbConnection.php';
require_once '../model/StaffelModel.php';
require_once '../controller/StaffelInputController.php';
require_once '../php_assets/CustomAlertBox.php';
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';

use Staffel\StaffelInputController;
use Staffel\StaffelModel;

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
$model = new StaffelModel($conn);
$controller = new StaffelInputController($model);
$controller->handleRequest();

// Ergebnisse aus dem Controller abrufen
$modalData = $controller->modalData;
$message = $controller->message;
$staffeln = $model->read();

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

// Aktuelle Ansicht bestimmen
$currentView = sanitize_view_param($_GET['view'] ?? null, ['overview', 'create', 'assign', 'assignment-overview'], 'overview');

// Seiten-Titel
$pageTitle = "Verwaltung der Staffeln";
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
    <link rel="stylesheet" href="../css/StaffelInputStyling.css">
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
                    onclick="showTab('create')">Neue Staffel</button>
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
                        Neue Staffel erstellen
                    </button>
                </div>

                <?php if (empty($staffeln)): ?>
                    <div class="no-data">
                        <p>Keine Staffeln vorhanden.</p>
                        <p><a href="#" onclick="showTab('create')">Erstellen Sie Ihre erste Staffel</a></p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th data-sort-key="name" width="80%">Name</th>
                            <th width="20%">Aktionen</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($staffeln as $staffel): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($staffel['name'] ?? "nicht gefunden"); ?></strong>
                                </td>
                                <td class="action-cell">
                                    <div class="button-group">
                                        <button class="btn warning-btn small"
                                                onclick="confirmDeleteStaffel(<?php echo (int)$staffel['ID']; ?>, <?php echo json_encode_for_js($staffel['name']); ?>)">
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

        <!-- Tab: Neue Staffel -->
        <div id="create" class="tab-content <?php echo $currentView === 'create' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Neue Staffel erstellen</h3>

                <form method="POST" id="createStaffelForm">
                    <input type="hidden" name="add_Staffel" value="1">

                    <div class="form-group">
                        <label for="name">Staffelname *</label>
                        <input type="text" id="name" name="name" required
                               placeholder="z.B. Tauchstaffel, Transportstaffel"
                               value="<?= htmlspecialchars($name); ?>">
                        <small>Geben Sie einen eindeutigen Namen für die Staffel ein</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn primary-btn">Staffel erstellen</button>
                        <button type="button" class="btn" onclick="showTab('overview')">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab: Wertung zuordnen -->
        <div id="assign" class="tab-content <?php echo $currentView === 'assign' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Staffeln einer Wertung zuordnen</h3>
                <p class="tab-description">Wählen Sie eine Wertung und haken Sie die Staffeln an, die in dieser Wertung gewertet werden. Nur die angehakten Staffeln zählen in die Schwimmpunkte dieser Wertung.</p>

                <form id="assignStaffelnForm" method="POST">
                    <div class="form-group">
                        <label for="assignWertung">Wertung *</label>
                        <select id="assignWertung" name="wertung" required onchange="loadStaffelCheckboxes()">
                            <option value="" disabled selected hidden>Bitte auswählen</option>
                            <?php foreach ($wertungen as $wertung): ?>
                                <option value="<?php echo htmlspecialchars($wertung['ID']); ?>">
                                    <?php echo htmlspecialchars($wertung['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Wählen Sie die Wertung aus, der Sie Staffeln zuordnen möchten</small>
                    </div>

                    <?php if (empty($wertungen)): ?>
                        <div class="no-data">
                            <p>Keine Wertungen vorhanden. Bitte legen Sie zuerst eine Wertung an.</p>
                        </div>
                    <?php elseif (empty($staffeln)): ?>
                        <div class="no-data">
                            <p>Keine Staffeln vorhanden.</p>
                            <p><a href="#" onclick="showTab('create')">Erstellen Sie Ihre erste Staffel</a></p>
                        </div>
                    <?php endif; ?>

                    <!-- Container für Staffel-Checkboxen -->
                    <div id="staffelCheckboxContainer" style="display: none;">
                        <div class="form-group">
                            <label>Staffeln auswählen</label>
                            <div class="team-selection-actions">
                                <button type="button" class="btn secondary-btn small" onclick="selectAllStaffeln()">Alle auswählen</button>
                                <button type="button" class="btn secondary-btn small" onclick="deselectAllStaffeln()">Alle abwählen</button>
                            </div>
                            <div id="staffelCheckboxList" class="assigned-teams-list">
                                <!-- Dynamisch geladen -->
                            </div>
                            <small>Ohne angehakte Staffel erhält die Wertung keine Schwimmpunkte.</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="assign_staffeln" value="1" class="btn primary-btn">Zuordnung speichern</button>
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
                        Staffeln zuordnen
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
                            <th width="55%">Zugeordnete Staffeln</th>
                            <th data-sort-key="count" data-sort-type="number" width="15%">Anzahl</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($assignmentOverview as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['wertung_name']); ?></strong></td>
                                <td>
                                    <?php
                                    echo empty($row['staffeln'])
                                        ? '<em>keine Staffeln – keine Schwimmpunkte</em>'
                                        : htmlspecialchars(implode(', ', $row['staffeln']));
                                    ?>
                                </td>
                                <td class="numeric-cell"><?php echo count($row['staffeln']); ?></td>
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
if (!empty($modalData) && isset($modalData['duplicate'])):
    echo CustomAlertBox::renderSimpleAlert(
        "duplicateStaffelAlert",
        "Staffel existiert bereits",
        "Eine Staffel mit diesem Namen existiert bereits."
    );
endif;

echo CustomAlertBox::renderSimpleConfirm(
    "confirmDeleteModal",
    "Löschen bestätigen",
    "Möchten Sie diese Staffel mit den enthaltenen Zeiten wirklich löschen?",
    "deleteStaffel()",
    "closeModal('confirmDeleteModal')"
);
?>

<!-- JavaScript einbinden -->
<script src="../js/TableSortUtils.js"></script>
<script src="../js/StaffelInputScript.js"></script>

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