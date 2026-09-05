# AGENTS.md — Security layout

Reference for agents working on Rescue-Compete auth, sessions, XSS, and CSRF. Prefer these helpers over inventing new patterns.

## Core files

| Path | Role |
|------|------|
| `public/CookieMonster.php` | `startSecureSession()`, cookie flags (`HttpOnly`, `SameSite=Strict` or `Lax` if OIDC, `Secure` on HTTPS) |
| `public/php_assets/SecurityHelpers.php` | XSS/CSRF helpers (`json_encode_for_js`, `sanitize_view_param`, `csrf_*`, `enforce_post_same_origin`) |
| `public/php_assets/RequireLogin.php` | Login guard; starts secure session + same-origin on POST |
| `public/auth/SessionLogin.php` | `establishUserSession()` (includes `session_regenerate_id`) + safe post-login redirects |
| `public/auth/OidcClient.php` | OIDC client; exposes `email_verified` from userinfo |
| `public/auth/PasswordHash.php` | Password hashing / verify / rehash |
| `public/controller/OidcSsoStart.php` / `OidcSsoCallback.php` | SSO authorize + callback |
| `public/php_assets/CustomAlertBox.php` | Modal forms auto-inject `csrf_field()` |

## Sessions (always)

1. Never call bare `session_start()`. Use:
   ```php
   require_once __DIR__ . '/../CookieMonster.php';
   startSecureSession();
   ```
2. After successful password or SSO login, only `establishUserSession($user)` (regenerates session id).
3. Cookie policy: `HttpOnly` + `SameSite=Strict` when OIDC is off; `Lax` when `OIDC_ENABLED` is on (IdP return). `Secure` when `isHttpsRequest()`.

## XSS (HTML / JS)

| Context | Do | Don't |
|---------|----|-------|
| HTML body text | `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')` | Echo raw `$_GET` / DB strings |
| Values inside `<script>` or `onclick` | `json_encode_for_js($x)` | `addslashes`, bare `echo`, `htmlspecialchars` inside JS strings |
| `?view=` tabs | `sanitize_view_param($_GET['view'] ?? null, $allowed, $default)` | `$currentView = $_GET['view']` |
| DOM `innerHTML` | `escapeHtml(...)` / `textContent` / DOM APIs | Concatenate API/DB strings into HTML |

Examples:

```php
$currentView = sanitize_view_param($_GET['view'] ?? null, ['overview', 'create'], 'overview');
// …
const currentView = <?php echo json_encode_for_js($currentView); ?>;
onclick="confirmDeleteUser(<?php echo (int)$id; ?>, <?php echo json_encode_for_js($name); ?>)"
```

Prefer `data-*` + event listeners over putting names in `onclick` when touching UI heavily.

## CSRF / same-origin

- **All mutating controllers (POST):** call `enforce_post_same_origin()` at the start of `handleRequest()`.
- **High-risk forms** (user/admin CRUD, competition reset): also `csrf_require()` and put `<?php echo csrf_field(); ?>` in every POST form.
- AJAX: send `_csrf` from `meta[name="csrf-token"]` or `input[name="_csrf"]` (see `UserInputScript.js` `appendCsrf`).
- Header alternative: `X-CSRF-Token`.

## SSO / accounts

- Link or create by email **only** when IdP `email_verified` is true (`OidcSsoCallback`).
- Only **Admin** may set `sso_email` (enforced in `UserController`; UI hidden for non-admins).
- Password login blocked when `oidc_sub` is set or `passwordHash` is empty.
- Never log full user rows (no `passwordHash` / tokens in `error_log`).
- Open redirects: return paths must be same-origin absolute paths (`/`…, not `//` or `://`) — see `SessionLogin` / `Login.php`.

## SQL / tokens

- Use prepared statements (`prepare` / `bind*`). No string-concat SQL with request data.
- New QR / form tokens: `bin2hex(random_bytes(16))` (needs `CollectionFormToken.token` varchar(64) — included in `2026-09-05-sso.sql`).
- Client-facing API errors: generic message; log `$e->getMessage()` server-side only.

## Migrations (security-related)

| File | Purpose |
|------|---------|
| `sql-scheme/migrations/2026-09-05-sso.sql` | SSO columns, unique `sso_email`/`oidc_sub`, widen `passwordHash`, widen collection tokens |

Apply on Docker e.g.:

```bash
docker compose exec -T db mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < sql-scheme/migrations/2026-09-05-sso.sql
```

(Avoid `source .env` if values contain spaces; pass credentials explicitly or via compose env.)

## Checklist for new features

- [ ] Session via `startSecureSession()`
- [ ] POST: `enforce_post_same_origin()`; high-risk: `csrf_require()` + `csrf_field()`
- [ ] No raw user/DB data in HTML/JS/`innerHTML`
- [ ] Tab `view` params whitelisted
- [ ] Prepared SQL; no exception text to clients
- [ ] Role checks for privileged actions (Admin vs Wettkampfleitung)
