<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Metodo non supportato'
    ]);
    exit;
}

// Carica il bootstrap
$bootstrap = require_once __DIR__ . '/../../src/bootstrap.php';

if (!$bootstrap || !isset($bootstrap['db'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore di configurazione del database'
    ]);
    exit;
}

try {
    $db = $bootstrap['db'];
    
    // Leggi i dati JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['qr_code']) || empty($input['qr_code'])) {
        echo json_encode([
            'success' => false,
            'message' => 'QR code mancante'
        ]);
        exit;
    }
    
    $qrCode = $input['qr_code'];
    
    // Cerca l'utente per token (QR code)
    $stmt = $db->prepare("
        SELECT u.*, e.title as evento_title 
        FROM utenti u 
        LEFT JOIN events e ON u.evento = e.id 
        WHERE u.token = ?
    ");
    $stmt->execute([$qrCode]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'QR code non valido'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $user
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore del server: ' . $e->getMessage()
    ]);
}
?>
