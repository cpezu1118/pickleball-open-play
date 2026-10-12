<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function auth_bootstrap(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $hash = setting_get('admin_password_hash');
    if ($hash === null || $hash === '') {
        $config = app_config();
        $plain = $config['default_admin_password'] ?? 'changeme';
        setting_set('admin_password_hash', password_hash($plain, PASSWORD_DEFAULT));
    }
}

function auth_attempt(string $password): bool
{
    auth_bootstrap();
    $hash = setting_get('admin_password_hash', '');
    if (!$hash || !password_verify($password, $hash)) {
        return false;
    }
    $_SESSION['admin_logged_in'] = true;
    session_regenerate_id(true);
    return true;
}

function auth_check(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return !empty($_SESSION['admin_logged_in']);
}

function require_admin(): void
{
    if (!auth_check()) {
        flash_set('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

function auth_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
