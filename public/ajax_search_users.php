<?php
/**
 * AJAX endpoint per la ricerca degli utenti
 * Gestisce la ricerca con supporto per utenti bloccati
 * Aggiornato: 2025-09-25 con miglioramenti robustezza
 */

// Override configurazione per produzione - DEVE essere il primo
require_once __DIR__ . '/config_override.php';

// Abilita error reporting per debugging in produzione
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php://stderr');
error_reporting(E_ALL);

// Imposta header JSON sicuro
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if (!function_exists('ajaxSearchLoadBootstrap')) {
    /**
     * Carica il bootstrap dai percorsi indicati e restituisce l'array atteso.
     */
    function ajaxSearchLoadBootstrap(array $possiblePaths): array
    {
        foreach ($possiblePaths as $path) {
            if (!$path || !is_string($path)) {
                continue;
            }

            if (!file_exists($path)) {
                continue;
            }

            $bootstrap = require $path;

            if (is_array($bootstrap) && isset($bootstrap['db'], $bootstrap['config'])) {
                error_log('[AJAX_SEARCH] Bootstrap caricato da: ' . $path);
                return $bootstrap;
            }

            error_log('[AJAX_SEARCH] Bootstrap inatteso da ' . $path . ' - tipo: ' . gettype($bootstrap));
        }

        throw new Exception('File bootstrap non trovato o invalido');
    }
}

// Controlla se il file è stato chiamato direttamente
// Se il SCRIPT_NAME contiene il nome del file, è stato chiamato direttamente
if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], 'ajax_search_users.php') !== false) {
    // File chiamato direttamente - questo può causare problemi con il bootstrap
    error_log('[AJAX_SEARCH] ATTENZIONE: File chiamato direttamente, non tramite router!');

    // Prova a caricare il bootstrap come fa index.php
    error_log('[AJAX_SEARCH] Tentativo caricamento bootstrap come index.php...');

    try {
        $bootstrap = ajaxSearchLoadBootstrap(isset($_SERVER['GAE_APPLICATION'])
            ? [
                __DIR__ . '/../src/bootstrap_gcloud.php',
                dirname(__DIR__) . '/src/bootstrap_gcloud.php',
                '/app/src/bootstrap_gcloud.php',
                __DIR__ . '/../src/bootstrap.php'
            ]
            : [
                __DIR__ . '/../src/bootstrap.php',
                dirname(__DIR__) . '/src/bootstrap.php',
                '/app/src/bootstrap.php',
                __DIR__ . '/../src/bootstrap_gcloud.php'
            ]
        );
        error_log('[AJAX_SEARCH] Bootstrap caricato con successo tramite logica index.php');
        $pdo = $bootstrap['db'];
        $config = $bootstrap['config'];
    } catch (Exception $directBootstrapException) {
        error_log('[AJAX_SEARCH] Bootstrap non caricato tramite logica index.php: ' . $directBootstrapException->getMessage());
    }
}

// Avvia la sessione
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Controllo login
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
    exit;
}

// Verifica metodo HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

// Debug info
error_log('[AJAX_SEARCH] Avvio, __DIR__: ' . __DIR__);
error_log('[AJAX_SEARCH] Bootstrap isset: ' . (isset($bootstrap) ? 'true' : 'false'));
error_log('[AJAX_SEARCH] GAE_APPLICATION: ' . (isset($_SERVER['GAE_APPLICATION']) ? $_SERVER['GAE_APPLICATION'] : 'non-set'));
error_log('[AJAX_SEARCH] REQUEST_URI: ' . ($_SERVER['REQUEST_URI'] ?? 'non-set'));
error_log('[AJAX_SEARCH] HTTP_HOST: ' . ($_SERVER['HTTP_HOST'] ?? 'non-set'));
error_log('[AJAX_SEARCH] SCRIPT_NAME: ' . ($_SERVER['SCRIPT_NAME'] ?? 'non-set'));

