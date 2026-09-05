<?php

require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

// Seiten-Titel festlegen
$pageTitle = "Gesamtergebnisse";

// Erforderliche Dateien einbinden
require_once __DIR__ . '/../db/DbConnection.php';
require_once __DIR__ . '/../model/ResultModel.php';
require_once __DIR__ . '/../model/ResultConfigurationModel.php';
require_once __DIR__ . '/../controller/CompleteResultsController.php';

use Model\ResultModel;
use Model\ResultConfigurationModel;
use Controllers\CompleteResultsController;

// Überprüfen, ob eine Datenbankverbindung besteht
if (!isset($conn)) {
    require __DIR__ . '/../php_assets/DbErrorPage.php'; die();
}

// Modelle und Controller instanziieren
$model = new ResultModel($conn);
$configModel = new ResultConfigurationModel();
$controller = new CompleteResultsController($model, $configModel);

// Ergebnisse verarbeiten und in Variablen extrahieren
$data = $controller->processRequest();
extract($data); // Es stehen nun $combinedResults, $staffelNamesByWertung, $stationNamesByWertung und $config zur Verfügung

// Fallback für die Konfiguration: Falls Schlüssel fehlen, werden Standardwerte gesetzt
$config['SHARE_SWIMMING'] = $config['SHARE_SWIMMING'] ?? 50;
$config['SHARE_PARCOURS'] = $config['SHARE_PARCOURS'] ?? 50;
$config['TOTAL_POINTS']   = $config['TOTAL_POINTS'] ?? 12000;
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
    <link rel="stylesheet" href="../css/ResultStyling.css">

    <!-- PDF-Export-Skripte und Styles einbinden -->
    <?php include __DIR__ . '/../php_assets/pdfExport/PdfExportLibs.php'; ?>
</head>
<body class="has-navbar">

<?php include __DIR__ . '/../php_assets/Navbar.php'; ?>

<div class="container">
    <div class="wrapper">
        <!-- Legenden-Bereich: Zeigt die Berechnungs-Konfiguration -->
        <div class="legend-container">
            <div class="legend">
                <h3>Gesamtberechnungs-Konfiguration</h3>
                <!-- Unsichtbare Tabelle zur sauberen Darstellung der Konfiguration -->
                <table>
                    <tr>
                        <td>Schwimm Anteil:</td>
                        <td><?php echo htmlspecialchars($config['SHARE_SWIMMING']); ?>%</td>
                    </tr>
                    <tr>
                        <td>Parcours Anteil:</td>
                        <td><?php echo htmlspecialchars($config['SHARE_PARCOURS']); ?>%</td>
                    </tr>
                    <tr>
                        <td>Gesamtpunkte:</td>
                        <td><?php echo htmlspecialchars($config['TOTAL_POINTS']); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Button für den Export -->
            <?php include __DIR__ . '/../php_assets/pdfExport/ExportButton.php'; ?>
        </div>

        <!-- Ergebnisbereich: Zeigt die kombinierten Ergebnisse aus Schwimmen und Parcours -->
        <div class="results-content">
            <?php if (empty($combinedResults)): ?>
                <p>Keine Daten verfügbar.</p>
            <?php else: ?>
                <?php foreach ($combinedResults as $wertung => $data): ?>
                    <?php $staffelNames = $staffelNamesByWertung[$wertung] ?? []; ?>
                    <?php $stationNames = $stationNamesByWertung[$wertung] ?? []; ?>
                    <div class="results-section">
                        <h2>Wertung: <?php echo htmlspecialchars($wertung); ?></h2>
                        <table class="results-table">
                            <thead>
                            <tr>
                                <th>Mannschaft</th>
                                <!-- Spaltenüberschriften für die dieser Wertung zugeordneten Staffeln -->
                                <?php foreach ($staffelNames as $staffelName): ?>
                                    <th>Staffel <?php echo htmlspecialchars($staffelName); ?></th>
                                <?php endforeach; ?>
                                <!-- Spaltenüberschriften für die dieser Wertung zugeordneten Stationen -->
                                <?php foreach ($stationNames as $stationName): ?>
                                    <th>Station <?php echo htmlspecialchars($stationName); ?></th>
                                <?php endforeach; ?>
                                <th>Gesamtpunkte</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($data['Teams'] as $teamName => $teamData): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($teamName); ?></td>
                                    <!-- Ausgabe der Schwimm-Ergebnisse pro Staffel: Fehlt ein Wert, wird 0 angezeigt -->
                                    <?php foreach ($staffelNames as $staffelName): ?>
                                        <td>
                                            <?php
                                            echo isset($teamData['swimming'][$staffelName])
                                                ? htmlspecialchars($teamData['swimming'][$staffelName])
                                                : "0";
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <!-- Ausgabe der adjustierten Parcours-Ergebnisse pro Station: Fehlt ein Wert, wird 0 angezeigt -->
                                    <?php foreach ($stationNames as $stationName): ?>
                                        <td>
                                            <?php
                                            echo isset($teamData['parcours'][$stationName])
                                                ? htmlspecialchars($teamData['parcours'][$stationName])
                                                : "0";
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <!-- Gesamtpunkte fett dargestellt -->
                                    <td><strong><?php echo htmlspecialchars($teamData['total']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../php_assets/Footer.php'; ?>

</body>
</html>