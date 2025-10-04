<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// require_once __DIR__ . '/../vendor/autoload.php'; // Commentato per compatibilità

// Carica variabili ambiente da file .env se presente
if (class_exists('Dotenv\Dotenv')) {
    $projectRoot = dirname(__DIR__);
    $envFile = $projectRoot . '/.env';
    if (is_readable($envFile)) {
        Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();
    }
}

// Rilevamento automatico ambiente Google Cloud
if (isset($_SERVER['GAE_APPLICATION'])) {
    // Ambiente Google Cloud - usa config_gcloud.php
    $configPath = realpath(__DIR__ . '/config/config_gcloud.php');
} else {
    // Ambiente locale - usa config.php
    $configPath = realpath(__DIR__ . '/config/config.php');
}

if (!$configPath || !file_exists($configPath)) {
    die('Impossibile trovare il file di configurazione.');
}

$config = require $configPath;

// Gestione errori in base all'ambiente
$isGae = isset($_SERVER['GAE_APPLICATION']);
$appDebug = false;
if (isset($config['app']['debug'])) {
    $appDebug = (bool)$config['app']['debug'];
} elseif (isset($_ENV['APP_DEBUG'])) {
    $appDebug = filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN);
}

if ($isGae) {
    // Su App Engine non mostrare errori a video per evitare "headers already sent"
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', 'php://stderr');
    error_reporting($appDebug ? E_ALL : (E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING));
} else {
    // In locale rispetta APP_DEBUG, default dettagliato
    ini_set('display_errors', $appDebug ? '1' : '0');
    ini_set('display_startup_errors', $appDebug ? '1' : '0');
    error_reporting($appDebug ? E_ALL : (E_ALL & ~E_DEPRECATED & ~E_NOTICE));
}

// Inizializzo la connessione al database
try {
    // Rileva se è Google Cloud dal socket path
    if (strpos($config['db']['host'], '/cloudsql/') !== false) {
        // Google Cloud - usa Unix Socket
        $dsn = sprintf(
            'mysql:unix_socket=%s;dbname=%s;charset=utf8mb4',
            $config['db']['unix_socket'] ?? $config['db']['host'],
            $config['db']['dbname']
        );
    } else {
        // Ambiente tradizionale - usa TCP
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['db']['host'],
            $config['db']['port'],
            $config['db']['dbname']
        );
    }
    
    $pdo = new PDO($dsn, $config['db']['username'], $config['db']['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Test connessione
    $pdo->query('SELECT 1');
    
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Connection failed: ' . $e->getMessage());
}

// Creo eventuali directory necessarie
if ($isGae) {
    // Google Cloud - crea directory temporanee
    $tempDirs = ['/tmp/qrcodes', '/tmp/uploads', '/tmp/generated_images', '/tmp/generated_pdfs'];
    foreach ($tempDirs as $dir) {
        if (!file_exists($dir)) {
            // Evita warning in output in caso di race/permessi
            @mkdir($dir, 0777, true);
        }
    }
} else {
    // Ambiente locale - crea directory pubbliche
    if (!empty($config['paths']) && is_array($config['paths'])) {
        foreach ($config['paths'] as $path) {
            if (!file_exists($path)) {
                @mkdir($path, 0777, true);
            }
        }
    }
}

// Restituisco configurazione e connessione
return [
    'db' => $pdo,
    'config' => $config
];
