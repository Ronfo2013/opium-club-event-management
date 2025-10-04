<?php
// Configurazione errori per PRODUZIONE
ini_set('display_errors', 0);  // DISABILITATO per produzione
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php://stderr'); // Google Cloud logs

// Cattura errori fatali - DEBUG MODE
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $errorMsg = '[FATAL ERROR] ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'];
        error_log($errorMsg);
        echo json_encode([
            'success' => false, 
            'message' => 'Fatal Error: ' . $error['message'],
            'debug' => [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ]
        ]);
    }
});

// Cattura eccezioni non gestite - DEBUG MODE
set_exception_handler(function($exception) {
    $errorMsg = '[UNCAUGHT EXCEPTION] ' . $exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine();
    error_log($errorMsg);
    echo json_encode([
        'success' => false, 
        'message' => 'Exception: ' . $exception->getMessage(),
        'debug' => [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]
    ]);
});

// DEBUG: Conferma che il file si sta caricando (solo nei log)
error_log('[DEBUG] save-form-complete.php caricato alle ' . date('Y-m-d H:i:s'));

// Sicurezza: Configurazione codifica UTF-8
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Imposta header per risposta JSON
header('Content-Type: application/json; charset=utf-8');

// Sicurezza: Avvia sessione sicura
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Strict'
    ]);
}

// Sicurezza: Headers di sicurezza
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Sicurezza: Rate limiting semplice
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitFile = sys_get_temp_dir() . '/rate_limit_' . md5($clientIP);
$currentTime = time();

if (file_exists($rateLimitFile)) {
    $lastRequest = (int)file_get_contents($rateLimitFile);
    if (($currentTime - $lastRequest) < 2) { // Max 1 richiesta ogni 2 secondi
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Troppe richieste. Riprova tra qualche secondo.']);
        exit;
    }
}
file_put_contents($rateLimitFile, $currentTime);

// Sicurezza: Error handler che non espone informazioni sensibili
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Log l'errore senza esporlo
    error_log("Errore PHP: $errstr in $errfile:$errline");
    
    // Solo per errori critici, invia notifica (senza dettagli sensibili)
    if (!in_array($errno, [E_WARNING, E_DEPRECATED, E_NOTICE])) {
        $safeMessage = "Errore sistema - " . date('Y-m-d H:i:s');
        @mail('angelo.bernardini@gmail.com', 'Errore sistema form', $safeMessage);
    }
    return true; // Non mostrare l'errore all'utente
});

