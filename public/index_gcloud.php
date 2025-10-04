<?php
/**
 * Entry point ottimizzato per Google Cloud Platform
 * Sostituisce index.php per l'ambiente di produzione
 */

// Configurazione per Google Cloud
ini_set('log_errors', 1);
ini_set('error_log', 'php://stderr');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// Verifica se siamo su Google Cloud
if (isset($_SERVER['GAE_APPLICATION'])) {
    $bootstrap = require_once __DIR__ . '/../src/bootstrap_gcloud.php';
} else {
    $bootstrap = require_once __DIR__ . '/../src/bootstrap.php';
}

// Ricava l'URI
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Rimuovi eventuali query string
$path = strtok($request_uri, '?');
// Normalizza eventuale trailing slash (mantiene root "/")
$normalized = rtrim($path, '/');
if ($normalized === '') { $normalized = '/'; }

// Gestione delle rotte
switch ($normalized) {
    case '/admin.php':
        header('Location: /admin', true, 307); // preserva il metodo
        exit;
    case '/login.php':
        header('Location: /login', true, 301);
        exit;
    case '/':
    case '':
        // Carica eventi per la home page (sistema originale)
        echo '<script>console.log("[DEBUG index_gcloud.php] Inizio caricamento eventi");</script>';
        try {
            $now = new DateTime();
            $oggi = $now->format('Y-m-d');
            $oraAttuale = $now->format('H:i:s');
            
            echo '<script>console.log("[DEBUG index_gcloud.php] Data oggi: ' . $oggi . ', Ora: ' . $oraAttuale . '");</script>';

            // Prima verifica se la tabella events esiste
            $checkTable = $bootstrap['db']->query("SHOW TABLES LIKE 'events'");
            if ($checkTable->rowCount() == 0) {
                echo '<script>console.log("[DEBUG index_gcloud.php] ERRORE: Tabella events non esiste!");</script>';
                $events = [];
            } else {
                echo '<script>console.log("[DEBUG index_gcloud.php] Tabella events trovata");</script>';
                
                $stmt = $bootstrap['db']->prepare("
                    SELECT id, event_date, titolo, chiuso 
                    FROM events 
                    WHERE 
                        event_date > ? OR 
                        (event_date = ? AND ? < '23:00:00')
                    ORDER BY event_date ASC
                ");
                $stmt->execute([$oggi, $oggi, $oraAttuale]);
                $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo '<script>console.log("[DEBUG index_gcloud.php] Eventi caricati: ' . count($events) . '");</script>';
                echo '<script>console.log("[DEBUG index_gcloud.php] Eventi:", ' . json_encode($events) . ');</script>';
            }
        } catch (PDOException $e) {
            echo '<script>console.log("[DEBUG index_gcloud.php] Errore caricamento eventi: ' . addslashes($e->getMessage()) . '");</script>';
            $events = [];
        }
        
        // Carica la configurazione per la view
        $config = $bootstrap['config'];
        require __DIR__ . '/../src/Views/form.php';
        break;

    case '/admin':
        // Usa l'admin completo legacy finché non unifichiamo le feature
        require __DIR__ . '/admin.php';
        break;

    case '/login':
        $controller = new App\Controllers\AuthController($bootstrap['db'], $bootstrap['config']);
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $controller->login();
        } else {
            $controller->showLoginPage();
        }
        break;

    case '/logout':
        $controller = new App\Controllers\AuthController($bootstrap['db'], $bootstrap['config']);
        $controller->logout();
        break;

    case '/save-form':
        require __DIR__ . '/save_form.php';
        break;

    case '/validate':
        $controller = new App\Controllers\ValidationController($bootstrap['db'], $bootstrap['config']);
        $token = $_GET['token'] ?? '';
        $controller->validateToken($token);
        break;

    case '/ajax_user_events.php':
    case '/ajax_user_events':
        // Carica lo storico eventi utente dal pannello admin
        require __DIR__ . '/ajax_user_events.php';
        break;

    case '/health':
        // Health check endpoint per Google Cloud
        header('Content-Type: application/json');
        $health = [
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'environment' => isset($_SERVER['GAE_APPLICATION']) ? 'google-cloud' : 'local'
        ];
        echo json_encode($health);
        break;

    case '/scanner.html':
    case '/scanner':
        // Scanner QR code
        header('Content-Type: text/html; charset=utf-8');
        require __DIR__ . '/scanner.html';
        break;

    default:
        header('HTTP/1.0 404 Not Found');
        echo '404 Not Found';
        break;
}
