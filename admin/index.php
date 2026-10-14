<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_admin();
auth_bootstrap();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    csrf_verify();
    $current = post_string('current_password');
    $new = post_string('new_password');
    $confirm = post_string('confirm_password');
    $hash = setting_get('admin_password_hash', '');
    if (!$hash || !password_verify($current, $hash)) {
        flash_set('error', 'Current password is incorrect.');
    } elseif (strlen($new) < 6) {
        flash_set('error', 'New password must be at least 6 characters.');
    } elseif ($new !== $confirm) {
        flash_set('error', 'New passwords do not match.');
    } else {
        setting_set('admin_password_hash', password_hash($new, PASSWORD_DEFAULT));
        flash_set('success', 'Password updated.');
    }
    redirect('admin/index.php');
}

$sessions = db()->query(
    "SELECT id, title, play_date, courts, hours, status, track_results, timer_enabled
     FROM sessions
     ORDER BY play_date DESC, id DESC
     LIMIT 50"
)->fetch_all(MYSQLI_ASSOC);

$playerCount = (int) db()->query('SELECT COUNT(*) AS c FROM players WHERE active = 1')->fetch_assoc()['c'];

$pageTitle = 'Admin dashboard';
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h1>Dashboard</h1>
    <p><?= $playerCount ?> active player<?= $playerCount === 1 ? '' : 's' ?> on file.</p>
    <div class="btn-row">
        <a class="btn" href="<?= e(url('admin/session_new.php')) ?>">New open play</a>
        <a class="btn secondary" href="<?= e(url('admin/players.php')) ?>">Manage players</a>
    </div>
</section>

<section class="panel">
    <h2>Open plays</h2>
    <?php if (!$sessions): ?>
        <p class="muted">None yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Courts</th>
                        <th>Status</th>
                        <th>Flags</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sessions as $s): ?>
                    <tr>
                        <td><?= e($s['title']) ?></td>
                        <td><?= e($s['play_date']) ?></td>
                        <td><?= (int) $s['courts'] ?></td>
                        <td><?= e($s['status']) ?></td>
                        <td>
                            <?php if ($s['status'] === 'active'): ?><span class="badge badge-on"><svg class="pin-icon" style="width:12px;height:12px"><use href="#icon-pin"></use></svg> Live</span><?php endif; ?>
                            <?php if ((int) $s['track_results']): ?><span class="badge">Results</span><?php endif; ?>
                            <?php if ((int) $s['timer_enabled']): ?><span class="badge">Timer</span><?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= e(url('admin/session.php?id=' . (int) $s['id'])) ?>">Setup</a>
                            ·
                            <a href="<?= e(url('play.php?session=' . (int) $s['id'])) ?>">Play</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Change admin password</h2>
    <form method="post" class="stack" style="max-width:420px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <label>Current password<input type="password" name="current_password" required></label>
        <label>New password<input type="password" name="new_password" required minlength="6"></label>
        <label>Confirm new password<input type="password" name="confirm_password" required minlength="6"></label>
        <div class="btn-row">
            <button type="submit">Update password</button>
        </div>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
