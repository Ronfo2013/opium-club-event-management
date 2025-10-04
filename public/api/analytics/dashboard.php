<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestisce le richieste OPTIONS per CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verifica che la richiesta sia GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
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

    // Ottiene il range di tempo
    $range = $_GET['range'] ?? '7d';
    $dateCondition = getDateCondition($range);

    // Statistiche principali
    $stats = getMainStats($pdo, $dateCondition);
    
    // Top pages
    $topPages = getTopPages($pdo, $dateCondition);
    
    // Top events
    $topEvents = getTopEvents($pdo, $dateCondition);
    
    // Statistiche dispositivi
    $deviceStats = getDeviceStats($pdo, $dateCondition);

    $result = [
        'stats' => $stats,
        'topPages' => $topPages,
        'topEvents' => $topEvents,
        'deviceStats' => $deviceStats,
        'range' => $range
    ];

    echo json_encode($result);

} catch (Exception $e) {
    error_log("Errore dashboard analytics: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno del server']);
}

function getDateCondition($range) {
    $now = new DateTime();
    
    switch ($range) {
        case '1d':
            $start = $now->modify('-1 day');
            break;
        case '7d':
            $start = $now->modify('-7 days');
            break;
        case '30d':
            $start = $now->modify('-30 days');
            break;
        case '90d':
            $start = $now->modify('-90 days');
            break;
        default:
            $start = $now->modify('-7 days');
    }
    
    return $start->format('Y-m-d H:i:s');
}

function getMainStats($pdo, $dateCondition) {
    // Utenti unici
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) as unique_users 
        FROM analytics_sessions 
        WHERE start_time >= ?
    ");
    $stmt->execute([$dateCondition]);
    $uniqueUsers = $stmt->fetch()['unique_users'] ?? 0;

    // Visualizzazioni pagine
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as page_views 
        FROM analytics_page_views 
        WHERE timestamp >= ?
    ");
    $stmt->execute([$dateCondition]);
    $pageViews = $stmt->fetch()['page_views'] ?? 0;

    // Eventi totali
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_events 
        FROM analytics_events 
        WHERE timestamp >= ?
    ");
    $stmt->execute([$dateCondition]);
    $totalEvents = $stmt->fetch()['total_events'] ?? 0;

    // Durata media sessione
    $stmt = $pdo->prepare("
        SELECT AVG(duration) as avg_duration 
        FROM analytics_sessions 
        WHERE start_time >= ? AND duration > 0
    ");
    $stmt->execute([$dateCondition]);
    $avgDuration = $stmt->fetch()['avg_duration'] ?? 0;

    return [
        'uniqueUsers' => (int)$uniqueUsers,
        'pageViews' => (int)$pageViews,
        'totalEvents' => (int)$totalEvents,
        'avgSessionDuration' => (int)$avgDuration
    ];
}

function getTopPages($pdo, $dateCondition) {
    $stmt = $pdo->prepare("
        SELECT page, COUNT(*) as views 
        FROM analytics_page_views 
        WHERE timestamp >= ? 
        GROUP BY page 
        ORDER BY views DESC 
        LIMIT 10
    ");
    $stmt->execute([$dateCondition]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTopEvents($pdo, $dateCondition) {
    $stmt = $pdo->prepare("
        SELECT event_name, COUNT(*) as count 
        FROM analytics_events 
        WHERE timestamp >= ? 
        GROUP BY event_name 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stmt->execute([$dateCondition]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDeviceStats($pdo, $dateCondition) {
    // Conta i dispositivi basandosi sulla risoluzione dello schermo
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN screen_resolution LIKE '%x%' AND CAST(SUBSTRING_INDEX(screen_resolution, 'x', 1) AS UNSIGNED) < 768 THEN 1 ELSE 0 END) as mobile,
            SUM(CASE WHEN screen_resolution LIKE '%x%' AND CAST(SUBSTRING_INDEX(screen_resolution, 'x', 1) AS UNSIGNED) BETWEEN 768 AND 1024 THEN 1 ELSE 0 END) as tablet,
            SUM(CASE WHEN screen_resolution LIKE '%x%' AND CAST(SUBSTRING_INDEX(screen_resolution, 'x', 1) AS UNSIGNED) > 1024 THEN 1 ELSE 0 END) as desktop
        FROM analytics_sessions 
        WHERE start_time >= ? AND screen_resolution IS NOT NULL
    ");
    $stmt->execute([$dateCondition]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = $result['mobile'] + $result['tablet'] + $result['desktop'];
    
    if ($total > 0) {
        return [
            'mobile' => round(($result['mobile'] / $total) * 100, 1),
            'tablet' => round(($result['tablet'] / $total) * 100, 1),
            'desktop' => round(($result['desktop'] / $total) * 100, 1)
        ];
    }
    
    return ['mobile' => 0, 'tablet' => 0, 'desktop' => 0];
}
?>
