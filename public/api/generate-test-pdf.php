<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo non supportato']);
    exit;
}

try {
    // Connessione diretta al database
    $host = 'mysql';
    $dbname = 'opium_events';
    $username = 'root';
    $password = 'docker_password';
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Leggi i dati JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $eventId = $input['event_id'] ?? null;
    
    if (!$eventId) {
        echo json_encode(['success' => false, 'message' => 'ID evento obbligatorio']);
        exit;
    }
    
    // Recupera i dati dell'evento
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = :id");
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Evento non trovato']);
        exit;
    }
    
    // Crea dati utente di test
    $testUserData = [
        'nome' => 'Mario',
        'cognome' => 'Rossi',
        'email' => 'mario.rossi@test.com',
        'telefono' => '1234567890',
        'data_nascita' => '1990-01-01',
        'evento' => $eventId,
        'token' => 'TEST_TOKEN_' . time()
    ];
    
    // Simula la generazione del PDF usando la logica di save_form.php
    // Per ora creiamo un PDF semplice con FPDF se disponibile
    
    $pdfDir = '/var/www/html/public/pdfs/';
    if (!file_exists($pdfDir)) {
        mkdir($pdfDir, 0777, true);
    }
    
    $pdfFileName = 'test_pdf_' . $eventId . '_' . time() . '.pdf';
    $pdfPath = $pdfDir . $pdfFileName;
    
    // Includi FPDF e QR Code
    require_once '/var/www/html/vendor/autoload.php';
    require_once '/var/www/html/lib/phpqrcode/qrlib.php';
    
    // Controlla se FPDF è disponibile
    if (class_exists('FPDF')) {
        // Usa le stesse specifiche di save_form.php
        $pdf = new FPDF();
        $pdf->AddPage('P', array(1080, 1920)); // Stesse dimensioni di save_form.php
        
        // Gestione immagine di background come in save_form.php
        $backgroundImagePath = null;
        $uploadsDir = '/var/www/html/public/uploads/';
        
        if (!empty($event['background_image'])) {
            $raw = $event['background_image'];
            
            // Prova diversi percorsi per l'immagine
            $possiblePaths = [
                $uploadsDir . basename($raw),
                '/var/www/html/public/uploads/' . basename($raw),
                '/var/www/html/public/' . basename($raw),
                $raw
            ];
            
            foreach ($possiblePaths as $bgPath) {
                if (file_exists($bgPath) && is_file($bgPath)) {
                    $realBgPath = realpath($bgPath);
                    if ($realBgPath) {
                        $backgroundImagePath = $realBgPath;
                        break;
                    }
                }
            }
        }
        
        // Fallback: usa immagine di default se disponibile
        if (!$backgroundImagePath) {
            $defaultPaths = [
                '/var/www/html/public/sfondo.png',
                '/var/www/html/public/default-bg.png'
            ];
            
            foreach ($defaultPaths as $path) {
                if (file_exists($path)) {
                    $backgroundImagePath = $path;
                    break;
                }
            }
        }
        
        // Genera immagine completa come in save_form.php
        $tempImagePath = '/var/www/html/public/generated_images/test_temp_' . $testUserData['token'] . '.png';
        
        // Crea directory se non esiste
        $tempDir = dirname($tempImagePath);
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        // Carica immagine di background
        $background = null;
        $bgWidth = 1080;
        $bgHeight = 1920;
        
        if ($backgroundImagePath && file_exists($backgroundImagePath)) {
            $mimeType = mime_content_type($backgroundImagePath);
            switch ($mimeType) {
                case 'image/png':
                    $background = imagecreatefrompng($backgroundImagePath);
                    break;
                case 'image/jpeg':
                case 'image/jpg':
                    $background = imagecreatefromjpeg($backgroundImagePath);
                    break;
            }
        }
        
        // Se non abbiamo un'immagine di background, crea una di default
        if (!$background) {
            $background = imagecreatetruecolor($bgWidth, $bgHeight);
            $bgColor = imagecolorallocate($background, 28, 28, 30); // Colore scuro elegante
            imagefill($background, 0, 0, $bgColor);
        }
        
        // Genera QR Code come in save_form.php
        $qrCodePath = '/var/www/html/public/qrcodes/test_qr_' . $testUserData['token'] . '.png';
        $qrDir = dirname($qrCodePath);
        if (!is_dir($qrDir)) {
            mkdir($qrDir, 0755, true);
        }
        
        if (class_exists('QRcode')) {
            QRcode::png($testUserData['token'], $qrCodePath, QR_ECLEVEL_H, 10, 1);
        }
        
        // Aggiungi QR Code all'immagine di background
        if (file_exists($qrCodePath)) {
            $qrImage = imagecreatefrompng($qrCodePath);
            if ($qrImage) {
                // Posizionamento QR Code come in save_form.php
                $qrBgX = 380;
                $qrBgY = 1026;
                $qrBgWidth = 510;
                $qrBgHeight = 510;
                
                // Sfondo bianco per QR Code
                $qrBgColor = imagecolorallocate($background, 255, 255, 255);
                imagefilledrectangle($background, $qrBgX, $qrBgY, $qrBgX + $qrBgWidth, $qrBgY + $qrBgHeight, $qrBgColor);
                
                // Copia QR Code sull'immagine
                imagecopyresampled($background, $qrImage, $qrBgX, $qrBgY, 0, 0, $qrBgWidth, $qrBgHeight, imagesx($qrImage), imagesy($qrImage));
                imagedestroy($qrImage);
            }
        }
        
        // Aggiungi testo all'immagine come in save_form.php
        $dataFormattata = date('d F Y', strtotime($event['event_date']));
        $textNome = $event['titolo'] . ' - ' . $dataFormattata . ' - ' . $testUserData['nome'] . ' ' . $testUserData['cognome'];
        
        // Font TTF se disponibile
        $fontFile = '/var/www/html/public/arial.ttf';
        if (!file_exists($fontFile)) {
            $fontFile = null;
        }
        
        $fontSize = 40;
        $paddingX = 50;
        $maxTextWidth = $bgWidth - ($paddingX * 2) - 20;
        
        // Calcolo dimensioni testo
        if ($fontFile && function_exists('imagettfbbox')) {
            $textBbox = imagettfbbox($fontSize, 0, $fontFile, $textNome);
            $textWidth = abs($textBbox[2] - $textBbox[0]);
            
            while ($textWidth > $maxTextWidth && $fontSize > 16) {
                $fontSize -= 2;
                $textBbox = imagettfbbox($fontSize, 0, $fontFile, $textNome);
                $textWidth = abs($textBbox[2] - $textBbox[0]);
            }
            
            $textHeight = abs($textBbox[7] - $textBbox[1]);
        } else {
            $textWidth = strlen($textNome) * 10;
            $textHeight = 20;
        }
        
        // Posizionamento testo
        $textMarginFromBottom = 150;
        $maxTextY = $bgHeight - $textMarginFromBottom;
        $preferredTextY = 1026 + 510 + 80; // Sotto il QR Code
        $textY = min($preferredTextY, $maxTextY);
        $minTextY = 1026 + 510 + 40;
        $textY = max($textY, $minTextY);
        
        // Sfondo testo
        $paddingY = 30;
        $rectY = max(0, $textY - $textHeight - $paddingY);
        $rectHeight = min($bgHeight - $rectY, $textHeight + ($paddingY * 2));
        $rectX = $paddingX;
        $rectWidth = $bgWidth - ($paddingX * 2);
        
        // Sfondo bianco per il testo con trasparenza
        $textWhite = imagecolorallocatealpha($background, 255, 255, 255, 60);
        imagefilledrectangle($background, $rectX, $rectY, $rectX + $rectWidth, $rectY + $rectHeight, $textWhite);
        
        // Bordo per migliore definizione
        $borderColor = imagecolorallocate($background, 200, 200, 200);
        imagerectangle($background, $rectX, $rectY, $rectX + $rectWidth, $rectY + $rectHeight, $borderColor);
        
        // Centra il testo nel rettangolo
        $textX = $rectX + (($rectWidth - $textWidth) / 2);
        $textX = max($rectX + 10, $textX);
        
        // Colore testo nero
        $textColor = imagecolorallocate($background, 0, 0, 0);
        
        // Scrivi testo
        if ($fontFile && function_exists('imagettftext')) {
            imagettftext($background, $fontSize, 0, $textX, $textY, $textColor, $fontFile, $textNome);
        } else {
            $systemFontSize = 5;
            $systemTextY = $textY - ($textHeight / 2);
            imagestring($background, $systemFontSize, $textX, $systemTextY, $textNome, $textColor);
        }
        
        // Salva immagine temporanea
        imagepng($background, $tempImagePath, 9, PNG_NO_FILTER);
        imagedestroy($background);
        
        // Verifica che l'immagine sia stata salvata
        if (file_exists($tempImagePath)) {
            // Aggiungi immagine completa al PDF
            $pdf->Image($tempImagePath, 0, 0, 1080, 1920);
        } else {
            // Fallback: crea un PDF semplice se l'immagine non è stata salvata
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, 'PDF di Test - ' . $event['titolo'], 0, 1, 'C');
            $pdf->Ln(10);
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 8, 'Data: ' . date('d/m/Y', strtotime($event['event_date'])), 0, 1, 'C');
            $pdf->Cell(0, 8, 'Utente: ' . $testUserData['nome'] . ' ' . $testUserData['cognome'], 0, 1, 'C');
            $pdf->Cell(0, 8, 'Token: ' . $testUserData['token'], 0, 1, 'C');
        }
        
        $pdf->Output('F', $pdfPath);
        
        // Pulisci file temporanei
        if (file_exists($qrCodePath)) {
            unlink($qrCodePath);
        }
        if (file_exists($tempImagePath)) {
            unlink($tempImagePath);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'PDF di test generato con successo',
            'pdf_url' => 'http://localhost:8000/pdfs/' . $pdfFileName,
            'pdf_path' => $pdfPath
        ]);
    } else {
        // Fallback: crea un file di testo se FPDF non è disponibile
        $textContent = "PDF DI TEST - " . $event['titolo'] . "\n\n";
        $textContent .= "Data evento: " . date('d/m/Y', strtotime($event['event_date'])) . "\n\n";
        $textContent .= "DETTAGLI PARTECIPANTE:\n";
        $textContent .= "Nome: " . $testUserData['nome'] . " " . $testUserData['cognome'] . "\n";
        $textContent .= "Email: " . $testUserData['email'] . "\n";
        $textContent .= "Telefono: " . $testUserData['telefono'] . "\n";
        $textContent .= "Data di nascita: " . date('d/m/Y', strtotime($testUserData['data_nascita'])) . "\n\n";
        $textContent .= "QR CODE: " . $testUserData['token'] . "\n\n";
        $textContent .= "Conserva questo documento e presentalo all'ingresso dell'evento.\n";
        $textContent .= "Opium Club Pordenone\n";
        
        $txtFileName = 'test_pdf_' . $eventId . '_' . time() . '.txt';
        $txtPath = $pdfDir . $txtFileName;
        file_put_contents($txtPath, $textContent);
        
        echo json_encode([
            'success' => true,
            'message' => 'Documento di test generato (FPDF non disponibile)',
            'pdf_url' => 'http://localhost:8000/pdfs/' . $txtFileName,
            'pdf_path' => $txtPath,
            'note' => 'FPDF non è installato. Installare FPDF per generare PDF veri.'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Test PDF generation error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Errore del server: ' . $e->getMessage()
    ]);
}
?>
