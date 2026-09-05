<?php
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';
require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

require_once '../db/DbConnection.php';
require_once '../controller/CompetitionResetController.php';
require_once '../php_assets/CustomAlertBox.php';

use Competition\CompetitionResetController;

// Überprüfen, ob eine Datenbankverbindung besteht
if (!isset($conn)) {
    require __DIR__ . '/../php_assets/DbErrorPage.php'; die();
}

// Weiterleitung, wenn der Nutzer nicht eingeloggt ist
if(!isset($_SESSION["login"]) || $_SESSION["login"] !== "ok"){
    header("Location: Login.php");
    exit;
}

// Weiterleitung, wenn Berechtigungen nicht Korrekt sind
$allowedAccountTypes = ['Wettkampfleitung', 'Admin'];
if(!isset($_SESSION["acc_typ"]) || !in_array($_SESSION["acc_typ"], $allowedAccountTypes)){
    header("Location: ../index.php");
    exit;
}

// Controller instanziieren
$controller = new CompetitionResetController($conn);

// Request verarbeiten
$controller->handleRequest();

// Meldung und Meldungstyp aus dem Controller holen
$message = $controller->getMessage();
$messageType = $controller->getMessageType();

$pageTitle = "Wettkampf zurücksetzen";
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
    <link rel="stylesheet" href="../css/Components.css">
    <link rel="stylesheet" href="../css/CompetitionResetStyling.css">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body class="has-navbar">
<!-- Navbar -->
<?php include '../php_assets/Navbar.php'; ?>

<div class="container">
    <section class="main-content">
        <!-- Warnung und Hinweise -->
        <div class="warning-box">
            <h2><span class="warning-icon">⚠️</span> ACHTUNG: Datenlöschung</h2>
            <p>Die folgenden Aktionen werden <strong>unwiderruflich</strong> Daten aus der Datenbank löschen.
                Stellen Sie sicher, dass Sie die Konsequenzen verstehen.</p>
            <p><strong>Wichtig:</strong> Bestätigen Sie jede Aktion durch Aktivieren der Checkbox und klicken Sie dann auf den entsprechenden Button.</p>
        </div>

        <!-- Erfolgs- oder Fehlermeldung anzeigen -->
        <?php if (!empty($message)): ?>
            <div class="message-box <?php echo htmlspecialchars($messageType); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="reset-cards">
            <!-- Staffeln Reset -->
            <div class="reset-card">
                <h3>Staffeln zurücksetzen</h3>
                <p>Löscht alle Staffeln und deren Verbindungen zu Mannschaften.</p>
                <form method="POST" action="" class="reset-form" id="form-staffeln">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="reset_staffeln" value="1">
                    <div class="form-actions standard-card">
                        <div class="confirm-checkbox">
                            <input type="checkbox" id="checkbox-staffeln" name="confirm" value="1" required>
                            <label for="checkbox-staffeln">Ich bestätige die Löschung</label>
                        </div>
                        <button type="button" class="danger-button reset-button" data-reset-type="staffeln">Staffeln zurücksetzen</button>
                    </div>
                </form>
            </div>

            <!-- Stationen Reset -->
            <div class="reset-card">
                <h3>Stationen zurücksetzen</h3>
                <p>Löscht alle Stationen, Protokolle und deren Verbindungen.</p>
                <form method="POST" action="" class="reset-form" id="form-stationen">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="reset_stationen" value="1">
                    <div class="form-actions standard-card">
                        <div class="confirm-checkbox">
                            <input type="checkbox" id="checkbox-stationen" name="confirm" value="1" required>
                            <label for="checkbox-stationen">Ich bestätige die Löschung</label>
                        </div>
                        <button type="button" class="danger-button reset-button" data-reset-type="stationen">Stationen zurücksetzen</button>
                    </div>
                </form>
            </div>

            <!-- Protokolle Reset -->
            <div class="reset-card">
                <h3>Protokolle zurücksetzen</h3>
                <p>Löscht alle Protokolle und deren Verbindungen zu Mannschaften.</p>
                <form method="POST" action="" class="reset-form" id="form-protokolle">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="reset_protokolle" value="1">
                    <div class="form-actions standard-card">
                        <div class="confirm-checkbox">
                            <input type="checkbox" id="checkbox-protokolle" name="confirm" value="1" required>
                            <label for="checkbox-protokolle">Ich bestätige die Löschung</label>
                        </div>
                        <button type="button" class="danger-button reset-button" data-reset-type="protokolle">Protokolle zurücksetzen</button>
                    </div>
                </form>
            </div>

            <!-- Mannschaften Reset -->
            <div class="reset-card">
                <h3>Mannschaften zurücksetzen</h3>
                <p>Löscht alle Mannschaften und deren Verbindungen.</p>
                <form method="POST" action="" class="reset-form" id="form-mannschaften">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="reset_mannschaften" value="1">
                    <div class="form-actions standard-card">
                        <div class="confirm-checkbox">
                            <input type="checkbox" id="checkbox-mannschaften" name="confirm" value="1" required>
                            <label for="checkbox-mannschaften">Ich bestätige die Löschung</label>
                        </div>
                        <button type="button" class="danger-button reset-button" data-reset-type="mannschaften">Mannschaften zurücksetzen</button>
                    </div>
                </form>
            </div>

            <!-- Formulare Reset -->
            <div class="reset-card">
                <h3>Formulare zurücksetzen</h3>
                <p>Löscht alle Formulare und deren Verbindungen. Die Fragenpools bleiben erhalten.</p>
                <form method="POST" action="" class="reset-form" id="form-formulare">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="reset_formulare" value="1">
                    <div class="form-actions standard-card">
                        <div class="confirm-checkbox">
                            <input type="checkbox" id="checkbox-formulare" name="confirm" value="1" required>
                            <label for="checkbox-formulare">Ich bestätige die Löschung</label>
                        </div>
                        <button type="button" class="danger-button reset-button" data-reset-type="formulare">Formulare zurücksetzen</button>
                    </div>
                </form>
            </div>

            <!-- Wertungen Reset -->
            <div class="reset-card">
                <h3>Wertungen zurücksetzen</h3>
                <p>Löscht alle Wertungsklassen und deren Verbindungen zu Mannschaften.</p>
                <form method="POST" action="" class="reset-form" id="form-wertungen">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="reset_wertungen" value="1">
                    <div class="form-actions standard-card">
                        <div class="confirm-checkbox">
                            <input type="checkbox" id="checkbox-wertungen" name="confirm" value="1" required>
                            <label for="checkbox-wertungen">Ich bestätige die Löschung</label>
                        </div>
                        <button type="button" class="danger-button reset-button" data-reset-type="wertungen">Wertungen zurücksetzen</button>
                    </div>
                </form>
            </div>

            <!-- Benutzer Reset -->
            <div class="reset-card">
                <h3>Benutzer zurücksetzen</h3>
                <p>Löscht alle Benutzer außer dem aktuell angemeldeten Benutzer.</p>
                <form method="POST" action="" class="reset-form" id="form-users">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="reset_users" value="1">
                    <div class="form-actions standard-card">
                        <div class="confirm-checkbox">
                            <input type="checkbox" id="checkbox-users" name="confirm" value="1" required>
                            <label for="checkbox-users">Ich bestätige die Löschung</label>
                        </div>
                        <button type="button" class="danger-button reset-button" data-reset-type="users">Benutzer zurücksetzen</button>
                    </div>
                </form>
            </div>

            <!-- ALLES Reset -->
            <div class="reset-card all-reset">
                <h3>ALLE DATEN ZURÜCKSETZEN</h3>
                <p>Löscht <strong>ALLE</strong> Wettkampfdaten außer dem aktuell angemeldeten Benutzer.</p>
                <form method="POST" action="" class="reset-form" id="form-all">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="reset_all" value="1">
                    <div class="form-actions large-card">
                        <div class="confirm-checkbox">
                            <input type="checkbox" id="checkbox-all" name="confirm" value="1" required>
                            <label for="checkbox-all">Ich bestätige die Löschung ALLER Daten</label>
                        </div>
                        <button type="button" class="danger-button reset-button" data-reset-type="all">KOMPLETTEN WETTKAMPF ZURÜCKSETZEN</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<?php
