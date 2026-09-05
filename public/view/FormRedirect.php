<?php

require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

// Datenbank-Verbindung und Models laden
require_once '../db/DbConnection.php';
require_once '../model/FormCollectionModel.php';

use FormCollection\FormCollectionModel;

// QR-Code Token aus der URL abrufen
$token = isset($_GET['code']) ? trim($_GET['code']) : '';

if (empty($token)) {
    die("Fehler: Ungültiger QR-Code.");
}

if (!isset($conn)) {
    die("Datenbankverbindung nicht verfügbar.");
}

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['id']) || !isset($_SESSION['login']) || $_SESSION['login'] !== 'ok') {
    // Nicht angemeldet, Token in Session speichern und zum Login weiterleiten
    $_SESSION['redirect_code'] = $token;
    header("Location: Login.php?redirect=form");
    exit;
}

// Benutzer ist angemeldet, FormCollection-System verwenden
$userId = $_SESSION['id'];
$formCollectionModel = new FormCollectionModel($conn);

// Team-ID des Benutzers ermitteln
$stmt = $conn->prepare("SELECT mannschaft_ID FROM User WHERE ID = :userId");
$stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result || !$result['mannschaft_ID']) {
    // Benutzer hat kein Team
    $errorMessage = "Du bist keinem Team zugeordnet. Bitte wende dich an die Wettkampfleitung.";
    $pageTitle = "Formular-Weiterleitung";
} else {
    $teamId = $result['mannschaft_ID'];

    // Token in CollectionFormToken-Tabelle nachschlagen
    $tokenInfo = $formCollectionModel->resolveFormToken($token);

    if (!$tokenInfo) {
        $errorMessage = "Ungültiger QR-Code. Das Formular wurde nicht gefunden.";
        $pageTitle = "Formular-Weiterleitung";
    } else {
        // TeamFormInstance on-demand erstellen
        $instanceResult = $formCollectionModel->createTeamFormInstanceOnDemand(
            $teamId,
            $tokenInfo['collection_ID'],
            $tokenInfo['formNumber']
        );

        if ($instanceResult && isset($instanceResult['token'])) {
            // Erfolg - zur FormView mit Instance-Token weiterleiten
            header("Location: FormView.php?token=" . $instanceResult['token']);
            exit;
        } else {
            $errorMessage = "Fehler beim Erstellen des Formulars. Bitte versuche es erneut oder wende dich an die Wettkampfleitung.";
            $pageTitle = "Formular-Weiterleitung";
        }
    }
}

// Wenn wir hier ankommen, ist ein Fehler aufgetreten
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
    <link rel="stylesheet" href="../css/Footer.css">
    <link rel="stylesheet" href="../css/FormViewStyling.css">
</head>
<body class="has-navbar">
<?php include '../php_assets/Navbar.php'; ?>

<div class="form-container">
    <div class="form-header">
        <h1>Formular-Weiterleitung</h1>
    </div>

    <div class="message-box error">
        <?php echo isset($errorMessage) ? htmlspecialchars($errorMessage) : "Ein unbekannter Fehler ist aufgetreten."; ?>
    </div>

    <div class="form-info">
        <h3>Informationen</h3>
        <ul>
            <li>Stelle sicher, dass du den korrekten QR-Code gescannt hast</li>
            <li>Überprüfe deine Team-Zuordnung in den Einstellungen</li>
            <li>Bei anhaltenden Problemen wende dich an die Wettkampfleitung</li>
        </ul>
    </div>

    <div class="form-actions">
        <a href="../index.php" class="btn">Zurück zur Startseite</a>
    </div>
</div>

<script>
    // Nach 8 Sekunden zur Startseite zurückleiten
    setTimeout(function() {
        window.location.href = '../index.php';
    }, 8000);
</script>
</body>
</html>