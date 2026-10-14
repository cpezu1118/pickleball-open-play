<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$viewId = isset($_GET['session']) ? (int) $_GET['session'] : 0;
if ($viewId > 0) {
    redirect('play.php?session=' . $viewId);
}

$sessions = db()->query(
    "SELECT id, title, play_date, courts, hours, status, track_results, timer_enabled
     FROM sessions
     ORDER BY play_date DESC, id DESC
     LIMIT 30"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Open play schedule';
$isAdmin = false;
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <h1>Open play</h1>
    <p>Pick a session to open the round-by-round play view.</p>
</section>

<?php if (!$sessions): ?>
    <section class="panel">
        <p class="muted">No open plays yet. An admin can create one after logging in.</p>
    </section>
<?php else: ?>
    <ul class="list-plain panel">
        <?php foreach ($sessions as $s): ?>
            <li>
                <div>
                    <a href="<?= e(url('play.php?session=' . (int) $s['id'])) ?>">
                        <strong><?= e($s['title']) ?></strong>
                    </a>
                    <div class="muted">
                        <?= e($s['play_date']) ?>
                        · <?= (int) $s['courts'] ?> courts
                    </div>
                </div>
                <div>
                    <?php if ($s['status'] === 'active'): ?>
                        <span class="badge badge-on"><svg class="pin-icon icon-sm"><use href="#icon-pin"></use></svg> Live now</span>
                    <?php else: ?>
                        <span class="badge"><?= e(ucfirst($s['status'])) ?></span>
                    <?php endif; ?>
                    <?php if ((int) $s['track_results']): ?><span class="badge">Results</span><?php endif; ?>
                    <?php if ((int) $s['timer_enabled']): ?><span class="badge">Timer</span><?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
