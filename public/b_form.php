<?php
// Sicurezza: Configurazione rigorosa degli errori
ini_set('display_errors', 0);  // Disabilita in produzione
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// Sicurezza: Configurazione codifica UTF-8
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

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

// Sicurezza: Verifica CSRF token (se implementato)
// if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
//     http_response_code(403);
//     echo json_encode(['success' => false, 'message' => 'Token di sicurezza non valido']);
//     exit;
// }

// Carica configurazione e database
try {
    $bootstrapPath = __DIR__ . '/../src/bootstrap.php';
    if (!file_exists($bootstrapPath)) {
        throw new Exception("File bootstrap.php non trovato in /src/. Verifica la struttura del progetto.");
    }
    $bootstrap = require_once $bootstrapPath;
    
    if (!isset($bootstrap['db']) || !isset($bootstrap['config'])) {
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
            if (!$sanitized || $sanitized[0] !== '+') {
                $sanitized = '+39' . ltrim($sanitized, '+39');
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

// Controllo modalità test
$testMode = isset($_POST['test_mode']) && $_POST['test_mode'] === 'true';

// Validazione input con gestione errori
try {
    $requiredFields = ['nome', 'cognome', 'email', 'telefono', 'data-nascita', 'evento'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new InvalidArgumentException("Il campo {$field} è obbligatorio");
        }
    }

    $nome = validateAndSanitizeInput($_POST['nome'], 'name', 50);
    $cognome = validateAndSanitizeInput($_POST['cognome'], 'name', 50);
    $email = validateAndSanitizeInput($_POST['email'], 'email');
    $telefono = validateAndSanitizeInput($_POST['telefono'], 'phone');
    $dataNascita = validateAndSanitizeInput($_POST['data-nascita'], 'date');
    $eventoId = validateAndSanitizeInput($_POST['evento'], 'integer');

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
    $stmt = $pdo->prepare("SELECT id, event_date, titolo, background_image, chiuso FROM events WHERE id = ? AND chiuso = 0 LIMIT 1");
    $stmt->execute([$eventoId]);
    $eventoData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$eventoData) {
        echo json_encode(['success' => false, 'message' => 'Evento non disponibile o chiuso']);
        exit;
    }
} catch (PDOException $e) {
    error_log("Errore query evento: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore verifica evento']);
    exit;
}

// Rimossa logica di blocco utenti per mancata validazione

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
            'subject' => 'Iscrizione Confermata - {evento}',
            'header_title' => 'Iscrizione Confermata',
            'header_subtitle' => 'Mr.Charlie Lignano Sabbiadoro',
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
            'footer_title' => 'Mr.Charlie Lignano Sabbiadoro',
            'footer_subtitle' => 'Il tuo locale di fiducia per eventi indimenticabili',
            'footer_email' => 'info@mrcharlie.it',
            'footer_location' => 'Lignano Sabbiadoro, Italia',
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

// Generazione QR Code sicura
try {
    // Controlla se la libreria QR esiste
    $qrLibPath = __DIR__ . '/../lib/phpqrcode/qrlib.php';
    if (!file_exists($qrLibPath)) {
        throw new Exception("Libreria QR Code non trovata. Installa phpqrcode in /lib/phpqrcode/");
    }
    require_once $qrLibPath;
    
    $qrDir = $config['paths']['qr_code_dir'];
    
    // Sicurezza: Verifica e crea directory se non esiste
    if (!is_dir($qrDir)) {
        if (!mkdir($qrDir, 0755, true)) {
            throw new Exception("Impossibile creare directory QR Code");
        }
    }
    
    // Sicurezza: Verifica permessi directory
    if (!is_writable($qrDir)) {
        throw new Exception("Directory QR Code non scrivibile");
    }
    
    $qrFile = $qrDir . 'qr_' . $token . '.png';
    
    // Sicurezza: Verifica percorso solo se la directory esiste
    $realQrDir = realpath($qrDir);
    $realQrFile = $realQrDir . DIRECTORY_SEPARATOR . 'qr_' . $token . '.png';
    
    if (!$realQrDir || strpos($realQrFile, $realQrDir) !== 0) {
        throw new Exception("Percorso QR non sicuro");
    }
    
    // Genera QR Code
    QRcode::png($token, $qrFile, QR_ECLEVEL_H, 10, 1);
    
    // Verifica che il file sia stato creato
    if (!file_exists($qrFile)) {
        throw new Exception("QR Code non generato correttamente");
    }
    
} catch (Exception $e) {
    error_log("Errore generazione QR: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore generazione QR Code']);
    exit;
}

// Sicurezza: Inserimento database con transazione
try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO utenti (nome, cognome, email, telefono, data_nascita, evento, qr_code_path, token, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $nome,
        $cognome,
        $email,
        $telefono,
        $dataNascita,
        $eventoData['id'],
        $qrFile,
        $token
    ]);
    
    $userId = $pdo->lastInsertId();
    $pdo->commit();
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Errore inserimento DB: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore salvataggio dati']);
    exit;
}

// Sicurezza: Generazione PDF sicura
try {
    $generatedImagesDir = $config['paths']['generated_images_dir'];
    
    // Sicurezza: Verifica e crea directory se non esiste
    if (!is_dir($generatedImagesDir)) {
        if (!mkdir($generatedImagesDir, 0755, true)) {
            throw new Exception("Impossibile creare directory immagini");
        }
    }
    
    $tempImage = $generatedImagesDir . 'temp_' . $token . '.png';
    
    // Sicurezza: Validazione percorso immagine background
    $uploadsDir = $config['paths']['uploads_dir'] ?? (__DIR__ . '/uploads/');
    $backgroundImagePath = null;
    
    if (!empty($eventoData['background_image'])) {
        $bgPath = $uploadsDir . basename($eventoData['background_image']); // basename per sicurezza
        if (file_exists($bgPath)) {
            // Verifica che il file sia nella directory uploads
            $realUploadsDir = realpath($uploadsDir);
            $realBgPath = realpath($bgPath);
            if ($realUploadsDir && $realBgPath && strpos($realBgPath, $realUploadsDir) === 0) {
                $backgroundImagePath = $bgPath;
            }
        }
    }
    
    // Fallback sicuro
    if (!$backgroundImagePath) {
        // Prova diversi percorsi per l'immagine di default
        $defaultPaths = [
            __DIR__ . '/sfondo_default.png',
            __DIR__ . '/sfondo.png',
            __DIR__ . '/background_default.png',
            __DIR__ . '/default.png'
        ];
        
        foreach ($defaultPaths as $path) {
            if (file_exists($path)) {
                $backgroundImagePath = $path;
                break;
            }
        }
    }
    
    if (!$backgroundImagePath || !file_exists($backgroundImagePath)) {
        // Crea un'immagine di sfondo predefinita al volo
        $backgroundImagePath = $tempImage . '_bg.png';
        $defaultBg = imagecreatetruecolor(1080, 1920);
        $bgColor = imagecolorallocate($defaultBg, 28, 28, 30); // Colore scuro elegante
        imagefill($defaultBg, 0, 0, $bgColor);
        
        // Aggiungi testo "Mr.Charlie Event"
        $white = imagecolorallocate($defaultBg, 255, 255, 255);
        $fontSize = 5;
        $text = "Mr.Charlie Event";
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textHeight = imagefontheight($fontSize);
        $x = (1080 - $textWidth) / 2;
        $y = (1920 - $textHeight) / 2;
        imagestring($defaultBg, $fontSize, $x, $y, $text, $white);
        
        imagepng($defaultBg, $backgroundImagePath);
        imagedestroy($defaultBg);
    }
    
    // Sicurezza: Validazione tipo file
    $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $backgroundImagePath);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception("Tipo file non supportato");
    }
    
    // Creazione immagine sicura
    switch ($mimeType) {
        case 'image/png':
            $background = imagecreatefrompng($backgroundImagePath);
            break;
        case 'image/jpeg':
        case 'image/jpg':
            $background = imagecreatefromjpeg($backgroundImagePath);
            break;
        default:
            throw new Exception("Formato immagine non supportato");
    }
    
    if (!$background) {
        throw new Exception("Impossibile caricare immagine");
    }
    
    $qrCodeImg = imagecreatefrompng($qrFile);
    if (!$qrCodeImg) {
        imagedestroy($background);
        throw new Exception("Impossibile caricare QR Code");
    }
    
    // Sicurezza: Validazione dimensioni
    $bgWidth = imagesx($background);
    $bgHeight = imagesy($background);
    $qrWidth = imagesx($qrCodeImg);
    $qrHeight = imagesy($qrCodeImg);
    
    if ($bgWidth <= 0 || $bgHeight <= 0 || $qrWidth <= 0 || $qrHeight <= 0) {
        imagedestroy($background);
        imagedestroy($qrCodeImg);
        throw new Exception("Dimensioni immagine non valide");
    }
    
    // Posizionamento sicuro del QR
    $destX = min(400, $bgWidth - $qrWidth - 20);
    $destY = min(1240, $bgHeight - $qrHeight - 20);
    
    // Sfondo QR
    $qrBgHeight = $qrHeight + 40;
    $qrBgY = max(0, $destY - 20);
    $white = imagecolorallocatealpha($background, 255, 255, 255, 60);
    imagefilledrectangle($background, 0, $qrBgY, $bgWidth, $qrBgY + $qrBgHeight, $white);
    
    // Copia QR
    imagecopy($background, $qrCodeImg, $destX, $destY, 0, 0, $qrWidth, $qrHeight);
    
    // Sicurezza: Validazione font
    $fontFile = __DIR__ . '/arial.ttf';
    if (!file_exists($fontFile) || !validatePath($fontFile, dirname(__FILE__))) {
        $fontFile = null; // Usa font di sistema
    }
    
    // Testo sicuro
    $dataEvento = DateTime::createFromFormat('Y-m-d', $eventoData['event_date']);
    $mesi = ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
    $dataFormattata = strtoupper($dataEvento->format('d') . ' ' . $mesi[intval($dataEvento->format('m')) - 1] . ' ' . $dataEvento->format('Y'));
    
    // Sicurezza: Escape per testo
    $textNome = htmlspecialchars($eventoData['titolo'], ENT_QUOTES, 'UTF-8') . ' - ' . 
                $dataFormattata . ' - ' . 
                htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . ' ' . 
                htmlspecialchars($cognome, ENT_QUOTES, 'UTF-8');
    
    $fontSize = 40;
    $maxTextWidth = $bgWidth - 100;
    
    // Calcolo dimensioni testo
    if ($fontFile) {
        $textBbox = imagettfbbox($fontSize, 0, $fontFile, $textNome);
        $textWidth = abs($textBbox[2] - $textBbox[0]);
        
        while ($textWidth > $maxTextWidth && $fontSize > 16) {
            $fontSize -= 2;
            $textBbox = imagettfbbox($fontSize, 0, $fontFile, $textNome);
            $textWidth = abs($textBbox[2] - $textBbox[0]);
        }
        
        $textHeight = abs($textBbox[7] - $textBbox[1]);
        $textX = max(0, intval(($bgWidth - $textWidth) / 2));
    } else {
        $textWidth = strlen($textNome) * 10; // Stima grossolana
        $textHeight = 20;
        $textX = max(0, intval(($bgWidth - $textWidth) / 2));
    }
    
    $textY = $qrBgY + $qrBgHeight + 80;
    
    // Sfondo testo
    $paddingY = 20;
    $rectY = max(0, $textY - $textHeight - $paddingY);
    $rectHeight = min($bgHeight - $rectY, $textHeight + ($paddingY * 2));
    
    imagefilledrectangle($background, 0, $rectY, $bgWidth, $rectY + $rectHeight, $white);
    
    // Scrivi testo
    $textColor = imagecolorallocate($background, 0, 0, 0);
    if ($fontFile) {
        imagettftext($background, $fontSize, 0, $textX, $textY, $textColor, $fontFile, $textNome);
    } else {
        imagestring($background, 5, $textX, $textY - 20, $textNome, $textColor);
    }
    
    // Salva immagine temporanea SENZA interlacciamento per compatibilità FPDF
    if (!imagepng($background, $tempImage, 9, PNG_NO_FILTER)) {
        throw new Exception("Errore salvataggio immagine");
    }
    
    // Verifica e rimuovi interlacciamento se presente
    if (function_exists('imageinterlace')) {
        imageinterlace($background, 0); // Disabilita interlacciamento
    }
    
    imagedestroy($background);
    imagedestroy($qrCodeImg);
    
    // Sicurezza: Rimuovi QR temporaneo
    if (file_exists($qrFile)) {
        unlink($qrFile);
    }
    
} catch (Exception $e) {
    error_log("Errore generazione immagine: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore generazione immagine']);
    exit;
}

// Sicurezza: Generazione PDF sicura
try {
    // Controlla se la libreria FPDF esiste
    $fpdfPath = __DIR__ . '/../lib/fpdf/fpdf.php';
    if (!file_exists($fpdfPath)) {
        throw new Exception("Libreria FPDF non trovata in: " . $fpdfPath);
    }
    
    error_log("Caricamento FPDF da: " . $fpdfPath);
    require_once $fpdfPath;
    
    // Verifica che la classe FPDF sia disponibile
    if (!class_exists('FPDF')) {
        throw new Exception("Classe FPDF non caricata correttamente dopo require");
    }
    
    error_log("FPDF caricato correttamente, creazione istanza PDF");
    $pdf = new FPDF();
    $pdf->AddPage('P', array(1080, 1920));
    
    // Verifica che l'immagine temporanea esista prima di aggiungerla al PDF
    if (!file_exists($tempImage)) {
        throw new Exception("Immagine temporanea non trovata: " . $tempImage);
    }
    
    // Rimuovi interlacciamento dall'immagine per compatibilità FPDF
    $tempImageFixed = $tempImage . '_fixed.png';
    $img = imagecreatefrompng($tempImage);
    if ($img) {
        imageinterlace($img, 0); // Disabilita interlacciamento
        imagepng($img, $tempImageFixed, 9, PNG_NO_FILTER);
        imagedestroy($img);
        
        // Usa l'immagine corretta
        $imageToUse = file_exists($tempImageFixed) ? $tempImageFixed : $tempImage;
    } else {
        $imageToUse = $tempImage;
    }
    
    error_log("Aggiunta immagine al PDF: " . $imageToUse);
    $pdf->Image($imageToUse, 0, 0, 1080, 1920);
    
    // Pulisci l'immagine temporanea corretta
    if (isset($tempImageFixed) && file_exists($tempImageFixed)) {
        unlink($tempImageFixed);
    }
    
    $pdfDir = $config['paths']['generated_pdfs_dir'];
    
    // Sicurezza: Verifica e crea directory se non esiste
    if (!is_dir($pdfDir)) {
        if (!mkdir($pdfDir, 0755, true)) {
            throw new Exception("Impossibile creare directory PDF");
        }
    }
    
    $outputPdf = $pdfDir . 'vip_pass_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $nome . '_' . $cognome) . '_' . $token . '.pdf';
    
    // Sicurezza: Validazione percorso PDF
    $realPdfDir = realpath($pdfDir);
    $realOutputPdf = $realPdfDir . DIRECTORY_SEPARATOR . basename($outputPdf);
    
    if (!$realPdfDir || strpos($realOutputPdf, $realPdfDir) !== 0) {
        throw new Exception("Percorso PDF non sicuro");
    }
    
    error_log("Salvataggio PDF in: " . $outputPdf);
    error_log("Directory PDF writable: " . (is_writable($pdfDir) ? 'YES' : 'NO'));
    
    $pdf->Output('F', $outputPdf);
    
    error_log("PDF Output completato, controllo esistenza file");
    
    // Verifica che il PDF sia stato creato
    if (!file_exists($outputPdf)) {
        throw new Exception("PDF non generato correttamente");
    }
    
    // Sicurezza: Rimuovi immagine temporanea
    if (file_exists($tempImage)) {
        unlink($tempImage);
    }
    
} catch (Exception $e) {
    // Log dettagliato per debug
    error_log("Errore generazione PDF dettagliato: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " - Linea: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Controlla se le directory esistono
    if (isset($pdfDir)) {
        error_log("PDF Directory: " . $pdfDir . " - Exists: " . (is_dir($pdfDir) ? 'YES' : 'NO'));
        error_log("PDF Directory writable: " . (is_writable($pdfDir) ? 'YES' : 'NO'));
    }
    
    if (isset($tempImage)) {
        error_log("Temp Image: " . $tempImage . " - Exists: " . (file_exists($tempImage) ? 'YES' : 'NO'));
    }
    
    echo json_encode(['success' => false, 'message' => 'Errore generazione PDF: ' . $e->getMessage()]);
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
    
    $mail->setFrom($fromEmail, 'Mr.Charlie Lignano Sabbiadoro');
    $mail->addAddress($email, htmlspecialchars($nome . ' ' . $cognome, ENT_QUOTES, 'UTF-8'));
    
    // Sicurezza: Nome file attachment sicuro
    $attachmentName = 'Omaggio_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $nome . '_' . $eventoData['titolo']) . '.pdf';
    $mail->addAttachment($outputPdf, $attachmentName);
    
    $mail->isHTML(true);
    
    // Carica testi personalizzabili
    $emailTexts = getEmailTexts($pdo);
    
    // Variabili per sostituzione
    $emailVariables = [
        'nome' => $nome,
        'cognome' => $cognome,
        'evento' => $eventoData['titolo'],
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
    <title>Conferma Iscrizione - Mr.Charlie</title>
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
                                    Ciao " . htmlspecialchars($nome . ' ' . $cognome, ENT_QUOTES, 'UTF-8') . "
                                </h2>
                                <p style='margin: 0; font-size: 17px; line-height: 1.5; color: rgba(255, 255, 255, 0.8); font-weight: 400;'>
                                    " . htmlspecialchars(replaceEmailVariables($emailTexts['greeting_message'], $emailVariables), ENT_QUOTES, 'UTF-8') . "
                                </p>
                            </div>
                            
                            <!-- Box evento con effetto glass -->
                            <div style='margin: 35px 0; padding: 32px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.03) 100%); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 20px; backdrop-filter: blur(15px); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);'>
                                <div style='margin-bottom: 24px;'>
                                    <h3 style='margin: 0 0 8px 0; font-size: 24px; font-weight: 600; color: #ffffff; letter-spacing: -0.2px;'>
                                        " . htmlspecialchars($eventoData['titolo'], ENT_QUOTES, 'UTF-8') . "
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

Ciao " . htmlspecialchars($nome . ' ' . $cognome, ENT_QUOTES, 'UTF-8') . "

" . replaceEmailVariables($emailTexts['greeting_message'], $emailVariables) . "

DETTAGLI EVENTO:
Evento: " . htmlspecialchars($eventoData['titolo'], ENT_QUOTES, 'UTF-8') . "
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
    
    $mail->send();
    
    // Sicurezza: Aggiorna timestamp invio
    $updateStmt = $pdo->prepare("UPDATE utenti SET email_inviata = NOW() WHERE id = ?");
    $updateStmt->execute([$userId]);
    
    // Sicurezza: Rimuovi PDF dopo invio
    if (file_exists($outputPdf)) {
        unlink($outputPdf);
    }
    
    // Log specifico per modalità test
    if ($testMode) {
        error_log("Test System: Registrazione test completata con successo - Email: $email, Token: $token");
        echo json_encode([
            'success' => true, 
            'message' => 'Test sistema completato con successo! Email inviata.',
            'test_mode' => true,
            'details' => [
                'email' => $email,
                'nome' => $nome . ' ' . $cognome,
                'evento' => $eventoData['titolo'],
                'token' => $token
            ]
        ]);
    } else {
        echo json_encode(['success' => true, 'message' => 'Iscrizione avvenuta correttamente.']);
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

// Sicurezza: Pulizia finale
if (isset($tempImage) && file_exists($tempImage)) {
    unlink($tempImage);
}
if (isset($qrFile) && file_exists($qrFile)) {
    unlink($qrFile);
}

exit;
?>