<?php

require_once '../db/DbConnection.php';
require_once '../model/ProtocolModel.php';
require_once '../model/StationModel.php';
require_once '../controller/ProtocolInputController.php';
require_once '../php_assets/CustomAlertBox.php';
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';

use Protocol\ProtocolInputController;
use Protocol\ProtocolModel;
use Station\StationModel;

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
$model = new ProtocolModel($conn);
$stationDb = new StationModel($conn);
$controller = new ProtocolInputController($model);
$controller->handleRequest();

// Ergebnisse aus dem Controller abrufen
$modalData = $controller->modalData;
$message = $controller->message;
$protokolle = $model->read();
$stationen = $stationDb->read();

// Werte aus dem POST-Request (falls vorhanden)
$nr = isset($_POST['Nr']) ? trim($_POST['Nr']) : "";
$name = isset($_POST['Name']) ? trim($_POST['Name']) : "";
$maxPunkte = isset($_POST['max_Punkte']) ? trim($_POST['max_Punkte']) : "";
$stationName = isset($_POST['stationName']) ? trim($_POST['stationName']) : "";

// Aktuelle Ansicht bestimmen
$currentView = sanitize_view_param($_GET['view'] ?? null, ['overview', 'create'], 'overview');

// Seiten-Titel
$pageTitle = "Verwaltung der Protokolle";
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
    <link rel="stylesheet" href="../css/ProtocolInputStyling.css">

    <!-- JavaScript-Konstanten übergeben -->
    <script>
        const stationen = <?php echo json_encode_for_js($stationen); ?>;
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
                    onclick="showTab('create')">Neues Protokoll</button>
        </div>

        <!-- Statusmeldungen -->
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
                        Neues Protokoll erstellen
                    </button>
                    <div class="filter-section">
                        <label for="stationFilter">Nach Station filtern:</label>
                        <select id="stationFilter">
                            <option value="">Alle Stationen anzeigen</option>
                        </select>
                    </div>
                </div>

                <?php if (empty($protokolle)): ?>
                    <div class="no-data">
                        <p>Keine Protokolle vorhanden.</p>
                        <p><a href="#" onclick="showTab('create')">Erstellen Sie Ihr erstes Protokoll</a></p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th data-sort-key="name" width="35%">Name</th>
                            <th data-sort-key="punkte" data-sort-type="number" width="20%">Maximale Punkte</th>
                            <th data-sort-key="station" width="30%">Station</th>
                            <th width="15%">Aktionen</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($protokolle as $protokoll): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($protokoll['Name'] ?? "nicht gefunden"); ?></strong>
                                </td>
                                <td class="numeric-cell"><?php echo htmlspecialchars($protokoll['max_Punkte'] ?? "nicht gefunden"); ?></td>
                                <td><?php echo htmlspecialchars($protokoll['station_name'] ?? "nicht gefunden"); ?></td>
                                <td class="action-cell">
                                    <div class="button-group">
                                        <button class="btn warning-btn small"
                                                onclick="confirmDeleteProtocol(<?php echo (int)($protokoll['protocol_Nr'] ?? 0); ?>, <?php echo json_encode_for_js($protokoll['Name']); ?>)">
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

        <!-- Tab: Neues Protokoll -->
        <div id="create" class="tab-content <?php echo $currentView === 'create' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Neue Protokolle erstellen</h3>

                <form method="POST" id="createProtocolForm">
                    <input type="hidden" name="add_protocol" value="1">

                    <div class="form-group">
                        <label for="stationName">Zugehörige Station *</label>
                        <select id="stationName" name="stationName" required>
                            <option value="" disabled selected hidden>Bitte auswählen</option>
                        </select>
                        <small>Wählen Sie die Station aus, zu der die Protokolle gehören sollen</small>
                    </div>

                    <!-- Container für dynamische Protokoll-Eingabefelder -->
                    <div id="protocolsContainer">
                        <div class="protocol-entry">
                            <h4>1. Protokoll:</h4>
                            <div class="form-group">
                                <label for="Name">Protokollname:</label>
                                <input type="text" id="Name" name="Name" required
                                       placeholder="z.B. bewusstlose Person"
                                       value="<?= htmlspecialchars($name); ?>">
                            </div>
                            <div class="form-group">
                                <label for="max_Punkte">Maximale Punktzahl:</label>
                                <input type="number" id="max_Punkte" name="max_Punkte" required min="1"
                                       placeholder="z.B. 100"
                                       value="<?= htmlspecialchars($maxPunkte); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Protokoll-Management Buttons -->
                    <div class="protocol-management">
                        <button type="button" class="btn secondary-btn" id="addProtocolBtn" onclick="addProtocol()">
                            Weiteres Protokoll hinzufügen
                        </button>
                        <button type="button" class="btn secondary-btn" id="removeProtocolBtn" onclick="removeProtocol()">
                            Letztes Protokoll entfernen
                        </button>
                    </div>

                    <!-- Aktionen -->
                    <div class="form-actions">
                        <button type="submit" class="btn primary-btn">Protokolle erstellen</button>
                        <button type="button" class="btn" onclick="showTab('overview')">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Modals für Alerts
if (!empty($modalData)):
    $alert = new CustomAlertBox("confirmDuplicateProtocol");
    $alert->setTitle("Duplikat gefunden");
    $alert->setMessage($modalData['message']);
    $alert->setData([
        'duplicate_Nr'  => $modalData['duplicate_Nr'] ?? "",
        'confirm_update'=> "1",
        'add_protocol'  => "1",
        'Name'          => $modalData['Name'] ?? "",
        'max_Punkte'    => $modalData['max_Punkte'] ?? "",
        'station_ID'    => $modalData['station_ID'] ?? ""
    ]);
    $alert->addButton("Ja", "", "btn", "submit");
    $alert->addButton("Nein", "closeModal('confirmDuplicateProtocol');", "btn", "button");
    echo $alert->render();
endif;

echo CustomAlertBox::renderSimpleConfirm(
    "confirmDeleteModal",
    "Löschen bestätigen",
    "Möchten Sie dieses Protokoll wirklich löschen?",
    "deleteProtocol()",
    "closeModal('confirmDeleteModal')"
);
?>

<!-- JavaScript einbinden -->
<script src="../js/TableSortUtils.js"></script>
<script src="../js/ProtocolInputScript.js"></script>

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