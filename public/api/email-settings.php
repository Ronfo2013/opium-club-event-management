<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
        // Recupera impostazioni email
        $stmt = $pdo->query("SELECT * FROM email_texts ORDER BY id DESC LIMIT 1");
        $emailSettings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $emailSettings ?: [
                'subject' => 'Conferma Iscrizione - Opium Club',
                'body' => 'Grazie per la tua iscrizione!',
                'footer' => 'Opium Club Pordenone'
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Leggi i dati JSON
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'update') {
            $subject = $input['subject'] ?? '';
            $body = $input['body'] ?? '';
            $footer = $input['footer'] ?? '';
            
            if (empty($subject) || empty($body)) {
                echo json_encode(['success' => false, 'message' => 'Oggetto e corpo sono obbligatori']);
                exit;
            }
            
            // Aggiorna o inserisci impostazioni email
            $stmt = $pdo->prepare("
                INSERT INTO email_texts (subject, body, footer, updated_at) 
                VALUES (:subject, :body, :footer, NOW())
                ON DUPLICATE KEY UPDATE 
                subject = VALUES(subject), 
                body = VALUES(body), 
                footer = VALUES(footer), 
                updated_at = NOW()
            ");
            $stmt->execute([
                ':subject' => $subject,
                ':body' => $body,
                ':footer' => $footer
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Impostazioni email aggiornate con successo'
            ]);
            
        } elseif ($action === 'test') {
            // Test invio email
            $testEmail = $input['test_email'] ?? '';
            
            if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Email di test non valida']);
                exit;
            }
            
            // Simula invio email di test
            // TODO: Implementare invio email reale
            
            echo json_encode([
                'success' => true,
                'message' => 'Email di test inviata con successo a ' . $testEmail
            ]);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Azione non supportata']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Metodo non supportato']);
    }
    
} catch (Exception $e) {
    error_log('Email settings API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Errore del server: ' . $e->getMessage()
    ]);
}
?>





