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
    
} catch (Exception $e) {
    error_log("Errore bootstrap: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore di configurazione']);
    exit;
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

// Ricevi dati dal POST
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dati non validi']);
    exit;
}

$nome = $input['nome'] ?? '';
$cognome = $input['cognome'] ?? '';
$email = $input['email'] ?? '';
$telefono = $input['telefono'] ?? '';
$dataNascita = $input['data_nascita'] ?? '';
$eventoId = $input['evento_id'] ?? '';
$token = $input['token'] ?? '';
$testMode = $input['test_mode'] ?? false;

// Verifica esistenza evento
try {
    $stmt = $pdo->prepare("
        SELECT id, event_date, titolo, background_image, chiuso
        FROM events
        WHERE id = ? AND COALESCE(chiuso, 0) = 0
        LIMIT 1
    ");
    $stmt->execute([$eventoId]);
    $eventoData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$eventoData) {
        echo json_encode(['success' => false, 'message' => 'Evento non valido']);
        exit;
    }
} catch (PDOException $e) {
    error_log("Errore query evento: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore verifica evento']);
    exit;
}

// Sicurezza: Inserimento database con transazione
try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO utenti (nome, cognome, email, telefono, data_nascita, evento, qr_code_path, token, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, '', ?, NOW())
    ");
    $stmt->execute([
        $nome,
        $cognome,
        $email,
        $telefono,
        $dataNascita,
        $eventoData['id'],
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

// Generazione QR Code sicura usando phpqrcode
try {
    // Controlla se la libreria phpqrcode esiste
    $qrLibPath = __DIR__ . '/../../lib/phpqrcode/qrlib.php';
    if (!file_exists($qrLibPath)) {
        throw new Exception("Libreria phpqrcode non trovata in: " . $qrLibPath);
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
    
    // Genera QR Code usando phpqrcode (bianco e nero standard)
    QRcode::png($token, $qrFile, QR_ECLEVEL_H, 10, 1);
    
    // Verifica che il file sia stato creato
    if (!file_exists($qrFile)) {
        throw new Exception("QR Code non generato correttamente");
    }
    
    error_log('[PDF] QR Code generato: ' . $qrFile);
    
} catch (Exception $e) {
    error_log("Errore generazione QR: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore generazione QR Code: ' . $e->getMessage()]);
    exit;
}

// Sicurezza: Generazione immagine sicura
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
    $uploadsDir = $config['paths']['uploads_dir'] ?? (__DIR__ . '/../uploads/');
    $backgroundImagePath = null;
    
    if (!empty($eventoData['background_image'])) {
         $raw = $eventoData['background_image'];
         error_log('[PDF] Background raw value: ' . $raw);
         error_log('[PDF] Uploads dir: ' . $uploadsDir);
         
         // Se è un URL (es. GCS), scarica in /tmp
         if (filter_var($raw, FILTER_VALIDATE_URL)) {
             $tmpDir = sys_get_temp_dir();
             $tmpFile = $tmpDir . '/' . basename(parse_url($raw, PHP_URL_PATH));
             $data = @file_get_contents($raw);
             if ($data !== false) {
                 file_put_contents($tmpFile, $data);
                 $backgroundImagePath = $tmpFile;
                 error_log('[PDF] Downloaded background from URL to: ' . $backgroundImagePath);
             }
         }
         // Altrimenti prova nella cartella uploads locale
         if (!$backgroundImagePath) {
             // Prova diversi percorsi per l'immagine
             $possiblePaths = [
                 $uploadsDir . basename($raw), // Percorso standard
                 __DIR__ . '/../uploads/' . basename($raw), // Percorso relativo da public
                 __DIR__ . '/../../public/uploads/' . basename($raw), // Percorso assoluto
                 '/var/www/html/public/uploads/' . basename($raw), // Percorso Docker
                 '/tmp/uploads/' . basename($raw), // Percorso temporaneo
                 $raw, // Percorso completo se già assoluto
                 '/var/www/html/public' . $raw // Percorso con prefisso /uploads/
             ];
             
             foreach ($possiblePaths as $bgPath) {
                 error_log('[PDF] Trying path: ' . $bgPath);
                 if (file_exists($bgPath) && is_file($bgPath)) {
                     // Verifica sicurezza del percorso
                     $realBgPath = realpath($bgPath);
                     if ($realBgPath) {
                         $backgroundImagePath = $realBgPath;
                         error_log('[PDF] Found background at: ' . $backgroundImagePath);
                         break;
                     }
                 }
             }
         }
    }
    
    // Fallback sicuro
    if (!$backgroundImagePath) {
        // Prova diversi percorsi per l'immagine di default
        $defaultPaths = [
            __DIR__ . '/../sfondo_default.png',
            __DIR__ . '/../sfondo.png',
            __DIR__ . '/../background_default.png',
            __DIR__ . '/../default.png'
        ];
        
        foreach ($defaultPaths as $path) {
            if (file_exists($path)) {
                $backgroundImagePath = $path;
                error_log('[PDF] Using fallback background: ' . $backgroundImagePath);
                break;
            }
        }
    }
    
    if (!$backgroundImagePath || !file_exists($backgroundImagePath)) {
        error_log('[PDF] No background image found, creating default');
        error_log('[PDF] Raw background value was: ' . ($eventoData['background_image'] ?? 'null'));
        error_log('[PDF] Uploads directory: ' . $uploadsDir);
        
        // Crea un'immagine di sfondo predefinita al volo
        $backgroundImagePath = $tempImage . '_bg.png';
        $defaultBg = imagecreatetruecolor(1080, 1920);
        $bgColor = imagecolorallocate($defaultBg, 28, 28, 30); // Colore scuro elegante
        imagefill($defaultBg, 0, 0, $bgColor);
        
        // Aggiungi testo "Mr.Charlie Event"
        $white = imagecolorallocate($defaultBg, 255, 255, 255);
        $fontSize = 5;
        $text = "Event";
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textHeight = imagefontheight($fontSize);
        $x = (1080 - $textWidth) / 2;
        $y = (1920 - $textHeight) / 2;
        imagestring($defaultBg, $fontSize, $x, $y, $text, $white);
        
        imagepng($defaultBg, $backgroundImagePath);
        imagedestroy($defaultBg);
        
        error_log('[PDF] Created default background at: ' . $backgroundImagePath);
    } else {
        error_log('[PDF] Using background image: ' . $backgroundImagePath);
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
    
    // Ridimensionamento automatico se necessario
    $originalWidth = imagesx($background);
    $originalHeight = imagesy($background);
    $targetWidth = 1080;
    $targetHeight = 1920;
    
    error_log('[PDF] Immagine originale: ' . $originalWidth . 'x' . $originalHeight);
    
    // Se l'immagine è più grande del target, ridimensiona
    if ($originalWidth > $targetWidth || $originalHeight > $targetHeight) {
        error_log('[PDF] Ridimensionamento necessario da ' . $originalWidth . 'x' . $originalHeight . ' a ' . $targetWidth . 'x' . $targetHeight);
        
        // Calcola le proporzioni per mantenere l'aspect ratio
        $scaleX = $targetWidth / $originalWidth;
        $scaleY = $targetHeight / $originalHeight;
        $scale = min($scaleX, $scaleY); // Usa la scala più piccola per mantenere le proporzioni
        
        $newWidth = intval($originalWidth * $scale);
        $newHeight = intval($originalHeight * $scale);
        
        // Crea nuova immagine con dimensioni target
        $resizedBackground = imagecreatetruecolor($targetWidth, $targetHeight);
        
        // Abilita alpha blending per la nuova immagine
        imagealphablending($resizedBackground, false);
        imagesavealpha($resizedBackground, true);
        
        // Riempie con colore di sfondo (nero)
        $bgColor = imagecolorallocate($resizedBackground, 28, 28, 30);
        imagefill($resizedBackground, 0, 0, $bgColor);
        
        // Centra l'immagine ridimensionata
        $offsetX = intval(($targetWidth - $newWidth) / 2);
        $offsetY = intval(($targetHeight - $newHeight) / 2);
        
        // Copia e ridimensiona l'immagine originale
        imagecopyresampled(
            $resizedBackground, $background,
            $offsetX, $offsetY, 0, 0,
            $newWidth, $newHeight, $originalWidth, $originalHeight
        );
        
        // Sostituisci l'immagine originale con quella ridimensionata
        imagedestroy($background);
        $background = $resizedBackground;
        
        error_log('[PDF] Immagine ridimensionata a: ' . $targetWidth . 'x' . $targetHeight . ' (offset: ' . $offsetX . ',' . $offsetY . ')');
    } else {
        error_log('[PDF] Immagine già nelle dimensioni corrette o più piccola');
    }
    
    // Abilita alpha blending per gestire correttamente la trasparenza
    imagealphablending($background, true);
    imagesavealpha($background, true);
    
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
    
    // Sfondo QR - area specifica intorno al QR code
    $qrPadding = 20;
    $qrBgX = max(0, $destX - $qrPadding);
    $qrBgY = max(0, $destY - $qrPadding);
    $qrBgWidth = min($bgWidth - $qrBgX, $qrWidth + ($qrPadding * 2));
    $qrBgHeight = min($bgHeight - $qrBgY, $qrHeight + ($qrPadding * 2));
    
    // Usa bianco con trasparenza come negli altri file per coerenza
    $white = imagecolorallocatealpha($background, 255, 255, 255, 60);
    error_log('[PDF] White color allocated with alpha: ' . $white);
    imagefilledrectangle($background, $qrBgX, $qrBgY, $qrBgX + $qrBgWidth, $qrBgY + $qrBgHeight, $white);
    
    error_log('[PDF] QR background: X=' . $qrBgX . ', Y=' . $qrBgY . ', W=' . $qrBgWidth . ', H=' . $qrBgHeight);
    
    // Copia QR
    imagecopy($background, $qrCodeImg, $destX, $destY, 0, 0, $qrWidth, $qrHeight);
    
    // Sicurezza: Validazione font
    $fontFile = __DIR__ . '/../arial.ttf';
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
    $paddingX = 50; // Definisco padding qui per coerenza
    $maxTextWidth = $bgWidth - ($paddingX * 2) - 20; // Lascia margine interno nel rettangolo
    
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
    
    // Posiziona il testo in modo sicuro - sempre visibile
    $textMarginFromBottom = 150; // Margine dal fondo dell'immagine
    $maxTextY = $bgHeight - $textMarginFromBottom;
    $preferredTextY = $qrBgY + $qrBgHeight + 80;
    $textY = min($preferredTextY, $maxTextY);
    
    // Assicurati che il testo non vada troppo in alto
    $minTextY = $qrBgY + $qrBgHeight + 40;
    $textY = max($textY, $minTextY);
    
    // Sfondo testo - area più ampia per migliore leggibilità
    $paddingY = 30;
    // $paddingX già definito sopra per coerenza
    $rectY = max(0, $textY - $textHeight - $paddingY);
    $rectHeight = min($bgHeight - $rectY, $textHeight + ($paddingY * 2));
    $rectX = $paddingX;
    $rectWidth = $bgWidth - ($paddingX * 2);
    
    // Sfondo bianco per il testo con trasparenza come negli altri file
    $textWhite = imagecolorallocatealpha($background, 255, 255, 255, 60);
    imagefilledrectangle($background, $rectX, $rectY, $rectX + $rectWidth, $rectY + $rectHeight, $textWhite);
    
    // Bordo per migliore definizione
    $borderColor = imagecolorallocate($background, 200, 200, 200);
    imagerectangle($background, $rectX, $rectY, $rectX + $rectWidth, $rectY + $rectHeight, $borderColor);
    
    // CORREZIONE: Centra il testo DENTRO il rettangolo bianco
    $textX = $rectX + (($rectWidth - $textWidth) / 2);
    $textX = max($rectX + 10, $textX); // Margine minimo dal bordo
    
    // Scrivi testo con colore nero ben visibile e forte contrasto
    $textColor = imagecolorallocate($background, 0, 0, 0); // Nero puro per massimo contrasto
    
    error_log('[PDF] Text position: X=' . $textX . ', Y=' . $textY . ', Text=' . $textNome);
    error_log('[PDF] Text area: rectX=' . $rectX . ', rectY=' . $rectY . ', rectW=' . $rectWidth . ', rectH=' . $rectHeight);
    error_log('[PDF] Background dimensions: W=' . $bgWidth . ', H=' . $bgHeight);
    
    if ($fontFile && function_exists('imagettftext')) {
        // Usa font TTF se disponibile
        imagettftext($background, $fontSize, 0, $textX, $textY, $textColor, $fontFile, $textNome);
        error_log('[PDF] Testo scritto con TTF font, size=' . $fontSize);
    } else {
        // Fallback a font di sistema più grande
        $systemFontSize = 5;
        $systemTextY = $textY - ($textHeight / 2); // Aggiusta posizione per font di sistema
        imagestring($background, $systemFontSize, $textX, $systemTextY, $textNome, $textColor);
        error_log('[PDF] Testo scritto con font di sistema, size=' . $systemFontSize);
    }
    
    // Salva immagine temporanea SENZA interlacciamento per compatibilità FPDF
    if (!imagepng($background, $tempImage, 9, PNG_NO_FILTER)) {
        throw new Exception("Errore salvataggio immagine");
    }
    
    error_log('[PDF] Temp image saved successfully: ' . $tempImage);
    error_log('[PDF] Image file size: ' . filesize($tempImage) . ' bytes');
    
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
    $fpdfPath = __DIR__ . '/../../lib/fpdf/fpdf.php';
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
    
    // Aggiorna database con percorso QR Code
    $stmt = $pdo->prepare("UPDATE utenti SET qr_code_path = ? WHERE id = ?");
    $stmt->execute([$qrFile, $userId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'PDF generato con successo',
        'data' => [
            'pdf_path' => $outputPdf,
            'pdf_url' => 'http://localhost:8000/generated_pdfs/' . basename($outputPdf),
            'user_id' => $userId,
            'token' => $token
        ]
    ]);
    
} catch (Exception $e) {
    // Log dettagliato per debug
    error_log("Errore generazione PDF dettagliato: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " - Linea: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode(['success' => false, 'message' => 'Errore generazione PDF: ' . $e->getMessage()]);
    exit;
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
