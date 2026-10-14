<?php
require_once __DIR__ . '/helpers.php';
$config = app_config();
$appName = $config['app_name'] ?? 'Pickleball Open Play';
$pageTitle = $pageTitle ?? $appName;
$isAdmin = $isAdmin ?? false;
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/app.css')) ?>">
</head>
<body>
<svg class="pin-sprite" aria-hidden="true">
    <symbol id="icon-pin" viewBox="0 0 24 24">
        <rect x="5" y="2" width="14" height="12" rx="6" fill="currentColor" />
        <rect x="10.5" y="13" width="3" height="8" rx="1.5" fill="currentColor" />
    </symbol>
    <symbol id="icon-ball" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9" fill="currentColor" />
        <circle cx="8" cy="9" r="1.1" fill="rgba(58,31,71,0.3)" />
        <circle cx="14" cy="8" r="1.1" fill="rgba(58,31,71,0.3)" />
        <circle cx="16" cy="13" r="1.1" fill="rgba(58,31,71,0.3)" />
        <circle cx="10" cy="15" r="1.1" fill="rgba(58,31,71,0.3)" />
    </symbol>
    <symbol id="icon-trophy" viewBox="0 0 24 24">
        <path d="M7 3h10v3c0 3-2 5-4.5 5.4V14H14a1 1 0 1 1 0 2h-4a1 1 0 1 1 0-2h1.5v-1.6C9 11 7 9 7 6V3z" fill="currentColor" />
        <rect x="9" y="17" width="6" height="2" rx="1" fill="currentColor" />
        <path d="M5 4H3v1a4 4 0 0 0 4 4V7a2 2 0 0 1-2-2V4z" fill="currentColor" />
        <path d="M19 4h2v1a4 4 0 0 1-4 4V7a2 2 0 0 0 2-2V4z" fill="currentColor" />
    </symbol>
    <symbol id="icon-sound-on" viewBox="0 0 24 24">
        <path d="M4 9v6h4l5 4V5L8 9H4z" fill="currentColor" />
        <path d="M16.5 8.5a5 5 0 0 1 0 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" fill="none" />
        <path d="M19 6a8.5 8.5 0 0 1 0 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" fill="none" />
    </symbol>
    <symbol id="icon-sound-off" viewBox="0 0 24 24">
        <path d="M4 9v6h4l5 4V5L8 9H4z" fill="currentColor" />
        <path d="M16 9l5 5M21 9l-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
    </symbol>
</svg>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="<?= e(url($isAdmin ? 'admin/index.php' : 'index.php')) ?>">
            <svg class="pin-icon" style="color: var(--coral)"><use href="#icon-pin"></use></svg>
            <?= e($appName) ?>
        </a>
        <nav class="nav">
            <?php if ($isAdmin): ?>
                <a href="<?= e(url('admin/index.php')) ?>">Dashboard</a>
                <a href="<?= e(url('admin/players.php')) ?>">Players</a>
                <a href="<?= e(url('admin/session_new.php')) ?>">New open play</a>
                <a href="<?= e(url('index.php')) ?>">Public</a>
                <a href="<?= e(url('logout.php')) ?>">Logout</a>
            <?php else: ?>
                <a href="<?= e(url('index.php')) ?>">Schedule</a>
                <a href="<?= e(url('login.php')) ?>">Admin</a>
            <?php endif; ?>
            <button type="button" class="sound-toggle" data-sound-toggle aria-label="Mute sound">
                <svg class="pin-icon"><use href="#icon-sound-on"></use></svg>
            </button>
        </nav>
    </div>
</header>
<main class="wrap">
<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
