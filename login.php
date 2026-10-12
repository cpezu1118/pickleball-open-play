<?php
require_once __DIR__ . '/includes/auth.php';

auth_bootstrap();

if (auth_check()) {
    redirect('admin/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $password = post_string('password');
    if (auth_attempt($password)) {
        flash_set('success', 'Welcome back.');
        redirect('admin/index.php');
    }
    $error = 'Incorrect password.';
}

$pageTitle = 'Admin login';
$isAdmin = false;
require __DIR__ . '/includes/header.php';
?>
<section class="panel" style="max-width:420px;margin:2rem auto;">
    <h1>Admin login</h1>
    <p>Shared password for managing open play.</p>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <label>
            Password
            <input type="password" name="password" required autofocus>
        </label>
        <div class="btn-row">
            <button type="submit">Log in</button>
        </div>
    </form>
    <p class="muted" style="margin-top:1rem;font-size:0.85rem;">
        Default password is <code>changeme</code> until you change it in Admin → Dashboard.
    </p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
