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
        $status = $index === 0 ? 'active' : 'pending';
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
    $status = 'active';
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

function load_session_board(int $sessionId): array
{
    $session = fetch_session($sessionId);
    if (!$session) {
        return [];
    }

    $names = player_map();
    $slots = [];
    $stmt = db()->prepare(
        'SELECT * FROM slots WHERE session_id = ? ORDER BY slot_index ASC'
    );
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $slotRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($slotRows as $slot) {
        $slotId = (int) $slot['id'];
        $gStmt = db()->prepare(
            'SELECT * FROM games WHERE slot_id = ? ORDER BY court_number ASC'
        );
        $gStmt->bind_param('i', $slotId);
        $gStmt->execute();
        $games = $gStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $gStmt->close();

        $sStmt = db()->prepare('SELECT player_id FROM slot_sitouts WHERE slot_id = ?');
        $sStmt->bind_param('i', $slotId);
        $sStmt->execute();
        $sitouts = [];
        $sRes = $sStmt->get_result();
        while ($row = $sRes->fetch_assoc()) {
            $pid = (int) $row['player_id'];
            $sitouts[] = ['id' => $pid, 'name' => $names[$pid] ?? ('#' . $pid)];
        }
        $sStmt->close();

        $courtViews = [];
        foreach ($games as $g) {
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
            'sitouts' => $sitouts,
        ];
    }

    return ['session' => $session, 'slots' => $slots, 'names' => $names];
}

/**
 * @return array<int, array{player_id:int,name:string,w:int,l:int,pf:int,pa:int,diff:int}>
 */
function session_standings(int $sessionId): array
{
    $board = load_session_board($sessionId);
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
