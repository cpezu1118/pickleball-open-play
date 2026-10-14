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
$roundCount = count($slots);
$liveRound = session_current_round($sessionId);
$started = $liveRound >= 1;

// Admin-only mutations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_string('action');

    if (!$isAdminUser) {
        flash_set('error', 'Only the host can control open play.');
        redirect('play.php?session=' . $sessionId);
    }

    if ($action === 'start_play') {
        if (!session_start_play($sessionId)) {
            flash_set('error', 'Could not start — generate a schedule first.');
        } else {
            flash_set('success', 'Open play started — Round 1 is live.');
        }
        redirect('play.php?session=' . $sessionId);
    }

    if ($action === 'next_round') {
        if (!session_advance_round($sessionId, 1)) {
            flash_set('error', 'Already on the last round (or not started).');
        } else {
            flash_set('success', 'Advanced to Round ' . session_current_round($sessionId) . '.');
        }
        redirect('play.php?session=' . $sessionId);
    }

    if ($action === 'prev_round') {
        if (!session_advance_round($sessionId, -1)) {
            flash_set('error', 'Already on Round 1 (or not started).');
        } else {
            flash_set('success', 'Back to Round ' . session_current_round($sessionId) . '.');
        }
        redirect('play.php?session=' . $sessionId);
    }

    if ($action === 'save_score') {
        if (!$trackResults) {
            flash_set('error', 'Results tracking is off for this open play.');
            redirect('play.php?session=' . $sessionId);
        }
        $err = save_game_score($sessionId, post_int('game_id'), post_int('team1_score'), post_int('team2_score'));
        flash_set($err ? 'error' : 'success', $err ?: 'Score saved.');
        redirect('play.php?session=' . $sessionId);
    }

    flash_set('error', 'Unknown action.');
    redirect('play.php?session=' . $sessionId);
}

