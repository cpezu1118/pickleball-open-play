<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Scheduler.php';

function player_map(): array
{
    $rows = db()->query('SELECT id, name FROM players')->fetch_all(MYSQLI_ASSOC);
    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['id']] = $row['name'];
    }
    return $map;
}

function fetch_session(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM sessions WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * @return int[]
 */
function session_player_ids(int $sessionId): array
{
    $stmt = db()->prepare('SELECT player_id FROM session_players WHERE session_id = ?');
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $ids = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['player_id'];
    }
    $stmt->close();
    return $ids;
}

function clear_session_schedule(int $sessionId): void
{
    $mysqli = db();
    $stmt = $mysqli->prepare('SELECT id FROM slots WHERE session_id = ?');
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $slotIds = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $slotIds[] = (int) $row['id'];
    }
    $stmt->close();

    if ($slotIds) {
        $in = implode(',', array_fill(0, count($slotIds), '?'));
        $types = str_repeat('i', count($slotIds));
        $delGames = $mysqli->prepare("DELETE FROM games WHERE slot_id IN ($in)");
        $delGames->bind_param($types, ...$slotIds);
        $delGames->execute();
        $delGames->close();

        $delSit = $mysqli->prepare("DELETE FROM slot_sitouts WHERE slot_id IN ($in)");
        $delSit->bind_param($types, ...$slotIds);
        $delSit->execute();
        $delSit->close();
    }

    $delSlots = $mysqli->prepare('DELETE FROM slots WHERE session_id = ?');
    $delSlots->bind_param('i', $sessionId);
    $delSlots->execute();
    $delSlots->close();
}

/**
 * @return array{has_repeats: bool, repeated_pairs: array<int, array{a:int,b:int}>}
 */
function persist_schedule(int $sessionId, array $schedule, int $slotMinutes, bool $ackWarning): array
{
    $mysqli = db();
    clear_session_schedule($sessionId);

    $slotStmt = $mysqli->prepare(
        'INSERT INTO slots (session_id, slot_index, starts_offset_min, status) VALUES (?, ?, ?, ?)'
    );
    $gameStmt = $mysqli->prepare(
        'INSERT INTO games (slot_id, court_number, p1_id, p2_id, p3_id, p4_id) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $sitStmt = $mysqli->prepare(
        'INSERT INTO slot_sitouts (slot_id, player_id) VALUES (?, ?)'
    );

    foreach ($schedule['slots'] as $index => $slot) {
        $offset = $index * $slotMinutes;
        // All pending until admin starts open play (sets Round 1 active).
        $status = 'pending';
        $slotStmt->bind_param('iiis', $sessionId, $index, $offset, $status);
        $slotStmt->execute();
        $slotId = (int) $mysqli->insert_id;

        foreach ($slot['courts'] as $cIndex => $court) {
            $courtNum = $cIndex + 1;
            $p1 = $court['p1'];
            $p2 = $court['p2'];
            $p3 = $court['p3'];
            $p4 = $court['p4'];
            $gameStmt->bind_param('iiiiii', $slotId, $courtNum, $p1, $p2, $p3, $p4);
            $gameStmt->execute();
        }

        foreach ($slot['sitouts'] as $pid) {
            $sitStmt->bind_param('ii', $slotId, $pid);
            $sitStmt->execute();
        }
    }

    $slotStmt->close();
    $gameStmt->close();
    $sitStmt->close();

    $ack = $ackWarning || !$schedule['has_repeats'] ? 1 : 0;
    // Schedule ready; live play starts when admin taps Start on play.php.
    $status = 'draft';
    $upd = $mysqli->prepare(
        'UPDATE sessions SET status = ?, partner_warning_ack = ? WHERE id = ?'
    );
    $upd->bind_param('sii', $status, $ack, $sessionId);
    $upd->execute();
    $upd->close();

    return [
        'has_repeats' => (bool) $schedule['has_repeats'],
        'repeated_pairs' => $schedule['repeated_pairs'],
    ];
}

/**
 * 1-based live round from the slot marked active. 0 = not started.
 */
