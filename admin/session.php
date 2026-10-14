<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_repo.php';

require_admin();

$config = app_config();
$slotMinutes = (int) ($config['slot_minutes'] ?? 15);
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$session = $id ? fetch_session($id) : null;
if (!$session) {
    flash_set('error', 'Session not found.');
    redirect('admin/index.php');
}

$pendingWarning = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_string('action');

    if ($action === 'update_flags') {
        $track = post_bool('track_results');
        $timer = post_bool('timer_enabled');
        $stmt = db()->prepare('UPDATE sessions SET track_results = ?, timer_enabled = ? WHERE id = ?');
        $stmt->bind_param('iii', $track, $timer, $id);
        $stmt->execute();
        $stmt->close();
        flash_set('success', 'Session options updated.');
        redirect('admin/session.php?id=' . $id);
    }

    if ($action === 'generate' || $action === 'confirm_generate') {
        $playerIds = session_player_ids($id);
        try {
            $schedule = Scheduler::generate(
                $playerIds,
                (int) $session['courts'],
                (float) $session['hours'],
                $slotMinutes
            );
        } catch (InvalidArgumentException $e) {
            flash_set('error', $e->getMessage());
            redirect('admin/session.php?id=' . $id);
        }

        if ($schedule['has_repeats'] && $action !== 'confirm_generate') {
            // Stash schedule in session for confirm step.
            $_SESSION['pending_schedule_' . $id] = $schedule;
            $pendingWarning = $schedule;
        } else {
            if ($action === 'confirm_generate' && !empty($_SESSION['pending_schedule_' . $id])) {
                $schedule = $_SESSION['pending_schedule_' . $id];
                unset($_SESSION['pending_schedule_' . $id]);
            }
            persist_schedule($id, $schedule, $slotMinutes, $action === 'confirm_generate');
            flash_set(
                $schedule['has_repeats'] ? 'warn' : 'success',
                $schedule['has_repeats']
                    ? 'Schedule saved with some repeated partnerships.'
                    : 'Schedule generated.'
            );
            redirect('admin/session.php?id=' . $id);
        }
    }

    if ($action === 'cancel_pending') {
        unset($_SESSION['pending_schedule_' . $id]);
        flash_set('success', 'Generation cancelled.');
        redirect('admin/session.php?id=' . $id);
    }

    if ($action === 'reset') {
        clear_session_schedule($id);
        $stmt = db()->prepare("UPDATE sessions SET status = 'draft', partner_warning_ack = 0 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        unset($_SESSION['pending_schedule_' . $id]);
        flash_set('success', 'Schedule cleared. Session is draft again.');
        redirect('admin/session.php?id=' . $id);
    }

    if ($action === 'mark_done') {
        $stmt = db()->prepare("UPDATE sessions SET status = 'done' WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_set('success', 'Session marked done.');
        redirect('admin/session.php?id=' . $id);
    }

    if ($action === 'set_active_slot') {
        $slotId = post_int('slot_id');
        $mysqli = db();
        $all = $mysqli->prepare("UPDATE slots SET status = 'pending' WHERE session_id = ?");
        $all->bind_param('i', $id);
        $all->execute();
        $all->close();
        $one = $mysqli->prepare("UPDATE slots SET status = 'active' WHERE id = ? AND session_id = ?");
        $one->bind_param('ii', $slotId, $id);
        $one->execute();
        $one->close();
        flash_set('success', 'Current slot updated.');
        redirect('admin/session.php?id=' . $id);
    }
}

// Restore pending warning display if redirected mid-flow (same request shows form).
if ($pendingWarning === null && !empty($_SESSION['pending_schedule_' . $id])) {
    $pendingWarning = $_SESSION['pending_schedule_' . $id];
}

$session = fetch_session($id);
$board = load_session_board($id);
$playerIds = session_player_ids($id);
$names = player_map();

$pageTitle = $session['title'];
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h1><?= e($session['title']) ?></h1>
    <p>
        <?= e($session['play_date']) ?>
        · <?= (int) $session['courts'] ?> courts
        · <?= e(rtrim(rtrim(number_format((float) $session['hours'], 2, '.', ''), '0'), '.')) ?> hours
        · <?= count($playerIds) ?> players
        · status <strong><?= e($session['status']) ?></strong>
    </p>
    <div class="btn-row">
        <?php if (!empty($board['slots'])): ?>
            <a class="btn" href="<?= e(url('play.php?session=' . $id)) ?>">Open play screen</a>
        <?php else: ?>
            <button type="button" class="btn" disabled title="Generate a schedule first">Open play screen</button>
        <?php endif; ?>
        <?php if ((int) $session['track_results'] && !empty($board['slots'])): ?>
            <a class="btn secondary" href="<?= e(url('admin/results.php?id=' . $id)) ?>">Results admin</a>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($board['slots'])): ?>
    <?php
    $sessionId = $id;
    $shareShowPlayButton = true;
    require __DIR__ . '/../includes/share_card.php';
    ?>
