<?php
require_once '../db/DbConnection.php';
require_once '../model/UserModel.php';
require_once '../model/TeamModel.php';
require_once '../controller/UserController.php';
require_once '../php_assets/CustomAlertBox.php';
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';

use Nutzer\UserController;
use Station\UserModel;
use Mannschaft\TeamModel;

require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

// Prüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['id']) || !isset($_SESSION['login']) || $_SESSION['login'] !== 'ok') {
    header("Location: ../index.php");
    exit;
}

$allowedAccountTypes = ['Admin', 'Wettkampfleitung'];
if (!isset($_SESSION['acc_typ']) || !in_array($_SESSION['acc_typ'], $allowedAccountTypes, true)) {
    header("Location: ../index.php");
    exit;
}

if (!isset($conn)) {
    die("Datenbankverbindung nicht verfügbar.");
}

// Benachrichtigungen aus Session abrufen
$sessionMessage = "";
$sessionMessageType = "";
if (isset($_SESSION['notification_message'])) {
    $sessionMessage = $_SESSION['notification_message'];
    $sessionMessageType = $_SESSION['notification_type'] ?? 'info';
    unset($_SESSION['notification_message'], $_SESSION['notification_type']);
}

// Model und Controller instanziieren
$model = new UserModel($conn);
$mannschaftModel = new TeamModel($conn);
$controller = new UserController($model);
$controller->handleRequest();

// Daten für die View
$modalData = $controller->modalData;
$message = $controller->message;
$messageType = $controller->messageType;

// Session-Nachrichten haben Priorität vor Controller-Nachrichten
if (!empty($sessionMessage)) {
    $message = $sessionMessage;
    $messageType = $sessionMessageType;
}

$users = $model->readNonAdminUsers(); // Nur Nicht-Admin-Benutzer laden
$mannschaften = $mannschaftModel->read(); // Für Dropdown

// Aktuelle Ansicht bestimmen
$currentView = sanitize_view_param($_GET['view'] ?? null, ['overview', 'create'], 'overview');

$pageTitle = "Benutzerverwaltung";
$isAdminSession = isset($_SESSION['acc_typ']) && $_SESSION['acc_typ'] === 'Admin';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RescueCompete - <?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/x-icon" href="../assets/images/logos/ww-favicon.ico">
    <link rel="stylesheet" href="../css/Colors.css">
    <link rel="stylesheet" href="../css/GlobalLayout.css">
    <link rel="stylesheet" href="../css/Navbar.css">
    <link rel="stylesheet" href="../css/Sidebar.css">
    <link rel="stylesheet" href="../css/Footer.css">
    <link rel="stylesheet" href="../css/Components.css">
    <link rel="stylesheet" href="../css/FormCollectionViewStyling.css">
    <link rel="stylesheet" href="../css/UserInputViewStyling.css">
</head>
<body class="has-navbar">
<!-- Navbar -->
<?php include '../php_assets/Navbar.php'; ?>

