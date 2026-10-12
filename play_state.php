<?php
/**
 * Public JSON: live round + revision for polling (viewers follow admin progress).
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/session_repo.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$sessionId = isset($_GET['session']) ? (int) $_GET['session'] : 0;
$session = $sessionId > 0 ? fetch_session($sessionId) : null;

if (!$session) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$roundCount = 0;
$stmt = db()->prepare('SELECT COUNT(*) AS c FROM slots WHERE session_id = ?');
$stmt->bind_param('i', $sessionId);
$stmt->execute();
$roundCount = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$current = session_current_round($sessionId);
$started = $current >= 1 && ($session['status'] === 'active' || $session['status'] === 'done');

echo json_encode([
    'session_id' => $sessionId,
    'status' => $session['status'],
    'started' => $started,
    'current_round' => $current,
    'round_count' => $roundCount,
    'revision' => session_play_revision($sessionId),
]);