<?php endif; ?>

<section class="panel">
    <h2>Session options</h2>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_flags">
        <label class="check-row">
            <input type="checkbox" name="track_results" value="1" <?= (int) $session['track_results'] ? 'checked' : '' ?>>
            Track win/loss and points
        </label>
        <label class="check-row">
            <input type="checkbox" name="timer_enabled" value="1" <?= (int) $session['timer_enabled'] ? 'checked' : '' ?>>
            Slot timer + sound
        </label>
        <div class="btn-row">
            <button type="submit" class="secondary">Save options</button>
        </div>
    </form>
</section>

<?php if ($pendingWarning): ?>
    <section class="warning-box">
        <h2 style="margin-top:0;color:inherit;">Partner repeats unavoidable</h2>
        <p>
            With this group size and session length, some players must partner again.
            Review the pairs below, then confirm to set the schedule and continue.
        </p>
        <ul>
            <?php foreach ($pendingWarning['repeated_pairs'] as $pair): ?>
                <li>
                    <?= e($names[$pair['a']] ?? ('#' . $pair['a'])) ?>
                    &amp;
                    <?= e($names[$pair['b']] ?? ('#' . $pair['b'])) ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" class="btn-row">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="confirm_generate">
            <button type="submit">Confirm and set schedule</button>
        </form>
        <form method="post" class="btn-row">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel_pending">
            <button type="submit" class="secondary">Cancel</button>
        </form>
    </section>
<?php endif; ?>

<section class="panel">
    <h2>Schedule</h2>
    <div class="btn-row">
        <?php if (!$board['slots']): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="generate">
                <button type="submit">Generate schedule</button>
            </form>
        <?php else: ?>
            <form method="post" onsubmit="return confirm('Clear the whole day and regenerate later?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset">
                <button type="submit" class="btn-danger">Reset day</button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="generate">
                <button type="submit" class="secondary" onclick="return confirm('This replaces the current schedule. Continue?');">
                    Regenerate
                </button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_done">
                <button type="submit" class="secondary">Mark done</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php if ((int) $session['timer_enabled'] && $board['slots']): ?>
<section class="panel">
    <h2>Slot timer</h2>
    <div class="timer-box" data-slot-timer data-duration="<?= (int) ($slotMinutes * 60) ?>">
        <div class="timer-display" data-timer-display>15:00</div>
        <label class="check-row">
            <input type="checkbox" data-timer-sound checked>
            Sound when time is up
        </label>
        <div class="btn-row">
            <button type="button" data-unlock-audio class="secondary">Enable sound</button>
            <button type="button" data-timer-start>Start</button>
            <button type="button" data-timer-pause class="secondary">Pause</button>
            <button type="button" data-timer-reset class="secondary">Reset</button>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ((int) $session['track_results'] && $board['slots']): ?>
    <?php $standings = session_standings($id, $board); ?>
    <section class="panel">
        <h2>Standings</h2>
        <?php require __DIR__ . '/../includes/standings_table.php'; ?>
    </section>
<?php endif; ?>

<?php foreach ($board['slots'] as $slot): ?>
    <article class="slot-card <?= $slot['status'] === 'active' ? 'current' : '' ?>">
        <strong><?= e(format_slot_label($slot['slot_index'], $slot['starts_offset_min'], $slotMinutes)) ?></strong>
        <span class="badge"><?= e($slot['status']) ?></span>
        <form method="post" class="inline-form" style="margin-left:0.5rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_active_slot">
            <input type="hidden" name="slot_id" value="<?= (int) $slot['id'] ?>">
            <button type="submit" class="secondary" style="padding:0.25rem 0.7rem;font-size:0.8rem;">Set current</button>
        </form>
        <div class="court-grid">
            <?php foreach ($slot['courts'] as $court): ?>
                <div class="court">
                    <h4>Court <?= (int) $court['court_number'] ?></h4>
                    <div class="team">
                        <span><?= e($court['team1'][0]) ?> &amp; <?= e($court['team1'][1]) ?></span>
                    </div>
                    <div class="vs">vs</div>
                    <div class="team">
                        <span><?= e($court['team2'][0]) ?> &amp; <?= e($court['team2'][1]) ?></span>
                    </div>
                    <?php if ((int) $session['track_results'] && $court['team1_score'] !== null): ?>
                        <p class="muted" style="margin:0.5rem 0 0;">
                            <?= (int) $court['team1_score'] ?>–<?= (int) $court['team2_score'] ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($slot['sitouts']): ?>
            <div class="sitouts">
                Sitting out: <?= e(implode(', ', array_column($slot['sitouts'], 'name'))) ?>
            </div>
        <?php endif; ?>
    </article>
<?php endforeach; ?>

<section class="panel">
    <h2>Players in this open play</h2>
    <p><?= e(implode(', ', array_map(static fn($pid) => $names[$pid] ?? ('#' . $pid), $playerIds))) ?></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
