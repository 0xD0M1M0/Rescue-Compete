<?php
require_once '../db/DbConnection.php';
require_once '../model/UserModel.php';
require_once '../CookieMonster.php';
require_once __DIR__ . '/../auth/PasswordHash.php';
require_once __DIR__ . '/../auth/SessionLogin.php';
require_once __DIR__ . '/../php_assets/SecurityHelpers.php';

use Station\UserModel;

startSecureSession();
enforce_post_same_origin();

if (!isset($conn) || !($conn instanceof PDO)) {
    error_log("Datenbankverbindung nicht verfügbar");
    require __DIR__ . '/../php_assets/DbErrorPage.php'; die();
}

$model = new UserModel($conn);

$hasQrRedirect = !empty($_POST['redirect_qrcode']);
$qrCode = $hasQrRedirect ? $_POST['redirect_qrcode'] : '';

$redirectPath = "/view/Login.php";
$errorFlag = "1";

$username = isset($_POST['username']) ? trim($_POST['username']) : "";
$password = isset($_POST['password']) ? trim($_POST['password']) : "";

if (!empty($username) && !empty($password)) {
    $user = $model->bootlegRead($username);

    if ($user) {
        // SSO-verknüpfte Accounts: nur IdP-Login
        if (!empty($user['oidc_sub']) || empty($user['passwordHash'])) {
            $errorFlag = "sso_only";
        } elseif (PasswordHash::verify($password, $user["passwordHash"])) {
            if (PasswordHash::needsRehash($user["passwordHash"])) {
                $upgraded = PasswordHash::hash($password);
                if (!$model->updatePassword((int)$user["ID"], $upgraded)) {
                    error_log("Warnung: Passwort-Hash konnte nicht modernisiert werden für User-ID " . (int)$user["ID"]);
                }
            }

            establishUserSession($user);

            if ($hasQrRedirect) {
                $_SESSION['redirect_code'] = $qrCode;
            }
            redirectAfterLogin();
        } else {
            $errorFlag = "3";
        }
    } else {
        $errorFlag = "2";
    }
}

if ($hasQrRedirect && strpos($redirectPath, "Login.php") !== false) {
    $redirectPath .= "?f=" . $errorFlag . "&redirect=form&code=" . urlencode($qrCode);
} else {
    $redirectPath .= "?f=" . $errorFlag;
}

header("Location: $redirectPath");
exit;
