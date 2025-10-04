<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $event_id = intval($input['event_id'] ?? 0);
    $user_ids = $input['user_ids'] ?? [];

    if (!$event_id || !is_array($user_ids) || empty($user_ids)) {
        echo json_encode(['success' => false, 'message' => 'Parametri mancanti.']);
        exit;
    }

    $queue = [];
    foreach ($user_ids as $uid) {
        $queue[] = ['user_id' => intval($uid), 'event_id' => $event_id];
    }

    // Su App Engine standard il FS è read-only: usa /tmp
    $queueFile = isset($_SERVER['GAE_APPLICATION'])
        ? '/tmp/mail_queue.json'
        : __DIR__ . '/mail_queue.json';

    if (false === file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT))) {
        echo json_encode(['success' => false, 'message' => 'Impossibile scrivere la coda.']);
        exit;
    }

    echo json_encode(['success' => true, 'path' => $queueFile]);
    exit;
}
echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
?>
