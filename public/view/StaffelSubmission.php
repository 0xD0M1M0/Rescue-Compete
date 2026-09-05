<?php

require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();
// StaffelSubmission.php
// Erwartet werden:
//   $staffelID        : die aktuelle Staffel ID
//   $staffelName      : Name der Staffel
//   $teams            : Array der Mannschaften (Teams)
//   $submittedTeams   : Array der Mannschaften, die bereits Ergebnisse eingetragen haben
//   $existingResults  : Array mit bereits vorhandenen Ergebnissen [teamId => ['schwimmzeit' => ..., 'strafzeit' => ...]]

$pageTitle = "Eingabe der Schwimm-Ergebnisse";
require_once '../php_assets/CustomAlertBox.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RescueCompete</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logos/ww-favicon.ico">
    <link rel="stylesheet" href="../css/Colors.css">
    <link rel="stylesheet" href="../css/GlobalLayout.css">
    <link rel="stylesheet" href="../css/Navbar.css">
    <link rel="stylesheet" href="../css/Footer.css">
    <link rel="stylesheet" href="../css/Components.css">
    <link rel="stylesheet" href="../css/StaffelSubmissionStyling.css">
</head>
<!-- Übergabe des submittedTeams-Arrays als Data-Attribut und spezifische CSS-Klasse -->
<body class="has-navbar staffel-submission-page" data-submitted-teams='<?php echo json_encode_for_js(array_map('strval', $submittedTeams ?? [])); ?>'>
<?php include '../php_assets/Navbar.php'; ?>
<div class="container">
    <div class="header-with-back">
        <a href="../controller/StaffelSubmissionController.php?action=list" class="back-button">&#8617;</a>
        <h1>Ergebniseingabe für: <?php echo htmlspecialchars($staffelName); ?></h1>
    </div>

    <!-- Status-Nachrichten -->
    <?php if (isset($_GET['status'])): ?>
        <div class="status-message <?php echo $_GET['status'] === 'success' ? 'success-message' : 'error-message'; ?>">
            <?php
            if ($_GET['status'] === 'success') {
                echo "Die Ergebnisse wurden erfolgreich gespeichert.";
            } else {
                echo isset($_GET['message'])
                    ? htmlspecialchars($_GET['message'])
                    : "Die Ergebnisse konnten nicht gespeichert werden. Bitte überprüfen Sie Ihre Eingaben.";
            }
            ?>
        </div>
    <?php endif; ?>

    <div class="wrapper">
        <!-- Legende -->
        <div class="legend">
            <h3>Legende</h3>
            <p>
                Die Zeiten können in einem flexiblen Format eingegeben werden:
            </p>
            <ul>
                <li>"1:20" wird als <strong>00:01:20.0000</strong> gespeichert</li>
                <li>"24,65" wird als <strong>00:00:24.6500</strong> gespeichert</li>
                <li>"12:24.33" wird als <strong>00:12:24.3300</strong> gespeichert</li>
            </ul>
            <p>
                <strong>Hinweise:</strong>
            </p>
            <ul>
                <li>Punkte und Kommas werden gleichwertig verarbeitet</li>
                <li>Fehlende Stunden oder Minuten werden automatisch ergänzt</li>
                <li>Sie können die Felder auch leer lassen, wenn ein Team nicht teilgenommen hat</li>
                <li>Bereits gespeicherte Zeiten werden automatisch angezeigt</li>
                <li>Es werden nur Mannschaften angezeigt, deren Wertung dieser Staffel zugeordnet ist</li>
            </ul>
        </div>

        <!-- Eingabeformular -->
        <div class="submission-form">
            <div class="submission-box">
                <form method="POST" action="../controller/StaffelSubmissionController.php?action=save">
                    <input type="hidden" name="staffelID" value="<?php echo htmlspecialchars($staffelID); ?>">

                    <?php if (empty($teams)): ?>
                        <p class="warning">
                            Für diese Staffel können noch keine Ergebnisse eingegeben werden, da keine passenden Mannschaften vorhanden sind.<br>
                            Die Staffel muss zuerst einer Wertung zugeordnet werden (unter „Staffeln → Wertung zuordnen"). Erst dann erscheinen hier die Mannschaften der zugeordneten Wertungen.
                        </p>
                    <?php else: ?>
                        <!-- Kopfzeile mit Beschriftungen -->
                        <div class="team-row header-row">
                            <div class="header-cell team-header">Mannschaft</div>
                            <div class="time-fields">
                                <div class="time-field-group">
                                    <span class="header-cell time-header">Geschwommene Zeit</span>
                                </div>
                                <div class="time-field-group">
                                    <span class="header-cell time-header">Strafzeit</span>
                                </div>
                            </div>
                        </div>

                        <!-- Team-Zeilen -->
                        <?php foreach ($teams as $team): ?>
                            <?php
                            // Vorhandene Ergebnisse für dieses Team laden
                            $teamResults = $existingResults[$team['ID']] ?? ['schwimmzeit' => '', 'strafzeit' => ''];
                            ?>
                            <div class="team-row">
                                <div class="team-name" data-team-id="<?php echo htmlspecialchars($team['ID']); ?>">
                                    <?php echo htmlspecialchars($team['Teamname']); ?>
                                </div>

                                <div class="time-fields">
                                    <div class="time-field-group">
                                        <input type="text"
                                               name="results[<?php echo htmlspecialchars($team['ID']); ?>][geschwommene_zeit]"
                                               class="time-input"
                                               placeholder="mm:ss.00"
                                               value="<?php echo htmlspecialchars($teamResults['schwimmzeit']); ?>">
                                    </div>

                                    <div class="time-field-group">
                                        <input type="text"
                                               name="results[<?php echo htmlspecialchars($team['ID']); ?>][strafzeit]"
                                               class="time-input"
                                               placeholder="mm:ss.00"
                                               value="<?php echo htmlspecialchars($teamResults['strafzeit']); ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <button type="submit" class="btn">Ergebnisse speichern</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- CustomAlertBox für ungültige Zeiteingaben -->
<?php echo CustomAlertBox::renderSimpleAlert(
    'invalidTimeAlert',
    'Ungültige Eingabe',
    'Bitte geben Sie nur Zahlen, Punkte, Kommas und Doppelpunkte ein. Buchstaben sind nicht erlaubt.'
); ?>

<!-- Platz für dynamisch erstellte Bestätigungs-Modals -->

<?php include '../php_assets/Footer.php'; ?>

<!-- JavaScript einbinden -->
<script src="../js/StaffelSubmissionScript.js"></script>
</body>
</html>