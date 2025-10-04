<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
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
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Recupera utenti con compleanno oggi
        $today = date('m-d');
        $stmt = $pdo->query("
            SELECT u.*, e.titolo as evento_titolo 
            FROM utenti u 
            LEFT JOIN events e ON u.evento = e.id 
            WHERE DATE_FORMAT(u.data_nascita, '%m-%d') = '$today'
            ORDER BY u.nome, u.cognome
        ");
        $birthdayUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recupera template attivi
        $stmt = $pdo->query("SELECT * FROM birthday_templates WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
        $activeTemplate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Recupera statistiche compleanni
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_birthdays_today,
                COUNT(CASE WHEN bs.id IS NOT NULL THEN 1 END) as sent_today,
                COUNT(CASE WHEN bs.id IS NULL THEN 1 END) as pending_today
            FROM utenti u 
            LEFT JOIN birthday_sent bs ON u.email = bs.user_email AND DATE(bs.sent_at) = CURDATE()
            WHERE DATE_FORMAT(u.data_nascita, '%m-%d') = '$today'
        ");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Recupera template disponibili
        $stmt = $pdo->query("SELECT * FROM birthday_templates ORDER BY created_at DESC");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'birthday_users' => $birthdayUsers,
                'active_template' => $activeTemplate,
                'templates' => $templates,
                'stats' => $stats,
                'today' => $today
            ]
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Leggi i dati JSON
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'send_birthday') {
            $userId = $input['user_id'] ?? null;
            $templateId = $input['template_id'] ?? null;
            
            if (!$userId || !$templateId) {
                echo json_encode(['success' => false, 'message' => 'ID utente e template obbligatori']);
                exit;
            }
            
            // Recupera utente
            $stmt = $pdo->prepare("SELECT * FROM utenti WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Utente non trovato']);
                exit;
            }
            
            // Recupera template
            $stmt = $pdo->prepare("SELECT * FROM birthday_templates WHERE id = :id");
            $stmt->execute([':id' => $templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                echo json_encode(['success' => false, 'message' => 'Template non trovato']);
                exit;
            }
            
            // Controlla se già inviato oggi
            $stmt = $pdo->prepare("SELECT id FROM birthday_sent WHERE user_email = :user_email AND DATE(sent_at) = CURDATE()");
            $stmt->execute([':user_email' => $user['email']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Auguri già inviati oggi']);
                exit;
            }
            
            // Simula invio email (per ora)
            // TODO: Implementare invio email reale con PHPMailer
            
            // Registra l'invio
            $stmt = $pdo->prepare("INSERT INTO birthday_sent (user_email, user_name, birthday_date, sent_year, template_id, sent_at) VALUES (:user_email, :user_name, :birthday_date, :sent_year, :template_id, NOW())");
            $stmt->execute([
                ':user_email' => $user['email'],
                ':user_name' => $user['nome'] . ' ' . $user['cognome'],
                ':birthday_date' => $user['data_nascita'],
                ':sent_year' => date('Y'),
                ':template_id' => $templateId
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Auguri inviati con successo a ' . $user['nome'] . ' ' . $user['cognome']
            ]);
            
        } elseif ($action === 'create_template') {
            $name = $input['name'] ?? '';
            $subject = $input['subject'] ?? '';
            $htmlContent = $input['html_content'] ?? '';
            $backgroundImage = $input['background_image'] ?? null;
            
            if (empty($name) || empty($subject) || empty($htmlContent)) {
                echo json_encode(['success' => false, 'message' => 'Nome, oggetto e contenuto sono obbligatori']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO birthday_templates (name, subject, html_content, background_image, is_active) 
                VALUES (:name, :subject, :html_content, :background_image, 0)
            ");
            $stmt->execute([
                ':name' => $name,
                ':subject' => $subject,
                ':html_content' => $htmlContent,
                ':background_image' => $backgroundImage
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template creato con successo',
                'template_id' => $pdo->lastInsertId()
            ]);
            
        } elseif ($action === 'activate_template') {
            $templateId = $input['template_id'] ?? null;
            
            if (!$templateId) {
                echo json_encode(['success' => false, 'message' => 'ID template obbligatorio']);
                exit;
            }
            
            // Disattiva tutti i template
            $pdo->exec("UPDATE birthday_templates SET is_active = 0");
            
            // Attiva il template selezionato
            $stmt = $pdo->prepare("UPDATE birthday_templates SET is_active = 1 WHERE id = :id");
            $stmt->execute([':id' => $templateId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template attivato con successo'
            ]);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Azione non supportata']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Metodo non supportato']);
    }
    
} catch (Exception $e) {
    error_log('Birthday API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Errore del server: ' . $e->getMessage()
    ]);
}
?>
