<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Connessione diretta al database
    $host = 'mysql';
    $dbname = 'opium_events';
    $username = 'root';
    $password = 'docker_password';
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Statistiche generali
        $stats = [];
        
        // Totale utenti
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM utenti");
        $stats['total_users'] = $stmt->fetch()['total'];
        
        // Totale eventi
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM events");
        $stats['total_events'] = $stmt->fetch()['total'];
        
        // Utenti validati
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM utenti WHERE validato = 1");
        $stats['validated_users'] = $stmt->fetch()['total'];
        
        // Utenti non validati
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM utenti WHERE validato = 0");
        $stats['pending_users'] = $stmt->fetch()['total'];
        
        // Statistiche per evento
        $stmt = $pdo->query("
            SELECT 
                e.id,
                e.titolo,
                e.event_date,
                COUNT(u.id) as total_registrations,
                SUM(CASE WHEN u.validato = 1 THEN 1 ELSE 0 END) as validated_count,
                SUM(CASE WHEN u.validato = 0 THEN 1 ELSE 0 END) as pending_count
            FROM events e
            LEFT JOIN utenti u ON e.id = u.evento
            GROUP BY e.id, e.titolo, e.event_date
            ORDER BY e.event_date DESC
        ");
        $stats['events_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcola tasso di presenza per ogni evento
        foreach ($stats['events_stats'] as &$event) {
            if ($event['total_registrations'] > 0) {
                $event['attendance_rate'] = round(($event['validated_count'] / $event['total_registrations']) * 100, 2);
            } else {
                $event['attendance_rate'] = 0;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Metodo non supportato'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore del server: ' . $e->getMessage()
    ]);
}
?>





