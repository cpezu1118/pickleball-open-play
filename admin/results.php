<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_repo.php';
require_once __DIR__ . '/../includes/score_save.php';

require_admin();

$config = app_config();
$slotMinutes = (int) ($config['slot_minutes'] ?? 15);
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$session = $id ? fetch_session($id) : null;

if (!$session) {
    flash_set('error', 'Session not found.');
    redirect('admin/index.php');
}
if (!(int) $session['track_results']) {
    flash_set('error', 'Results tracking is off for this open play. Enable it on the session page.');
    redirect('admin/session.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $err = save_game_score($id, post_int('game_id'), post_int('team1_score'), post_int('team2_score'));
    flash_set($err ? 'error' : 'success', $err ?: 'Score saved.');
    redirect('admin/results.php?id=' . $id);
}

$board = load_session_board($id);
$standings = session_standings($id);

$pageTitle = 'Results · ' . $session['title'];
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h1>Results</h1>
    <p><?= e($session['title']) ?> · <?= e($session['play_date']) ?></p>
    <div class="btn-row">
        <a class="btn secondary" href="<?= e(url('admin/session.php?id=' . $id)) ?>">Back to session</a>
        <a class="btn secondary" href="<?= e(url('play.php?session=' . $id)) ?>">Play view</a>
    </div>
</section>

<section class="panel">
    <h2>Standings</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Player</th><th>W</th><th>L</th><th>PF</th><th>PA</th><th>Diff</th></tr>
            </thead>
            <tbody>
            <?php foreach ($standings as $i => $row): ?>
                <tr class="<?= $i === 0 ? 'standings-top' : '' ?>">
                    <td><?= e($row['name']) ?></td>
                    <td><?= (int) $row['w'] ?></td>
                    <td><?= (int) $row['l'] ?></td>
                    <td><?= (int) $row['pf'] ?></td>
                    <td><?= (int) $row['pa'] ?></td>
                    <td><?= (int) $row['diff'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (!$board['slots']): ?>
    <p class="muted">Generate a schedule before entering results.</p>
<?php endif; ?>

<?php foreach ($board['slots'] as $slot): ?>
    <article class="slot-card">
        <strong><?= e(format_slot_label($slot['slot_index'], $slot['starts_offset_min'], $slotMinutes)) ?></strong>
        <div class="court-grid">
            <?php foreach ($slot['courts'] as $court): ?>
                <div class="court">
                    <h4>Court <?= (int) $court['court_number'] ?></h4>
                    <div><?= e($court['team1'][0]) ?> &amp; <?= e($court['team1'][1]) ?></div>
                    <div class="vs">vs</div>
                    <div><?= e($court['team2'][0]) ?> &amp; <?= e($court['team2'][1]) ?></div>
                    <form method="post" class="score-inputs">
                        <?= csrf_field() ?>
                        <input type="hidden" name="game_id" value="<?= (int) $court['id'] ?>">
                        <input type="number" name="team1_score" min="0" max="99" required
                               value="<?= $court['team1_score'] !== null ? (int) $court['team1_score'] : '' ?>"
                               aria-label="Team 1 score">
                        <span>–</span>
                        <input type="number" name="team2_score" min="0" max="99" required
                               value="<?= $court['team2_score'] !== null ? (int) $court['team2_score'] : '' ?>"
                               aria-label="Team 2 score">
                        <button type="submit">Save</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
