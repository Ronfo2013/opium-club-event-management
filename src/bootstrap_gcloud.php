<?php
/**
 * Bootstrap per Google Cloud Platform
 * Configurazione ottimizzata per App Engine
 */

// Autoload Composer (necessario per Controllers e librerie)
require_once __DIR__ . '/../vendor/autoload.php';

// Configurazione errori per produzione
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php://stderr');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// Configurazione per Google Cloud
if (isset($_SERVER['GAE_APPLICATION'])) {
    // Siamo su Google App Engine
    $config = require __DIR__ . '/config/config_gcloud.php';
} else {
    // Ambiente locale
    $config = require __DIR__ . '/config/config.php';
}

// Configurazione database per Cloud SQL
try {
    $dsn = "mysql:unix_socket={$config['db']['host']};dbname={$config['db']['dbname']};charset=utf8mb4";
    
    $pdo = new PDO($dsn, $config['db']['username'], $config['db']['password'], $config['db']['options']);
    
    // Test connessione
    $pdo->query("SELECT 1");
    
} catch (PDOException $e) {
    // Fallback per ambiente locale o errori di connessione
    try {
        $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db']['username'], $config['db']['password'], $config['db']['options']);
    } catch (PDOException $e2) {
        error_log("Database connection failed: " . $e2->getMessage());
        http_response_code(500);
        die("Database connection failed");
    }
}

// Configurazione per Google Cloud Storage (opzionale)
if (isset($_SERVER['GAE_APPLICATION'])) {
    // Usa Google Cloud Storage per file statici
    $config['storage'] = [
        'bucket' => $_ENV['GCS_BUCKET'] ?? 'your-bucket-name',
        'public_url' => 'https://storage.googleapis.com/your-bucket-name'
    ];
}

// Crea directory temporanee su Google Cloud
if (isset($_SERVER['GAE_APPLICATION'])) {
    $tempDirs = ['/tmp/qrcodes', '/tmp/uploads', '/tmp/generated_images', '/tmp/generated_pdfs'];
    foreach ($tempDirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}

// Configurazione cache per App Engine
if (function_exists('apcu_enabled') && apcu_enabled()) {
    $config['cache'] = [
        'driver' => 'apcu',
        'prefix' => 'mrcharlie_'
    ];
} else {
    $config['cache'] = [
        'driver' => 'file',
        'path' => sys_get_temp_dir() . '/mrcharlie_cache'
    ];
}

// Configurazione logging per Google Cloud
$config['logging'] = [
    'level' => $config['app']['debug'] ? 'debug' : 'info',
    'driver' => 'stack',
    'channels' => ['stderr', 'file']
];

return [
    'db' => $pdo,
    'config' => $config
];
