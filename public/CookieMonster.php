<?php

require_once __DIR__ . '/php_assets/SecurityHelpers.php';

/**
 * SameSite for the session cookie.
 * Lax only when OIDC is enabled (IdP return is a cross-site top-level GET; Strict would drop the cookie).
 */
function sessionCookieSameSite(): string
{
    $enabled = getenv('OIDC_ENABLED');
    if ($enabled === '1' || strtolower((string)$enabled) === 'true') {
        return 'Lax';
    }
    return 'Strict';
}

/**
 * Initialisiert sichere Session-Einstellungen
 * MUSS vor session_start() aufgerufen werden
 */
function initializeSecureSession() {
    $params = [
        'lifetime' => 86400, // 24 Stunden
        'path' => '/',
        'httponly' => true,
        'samesite' => sessionCookieSameSite(),
    ];
    if (isHttpsRequest()) {
        $params['secure'] = true;
    }
    session_set_cookie_params($params);

    ini_set('session.gc_maxlifetime', 86400);
}

/**
 * Startet die Session mit sicheren Cookie-Parametern (idempotent).
 */
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    initializeSecureSession();
    session_start();
}

/**
 * Erstellt einen Cookie für die aktuelle Session mit längerem Ablauf
 * Wird NACH erfolgreichem Login aufgerufen
 */
function createSessionCookie() {
    // Prüfe, ob Session bereits gestartet ist
    if (session_status() !== PHP_SESSION_ACTIVE) {
        error_log("Warnung: createSessionCookie() aufgerufen, aber Session ist nicht aktiv");
        return false;
    }

    // Lebensdauer des Cookies auf 24 Stunden setzen
    $lifetime = time() + 86400; // 24 Stunden in Sekunden

    $options = [
        'expires' => $lifetime,
        'path' => '/',
        'httponly' => true,
        'samesite' => sessionCookieSameSite(),
    ];
    if (isHttpsRequest()) {
        $options['secure'] = true;
    }

    $result = setcookie(session_name(), session_id(), $options);

    if (!$result) {
        error_log("Fehler beim Setzen des Session-Cookies");
        return false;
    }

    return true;
}

/**
 * Entfernt den Cookie einer Session und löscht die Session-Daten
 */
function removeSessionCookie() {
    // Session-Daten löschen
    $_SESSION = [];

    // Cookie löschen, wenn er existiert
    if (isset($_COOKIE[session_name()])) {
        // Cookie-Optionen setzen
        $options = [
            'expires' => time() - 42000,
            'path' => '/',
            'httponly' => true,
            'samesite' => sessionCookieSameSite(),
        ];
        if (isHttpsRequest()) {
            $options['secure'] = true;
        }
        setcookie(session_name(), '', $options);
    }
}

/**
 * Prüft, ob die aktuelle Session gültig ist
 *
 * @return bool True wenn die Session gültig ist, andernfalls false
 */
function isValidSession(): bool
{
    // Prüfe, ob die notwendigen Session-Variablen gesetzt sind
    if (!isset($_SESSION["login"]) || $_SESSION["login"] !== "ok") {
        return false;
    }

    return true;
}