<div class="container">
    <!-- Sidebar -->
    <?php include '../php_assets/Sidebar.php'; ?>

    <!-- Hauptinhalt -->
    <div class="main-content vertical">

        <!-- Navigation Tabs -->
        <div class="tab-navigation">
            <button class="tab-button <?php echo $currentView === 'overview' ? 'active' : ''; ?>"
                    data-tab="overview"
                    onclick="showTab('overview')">Übersicht</button>
            <button class="tab-button <?php echo $currentView === 'create' ? 'active' : ''; ?>"
                    data-tab="create"
                    onclick="showTab('create')">Neuen Benutzer erstellen</button>
        </div>

        <!-- Tab: Übersicht -->
        <div id="overview" class="tab-content <?php echo $currentView === 'overview' ? 'active' : ''; ?>">
            <div class="data-container">
                <div class="actions-bar">
                    <button class="btn primary-btn" onclick="showTab('create')">
                        Neuen Benutzer erstellen
                    </button>
                    <?php if (isset($_SESSION['acc_typ']) && $_SESSION['acc_typ'] === 'Admin'): ?>
                        <a href="AdminUserInputView.php" class="btn secondary-btn">
                            Zur Admin-Verwaltung
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($users)): ?>
                    <div class="no-data">
                        <p>Keine Benutzer vorhanden.</p>
                        <p><a href="#" onclick="showTab('create')">Erstellen Sie den ersten Benutzer</a></p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Benutzername</th>
                            <th>Neues Passwort</th>
                            <th>Rolle / SSO</th>
                            <th>Team</th>
                            <th>Aktionen</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    <?php if (!empty($user['oidc_sub'])): ?>
                                        <br><small class="sso-linked-badge">SSO verknüpft</small>
                                    <?php else: ?>
                                        <br><small class="sso-unlinked-badge">nicht verknüpft</small>
                                    <?php endif; ?>
                                    <?php if ($user['ID'] == $_SESSION['id']): ?>
                                        <br><small class="current-user-indicator">Sie sind angemeldet</small>
                                    <?php endif; ?>
                                </td>
                                <td class="password-update-cell">
                                    <div class="password-update-container">
                                        <input type="password"
                                               class="password-input"
                                               id="password_<?php echo $user['ID']; ?>"
                                               placeholder="Neues Passwort eingeben"
                                               minlength="8">
                                        <button type="button"
                                                class="btn update-password-btn small"
                                                onclick="updateUserPassword(<?php echo $user['ID']; ?>)">
                                            Aktualisieren
                                        </button>
                                    </div>
                                </td>
                                <td class="role-sso-cell">
                                    <form method="POST" class="inline-meta-form">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo (int)$user['ID']; ?>">
                                        <select name="acc_typ" class="role-select" required>
                                            <?php
                                            $roles = ['Wartend', 'Wettkampfleitung', 'Schiedsrichter', 'Teilnehmer'];
                                            foreach ($roles as $role):
                                            ?>
                                                <option value="<?php echo $role; ?>" <?php echo ($user['acc_typ'] === $role) ? 'selected' : ''; ?>>
                                                    <?php echo $role; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($isAdminSession): ?>
                                        <input type="email"
                                               name="sso_email"
                                               class="sso-email-input"
                                               placeholder="SSO-E-Mail"
                                               value="<?php echo htmlspecialchars($user['sso_email'] ?? ''); ?>">
                                        <?php elseif (!empty($user['sso_email'])): ?>
                                        <small class="sso-email-readonly"><?php echo htmlspecialchars($user['sso_email']); ?></small>
                                        <?php endif; ?>
                                        <button type="submit" name="update_user_meta" value="1" class="btn small primary-btn">
                                            Speichern
                                        </button>
                                    </form>
                                </td>
                                <td class="assignment-cell">
                                    <?php if (!empty($user['Teamname'])): ?>
                                        <strong>Team:</strong> <?php echo htmlspecialchars($user['Teamname']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="action-cell">
                                    <div class="button-group">
                                        <button class="btn warning-btn small"
                                                onclick="confirmDeleteUser(<?php echo (int)$user['ID']; ?>, <?php echo json_encode_for_js($user['username']); ?>)">
                                            Löschen
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Neuen Benutzer erstellen -->
        <div id="create" class="tab-content <?php echo $currentView === 'create' ? 'active' : ''; ?>">
            <div class="data-container">
                <h3>Neuen Benutzer erstellen</h3>

                <form method="POST" id="createUserForm">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="username">Benutzername *</label>
                        <input type="text" id="username" name="username" required
                               placeholder="z.B. mueller.karl"
                               maxlength="32">
                        <small>Der Benutzername sollte eindeutig und leicht zu merken sein. Maximal 32 Zeichen.</small>
                    </div>

                    <div class="form-group">
                        <label for="password">Passwort</label>
                        <input type="password" id="password" name="password"
                               placeholder="Sicheres Passwort eingeben">
                        <small>Erforderlich für lokale Anmeldung. Optional, wenn SSO-E-Mail gesetzt ist.</small>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Passwort bestätigen</label>
                        <input type="password" id="password_confirm" name="password_confirm"
                               placeholder="Passwort wiederholen">
                        <div class="validation-message" id="password-mismatch">
                            Die Passwörter stimmen nicht überein.
                        </div>
                    </div>

                    <?php if ($isAdminSession): ?>
                    <div class="form-group">
                        <label for="sso_email">SSO-E-Mail</label>
                        <input type="email" id="sso_email" name="sso_email"
                               placeholder="z.B. name@example.org">
                        <small>Für Konto-Übernahme: E-Mail aus der SSO-Anmeldung. Leer lassen für rein lokale Accounts.</small>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="acc_typ">Account-Typ *</label>
                        <select id="acc_typ" name="acc_typ" required>
                            <option value="">Bitte Account-Typ auswählen</option>
                            <option value="Wartend">Wartend (ohne Rechte)</option>
                            <option value="Wettkampfleitung">Wettkampfleitung</option>
                            <option value="Schiedsrichter">Schiedsrichter</option>
                            <option value="Teilnehmer">Teilnehmer</option>
                        </select>
                    </div>

                    <!-- Dynamische Felder werden hier eingefügt -->
                    <div id="dynamic-fields"></div>

                    <div class="info-box">
                        <h4>Hinweise zur Benutzer-Erstellung:</h4>
                        <ul>
                            <li><strong>Wartend:</strong> Kann sich anmelden, hat aber noch keine Rechte</li>
                            <li><strong>Wettkampfleitung:</strong> Hat Zugriff auf alle Verwaltungsfunktionen</li>
                            <li><strong>Schiedsrichter:</strong> Kann Bewertungen eingeben und verwalten</li>
                            <li><strong>Teilnehmer:</strong> Müssen einem Team zugeordnet werden</li>
                            <?php if ($isAdminSession): ?>
                            <li><strong>SSO:</strong> Mit gesetzter SSO-E-Mail übernimmt der Nutzer den Account beim ersten SSO-Login</li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="add_user" class="btn primary-btn">Benutzer erstellen</button>
                        <button type="button" class="btn" onclick="showTab('overview')">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Mannschaften-Daten für JavaScript -->
<script>
    const mannschaften = <?php echo json_encode_for_js($mannschaften); ?>;
</script>

<!-- Modals -->
<?php
// Benutzer-Löschung bestätigen
echo CustomAlertBox::renderSimpleConfirm(
    "confirmDeleteUserModal",
    "Benutzer löschen",
    "Möchten Sie diesen Benutzer wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.",
    "deleteUser()",
    "closeModal('confirmDeleteUserModal')"
);

// Duplikat-Modal
if (!empty($modalData)):
    $alert = new CustomAlertBox("confirmDuplicateUser");
    $alert->setTitle("Duplikat gefunden");
    $alert->setMessage("Ein Benutzer mit diesem Namen existiert bereits. Möchten Sie diesen aktualisieren?");
    $alert->setData([
        'username' => $modalData['username'] ?? "",
        'passwordHash' => $modalData['passwordHash'] ?? "",
        'acc_typ' => $modalData['acc_typ'] ?? "",
        'mannschaft_ID' => $modalData['mannschaft_ID'] ?? "",
        'sso_email' => $modalData['sso_email'] ?? "",
        'duplicate_id' => $modalData['duplicate_id'] ?? "",
        'confirm_update' => "1",
        'add_user' => "1"
    ]);
    $alert->addButton("Ja", "", "btn primary-btn", "submit");
    $alert->addButton("Nein", "document.getElementById('confirmDuplicateUser').classList.remove('active');");
    echo $alert->render();
endif;

// Erfolgs-/Fehlermeldungen
if (!empty($message)):
    $alertType = ($messageType === 'success') ? 'Erfolg' :
        (($messageType === 'error') ? 'Fehler' : 'Hinweis');
    echo CustomAlertBox::renderSimpleAlert(
        "messageAlert",
        $alertType,
        $message
    );
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            setTimeout(function() {
                const messageAlert = document.getElementById("messageAlert");
                if (messageAlert) {
                    messageAlert.classList.add("active");
                }
            }, 100);
        });
    </script>';
endif;
?>

<!-- JavaScript einbinden -->
<script src="../js/UserInputScript.js"></script>

<!-- Tab-Initialisierung sicherstellen -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sicherstellen, dass der korrekte Tab angezeigt wird
        const currentView = <?php echo json_encode_for_js($currentView); ?>;
        showTab(currentView);

        // Duplikat-Modal anzeigen, falls vorhanden
        <?php if (!empty($modalData)): ?>
        setTimeout(function() {
            const duplicateModal = document.getElementById('confirmDuplicateUser');
            if (duplicateModal) {
                duplicateModal.classList.add('active');
            }
        }, 100);
        <?php endif; ?>
    });
</script>

<?php include '../php_assets/Footer.php'; ?>

</body>
</html>