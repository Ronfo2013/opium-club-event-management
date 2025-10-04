<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestisce le richieste OPTIONS per CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    exit();
}

// Legge i dati JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Dati JSON non validi']);
    exit();
}

try {
    // Configurazione database
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_DATABASE'] ?? 'opium_events';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crea le tabelle se non esistono
    createAnalyticsTables($pdo);

    // Salva i dati di analytics
    $sessionId = $data['session_id'] ?? null;
    $userId = $data['user_id'] ?? null;
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');

    // Salva gli eventi
    if (isset($data['events']) && is_array($data['events'])) {
        foreach ($data['events'] as $event) {
            saveEvent($pdo, $event, $sessionId, $userId, $timestamp);
        }
    }

    // Salva le page views
    if (isset($data['page_views']) && is_array($data['page_views'])) {
        foreach ($data['page_views'] as $pageView) {
            savePageView($pdo, $pageView, $sessionId, $userId, $timestamp);
        }
    }

    // Aggiorna le statistiche della sessione
    updateSessionStats($pdo, $sessionId, $userId, $data);

    echo json_encode(['success' => true, 'message' => 'Dati analytics salvati']);

} catch (Exception $e) {
    error_log("Errore analytics: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno del server']);
}

function createAnalyticsTables($pdo) {
    // Tabella per le sessioni
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS analytics_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) UNIQUE NOT NULL,
            user_id VARCHAR(255) NOT NULL,
            start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            end_time TIMESTAMP NULL,
            duration INT DEFAULT 0,
            events_count INT DEFAULT 0,
            page_views_count INT DEFAULT 0,
            user_agent TEXT,
            screen_resolution VARCHAR(50),
            viewport_size VARCHAR(50),
            language VARCHAR(10),
            timezone VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            INDEX idx_user_id (user_id),
            INDEX idx_start_time (start_time)
        )
    ");

    // Tabella per gli eventi
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS analytics_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) NOT NULL,
            user_id VARCHAR(255) NOT NULL,
            event_name VARCHAR(255) NOT NULL,
            properties JSON,
            page VARCHAR(255),
            referrer TEXT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            INDEX idx_user_id (user_id),
            INDEX idx_event_name (event_name),
            INDEX idx_timestamp (timestamp),
            INDEX idx_page (page)
        )
    ");

    // Tabella per le page views
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS analytics_page_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) NOT NULL,
            user_id VARCHAR(255) NOT NULL,
            page VARCHAR(255) NOT NULL,
            title VARCHAR(255),
            referrer TEXT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            INDEX idx_user_id (user_id),
            INDEX idx_page (page),
            INDEX idx_timestamp (timestamp)
        )
    ");

    // Tabella per le metriche di performance
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS analytics_performance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) NOT NULL,
            user_id VARCHAR(255) NOT NULL,
            page VARCHAR(255) NOT NULL,
            load_time INT,
            dom_content_loaded INT,
            first_paint INT,
            first_contentful_paint INT,
            largest_contentful_paint INT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            INDEX idx_user_id (user_id),
            INDEX idx_page (page),
            INDEX idx_timestamp (timestamp)
        )
    ");
}

function saveEvent($pdo, $event, $sessionId, $userId, $timestamp) {
    $stmt = $pdo->prepare("
        INSERT INTO analytics_events 
        (session_id, user_id, event_name, properties, page, referrer, timestamp) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $sessionId,
        $userId,
        $event['event_name'] ?? 'unknown',
        json_encode($event['properties'] ?? []),
        $event['properties']['page'] ?? null,
        $event['properties']['referrer'] ?? null,
        $event['properties']['timestamp'] ?? $timestamp
    ]);
}

function savePageView($pdo, $pageView, $sessionId, $userId, $timestamp) {
    $stmt = $pdo->prepare("
        INSERT INTO analytics_page_views 
        (session_id, user_id, page, title, referrer, timestamp) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $sessionId,
        $userId,
        $pageView['page'] ?? '/',
        $pageView['title'] ?? null,
        $pageView['referrer'] ?? null,
        $pageView['timestamp'] ?? $timestamp
    ]);
}

function updateSessionStats($pdo, $sessionId, $userId, $data) {
    // Controlla se la sessione esiste già
    $stmt = $pdo->prepare("SELECT id FROM analytics_sessions WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if ($session) {
        // Aggiorna la sessione esistente
        $stmt = $pdo->prepare("
            UPDATE analytics_sessions 
            SET events_count = events_count + ?, 
                page_views_count = page_views_count + ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE session_id = ?
        ");
        $stmt->execute([
            count($data['events'] ?? []),
            count($data['page_views'] ?? []),
            $sessionId
        ]);
    } else {
        // Crea una nuova sessione
        $stmt = $pdo->prepare("
            INSERT INTO analytics_sessions 
            (session_id, user_id, events_count, page_views_count, user_agent, screen_resolution, viewport_size, language, timezone) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $firstEvent = $data['events'][0] ?? [];
        $properties = $firstEvent['properties'] ?? [];

        $stmt->execute([
            $sessionId,
            $userId,
            count($data['events'] ?? []),
            count($data['page_views'] ?? []),
            $properties['user_agent'] ?? null,
            $properties['screen_resolution'] ?? null,
            $properties['viewport_size'] ?? null,
            $properties['language'] ?? null,
            $properties['timezone'] ?? null
        ]);
    }
}
?>
