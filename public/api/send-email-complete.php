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

// Carica configurazione e database
try {
    $bootstrapPath = __DIR__ . '/../../src/bootstrap.php';
    if (!file_exists($bootstrapPath)) {
        throw new Exception("File bootstrap.php non trovato");
    }
    
    $bootstrap = require_once $bootstrapPath;
    if (!isset($bootstrap['db']) || !isset($bootstrap['config'])) {
        throw new Exception("Bootstrap non valido");
    }
    
    $pdo = $bootstrap['db'];
    $config = $bootstrap['config'];
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // TEST: Override configurazione email per test
    $config['email'] = [
        'host' => 'smtp.ionos.it',
        'username' => 'info@opiumpordenone.com',
        'password' => 'Camilla2020@',
        'port' => 587,
        'encryption' => 'tls'
    ];
    
} catch (Exception $e) {
    error_log("Errore bootstrap: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore di configurazione']);
    exit;
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

// Ricevi dati dal POST
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dati non validi']);
    exit;
}

$userId = $input['user_id'] ?? '';
$pdfPath = $input['pdf_path'] ?? '';
$testMode = $input['test_mode'] ?? false;

// Recupera dati utente e evento
try {
    $stmt = $pdo->prepare("
        SELECT u.*, e.titolo, e.event_date 
        FROM utenti u 
        JOIN events e ON u.evento = e.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        echo json_encode(['success' => false, 'message' => 'Utente non trovato']);
        exit;
    }
} catch (PDOException $e) {
    error_log("Errore query utente: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore recupero dati utente']);
    exit;
}

// Sicurezza: Invio email sicuro
try {
    // Verifica configurazione SMTP prima di procedere
    if (!isset($config['email']) || 
        !isset($config['email']['host']) || 
        !isset($config['email']['username']) || 
        !isset($config['email']['password']) || 
        !isset($config['email']['port']) || 
        !isset($config['email']['encryption']) ||
        empty($config['email']['host']) ||
        empty($config['email']['username']) ||
        empty($config['email']['password'])) {
        
        error_log("Errore configurazione SMTP: Configurazione email mancante o incompleta");
        echo json_encode(['success' => false, 'message' => 'Errore configurazione email del server']);
        exit;
    }
    
    // Carica PHPMailer manualmente
    require_once __DIR__ . '/../../lib/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../../lib/PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/../../lib/PHPMailer/src/Exception.php';
    
    // Verifica che PHPMailer sia disponibile
    if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        error_log("Errore PHPMailer: Classe PHPMailer non trovata");
        echo json_encode(['success' => false, 'message' => 'Errore sistema email']);
        exit;
    }
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    // Sicurezza: Impostazione codifica UTF-8
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    
    // Forza invio HTML su Google Cloud
    $mail->isHTML(true);
    $mail->ContentType = 'text/html; charset=UTF-8';
    
    // Configurazione SMTP sicura con debug migliorato
    $mail->isSMTP();
    $mail->Host = $config['email']['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['email']['username'];
    $mail->Password = $config['email']['password'];
    $mail->SMTPSecure = $config['email']['encryption'];
    $mail->Port = $config['email']['port'];
    
    // Debug SMTP solo per test
    if ($testMode) {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP Debug ($level): $str");
        };
    }
    
    // Timeout configurazioni
    $mail->Timeout = 30;
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // Test connessione SMTP in modalità test
    if ($testMode) {
        try {
            $mail->smtpConnect();
            error_log("Test System: Connessione SMTP riuscita");
            $mail->smtpClose();
        } catch (\PHPMailer\PHPMailer\Exception $smtpTest) {
            error_log("Test System: Errore connessione SMTP - " . $smtpTest->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Errore connessione SMTP: ' . $smtpTest->getMessage(),
                'smtp_config' => [
                    'host' => $config['email']['host'],
                    'port' => $config['email']['port'],
                    'username' => $config['email']['username'],
                    'encryption' => $config['email']['encryption']
                ]
            ]);
            exit;
        }
    }
    
    // Sicurezza: Validazione mittente
    $fromEmail = filter_var($config['email']['username'], FILTER_VALIDATE_EMAIL);
    if (!$fromEmail) {
        error_log("Errore email mittente: Email mittente non valida - " . $config['email']['username']);
        echo json_encode(['success' => false, 'message' => 'Errore configurazione email mittente']);
        exit;
    }
    
    $mail->setFrom($fromEmail, 'Opium Club Pordenone');
    $mail->addAddress($userData['email'], htmlspecialchars($userData['nome'] . ' ' . $userData['cognome'], ENT_QUOTES, 'UTF-8'));
    
    // Sicurezza: Nome file attachment sicuro
    $attachmentName = 'Omaggio_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $userData['nome'] . '_' . $userData['titolo']) . '.pdf';
    $mail->addAttachment($pdfPath, $attachmentName);
    
    // Carica testi personalizzabili
    $emailTexts = getEmailTexts($pdo);
    
    // Formatta data evento
    $dataEvento = DateTime::createFromFormat('Y-m-d', $userData['event_date']);
    $mesi = ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
    $dataFormattata = strtoupper($dataEvento->format('d') . ' ' . $mesi[intval($dataEvento->format('m')) - 1] . ' ' . $dataEvento->format('Y'));
    
    // Variabili per sostituzione
    $emailVariables = [
        'nome' => $userData['nome'],
        'cognome' => $userData['cognome'],
        'evento' => $userData['titolo'],
        'data' => $dataFormattata
    ];
    
    // Oggetto email personalizzabile
    $mail->Subject = replaceEmailVariables($emailTexts['subject'], $emailVariables);
    
    // Sicurezza: Template email Liquid Glass Apple-inspired con testi personalizzabili
    $emailBody = "<!DOCTYPE html>
<html lang='it'>
<head>
    <meta charset='UTF-8'>
    <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>VIP Pass - Mr.Charlie</title>
</head>
<body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"SF Pro Display\", \"Helvetica Neue\", Arial, sans-serif; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 50%, #1a1a1a 100%); color: #ffffff; min-height: 100vh;'>
    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 50%, #1a1a1a 100%); min-height: 100vh;'>
        <tr>
            <td align='center' style='padding: 60px 20px;'>
                <!-- Container principale con effetto glass -->
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='600' style='max-width: 600px; background: rgba(40, 40, 40, 0.8); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.05); overflow: hidden;'>
                    
                    <!-- Header minimalista -->
                    <tr>
                        <td style='padding: 50px 40px 30px 40px; text-align: center; background: linear-gradient(180deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%);'>
                            <div style='display: inline-block; padding: 20px 40px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; backdrop-filter: blur(10px);'>
                                <h1 style='margin: 0; font-size: 32px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;'>
                                    " . htmlspecialchars(replaceEmailVariables($emailTexts['header_title'], $emailVariables), ENT_QUOTES, 'UTF-8') . "
                                </h1>
                            </div>
                            <p style='margin: 25px 0 0 0; font-size: 17px; color: rgba(255, 255, 255, 0.7); font-weight: 400; letter-spacing: 0.2px;'>
                                " . htmlspecialchars(replaceEmailVariables($emailTexts['header_subtitle'], $emailVariables), ENT_QUOTES, 'UTF-8') . "
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Contenuto principale -->
                    <tr>
                        <td style='padding: 20px 40px 40px 40px;'>
                            <!-- Saluto personalizzato -->
                            <div style='margin-bottom: 35px; padding: 30px; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 18px; backdrop-filter: blur(10px);'>
                                <h2 style='margin: 0 0 12px 0; font-size: 28px; font-weight: 600; color: #ffffff; letter-spacing: -0.3px;'>
                                    Ciao " . htmlspecialchars($userData['nome'] . ' ' . $userData['cognome'], ENT_QUOTES, 'UTF-8') . "
                                </h2>
                                <p style='margin: 0; font-size: 17px; line-height: 1.5; color: rgba(255, 255, 255, 0.8); font-weight: 400;'>
                                    " . htmlspecialchars(replaceEmailVariables($emailTexts['greeting_message'], $emailVariables), ENT_QUOTES, 'UTF-8') . "
                                </p>
                            </div>
                            
                            <!-- Box evento con effetto glass -->
                            <div style='margin: 35px 0; padding: 32px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.03) 100%); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 20px; backdrop-filter: blur(15px); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);'>
                                <div style='margin-bottom: 24px;'>
                                    <h3 style='margin: 0 0 8px 0; font-size: 24px; font-weight: 600; color: #ffffff; letter-spacing: -0.2px;'>
                                        " . htmlspecialchars($userData['titolo'], ENT_QUOTES, 'UTF-8') . "
                                    </h3>
                                    <div style='height: 1px; background: linear-gradient(90deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.05) 50%, rgba(255, 255, 255, 0.2) 100%); margin: 16px 0;'></div>
                                </div>
                                
                                <div style='display: flex; align-items: center; margin-bottom: 20px;'>
                                    <span style='font-size: 15px; font-weight: 500; color: rgba(255, 255, 255, 0.6); margin-right: 12px; text-transform: uppercase; letter-spacing: 1px;'>Data</span>
                                    <span style='font-size: 18px; font-weight: 500; color: #ffffff;'>" . htmlspecialchars($dataFormattata, ENT_QUOTES, 'UTF-8') . "</span>
                                </div>
                                
                                <div style='padding: 20px; background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; text-align: center; backdrop-filter: blur(5px);'>
                                    <h4 style='margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: #ffffff;'>
                                        " . htmlspecialchars(replaceEmailVariables($emailTexts['qr_title'], $emailVariables), ENT_QUOTES, 'UTF-8') . "
                                    </h4>
                                    <p style='margin: 0; font-size: 14px; color: rgba(255, 255, 255, 0.7); line-height: 1.4;'>
                                        " . htmlspecialchars(replaceEmailVariables($emailTexts['qr_description'], $emailVariables), ENT_QUOTES, 'UTF-8') . "<br>
                                        <span style='font-size: 13px; color: rgba(255, 255, 255, 0.5);'>" . htmlspecialchars(replaceEmailVariables($emailTexts['qr_note'], $emailVariables), ENT_QUOTES, 'UTF-8') . "</span>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Istruzioni con design minimalista -->
                            <div style='margin: 35px 0; padding: 28px; background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; backdrop-filter: blur(10px);'>
                                <h4 style='margin: 0 0 20px 0; font-size: 18px; font-weight: 600; color: #ffffff; letter-spacing: -0.1px;'>
                                    " . htmlspecialchars(replaceEmailVariables($emailTexts['instructions_title'], $emailVariables), ENT_QUOTES, 'UTF-8') . "
                                </h4>
                                <div style='space-y: 12px;'>
                                    <div style='display: flex; align-items: flex-start; margin-bottom: 12px;'>
                                        <div style='width: 6px; height: 6px; background: rgba(255, 255, 255, 0.4); border-radius: 50%; margin: 8px 16px 0 0; flex-shrink: 0;'></div>
                                        <span style='font-size: 15px; color: rgba(255, 255, 255, 0.8); line-height: 1.5;'>" . htmlspecialchars(replaceEmailVariables($emailTexts['instruction_1'], $emailVariables), ENT_QUOTES, 'UTF-8') . "</span>
                                    </div>
                                    <div style='display: flex; align-items: flex-start; margin-bottom: 12px;'>
                                        <div style='width: 6px; height: 6px; background: rgba(255, 255, 255, 0.4); border-radius: 50%; margin: 8px 16px 0 0; flex-shrink: 0;'></div>
                                        <span style='font-size: 15px; color: rgba(255, 255, 255, 0.8); line-height: 1.5;'>" . htmlspecialchars(replaceEmailVariables($emailTexts['instruction_2'], $emailVariables), ENT_QUOTES, 'UTF-8') . "</span>
                                    </div>
                                    <div style='display: flex; align-items: flex-start; margin-bottom: 12px;'>
                                        <div style='width: 6px; height: 6px; background: rgba(255, 255, 255, 0.4); border-radius: 50%; margin: 8px 16px 0 0; flex-shrink: 0;'></div>
                                        <span style='font-size: 15px; color: rgba(255, 255, 255, 0.8); line-height: 1.5;'>" . htmlspecialchars(replaceEmailVariables($emailTexts['instruction_3'], $emailVariables), ENT_QUOTES, 'UTF-8') . "</span>
                                    </div>
                                    <div style='display: flex; align-items: flex-start;'>
                                        <div style='width: 6px; height: 6px; background: rgba(255, 255, 255, 0.4); border-radius: 50%; margin: 8px 16px 0 0; flex-shrink: 0;'></div>
                                        <span style='font-size: 15px; color: rgba(255, 255, 255, 0.8); line-height: 1.5;'>" . htmlspecialchars(replaceEmailVariables($emailTexts['instruction_4'], $emailVariables), ENT_QUOTES, 'UTF-8') . "</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Status indicator -->
                            <div style='text-align: center; margin: 40px 0;'>
                                <div style='display: inline-flex; align-items: center; padding: 16px 32px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 50px; backdrop-filter: blur(10px);'>
                                    <div style='width: 8px; height: 8px; background: #00ff88; border-radius: 50%; margin-right: 12px; box-shadow: 0 0 12px rgba(0, 255, 136, 0.4);'></div>
                                    <span style='font-size: 16px; font-weight: 500; color: #ffffff;'>" . htmlspecialchars(replaceEmailVariables($emailTexts['status_message'], $emailVariables), ENT_QUOTES, 'UTF-8') . "</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer con design glass -->
                    <tr>
                        <td style='padding: 40px; background: linear-gradient(180deg, rgba(0, 0, 0, 0.1) 0%, rgba(0, 0, 0, 0.2) 100%); border-top: 1px solid rgba(255, 255, 255, 0.08);'>
                            <div style='text-align: center; margin-bottom: 24px;'>
                                <h3 style='margin: 0 0 8px 0; font-size: 22px; font-weight: 600; color: #ffffff; letter-spacing: -0.2px;'>
                                    " . htmlspecialchars(replaceEmailVariables($emailTexts['footer_title'], $emailVariables), ENT_QUOTES, 'UTF-8') . "
                                </h3>
                                <p style='margin: 0; font-size: 15px; color: rgba(255, 255, 255, 0.6); font-weight: 400;'>
                                    " . htmlspecialchars(replaceEmailVariables($emailTexts['footer_subtitle'], $emailVariables), ENT_QUOTES, 'UTF-8') . "
                                </p>
                            </div>
                            
                            <!-- Contatti -->
                            <div style='text-align: center; padding: 20px 0; border-top: 1px solid rgba(255, 255, 255, 0.06); margin-top: 20px;'>
                                <p style='margin: 0 0 6px 0; font-size: 14px; color: rgba(255, 255, 255, 0.5);'>
                                    Email: " . htmlspecialchars(replaceEmailVariables($emailTexts['footer_email'], $emailVariables), ENT_QUOTES, 'UTF-8') . "
                                </p>
                                <p style='margin: 0; font-size: 14px; color: rgba(255, 255, 255, 0.5);'>
                                    " . htmlspecialchars(replaceEmailVariables($emailTexts['footer_location'], $emailVariables), ENT_QUOTES, 'UTF-8') . "
                                </p>
                            </div>
                            
                            <!-- Disclaimer -->
                            <div style='text-align: center; padding-top: 20px; margin-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.04);'>
                                <p style='margin: 0; font-size: 12px; color: rgba(255, 255, 255, 0.4); line-height: 1.4;'>
                                    " . htmlspecialchars(replaceEmailVariables($emailTexts['footer_disclaimer'], $emailVariables), ENT_QUOTES, 'UTF-8') . "<br>
                                    © " . date('Y') . " " . htmlspecialchars(replaceEmailVariables($emailTexts['footer_title'], $emailVariables), ENT_QUOTES, 'UTF-8') . ". Tutti i diritti riservati.
                                </p>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";
    
    $mail->Body = $emailBody;
    
    // Versione testo minimalista per client che non supportano HTML con testi personalizzabili
    $mail->AltBody = "
" . strtoupper(replaceEmailVariables($emailTexts['header_title'], $emailVariables)) . " - " . replaceEmailVariables($emailTexts['header_subtitle'], $emailVariables) . "

Ciao " . htmlspecialchars($userData['nome'] . ' ' . $userData['cognome'], ENT_QUOTES, 'UTF-8') . "

" . replaceEmailVariables($emailTexts['greeting_message'], $emailVariables) . "

DETTAGLI EVENTO:
Evento: " . htmlspecialchars($userData['titolo'], ENT_QUOTES, 'UTF-8') . "
Data: " . htmlspecialchars($dataFormattata, ENT_QUOTES, 'UTF-8') . "

" . strtoupper(replaceEmailVariables($emailTexts['qr_title'], $emailVariables)) . ":
" . replaceEmailVariables($emailTexts['qr_description'], $emailVariables) . ".
" . replaceEmailVariables($emailTexts['qr_note'], $emailVariables) . "

" . strtoupper(replaceEmailVariables($emailTexts['instructions_title'], $emailVariables)) . ":
• " . replaceEmailVariables($emailTexts['instruction_1'], $emailVariables) . "
• " . replaceEmailVariables($emailTexts['instruction_2'], $emailVariables) . "
• " . replaceEmailVariables($emailTexts['instruction_3'], $emailVariables) . "
• " . replaceEmailVariables($emailTexts['instruction_4'], $emailVariables) . "

STATUS: " . replaceEmailVariables($emailTexts['status_message'], $emailVariables) . "

---
" . replaceEmailVariables($emailTexts['footer_title'], $emailVariables) . "
" . replaceEmailVariables($emailTexts['footer_subtitle'], $emailVariables) . "

Email: " . replaceEmailVariables($emailTexts['footer_email'], $emailVariables) . "
" . replaceEmailVariables($emailTexts['footer_location'], $emailVariables) . "

" . replaceEmailVariables($emailTexts['footer_disclaimer'], $emailVariables) . "
© " . date('Y') . " " . replaceEmailVariables($emailTexts['footer_title'], $emailVariables) . ". Tutti i diritti riservati.
";
    
    // Tentativo invio email con gestione errori
    $emailSent = $mail->send();
    
    if ($emailSent) {
        // Email inviata con successo
        $updateStmt = $pdo->prepare("UPDATE utenti SET email_inviata = NOW(), email_status = 'inviata' WHERE id = ?");
        $updateStmt->execute([$userId]);
        error_log('[EMAIL] Email inviata con successo per utente ID: ' . $userId);
        
        // Sicurezza: Rimuovi PDF dopo invio
        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }
        
        // Log specifico per modalità test
        if ($testMode) {
            error_log("Test System: Email test inviata con successo - Email: " . $userData['email'] . ", Token: " . $userData['token']);
            echo json_encode([
                'success' => true, 
                'message' => 'Test sistema completato con successo! Email inviata.',
                'test_mode' => true,
                'details' => [
                    'email' => $userData['email'],
                    'nome' => $userData['nome'] . ' ' . $userData['cognome'],
                    'evento' => $userData['titolo'],
                    'token' => $userData['token']
                ]
            ]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Iscrizione avvenuta correttamente.']);
        }
    } else {
        // Errore nell'invio email
        $errorMsg = $mail->ErrorInfo;
        $updateStmt = $pdo->prepare("UPDATE utenti SET email_status = 'errore', email_error = ? WHERE id = ?");
        $updateStmt->execute([$errorMsg, $userId]);
        error_log('[EMAIL] Errore invio email per utente ID: ' . $userId . ' - Errore: ' . $errorMsg);
        
        // Non interrompe il processo, ma logga l'errore
        echo json_encode([
            'success' => true, 
            'message' => 'Iscrizione completata. Attenzione: problema nell\'invio email.',
            'email_warning' => true
        ]);
    }
    
} catch (\PHPMailer\PHPMailer\Exception $e) {
    $errorDetails = [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'smtp_host' => $config['email']['host'] ?? 'non configurato',
        'smtp_port' => $config['email']['port'] ?? 'non configurato',
        'smtp_username' => $config['email']['username'] ?? 'non configurato'
    ];
    
    error_log("Errore PHPMailer dettagliato: " . json_encode($errorDetails));
    
    if ($testMode) {
        error_log("Test System: Errore PHPMailer durante test - " . json_encode($errorDetails));
        echo json_encode([
            'success' => false, 
            'message' => 'Errore SMTP durante test: ' . $e->getMessage(),
            'debug_info' => $errorDetails
        ]);
    } else {
        // Messaggio generico per produzione
        echo json_encode(['success' => false, 'message' => 'Errore nell\'invio dell\'email di conferma. Contatta l\'assistenza.']);
    }
} catch (Exception $e) {
    error_log("Errore email generico: " . $e->getMessage() . " - File: " . $e->getFile() . " - Linea: " . $e->getLine());
    
    if ($testMode) {
        error_log("Test System: Errore generico durante test - " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Errore generico durante test: ' . $e->getMessage(),
            'debug_info' => [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore nell\'invio dell\'email']);
    }
}

exit;
?>
