<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo non supportato']);
    exit;
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
    
    // Leggi i dati JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validazione input
    $requiredFields = ['nome', 'cognome', 'email', 'telefono', 'data_nascita', 'evento'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            echo json_encode(['success' => false, 'message' => "Il campo $field è obbligatorio"]);
            exit;
        }
    }
    
    // Validazione email
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email non valida']);
        exit;
    }
    
    // Controlla se l'email esiste già per questo evento
    $stmt = $pdo->prepare("SELECT id FROM utenti WHERE email = :email AND evento = :evento");
    $stmt->execute([':email' => $input['email'], ':evento' => $input['evento']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email già registrata per questo evento']);
        exit;
    }
    
    // Genera token unico per QR code
    $token = 'OPIUM_' . uniqid() . '_' . time();
    
    // Prepara i dati per l'inserimento
    $userData = [
        'nome' => trim($input['nome']),
        'cognome' => trim($input['cognome']),
        'email' => trim($input['email']),
        'telefono' => trim($input['telefono']),
        'data_nascita' => $input['data_nascita'],
        'evento' => $input['evento'],
        'token' => $token,
        'validato' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Inserisci utente nel database
    $stmt = $pdo->prepare("
        INSERT INTO utenti (nome, cognome, email, telefono, data_nascita, evento, token, validato, created_at) 
        VALUES (:nome, :cognome, :email, :telefono, :data_nascita, :evento, :token, :validato, :created_at)
    ");
    
    if ($stmt->execute($userData)) {
        $userId = $pdo->lastInsertId();
        
        // Genera QR code
        $qrCodePath = "/var/www/html/public/qrcodes/qr_" . $userId . ".png";
        $qrCodeDir = dirname($qrCodePath);
        
        if (!file_exists($qrCodeDir)) {
            mkdir($qrCodeDir, 0777, true);
        }
        
        // Genera QR code usando phpqrcode se disponibile
        $qrLibPath = '/var/www/html/lib/phpqrcode/qrlib.php';
        if (file_exists($qrLibPath)) {
            require_once $qrLibPath;
            QRcode::png($token, $qrCodePath, QR_ECLEVEL_H, 10, 1);
        }
        
        // Aggiorna il percorso del QR code nel database
        $stmt = $pdo->prepare("UPDATE utenti SET qr_code_path = :qr_path WHERE id = :id");
        $stmt->execute([':qr_path' => $qrCodePath, ':id' => $userId]);
        
        // Recupera i dati dell'evento
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = :evento");
        $stmt->execute([':evento' => $input['evento']]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Invia email di conferma (simulato per ora)
        // TODO: Implementare invio email reale
        
        echo json_encode([
            'success' => true,
            'message' => 'Registrazione completata con successo!',
            'data' => [
                'user_id' => $userId,
                'token' => $token,
                'qr_code_path' => $qrCodePath,
                'event' => $event
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore durante la registrazione']);
    }
    
} catch (Exception $e) {
    error_log('Registration error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Errore del server: ' . $e->getMessage()
    ]);
}
?>