set_exception_handler(function ($exception) {
    error_log("Eccezione: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore interno del server']);
    exit;
});

// Sicurezza: Verifica metodo HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

// Carica configurazione e database
try {
    error_log('[DEBUG] Inizio processo iscrizione');
    
    $bootstrapPath = __DIR__ . '/../../src/bootstrap.php';
    error_log('[DEBUG] Bootstrap path: ' . $bootstrapPath);
    
    if (!file_exists($bootstrapPath)) {
        throw new Exception("File bootstrap.php non trovato in /src/. Verifica la struttura del progetto.");
    }
    
    $bootstrap = require_once $bootstrapPath;
    error_log('[DEBUG] Bootstrap caricato, keys: ' . implode(',', array_keys($bootstrap ?? [])));
    
    if (!isset($bootstrap['db']) || !isset($bootstrap['config'])) {
        error_log('[DEBUG] Bootstrap mancante db o config');
        throw new Exception("Bootstrap non ha restituito database o configurazione validi.");
    }
    
    $pdo = $bootstrap['db'];
    $config = $bootstrap['config'];
    
    // Sicurezza: Forza codifica UTF-8 per la connessione database
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
} catch (Exception $e) {
    error_log("Errore bootstrap: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore di configurazione']);
    exit;
}

// Sicurezza: Validazione rigorosa input
function validateAndSanitizeInput($input, $type, $maxLength = null) {
    if (empty($input)) {
        return null;
    }
    
    switch ($type) {
        case 'name':
            // Rimuovi solo spazi multipli e caratteri di controllo
            $sanitized = preg_replace('/\s+/', ' ', trim($input));
            $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $sanitized);
            
            if ($maxLength && mb_strlen($sanitized, 'UTF-8') > $maxLength) {
                throw new InvalidArgumentException("Campo troppo lungo (max {$maxLength} caratteri)");
            }
            
            if (mb_strlen($sanitized, 'UTF-8') < 2) {
                throw new InvalidArgumentException("Nome troppo corto (min 2 caratteri)");
            }
            
            // Pattern più permissivo per nomi internazionali
            if (!preg_match('/^[\p{L}\p{M}\s\'\-\.]{2,}$/u', $sanitized)) {
                throw new InvalidArgumentException("Nome contiene caratteri non validi. Usa solo lettere, spazi, apostrofi e trattini");
            }
            
            // Verifica che non sia solo spazi o caratteri speciali
            if (!preg_match('/\p{L}/u', $sanitized)) {
                throw new InvalidArgumentException("Il nome deve contenere almeno una lettera");
            }
            
            return $sanitized;
            
        case 'email':
            $sanitized = filter_var($input, FILTER_SANITIZE_EMAIL);
            if (!filter_var($sanitized, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Email non valida");
            }
            if (strlen($sanitized) > 254) {
                throw new InvalidArgumentException("Email troppo lunga");
            }
            return strtolower($sanitized);
            
        case 'phone':
            $sanitized = preg_replace('/[^\d+]/', '', $input);
            
            // Se non inizia con +, aggiungi +39
            if (!$sanitized || $sanitized[0] !== '+') {
                // Rimuovi solo il prefisso 39 se presente all'inizio (senza +)
                if (substr($sanitized, 0, 2) === '39') {
                    $sanitized = substr($sanitized, 2);
                }
                $sanitized = '+39' . $sanitized;
            }
            
            if (!preg_match('/^\+\d{1,3}\d{4,14}$/', $sanitized)) {
                throw new InvalidArgumentException("Numero di telefono non valido");
            }
            return $sanitized;
            
        case 'date':
            $date = DateTime::createFromFormat('Y-m-d', $input);
            if (!$date || $date->format('Y-m-d') !== $input) {
                throw new InvalidArgumentException("Data non valida");
            }
            // Verifica età minima (es. 16 anni)
            $minAge = new DateTime('-16 years');
            if ($date > $minAge) {
                throw new InvalidArgumentException("Età minima richiesta: 16 anni");
            }
            return $input;
            
        case 'integer':
            $int = filter_var($input, FILTER_VALIDATE_INT);
            if ($int === false) {
                throw new InvalidArgumentException("Valore numerico non valido");
            }
            return $int;
            
        default:
            throw new InvalidArgumentException("Tipo di validazione non supportato");
    }
}

// Sicurezza: Validazione percorsi file
function validatePath($path, $allowedDir) {
    $realPath = realpath($path);
    $realAllowedDir = realpath($allowedDir);
    
    if (!$realPath || !$realAllowedDir) {
        return false;
    }
    
    return strpos($realPath, $realAllowedDir) === 0;
}

// Funzione per caricare testi email personalizzati
function getEmailTexts($pdo) {
    try {
        // Testi predefiniti
        $defaultTexts = [
            'subject' => 'VIP Pass - {evento}',
            'header_title' => 'VIP Pass Confermato',
            'header_subtitle' => 'Opium Club Pordenone',
            'greeting_message' => 'La tua registrazione è stata completata con successo. Tutti i dettagli sono confermati.',
            'qr_title' => 'Codice QR di Accesso',
            'qr_description' => 'Il QR Code ti servirà per l\'accesso all\'evento',
            'qr_note' => 'Conserva il PDF allegato e presentalo all\'ingresso',
            'instructions_title' => 'Informazioni Importanti',
            'instruction_1' => 'Porta con te il PDF allegato (digitale o stampato)',
            'instruction_2' => 'Arriva in tempo per l\'ingresso',
            'instruction_3' => 'Il QR Code è personale e non trasferibile',
            'instruction_4' => 'Per modifiche o cancellazioni, contattaci immediatamente',
            'status_message' => 'Tutto pronto per l\'evento',
            'footer_title' => 'Opium Club Pordenone',
            'footer_subtitle' => 'Il tuo locale di fiducia per eventi indimenticabili',
            'footer_email' => 'info@opiumpordenone.com',
            'footer_location' => 'Pordenone, Italia',
            'footer_disclaimer' => 'Questa email è stata generata automaticamente. Per assistenza, rispondi a questa email.'
        ];
        
        // Prova a caricare testi personalizzati dal database
        $stmt = $pdo->query("SELECT text_key, text_value FROM email_texts");
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $defaultTexts[$row['text_key']] = $row['text_value'];
            }
        }
        
        return $defaultTexts;
    } catch (PDOException $e) {
        // In caso di errore, restituisci testi predefiniti
        error_log("Errore caricamento testi email: " . $e->getMessage());
        return $defaultTexts ?? [];
    }
}

