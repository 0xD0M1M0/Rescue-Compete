<?php
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

// Alle erforderlichen Dateien laden
require_once '../db/DbConnection.php';
require_once '../model/ResultModel.php';
require_once '../controller/SwimmingResultsController.php';

use Model\ResultModel;
use Controllers\SwimmingResultsController;

// Überprüfen, ob eine Datenbankverbindung besteht
if (!isset($conn)) {
    require __DIR__ . '/../php_assets/DbErrorPage.php'; die();
}

// Instanziiere das Model und den Controller
$model = new ResultModel($conn);
$controller = new SwimmingResultsController($model);

// Verarbeite den Request und rufe die Ergebnisse ab
$data = $controller->processRequest();
extract($data); // Nun stehen $wertungDetails und $staffelIDsByWertung zur Verfügung

$pageTitle = "Schwimm Ergebnisse";
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
<?php include '../php_assets/Navbar.php'; ?>

<div class="container">
    <div class="wrapper">
        <!-- Legende zur Erklärung der angezeigten Werte -->
        <div class="legend-container">
            <div class="legend">
                <h3>Legende</h3>
                <ul>
                    <li>1. Wert: geschwommene Zeit</li>
                    <li>2. Wert: Strafzeit</li>
                    <li>3. Wert: Gesamtzeit</li>
                    <li>4. Wert: Punkte</li>
                </ul>
            </div>

            <!-- Button für den Export -->
            <?php include __DIR__ . '/../php_assets/pdfExport/ExportButton.php'; ?>
        </div>
        <!-- Ergebnisbereich -->
        <div class="results-content">
            <?php if (empty($wertungDetails)): ?>
                <p>Keine Daten verfügbar.</p>
            <?php else: ?>
                <?php foreach ($wertungDetails as $wertungsklasse => $details): ?>
                    <?php $staffelIDs = $staffelIDsByWertung[$wertungsklasse] ?? []; ?>
                    <div class="results-section">
                        <h2>Wertung: <?php echo htmlspecialchars($wertungsklasse); ?></h2>
                        <table class="results-table">
                            <thead>
                            <tr>
                                <th>Mannschaft</th>
                                <?php foreach ($staffelIDs as $staffelName): ?>
                                    <th><?php echo htmlspecialchars($staffelName); ?></th>
                                <?php endforeach; ?>
                                <th>TotalStaffelScore</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($details['Teams'] as $teamName => $staffelData): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($teamName); ?></td>
                                    <?php foreach ($staffelIDs as $staffelName): ?>
                                        <td>
                                            <?php
                                            if (isset($staffelData[$staffelName]) && is_array($staffelData[$staffelName])) {
                                                $dataArray = $staffelData[$staffelName];
                                                // Nur anzeigen, wenn Schwimmzeit nicht leer ist
                                                if (!empty($dataArray[0])) {
                                                    echo htmlspecialchars($dataArray[0]) . "<br>" .
                                                        htmlspecialchars($dataArray[1]) . "<br>" .
                                                        htmlspecialchars($dataArray[2]) . "<br>" .
                                                        "<strong>" . htmlspecialchars($dataArray[3] ?? '') . "</strong>";
                                                } else {
                                                    echo "-";
                                                }
                                            } else {
                                                echo "-";
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td><?php echo "<strong>" . htmlspecialchars($staffelData['TotalStaffelScore'] ?? '') . "</strong>"; ?></td>
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