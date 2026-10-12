<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/session_repo.php';
require_once __DIR__ . '/includes/score_save.php';

$config = app_config();
$slotMinutes = (int) ($config['slot_minutes'] ?? 15);
$sessionId = isset($_GET['session']) ? (int) $_GET['session'] : 0;
$isAdminUser = auth_check();

if ($sessionId < 1) {
    flash_set('error', 'Choose an open play first.');
    redirect('index.php');
}

$board = load_session_board($sessionId);
if (!$board) {
    flash_set('error', 'Session not found.');
    redirect('index.php');
}

$session = $board['session'];
$slots = $board['slots'];
$trackResults = (int) $session['track_results'] === 1;
$timerEnabled = (int) $session['timer_enabled'] === 1;

// Score save (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_string('action') === 'save_score') {
    csrf_verify();
    if (!$isAdminUser) {
        flash_set('error', 'Only admin can enter scores.');
        redirect('play.php?session=' . $sessionId . '&round=' . max(1, post_int('round')));
    }
    if (!$trackResults) {
        flash_set('error', 'Results tracking is off for this open play.');
        redirect('play.php?session=' . $sessionId);
    }
    $round = max(1, post_int('round'));
    $err = save_game_score($sessionId, post_int('game_id'), post_int('team1_score'), post_int('team2_score'));
    flash_set($err ? 'error' : 'success', $err ?: 'Score saved.');
    redirect('play.php?session=' . $sessionId . '&round=' . $round . '&view=rounds');
}

$standings = $trackResults ? session_standings($sessionId) : [];
$initialRound = isset($_GET['round']) ? max(1, (int) $_GET['round']) : 1;
$view = ($_GET['view'] ?? 'rounds') === 'standings' ? 'standings' : 'rounds';
$roundCount = count($slots);
if ($initialRound > $roundCount && $roundCount > 0) {
    $initialRound = $roundCount;
}

