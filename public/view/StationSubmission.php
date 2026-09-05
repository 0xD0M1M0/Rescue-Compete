<?php

require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

$pageTitle = "Eingabe der Parcours-Ergebnisse";
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
    <link rel="stylesheet" href="../css/StationSubmissionStyling.css">
</head>
<!-- Übergabe des submittedTeams-Arrays als Data-Attribut -->
<body class="has-navbar" data-submitted-teams='<?php echo json_encode_for_js(array_map('strval', $submittedTeams ?? [])); ?>'>
<?php include '../php_assets/Navbar.php'; ?>
<div class="container">
    <div class="wrapper">
        <div class="submission-form">
            <!-- Header mit Rückpfeil und Überschrift -->
            <div class="header-with-back">
                <a href="../controller/StationSubmissionController.php?action=list" class="back-button" aria-label="Zurück zur Stationsliste">&#8617;</a>
                <h1>Ergebniseingabe für Station: <?php echo htmlspecialchars($stationData['name'] ?? $stationID); ?></h1>
            </div>

            <!-- Eingabe-Box -->
            <div class="submission-box">
                <form method="POST" action="../controller/StationSubmissionController.php?action=save">
                    <!-- Hidden-Feld zur Übermittlung der Station-Nummer -->
                    <input type="hidden" name="stationID" value="<?php echo htmlspecialchars($stationID); ?>">

                    <!-- Dropdown-Menü für Mannschaften -->
                    <div class="form-group">
                        <label for="teamSelect">Mannschaft auswählen:</label>
                        <?php if (empty($teams)): ?>
                            <p class="warning">Keine Mannschaften für diese Station vorhanden. Es werden nur Teams angezeigt, deren Wertung diese Station enthält – die Station muss zuerst einer Wertung zugeordnet werden.</p>
                        <?php endif; ?>
                        <select name="teamID" id="teamSelect" required>
                            <option value="" disabled selected hidden>Bitte wählen</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo htmlspecialchars($team['ID']); ?>">
                                    <?php echo htmlspecialchars($team['Teamname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Überprüfung, ob Protokolle vorhanden sind -->
                    <?php if (empty($protocols)): ?>
                        <p class="warning">Für diese Station sind keine Protokolle hinterlegt. Sollte hier ein Fehler vorliegen, dann kontaktiere bitte den Administrator.</p>
                    <?php else: ?>
                        <!-- Container für Protokoll-Eingaben für bessere Organisation auf mobilen Geräten -->
                        <div class="protocols-container">
                            <!-- Für jedes Protokoll eine Zeile mit Label und Eingabefeld (in einer flex-row) -->
                            <?php foreach ($protocols as $protocol): ?>
                                <div class="form-group flex-row">
                                    <label for="protocol_<?php echo htmlspecialchars($protocol['Nr']); ?>">
                                        <?php echo htmlspecialchars($protocol['Name']); ?>
                                        <span class="points-info">(max: <?php echo htmlspecialchars($protocol['max_Punkte']); ?>)</span>
                                    </label>
                                    <input type="number" id="protocol_<?php echo htmlspecialchars($protocol['Nr']); ?>"
                                           name="results[<?php echo htmlspecialchars($protocol['Nr']); ?>]"
                                           class="protocol-input"
                                           inputmode="numeric"
                                           min="0" max="<?php echo htmlspecialchars($protocol['max_Punkte']); ?>"
                                           placeholder="Puntzahl">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Container für den Submit-Button -->
                    <div class="form-actions">
                        <button type="submit" class="btn">Ergebnisse speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../php_assets/Footer.php'; ?>

<!-- Externes JavaScript am Ende des Bodys einbinden -->
<script src="../js/ModalUtils.js"></script>
<script src="../js/StationSubmissionScript.js"></script>
</body>
</html>