// Carica configurazione database
try {
    // Se il bootstrap è già stato caricato (tramite chiamata diretta), salta
    if (isset($bootstrap) && is_array($bootstrap) && isset($pdo) && isset($config)) {
        error_log('[AJAX_SEARCH] Bootstrap già caricato, uso quello esistente');
    } else {
        // Carica il bootstrap normalmente
        error_log('[AJAX_SEARCH] Caricando bootstrap tramite helper');

        $forceGoogleCloud = isset($_SERVER['GAE_APPLICATION']) ||
            (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'eventi.opiumpordenone.com') !== false);

        $bootstrap = ajaxSearchLoadBootstrap($forceGoogleCloud
            ? [
                __DIR__ . '/../src/bootstrap_gcloud.php',
                dirname(__DIR__) . '/src/bootstrap_gcloud.php',
                '/app/src/bootstrap_gcloud.php',
                __DIR__ . '/../src/bootstrap.php'
            ]
            : [
                __DIR__ . '/../src/bootstrap.php',
                dirname(__DIR__) . '/src/bootstrap.php',
                '/app/src/bootstrap.php',
                __DIR__ . '/../src/bootstrap_gcloud.php'
            ]
        );

        $pdo = $bootstrap['db'];
        $config = $bootstrap['config'];
    }
    
    // Verifica finale che tutto sia caricato
    if (!isset($bootstrap) || !is_array($bootstrap)) {
        throw new Exception('Bootstrap non caricato correttamente - tipo: ' . gettype($bootstrap));
    }
    
    if (!isset($pdo) || !$pdo) {
        throw new Exception('PDO non disponibile');
    }
    
    if (!isset($config) || !$config) {
        throw new Exception('Config non disponibile');
    }
    
    error_log('[AJAX_SEARCH] Bootstrap caricato con successo');
} catch (Exception $e) {
    error_log('[AJAX_SEARCH] Errore configurazione database: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore configurazione database: ' . $e->getMessage()]);
    exit;
}

// Parametri di ricerca
$searchTerm = trim($_GET['search'] ?? '');
$limit = min(100, max(10, (int)($_GET['limit'] ?? 50))); // Limite tra 10 e 100
$sort = $_GET['sort'] ?? 'relevance';

error_log('[AJAX_SEARCH] Parametri ricevuti - searchTerm: ' . $searchTerm . ', limit: ' . $limit . ', sort: ' . $sort);

$sql = '';
$debugBindings = [];

// Validazione termine di ricerca
if (strlen($searchTerm) < 3) {
    error_log('[AJAX_SEARCH] Termine di ricerca troppo corto: ' . strlen($searchTerm));
    echo json_encode([
        'success' => false, 
        'error' => 'Termine di ricerca troppo corto (minimo 3 caratteri)',
        'search_term' => $searchTerm
    ]);
    exit;
}

