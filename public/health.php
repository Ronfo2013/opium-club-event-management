<?php
/**
 * Health check endpoint per Google Cloud
 */

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'version' => '1.0.0',
    'checks' => []
];

// Check database connection
try {
    // Carica il bootstrap appropriato per l'ambiente
    if (isset($_SERVER['GAE_APPLICATION'])) {
        // Ambiente Google Cloud
        $bootstrap = require_once __DIR__ . '/../src/bootstrap_gcloud.php';
    } else {
        // Ambiente locale
        $bootstrap = require_once __DIR__ . '/../src/bootstrap.php';
    }
    $pdo = $bootstrap['db'];
    $stmt = $pdo->query("SELECT 1");
    $health['checks']['database'] = 'ok';
} catch (Exception $e) {
    $health['checks']['database'] = 'error: ' . $e->getMessage();
    $health['status'] = 'error';
}

// Check file permissions: usa percorsi da configurazione quando disponibili
$cfgPaths = $bootstrap['config']['paths'] ?? [];
$directories = [
    'qrcodes' => $cfgPaths['qr_code_dir'] ?? (__DIR__ . '/qrcodes/'),
    'generated_images' => $cfgPaths['generated_images_dir'] ?? (__DIR__ . '/generated_images/'),
    'generated_pdfs' => $cfgPaths['generated_pdfs_dir'] ?? (__DIR__ . '/generated_pdfs/'),
    'uploads' => $cfgPaths['uploads_dir'] ?? (__DIR__ . '/uploads/')
];

foreach ($directories as $name => $path) {
    if (is_dir($path) && is_writable($path)) {
        $health['checks'][$name . '_permissions'] = 'ok';
    } else {
        $health['checks'][$name . '_permissions'] = 'error: not writable';
        $health['status'] = 'error';
    }
}

// Check PHP extensions
$required_extensions = ['pdo', 'pdo_mysql', 'gd', 'mbstring', 'json'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        $health['checks']['php_' . $ext] = 'ok';
    } else {
        $health['checks']['php_' . $ext] = 'error: missing';
        $health['status'] = 'error';
    }
}

http_response_code($health['status'] === 'ok' ? 200 : 500);
echo json_encode($health, JSON_PRETTY_PRINT);
?>
