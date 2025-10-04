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
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Leggi i dati JSON
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $eventId = $input['event_id'] ?? null;
        
        // Per la creazione non serve l'ID evento
        if ($action !== 'create' && !$eventId) {
            echo json_encode(['success' => false, 'message' => 'ID evento obbligatorio']);
            exit;
        }
        
        switch ($action) {
            case 'delete':
                // Elimina evento
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
                $stmt->execute([':id' => $eventId]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Evento eliminato con successo'
                ]);
                break;
                
            case 'close':
                // Chiudi evento
                $stmt = $pdo->prepare("UPDATE events SET chiuso = 1 WHERE id = :id");
                $stmt->execute([':id' => $eventId]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Evento chiuso con successo'
                ]);
                break;
                
            case 'reopen':
                // Riapri evento
                $stmt = $pdo->prepare("UPDATE events SET chiuso = 0 WHERE id = :id");
                $stmt->execute([':id' => $eventId]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Evento riaperto con successo'
                ]);
                break;
                
            case 'create':
                // Crea nuovo evento
                $titolo = $input['titolo'] ?? '';
                $event_date = $input['event_date'] ?? '';
                $background_image = $input['background_image'] ?? null;
                
                if (empty($titolo) || empty($event_date)) {
                    echo json_encode(['success' => false, 'message' => 'Titolo e data sono obbligatori']);
                    exit;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO events (titolo, event_date, background_image, chiuso, created_at) 
                    VALUES (:titolo, :event_date, :background_image, 0, NOW())
                ");
                $stmt->execute([
                    ':titolo' => $titolo,
                    ':event_date' => $event_date,
                    ':background_image' => $background_image
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Evento creato con successo',
                    'event_id' => $pdo->lastInsertId()
                ]);
                break;
                
            case 'update':
                // Aggiorna evento
                $titolo = $input['titolo'] ?? '';
                $event_date = $input['event_date'] ?? '';
                $background_image = $input['background_image'] ?? null;
                $chiuso = $input['chiuso'] ?? 0;
                
                if (empty($titolo) || empty($event_date)) {
                    echo json_encode(['success' => false, 'message' => 'Titolo e data sono obbligatori']);
                    exit;
                }
                
                $stmt = $pdo->prepare("
                    UPDATE events 
                    SET titolo = :titolo, event_date = :event_date, background_image = :background_image, chiuso = :chiuso, updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':titolo' => $titolo,
                    ':event_date' => $event_date,
                    ':background_image' => $background_image,
                    ':chiuso' => $chiuso,
                    ':id' => $eventId
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Evento aggiornato con successo'
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Azione non supportata']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Metodo non supportato']);
    }
    
} catch (Exception $e) {
    error_log('Event actions API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Errore del server: ' . $e->getMessage()
    ]);
}
?>
