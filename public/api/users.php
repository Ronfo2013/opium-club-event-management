<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
        // Recupera tutti gli utenti con informazioni evento
        $stmt = $pdo->query("
            SELECT u.*, e.titolo as evento_titolo, e.event_date 
            FROM utenti u 
            LEFT JOIN events e ON u.evento = e.id 
            ORDER BY u.created_at DESC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $users
        ]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Leggi i dati JSON
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'delete') {
            $userId = $input['user_id'] ?? null;
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'ID utente mancante']);
                exit;
            }
            
            // Elimina utente
            $stmt = $pdo->prepare("DELETE FROM utenti WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Utente eliminato con successo'
            ]);
        } elseif ($action === 'validate') {
            $userId = $input['user_id'] ?? null;
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'ID utente mancante']);
                exit;
            }
            
            // Valida utente
            $stmt = $pdo->prepare("UPDATE utenti SET validato = 1, validated_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Utente validato con successo'
            ]);
        } elseif ($action === 'reject') {
            $userId = $input['user_id'] ?? null;
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'ID utente mancante']);
                exit;
            }
            
            // Rifiuta utente
            $stmt = $pdo->prepare("UPDATE utenti SET validato = 0 WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Utente rifiutato'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Azione non supportata']);
        }
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





