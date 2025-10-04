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
    // Verifica se è stato caricato un file
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Nessun file caricato o errore nel caricamento']);
        exit;
    }
    
    $file = $_FILES['image'];
    $uploadType = $_POST['type'] ?? 'general'; // 'hero', 'background', 'general'
    
    // Validazione file
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Tipo file non supportato. Usa JPG, PNG o GIF']);
        exit;
    }
    
    // Limite dimensione (10MB per immagini PDF)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File troppo grande. Massimo 10MB']);
        exit;
    }
    
    // Controllo dimensioni immagine per PDF background
    if ($uploadType === 'pdf_background') {
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            echo json_encode(['success' => false, 'message' => 'File immagine non valido']);
            exit;
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        // Dimensioni consigliate per PDF (1080x1920 o proporzioni simili)
        $recommendedWidth = 1080;
        $recommendedHeight = 1920;
        $aspectRatio = $recommendedWidth / $recommendedHeight; // 0.5625
        
        // Controlla se le dimensioni sono accettabili
        $currentAspectRatio = $width / $height;
        $tolerance = 0.2; // 20% di tolleranza
        
        // Controlla se l'immagine è troppo piccola
        if ($width < 800 || $height < 1200) {
            echo json_encode([
                'success' => false, 
                'message' => 'Immagine troppo piccola. Dimensioni minime consigliate: 800x1200px',
                'current_dimensions' => $width . 'x' . $height,
                'recommended_dimensions' => '1080x1920px'
            ]);
            exit;
        }
        
        // Controlla se l'aspect ratio è troppo diverso
        if (abs($currentAspectRatio - $aspectRatio) > $tolerance) {
            echo json_encode([
                'success' => false, 
                'message' => 'Proporzioni immagine non ottimali per PDF. Proporzioni consigliate: 9:16 (verticale)',
                'current_dimensions' => $width . 'x' . $height,
                'current_ratio' => round($currentAspectRatio, 2),
                'recommended_ratio' => round($aspectRatio, 2),
                'recommended_dimensions' => '1080x1920px'
            ]);
            exit;
        }
        
        // Avviso se le dimensioni non sono perfette ma accettabili
        if ($width !== $recommendedWidth || $height !== $recommendedHeight) {
            if ($width > $recommendedWidth || $height > $recommendedHeight) {
                $warning = "Immagine più grande delle dimensioni consigliate. Verrà ridimensionata automaticamente a {$recommendedWidth}x{$recommendedHeight}px";
            } else {
                $warning = "Immagine più piccola delle dimensioni consigliate. Dimensioni consigliate: {$recommendedWidth}x{$recommendedHeight}px";
            }
        }
    }
    
    // Crea directory se non esiste
    $uploadDir = "/var/www/html/public/uploads/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Genera nome file unico
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = $uploadType . '_' . uniqid() . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $fileName;
    
    // Sposta il file
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Se è un'immagine hero, aggiorna il database
        if ($uploadType === 'hero') {
            $eventId = $_POST['event_id'] ?? null;
            if ($eventId) {
                // Connessione al database
                $host = 'mysql';
                $dbname = 'opium_events';
                $username = 'root';
                $password = 'docker_password';
                
                $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
                $pdo = new PDO($dsn, $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $stmt = $pdo->prepare("UPDATE events SET background_image = :image_path WHERE id = :event_id");
                $stmt->execute([
                    ':image_path' => '/uploads/' . $fileName,
                    ':event_id' => $eventId
                ]);
            }
        }
        
        $response = [
            'success' => true,
            'message' => 'Immagine caricata con successo',
            'data' => [
                'filename' => $fileName,
                'path' => '/uploads/' . $fileName,
                'url' => 'http://localhost:8000/image-proxy.php?file=' . urlencode($fileName),
                'direct_url' => 'http://localhost:8000/uploads/' . $fileName,
                'size' => $file['size'],
                'type' => $file['type']
            ]
        ];
        
        // Aggiungi avviso se presente
        if (isset($warning)) {
            $response['warning'] = $warning;
        }
        
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore durante il salvataggio del file']);
    }
    
} catch (Exception $e) {
    error_log('Image upload error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Errore del server: ' . $e->getMessage()
    ]);
}
?>
