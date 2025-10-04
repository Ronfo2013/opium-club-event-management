<?php
// Proxy per servire immagini con intestazioni CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 3600');

// Gestisci preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verifica che sia stata specificata un'immagine
if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'File parameter required']);
    exit();
}

$fileName = basename($_GET['file']); // Sicurezza: solo nome file
$filePath = __DIR__ . '/uploads/' . $fileName;

// Verifica che il file esista
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit();
}

// Verifica che sia un'immagine
$imageInfo = getimagesize($filePath);
if (!$imageInfo) {
    http_response_code(400);
    echo json_encode(['error' => 'Not a valid image file']);
    exit();
}

// Imposta il tipo di contenuto appropriato
$mimeType = $imageInfo['mime'];
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=3600'); // Cache per 1 ora

// Servi il file
readfile($filePath);
?>