$pageTitle = $session['title'];
$appName = $config['app_name'] ?? 'Pickleball Open Play';
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/app.css')) ?>">
</head>
<body class="play-body">
<div class="play-app"
     data-play-carousel
     data-session-id="<?= (int) $sessionId ?>"
     data-initial-round="<?= (int) $initialRound ?>"
     data-round-count="<?= (int) $roundCount ?>"
     data-view="<?= e($view) ?>">

    <header class="play-top">
        <div class="play-top-row">
            <div class="play-title-block">
                <p class="play-eyebrow"><?= e($appName) ?></p>
                <h1 class="play-title"><?= e($session['title']) ?></h1>
            </div>
            <div class="play-top-actions">
                <?php if ($isAdminUser): ?>
                    <a class="play-link" href="<?= e(url('admin/session.php?id=' . $sessionId)) ?>">Setup</a>
                <?php else: ?>
                    <a class="play-link" href="<?= e(url('index.php')) ?>">All</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?> play-flash"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <?php if ($trackResults && $roundCount > 0): ?>
            <div class="play-tabs" role="tablist">
                <button type="button" class="play-tab <?= $view === 'rounds' ? 'is-active' : '' ?>" data-play-tab="rounds">Rounds</button>
                <button type="button" class="play-tab <?= $view === 'standings' ? 'is-active' : '' ?>" data-play-tab="standings">Standings</button>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!$roundCount): ?>
        <main class="play-empty">
            <p>Schedule not generated yet.</p>
            <?php if ($isAdminUser): ?>
                <a class="btn" href="<?= e(url('admin/session.php?id=' . $sessionId)) ?>">Generate in setup</a>
            <?php endif; ?>
        </main>
    <?php else: ?>

        <?php if ($timerEnabled): ?>
            <section class="play-timer-bar" data-slot-timer data-duration="<?= (int) ($slotMinutes * 60) ?>">
                <div class="timer-display" data-timer-display>15:00</div>
                <div class="play-timer-controls">
                    <label class="check-row play-sound-label">
                        <input type="checkbox" data-timer-sound checked>
                        Sound
                    </label>
                    <button type="button" data-unlock-audio class="secondary play-btn-sm">Enable sound</button>
                    <button type="button" data-timer-start class="play-btn-sm">Start</button>
                    <button type="button" data-timer-pause class="secondary play-btn-sm">Pause</button>
                    <button type="button" data-timer-reset class="secondary play-btn-sm">Reset</button>
                </div>
            </section>
        <?php endif; ?>

        <section class="play-panel play-rounds-panel <?= $view === 'standings' ? 'is-hidden' : '' ?>" data-play-panel="rounds">
            <div class="play-round-meta">
                <button type="button" class="play-nav-btn" data-play-prev aria-label="Previous round">‹</button>
                <div class="play-round-label">
                    <span data-play-round-label>Round <?= (int) $initialRound ?></span>
                    <span class="play-round-of">of <?= (int) $roundCount ?></span>
                </div>
                <button type="button" class="play-nav-btn" data-play-next aria-label="Next round">›</button>
            </div>

            <div class="play-dots" data-play-dots aria-hidden="true">
                <?php for ($i = 1; $i <= $roundCount; $i++): ?>
                    <button type="button" class="play-dot <?= $i === $initialRound ? 'is-active' : '' ?>" data-play-dot="<?= $i ?>" aria-label="Round <?= $i ?>"></button>
                <?php endfor; ?>
            </div>

            <div class="play-viewport" data-play-viewport>
                <div class="play-track" data-play-track>
                    <?php foreach ($slots as $slot): ?>
                        <?php
                        $roundNum = (int) $slot['slot_index'] + 1;
                        $timeLabel = format_slot_label($slot['slot_index'], $slot['starts_offset_min'], $slotMinutes);
                        ?>
                        <article class="play-slide" data-play-slide data-round="<?= $roundNum ?>">
                            <p class="play-time muted"><?= e(preg_replace('/^Slot \d+ · /', '', $timeLabel)) ?></p>
                            <div class="play-court-grid">
                                <?php foreach ($slot['courts'] as $court): ?>
                                    <div class="play-court">
                                        <h3 class="play-court-label">Court <?= (int) $court['court_number'] ?></h3>
                                        <div class="play-team">
                                            <span class="play-names"><?= e($court['team1'][0]) ?> &amp; <?= e($court['team1'][1]) ?></span>
                                        </div>
                                        <div class="play-vs">vs</div>
                                        <div class="play-team">
                                            <span class="play-names"><?= e($court['team2'][0]) ?> &amp; <?= e($court['team2'][1]) ?></span>
                                        </div>

                                        <?php if ($trackResults): ?>
                                            <?php if ($isAdminUser): ?>
                                                <form method="post" class="play-score-form">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="save_score">
                                                    <input type="hidden" name="game_id" value="<?= (int) $court['id'] ?>">
                                                    <input type="hidden" name="round" value="<?= $roundNum ?>">
                                                    <div class="play-score-inputs">
                                                        <input type="number" name="team1_score" min="0" max="99" required
                                                               inputmode="numeric"
                                                               value="<?= $court['team1_score'] !== null ? (int) $court['team1_score'] : '' ?>"
                                                               aria-label="Team 1 score">
                                                        <span>–</span>
                                                        <input type="number" name="team2_score" min="0" max="99" required
                                                               inputmode="numeric"
                                                               value="<?= $court['team2_score'] !== null ? (int) $court['team2_score'] : '' ?>"
                                                               aria-label="Team 2 score">
                                                        <button type="submit" class="play-btn-sm">Save</button>
                                                    </div>
                                                </form>
                                            <?php elseif ($court['team1_score'] !== null): ?>
                                                <p class="play-score-readout">
                                                    <?= (int) $court['team1_score'] ?>–<?= (int) $court['team2_score'] ?>
                                                </p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($slot['sitouts']): ?>
                                <div class="play-sitouts">
                                    <span class="play-sitouts-label">Sitting out</span>
                                    <span><?= e(implode(', ', array_column($slot['sitouts'], 'name'))) ?></span>
                                </div>
                            <?php else: ?>
                                <div class="play-sitouts play-sitouts-empty">Everyone playing</div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <?php if ($trackResults): ?>
            <section class="play-panel play-standings-panel <?= $view === 'rounds' ? 'is-hidden' : '' ?>" data-play-panel="standings">
                <h2 class="play-standings-title">Standings</h2>
                <div class="table-wrap play-standings-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Player</th>
                                <th>W</th>
                                <th>L</th>
                                <th>PF</th>
                                <th>PA</th>
                                <th>Diff</th>
                            </tr>
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
        <?php endif; ?>

    <?php endif; ?>
</div>
<script src="<?= e(url('assets/app.js')) ?>"></script>
</body>
</html>
