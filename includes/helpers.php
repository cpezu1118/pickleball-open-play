<?php

/**
 * Some shared hosts restrict PHP (via open_basedir) to the account's own
 * file tree, while PHP's default session.save_path points outside it.
 * session_start() then fails silently: the same session id round-trips via
 * cookie, but $_SESSION is never actually persisted between requests (every
 * request sees an empty session — symptom observed in production: CSRF
 * token mismatches on every single login attempt). Point sessions at a
 * writable directory inside this app instead, once per process.
 */
(function () {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $dir = __DIR__ . '/../.sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        $guard = $dir . '/.htaccess';
        if (!file_exists($guard)) {
            @file_put_contents($guard, "Require all denied\nDeny from all\n");
        }
        session_save_path($dir);
    }
})();

/**
 * Every page here is session-bearing (login state, CSRF tokens, live round
 * state) and must never be served from a shared cache. Some hosts don't
 * reliably send PHP's own default session cache-limiter headers (observed:
 * a reverse-proxy cache serving a stale embedded CSRF token to a session
 * that didn't generate it), so send the no-store headers explicitly rather
 * than relying on php.ini defaults.
 */
function no_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
}

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

/**
 * Base URL path of the app ('' when at document root, '/pickleball-open-play' in a subfolder).
 * Override with config base_path if auto-detect is wrong on your host.
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $config = app_config();
    if (isset($config['base_path']) && $config['base_path'] !== null) {
        $base = rtrim((string) $config['base_path'], '/');
        return $base;
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    // If we're in /admin, go up one level.
    if (substr($dir, -6) === '/admin') {
        $dir = dirname($dir);
    }
    $base = ($dir === '/' || $dir === '\\' || $dir === '.') ? '' : rtrim($dir, '/');
    return $base;
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = base_path();
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }
    return ($base === '' ? '' : $base) . '/' . $path;
}

/**
 * Canonical public site origin for share links / QR (no trailing slash).
 * Set config public_base_url on hosting, e.g. https://pickleball.example.com
 * or https://example.com/pickleball — do not use localhost in production.
 */
function public_base_url(): string
{
    $config = app_config();
    $configured = trim((string) ($config['public_base_url'] ?? ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    // Fallback for local testing only when config is empty.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . base_path();
}

function absolute_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $origin = public_base_url();
    if ($path === '') {
        return $origin . '/';
    }
    // If public_base_url already includes the app subfolder, path is relative to that.
    return $origin . '/' . $path;
}

function share_play_url(int $sessionId): string
{
    return absolute_url('play.php?session=' . $sessionId);
}

function qr_image_url(string $data, int $size = 240): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
        'size' => $size . 'x' . $size,
        'data' => $data,
        'margin' => 8,
    ]);
}

function share_url_is_local(?string $url = null): bool
{
    $url = $url ?? public_base_url();
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        return true;
    }
    $host = strtolower($host);
    return $host === 'localhost'
        || $host === '127.0.0.1'
        || str_ends_with($host, '.local')
        || $host === 'host.docker.internal';
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    if ($path !== '' && $path[0] === '/' && strpos($path, '://') === false) {
        // Absolute app path like /login.php → honor base_path
        $path = url(ltrim($path, '/'));
    } elseif ($path !== '' && $path[0] !== '/' && strpos($path, '://') === false) {
        $path = url($path);
    }
    header('Location: ' . $path);
    exit;
}

function flash_set(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $token = $_POST['csrf_token'] ?? '';
    $session = $_SESSION['csrf_token'] ?? '';
    if (!$token || !$session || !hash_equals($session, $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function post_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function post_int(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $default);
}

function post_bool(string $key): int
{
    return !empty($_POST[$key]) ? 1 : 0;
}

function format_slot_label(int $slotIndex, int $offsetMin, int $slotMinutes): string
{
    $end = $offsetMin + $slotMinutes;
    $startH = intdiv($offsetMin, 60);
    $startM = $offsetMin % 60;
    $endH = intdiv($end, 60);
    $endM = $end % 60;
    return sprintf(
        'Slot %d · %d:%02d–%d:%02d',
        $slotIndex + 1,
        $startH,
        $startM,
        $endH,
        $endM
    );
}

function partner_key(int $a, int $b): string
{
    if ($a > $b) {
        [$a, $b] = [$b, $a];
    }
    return $a . '-' . $b;
}
