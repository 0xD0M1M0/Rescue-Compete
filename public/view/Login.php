<?php
require_once __DIR__ . '/../CookieMonster.php';
startSecureSession();

require_once __DIR__ . '/../auth/OidcClient.php';
$ssoEnabled = OidcClient::isEnabled();
$ssoLoginLabel = OidcClient::loginLabel();

// Absolute URLs für die Weiterleitung
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = htmlspecialchars($_SERVER["HTTP_HOST"]);
$baseUrl = $protocol . $host . rtrim(dirname(htmlspecialchars($_SERVER["PHP_SELF"]), 2), "/\\");

// Validiert einen Return-Pfad gegen Open-Redirect-Angriffe.
// Akzeptiert nur absolute Pfade auf derselben Domain.
function isValidReturnPath($path): bool {
    if (!is_string($path) || $path === '') return false;
    if ($path[0] !== '/') return false;
    if (str_starts_with($path, '//')) return false;
    if (strpos($path, '://') !== false) return false;
    if (strpos($path, "\r") !== false || strpos($path, "\n") !== false) return false;
    return true;
}

// QR-Code aus der URL-Weiterleitung erfassen
if (isset($_GET['redirect']) && $_GET['redirect'] === 'form' && isset($_GET['code'])) {
    $_SESSION['redirect_code'] = $_GET['code'];
}

// Return-URL aus der URL erfassen (von einem geschützten Controller geschickt)
if (isset($_GET['return']) && isValidReturnPath($_GET['return'])) {
    $_SESSION['return_after_login'] = $_GET['return'];
}

// Leite den Nutzer weiter, wenn er bereits eingeloggt ist
if(isset($_SESSION["login"]) && $_SESSION["login"] === "ok"){
    // Falls ein redirect_code in der Session existiert, leite zum entsprechenden Formular weiter
    if (isset($_SESSION['redirect_code'])) {
        $code = $_SESSION['redirect_code'];
        // Code aus Session entfernen um Weiterleitungsschleifen zu verhindern
        unset($_SESSION['redirect_code']);
        header("location: FormRedirect.php?code=" . urlencode($code));
        exit;
    }

    // Falls eine return-URL gespeichert ist, dorthin zurückspringen
    if (!empty($_SESSION['return_after_login']) && isValidReturnPath($_SESSION['return_after_login'])) {
        $target = $_SESSION['return_after_login'];
        unset($_SESSION['return_after_login']);
        header("location: $baseUrl" . $target);
        exit;
    }

    header("location: $baseUrl/index.php");
    exit;
}

$pageTitle = "Login";

// Fehlermeldung basierend auf dem Parameter anzeigen
$errorMessage = '';
$noticeMessage = '';

if (isset($_GET['f'])) {
    switch ($_GET['f']) {
        case '1':
            $errorMessage = 'Bitte geben Sie Benutzername und Passwort ein.';
            break;
        case '2':
            $errorMessage = 'Benutzer nicht gefunden.';
            break;
        case '3':
            $errorMessage = 'Falsches Passwort.';
            break;
        case 'sso_only':
            $errorMessage = 'Dieser Account ist mit SSO verknüpft. Bitte über SSO anmelden.';
            break;
        case 'sso_disabled':
            $errorMessage = 'SSO-Anmeldung ist nicht konfiguriert.';
            break;
        case 'sso_state':
            $errorMessage = 'SSO-Anmeldung ungültig (State). Bitte erneut versuchen.';
            break;
        case 'sso_email':
            $errorMessage = 'SSO-Anmeldung hat keine E-Mail geliefert. Anmeldung nicht möglich.';
            break;
        case 'sso_error':
            $errorMessage = 'Anmeldung über SSO fehlgeschlagen. Bitte versuchen Sie es erneut.';
            break;
        case '999':
            $errorMessage = 'Datenbankverbindung nicht verfügbar.';
            break;
        default:
            $errorMessage = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
    }
}

// QR-Code Weiterleitung Hinweis
if (isset($_SESSION['redirect_code'])) {
    $noticeMessage = 'Bitte melde dich an, um zum Formular zu gelangen.';
}

// Weiterleitung nach Login anpassen - verstecktes Feld für QR-Code-Weiterleitung
$loginAction = '../controller/LoginManager.php';
if (isset($_SESSION['redirect_code'])) {
    // Wenn ein QR-Code in der Session ist, URL-Parameter hinzufügen
    $loginAction .= '?redirect_after=form';
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RescueCompete - <?php echo $pageTitle; ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logos/ww-favicon.ico">
    <link rel="stylesheet" href="../css/Colors.css">
    <link rel="stylesheet" href="../css/GlobalLayout.css">
    <link rel="stylesheet" href="../css/Components.css">
    <link rel="stylesheet" href="../css/Login.css">
    <link rel="stylesheet" href="../css/PasswordVisibility.css">
    <script src="../js/LoginErrors.js"></script>
    <script src="../js/PasswordVisibility.js"></script>
</head>
<body>

<div id="body_container">
    <div class="login-wrapper">
        <!-- Login Formular -->
        <form class="form-section" method="post" action="<?php echo $loginAction; ?>" id="login_form">
            <h2>Wettkampf-Software Login</h2>

            <div class="form-group">
                <label for="username">Benutzername:</label>
                <input type="text" class="valid" id="username" name="username" placeholder="Benutzername eingeben" required>
            </div>

            <div class="form-group">
                <label for="password">Passwort:</label>
                <div class="password-container">
                    <input type="password" class="valid" id="password" name="password" placeholder="Passwort eingeben" required>
                    <button type="button" id="toggle-password" class="toggle-password" title="Passwort anzeigen">
                        <span id="toggle-icon" class="eye-icon eye-closed"></span>
                    </button>
                </div>
            </div>

            <?php if (isset($_SESSION['redirect_code'])): ?>
                <!-- Versteckte Felder für die Weiterleitung -->
                <input type="hidden" name="redirect_qrcode" value="<?php echo htmlspecialchars($_SESSION['redirect_code']); ?>">
            <?php endif; ?>

            <div class="button-group">
                <input type="submit" value="Einloggen">
            </div>
        </form>

        <?php if ($ssoEnabled): ?>
            <div class="sso-section">
                <div class="sso-divider"><span>oder</span></div>
                <a class="sso-button" href="../controller/OidcSsoStart.php"><?php echo htmlspecialchars($ssoLoginLabel); ?></a>
            </div>
        <?php endif; ?>

        <!-- Container für Fehlermeldungen außerhalb der Loginbox -->
        <div id="error-message" class="message-container">
            <?php if (!empty($errorMessage)): ?>
                <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <?php if (!empty($noticeMessage)): ?>
                <div class="notice-message"><?php echo htmlspecialchars($noticeMessage); ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
