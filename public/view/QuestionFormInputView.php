<?php
require_once __DIR__ . '/../php_assets/RequireLogin.php';
requireLogin();

// DEBUG CODE für view
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../db/DbConnection.php';
require_once '../model/FormManagementModel.php';
require_once '../model/QuestionModel.php';
require_once '../model/QuizPoolModel.php';
require_once '../model/StationModel.php';
require_once '../model/TeamFormRelationModel.php';
require_once '../model/MannschaftModel.php';
require_once '../controller/QuestionFormInputController.php';
require_once '../php_assets/CustomAlertBox.php';
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';

use Mannschaft\MannschaftModel;
use QuestionForm\QuestionFormInputController;
use QuestionForm\FormManagementModel;
use Question\QuestionModel;
use QuestionPool\QuizPoolModel;
use Station\StationModel;
use TeamForm\TeamFormRelationModel;

if (!isset($conn)) {
    require __DIR__ . '/../php_assets/DbErrorPage.php'; die();
}

// Prüfen, ob es eine AJAX-Anfrage ist
$isAjaxRequest = isset($_GET['action']);

if ($isAjaxRequest) {
    // AJAX-Anfrage behandeln
    $controller = new QuestionFormInputController(
        new FormManagementModel($conn),
        new QuestionModel($conn),
        new MannschaftModel($conn),
        new TeamFormRelationModel($conn)
    );
    $controller->handleAction();
    exit; // Script beenden nach AJAX-Behandlung
}

// Normale Seitenansicht - Modelle instanziieren
$formModel = new FormManagementModel($conn);
$questionModel = new QuestionModel($conn);
$poolModel = new QuizPoolModel($conn);
$stationModel = new StationModel($conn);
$teamFormModel = new TeamFormRelationModel($conn);

// Controller für normale Anfragen instanziieren
$controller = new QuestionFormInputController(
    $formModel,
    $questionModel,
    new MannschaftModel($conn),
    $teamFormModel
);

// Normale Anfragen verarbeiten
$controller->handleRequest();

// Daten für die View
$message = $controller->message;
$modalData = $controller->modalData;
$questionForms = $formModel->read();
$stations = $stationModel->read();
$pools = $poolModel->read();

// Token-Informationen aus TeamForm-Tabelle abrufen
$formTokens = $formModel->getFormTokens();

// Ausgewählter Pool (falls vorhanden)
$selectedPoolId = isset($_GET['pool_id']) ? intval($_GET['pool_id']) : null;
$questions = $selectedPoolId ? $questionModel->getQuestionsByPool($selectedPoolId) : [];

$pageTitle = "Verwaltung der Frageformulare";

// Status-Meldung verarbeiten (wird später mit CustomAlertBox angezeigt)
$statusMessage = '';
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $formsCreated = isset($_GET['forms_created']) ? intval($_GET['forms_created']) : 0;
    $statusMessage = "Erfolgreich $formsCreated Formular(e) erstellt.";
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RescueCompete - Frageformulare</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logos/ww-favicon.ico">
    <link rel="stylesheet" href="../css/Colors.css">
    <link rel="stylesheet" href="../css/GlobalLayout.css">
    <link rel="stylesheet" href="../css/Navbar.css">
    <link rel="stylesheet" href="../css/Sidebar.css">
    <link rel="stylesheet" href="../css/Footer.css">
    <link rel="stylesheet" href="../css/Components.css">
    <link rel="stylesheet" href="../css/QuestionFormInputStyling.css">
</head>
<body class="has-navbar">
<!-- Navbar -->
<?php include '../php_assets/Navbar.php'; ?>

