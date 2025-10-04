<?php
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

if (!$data || !isset($data['subscription'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dati sottoscrizione mancanti']);
    exit();
}

try {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_DATABASE'] ?? 'opium_events';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crea tabella se non esiste
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            endpoint TEXT NOT NULL,
            p256dh_key TEXT,
            auth_key TEXT,
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_endpoint (endpoint(255))
        )
    ");

    $subscription = $data['subscription'];
    $endpoint = $subscription['endpoint'];
    $keys = $subscription['keys'] ?? [];
    $p256dh = $keys['p256dh'] ?? null;
    $auth = $keys['auth'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO push_subscriptions (endpoint, p256dh_key, auth_key, user_agent) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        p256dh_key = VALUES(p256dh_key),
        auth_key = VALUES(auth_key),
        user_agent = VALUES(user_agent),
        updated_at = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        $endpoint,
        $p256dh,
        $auth,
        $data['user_agent'] ?? null
    ]);

    echo json_encode(['success' => true, 'message' => 'Sottoscrizione salvata']);

} catch (Exception $e) {
    error_log("Errore sottoscrizione: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno del server']);
}
?>
