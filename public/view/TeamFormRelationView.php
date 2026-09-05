<?php
require_once '../db/DbConnection.php';
require_once '../model/TeamFormRelationModel.php';
require_once '../model/MannschaftModel.php';
require_once '../model/FormManagementModel.php';
require_once '../model/StationModel.php';
require_once '../php_assets/CustomAlertBox.php';
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';

use TeamForm\TeamFormRelationModel;
use Mannschaft\MannschaftModel;
use QuestionForm\FormManagementModel;
use Station\StationModel;

// Überprüfen, ob eine Datenbankverbindung besteht
if (!isset($conn)) {
    require __DIR__ . '/../php_assets/DbErrorPage.php'; die();
}

// Modelle instanziieren
$teamFormModel = new TeamFormRelationModel($conn);
$mannschaftModel = new MannschaftModel($conn);
$formModel = new FormManagementModel($conn);
$stationModel = new StationModel($conn);

// Status-Nachricht Variable
$message = '';
$messageType = 'info';

// Nachricht aus der Session abrufen, falls vorhanden
require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();
if (isset($_SESSION['form_message']) && isset($_SESSION['message_type'])) {
    $message = $_SESSION['form_message'];
    $messageType = $_SESSION['message_type'];

    // Nachricht aus der Session entfernen nach dem Abrufen
    unset($_SESSION['form_message']);
    unset($_SESSION['message_type']);
}

