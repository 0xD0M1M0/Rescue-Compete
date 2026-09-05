<?php

/**
 * Thin OpenID Connect Authorization Code client (discovery + token + userinfo).
 */
class OidcClient
{
    private string $issuer;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private ?array $discovery = null;

    public function __construct(
        string $issuer,
        string $clientId,
        string $clientSecret,
        string $redirectUri
    ) {
        $this->issuer = rtrim($issuer, '/');
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
    }

    public static function fromEnv(): ?self
    {
        $enabled = getenv('OIDC_ENABLED');
        if ($enabled !== '1' && strtolower((string)$enabled) !== 'true') {
            return null;
        }

        $issuer = trim((string)getenv('OIDC_ISSUER'));
        $clientId = trim((string)getenv('OIDC_CLIENT_ID'));
        $clientSecret = trim((string)getenv('OIDC_CLIENT_SECRET'));
        $redirectUri = trim((string)getenv('OIDC_REDIRECT_URI'));

        if ($issuer === '' || $clientId === '' || $clientSecret === '' || $redirectUri === '') {
            return null;
        }

        return new self($issuer, $clientId, $clientSecret, $redirectUri);
    }

    public static function isEnabled(): bool
    {
        return self::fromEnv() !== null;
    }

    /**
     * Label for the SSO login button (OIDC_LOGIN_LABEL, default: Mit SSO anmelden).
     */
    public static function loginLabel(): string
    {
        $label = trim((string)getenv('OIDC_LOGIN_LABEL'));
        return $label !== '' ? $label : 'Mit SSO anmelden';
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function buildAuthorizeUrl(string $state): string
    {
        $discovery = $this->getDiscovery();
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'openid profile email',
            'state' => $state,
        ]);

        return $discovery['authorization_endpoint'] . '?' . $params;
    }

    /**
     * @return array{sub: string, email: ?string, email_verified: bool, preferred_username: ?string, name: ?string, id_token: ?string}
     */
    public function exchangeCode(string $code): array
    {
        $discovery = $this->getDiscovery();
        $tokenResponse = $this->httpPostForm($discovery['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if (empty($tokenResponse['access_token'])) {
            throw new RuntimeException('OIDC token response ohne access_token');
        }

        $userinfo = $this->httpGetJson(
            $discovery['userinfo_endpoint'],
            $tokenResponse['access_token']
        );

        $sub = isset($userinfo['sub']) ? trim((string)$userinfo['sub']) : '';
        if ($sub === '') {
            throw new RuntimeException('OIDC userinfo ohne sub');
        }

        $email = isset($userinfo['email']) ? trim((string)$userinfo['email']) : null;
        if ($email === '') {
            $email = null;
        }

        $emailVerified = false;
        if (array_key_exists('email_verified', $userinfo)) {
            $raw = $userinfo['email_verified'];
            $emailVerified = $raw === true || $raw === 1 || $raw === '1'
                || (is_string($raw) && strtolower($raw) === 'true');
        }

        $preferred = isset($userinfo['preferred_username'])
            ? trim((string)$userinfo['preferred_username'])
            : null;
        if ($preferred === '') {
            $preferred = null;
        }

        $name = isset($userinfo['name']) ? trim((string)$userinfo['name']) : null;
        if ($name === '') {
            $name = null;
        }

        $idToken = isset($tokenResponse['id_token']) ? (string)$tokenResponse['id_token'] : null;
        if ($idToken === '') {
            $idToken = null;
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'email_verified' => $emailVerified,
            'preferred_username' => $preferred,
            'name' => $name,
            'id_token' => $idToken,
        ];
    }

    /**
     * RP-initiated logout URL (OIDC end_session), or null if IdP has no endpoint.
     */
    public function buildLogoutUrl(?string $idToken, string $postLogoutRedirectUri): ?string
    {
        $discovery = $this->getDiscovery();
        if (empty($discovery['end_session_endpoint'])) {
            return null;
        }

        $params = [
            'client_id' => $this->clientId,
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
        ];
        if ($idToken !== null && $idToken !== '') {
            $params['id_token_hint'] = $idToken;
        }

        return $discovery['end_session_endpoint'] . '?' . http_build_query($params);
    }

    private function getDiscovery(): array
    {
        if ($this->discovery !== null) {
            return $this->discovery;
        }

        $url = $this->issuer . '/.well-known/openid-configuration';
        $this->discovery = $this->httpGetJson($url);

        foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $key) {
            if (empty($this->discovery[$key])) {
                throw new RuntimeException("OIDC discovery fehlt: {$key}");
            }
        }

        return $this->discovery;
    }

    private function httpGetJson(string $url, ?string $bearerToken = null): array
    {
        $headers = "Accept: application/json\r\n";
        if ($bearerToken !== null) {
            $headers .= 'Authorization: Bearer ' . $bearerToken . "\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $headers,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $body = file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('HTTP-GET fehlgeschlagen: ' . $url);
        }

        $this->assertHttpOk($http_response_header ?? []);

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Ungültige JSON-Antwort von ' . $url);
        }

        return $data;
    }

    private function httpPostForm(string $url, array $fields): array
    {
        $body = http_build_query($fields);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n",
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException('HTTP-POST fehlgeschlagen: ' . $url);
        }

        $this->assertHttpOk($http_response_header ?? []);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('Ungültige Token-Antwort');
        }

        if (!empty($data['error'])) {
            $desc = $data['error_description'] ?? $data['error'];
            throw new RuntimeException('OIDC token error: ' . $desc);
        }

        return $data;
    }

    private function assertHttpOk(array $responseHeaders): void
    {
        $statusLine = $responseHeaders[0] ?? '';
        if (!preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
            return;
        }
        $status = (int)$matches[1];
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("HTTP-Status {$status}");
        }
    }
}