<div class="container">
    <!-- Sidebar import -->
    <?php include '../php_assets/Sidebar.php'; ?>

    <!-- Formularerstellung -->
    <section class="form-section">
        <h2>Frageformular erstellen</h2>

        <form id="questionform-creator" method="POST">
            <!-- Step 1: Station auswählen -->
            <div class="form-step" id="step-1">
                <h3>1. Station auswählen</h3>
                <div class="form-group">
                    <label for="station">Station:</label>
                    <select id="station" name="station" required>
                        <option value="">Bitte Station auswählen</option>
                        <?php foreach ($stations as $station): ?>
                            <option value="<?= htmlspecialchars($station['ID']); ?>">
                                <?= htmlspecialchars($station['name'] ?? "Station {$station['ID']}"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Step 2: Fragenpool auswählen -->
            <div class="form-step" id="step-2">
                <h3>2. Fragenpool auswählen</h3>
                <div class="form-group">
                    <label for="question_pool">Fragenpool:</label>
                    <div style="display: flex; align-items: center;">
                        <select id="question_pool" name="question_pool" required>
                            <option value="">Bitte Fragenpool auswählen</option>
                            <?php foreach ($pools as $pool): ?>
                                <option value="<?= htmlspecialchars($pool['ID']); ?>" <?= $selectedPoolId == $pool['ID'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($pool['Name'] ?? "Pool {$pool['ID']}"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Step 3: Fragen auswählen -->
            <div class="form-step" id="step-3">
                <h3>3. Fragen auswählen</h3>
                <div class="question-selection">
                    <div class="question-header">
                        <div class="checkbox-column">
                            <input type="checkbox" id="select-all" onclick="toggleAllQuestions()">
                            <label for="select-all">Alle auswählen</label>
                        </div>
                        <div class="question-count">
                            <span id="selected-count">0</span> von <span id="total-count">0</span> Fragen ausgewählt
                        </div>
                    </div>

                    <div class="questions-container" id="questions-list">
                        <!-- Fragen werden per JavaScript geladen -->
                        <p class="info-text">Bitte wählen Sie zunächst einen Fragenpool aus und klicken Sie auf "Fragen laden".</p>
                    </div>
                </div>
            </div>

            <!-- Step 4: Formular-Einstellungen -->
            <div class="form-step" id="step-4">
                <h3>4. Formular-Einstellungen</h3>

                <div class="form-group">
                    <label for="form_title">Basistitel der Formulare:</label>
                    <input type="text" id="form_title" name="form_title" placeholder="Grundtitel für alle Formulare" required>
                    <p class="help-text">Bei mehreren Formularen wird die Nummerierung automatisch hinzugefügt (z.B. "Fragebogen (1/3)")</p>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="distribute-toggle" name="distribute_questions" value="1">
                        Fragen auf mehrere Formulare verteilen
                    </label>

                    <div id="form-count-container" class="hidden">
                        <label for="form_count">Anzahl der Formulare:</label>
                        <input type="number" id="form_count" name="form_count" min="1" max="20" value="1">
                        <div id="distribution-info" class="hidden"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="randomize-toggle" name="randomize_order" value="1">
                        Fragenzuteilung zufällig gestalten
                    </label>
                    <p class="help-text">Die Reihenfolge der Fragen variiert mit jeder Mannschaft auf jedem Formular</p>
                </div>
            </div>

            <div class="form-step" id="step-5">
                <h3>5. Hinweis</h3>
                <p>
                    Formulare werden beim Erstellen bereits allen Mannschaften zugeordnet. <br>
                    Deswegen ist es wichitg, dass zuerst die Mannschaften stimmen, bevor die Formulare anglegt werden.
                </p>
            </div>

            <div class="form-actions">
                <button type="submit" name="create_form" class="btn primary-btn">Formular(e) erstellen</button>
            </div>
        </form>
    </section>

    <!-- Vorhandene Frageformulare anzeigen & löschen -->
    <div class="info-section">
        <section>
            <h2>Bestehende Frageformulare</h2>
            <table>
                <thead>
                <tr>
                    <th width="35%">Titel</th>
                    <th width="25%">Station</th>
                    <th width="15%">Fragen</th>
                    <th width="25%">Aktionen</th>
                </tr>
                </thead>
                <tbody>
                <?php
                if (!empty($questionForms)):
                    // Titel-Tracking, um Duplikate zu vermeiden
                    $seenTitles = [];

                    foreach ($questionForms as $form):
                        $title = $form['titel'] ?? "nicht gefunden";

                        // Prüfen, ob der Titel bereits angezeigt wurde
                        if (!in_array($title, $seenTitles)):
                            // Titel zur Liste der gesehenen Titel hinzufügen
                            $seenTitles[] = $title;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($title); ?></td>
                                <td><?php echo htmlspecialchars($form['station_name'] ?? "nicht gefunden"); ?></td>
                                <td><?php echo htmlspecialchars($formModel->getFormQuestionCount($form['ID']) ?? "0"); ?></td>
                                <td>
                                    <div class="button-group">
                                        <form method="POST" class="delete-form">
                                            <input type="hidden" name="delete_ID" value="<?php echo htmlspecialchars($form['ID'] ?? ''); ?>">
                                            <input type="hidden" name="delete_questionform" value="1">
                                            <button type="submit" class="btn delete-btn">Löschen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php
                        endif;
                    endforeach;
                else:
                    ?>
                    <tr>
                        <td colspan="4">Keine Frageformulare gefunden.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>

    <?php
    // Löschen-Confirm-Modal via CustomAlertBox
    echo CustomAlertBox::renderSimpleConfirm(
        "confirmDeleteModal",
        "Löschen bestätigen",
        "Möchten Sie dieses Frageformular wirklich löschen?",
        "if(deleteForm){ deleteForm.submit(); }",
        "document.getElementById('confirmDeleteModal').classList.remove('active');"
    );

    // Erfolgsmeldung (falls vorhanden) als Modal anzeigen
    if (!empty($statusMessage)) {
        echo CustomAlertBox::renderSimpleAlert(
            "successAlert",
            "Erfolg",
            $statusMessage
        );
        echo '<script>document.addEventListener("DOMContentLoaded", function() { 
                document.getElementById("successAlert").classList.add("active");
            });</script>';
    }

    // Fehlermeldung (falls vorhanden) als Modal anzeigen
    if (!empty($message)) {
        echo CustomAlertBox::renderSimpleAlert(
            "messageAlert",
            strpos($message, 'Fehler') !== false ? "Fehler" : "Hinweis",
            $message
        );
        echo '<script>document.addEventListener("DOMContentLoaded", function() { 
                document.getElementById("messageAlert").classList.add("active");
            });</script>';
    }
    ?>
</div>

<script>
    // Übergabe der Daten an das JavaScript
    const API_ENDPOINT = '<?php echo $_SERVER["PHP_SELF"]; ?>'; // Verwendet die aktuelle Seite für AJAX
    let stations = <?php echo json_encode_for_js($stations); ?>;
    let selectedPoolId = <?php echo $selectedPoolId ? $selectedPoolId : 'null'; ?>;
</script>
<script src="../js/ModalUtils.js"></script>
<script src="../js/QuestionFormInputScript.js"></script>
</body>
</html>