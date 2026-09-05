<?php

require_once __DIR__ . '/../db/DbConnection.php';
require_once __DIR__ . '/../model/UserModel.php';
require_once __DIR__ . '/../CookieMonster.php';
require_once __DIR__ . '/../auth/OidcClient.php';
require_once __DIR__ . '/../auth/SessionLogin.php';

use Station\UserModel;

startSecureSession();

function redirectSsoError(string $code): void
{
    header('Location: /view/Login.php?f=' . urlencode($code));
    exit;
}

$client = OidcClient::fromEnv();
if ($client === null) {
    redirectSsoError('sso_disabled');
}

if (isset($_GET['error'])) {
    error_log('OIDC IdP error: ' . ($_GET['error_description'] ?? $_GET['error']));
    redirectSsoError('sso_error');
}

$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$state = isset($_GET['state']) ? trim((string)$_GET['state']) : '';
$expectedState = $_SESSION['oidc_state'] ?? null;
unset($_SESSION['oidc_state']);

if ($code === '' || $state === '' || !is_string($expectedState) || !hash_equals($expectedState, $state)) {
    redirectSsoError('sso_state');
}

if (!isset($conn) || !($conn instanceof PDO)) {
    redirectSsoError('999');
}

try {
    $claims = $client->exchangeCode($code);
} catch (Throwable $e) {
    error_log('OIDC callback failed: ' . $e->getMessage());
    redirectSsoError('sso_error');
}

$model = new UserModel($conn);

$emailVerified = !empty($claims['email_verified']);
$trustedEmail = ($emailVerified && !empty($claims['email'])) ? $claims['email'] : null;

// E-Mail-Match nur mit verifizierter IdP-E-Mail; sonst bestehendes oidc_sub
$user = null;
if ($trustedEmail !== null) {
    $user = $model->findBySsoEmail($trustedEmail);
}
if (!$user) {
    $user = $model->findByOidcSub($claims['sub']);
}

if ($user) {
    $needsLink = empty($user['oidc_sub']) || $user['oidc_sub'] !== $claims['sub'];
    if ($needsLink) {
        if (!$model->linkOidcSub((int)$user['ID'], $claims['sub'])) {
            redirectSsoError('sso_error');
        }
        $user = $model->read((int)$user['ID']);
    }
}

if (!$user) {
    if ($trustedEmail === null) {
        redirectSsoError('sso_email');
    }

    $newId = $model->createFromSso(
        $claims['sub'],
        $trustedEmail,
        $claims['preferred_username']
    );

    if ($newId === null) {
        redirectSsoError('sso_error');
    }

    $user = $model->read($newId);
}

if (!$user) {
    redirectSsoError('sso_error');
}

establishUserSession($user);
if (!empty($claims['id_token'])) {
    $_SESSION['oidc_id_token'] = $claims['id_token'];
    $_SESSION['login_via_oidc'] = true;
}
redirectAfterLogin();