function session_current_round(int $sessionId): int
{
    $stmt = db()->prepare(
        "SELECT slot_index FROM slots
         WHERE session_id = ? AND status = 'active'
         ORDER BY slot_index ASC LIMIT 1"
    );
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return 0;
    }
    return (int) $row['slot_index'] + 1;
}

function session_has_schedule(int $sessionId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM slots WHERE session_id = ?');
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $c = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $c > 0;
}

/**
 * Set the live round (1-based). Marks earlier slots done, current active, later pending.
 */
function session_set_current_round(int $sessionId, int $round1Based): bool
{
    $mysqli = db();
    $stmt = $mysqli->prepare(
        'SELECT id, slot_index FROM slots WHERE session_id = ? ORDER BY slot_index ASC'
    );
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$rows) {
        return false;
    }

    $targetIndex = $round1Based - 1;
    $maxIndex = (int) $rows[count($rows) - 1]['slot_index'];
    if ($targetIndex < 0 || $targetIndex > $maxIndex) {
        return false;
    }

    $upd = $mysqli->prepare('UPDATE slots SET status = ? WHERE id = ?');
    foreach ($rows as $row) {
        $idx = (int) $row['slot_index'];
        $id = (int) $row['id'];
        if ($idx < $targetIndex) {
            $status = 'done';
        } elseif ($idx === $targetIndex) {
            $status = 'active';
        } else {
            $status = 'pending';
        }
        $upd->bind_param('si', $status, $id);
        $upd->execute();
    }
    $upd->close();

    $sessStatus = 'active';
    $s = $mysqli->prepare('UPDATE sessions SET status = ? WHERE id = ?');
    $s->bind_param('si', $sessStatus, $sessionId);
    $s->execute();
    $s->close();

    return true;
}

function session_start_play(int $sessionId): bool
{
    if (!session_has_schedule($sessionId)) {
        return false;
    }
    return session_set_current_round($sessionId, 1);
}

function session_advance_round(int $sessionId, int $delta): bool
{
    $current = session_current_round($sessionId);
    if ($current < 1) {
        return false;
    }
    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM slots WHERE session_id = ?');
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    $next = $current + $delta;
    if ($next < 1 || $next > $count) {
        return false;
    }
    return session_set_current_round($sessionId, $next);
}

/**
 * Revision string so clients can detect round or score changes.
 */
function session_play_revision(int $sessionId): string
{
    $round = session_current_round($sessionId);
    $stmt = db()->prepare(
        'SELECT g.team1_score, g.team2_score, g.winner_team
         FROM games g
         INNER JOIN slots s ON s.id = g.slot_id
         WHERE s.session_id = ?
         ORDER BY s.slot_index, g.court_number'
    );
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $parts = [(string) $round];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $parts[] = ($row['team1_score'] ?? 'n') . '-' . ($row['team2_score'] ?? 'n') . ':' . ($row['winner_team'] ?? 'n');
    }
    $stmt->close();
    $session = fetch_session($sessionId);
    $parts[] = $session['status'] ?? '';
    return md5(implode('|', $parts));
}

