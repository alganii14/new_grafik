<?php

const DEFAULT_DASHBOARD_USERNAME = 'admin';
const DEFAULT_DASHBOARD_PASSWORD_HASH = '$argon2id$v=19$m=65536,t=4,p=1$LjZRRkRRODNHLjRjU2dwZw$eYSnimsvEmMnOGNtbBhfuRuK8UCqsI9ZTGhebp2xdUo';
const SESSION_IDLE_TIMEOUT = 1800;
const SESSION_ABSOLUTE_TIMEOUT = 28800;
const SESSION_REGENERATE_INTERVAL = 600;
const LOGIN_WINDOW_SECONDS = 900;
const LOGIN_LOCK_SECONDS = 900;
const LOGIN_MAX_ATTEMPTS_PER_ACCOUNT_IP = 5;
const LOGIN_MAX_ATTEMPTS_PER_IP = 15;

function is_https_request()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    return getenv('VERCEL') === '1'
        && isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

function send_security_headers($contentSecurityPolicy = null)
{
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000');
    }

    if ($contentSecurityPolicy !== null) {
        header('Content-Security-Policy: ' . $contentSecurityPolicy);
    }
}

function dashboard_username()
{
    $configuredUsername = getenv('DASHBOARD_USERNAME');

    return is_string($configuredUsername) && trim($configuredUsername) !== ''
        ? trim($configuredUsername)
        : DEFAULT_DASHBOARD_USERNAME;
}

function dashboard_password_hash()
{
    $configuredHash = getenv('DASHBOARD_PASSWORD_HASH');

    return is_string($configuredHash) && trim($configuredHash) !== ''
        ? trim($configuredHash)
        : DEFAULT_DASHBOARD_PASSWORD_HASH;
}

