<?php
require_once __DIR__ . '/CookieMonster.php';
require_once __DIR__ . '/auth/OidcClient.php';

startSecureSession();

logout();

/**
 * Loggt den aktuell angemeldeten Benutzer aus und löscht die Session.
 * Bei OIDC-Login zusätzlich IdP-Session beenden (sonst bleibt man bei Keycloak angemeldet).
 */
function logout() {
    $viaOidc = !empty($_SESSION['login_via_oidc']);
    $idToken = isset($_SESSION['oidc_id_token']) ? (string)$_SESSION['oidc_id_token'] : null;

    // Absolute URL für die Weiterleitung
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = htmlspecialchars($_SERVER["HTTP_HOST"]);
    $baseUrl = $protocol . $host . rtrim(dirname(dirname(htmlspecialchars($_SERVER["PHP_SELF"]))), "/\\");
    $loginUrl = "$baseUrl/view/Login.php";

    $oidcLogoutUrl = null;
    if ($viaOidc) {
        $client = OidcClient::fromEnv();
        if ($client !== null) {
            try {
                $oidcLogoutUrl = $client->buildLogoutUrl($idToken, $loginUrl);
            } catch (Throwable $e) {
                error_log('OIDC logout URL failed: ' . $e->getMessage());
            }
        }
    }

    // Session-Array leeren
    session_unset();

    // Cookie für die Session entfernen
    removeSessionCookie();

    // Session löschen
    session_destroy();

    if ($oidcLogoutUrl !== null) {
        header('Location: ' . $oidcLogoutUrl);
        exit;
    }

    header('Location: ' . $loginUrl);
    exit;
}
