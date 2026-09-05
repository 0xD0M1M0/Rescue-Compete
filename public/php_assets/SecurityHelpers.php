<?php

/**
 * Shared escaping / view / CSRF helpers.
 */

function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

/**
 * JSON encode for embedding in HTML/JS (script blocks and event handlers).
 */
function json_encode_for_js($value): string
{
    return json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
}

/**
 * Whitelist a ?view= tab name.
 */
function sanitize_view_param(?string $view, array $allowed, string $default): string
{
    if ($view !== null && $view !== '' && in_array($view, $allowed, true)) {
        return $view;
    }
    return $default;
}

/**
 * Reject cross-site POSTs (Origin / Referer check). Complements SameSite cookies.
 */
function enforce_post_same_origin(): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        return;
    }

    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return;
    }

    $allowed = [];
    $schemeHttps = 'https://' . $host;
    $schemeHttp = 'http://' . $host;
    $allowed[] = $schemeHttps;
    $allowed[] = $schemeHttp;

    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        foreach ($allowed as $prefix) {
            if ($origin === $prefix) {
                return;
            }
        }
        http_response_code(403);
        exit('Forbidden');
    }

    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer !== '') {
        foreach ($allowed as $prefix) {
            if (str_starts_with($referer, $prefix . '/') || $referer === $prefix) {
                return;
            }
        }
        http_response_code(403);
        exit('Forbidden');
    }

    // No Origin/Referer: allow (privacy browsers / same-site navigational edge cases).
    // CSRF token (csrf_require) covers high-risk forms when present.
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf" value="' . $token . '">';
}

function csrf_validate(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }
    $expected = $_SESSION['_csrf_token'] ?? '';
    return is_string($expected) && $expected !== '' && hash_equals($expected, $token);
}

/**
 * Require a valid CSRF token on POST. Call from mutating controllers.
 */
function csrf_require(): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        return;
    }
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!csrf_validate(is_string($token) ? $token : null)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

/**
 * Escape user-facing API error text (avoid leaking internals / XSS via clients).
 */
function safe_client_error(string $fallback = 'Ein Fehler ist aufgetreten.'): string
{
    return $fallback;
}
