<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['title'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Titolo notifica mancante']);
    exit();
}

try {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_DATABASE'] ?? 'opium_events';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ottiene tutte le sottoscrizioni
    $stmt = $pdo->query("SELECT endpoint, p256dh_key, auth_key FROM push_subscriptions");
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($subscriptions)) {
        echo json_encode(['success' => true, 'message' => 'Nessuna sottoscrizione trovata']);
        exit();
    }

    // Configura WebPush
    $auth = [
        'VAPID' => [
            'subject' => 'mailto:info@opiumpordenone.com',
            'publicKey' => 'BEl62iUYgUivxIkv69yViEuiBIa40HI0lF5AwyK3rS8',
            'privateKey' => 'your-private-key-here'
        ]
    ];

    $webPush = new WebPush($auth);

    $payload = json_encode([
        'title' => $data['title'],
        'body' => $data['body'] ?? '',
        'icon' => '/logo192.png',
        'badge' => '/logo192.png',
        'url' => $data['url'] ?? '/',
        'data' => $data['data'] ?? []
    ]);

    $results = [];
    foreach ($subscriptions as $sub) {
        $subscription = Subscription::create([
            'endpoint' => $sub['endpoint'],
            'keys' => [
                'p256dh' => $sub['p256dh_key'],
                'auth' => $sub['auth_key']
            ]
        ]);

        $webPush->queueNotification($subscription, $payload);
    }

    $reports = $webPush->flush();

    $successCount = 0;
    $errorCount = 0;

    foreach ($reports as $report) {
        if ($report->isSuccess()) {
            $successCount++;
        } else {
            $errorCount++;
            error_log("Errore invio notifica: " . $report->getReason());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Notifiche inviate: $successCount successi, $errorCount errori",
        'sent' => $successCount,
        'errors' => $errorCount
    ]);

} catch (Exception $e) {
    error_log("Errore invio notifiche: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno del server']);
}
?>
