<?php

require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

// Prüfen, ob der Benutzer angemeldet ist und die richtigen Rechte hat
if (!isset($_SESSION['id']) || !isset($_SESSION['login']) || $_SESSION['login'] !== 'ok' || $_SESSION['acc_typ'] !== 'Wettkampfleitung') {
    header("Location: Login.php");
    exit;
}

// Datenbank-Verbindung herstellen
require_once '../db/DbConnection.php';
if (!isset($conn)) {
    die("Datenbankverbindung nicht verfügbar.");
}

// Alle einzigartigen Formulartitel abrufen
$stmt = $conn->query("SELECT DISTINCT Titel FROM QuestionForm ORDER BY Titel");
$formTitles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// QR-Code-URLs für jeden Titel generieren
$formGroups = [];
foreach ($formTitles as $title) {
    // Eindeutigen Code für die Formulargruppe generieren
    $code = substr(md5($title), 0, 12);

    // URL für QR-Code generieren
    $url = "http://" . $_SERVER['HTTP_HOST'] . "/view/FormRedirect.php?code=" . urlencode($code);

    $formGroups[] = [
        'title' => $title,
        'code' => $code,
        'url' => $url
    ];
}

$pageTitle = "QR-Code Verwaltung";
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
    <!-- QR-Code-Bibliothek -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body class="has-navbar">
<!-- Navbar -->
<?php include '../php_assets/Navbar.php'; ?>

<div class="container">
    <!-- Sidebar -->
    <?php include '../php_assets/Sidebar.php'; ?>

    <!-- Inhalt in Form-Section -->
    <section class="form-section">
        <h2>QR-Code Verwaltung für Formulare</h2>

        <?php if (empty($formGroups)): ?>
            <div class="message-box info">
                Keine Formulare gefunden. Bitte erstellen Sie zuerst Formulare.
            </div>
        <?php else: ?>

            <p>Hier sind die QR-Codes für alle Formulare. Jedes Team, das den QR-Code scannt, wird nach dem Login zu seinem entsprechenden Formular weitergeleitet.</p>

            <div class="qr-codes-grid" id="qrCodesGrid">
                <?php foreach ($formGroups as $index => $group): ?>
                    <div class="qr-code-item">
                        <h3><?php echo htmlspecialchars($group['title']); ?></h3>
                        <div class="qr-code-container" id="qrcode-<?php echo $index; ?>" data-url="<?php echo htmlspecialchars($group['url']); ?>"></div>
                        <div class="qr-code-info">
                            <p>URL: <a href="<?php echo htmlspecialchars($group['url']); ?>" target="_blank"><?php echo htmlspecialchars($group['url']); ?></a></p>
                            <button class="btn download-btn" onclick="downloadQrCode(<?php echo (int)$index; ?>, <?php echo json_encode_for_js($group['title']); ?>)">QR-Code herunterladen</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Info-Section für zusätzliche Informationen -->
    <div class="info-section">
        <h2>Informationen zur QR-Code-Nutzung</h2>
        <div class="info-content">
            <h3>So funktionieren die QR-Codes</h3>
            <p>Die QR-Codes in dieser Übersicht leiten Teams zu ihren jeweiligen Formularen weiter. Jeder QR-Code ist einem Formulartyp zugeordnet.</p>

            <h3>Anwendung im Wettkampf</h3>
            <p>Drucken Sie die QR-Codes aus und platzieren Sie sie an den entsprechenden Stationen oder Wartepunkten. Teams können dann:</p>
            <ul>
                <li>Den QR-Code mit ihrem Smartphone scannen</li>
                <li>Bei Bedarf ihre Anmeldedaten eingeben</li>
                <li>Automatisch zum richtigen Formular weitergeleitet werden</li>
            </ul>
        </div>
    </div>
</div>

<!-- JavaScript-Datei einbinden -->
<script src="../js/QrCodeManagementScript.js"></script>
</body>
</html>