<?php

require_once __DIR__ . '/db.php';

/**
 * Save a game score for a session. Returns null on success, or an error message.
 */
function save_game_score(int $sessionId, int $gameId, int $team1Score, int $team2Score): ?string
{
    if ($gameId < 1 || $team1Score < 0 || $team2Score < 0) {
        return 'Invalid score.';
    }

    $check = db()->prepare(
        'SELECT g.id FROM games g
         INNER JOIN slots s ON s.id = g.slot_id
         WHERE g.id = ? AND s.session_id = ? LIMIT 1'
    );
    $check->bind_param('ii', $gameId, $sessionId);
    $check->execute();
    $ok = (bool) $check->get_result()->fetch_assoc();
    $check->close();

    if (!$ok) {
        return 'Game not found in this session.';
    }

    $winner = 0;
    if ($team1Score > $team2Score) {
        $winner = 1;
    } elseif ($team2Score > $team1Score) {
        $winner = 2;
    }

    $stmt = db()->prepare(
        'UPDATE games SET team1_score = ?, team2_score = ?, winner_team = ? WHERE id = ?'
    );
    $stmt->bind_param('iiii', $team1Score, $team2Score, $winner, $gameId);
    $stmt->execute();
    $stmt->close();

    return null;
}
