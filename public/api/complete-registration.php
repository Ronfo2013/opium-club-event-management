<?php
// Configurazione errori per PRODUZIONE
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php://stderr');

// Cattura errori fatali
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $errorMsg = '[FATAL ERROR] ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'];
        error_log($errorMsg);
        echo json_encode([
            'success' => false, 
            'message' => 'Fatal Error: ' . $error['message']
        ]);
    }
});

// Cattura eccezioni non gestite
set_exception_handler(function($exception) {
    $errorMsg = '[UNCAUGHT EXCEPTION] ' . $exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine();
    error_log($errorMsg);
    echo json_encode([
        'success' => false, 
        'message' => 'Exception: ' . $exception->getMessage()
    ]);
});

// Sicurezza: Configurazione codifica UTF-8
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Imposta header per risposta JSON
header('Content-Type: application/json; charset=utf-8');

// Sicurezza: Headers di sicurezza
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Sicurezza: Verifica metodo HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

// Funzione per chiamare API interne
function callInternalAPI($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($data))
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Errore cURL: " . $error);
    }
    
    if ($httpCode !== 200) {
        throw new Exception("Errore HTTP: " . $httpCode);
    }
    
    $decodedResponse = json_decode($response, true);
    if (!$decodedResponse) {
        throw new Exception("Risposta non valida dall'API");
    }
    
    return $decodedResponse;
}

// Ricevi dati dal POST
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dati non validi']);
    exit;
}

try {
    error_log('[COMPLETE-REGISTRATION] Inizio processo completo di registrazione');
    
    // FASE 1: Validazione e preparazione dati
    error_log('[COMPLETE-REGISTRATION] Fase 1: Validazione dati');
    
    // Includi direttamente il file di validazione
    $_POST = $input;
    ob_start();
    include __DIR__ . '/save-form-complete.php';
    $validationOutput = ob_get_clean();
    $validationResponse = json_decode($validationOutput, true);
    
    if (!$validationResponse || !$validationResponse['success']) {
        echo $validationOutput;
        exit;
    }
    
    $validationData = $validationResponse['data'];
    error_log('[COMPLETE-REGISTRATION] Validazione completata per: ' . $validationData['email']);
    
    // FASE 2: Generazione PDF
    error_log('[COMPLETE-REGISTRATION] Fase 2: Generazione PDF');
    
    // Includi direttamente il file di generazione PDF
    $_POST = json_encode($validationData);
    ob_start();
    include __DIR__ . '/generate-pdf-complete.php';
    $pdfOutput = ob_get_clean();
    $pdfResponse = json_decode($pdfOutput, true);
    
    if (!$pdfResponse || !$pdfResponse['success']) {
        echo $pdfOutput;
        exit;
    }
    
    $pdfData = $pdfResponse['data'];
    error_log('[COMPLETE-REGISTRATION] PDF generato: ' . $pdfData['pdf_path']);
    
    // FASE 3: Invio Email
    error_log('[COMPLETE-REGISTRATION] Fase 3: Invio Email');
    $emailData = [
        'user_id' => $pdfData['user_id'],
        'pdf_path' => $pdfData['pdf_path'],
        'test_mode' => $validationData['test_mode']
    ];
    
    // Includi direttamente il file di invio email
    $_POST = json_encode($emailData);
    ob_start();
    include __DIR__ . '/send-email-complete.php';
    $emailOutput = ob_get_clean();
    $emailResponse = json_decode($emailOutput, true);
    
    if (!$emailResponse['success']) {
        // Anche se l'email fallisce, la registrazione è completata
        error_log('[COMPLETE-REGISTRATION] Errore invio email, ma registrazione completata');
        echo json_encode([
            'success' => true,
            'message' => 'Registrazione completata. Attenzione: problema nell\'invio email.',
            'email_warning' => true,
            'data' => [
                'user_id' => $pdfData['user_id'],
                'token' => $pdfData['token'],
                'pdf_url' => $pdfData['pdf_url']
            ]
        ]);
        exit;
    }
    
    // SUCCESSO COMPLETO
    error_log('[COMPLETE-REGISTRATION] Processo completato con successo per: ' . $validationData['email']);
    
    echo json_encode([
        'success' => true,
        'message' => $validationData['test_mode'] ? 'Test sistema completato con successo!' : 'Iscrizione avvenuta correttamente.',
        'data' => [
            'user_id' => $pdfData['user_id'],
            'token' => $pdfData['token'],
            'pdf_url' => $pdfData['pdf_url'],
            'email' => $validationData['email'],
            'nome' => $validationData['nome'],
            'cognome' => $validationData['cognome'],
            'evento' => $validationData['evento_id']
        ],
        'test_mode' => $validationData['test_mode']
    ]);
    
} catch (Exception $e) {
    error_log('[COMPLETE-REGISTRATION] Errore: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Errore durante la registrazione: ' . $e->getMessage()
    ]);
}

exit;
?>