// Refresh after possible redirects
$session = fetch_session($sessionId) ?: $session;
$liveRound = session_current_round($sessionId);
$started = $liveRound >= 1;
$revision = $roundCount ? session_play_revision($sessionId) : '';
$standings = $trackResults ? session_standings($sessionId, $board) : [];
$view = ($_GET['view'] ?? 'rounds') === 'standings' ? 'standings' : 'rounds';
$displayRound = $started ? $liveRound : 1;

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
<div class="play-app"
     data-play-carousel
     data-session-id="<?= (int) $sessionId ?>"
     data-is-admin="<?= $isAdminUser ? '1' : '0' ?>"
     data-started="<?= $started ? '1' : '0' ?>"
     data-live-round="<?= (int) $liveRound ?>"
     data-initial-round="<?= (int) $displayRound ?>"
     data-round-count="<?= (int) $roundCount ?>"
     data-revision="<?= e($revision) ?>"
     data-state-url="<?= e(url('play_state.php?session=' . $sessionId)) ?>"
     data-view="<?= e($view) ?>"
     data-celebrate="<?= ($flash && $flash['message'] === 'Score saved.') ? '1' : '0' ?>">

    <header class="play-top">
        <div class="play-top-row">
            <div class="play-title-block">
                <h1 class="play-title"><?= e($session['title']) ?></h1>
            </div>
            <div class="play-top-actions">
                <span class="play-brand-mark"><svg class="pin-icon icon-sm"><use href="#icon-pin"></use></svg> <?= e($appName) ?></span>
                <button type="button" class="sound-toggle" data-sound-toggle aria-label="Mute sound">
                    <svg class="pin-icon"><use href="#icon-sound-on"></use></svg>
                </button>
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

        <div class="play-live-bar">
            <?php if (!$roundCount): ?>
                <span class="badge">No schedule</span>
            <?php elseif (!$started): ?>
                <span class="badge">Waiting for host to start</span>
            <?php else: ?>
                <span class="badge badge-on"><svg class="pin-icon icon-sm"><use href="#icon-pin"></use></svg> Live · Round <?= (int) $liveRound ?> of <?= (int) $roundCount ?></span>
            <?php endif; ?>
            <?php if (!$isAdminUser): ?>
                <span class="play-view-only muted">View only</span>
            <?php endif; ?>
        </div>

        <?php if ($isAdminUser && $roundCount): ?>
            <div class="play-host-controls">
                <?php if (!$started): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="start_play">
                        <button type="submit" class="btn-primary">Start open play</button>
                    </form>
                <?php else: ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="prev_round">
                        <button type="submit" class="secondary" <?= $liveRound <= 1 ? 'disabled' : '' ?>>Previous round</button>
                    </form>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="next_round">
                        <button type="submit" class="btn-primary" <?= $liveRound >= $roundCount ? 'disabled' : '' ?>>Next round</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($isAdminUser && $roundCount): ?>
            <?php require __DIR__ . '/includes/share_card.php'; ?>
        <?php endif; ?>

        <?php if ($trackResults && $roundCount > 0 && $started): ?>
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
    <?php elseif (!$started && !$isAdminUser): ?>
        <main class="play-empty">
            <p>The host hasn’t started this open play yet.</p>
            <p class="muted">This page will update when Round 1 goes live.</p>
        </main>
    <?php else: ?>

        <?php if ($timerEnabled && $isAdminUser && $started): ?>
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

        <?php if ($started || $isAdminUser): ?>
        <section class="play-panel play-rounds-panel <?= $view === 'standings' ? 'is-hidden' : '' ?>" data-play-panel="rounds">
            <?php if ($isAdminUser && !$started): ?>
                <p class="muted" style="text-align:center;">Preview — tap <strong>Start open play</strong> when everyone is ready. Friends stay on waiting until then.</p>
            <?php endif; ?>

            <div class="play-round-meta">
                <?php if ($isAdminUser && $started): ?>
                    <span class="play-nav-spacer" aria-hidden="true"></span>
                <?php else: ?>
                    <button type="button" class="play-nav-btn" data-play-prev aria-label="Previous round">‹</button>
                <?php endif; ?>
                <div class="play-round-label">
                    <span data-play-round-label>Round <?= (int) $displayRound ?></span>
                    <span class="play-round-of">of <?= (int) $roundCount ?></span>
                    <button type="button" class="play-jump-live is-hidden" data-jump-live>Back to live</button>
                </div>
                <?php if ($isAdminUser && $started): ?>
                    <span class="play-nav-spacer" aria-hidden="true"></span>
                <?php else: ?>
                    <button type="button" class="play-nav-btn" data-play-next aria-label="Next round">›</button>
                <?php endif; ?>
            </div>

            <?php if (!($isAdminUser && $started)): ?>
            <div class="play-dots" data-play-dots aria-hidden="true">
                <?php for ($i = 1; $i <= $roundCount; $i++): ?>
                    <button type="button" class="play-dot <?= $i === $displayRound ? 'is-active' : '' ?>" data-play-dot="<?= $i ?>" aria-label="Round <?= $i ?>"></button>
                <?php endfor; ?>
            </div>
            <?php else: ?>
            <div class="play-dots" data-play-dots aria-hidden="true">
                <?php for ($i = 1; $i <= $roundCount; $i++): ?>
                    <span class="play-dot <?= $i === $displayRound ? 'is-active' : '' ?>" data-play-dot="<?= $i ?>"></span>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

            <div class="play-viewport" data-play-viewport>
                <div class="ball-transit" data-ball-transit aria-hidden="true"></div>
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

                                        <?php if ($trackResults && $started): ?>
                                            <?php if ($isAdminUser): ?>
                                                <form method="post" class="play-score-form">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="save_score">
                                                    <input type="hidden" name="game_id" value="<?= (int) $court['id'] ?>">
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
                                    <span class="play-sitouts-label">On the bench</span>
                                    <span><?= e(implode(', ', array_column($slot['sitouts'], 'name'))) ?></span>
                                </div>
                            <?php else: ?>
                                <div class="play-sitouts play-sitouts-empty">Everyone's in!</div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($trackResults && $started): ?>
            <section class="play-panel play-standings-panel <?= $view === 'rounds' ? 'is-hidden' : '' ?>" data-play-panel="standings">
                <h2 class="play-standings-title">Standings</h2>
                <div class="table-wrap play-standings-table">
                    <?php require __DIR__ . '/includes/standings_table.php'; ?>
                </div>
            </section>
        <?php endif; ?>

    <?php endif; ?>
</div>
<script src="<?= e(url('assets/app.js')) ?>"></script>
</body>
</html>
