<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/includes/db.php';

    $user = current_user($pdo);
    if (!$user) {
        echo json_encode(['error' => 'Not logged in. Please log in again.']);
        exit;
    }

    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $qids       = $_POST['qids']    ?? [];
    $answers    = $_POST['answers'] ?? [];

    if (!$subject_id || empty($qids)) {
        echo json_encode(['error' => 'Missing data']);
        exit;
    }

    $score   = 0;
    $total   = count($qids);
    $results = [];

    $st = $pdo->prepare('SELECT correct_index FROM questions WHERE id = ? LIMIT 1');

    foreach ($qids as $qid) {
        $qid = (int)$qid;
        $st->execute([$qid]);
        $q = $st->fetch();

        $sel = isset($answers[$qid]) ? (int)$answers[$qid] : -1;
        $ok  = ($q && $sel === (int)$q['correct_index']) ? 1 : 0;

        $results[$qid] = $ok;
        $score += $ok;
    }

    $pdo->prepare('INSERT INTO attempts (user_id, subject_id, score, total) VALUES (?,?,?,?)')
        ->execute([(int)$user['id'], $subject_id, $score, $total]);

    echo json_encode([
        'score'   => $score,
        'total'   => $total,
        'results' => $results,
    ]);

} catch (Throwable $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}