// CustomAlertBoxen für Bestätigungen rendern
$resetTypes = [
    'staffeln' => ['title' => 'Staffeln löschen', 'message' => 'Möchten Sie wirklich alle Staffeln und ihre Verbindungen löschen? Diese Aktion kann nicht rückgängig gemacht werden.'],
    'stationen' => ['title' => 'Stationen löschen', 'message' => 'Möchten Sie wirklich alle Stationen, Protokolle und ihre Verbindungen löschen? Diese Aktion kann nicht rückgängig gemacht werden.'],
    'protokolle' => ['title' => 'Protokolle löschen', 'message' => 'Möchten Sie wirklich alle Protokolle und ihre Verbindungen löschen? Diese Aktion kann nicht rückgängig gemacht werden.'],
    'mannschaften' => ['title' => 'Mannschaften löschen', 'message' => 'Möchten Sie wirklich alle Mannschaften und ihre Verbindungen löschen? Diese Aktion kann nicht rückgängig gemacht werden.'],
    'formulare' => ['title' => 'Formulare löschen', 'message' => 'Möchten Sie wirklich alle Formulare und ihre Verbindungen löschen? Die Fragenpools bleiben erhalten. Diese Aktion kann nicht rückgängig gemacht werden.'],
    'wertungen' => ['title' => 'Wertungen löschen', 'message' => 'Möchten Sie wirklich alle Wertungsklassen und ihre Verbindungen löschen? Diese Aktion kann nicht rückgängig gemacht werden.'],
    'users' => ['title' => 'Benutzer löschen', 'message' => 'Möchten Sie wirklich alle Benutzer (außer dem aktuell angemeldeten) löschen? Diese Aktion kann nicht rückgängig gemacht werden.'],
    'all' => ['title' => 'ALLE DATEN LÖSCHEN', 'message' => 'WARNUNG: Sie sind dabei, ALLE WETTKAMPFDATEN unwiderruflich zu löschen! Dies umfasst alle Mannschaften, Stationen, Protokolle, Staffeln, Formulare, Wertungen und Benutzer (außer Ihrem eigenen). Diese Aktion kann NICHT rückgängig gemacht werden!']
];

foreach ($resetTypes as $type => $config) {
    echo CustomAlertBox::renderSimpleConfirm(
        "modal-{$type}",
        $config['title'],
        $config['message'],
        "submitResetForm('{$type}');",
        "closeModal('modal-{$type}');"
    );
}

// Erfolgs- oder Fehlermeldung, falls vorhanden
if (!empty($message)) {
    $title = $messageType === 'success' ? 'Erfolg' : ($messageType === 'error' ? 'Fehler' : 'Information');
    echo CustomAlertBox::renderSimpleAlert(
        "message-alert",
        $title,
        $message
    );
    echo '<script>document.addEventListener("DOMContentLoaded", function() { 
            showModal("message-alert");
          });</script>';
}
?>

<?php include '../php_assets/Footer.php'; ?>

<!-- JavaScript für Funktionalität -->
<script src="../js/ModalUtils.js"></script>
<script src="../js/CompetitionResetScript.js"></script>
</body>
</html>