function load_session_board(int $sessionId): array
{
    $session = fetch_session($sessionId);
    if (!$session) {
        return [];
    }

    $names = player_map();

    $stmt = db()->prepare(
        'SELECT * FROM slots WHERE session_id = ? ORDER BY slot_index ASC'
    );
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $slotRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Batch-fetch games and sitouts for every slot in this session (2 queries
    // total instead of one round trip per slot) — this matters a lot on a
    // remote DB, where each round trip costs real latency.
    $gamesBySlot = [];
    $gStmt = db()->prepare(
        'SELECT g.* FROM games g
         INNER JOIN slots s ON s.id = g.slot_id
         WHERE s.session_id = ?
         ORDER BY g.slot_id ASC, g.court_number ASC'
    );
    $gStmt->bind_param('i', $sessionId);
    $gStmt->execute();
    $gRes = $gStmt->get_result();
    while ($g = $gRes->fetch_assoc()) {
        $gamesBySlot[(int) $g['slot_id']][] = $g;
    }
    $gStmt->close();

    $sitoutsBySlot = [];
    $sStmt = db()->prepare(
        'SELECT ss.slot_id, ss.player_id FROM slot_sitouts ss
         INNER JOIN slots s ON s.id = ss.slot_id
         WHERE s.session_id = ?'
    );
    $sStmt->bind_param('i', $sessionId);
    $sStmt->execute();
    $sRes = $sStmt->get_result();
    while ($row = $sRes->fetch_assoc()) {
        $pid = (int) $row['player_id'];
        $sitoutsBySlot[(int) $row['slot_id']][] = ['id' => $pid, 'name' => $names[$pid] ?? ('#' . $pid)];
    }
    $sStmt->close();

    $slots = [];
    foreach ($slotRows as $slot) {
        $slotId = (int) $slot['id'];

        $courtViews = [];
        foreach ($gamesBySlot[$slotId] ?? [] as $g) {
            $courtViews[] = [
                'id' => (int) $g['id'],
                'court_number' => (int) $g['court_number'],
                'team1' => [
                    $names[(int) $g['p1_id']] ?? '',
                    $names[(int) $g['p2_id']] ?? '',
                ],
                'team2' => [
                    $names[(int) $g['p3_id']] ?? '',
                    $names[(int) $g['p4_id']] ?? '',
                ],
                'p1_id' => (int) $g['p1_id'],
                'p2_id' => (int) $g['p2_id'],
                'p3_id' => (int) $g['p3_id'],
                'p4_id' => (int) $g['p4_id'],
                'team1_score' => $g['team1_score'],
                'team2_score' => $g['team2_score'],
                'winner_team' => $g['winner_team'],
            ];
        }

        $slots[] = [
            'id' => $slotId,
            'slot_index' => (int) $slot['slot_index'],
            'starts_offset_min' => (int) $slot['starts_offset_min'],
            'status' => $slot['status'],
            'courts' => $courtViews,
            'sitouts' => $sitoutsBySlot[$slotId] ?? [],
        ];
    }

    return ['session' => $session, 'slots' => $slots, 'names' => $names];
}

/**
 * @return array<int, array{player_id:int,name:string,w:int,l:int,pf:int,pa:int,diff:int}>
 */
function session_standings(int $sessionId, ?array $board = null): array
{
    $board = $board ?? load_session_board($sessionId);
    if (!$board) {
        return [];
    }

    $stats = [];
    foreach (session_player_ids($sessionId) as $pid) {
        $stats[$pid] = [
            'player_id' => $pid,
            'name' => $board['names'][$pid] ?? ('#' . $pid),
            'w' => 0,
            'l' => 0,
            'pf' => 0,
            'pa' => 0,
            'diff' => 0,
        ];
    }

    foreach ($board['slots'] as $slot) {
        foreach ($slot['courts'] as $court) {
            if ($court['team1_score'] === null || $court['team2_score'] === null) {
                continue;
            }
            $t1 = [(int) $court['p1_id'], (int) $court['p2_id']];
            $t2 = [(int) $court['p3_id'], (int) $court['p4_id']];
            $s1 = (int) $court['team1_score'];
            $s2 = (int) $court['team2_score'];
            $winner = (int) $court['winner_team'];

            foreach ($t1 as $pid) {
                if (!isset($stats[$pid])) {
                    continue;
                }
                $stats[$pid]['pf'] += $s1;
                $stats[$pid]['pa'] += $s2;
                if ($winner === 1) {
                    $stats[$pid]['w']++;
                } elseif ($winner === 2) {
                    $stats[$pid]['l']++;
                }
            }
            foreach ($t2 as $pid) {
                if (!isset($stats[$pid])) {
                    continue;
                }
                $stats[$pid]['pf'] += $s2;
                $stats[$pid]['pa'] += $s1;
                if ($winner === 2) {
                    $stats[$pid]['w']++;
                } elseif ($winner === 1) {
                    $stats[$pid]['l']++;
                }
            }
        }
    }

    foreach ($stats as &$row) {
        $row['diff'] = $row['pf'] - $row['pa'];
    }
    unset($row);

    usort($stats, static function (array $a, array $b): int {
        return [$b['w'], $b['diff'], $b['pf'], $a['name']]
            <=> [$a['w'], $a['diff'], $a['pf'], $b['name']];
    });

    return array_values($stats);
}