// Formular zurücksetzen
if (isset($_POST['reset_form'])) {
    $teamId = intval($_POST['team_id'] ?? 0);
    $formId = intval($_POST['form_id'] ?? 0);

    if ($teamId > 0 && $formId > 0) {
        try {
            // Formular zurücksetzen (completed = 0, points = 0, completion_date = NULL)
            $stmt = $conn->prepare(
                "UPDATE TeamForm 
                SET completed = 0, 
                    points = 0, 
                    completion_date = NULL 
                WHERE team_ID = :teamId 
                AND form_ID = :formId"
            );
            $stmt->bindParam(':teamId', $teamId, PDO::PARAM_INT);
            $stmt->bindParam(':formId', $formId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                // Erfolgsmeldung in Session speichern
                $_SESSION['form_message'] = "Das Formular wurde erfolgreich zurückgesetzt.";
                $_SESSION['message_type'] = 'success';

                // Weiterleitung, um POST-Daten zu "verbrauchen"
                header("Location: TeamFormRelationView.php");
                exit;
            } else {
                $message = "Fehler beim Zurücksetzen des Formulars.";
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $message = "Datenbankfehler. Bitte später erneut versuchen.";
            error_log("TeamFormRelationView: " . $e->getMessage());
            $messageType = 'error';
        }
    } else {
        $message = "Ungültige Formular- oder Team-ID.";
        $messageType = 'error';
    }
}

// Alle TeamForm-Beziehungen abrufen (mit join zu Mannschaft und QuestionForm)
try {
    $stmt = $conn->prepare(
        "SELECT tf.*, m.Teamname, m.Kreisverband, qf.Titel, qf.Station_ID, s.name AS station_name
         FROM TeamForm tf
         JOIN Mannschaft m ON tf.team_ID = m.ID
         JOIN QuestionForm qf ON tf.form_ID = qf.ID
         JOIN Station s ON qf.Station_ID = s.ID
         ORDER BY m.Teamname, qf.Titel"
    );
    $stmt->execute();
    $teamForms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Datenbankfehler. Bitte später erneut versuchen.";
    error_log("TeamFormRelationView: " . $e->getMessage());
    $messageType = 'error';
    $teamForms = [];
}

// Für jedes Formular die Fragenanzahl ermitteln
foreach ($teamForms as &$form) {
    $form['question_count'] = $formModel->getFormQuestionCount($form['form_ID']) ?? 0;
}

$pageTitle = "Formular-Zuweisungen";
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RescueCompete - <?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logos/ww-favicon.ico">
    <link rel="stylesheet" href="../css/Colors.css">
    <link rel="stylesheet" href="../css/GlobalLayout.css">
    <link rel="stylesheet" href="../css/Navbar.css">
    <link rel="stylesheet" href="../css/Sidebar.css">
    <link rel="stylesheet" href="../css/Footer.css">
    <link rel="stylesheet" href="../css/Components.css">
</head>
<body class="has-navbar">
<!-- Navbar -->
<?php include '../php_assets/Navbar.php'; ?>

<div class="container">
    <!-- Sidebar import -->
    <?php include '../php_assets/Sidebar.php'; ?>

    <div class="form-section">
        <h2>Formular-Übersicht</h2>

        <?php if (!empty($message)): ?>
            <div class="message-box <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <table>
            <thead>
            <tr>
                <th>Mannschaft</th>
                <th>Formular</th>
                <th>Station</th>
                <th>Fragen</th>
                <th>Ausgefüllt</th>
                <th>Punkte</th>
                <th>Aktionen</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($teamForms)): ?>
                <?php foreach ($teamForms as $form): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($form['Teamname']); ?> (<?php echo htmlspecialchars($form['Kreisverband']); ?>)</td>
                        <td><?php echo htmlspecialchars($form['Titel'] ?? "Unbenannt"); ?></td>
                        <td><?php echo htmlspecialchars($form['station_name'] ?? "-"); ?></td>
                        <td class="numeric-cell"><?php echo $form['question_count']; ?></td>
                        <td class="status-cell">
                            <?php if ($form['completed'] == 1): ?>
                                <span class="status-indicator completed">Ja</span>
                            <?php else: ?>
                                <span class="status-indicator pending">Nein</span>
                            <?php endif; ?>
                        </td>
                        <td class="numeric-cell">
                            <?php echo $form['completed'] == 1 ? $form['points'] . ' / ' . $form['question_count'] : '-'; ?>
                        </td>
                        <td class="action-cell">
                            <div class="button-group">
                                <?php if (!empty($form['token'])): ?>
                                    <button type="button" class="btn" onclick="copyFormLink('<?php echo htmlspecialchars($form['token']); ?>')">
                                        Link kopieren
                                    </button>
                                <?php endif; ?>

                                <?php if ($form['completed'] == 1): ?>
                                    <button type="button" class="btn warning-btn"
                                            onclick="confirmReset(<?php echo $form['team_ID']; ?>, <?php echo $form['form_ID']; ?>)">
                                        Zurücksetzen
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn" onclick="window.open('../view/FormView.php?token=<?php echo htmlspecialchars($form['token']); ?>', '_blank')">
                                        Öffnen
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="no-data">Keine Formular-Zuweisungen gefunden.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Verstecktes Formular für das Zurücksetzen -->
<form id="reset-form" method="POST" style="display: none;">
    <input type="hidden" id="reset_team_id" name="team_id" value="">
    <input type="hidden" id="reset_form_id" name="form_id" value="">
    <input type="hidden" name="reset_form" value="1">
</form>

<?php
// Bestätigungsdialog für das Zurücksetzen einbinden
echo CustomAlertBox::renderSimpleConfirm(
    "confirmResetModal",
    "Formular zurücksetzen",
    "Möchten Sie dieses Formular wirklich zurücksetzen? Alle eingegebenen Antworten und Punkte werden gelöscht.",
    "document.getElementById('reset-form').submit();",
    "document.getElementById('confirmResetModal').classList.remove('active');"
);

// Alert-Box für das Kopieren des Links
echo CustomAlertBox::renderSimpleAlert(
    "copyAlertBox",
    "Link kopiert",
    "Der Link zum Formular wurde in die Zwischenablage kopiert."
);
?>

<script src="../js/TeamFormRelationScript.js"></script>

</body>
</html>