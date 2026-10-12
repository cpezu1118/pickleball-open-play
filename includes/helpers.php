<?php

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
