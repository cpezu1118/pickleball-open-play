<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_admin();

$players = db()->query(
    'SELECT id, name FROM players WHERE active = 1 ORDER BY name ASC'
)->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title = post_string('title');
    $playDate = post_string('play_date');
    $courts = post_int('courts');
    $hours = (float) ($_POST['hours'] ?? 0);
    $trackResults = post_bool('track_results');
    $timerEnabled = post_bool('timer_enabled');
    $selected = array_map('intval', $_POST['players'] ?? []);
    $selected = array_values(array_unique(array_filter($selected, static fn($id) => $id > 0)));

    if ($title === '') {
        $title = 'Open play ' . $playDate;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $playDate)) {
        flash_set('error', 'Valid play date is required.');
        redirect('admin/session_new.php');
    }
    if ($courts < 1 || $courts > 20) {
        flash_set('error', 'Courts must be between 1 and 20.');
        redirect('admin/session_new.php');
    }
    if ($hours <= 0 || $hours > 12) {
        flash_set('error', 'Hours must be between 0.25 and 12.');
        redirect('admin/session_new.php');
    }
    // Snap hours to 15-minute increments.
    $hours = round($hours * 4) / 4;
    if (count($selected) < 4) {
        flash_set('error', 'Select at least 4 players.');
        redirect('admin/session_new.php');
    }

    $mysqli = db();
    $stmt = $mysqli->prepare(
        'INSERT INTO sessions (title, play_date, courts, hours, track_results, timer_enabled, status)
         VALUES (?, ?, ?, ?, ?, ?, \'draft\')'
    );
    $stmt->bind_param('ssidii', $title, $playDate, $courts, $hours, $trackResults, $timerEnabled);
    $stmt->execute();
    $sessionId = (int) $mysqli->insert_id;
    $stmt->close();

    $link = $mysqli->prepare('INSERT INTO session_players (session_id, player_id) VALUES (?, ?)');
    foreach ($selected as $pid) {
        $link->bind_param('ii', $sessionId, $pid);
        $link->execute();
    }
    $link->close();

    flash_set('success', 'Open play created. Generate the schedule next.');
    redirect('admin/session.php?id=' . $sessionId);
}

$pageTitle = 'New open play';
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h1>New open play</h1>
    <p>Set courts, hours, players, and optional features for the day.</p>

    <?php if (!$players): ?>
        <div class="flash flash-warn">
            Add players first on the <a href="<?= e(url('admin/players.php')) ?>">Players</a> screen.
        </div>
    <?php endif; ?>

    <form method="post" class="stack">
        <?= csrf_field() ?>
        <div class="grid-2">
            <label>
                Title
                <input type="text" name="title" maxlength="150" placeholder="Saturday open play">
            </label>
            <label>
                Play date
                <input type="date" name="play_date" required value="<?= e(date('Y-m-d')) ?>">
            </label>
            <label>
                Courts
                <input type="number" name="courts" min="1" max="20" value="2" required>
            </label>
            <label>
                Hours
                <input type="number" name="hours" min="0.25" max="12" step="0.25" value="2" required>
            </label>
        </div>

        <div class="stack">
            <label class="check-row">
                <input type="checkbox" name="track_results" value="1">
                Track win/loss and points for this open play
            </label>
            <label class="check-row">
                <input type="checkbox" name="timer_enabled" value="1">
                Enable 15-minute slot timer + sound
            </label>
            <p class="muted" style="margin:0;">Results stay off by default for schedule-only days.</p>
        </div>

        <div>
            <h2>Players</h2>
            <div class="player-checks">
                <?php foreach ($players as $p): ?>
                    <label>
                        <input type="checkbox" name="players[]" value="<?= (int) $p['id'] ?>" checked>
                        <?= e($p['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="btn-row">
            <button type="submit" <?= $players ? '' : 'disabled' ?>>Create open play</button>
            <a class="btn secondary" href="<?= e(url('admin/index.php')) ?>">Cancel</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
