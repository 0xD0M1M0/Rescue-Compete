<?php

/**
 * Session-Guard: Stellt sicher, dass eine gültige Login-Session existiert.
 * Bei fehlendem Login wird auf view/Login.php weitergeleitet, wobei die
 * aktuell angeforderte URL als `return`-Parameter mitgegeben wird, damit
 * der User nach erfolgreichem Login automatisch dorthin zurückspringt.
 *
 * Aufruf am Beginn jeder geschützten Seite:
 *   require_once __DIR__ . '/../php_assets/RequireLogin.php';
 *   requireLogin();
 */
function requireLogin(): void
{
    require_once __DIR__ . '/../CookieMonster.php';
    startSecureSession();
    enforce_post_same_origin();

    if (isset($_SESSION["login"]) && $_SESSION["login"] === "ok") {
        return;
    }

    // Aktuelle URL als sicheren relativen Pfad ermitteln (Open-Redirect-Schutz)
    $return = $_SERVER['REQUEST_URI'] ?? '';
    if (!is_string($return) || $return === ''
        || $return[0] !== '/'
        || str_starts_with($return, '//')
        || strpos($return, '://') !== false
        || strpos($return, "\r") !== false
        || strpos($return, "\n") !== false) {
        $return = '';
    }

    // Konsumenten liegen in /controller/ oder /view/, daher relativ "../view/Login.php"
    $loginUrl = '../view/Login.php';
    if ($return !== '') {
        $loginUrl .= '?return=' . urlencode($return);
    }

    header("Location: $loginUrl");
    exit;
}
