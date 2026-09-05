<?php

require_once __DIR__ . '/../CookieMonster.php';
require_once __DIR__ . '/../auth/OidcClient.php';

startSecureSession();

$client = OidcClient::fromEnv();
if ($client === null) {
    header('Location: /view/Login.php?f=sso_disabled');
    exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['oidc_state'] = $state;

try {
    $authorizeUrl = $client->buildAuthorizeUrl($state);
} catch (Throwable $e) {
    error_log('OIDC start failed: ' . $e->getMessage());
    header('Location: /view/Login.php?f=sso_error');
    exit;
}

header('Location: ' . $authorizeUrl);
exit;
