<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

echo json_encode([
    'success' => true,
    'message' => 'Debug API working',
    'timestamp' => date('Y-m-d H:i:s'),
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown'
]);

// Test bootstrap loading
try {
    $bootstrap = require_once __DIR__ . '/../../src/bootstrap.php';
    echo json_encode([
        'success' => true,
        'bootstrap_loaded' => isset($bootstrap),
        'db_available' => isset($bootstrap['db']),
        'config_available' => isset($bootstrap['config'])
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
