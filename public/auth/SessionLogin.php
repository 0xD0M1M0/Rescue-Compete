<?php

require_once __DIR__ . '/../CookieMonster.php';

/**
 * Setzt die Login-Session analog zu LoginManager.
 *
 * @param array $user Zeile aus User (mindestens ID, username, acc_typ)
 */
function establishUserSession(array $user): void
{
    // Session fixation prevention: new ID after authentication
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['id'] = $user['ID'];
    $_SESSION['login'] = 'ok';
    $_SESSION['username'] = $user['username'];
    $_SESSION['acc_typ'] = $user['acc_typ'] ?? null;

    createSessionCookie();
}

/**
 * Leitet nach Login zur Zielseite (QR / return / Index).
 */
function redirectAfterLogin(): void
{
    if (!empty($_SESSION['redirect_code'])) {
        $code = $_SESSION['redirect_code'];
        unset($_SESSION['redirect_code']);
        header('Location: /view/FormRedirect.php?code=' . urlencode($code));
        exit;
    }

    if (!empty($_SESSION['return_after_login'])
        && is_string($_SESSION['return_after_login'])
        && $_SESSION['return_after_login'][0] === '/'
        && !str_starts_with($_SESSION['return_after_login'], '//')
        && strpos($_SESSION['return_after_login'], '://') === false) {
        $path = $_SESSION['return_after_login'];
        unset($_SESSION['return_after_login']);
        header('Location: ' . $path);
        exit;
    }

    header('Location: /index.php');
    exit;
}
