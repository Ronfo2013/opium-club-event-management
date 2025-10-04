<?php
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/error_log.txt');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Carica il bootstrap appropriato per l'ambiente
if (isset($_SERVER['GAE_APPLICATION'])) {
    // Ambiente Google Cloud
    $bootstrap = require_once __DIR__ . '/../src/bootstrap_gcloud.php';
} else {
    // Ambiente locale
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
        // Usa l'admin completo legacy con tutte le funzionalità
        require __DIR__ . '/admin.php';
        break;
    case '/login.php':
        header('Location: /login', true, 307);
        exit;
    case '/':
    case '':
        // Carica eventi direttamente per la home page
        echo '<script>console.log("[DEBUG index.php] Inizio caricamento eventi");</script>';
        try {
            $now = new DateTime();
            $oggi = $now->format('Y-m-d');
            $oraAttuale = $now->format('H:i:s');
            
            echo '<script>console.log("[DEBUG index.php] Data oggi: ' . $oggi . ', Ora: ' . $oraAttuale . '");</script>';

            // Prima verifica se la tabella events esiste
            $checkTable = $bootstrap['db']->query("SHOW TABLES LIKE 'events'");
            if ($checkTable->rowCount() == 0) {
                echo '<script>console.log("[DEBUG index.php] ERRORE: Tabella events non esiste!");</script>';
                $events = [];
            } else {
                echo '<script>console.log("[DEBUG index.php] Tabella events trovata");</script>';
                
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
                echo '<script>console.log("[DEBUG index.php] Eventi caricati: ' . count($events) . '");</script>';
                echo '<script>console.log("[DEBUG index.php] Eventi:", ' . json_encode($events) . ');</script>';
            }
        } catch (PDOException $e) {
            echo '<script>console.log("[DEBUG index.php] Errore caricamento eventi: ' . addslashes($e->getMessage()) . '");</script>';
            $events = [];
        }
        
        // Espone la configurazione alla view
        $config = $bootstrap['config'];
        require __DIR__ . '/../src/Views/form.php';
        break;

    case '/admin':
        // Usa l'admin completo legacy con tutte le funzionalità
        require __DIR__ . '/admin.php';
        break;

    case '/login':
        require __DIR__ . '/login.php';
        break;

    case '/logout':
        require __DIR__ . '/logout.php';
        break;

    case '/save-form':
        require __DIR__ . '/save_form.php';
        break;
    case '/api/events':
        require __DIR__ . '/api/events.php';
        break;
    case '/api/validate-qr':
        require __DIR__ . '/api/validate-qr.php';
        break;

    case '/validate':
        $controller = new App\Controllers\ValidationController($bootstrap['db'], $bootstrap['config']);
        $token = $_GET['token'] ?? '';
        $controller->validateToken($token);
        break;

    case '/ajax_search_users.php':
    case '/ajax_search_users':
        // Gestisce la ricerca AJAX degli utenti
        require __DIR__ . '/ajax_search_users.php';
        break;

    case '/ajax_user_events.php':
    case '/ajax_user_events':
        // Carica lo storico eventi utente dal pannello admin
        require __DIR__ . '/ajax_user_events.php';
        break;

    case '/test_bootstrap':
        // Test del bootstrap per debugging
        require __DIR__ . '/test_bootstrap.php';
        break;

    case '/simple_test':
        // Test semplice del bootstrap
        require __DIR__ . '/simple_test.php';
        break;

    default:
        header('HTTP/1.0 404 Not Found');
        echo '404 Not Found';
        break;
}
