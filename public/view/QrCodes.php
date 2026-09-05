<?php

require_once __DIR__ . '/../php_assets/RequireLogin.php';
requireLogin();
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';

// Berechtigungs-Check: nur Wettkampfleitung/Admin
$allowedAccountTypes = ['Wettkampfleitung', 'Admin'];
if (!isset($_SESSION['acc_typ']) || !in_array($_SESSION['acc_typ'], $allowedAccountTypes, true)) {
    header("Location: ../index.php");
    exit;
}

require_once '../db/DbConnection.php';
$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    die("Datenbankverbindung nicht verfügbar.");
}

require_once '../model/StationSubmissionModel.php';
require_once '../model/StaffelSubmissionModel.php';

use Model\StationSubmissionModel;
use Model\StaffelSubmissionModel;

$stationModel = new StationSubmissionModel($conn);
$staffelModel = new StaffelSubmissionModel($conn);

$stations = $stationModel->getAllStations();
$staffeln = $staffelModel->getAllStaffeln();

// Basis-URL für die QR-Codes (absolute URL, da der Code mit einem Handy gescannt wird)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $host;

// Aktiven Tab aus URL bestimmen (default: stations)
$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'staffeln' ? 'staffeln' : 'stations';

$pageTitle = "QR-Codes";
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
    <link rel="stylesheet" href="../css/QrCodeManagementStyling.css">
    <!-- QR-Code-Bibliothek (identisch zu QrCodeManagement.php) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- JSZip + FileSaver für "Alle herunterladen" -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
</head>
<body class="has-navbar">
<?php include '../php_assets/Navbar.php'; ?>

<div class="container">
    <?php include '../php_assets/Sidebar.php'; ?>

    <div class="main-content vertical">
        <div class="data-container">
            <h2>QR-Codes für Schiedsrichter</h2>
            <p>Hier findest du QR-Codes und Links zu den Eingabe-Seiten aller Stationen und Staffeln. Schiedsrichter scannen den jeweiligen Code an ihrem Platz und gelangen nach dem Login direkt zum richtigen Eingabe-Formular.</p>
        </div>

        <div class="tab-navigation">
            <button class="tab-button <?php echo $activeTab === 'stations' ? 'active' : ''; ?>"
                    data-tab="stations" type="button">Stations Codes</button>
            <button class="tab-button <?php echo $activeTab === 'staffeln' ? 'active' : ''; ?>"
                    data-tab="staffeln" type="button">Staffel Codes</button>
        </div>

        <!-- TAB: STATIONEN -->
        <div id="tab-stations" class="tab-content <?php echo $activeTab === 'stations' ? 'active' : ''; ?>">
            <?php if (empty($stations)): ?>
                <div class="message-box info">Keine Stationen gefunden.</div>
            <?php else: ?>
                <div class="qr-bulk-actions">
                    <button type="button" class="btn primary-btn" onclick="downloadAllQrCodes('stations')">Alle Stations-Codes herunterladen (ZIP)</button>
                </div>
                <div class="qr-codes-grid">
                    <?php foreach ($stations as $i => $station):
                        $label = !empty($station['name']) ? $station['name'] : ('Station ' . ($station['Nr'] ?? $station['ID']));
                        $url = $baseUrl . '/controller/StationSubmissionController.php?action=input&station=' . urlencode($station['ID']);
                        $cardId = 'station-' . $i;
                        ?>
                        <div class="qr-code-item" data-qr-kind="stations" data-qr-label="<?php echo htmlspecialchars($label); ?>">
                            <h3><?php echo htmlspecialchars($label); ?></h3>
                            <div class="qr-code-container" id="qrcode-<?php echo $cardId; ?>" data-url="<?php echo htmlspecialchars($url); ?>"></div>
                            <div class="qr-code-info">
                                <p>URL: <a href="<?php echo htmlspecialchars($url); ?>" target="_blank"><?php echo htmlspecialchars($url); ?></a></p>
                                <button type="button" class="btn download-btn"
                                        onclick="downloadQrCode(<?php echo json_encode_for_js($cardId); ?>, <?php echo json_encode_for_js($label); ?>)">
                                    QR-Code herunterladen
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: STAFFELN -->
        <div id="tab-staffeln" class="tab-content <?php echo $activeTab === 'staffeln' ? 'active' : ''; ?>">
            <?php if (empty($staffeln)): ?>
                <div class="message-box info">Keine Staffeln gefunden.</div>
            <?php else: ?>
                <div class="qr-bulk-actions">
                    <button type="button" class="btn primary-btn" onclick="downloadAllQrCodes('staffeln')">Alle Staffel-Codes herunterladen (ZIP)</button>
                </div>
                <div class="qr-codes-grid">
                    <?php foreach ($staffeln as $i => $staffel):
                        $label = !empty($staffel['name']) ? $staffel['name'] : ('Staffel ' . $staffel['ID']);
                        $url = $baseUrl . '/controller/StaffelSubmissionController.php?action=input&staffel=' . urlencode($staffel['ID']);
                        $cardId = 'staffel-' . $i;
                        ?>
                        <div class="qr-code-item" data-qr-kind="staffeln" data-qr-label="<?php echo htmlspecialchars($label); ?>">
                            <h3><?php echo htmlspecialchars($label); ?></h3>
                            <div class="qr-code-container" id="qrcode-<?php echo $cardId; ?>" data-url="<?php echo htmlspecialchars($url); ?>"></div>
                            <div class="qr-code-info">
                                <p>URL: <a href="<?php echo htmlspecialchars($url); ?>" target="_blank"><?php echo htmlspecialchars($url); ?></a></p>
                                <button type="button" class="btn download-btn"
                                        onclick="downloadQrCode(<?php echo json_encode_for_js($cardId); ?>, <?php echo json_encode_for_js($label); ?>)">
                                    QR-Code herunterladen
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../php_assets/Footer.php'; ?>

<script src="../js/QrCodesScript.js"></script>
</body>
</html>
