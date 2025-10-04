<?php
require_once 'admin.php'; // Per usare resendEmail, $pdo, $config
// Su App Engine standard usa /tmp per file temporanei
$queueFile = isset($_SERVER['GAE_APPLICATION'])
    ? '/tmp/mail_queue.json'
    : __DIR__ . '/mail_queue.json';

if (!file_exists($queueFile)) {
    echo "Nessuna coda trovata.";
    exit;
}
$queue = json_decode(file_get_contents($queueFile), true);

$total = count($queue);
echo "<h2>Invio batch: $total mail</h2>";
echo "<pre>";

$successi = 0;
$fail = 0;
foreach ($queue as $i => $item) {
    $userId = $item['user_id'];
    $successMessage = '';
    $errorMessage = '';
    resendEmail($pdo, $userId, $config, $successMessage, $errorMessage);

    if ($successMessage) {
        echo "[$i/" . ($total-1) . "] ✅ $successMessage (user id $userId)\n";
        $successi++;
    } else {
        echo "[$i/" . ($total-1) . "] ❌ $errorMessage (user id $userId)\n";
        $fail++;
    }
    flush(); ob_flush();
    sleep(2); // Delay di sicurezza, puoi aumentare/diminuire
}
echo "\nTotali inviate: $successi\nTotali errore: $fail";
echo "</pre>";

unlink($queueFile); // Cancella la coda dopo l’invio
?>