function client_ip_address()
{
    if (getenv('VERCEL') === '1' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim($forwarded[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
}

function uses_stateless_session()
{
    return getenv('VERCEL') === '1' || getenv('DASHBOARD_STATELESS_SESSION') === '1';
}

function dashboard_session_secret()
{
    $secret = getenv('DASHBOARD_SESSION_SECRET');
    if (!is_string($secret) || strlen($secret) < 32) {
        throw new RuntimeException('DASHBOARD_SESSION_SECRET wajib diisi minimal 32 karakter.');
    }

    return $secret;
}

function base64url_encode($value)
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64url_decode($value)
{
    $padding = strlen($value) % 4;
    if ($padding !== 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($value, '-_', '+/'), true);
}

function load_stateless_session()
{
    $cookieName = 'grafik_dashboard_session';
    $cookie = isset($_COOKIE[$cookieName]) ? (string) $_COOKIE[$cookieName] : '';
    $parts = explode('.', $cookie);
    if (count($parts) !== 2) {
        return [];
    }

    $payload = base64url_decode($parts[0]);
    $signature = base64url_decode($parts[1]);
    if ($payload === false || $signature === false) {
        return [];
    }

    $expected = hash_hmac('sha256', $payload, dashboard_session_secret(), true);
    if (!hash_equals($expected, $signature)) {
        return [];
    }

    $decoded = json_decode($payload, true);
    if (!is_array($decoded)
        || !isset($decoded['issued_at'], $decoded['data'])
        || !is_array($decoded['data'])
        || (int) $decoded['issued_at'] < time() - SESSION_ABSOLUTE_TIMEOUT) {
        return [];
    }

    return $decoded['data'];
}

function persist_stateless_session()
{
    if (!uses_stateless_session()) {
        return;
    }

    $payload = json_encode([
        'issued_at' => time(),
        'data' => isset($_SESSION) && is_array($_SESSION) ? $_SESSION : [],
    ], JSON_UNESCAPED_SLASHES);
    $signature = hash_hmac('sha256', $payload, dashboard_session_secret(), true);
    $value = base64url_encode($payload) . '.' . base64url_encode($signature);

    setcookie('grafik_dashboard_session', $value, [
        'expires' => time() + SESSION_ABSOLUTE_TIMEOUT,
        'path' => '/',
        'domain' => '',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function session_fingerprint()
{
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

    return hash('sha256', $userAgent . '|' . client_ip_address());
}

function start_secure_session()
{
    static $statelessStarted = false;

    if (uses_stateless_session()) {
        if (!$statelessStarted) {
            $_SESSION = load_stateless_session();
            $statelessStarted = true;
        }
        return;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.sid_length', '64');
    ini_set('session.sid_bits_per_character', '6');
    ini_set('session.gc_maxlifetime', (string) SESSION_ABSOLUTE_TIMEOUT);
    session_name('grafik_dashboard_session');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}

function is_authenticated()
{
    start_secure_session();

    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        return false;
    }

    $now = time();
    $createdAt = isset($_SESSION['created_at']) ? (int) $_SESSION['created_at'] : 0;
    $lastActivity = isset($_SESSION['last_activity']) ? (int) $_SESSION['last_activity'] : 0;
    $storedFingerprint = isset($_SESSION['fingerprint']) ? (string) $_SESSION['fingerprint'] : '';
    $fingerprintMatches = $storedFingerprint !== ''
        && hash_equals($storedFingerprint, session_fingerprint());
    $idleExpired = $lastActivity === 0 || ($now - $lastActivity) > SESSION_IDLE_TIMEOUT;
    $absoluteExpired = $createdAt === 0 || ($now - $createdAt) > SESSION_ABSOLUTE_TIMEOUT;

    if (!$fingerprintMatches || $idleExpired || $absoluteExpired) {
        end_authenticated_session('session_rejected');
        return false;
    }

    $lastRegenerated = isset($_SESSION['last_regenerated'])
        ? (int) $_SESSION['last_regenerated']
        : 0;

    if (($now - $lastRegenerated) >= SESSION_REGENERATE_INTERVAL) {
        if (!uses_stateless_session()) {
            session_regenerate_id(true);
        }
        $_SESSION['last_regenerated'] = $now;
    }

    $_SESSION['last_activity'] = $now;
    persist_stateless_session();

    return true;
}

function attempt_login($username, $password)
{
    start_secure_session();

    $usernameMatches = hash_equals(dashboard_username(), trim((string) $username));
    $passwordMatches = password_verify((string) $password, dashboard_password_hash());

    if (!$usernameMatches || !$passwordMatches) {
        return false;
    }

    if (!uses_stateless_session()) {
        session_regenerate_id(true);
    }
    $_SESSION = [];

    $now = time();
    $_SESSION['authenticated'] = true;
    $_SESSION['username'] = dashboard_username();
    $_SESSION['created_at'] = $now;
    $_SESSION['last_activity'] = $now;
    $_SESSION['last_regenerated'] = $now;
    $_SESSION['fingerprint'] = session_fingerprint();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    clear_login_attempts($username);
    audit_security_event('login_success', dashboard_username());
    persist_stateless_session();

    return true;
}

function csrf_token()
{
    start_secure_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        persist_stateless_session();
    }

    return $_SESSION['csrf_token'];
}

function csrf_is_valid($token)
{
    start_secure_session();

    return is_string($token)
        && strlen($token) === 64
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_authentication($jsonResponse = false)
{
    if (is_authenticated()) {
        send_security_headers();
        return;
    }

    send_security_headers("default-src 'none'; frame-ancestors 'none'; base-uri 'none'");

    if ($jsonResponse) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Sesi login tidak valid. Silakan login kembali.',
            'redirect' => 'login.php',
        ]);
        exit;
    }

    header('Location: login.php');
    exit;
}

function require_api_csrf()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    if (csrf_is_valid($token)) {
        return;
    }

    audit_security_event('csrf_rejected', isset($_SESSION['username']) ? $_SESSION['username'] : '');
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.',
    ]);
    exit;
}

function end_authenticated_session($reason = 'logout')
{
    start_secure_session();

    $username = isset($_SESSION['username']) ? (string) $_SESSION['username'] : '';
    if ($username !== '') {
        audit_security_event($reason, $username);
    }

    $_SESSION = [];

    if (uses_stateless_session()) {
        setcookie('grafik_dashboard_session', '', [
            'expires' => time() - 42000,
            'path' => '/',
            'domain' => '',
            'secure' => is_https_request(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        return;
    }

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => isset($params['samesite']) ? $params['samesite'] : 'Strict',
        ]);
    }

    session_destroy();
}