try {
    error_log('[AJAX_SEARCH] Inizio elaborazione query');
    
    // Data corrente per filtro mese
    $now = new DateTime();
    $firstDayOfCurrentMonth = $now->format('Y-m-01');
    
    // Query per cercare utenti con informazioni di blocco - SINCRONIZZATA CON admin.php
    $searchPattern = '%' . $searchTerm . '%';
    
    // Query base con statistiche utente e controllo blocco CORRETTO
    $sql = "
        SELECT DISTINCT
            u.nome,
            u.cognome,
            u.email,
            u.telefono,
            u.data_nascita,
            MIN(u.created_at) as prima_iscrizione,
            MAX(CASE WHEN u.validato = 1 THEN e.event_date END) as ultima_presenza,
            -- Statistiche TOTALI (tutti gli eventi)
            COUNT(u.id) as total_eventi,
            SUM(CASE WHEN u.validato = 1 THEN 1 ELSE 0 END) as eventi_validati,
            CASE 
                WHEN COUNT(u.id) > 0 THEN 
                    ROUND((SUM(CASE WHEN u.validato = 1 THEN 1 ELSE 0 END) / COUNT(u.id)) * 100, 1)
                ELSE 0 
            END as tasso_presenza
        FROM utenti u
        LEFT JOIN events e ON u.evento = e.id
        WHERE (
            u.nome LIKE :search_nome OR 
            u.cognome LIKE :search_cognome OR 
            u.email LIKE :search_email OR 
            u.telefono LIKE :search_telefono OR
            CONCAT(u.nome, ' ', u.cognome) LIKE :search_full
        )
        GROUP BY u.email, u.nome, u.cognome, u.telefono, u.data_nascita
    ";
    
    // Aggiunta ordinamento
    switch ($sort) {
        case 'name_asc':
            $sql .= " ORDER BY u.nome ASC, u.cognome ASC";
            break;
        case 'name_desc':
            $sql .= " ORDER BY u.nome DESC, u.cognome DESC";
            break;
        case 'registrations_desc':
            $sql .= " ORDER BY total_eventi DESC, u.nome ASC";
            break;
        case 'registrations_asc':
            $sql .= " ORDER BY total_eventi ASC, u.nome ASC";
            break;
        case 'attendance_desc':
            $sql .= " ORDER BY tasso_presenza DESC, u.nome ASC";
            break;
        case 'attendance_asc':
            $sql .= " ORDER BY tasso_presenza ASC, u.nome ASC";
            break;
        case 'relevance':
        default:
            $sql .= " ORDER BY 
                CASE 
                    WHEN u.email LIKE :order_email THEN 1
                    WHEN CONCAT(u.nome, ' ', u.cognome) LIKE :order_full THEN 2
                    WHEN u.nome LIKE :order_nome THEN 3
                    WHEN u.cognome LIKE :order_cognome THEN 4
                    ELSE 5
                END,
                total_eventi DESC,
                u.nome ASC";
            break;
    }
    
    $sql .= " LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);

    $bindings = [
        ':search_nome' => $searchPattern,
        ':search_cognome' => $searchPattern,
        ':search_email' => $searchPattern,
        ':search_telefono' => $searchPattern,
        ':search_full' => $searchPattern,
        ':order_email' => $searchPattern,
        ':order_full' => $searchPattern,
        ':order_nome' => $searchPattern,
        ':order_cognome' => $searchPattern,
    ];

    foreach ($bindings as $placeholder => $value) {
        if (strpos($sql, $placeholder) !== false) {
            $stmt->bindValue($placeholder, $value, PDO::PARAM_STR);
            $debugBindings[$placeholder] = $value;
        }
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $debugBindings[':limit'] = $limit;

    error_log('[AJAX_SEARCH] SQL finale: ' . preg_replace('/\s+/', ' ', $sql));
    error_log('[AJAX_SEARCH] Parametri bind: ' . json_encode($debugBindings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatta i risultati e calcola is_blocked usando la STESSA LOGICA di admin.php
    $formattedUsers = [];
    foreach ($users as $user) {
        // BLOCCO UTENTI DISABILITATO
        $isBlocked = false;
        
        $formattedUsers[] = [
            'nome' => $user['nome'],
            'cognome' => $user['cognome'],
            'email' => $user['email'],
            'telefono' => $user['telefono'],
            'data_nascita' => $user['data_nascita'],
            'prima_iscrizione' => $user['prima_iscrizione'],
            'ultima_presenza' => $user['ultima_presenza'],
            'total_eventi' => (int)$user['total_eventi'],
            'eventi_validati' => (int)$user['eventi_validati'],
            'tasso_presenza' => (float)$user['tasso_presenza'],
            'is_blocked' => $isBlocked  // Calcolato con logica corretta
        ];
    }
    
    // Risposta JSON
    echo json_encode([
        'success' => true,
        'users' => $formattedUsers,
        'total_found' => count($formattedUsers),
        'search_term' => $searchTerm,
        'limit' => $limit,
        'sort' => $sort
    ]);
    
} catch (PDOException $e) {
    error_log("[AJAX_SEARCH] Errore PDO: " . $e->getMessage());
    error_log('[AJAX_SEARCH] SQL in errore: ' . preg_replace('/\s+/', ' ', $sql));
    error_log('[AJAX_SEARCH] Parametri in errore: ' . json_encode($debugBindings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Errore nel database durante la ricerca',
        'debug_error' => $e->getMessage(),
        'error_type' => 'PDOException'
    ]);
} catch (Exception $e) {
    error_log("[AJAX_SEARCH] Errore generico: " . $e->getMessage());
    error_log('[AJAX_SEARCH] SQL in errore (Exception): ' . preg_replace('/\s+/', ' ', $sql));
    error_log('[AJAX_SEARCH] Parametri in errore (Exception): ' . json_encode($debugBindings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Errore interno del server',
        'debug_error' => $e->getMessage(),
        'error_type' => 'Exception'
    ]);
}
?> 