// Funzione per sostituire variabili nei testi
function replaceEmailVariables($text, $variables) {
    foreach ($variables as $key => $value) {
        $text = str_replace('{' . $key . '}', $value, $text);
    }
    return $text;
}

// Gestisci input JSON o POST form
$inputData = $_POST;
if (empty($inputData) && $_SERVER['CONTENT_TYPE'] === 'application/json') {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    if ($jsonInput) {
        $inputData = $jsonInput;
    }
}

// Controllo modalità test
$testMode = isset($inputData['test_mode']) && $inputData['test_mode'] === 'true';

// Debug: Log tutti i dati ricevuti (solo nei log)
error_log('[DEBUG] Dati ricevuti: ' . json_encode($inputData));

// Validazione input con gestione errori
try {
    $requiredFields = ['nome', 'cognome', 'email', 'telefono', 'data-nascita', 'evento'];
    foreach ($requiredFields as $field) {
        if (empty($inputData[$field])) {
            error_log('[DEBUG] Campo mancante: ' . $field);
            throw new InvalidArgumentException("Il campo {$field} è obbligatorio");
        }
    }
    error_log('[DEBUG] Validazione campi completata');

    $nome = validateAndSanitizeInput($inputData['nome'], 'name', 50);
    $cognome = validateAndSanitizeInput($inputData['cognome'], 'name', 50);
    $email = validateAndSanitizeInput($inputData['email'], 'email');
    $telefono = validateAndSanitizeInput($inputData['telefono'], 'phone');
    $dataNascita = validateAndSanitizeInput($inputData['data-nascita'], 'date');

    $eventoId = validateAndSanitizeInput($inputData['evento'], 'integer');
    error_log('[PATCH-DEBUG] EventoId ricevuto dal form: ' . var_export($eventoId, true));
    error_log('[PATCH-DEBUG] Lista POST completa: ' . json_encode($_POST));

    // In modalità test, forza valori specifici
    if ($testMode) {
        $nome = 'Test';
        $cognome = 'Sistema';
        $telefono = '+393331234567';
        $dataNascita = '1990-01-01';
    }

} catch (InvalidArgumentException $e) {
    // Log per debug (rimuovere in produzione)
    error_log("Errore validazione input: " . $e->getMessage() . " - Dati: " . json_encode($_POST));
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// Sicurezza: Verifica esistenza evento con prepared statement
try {
    error_log('[DEBUG] Inizio verifica evento');
    
    // Debug: Log eventoId ricevuto
    error_log('[DEBUG] EventoId ricevuto: ' . $eventoId . ' (tipo: ' . gettype($eventoId) . ')');

    // Usa COALESCE per trattare chiuso NULL come 0 (aperto)
    $stmt = $pdo->prepare("
        SELECT id, event_date, titolo, background_image, chiuso
        FROM events
        WHERE id = ? AND COALESCE(chiuso, 0) = 0
        LIMIT 1
    ");
    $stmt->execute([$eventoId]);
    $eventoData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log('[DEBUG] Query evento eseguita, risultato: ' . ($eventoData ? json_encode($eventoData) : 'NESSUN RISULTATO'));
    error_log('[PATCH-DEBUG] Risultato query eventoData: ' . json_encode($eventoData));

    // Fallback ulteriore: se la colonna chiuso non esiste su vecchie installazioni, riprova senza la condizione
    if (!$eventoData) {
        try {
            $stmtFallback = $pdo->prepare("SELECT id, event_date, titolo, background_image FROM events WHERE id = ? LIMIT 1");
            $stmtFallback->execute([$eventoId]);
            $eventoData = $stmtFallback->fetch(PDO::FETCH_ASSOC);
            error_log('[PATCH-DEBUG] Fallback query risultato: ' . json_encode($eventoData));
            if ($eventoData) {
                // Se manca 'chiuso', assumiamo 0 (aperto)
                $eventoData['chiuso'] = 0;
            }
        } catch (PDOException $e2) {
            // Ignora: potrebbe fallire se la colonna chiuso esiste ma la prima query non ha trovato corrispondenze
        }
    }

    // Debug: Log risultato query
    error_log('[DEBUG] Query risultato: ' . ($eventoData ? json_encode($eventoData) : 'NESSUN RISULTATO'));

    if (!$eventoData) {
        // Debug: Verifica se l'evento esiste ma è chiuso
        $stmt2 = $pdo->prepare("SELECT id, titolo, chiuso FROM events WHERE id = ? LIMIT 1");
        $stmt2->execute([$eventoId]);
        $eventoExists = $stmt2->fetch(PDO::FETCH_ASSOC);

        if ($eventoExists) {
            error_log('[DEBUG] Evento esiste ma è chiuso: ' . json_encode($eventoExists));
            echo json_encode(['success' => false, 'message' => 'Evento non disponibile o chiuso']);
        } else {
            error_log('[DEBUG] Evento non esiste nel database');

            // Debug: Verifica quanti eventi ci sono nel database
            $stmt3 = $pdo->query("SELECT COUNT(*) as total FROM events");
            $totalEvents = $stmt3->fetch(PDO::FETCH_ASSOC);
            error_log('[DEBUG] Totale eventi nel database: ' . $totalEvents['total']);

            // Debug: Lista i primi 5 eventi per debug
            $stmt4 = $pdo->query("SELECT id, titolo, chiuso FROM events ORDER BY id DESC LIMIT 5");
            $sampleEvents = $stmt4->fetchAll(PDO::FETCH_ASSOC);
            error_log('[DEBUG] Eventi di esempio: ' . json_encode($sampleEvents));

            echo json_encode(['success' => false, 'message' => 'Evento non valido']);
        }
        exit;
    }

    // Verifica che l'evento abbia un'immagine di background
    if (empty($eventoData['background_image'])) {
        error_log('[DEBUG] Evento senza immagine di background: ' . $eventoData['titolo']);
        echo json_encode(['success' => false, 'message' => 'Questo evento non ha un\'immagine di background configurata. Contatta l\'amministratore.']);
        exit;
    }

    // Debug: Log informazioni evento
    error_log('[PDF] Event data: ' . json_encode([
        'id' => $eventoData['id'],
        'titolo' => $eventoData['titolo'],
        'background_image' => $eventoData['background_image'],
        'event_date' => $eventoData['event_date']
    ]));
} catch (PDOException $e) {
    error_log("Errore query evento: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore verifica evento']);
    exit;
}

// Sicurezza: Verifica duplicati con prepared statement (skip in modalità test)
if (!$testMode) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM utenti WHERE email = ? AND evento = ? LIMIT 1");
        $stmt->execute([$email, $eventoData['id']]);
        
        if ($stmt->fetch(PDO::FETCH_ASSOC) && $email !== 'angelo.bernardini@gmail.com') {
            echo json_encode(['success' => false, 'message' => 'Sei già registrato a questo evento']);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Errore query duplicati: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore verifica duplicati']);
        exit;
    }
} else {
    // In modalità test, rimuovi eventuali registrazioni precedenti
    try {
        $stmt = $pdo->prepare("DELETE FROM utenti WHERE email = ? AND nome = 'Test' AND cognome = 'Sistema'");
        $stmt->execute([$email]);
        error_log("Test System: Pulizia dati test precedenti per email: " . $email);
    } catch (PDOException $e) {
        error_log("Errore pulizia test precedente: " . $e->getMessage());
    }
}

// Sicurezza: Genera token sicuro
$token = bin2hex(random_bytes(32)); // 64 caratteri hex = 256 bit

echo json_encode([
    'success' => true,
    'message' => 'Validazione completata - Pronto per generazione PDF',
    'data' => [
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email,
        'telefono' => $telefono,
        'data_nascita' => $dataNascita,
        'evento_id' => $eventoId,
        'token' => $token,
        'test_mode' => $testMode
    ]
]);

exit;
?>