function security_storage_directory()
{
    $directory = getenv('VERCEL') === '1'
        ? rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'grafik-dashboard-security'
        : __DIR__ . '/storage/security';

    if (!is_dir($directory)) {
        mkdir($directory, 0700, true);
    }

    return $directory;
}

function login_rate_keys($username)
{
    $ip = client_ip_address();
    $submittedUsername = trim((string) $username);
    $knownAccount = hash_equals(dashboard_username(), $submittedUsername);
    $accountBucket = $knownAccount ? dashboard_username() : 'unknown-account';

    return [
        ['key' => 'account-ip|' . $accountBucket . '|' . $ip, 'limit' => LOGIN_MAX_ATTEMPTS_PER_ACCOUNT_IP],
        ['key' => 'ip|' . $ip, 'limit' => LOGIN_MAX_ATTEMPTS_PER_IP],
    ];
}

function login_rate_file($key)
{
    return security_storage_directory() . '/rate_' . hash('sha256', $key) . '.json';
}

function mutate_login_rate($key, $limit, $recordFailure)
{
    $file = login_rate_file($key);
    $handle = fopen($file, 'c+');

    if ($handle === false) {
        return 0;
    }

    $retryAfter = 0;

    if (flock($handle, LOCK_EX)) {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $state = json_decode($raw, true);
        if (!is_array($state)) {
            $state = [];
        }

        $now = time();
        $attempts = isset($state['attempts']) && is_array($state['attempts'])
            ? array_values(array_filter($state['attempts'], function ($timestamp) use ($now) {
                return is_numeric($timestamp) && ((int) $timestamp) > ($now - LOGIN_WINDOW_SECONDS);
            }))
            : [];
        $lockedUntil = isset($state['locked_until']) ? (int) $state['locked_until'] : 0;

        if ($lockedUntil > $now) {
            $retryAfter = $lockedUntil - $now;
        } else {
            $lockedUntil = 0;

            if ($recordFailure) {
                $attempts[] = $now;
                if (count($attempts) >= $limit) {
                    $lockedUntil = $now + LOGIN_LOCK_SECONDS;
                    $attempts = [];
                    $retryAfter = LOGIN_LOCK_SECONDS;
                }
            }
        }

        $newState = json_encode([
            'attempts' => $attempts,
            'locked_until' => $lockedUntil,
            'updated_at' => $now,
        ]);

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, $newState);
        fflush($handle);
        flock($handle, LOCK_UN);
    }

    fclose($handle);
    @chmod($file, 0600);

    return $retryAfter;
}

function login_retry_after($username)
{
    $retryAfter = 0;

    foreach (login_rate_keys($username) as $rate) {
        $retryAfter = max(
            $retryAfter,
            mutate_login_rate($rate['key'], $rate['limit'], false)
        );
    }

    return $retryAfter;
}

function record_failed_login($username)
{
    $retryAfter = 0;

    foreach (login_rate_keys($username) as $rate) {
        $retryAfter = max(
            $retryAfter,
            mutate_login_rate($rate['key'], $rate['limit'], true)
        );
    }

    audit_security_event($retryAfter > 0 ? 'login_locked' : 'login_failed', trim((string) $username));
    usleep(random_int(350000, 700000));

    return $retryAfter;
}

function clear_login_attempts($username)
{
    foreach (login_rate_keys($username) as $rate) {
        $file = login_rate_file($rate['key']);
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function audit_security_event($event, $username = '')
{
    $directory = security_storage_directory();
    $logFile = $directory . '/events.log';

    if (is_file($logFile) && filesize($logFile) > 1048576) {
        @rename($logFile, $directory . '/events.log.1');
    }

    $safeEvent = preg_replace('/[^a-z0-9_-]/i', '', (string) $event);
    $safeUsername = substr(str_replace(["\r", "\n", "\t"], '', (string) $username), 0, 128);
    $safeIp = str_replace(["\r", "\n", "\t"], '', client_ip_address());
    $line = gmdate('c') . "\t" . $safeEvent . "\t" . $safeIp . "\t" . $safeUsername . PHP_EOL;

    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    @chmod($logFile, 0600);
}
