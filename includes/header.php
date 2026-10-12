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
<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="<?= e(url($isAdmin ? 'admin/index.php' : 'index.php')) ?>"><?= e($appName) ?></a>
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
        </nav>
    </div>
</header>
<main class="wrap">
<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
