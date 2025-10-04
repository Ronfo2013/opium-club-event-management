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
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Leggi i dati JSON
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['email']) || !isset($input['password'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Email e password sono obbligatori'
            ]);
            exit;
        }
        
        $email = $input['email'];
        $password = $input['password'];
        
        // Verifica credenziali (per ora usiamo la password hardcoded 'Cami')
        if ($password === 'Cami') {
            // Genera un token semplice
            $token = 'admin_token_' . time() . '_' . rand(1000, 9999);
            
            echo json_encode([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => 1,
                    'name' => 'Admin',
                    'email' => $email,
                    'role' => 'admin'
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Credenziali non valide'
            ]);
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





