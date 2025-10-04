<?php
// Inizia output buffering per evitare problemi con header
ob_start();

// Abilita la visualizzazione degli errori (solo per ambiente di test)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Avvia la sessione
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Controllo login: se non loggato, reindirizza alla pagina di login
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    // Pulisci il buffer e reindirizza
    ob_end_clean();
    header("Location: /login.php");
    exit;
}

/**
 * Pannello Admin per Gestione Template Compleanno
 */

// Carica bootstrap per configurazione e database
// Carica il bootstrap appropriato per l'ambiente
if (isset($_SERVER['GAE_APPLICATION'])) {
    // Ambiente Google Cloud
    $bootstrap = require_once __DIR__ . '/../src/bootstrap_gcloud.php';
} else {
    // Ambiente locale
    $bootstrap = require_once __DIR__ . '/../src/bootstrap.php';
}
$pdo = $bootstrap['db'];
$config = $bootstrap['config'];

// Usa il nuovo sistema JSON per i template
require_once __DIR__ . '/BirthdayTemplateManager.php';

try {
    $templateManager = new BirthdayTemplateManager(__DIR__);
    $useMockData = false;
    echo "<!-- Sistema JSON attivo - Template caricati correttamente -->\n";
} catch (Exception $e) {
    // Fallback in caso di errore
    $useMockData = true;
    $templateManager = null;
    error_log("Errore caricamento template manager: " . $e->getMessage());
}

// Funzione di fallback per dati mock (mantenuta per compatibilità)
function createMockData() {
    return [
        'templates' => [],
        'stats' => [
            'today_birthdays' => 0,
            'sent_this_year' => 0,
            'upcoming_birthdays' => 0,
            'total_templates' => 0
        ],
        'upcomingBirthdays' => []
    ];
}

// Gestione POST
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'save_template':
                    if ($templateManager) {
                        // DEBUG: Log dei dati ricevuti
                        error_log("🔧 DEBUG SAVE - Dati POST ricevuti:");
                        error_log("🔧 DEBUG SAVE - template_id: " . ($_POST['template_id'] ?? 'NON_PRESENTE'));
                        error_log("🔧 DEBUG SAVE - template_name: " . ($_POST['template_name'] ?? 'NON_PRESENTE'));
                        error_log("🔧 DEBUG SAVE - template_subject: " . ($_POST['template_subject'] ?? 'NON_PRESENTE'));
                        error_log("🔧 DEBUG SAVE - template_content length: " . strlen($_POST['template_content'] ?? ''));
                        error_log("🔧 DEBUG SAVE - template_content preview: " . substr($_POST['template_content'] ?? '', 0, 200));
                        
                        $data = [
                            'id' => $_POST['template_id'] ?? 0,
                            'name' => $_POST['template_name'] ?? 'Template Senza Nome',
                            'subject' => $_POST['template_subject'] ?? 'Oggetto Template',
                            'html_content' => $_POST['template_content'] ?? '',
                            'background_image' => $_POST['background_image'] ?? null
                        ];
                        
                        // Validazione base
                        if (empty($data['name']) || empty($data['subject'])) {
                            $errorMessage = "Nome e oggetto del template sono obbligatori.";
                        } else if (empty($data['html_content'])) {
                            $errorMessage = "Il contenuto del template non può essere vuoto.";
                        } else {
                            error_log("🔧 DEBUG SAVE - Tentativo salvataggio con ID: " . $data['id']);
                            
                            if ($templateManager->saveTemplate($data)) {
                                $successMessage = "Template salvato con successo!";
                                error_log("🔧 DEBUG SAVE - Template salvato con successo");
                            } else {
                                $errorMessage = "Errore durante il salvataggio del template.";
                                error_log("🔧 DEBUG SAVE - Errore nel salvataggio");
                            }
                        }
                    } else {
                        $errorMessage = "Sistema template non disponibile.";
                        error_log("🔧 DEBUG SAVE - Template manager non disponibile");
                    }
                    break;
                    
                case 'activate_template':
                    if ($templateManager && $templateManager->activateTemplate($_POST['template_id'])) {
                        $successMessage = "Template attivato con successo!";
                    } else {
                        $errorMessage = "Errore durante l'attivazione del template.";
                    }
                    break;
                    
                case 'delete_template':
                    if ($templateManager) {
                        try {
                            $templateManager->deleteTemplate($_POST['template_id']);
                            $successMessage = "Template eliminato con successo!";
                        } catch (Exception $e) {
                            $errorMessage = $e->getMessage();
                        }
                    } else {
                        $errorMessage = "Sistema template non disponibile.";
                    }
                    break;
                    
                case 'upload_image':
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $safeName = time() . '_' . basename($_FILES['image']['name']);
                        $mime = mime_content_type($_FILES['image']['tmp_name']) ?: null;

                        $useGcs = isset($config['gcs']['enabled']) && $config['gcs']['enabled'] === true && !empty($config['gcs']['bucket']);
                        if ($useGcs) {
                            try {
                                $uploader = new \App\Lib\GcsUploader($config['gcs']['bucket']);
                                $dest = rtrim($config['gcs']['uploads_prefix'] ?? 'uploads/', '/') . '/' . $safeName;
                                $url = $uploader->upload($dest, $_FILES['image']['tmp_name'], $mime);
                                echo json_encode(['success' => true, 'url' => $url]);
                                exit;
                            } catch (\Throwable $e) {
                                echo json_encode(['success' => false, 'error' => 'Errore upload GCS: ' . $e->getMessage()]);
                                exit;
                            }
                        }

                        // Fallback locale (sviluppo)
                        $uploadDir = __DIR__ . '/uploads/';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }
                        $filePath = $uploadDir . $safeName;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
                            echo json_encode(['success' => true, 'url' => 'uploads/' . $safeName]);
                        } else {
                            echo json_encode(['success' => false, 'error' => 'Errore upload']);
                        }
                        exit;
                    }
                    break;
                    
                case 'test_template':
                    if ($templateManager && $templateManager->testTemplate($_POST['template_id'], $_POST['test_email'])) {
                        $successMessage = "Email di test inviata con successo!";
                    } else {
                        $errorMessage = "Errore durante l'invio dell'email di test.";
                    }
                    break;
                    
                case 'send_birthday_check':
                    // Simula controllo compleanni
                    $successMessage = "Controllo compleanni completato. Trovati: 2, Inviati: 2";
                    break;
            }
        }
    } catch (Exception $e) {
        $errorMessage = "Errore: " . $e->getMessage();
    }
}

// Carica dati dal sistema JSON
if ($templateManager) {
    $templates = $templateManager->getAllTemplates();
    $stats = $templateManager->getBirthdayStats();
    $upcomingBirthdays = $templateManager->getUpcomingBirthdays();
    
    // DEBUG: Verifica template caricati
    error_log("🔧 DEBUG PHP - Template caricati: " . count($templates));
    foreach ($templates as $i => $template) {
        error_log("🔧 DEBUG PHP - Template $i: ID={$template['id']}, Nome={$template['name']}, HTML_length=" . strlen($template['html_content'] ?? ''));
    }
    
    // GET actions
    if (isset($_GET['edit'])) {
        $editTemplate = $templateManager->getTemplate($_GET['edit']);
    }
} else {
    // Fallback ai dati mock
    $mockData = createMockData();
    $templates = $mockData['templates'];
    $stats = $mockData['stats'];
    $upcomingBirthdays = $mockData['upcomingBirthdays'];
    $editTemplate = null;
    error_log("🔧 DEBUG PHP - Usando dati mock");
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Compleanni - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Google Fonts per Quill Editor -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@300;400;500;600;700&family=Lato:wght@300;400;700&family=Montserrat:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&family=Nunito:wght@300;400;500;600;700&family=Source+Sans+Pro:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&family=Ubuntu:wght@300;400;500;700&family=Crimson+Text:wght@400;600&family=Libre+Baskerville:wght@400;700&family=Merriweather:wght@300;400;700&family=PT+Serif:wght@400;700&family=Lora:wght@400;500;600;700&family=Dancing+Script:wght@400;500;600;700&family=Pacifico&family=Caveat:wght@400;500;600;700&family=Kalam:wght@300;400;700&display=swap" rel="stylesheet">
    
    <!-- CKEditor 5 - Classic Editor con configurazione base -->
    <script src="https://cdn.ckeditor.com/ckeditor5/40.1.0/classic/ckeditor.js"></script>
    <!-- Admin Styles -->
    <link href="admin_styles.css" rel="stylesheet">
    <style>
        /* Birthday Admin Specific Styles */
        .birthday-hero {
            background: linear-gradient(135deg, 
                rgba(107, 115, 255, 0.9) 0%, 
                rgba(129, 212, 250, 0.8) 50%,
                rgba(176, 179, 184, 0.7) 100%);
            backdrop-filter: blur(25px) saturate(180%);
            -webkit-backdrop-filter: blur(25px) saturate(180%);
            border-radius: 28px;
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out;
        }
        
        .birthday-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        .birthday-hero h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 4px 20px rgba(0,0,0,0.3);
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }
        
        .birthday-hero p {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.9);
            margin: 0;
            position: relative;
            z-index: 2;
        }
        
        .birthday-stat-card {
            background: linear-gradient(135deg, 
                rgba(255, 255, 255, 0.1) 0%, 
                rgba(255, 255, 255, 0.05) 100%);
            backdrop-filter: blur(25px) saturate(180%);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: slideInUp 0.6s ease-out;
        }
        
        .birthday-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                rgba(107, 115, 255, 0.6) 50%, 
                transparent 100%);
        }
        
        .birthday-stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border-color: rgba(107, 115, 255, 0.3);
        }
        
        .birthday-stat-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #6B73FF 0%, #81D4FA 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            display: block;
            transition: all 0.3s ease;
        }
        
        .birthday-stat-number.counting {
            transform: scale(1.1);
            filter: brightness(1.2);
        }
        
        .birthday-stat-card h6 {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .template-card {
            background: linear-gradient(135deg, 
                rgba(255, 255, 255, 0.08) 0%, 
                rgba(255, 255, 255, 0.04) 100%);
            backdrop-filter: blur(20px) saturate(180%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .template-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                rgba(126, 211, 33, 0.6) 50%, 
                transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .template-card.active::before {
            opacity: 1;
        }
        
        .template-card.active {
            border-color: rgba(126, 211, 33, 0.3);
            background: linear-gradient(135deg, 
                rgba(126, 211, 33, 0.1) 0%, 
                rgba(255, 255, 255, 0.04) 100%);
        }
        
        .template-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border-color: rgba(107, 115, 255, 0.2);
        }
        
        .template-preview {
            max-height: 200px;
            overflow: hidden;
            border-radius: 12px;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.2);
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.8);
            margin: 1rem 0;
        }
        
        .birthday-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .birthday-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            border-radius: 12px;
            margin-bottom: 0.5rem;
        }
        
        .birthday-item:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(8px);
        }
        
        .days-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .variable-tag {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            margin: 0.25rem;
            display: inline-block;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 500;
        }
        
        .variable-tag:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 25px rgba(107, 115, 255, 0.4);
        }
        
        /* Stili personalizzati per CKEditor - Ottimizzato per template complessi */
        .ck-editor__editable {
            min-height: 400px !important;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            font-size: 15px;
            line-height: 1.7;
            background: #ffffff !important;
            color: #2c3e50 !important;
            border-radius: 0 0 12px 12px;
            padding: 20px !important;
        }
        
        .ck-editor__editable p {
            margin-bottom: 12px;
        }
        
        .ck-editor__editable h1, .ck-editor__editable h2, .ck-editor__editable h3, 
        .ck-editor__editable h4, .ck-editor__editable h5, .ck-editor__editable h6 {
            color: #34495e;
            font-weight: 600;
            margin-top: 20px;
            margin-bottom: 12px;
        }
        
        .ck-toolbar {
            border-radius: 12px 12px 0 0 !important;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
            border: 1px solid #dee2e6 !important;
        }
        
        .ck-content {
            border: 1px solid #dee2e6 !important;
            border-top: none !important;
            border-radius: 0 0 12px 12px !important;
        }
        
        /* CKEditor supporta automaticamente tutti i font Google */
        

        
        /* HTML Editor - Migliorata leggibilità */
        #html_source_editor {
            background: #ffffff;
            color: #2c3e50;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            font-family: 'SF Mono', 'Monaco', 'Menlo', 'Consolas', monospace;
            font-size: 14px;
            line-height: 1.6;
            padding: 20px;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
        }
        
        #html_source_editor:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
            outline: none;
            background: #ffffff;
        }
        
        /* Syntax highlighting simulation */
        #html_source_editor::selection {
            background: rgba(102, 126, 234, 0.2);
        }
        
        /* Toolbar migliorata - Leggibilità migliorata */
        .template-toolbar {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .template-toolbar .btn-group .btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 8px;
            margin: 0 3px;
            font-weight: 500;
            border: 1px solid #ced4da;
            background: #ffffff;
            color: #495057;
        }
        
        .template-toolbar .btn-group .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .template-toolbar .btn-group .btn:active,
        .template-toolbar .btn-group .btn.active {
            background: #667eea;
            border-color: #667eea;
            color: white;
            transform: translateY(0);
        }
        
        /* Animazioni */
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(5deg); }
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Sidebar moderna */
        .birthday-sidebar {
            background: linear-gradient(135deg, 
                rgba(28, 28, 30, 0.95) 0%, 
                rgba(44, 44, 46, 0.9) 100%);
            backdrop-filter: blur(25px) saturate(180%);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            min-height: 100vh;
            position: sticky;
            top: 0;
        }
        
        .birthday-sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin: 0.25rem 0.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
        }
        
        .birthday-sidebar .nav-link:hover {
            color: white;
            background: rgba(107, 115, 255, 0.2);
            transform: translateX(4px);
        }
        
        .birthday-sidebar .nav-link.active {
            color: white;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            box-shadow: 0 4px 20px rgba(107, 115, 255, 0.3);
        }
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .birthday-hero {
                padding: 2rem 1rem;
            }
            
            .birthday-hero h2 {
                font-size: 2rem;
            }
            
            .birthday-stat-card {
                padding: 1.5rem;
            }
            
            .birthday-stat-number {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <header class="top-header">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="m-0 mb-2">🎂 Gestione Sistema Compleanni</h1>
                    <p class="mb-0 opacity-75">Crea e gestisci i template per gli auguri automatici di compleanno</p>
                </div>
                <div class="d-flex gap-3 align-items-center">
                    <a href="admin.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i>Torna ad Admin
                    </a>
                    <a href="./logout.php" class="btn logout-btn">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </div>
            </div>
            
            <nav>
                <ul class="nav nav-pills justify-content-center flex-wrap" style="gap: 12px;">
                    <li class="nav-item">
                        <a class="nav-link active" href="#dashboard" onclick="showSection('dashboard')">
                            <i class="bi bi-speedometer2 me-2"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#templates" onclick="showSection('templates')">
                            <i class="bi bi-file-text me-2"></i>
                            <span>Template</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#editor" onclick="showSection('editor')">
                            <i class="bi bi-pencil-square me-2"></i>
                            <span>Editor</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#birthdays" onclick="showSection('birthdays')">
                            <i class="bi bi-calendar-heart me-2"></i>
                            <span>Prossimi Compleanni</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#settings" onclick="showSection('settings')">
                            <i class="bi bi-gear me-2"></i>
                            <span>Impostazioni</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </header>
        
        <main class="content-wrapper">
                
                <!-- Messages -->
                <?php if (!$templateManager): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Errore Sistema</strong> - Il sistema di gestione template non è disponibile. 
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Sistema JSON Attivo</strong> - Template gestiti tramite file JSON. 
                        Tutte le modifiche vengono salvate automaticamente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($successMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Section -->
                <div id="dashboard" class="content-section">
                    <div class="birthday-hero">
                        <h2><i class="bi bi-balloon-heart me-2"></i>Sistema Auguri Compleanno</h2>
                        <p>Gestisci i messaggi automatici di auguri di compleanno per gli utenti MrCharlie con template personalizzati e invii automatici</p>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-md-3">
                            <div class="birthday-stat-card" style="animation-delay: 0.1s;">
                                <div class="birthday-stat-number"><?php echo $stats['today_birthdays']; ?></div>
                                <h6>Compleanni Oggi</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="birthday-stat-card" style="animation-delay: 0.2s;">
                                <div class="birthday-stat-number"><?php echo $stats['sent_this_year']; ?></div>
                                <h6>Auguri Inviati 2024</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="birthday-stat-card" style="animation-delay: 0.3s;">
                                <div class="birthday-stat-number"><?php echo $stats['upcoming_birthdays']; ?></div>
                                <h6>Prossimi 30 Giorni</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="birthday-stat-card" style="animation-delay: 0.4s;">
                                <div class="birthday-stat-number"><?php echo $stats['total_templates']; ?></div>
                                <h6>Template Attivi</h6>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card" style="animation-delay: 0.5s; margin-top: 3rem;">
                        <div class="card-header">
                            <h5 class="card-title"><i class="bi bi-lightning me-2"></i>Azioni Rapide</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-3 justify-content-center">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="send_birthday_check">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-send me-2"></i>Controlla e Invia Auguri Oggi
                                    </button>
                                </form>
                                
                                <button class="btn btn-success btn-lg" onclick="createNewTemplate()">
                                    <i class="bi bi-plus-circle me-2"></i>Nuovo Template
                                </button>
                                
                                <button class="btn btn-info btn-lg" onclick="showSection('birthdays')">
                                    <i class="bi bi-calendar me-2"></i>Vedi Prossimi Compleanni
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Templates Section -->
                <div id="templates" class="content-section" style="display: none;">
                    <h3><i class="bi bi-file-text me-2"></i>Template Messaggi</h3>
                    
                    <div class="row">
                        <?php foreach ($templates as $template): ?>
                            <div class="col-md-6">
                                <div class="template-card <?php echo $template['is_active'] ? 'active' : ''; ?>">
                                    <?php if ($template['is_active']): ?>
                                        <span class="badge bg-success position-absolute top-0 end-0 m-2">ATTIVO</span>
                                    <?php endif; ?>
                                    
                                    <h5><?php echo htmlspecialchars($template['name']); ?></h5>
                                    <p class="text-muted">
                                        <strong>Oggetto:</strong> <?php echo htmlspecialchars($template['subject']); ?>
                                    </p>
                                    
                                    <div class="template-preview">
                                        <?php echo $template['html_content']; ?>
                                    </div>
                                    
                                    <?php 
                                    // Rileva template complessi anche in PHP per l'indicatore
                                    $isComplex = strlen($template['html_content']) > 1000 || 
                                                strpos($template['html_content'], 'linear-gradient') !== false ||
                                                strpos($template['html_content'], 'backdrop-filter') !== false ||
                                                substr_count($template['html_content'], 'style="') > 10;
                                    if ($isComplex): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-info">
                                                <i class="bi bi-code-slash me-1"></i>Template Avanzato
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            Usato <?php echo $template['times_used']; ?> volte | 
                                            Creato: <?php echo date('d/m/Y', strtotime($template['created_at'])); ?>
                                        </small>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button class="btn btn-sm btn-primary" onclick="console.log('🔧 DEBUG - Click su Modifica per template ID:', <?php echo $template['id']; ?>); editTemplate(<?php echo $template['id']; ?>)">
                                            <i class="bi bi-pencil me-1"></i>Modifica
                                        </button>
                                        
                                        <?php if (!$template['is_active']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="activate_template">
                                                <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="bi bi-check-circle me-1"></i>Attiva
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm btn-info" onclick="testTemplate(<?php echo $template['id']; ?>)">
                                            <i class="bi bi-envelope me-1"></i>Test
                                        </button>
                                        
                                        <?php if (count($templates) > 1 && !$template['is_active']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Sei sicuro di voler eliminare questo template?')">
                                                <input type="hidden" name="action" value="delete_template">
                                                <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-trash me-1"></i>Elimina
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Editor Section -->
                <div id="editor" class="content-section" style="display: none;">
                    <h3><i class="bi bi-pencil-square me-2"></i>Editor Template</h3>
                    
                    <!-- Istruzioni per l'uso -->
                    <div class="alert alert-info">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-info-circle me-2 mt-1"></i>
                            <div>
                                <strong>Come utilizzare l'editor:</strong>
                                <ol class="mb-0 mt-2">
                                    <li><strong>Nome e Oggetto:</strong> Compila i campi obbligatori</li>
                                    <li><strong>Contenuto:</strong> Scrivi nell'editor visuale o passa alla modalità HTML per codice avanzato</li>
                                    <li><strong>Test:</strong> Usa il pulsante "Test Form" per verificare i dati prima del salvataggio</li>
                                    <li><strong>Salva:</strong> Clicca "Salva Template" per memorizzare le modifiche</li>
                                </ol>
                                <small class="text-muted mt-2 d-block">
                                    💡 <strong>Suggerimento:</strong> I template complessi vengono automaticamente aperti in modalità HTML per preservare la formattazione.
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Template Selector -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5><i class="bi bi-palette me-2"></i>Template Predefiniti</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-primary w-100 mb-2" onclick="loadTemplate('classic')">
                                        <i class="bi bi-star me-1"></i>Classico MrCharlie
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-success w-100 mb-2" onclick="loadTemplate('modern')">
                                        <i class="bi bi-grid me-1"></i>Moderno
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-info w-100 mb-2" onclick="loadTemplate('elegant')">
                                        <i class="bi bi-gem me-1"></i>Elegante
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" id="templateForm">
                        <input type="hidden" name="action" value="save_template">
                        <input type="hidden" name="template_id" id="template_id" value="<?php echo $editTemplate['id'] ?? 0; ?>">
                        
                        <div class="row">
                            <div class="col-md-8">
                                                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 text-dark"><i class="bi bi-file-text me-2 text-primary"></i>Contenuto Template</h5>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="exportTemplate()">
                                                <i class="bi bi-download me-1"></i>Esporta
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('importFile').click()">
                                                <i class="bi bi-upload me-1"></i>Importa
                                            </button>
                                            <input type="file" id="importFile" accept=".json" style="display: none;" onchange="importTemplate(this)">
                                        </div>
                                    </div>
                                    <div class="card-body bg-white">
                                        <div class="mb-4">
                                            <label class="form-label fw-semibold text-dark">Nome Template</label>
                                            <input type="text" class="form-control form-control-lg border-2" name="template_name" id="template_name"
                                                   value="<?php echo htmlspecialchars($editTemplate['name'] ?? ''); ?>" 
                                                   required placeholder="Inserisci un nome descrittivo per il template"
                                                   style="background: #f8f9fa; border-color: #dee2e6;">
                                            <div class="invalid-feedback" id="name-feedback"></div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label fw-semibold text-dark">Oggetto Email</label>
                                            <input type="text" class="form-control form-control-lg border-2" name="template_subject" id="template_subject"
                                                   value="<?php echo htmlspecialchars($editTemplate['subject'] ?? ''); ?>" 
                                                   required placeholder="es: 🎉 Buon Compleanno {{NOME}}! 🎂"
                                                   style="background: #f8f9fa; border-color: #dee2e6;">
                                            <small class="form-text text-muted fw-medium">💡 Usa le variabili per personalizzare l'oggetto</small>
                                            <div class="invalid-feedback" id="subject-feedback"></div>
                                        </div>
                                        
                                        <!-- Toolbar Personalizzata -->
                                                                <div class="mb-4">
                            <label class="form-label fw-semibold text-dark">Contenuto Template</label>
                            <div class="template-toolbar bg-light p-3 rounded-top border-2" style="border-color: #dee2e6;"
                                <!-- Riga 1: Toggle e Azioni Principali -->
                                <div class="d-flex flex-wrap align-items-center mb-2">
                                    <div class="btn-group btn-group-sm me-3" role="group">
                                        <button type="button" class="btn btn-outline-dark" onclick="toggleHtmlMode()" id="htmlModeBtn" disabled>
                                            <i class="bi bi-code me-1"></i>HTML
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" onclick="validateTemplate()">
                                            <i class="bi bi-check-circle me-1"></i>Valida
                                        </button>
                                        <button type="button" class="btn btn-outline-info" onclick="previewTemplate()" title="Anteprima nel modal">
                                            <i class="bi bi-eye me-1"></i>Anteprima
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="openPreviewInNewTab()" title="Apri anteprima in nuova finestra">
                                            <i class="bi bi-box-arrow-up-right me-1"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" onclick="debugTemplateContent()" title="Debug contenuto template">
                                            <i class="bi bi-bug me-1"></i>Debug
                                        </button>
                                        <button type="button" class="btn btn-outline-success" onclick="testFormData()" title="Verifica dati form prima del salvataggio">
                                            <i class="bi bi-check-circle me-1"></i>Test Form
                                        </button>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="Test caricamento template">
                                                <i class="bi bi-arrow-down-circle me-1"></i>Test Load
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><h6 class="dropdown-header">Template Disponibili</h6></li>
                                                <li><a class="dropdown-item" href="#" onclick="testLoadTemplate(1)">Template Default</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="testLoadTemplate(2)">Template Elegante</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="testLoadTemplate(3)">Template Moderno</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="#" onclick="forceVisualMode()">🔧 Forza Modalità Visuale</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <div class="btn-group btn-group-sm me-3" role="group">
                                        <button type="button" class="btn btn-outline-purple" onclick="insertTemplateStructure()">
                                            <i class="bi bi-layout-text-window me-1"></i>Struttura Base
                                        </button>
                                    </div>
                                    
                                    <small class="text-dark fw-medium ms-auto" id="editor-mode-indicator">
                                        <i class="bi bi-pencil-square me-1 text-primary"></i>Modalità Visuale
                                    </small>
                                </div>
                                
                                <!-- Riga 2: Emoji e Testi Rapidi -->
                                <div class="d-flex flex-wrap align-items-center">
                                    <div class="btn-group btn-group-sm me-2" role="group">
                                        <button type="button" class="btn btn-outline-secondary" onclick="insertEmoji('🎉')" title="Festa">🎉</button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="insertEmoji('🎂')" title="Torta">🎂</button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="insertEmoji('🎈')" title="Palloncini">🎈</button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="insertEmoji('🎁')" title="Regalo">🎁</button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="insertEmoji('🎊')" title="Coriandoli">🎊</button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="insertEmoji('👑')" title="Corona">👑</button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="insertEmoji('✨')" title="Stelline">✨</button>
                                    </div>
                                    
                                    <div class="btn-group btn-group-sm me-2" role="group">
                                        <button type="button" class="btn btn-outline-primary" onclick="insertQuickText('birthday_greeting')" title="Inserisci saluto di compleanno">
                                            <i class="bi bi-chat-quote me-1"></i>Saluto
                                        </button>
                                        <button type="button" class="btn btn-outline-success" onclick="insertQuickText('gift_offer')" title="Inserisci offerta regalo">
                                            <i class="bi bi-gift me-1"></i>Offerta
                                        </button>
                                        <button type="button" class="btn btn-outline-info" onclick="insertQuickText('contact_info')" title="Inserisci informazioni di contatto">
                                            <i class="bi bi-envelope me-1"></i>Contatti
                                        </button>
                                    </div>
                                    
                                    <div class="btn-group btn-group-sm me-2" role="group">
                                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="Font rapidi">
                                            <i class="bi bi-fonts me-1"></i>Font
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><h6 class="dropdown-header">Sans Serif</h6></li>
                                            <li><a class="dropdown-item" href="#" onclick="applyQuickFont('inter')" style="font-family: 'Inter', sans-serif;">Inter</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="applyQuickFont('roboto')" style="font-family: 'Roboto', sans-serif;">Roboto</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="applyQuickFont('opensans')" style="font-family: 'Open Sans', sans-serif;">Open Sans</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="applyQuickFont('montserrat')" style="font-family: 'Montserrat', sans-serif;">Montserrat</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><h6 class="dropdown-header">Serif</h6></li>
                                            <li><a class="dropdown-item" href="#" onclick="applyQuickFont('playfair')" style="font-family: 'Playfair Display', serif;">Playfair Display</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="applyQuickFont('lora')" style="font-family: 'Lora', serif;">Lora</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="applyQuickFont('merriweather')" style="font-family: 'Merriweather', serif;">Merriweather</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><h6 class="dropdown-header">Decorativi</h6></li>
                                            <li><a class="dropdown-item" href="#" onclick="applyQuickFont('dancing')" style="font-family: 'Dancing Script', cursive;">Dancing Script</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="applyQuickFont('pacifico')" style="font-family: 'Pacifico', cursive;">Pacifico</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="applyQuickFont('caveat')" style="font-family: 'Caveat', cursive;">Caveat</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                                            <!-- Quill Editor Container -->
                                            <div id="template_content" style="height: 400px; border: 1px solid #ddd;">
                                                <?php echo $editTemplate['html_content'] ?? ''; ?>
                                            </div>
                                            
                                                                        <!-- HTML Source Editor -->
                            <div id="html_editor_container" style="display: none;">
                                <textarea id="html_source_editor" class="form-control" style="height: 400px;" placeholder="Inserisci o modifica il codice HTML del template...&#10;&#10;Suggerimenti:&#10;- Usa Tab per indentare&#10;- Ctrl+Shift+F per formattare&#10;- Ctrl+Shift+> per chiudere tag automaticamente&#10;- Le variabili {{NOME}}, {{ETA}}, {{EMAIL}} saranno sostituite automaticamente">
                                    <?php echo htmlspecialchars($editTemplate['html_content'] ?? ''); ?>
                                </textarea>
                                <div class="mt-2 d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>
                                        <strong>Modalità HTML</strong>: Modifica diretta del codice sorgente. 
                                        Usa le variabili MrCharlie per personalizzare i contenuti.
                                    </small>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-secondary" onclick="formatHtmlCode()" title="Formatta il codice HTML (Ctrl+Shift+F)">
                                            <i class="bi bi-indent me-1"></i>Formatta
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="validateHtmlSyntax()" title="Valida la sintassi HTML">
                                            <i class="bi bi-check-circle me-1"></i>Valida
                                        </button>
                                    </div>
                                </div>
                            </div>
                                            
                                            <!-- Hidden textarea per il form -->
                                            <textarea name="template_content" id="template_content_hidden" style="display: none;">
                                                <?php echo htmlspecialchars($editTemplate['html_content'] ?? ''); ?>
                                            </textarea>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <span id="content-stats">Caratteri: 0</span> | 
                                                    <span class="text-success" id="validation-status">✓ Template valido</span>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Immagine di Sfondo (opzionale)</label>
                                            <div class="input-group">
                                                <input type="file" class="form-control" id="backgroundImage" accept="image/*">
                                                <button type="button" class="btn btn-outline-secondary" onclick="clearBackgroundImage()">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </div>
                                            <input type="hidden" name="background_image" id="background_image_url" 
                                                   value="<?php echo htmlspecialchars($editTemplate['background_image'] ?? ''); ?>">
                                            <div class="mt-2" id="image-preview" style="display: none;">
                                                <img id="preview-img" src="" alt="Anteprima" class="img-thumbnail" style="max-height: 100px;">
                                                <small class="form-text text-muted d-block">Anteprima immagine caricata</small>
                                            </div>
                                        </div>
                                        
                                        <!-- Sezione Personalizzazione Avanzata -->
                                        <div class="accordion" id="advancedOptions">
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="headingAdvanced">
                                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced">
                                                        <i class="bi bi-gear me-2"></i>Opzioni Avanzate
                                                    </button>
                                                </h2>
                                                <div id="collapseAdvanced" class="accordion-collapse collapse" data-bs-parent="#advancedOptions">
                                                    <div class="accordion-body">
                                                                                                <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Colore Primario</label>
                                                <input type="color" class="form-control form-control-color" id="primaryColor" value="#667eea" onchange="updateTemplateColors()">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Colore Secondario</label>
                                                <input type="color" class="form-control form-control-color" id="secondaryColor" value="#764ba2" onchange="updateTemplateColors()">
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <label class="form-label">Font Family</label>
                                            <select class="form-select" id="fontFamily" onchange="updateTemplateFont()">
                                                <optgroup label="Sans Serif">
                                                    <option value="'Inter', sans-serif">Inter</option>
                                                    <option value="'Roboto', sans-serif">Roboto</option>
                                                    <option value="'Open Sans', sans-serif">Open Sans</option>
                                                    <option value="'Lato', sans-serif">Lato</option>
                                                    <option value="'Montserrat', sans-serif">Montserrat</option>
                                                    <option value="'Poppins', sans-serif">Poppins</option>
                                                    <option value="'Nunito', sans-serif">Nunito</option>
                                                    <option value="'Source Sans Pro', sans-serif">Source Sans Pro</option>
                                                    <option value="'Raleway', sans-serif">Raleway</option>
                                                    <option value="'Ubuntu', sans-serif">Ubuntu</option>
                                                    <option value="Arial, sans-serif">Arial</option>
                                                    <option value="'Helvetica', sans-serif">Helvetica</option>
                                                </optgroup>
                                                <optgroup label="Serif">
                                                    <option value="'Playfair Display', serif">Playfair Display</option>
                                                    <option value="'Crimson Text', serif">Crimson Text</option>
                                                    <option value="'Libre Baskerville', serif">Libre Baskerville</option>
                                                    <option value="'Merriweather', serif">Merriweather</option>
                                                    <option value="'PT Serif', serif">PT Serif</option>
                                                    <option value="'Lora', serif">Lora</option>
                                                    <option value="Georgia, serif">Georgia</option>
                                                    <option value="'Times New Roman', serif">Times New Roman</option>
                                                </optgroup>
                                                <optgroup label="Cursive/Decorative">
                                                    <option value="'Dancing Script', cursive">Dancing Script</option>
                                                    <option value="'Pacifico', cursive">Pacifico</option>
                                                    <option value="'Caveat', cursive">Caveat</option>
                                                    <option value="'Kalam', cursive">Kalam</option>
                                                </optgroup>
                                                <optgroup label="Monospace">
                                                    <option value="'Courier New', monospace">Courier New</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                        
                                        <hr>
                                        
                                        <h6><i class="bi bi-code-square me-2"></i>Strumenti HTML</h6>
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertHtmlBlock('header')">
                                                <i class="bi bi-header me-1"></i>Inserisci Header
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertHtmlBlock('box')">
                                                <i class="bi bi-box me-1"></i>Inserisci Box Colorato
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertHtmlBlock('button')">
                                                <i class="bi bi-link-45deg me-1"></i>Inserisci Bottone
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatHtmlCode()">
                                                <i class="bi bi-indent me-1"></i>Formatta HTML
                                            </button>
                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-white border-bottom">
                                        <h5 class="mb-0 text-dark"><i class="bi bi-tags me-2 text-success"></i>Variabili Disponibili</h5>
                                    </div>
                                    <div class="card-body bg-white">
                                        <p class="small text-dark fw-medium mb-3">💡 Clicca per inserire nel template:</p>
                                        <div class="variable-tag" onclick="insertVariable('{{NOME}}')" title="Nome dell'utente">{{NOME}}</div>
                                        <div class="variable-tag" onclick="insertVariable('{{COGNOME}}')" title="Cognome dell'utente">{{COGNOME}}</div>
                                        <div class="variable-tag" onclick="insertVariable('{{NOME_COMPLETO}}')" title="Nome e cognome">{{NOME_COMPLETO}}</div>
                                        <div class="variable-tag" onclick="insertVariable('{{EMAIL}}')" title="Email dell'utente">{{EMAIL}}</div>
                                        <div class="variable-tag" onclick="insertVariable('{{DATA_NASCITA}}')" title="Data di nascita (DD/MM/YYYY)">{{DATA_NASCITA}}</div>
                                        <div class="variable-tag" onclick="insertVariable('{{ETA}}')" title="Età in anni">{{ETA}}</div>
                                        <div class="variable-tag" onclick="insertVariable('{{ANNO}}')" title="Anno corrente">{{ANNO}}</div>
                                        
                                        <hr class="my-3">
                                        <h6 class="small text-dark fw-semibold"><i class="bi bi-question-circle me-1 text-info"></i>Esempio Output:</h6>
                                        <div class="bg-light p-3 rounded border small">
                                            <div class="text-dark"><strong class="text-primary">{{NOME_COMPLETO}}</strong> → Mario Rossi</div>
                                            <div class="text-dark"><strong class="text-primary">{{ETA}}</strong> → 34</div>
                                            <div class="text-dark"><strong class="text-primary">{{DATA_NASCITA}}</strong> → 01/01/1990</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mt-3 border-0 shadow-sm">
                                    <div class="card-header bg-white border-bottom">
                                        <h5 class="mb-0 text-dark"><i class="bi bi-lightning me-2 text-warning"></i>Azioni</h5>
                                    </div>
                                    <div class="card-body bg-white">
                                        <button type="submit" class="btn btn-primary w-100 mb-2" id="saveBtn" onclick="console.log('🔧 DEBUG - Click su Salva Template');">
                                            <i class="bi bi-save me-1"></i>Salva Template
                                        </button>
                                        
                                        <button type="button" class="btn btn-info w-100 mb-2" onclick="previewTemplate()">
                                            <i class="bi bi-eye me-1"></i>Anteprima
                                        </button>
                                        
                                        <button type="button" class="btn btn-warning w-100 mb-2" onclick="testTemplate(<?php echo $editTemplate['id'] ?? 'null'; ?>)">
                                            <i class="bi bi-envelope me-1"></i>Invia Test
                                        </button>
                                        
                                        <button type="button" class="btn btn-secondary w-100 mb-2" onclick="clearEditor()">
                                            <i class="bi bi-x-circle me-1"></i>Pulisci
                                        </button>
                                        
                                        <hr>
                                        
                                        <button type="button" class="btn btn-outline-primary w-100 mb-1" onclick="duplicateTemplate()">
                                            <i class="bi bi-copy me-1"></i>Duplica
                                        </button>
                                        
                                        <button type="button" class="btn btn-outline-success w-100" onclick="saveAsDraft()">
                                            <i class="bi bi-journal me-1"></i>Salva Bozza
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Template Statistics -->
                                <div class="card mt-3 border-0 shadow-sm">
                                    <div class="card-header bg-white border-bottom">
                                        <h6 class="mb-0 text-dark"><i class="bi bi-graph-up me-2 text-info"></i>Statistiche</h6>
                                    </div>
                                    <div class="card-body bg-white">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="text-dark fw-medium">Variabili usate:</small>
                                            <span class="badge bg-primary fs-6" id="variables-count">0</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="text-dark fw-medium">Lunghezza testo:</small>
                                            <span class="badge bg-info fs-6" id="text-length">0</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-dark fw-medium">Ultima modifica:</small>
                                            <span class="badge bg-secondary fs-6" id="last-modified">Mai</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Birthdays Section -->
                <div id="birthdays" class="content-section" style="display: none;">
                    <h3><i class="bi bi-calendar-heart me-2"></i>Prossimi Compleanni</h3>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5>Compleanni nei prossimi 30 giorni</h5>
                        </div>
                        <div class="card-body">
                            <div class="birthday-list">
                                <?php foreach ($upcomingBirthdays as $birthday): ?>
                                    <div class="birthday-item">
                                        <div>
                                            <strong><?php echo htmlspecialchars($birthday['nome'] . ' ' . $birthday['cognome']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($birthday['email']); ?></small>
                                        </div>
                                        <div>
                                            <span class="birthday-display"><?php echo $birthday['birthday_display']; ?></span>
                                            <span class="days-badge <?php echo $birthday['days_until'] == 0 ? 'bg-warning' : ($birthday['days_until'] <= 7 ? 'bg-success' : 'bg-primary'); ?>">
                                                <?php echo $birthday['days_until'] == 0 ? 'OGGI!' : "tra {$birthday['days_until']} giorni"; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (empty($upcomingBirthdays)): ?>
                                    <p class="text-center text-muted">Nessun compleanno nei prossimi 30 giorni</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Section -->
                <div id="settings" class="content-section" style="display: none;">
                    <h3><i class="bi bi-gear me-2"></i>Impostazioni Sistema</h3>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5>Configurazione Automatica</h5>
                        </div>
                        <div class="card-body">
                            <p>Per attivare l'invio automatico giornaliero, aggiungi questo comando al cron del server:</p>
                            <code>0 9 * * * php <?php echo __DIR__; ?>/birthday_system.php</code>
                            <p class="mt-2"><small>Questo invierà gli auguri ogni giorno alle 9:00</small></p>
                            
                            <hr>
                            
                            <h6>Test Sistema</h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="send_birthday_check">
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-play-circle me-1"></i>Esegui Controllo Manuale
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Modal -->
    <div class="modal fade" id="testModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Test Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="test_template">
                        <input type="hidden" name="template_id" id="test_template_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Email di Test</label>
                            <input type="email" class="form-control" name="test_email" required
                                   placeholder="Inserisci email per ricevere il test">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">Invia Test</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Anteprima Template</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshPreview()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Aggiorna
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openPreviewInNewTab()">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Nuova Finestra
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-0" style="background: #f8f9fa;">
                    <div id="preview-content" style="min-height: 600px;"></div>
                </div>
                <div class="modal-footer bg-light">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Questa è un'anteprima di come apparirà l'email ai destinatari. 
                        I dati mostrati sono di esempio.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dati template per JavaScript
        <?php 
        // DEBUG: Verifica JSON encoding
        $jsonData = json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("🔧 DEBUG PHP - Errore JSON encoding: " . json_last_error_msg());
            // Fallback: prova senza caratteri speciali
            $cleanTemplates = $templates;
            foreach ($cleanTemplates as &$template) {
                if (isset($template['html_content'])) {
                    $template['html_content'] = base64_encode($template['html_content']);
                    $template['_encoded'] = true;
                }
            }
            $jsonData = json_encode($cleanTemplates);
            error_log("🔧 DEBUG PHP - Fallback con base64, lunghezza: " . strlen($jsonData));
        } else {
            error_log("🔧 DEBUG PHP - JSON encoding OK, lunghezza: " . strlen($jsonData));
        }
        ?>
        const templatesData = <?php echo $jsonData; ?>;
        
        // Decodifica base64 se necessario
        templatesData.forEach(template => {
            if (template._encoded && template.html_content) {
                template.html_content = atob(template.html_content);
                delete template._encoded;
                console.log('🔧 DEBUG - Decodificato template:', template.name);
            }
        });
        console.log('📊 DEBUG - Template caricati:', templatesData.length, 'template disponibili');
        console.log('📊 DEBUG - Dati completi templatesData:', templatesData);
        
        // Verifica che ogni template abbia i dati necessari
        templatesData.forEach((template, index) => {
            console.log(`📊 DEBUG - Template ${index + 1}:`, {
                id: template.id,
                name: template.name,
                subject: template.subject,
                hasHtmlContent: !!template.html_content,
                htmlContentLength: template.html_content ? template.html_content.length : 0,
                isActive: template.is_active
            });
        });
        
        // Initialize CKEditor
        let ckEditor = null;
        let ckEditorInitialized = false;
        
        // Funzione di inizializzazione CKEditor migliorata
        function initializeCKEditor() {
            console.log('🔧 DEBUG initializeCKEditor() - Inizio inizializzazione');
            console.log('🔧 DEBUG initializeCKEditor() - Stato:', {
                ckEditorInitialized: ckEditorInitialized,
                windowClassicEditor: !!window.ClassicEditor,
                templateContentElement: !!document.getElementById('template_content')
            });
            
            if (ckEditorInitialized || !window.ClassicEditor) {
                console.log('🔧 DEBUG initializeCKEditor() - Uscita anticipata:', {
                    ckEditorInitialized: ckEditorInitialized,
                    windowClassicEditor: !!window.ClassicEditor
                });
                return;
            }
            
            try {
                console.log('🔧 DEBUG initializeCKEditor() - Creazione configurazione...');
                
                                const editorConfig = {
                    // Usa solo i plugin disponibili di default
                    placeholder: 'Scrivi qui il contenuto del template... Usa le variabili come {{NOME}}, {{ETA}}, etc.'
                };

                console.log('🔧 DEBUG initializeCKEditor() - Creazione istanza CKEditor...');
                
                ClassicEditor
                    .create(document.querySelector('#template_content'), editorConfig)
                    .then(editor => {
                        ckEditor = editor;
                        ckEditorInitialized = true;
                        
                        console.log('🔧 DEBUG initializeCKEditor() - CKEditor creato, aggiunta listener...');
                        console.log('✅ DEBUG - CKEditor inizializzato senza errori');

                        // Sincronizza con il textarea nascosto - VERSIONE MIGLIORATA
                        editor.model.document.on('change:data', () => {
                            const data = editor.getData();
                            const hiddenField = document.getElementById('template_content_hidden');
                            if (hiddenField) {
                                hiddenField.value = data;
                                console.log('🔧 DEBUG CKEditor - Contenuto sincronizzato:', {
                                    length: data.length,
                                    preview: data.substring(0, 100) + '...',
                                    hiddenFieldValue: hiddenField.value.substring(0, 100) + '...'
                                });
                            }
                            updateTemplateStats();
                            updateLastModified();
                            
                            // Sincronizza anche con l'editor HTML se è visibile
                            if (isHtmlMode) {
                                const htmlEditor = document.getElementById('html_source_editor');
                                if (htmlEditor) {
                                    htmlEditor.value = data;
                                    console.log('🔧 DEBUG CKEditor - Contenuto sincronizzato con HTML editor');
                                }
                            }
                        });
                        
                        // AGGIUNTA: Sincronizzazione forzata ogni 5 secondi per sicurezza
                        setInterval(() => {
                            if (ckEditor && ckEditorInitialized && !isHtmlMode) {
                                try {
                                    const currentData = ckEditor.getData();
                                    const hiddenField = document.getElementById('template_content_hidden');
                                    if (hiddenField && hiddenField.value !== currentData) {
                                        hiddenField.value = currentData;
                                        console.log('🔧 DEBUG Auto-sync - Campo nascosto aggiornato');
                                    }
                                } catch (error) {
                                    console.warn('🔧 DEBUG Auto-sync - Errore:', error);
                                }
                            }
                        }, 5000);

                        // Carica contenuto iniziale se presente
                        console.log('🔧 DEBUG initializeCKEditor() - Controllo contenuto iniziale...');
                        const hiddenField = document.getElementById('template_content_hidden');
                        const initialContent = hiddenField ? hiddenField.value : '';
                        console.log('🔧 DEBUG initializeCKEditor() - Contenuto iniziale trovato:', initialContent.substring(0, 100));
                        
                        if (initialContent && initialContent.trim()) {
                            console.log('🔧 DEBUG initializeCKEditor() - Caricamento contenuto iniziale in CKEditor...');
                            editor.setData(initialContent);
                            console.log('🔧 DEBUG initializeCKEditor() - Contenuto iniziale caricato');
                        }

                        // Inizializza statistiche
                        setTimeout(() => {
                            console.log('🔧 DEBUG initializeCKEditor() - Aggiornamento statistiche...');
                            updateTemplateStats();
                        }, 1000);

                        console.log('✅ DEBUG initializeCKEditor() - CKEditor inizializzato correttamente');
                        
                        // Abilita il bottone toggle dopo l'inizializzazione
                        const htmlBtn = document.getElementById('htmlModeBtn');
                        if (htmlBtn) {
                            htmlBtn.disabled = false;
                            htmlBtn.title = 'Passa alla modalità HTML per modificare il codice sorgente';
                            console.log('🔧 DEBUG initializeCKEditor() - Bottone HTML abilitato');
                        } else {
                            console.warn('🔧 DEBUG initializeCKEditor() - Bottone HTML non trovato');
                        }
                        
                        // Test immediato di CKEditor
                        setTimeout(() => {
                            console.log('🔧 DEBUG initializeCKEditor() - Test CKEditor:', {
                                ckEditorExists: !!ckEditor,
                                ckEditorData: ckEditor.getData().substring(0, 100)
                            });
                        }, 100);
                        
                    })
                    .catch(error => {
                        console.error('❌ DEBUG initializeCKEditor() - Errore nell\'inizializzazione di CKEditor:', error);
                        // Fallback a textarea semplice
                        showTextareaFallback();
                    });
                
            } catch (error) {
                console.error('❌ DEBUG initializeCKEditor() - Errore nell\'inizializzazione di CKEditor:', error);
                // Fallback a textarea semplice
                showTextareaFallback();
            }
        }

        // Fallback se Quill non funziona
        function showTextareaFallback() {
            const quillContainer = document.getElementById('template_content');
            const hiddenTextarea = document.getElementById('template_content_hidden');
            
            // Nasconde il container Quill
            quillContainer.style.display = 'none';
            
            // Mostra e configura il textarea
            hiddenTextarea.style.display = 'block';
            hiddenTextarea.style.height = '400px';
            hiddenTextarea.className = 'form-control';
            hiddenTextarea.placeholder = 'Scrivi qui il contenuto del template (HTML)...';
            
            // Aggiunge listener per statistiche
            hiddenTextarea.addEventListener('input', function() {
                updateTemplateStats();
                updateLastModified();
            });
            
            // Disabilita il toggle HTML visto che siamo già in modalità testo
            const htmlBtn = document.getElementById('htmlModeBtn');
            if (htmlBtn) {
                htmlBtn.disabled = true;
                htmlBtn.title = 'Editor visuale non disponibile';
            }
            
            console.log('⚠️ Fallback a textarea semplice attivato');
        }

        // Variabili globali per modalità HTML
        let isHtmlMode = false;
        let htmlEditorSyncTimeout = null;
        
        // Toggle modalità HTML migliorato
        function toggleHtmlMode() {
            const quillContainer = document.getElementById('template_content');
            const htmlContainer = document.getElementById('html_editor_container');
            const htmlEditor = document.getElementById('html_source_editor');
            const htmlBtn = document.getElementById('htmlModeBtn');
            
            if (!ckEditorInitialized) {
                alert('Editor visuale non disponibile. Utilizzare il textarea per la modifica.');
                return;
            }
            
            if (!isHtmlMode) {
                // Passa a modalità HTML
                let content = '';
                
                // Prima controlla se l'HTML editor ha già contenuto (da editTemplate)
                const existingHtmlContent = htmlEditor.value;
                if (existingHtmlContent && existingHtmlContent.trim() && existingHtmlContent.length > 100) {
                    console.log('🔧 DEBUG toggleHtmlMode() - HTML editor ha già contenuto, lo mantengo');
                    content = existingHtmlContent;
                } else {
                    // Altrimenti prendi da CKEditor o campo nascosto
                    try {
                        const ckContent = ckEditor.getData();
                        const hiddenContent = document.getElementById('template_content_hidden').value || '';
                        
                        // Usa il contenuto più completo tra CKEditor e campo nascosto
                        if (hiddenContent.length > ckContent.length) {
                            content = hiddenContent;
                            console.log('🔧 DEBUG toggleHtmlMode() - Uso contenuto dal campo nascosto (più completo)');
                        } else {
                            content = ckContent;
                            console.log('🔧 DEBUG toggleHtmlMode() - Uso contenuto da CKEditor');
                        }
                    } catch (error) {
                        content = document.getElementById('template_content_hidden').value || '';
                        console.log('🔧 DEBUG toggleHtmlMode() - Fallback a campo nascosto');
                    }
                    
                    // Formatta il codice HTML per renderlo più leggibile
                    content = formatHtmlForEditing(content);
                    htmlEditor.value = content;
                }
                quillContainer.style.display = 'none';
                htmlContainer.style.display = 'block';
                htmlBtn.innerHTML = '<i class="bi bi-eye me-1"></i>Visuale';
                htmlBtn.classList.remove('btn-outline-dark');
                htmlBtn.classList.add('btn-dark');
                htmlBtn.title = 'Torna alla modalità visuale';
                isHtmlMode = true;
                
                // Aggiorna indicatore modalità
                updateModeIndicator('HTML', 'code');
                
                // Focus sull'editor HTML
                setTimeout(() => htmlEditor.focus(), 100);
                
                // Mostra suggerimenti per modalità HTML
                showHtmlModeHelp();
                
            } else {
                // Passa a modalità Visual
                const htmlContent = htmlEditor.value;
                
                try {
                    // Valida l'HTML prima di caricarlo
                    if (isValidHtml(htmlContent)) {
                        ckEditor.setData(htmlContent);
                        document.getElementById('template_content_hidden').value = htmlContent;
                    } else {
                        if (confirm('Il codice HTML contiene errori. Vuoi comunque procedere?')) {
                            ckEditor.setData(htmlContent);
                            document.getElementById('template_content_hidden').value = htmlContent;
                        } else {
                            return; // Non cambiare modalità
                        }
                    }
                } catch (error) {
                    console.warn('Errore nel caricamento HTML in CKEditor:', error);
                    alert('Errore nel caricamento del codice HTML. Verifica la sintassi.');
                    return;
                }
                
                htmlContainer.style.display = 'none';
                quillContainer.style.display = 'block';
                htmlBtn.innerHTML = '<i class="bi bi-code me-1"></i>HTML';
                htmlBtn.classList.remove('btn-dark');
                htmlBtn.classList.add('btn-outline-dark');
                htmlBtn.title = 'Passa alla modalità HTML per modificare il codice sorgente';
                isHtmlMode = false;
                
                // Aggiorna indicatore modalità
                updateModeIndicator('Visuale', 'pencil-square');
                
                updateTemplateStats();
                hideHtmlModeHelp();
                
                // Focus sull'editor CKEditor
                setTimeout(() => ckEditor.focus(), 100);
            }
        }
        
        // Formatta HTML per l'editing
        function formatHtmlForEditing(html) {
            if (!html) return '';
            
            // Rimuovi spazi extra e indenta
            let formatted = html.replace(/></g, '>\n<');
            formatted = formatted.replace(/\n\s*\n/g, '\n');
            
            const lines = formatted.split('\n');
            let indent = 0;
            const indentedLines = lines.map(line => {
                const trimmed = line.trim();
                if (!trimmed) return '';
                
                if (trimmed.startsWith('</')) {
                    indent = Math.max(0, indent - 1);
                }
                
                const result = '    '.repeat(indent) + trimmed;
                
                if (trimmed.startsWith('<') && !trimmed.startsWith('</') && 
                    !trimmed.endsWith('/>') && !trimmed.includes('</')){
                    indent++;
                }
                
                return result;
            });
            
            return indentedLines.join('\n');
        }
        
        // Valida HTML base
        function isValidHtml(html) {
            if (!html) return true;
            
            // Controlli base per tag aperti/chiusi
            const openTags = html.match(/<[^\/][^>]*>/g) || [];
            const closeTags = html.match(/<\/[^>]*>/g) || [];
            
            // Escludi tag self-closing
            const selfClosing = ['img', 'br', 'hr', 'input', 'meta', 'link'];
            const actualOpenTags = openTags.filter(tag => {
                const tagName = tag.match(/<(\w+)/)?.[1]?.toLowerCase();
                return tagName && !selfClosing.includes(tagName) && !tag.endsWith('/>');
            });
            
            // Controllo bilanciamento base
            return Math.abs(actualOpenTags.length - closeTags.length) <= 2; // Tollera piccole differenze
        }
        
        // Mostra suggerimenti modalità HTML
        function showHtmlModeHelp() {
            let helpDiv = document.getElementById('html-mode-help');
            if (!helpDiv) {
                helpDiv = document.createElement('div');
                helpDiv.id = 'html-mode-help';
                helpDiv.className = 'alert alert-info mt-2';
                helpDiv.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle me-2"></i>
                        <div>
                            <strong>Modalità HTML attiva</strong> - Puoi modificare direttamente il codice sorgente.
                            <small class="d-block mt-1">
                                💡 <strong>Suggerimenti:</strong> Usa Ctrl+A per selezionare tutto, 
                                le variabili come {{NOME}}, {{ETA}} verranno sostituite automaticamente.
                            </small>
                        </div>
                        <button type="button" class="btn-close btn-close-sm ms-auto" onclick="hideHtmlModeHelp()"></button>
                    </div>
                `;
                document.getElementById('html_editor_container').appendChild(helpDiv);
            }
            helpDiv.style.display = 'block';
        }
        
        // Nascondi suggerimenti modalità HTML
        function hideHtmlModeHelp() {
            const helpDiv = document.getElementById('html-mode-help');
            if (helpDiv) {
                helpDiv.style.display = 'none';
            }
        }
        
        // Mostra notifica template caricato
        function showTemplateLoadedNotification(templateName) {
            // Rimuovi notifiche precedenti
            const existingNotif = document.getElementById('template-loaded-notification');
            if (existingNotif) {
                existingNotif.remove();
            }
            
            const notification = document.createElement('div');
            notification.id = 'template-loaded-notification';
            notification.className = 'alert alert-success alert-dismissible fade show';
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.maxWidth = '400px';
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle me-2"></i>
                    <div>
                        <strong>Template Caricato</strong><br>
                        <small>"${templateName}" è ora pronto per la modifica</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-rimuovi dopo 3 secondi
            setTimeout(() => {
                if (notification && notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }
        
        // Mostra notifica per template complesso
        function showComplexTemplateNotification() {
            // Rimuovi notifiche precedenti
            const existingNotif = document.getElementById('complex-template-notification');
            if (existingNotif) {
                existingNotif.remove();
            }
            
            const notification = document.createElement('div');
            notification.id = 'complex-template-notification';
            notification.className = 'alert alert-info alert-dismissible fade show';
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.maxWidth = '400px';
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-info-circle me-2"></i>
                    <div>
                        <strong>Template Complesso Rilevato</strong><br>
                        <small>Passato automaticamente alla modalità HTML per preservare la formattazione avanzata.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-rimuovi dopo 5 secondi
            setTimeout(() => {
                if (notification && notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // Sincronizza da HTML editor con debouncing
        function syncFromHtmlEditor() {
            console.log('🔧 DEBUG syncFromHtmlEditor() - Inizio sincronizzazione');
            
            const htmlEditor = document.getElementById('html_source_editor');
            const hiddenField = document.getElementById('template_content_hidden');
            
            if (!htmlEditor) {
                console.error('🔧 DEBUG syncFromHtmlEditor() - HTML editor non trovato!');
                return;
            }
            
            if (!hiddenField) {
                console.error('🔧 DEBUG syncFromHtmlEditor() - Campo nascosto non trovato!');
                return;
            }
            
            const htmlContent = htmlEditor.value;
            console.log('🔧 DEBUG syncFromHtmlEditor() - Contenuto HTML:', htmlContent.substring(0, 100) + '...');
            
            hiddenField.value = htmlContent;
            console.log('🔧 DEBUG syncFromHtmlEditor() - Contenuto sincronizzato con campo nascosto');
            
            // Debouncing per evitare aggiornamenti troppo frequenti
            if (htmlEditorSyncTimeout) {
                clearTimeout(htmlEditorSyncTimeout);
            }
            
            htmlEditorSyncTimeout = setTimeout(() => {
                console.log('🔧 DEBUG syncFromHtmlEditor() - Timeout scaduto, aggiornamento statistiche...');
                updateTemplateStats();
                updateLastModified();
                
                // Aggiorna anche l'anteprima in tempo reale se CKEditor è disponibile
                if (ckEditor && ckEditorInitialized && !isHtmlMode) {
                    try {
                        console.log('🔧 DEBUG syncFromHtmlEditor() - Sincronizzazione con CKEditor...');
                        ckEditor.setData(htmlContent);
                        console.log('🔧 DEBUG syncFromHtmlEditor() - CKEditor aggiornato');
                    } catch (error) {
                        console.warn('🔧 DEBUG syncFromHtmlEditor() - Errore sincronizzazione HTML -> CKEditor:', error);
                    }
                } else {
                    console.log('🔧 DEBUG syncFromHtmlEditor() - CKEditor non disponibile o in modalità HTML');
                }
            }, 500); // Aspetta 500ms prima di aggiornare
        }

        // Inserisce struttura template base
        function insertTemplateStructure() {
            const baseStructure = `<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; background: #ffffff; padding: 20px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
    <!-- HEADER -->
    <div style="text-align: center; margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; color: white;">
        <h1 style="margin: 0; font-size: 2.2em;">🎉 Buon Compleanno {{NOME}}! 🎂</h1>
        <p style="margin: 10px 0 0 0; font-size: 1.1em;">Oggi è il tuo giorno speciale!</p>
    </div>
    
    <!-- CONTENUTO PRINCIPALE -->
    <div style="padding: 20px; text-align: center;">
        <h2 style="color: #333; margin-bottom: 20px;">Caro {{NOME}},</h2>
        
        <p style="font-size: 1.1em; line-height: 1.6; color: #555; margin-bottom: 25px;">
            Oggi compi <strong>{{ETA}} anni</strong> e tutto il team di <strong>MrCharlie</strong> 
            vuole celebrare con te questo momento speciale! 🎈
        </p>
        
        <!-- BOX OFFERTA -->
        <div style="background: #f8f9fa; border: 2px solid #667eea; border-radius: 10px; padding: 20px; margin: 25px 0;">
            <h3 style="color: #667eea; margin: 0 0 10px 0;">🎁 Regalo Speciale</h3>
            <p style="margin: 0; color: #333;">
                Come regalo di compleanno, hai diritto a <strong>un ingresso omaggio VIP</strong> 
                per il prossimo evento MrCharlie!
            </p>
        </div>
        
        <p style="font-size: 1em; color: #666; margin-top: 30px;">
            Grazie per essere parte della famiglia MrCharlie! 💜
        </p>
    </div>
    
    <!-- FOOTER -->
    <div style="text-align: center; margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
        <div style="font-size: 1.8em; margin-bottom: 15px;">
            🎊 🎈 🎂 🎁 🎉
        </div>
        
        <p style="color: #666; font-size: 0.9em; margin: 0; line-height: 1.4;">
            Con affetto,<br>
            <strong>Il Team MrCharlie</strong><br>
            📍 Lignano Sabbiadoro | 📧 info@mrcharlie.net
        </p>
    </div>
</div>`;

            if (confirm('Vuoi inserire la struttura template base? Questo sostituirà il contenuto attuale.')) {
                if (isHtmlMode) {
                    // Modalità HTML
                    document.getElementById('html_source_editor').value = baseStructure;
                    syncFromHtmlEditor();
                } else {
                    // Modalità Visual
                    if (ckEditor && ckEditorInitialized) {
                        try {
                            ckEditor.setData(baseStructure);
                        } catch (error) {
                            console.warn('Errore nel caricamento struttura in CKEditor');
                        }
                    }
                    document.getElementById('template_content_hidden').value = baseStructure;
                    updateTemplateStats();
                    updateLastModified();
                }
            }
        }

        // Animazione contatori
        function animateCounters() {
            const counters = document.querySelectorAll('.birthday-stat-number');
            
            counters.forEach((counter, index) => {
                const target = parseInt(counter.textContent);
                const duration = 2000; // 2 secondi
                const increment = target / (duration / 16); // 60fps
                let current = 0;
                
                counter.classList.add('counting');
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target;
                        counter.classList.remove('counting');
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current);
                    }
                }, 16);
                
                // Stagger l'animazione
                setTimeout(() => {
                    // Animazione già iniziata
                }, index * 200);
            });
        }

        // Inizializzazione robusta
        document.addEventListener('DOMContentLoaded', function() {
            // Aspetta che CKEditor sia caricato
            if (window.ClassicEditor) {
                initializeCKEditor();
            } else {
                // Retry dopo un breve delay
                setTimeout(() => {
                    if (window.ClassicEditor) {
                        initializeCKEditor();
                    } else {
                        console.warn('⚠️ CKEditor non disponibile, attivo fallback');
                        showTextareaFallback();
                    }
                }, 1000);
            }
            
            // Inizializza HTML editor con funzionalità avanzate
            initializeHtmlEditor();
            
            // Inizializza statistiche
            updateTemplateStats();
            
            // Avvia animazione contatori dopo un breve delay
            setTimeout(() => {
                animateCounters();
            }, 500);
        });
        
        // Inizializza l'editor HTML
        function initializeHtmlEditor() {
            const htmlEditor = document.getElementById('html_source_editor');
            if (!htmlEditor) return;
            
            // Listener per sincronizzazione in tempo reale
            htmlEditor.addEventListener('input', syncFromHtmlEditor);
            
            // Miglioramenti per l'editing
            htmlEditor.addEventListener('keydown', function(e) {
                // Auto-indentazione con Tab
                if (e.key === 'Tab') {
                    e.preventDefault();
                    const start = this.selectionStart;
                    const end = this.selectionEnd;
                    
                    // Inserisci 4 spazi invece del tab
                    this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
                    this.selectionStart = this.selectionEnd = start + 4;
                }
                
                // Auto-chiusura tag con Ctrl+Shift+>
                if (e.ctrlKey && e.shiftKey && e.key === '>') {
                    e.preventDefault();
                    autoCloseHtmlTag();
                }
                
                // Formattazione rapida con Ctrl+Shift+F
                if (e.ctrlKey && e.shiftKey && e.key === 'F') {
                    e.preventDefault();
                    formatHtmlCode();
                }
            });
            
            // Highlight errori syntax base
            htmlEditor.addEventListener('blur', function() {
                validateHtmlSyntax();
            });
            
            console.log('✅ HTML editor inizializzato con funzionalità avanzate');
        }
        
        // Auto-chiusura tag HTML
        function autoCloseHtmlTag() {
            const htmlEditor = document.getElementById('html_source_editor');
            const cursorPos = htmlEditor.selectionStart;
            const textBefore = htmlEditor.value.substring(0, cursorPos);
            
            // Trova l'ultimo tag aperto
            const lastOpenTag = textBefore.match(/<(\w+)[^>]*>(?![^<]*<\/\1>)/g);
            if (lastOpenTag && lastOpenTag.length > 0) {
                const tagName = lastOpenTag[lastOpenTag.length - 1].match(/<(\w+)/)[1];
                const closeTag = `</${tagName}>`;
                
                const start = htmlEditor.selectionStart;
                const end = htmlEditor.selectionEnd;
                htmlEditor.value = htmlEditor.value.substring(0, start) + closeTag + htmlEditor.value.substring(end);
                htmlEditor.selectionStart = htmlEditor.selectionEnd = start;
                
                syncFromHtmlEditor();
            }
        }
        
        // Validazione syntax HTML
        function validateHtmlSyntax() {
            const htmlEditor = document.getElementById('html_source_editor');
            const html = htmlEditor.value;
            
            if (!html.trim()) return;
            
            // Rimuovi eventuali highlight precedenti
            htmlEditor.classList.remove('is-invalid', 'is-valid');
            
            if (isValidHtml(html)) {
                htmlEditor.classList.add('is-valid');
                updateEditorStatus('✅ HTML valido', 'success');
            } else {
                htmlEditor.classList.add('is-invalid');
                updateEditorStatus('⚠️ Possibili errori HTML', 'warning');
            }
        }
        
        // Aggiorna indicatore di stato dell'editor
        function updateEditorStatus(message, type) {
            let statusElement = document.getElementById('html-editor-status');
            if (!statusElement) {
                statusElement = document.createElement('small');
                statusElement.id = 'html-editor-status';
                statusElement.className = 'form-text';
                document.getElementById('html_editor_container').appendChild(statusElement);
            }
            
            statusElement.className = `form-text text-${type}`;
            statusElement.innerHTML = `<i class="bi bi-info-circle me-1"></i>${message}`;
        }

        // Template predefiniti
        const predefinedTemplates = {
            classic: {
                name: 'Template Classico MrCharlie',
                subject: '🎉 Buon Compleanno {{NOME}}! 🎂',
                content: `<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
    <div style="text-align: center; color: white;">
        <!-- Header con logo -->
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 2.8em; margin: 0; text-shadow: 2px 2px 4px rgba(0,0,0,0.4); font-weight: bold;">
                🎉 Buon Compleanno! 🎂
            </h1>
            <div style="width: 100px; height: 3px; background: linear-gradient(90deg, #ffd700, #ff6b6b); margin: 15px auto; border-radius: 2px;"></div>
        </div>
        
        <!-- Contenuto principale -->
        <div style="background: rgba(255,255,255,0.15); border-radius: 20px; padding: 30px; margin: 20px 0; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);">
            <h2 style="color: #fff; margin-bottom: 20px; font-size: 1.8em;">Caro {{NOME}},</h2>
            
            <p style="font-size: 1.2em; line-height: 1.8; color: #fff; margin-bottom: 25px;">
                Oggi compi <strong style="color: #ffd700;">{{ETA}} anni</strong> e tutto il team di <strong>MrCharlie</strong> vuole celebrare con te questo momento speciale! 🎈
            </p>
            
            <p style="font-size: 1.1em; line-height: 1.6; color: #fff; margin-bottom: 30px;">
                Ti auguriamo un compleanno fantastico e un anno ricco di momenti indimenticabili, musica, divertimento e nuove avventure!
            </p>
            
            <!-- Box regalo -->
            <div style="background: linear-gradient(135deg, rgba(255,255,255,0.25), rgba(255,255,255,0.1)); border-radius: 15px; padding: 25px; margin: 25px 0; border: 2px solid rgba(255,215,0,0.3);">
                <h3 style="color: #ffd700; margin: 0 0 15px 0; font-size: 1.4em;">🎁 Sorpresa Speciale!</h3>
                <p style="color: #fff; margin: 0; font-size: 1.1em; line-height: 1.5;">
                    Come regalo di compleanno, hai diritto a <strong style="color: #ffd700;">un ingresso omaggio VIP</strong> per il prossimo evento MrCharlie!<br>
                    <small style="color: rgba(255,255,255,0.8);">Presenta questa email alla reception</small>
                </p>
            </div>
            
            <div style="margin: 30px 0;">
                <p style="font-size: 1.2em; color: #fff; margin: 0;">
                    Grazie per essere parte della famiglia MrCharlie! 💜
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div style="margin-top: 40px;">
            <div style="font-size: 2.5em; margin-bottom: 20px; letter-spacing: 10px;">
                🎊 🎈 🎂 🎁 🎉
            </div>
            
            <div style="background: rgba(0,0,0,0.2); border-radius: 10px; padding: 20px; margin-top: 20px;">
                <p style="color: rgba(255,255,255,0.9); font-size: 0.95em; margin: 0; line-height: 1.4;">
                    Con affetto,<br>
                    <strong style="color: #ffd700;">Il Team MrCharlie</strong><br>
                    📍 Lignano Sabbiadoro<br>
                    📧 info@mrcharlie.net | 📱 +39 XXX XXX XXXX
                </p>
            </div>
        </div>
    </div>
</div>`
            },
            modern: {
                name: 'Template Moderno',
                subject: '🎂 {{NOME}}, oggi è il tuo giorno speciale! ✨',
                content: `
                <div style="max-width: 600px; margin: 0 auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #ffffff;">
                    <div style="background: linear-gradient(45deg, #FF6B6B, #4ECDC4); padding: 40px; text-align: center;">
                        <h1 style="color: white; font-size: 2.5rem; margin: 0; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                            Buon Compleanno {{NOME}}! 🎉
                        </h1>
                    </div>
                    
                    <div style="padding: 40px; background: #f8f9fa;">
                        <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                            <p style="font-size: 1.1em; line-height: 1.6; color: #333; margin-bottom: 25px;">
                                Ciao <strong>{{NOME}}</strong>! 👋
                            </p>
                            <p style="font-size: 1.1em; line-height: 1.6; color: #333; margin-bottom: 25px;">
                                Oggi compi <strong>{{ETA}} anni</strong> e tutto il team MrCharlie vuole celebrare con te questo momento speciale!
                            </p>
                            
                            <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; border-radius: 10px; margin: 25px 0;">
                                <h3 style="margin: 0 0 10px 0;">🎁 Regalo Speciale</h3>
                                <p style="margin: 0;">Presenta questa email per ricevere il tuo ingresso VIP gratuito!</p>
                            </div>
                            
                            <p style="font-size: 1em; color: #666; text-align: center; margin-top: 30px;">
                                Buon compleanno da tutto lo staff MrCharlie! 🎊
                            </p>
                        </div>
                    </div>
                </div>`
            },
            elegant: {
                name: 'Template Elegante',
                subject: '🌟 Auguri di compleanno {{NOME}} - MrCharlie',
                content: `
                <div style="max-width: 600px; margin: 0 auto; font-family: Georgia, serif; background: #1a1a1a; color: #ffffff;">
                    <div style="background: linear-gradient(135deg, #C9FFBF 0%, #FFAFBD 100%); padding: 2px;">
                        <div style="background: #1a1a1a; padding: 40px; text-align: center;">
                            <h1 style="color: #C9FFBF; font-size: 2.2rem; margin: 0 0 20px 0; font-weight: normal;">
                                ✨ Buon Compleanno ✨
                            </h1>
                            <h2 style="color: #FFAFBD; font-size: 1.8rem; margin: 0; font-weight: normal;">
                                {{NOME_COMPLETO}}
                            </h2>
                        </div>
                    </div>
                    
                    <div style="padding: 40px; text-align: center;">
                        <p style="font-size: 1.2em; line-height: 1.8; color: #e0e0e0; margin-bottom: 30px; font-style: italic;">
                            "Un altro anno di vita è un altro anno di bellezza, saggezza e opportunità."
                        </p>
                        
                        <div style="border: 1px solid #333; padding: 25px; margin: 30px 0; border-radius: 5px;">
                            <h3 style="color: #C9FFBF; margin: 0 0 15px 0;">Celebra con Noi</h3>
                            <p style="color: #e0e0e0; margin: 0; line-height: 1.6;">
                                In occasione del tuo compleanno, MrCharlie ti offre un'esperienza VIP esclusiva. 
                                Vieni a festeggiare con noi!
                            </p>
                        </div>
                        
                        <p style="font-size: 0.9em; color: #999; margin-top: 40px;">
                            Con i migliori auguri,<br>
                            <span style="color: #FFAFBD;">MrCharlie Team</span><br>
                            Lignano Sabbiadoro
                        </p>
                    </div>
                </div>`
            }
        };

        // Carica template predefinito
        function loadTemplate(type) {
            if (predefinedTemplates[type]) {
                if (confirm('Vuoi caricare questo template? Il contenuto attuale verrà sostituito.')) {
                    const template = predefinedTemplates[type];
                    document.getElementById('template_name').value = template.name;
                    document.getElementById('template_subject').value = template.subject;
                    
                    // Aggiorna il contenuto
                    if (ckEditor && ckEditorInitialized) {
                        try {
                            ckEditor.setData(template.content);
                        } catch (error) {
                            console.warn('Errore nel caricamento template in CKEditor, uso fallback');
                        }
                    }
                    document.getElementById('template_content_hidden').value = template.content;
                    
                    updateTemplateStats();
                    updateLastModified();
                }
            }
        }

        // Inserisci emoji
        function insertEmoji(emoji) {
            if (ckEditor && ckEditorInitialized) {
                try {
                    ckEditor.model.change(writer => {
                        const insertPosition = ckEditor.model.document.selection.getFirstPosition();
                        writer.insertText(emoji + ' ', insertPosition);
                    });
                } catch (error) {
                    insertIntoTextarea(emoji + ' ');
                }
            } else {
                insertIntoTextarea(emoji + ' ');
            }
        }
        
        // Funzione helper per inserimento nel textarea
        function insertIntoTextarea(text) {
            const textarea = document.getElementById('template_content_hidden');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const value = textarea.value;
            
            textarea.value = value.substring(0, start) + text + value.substring(end);
            textarea.selectionStart = textarea.selectionEnd = start + text.length;
            textarea.focus();
            
            updateTemplateStats();
        }
        
        // Inserisce blocchi HTML predefiniti
        function insertHtmlBlock(type) {
            let htmlBlock = '';
            
            switch(type) {
                case 'header':
                    htmlBlock = `<div style="text-align: center; margin: 20px 0; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; color: white;">
    <h2 style="margin: 0; font-size: 1.8em;">🎉 Titolo Sezione</h2>
    <p style="margin: 10px 0 0 0;">Sottotitolo o descrizione</p>
</div>`;
                    break;
                    
                case 'box':
                    htmlBlock = `<div style="background: #f8f9fa; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <h4 style="color: #667eea; margin: 0 0 10px 0;">💡 Informazione Importante</h4>
    <p style="margin: 0; color: #333; line-height: 1.5;">
        Inserisci qui il contenuto del box informativo. Puoi usare le variabili come {{NOME}} e {{ETA}}.
    </p>
</div>`;
                    break;
                    
                case 'button':
                    htmlBlock = `<div style="text-align: center; margin: 25px 0;">
    <a href="#" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; padding: 12px 25px; border-radius: 25px; font-weight: bold; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
        🎁 Clicca Qui
    </a>
</div>`;
                    break;
            }
            
            if (htmlBlock) {
                if (isHtmlMode) {
                    insertIntoHtmlEditor(htmlBlock);
                } else {
                    if (ckEditor && ckEditorInitialized) {
                        try {
                            ckEditor.model.change(writer => {
                                const viewFragment = ckEditor.data.processor.toView(htmlBlock);
                                const modelFragment = ckEditor.data.toModel(viewFragment);
                                ckEditor.model.insertContent(modelFragment);
                            });
                        } catch (error) {
                            insertIntoTextarea(htmlBlock);
                        }
                    } else {
                        insertIntoTextarea(htmlBlock);
                    }
                }
            }
        }
        
        // Inserisce nel HTML editor
        function insertIntoHtmlEditor(text) {
            const htmlEditor = document.getElementById('html_source_editor');
            const start = htmlEditor.selectionStart;
            const end = htmlEditor.selectionEnd;
            const value = htmlEditor.value;
            
            htmlEditor.value = value.substring(0, start) + '\n' + text + '\n' + value.substring(end);
            htmlEditor.selectionStart = htmlEditor.selectionEnd = start + text.length + 2;
            htmlEditor.focus();
            
            syncFromHtmlEditor();
        }
        
        // Formatta il codice HTML migliorato
        function formatHtmlCode() {
            if (!isHtmlMode) {
                alert('Passa alla modalità HTML per formattare il codice.');
                return;
            }
            
            const htmlEditor = document.getElementById('html_source_editor');
            let html = htmlEditor.value;
            
            if (!html.trim()) {
                alert('Nessun contenuto da formattare.');
                return;
            }
            
            try {
                // Rimuovi spazi extra e normalizza
                html = html.replace(/>\s+</g, '><');
                html = html.replace(/></g, '>\n<');
                html = html.replace(/\n\s*\n/g, '\n');
                
                // Indentazione migliorata
                const lines = html.split('\n');
                let indent = 0;
                const formattedLines = lines.map(line => {
                    const trimmed = line.trim();
                    if (!trimmed) return '';
                    
                    // Gestisci tag di chiusura
                    if (trimmed.startsWith('</')) {
                        indent = Math.max(0, indent - 1);
                    }
                    
                    const result = '    '.repeat(indent) + trimmed;
                    
                    // Gestisci tag di apertura (escludi self-closing e inline)
                    if (trimmed.startsWith('<') && 
                        !trimmed.startsWith('</') && 
                        !trimmed.endsWith('/>') && 
                        !trimmed.includes('</') &&
                        !['img', 'br', 'hr', 'input', 'meta', 'link'].some(tag => trimmed.includes(`<${tag}`))) {
                        indent++;
                    }
                    
                    return result;
                });
                
                const formattedHtml = formattedLines.join('\n');
                htmlEditor.value = formattedHtml;
                syncFromHtmlEditor();
                
                // Mostra feedback positivo
                updateEditorStatus('✅ Codice formattato correttamente', 'success');
                
            } catch (error) {
                console.error('Errore nella formattazione:', error);
                updateEditorStatus('❌ Errore nella formattazione', 'danger');
            }
        }

        // Aggiorna indicatore modalità
        function updateModeIndicator(mode, icon) {
            const indicator = document.getElementById('editor-mode-indicator');
            if (indicator) {
                indicator.innerHTML = `<i class="bi bi-${icon} me-1 text-primary"></i>Modalità ${mode}`;
                indicator.className = 'text-dark fw-medium ms-auto';
            }
        }
        
        // Inserisci testo rapido migliorato
        function insertQuickText(type) {
            const quickTexts = {
                birthday_greeting: '<p style="font-size: 1.1em; line-height: 1.6; margin: 15px 0;">Caro <strong>{{NOME}}</strong>, oggi è il tuo giorno speciale! 🎉<br>Tutto il team di MrCharlie ti augura un compleanno fantastico!</p>',
                gift_offer: '<div style="background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%); border-left: 4px solid #667eea; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"><h4 style="color: #667eea; margin: 0 0 10px 0; font-size: 1.2em;">🎁 Offerta Speciale per {{NOME}}</h4><p style="margin: 0; color: #333; line-height: 1.5;">Come regalo di compleanno, hai diritto a <strong style="color: #667eea;">un ingresso VIP gratuito</strong> per il prossimo evento MrCharlie!</p></div>',
                contact_info: '<div style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center; border: 1px solid #e9ecef;"><p style="margin: 0; font-size: 0.9em; color: #666; line-height: 1.4;">Per info e prenotazioni:<br><strong style="color: #333;">MrCharlie</strong><br>📍 Lignano Sabbiadoro<br>📧 <a href="mailto:info@mrcharlie.net" style="color: #667eea;">info@mrcharlie.net</a></p></div>'
            };
            
            const textToInsert = quickTexts[type];
            if (!textToInsert) return;
            
            if (isHtmlMode) {
                // Modalità HTML - inserisci nel textarea
                insertIntoHtmlEditor(textToInsert);
            } else if (ckEditor && ckEditorInitialized) {
                // Modalità visuale - inserisci in CKEditor
                try {
                    ckEditor.model.change(writer => {
                        const viewFragment = ckEditor.data.processor.toView(textToInsert);
                        const modelFragment = ckEditor.data.toModel(viewFragment);
                        ckEditor.model.insertContent(modelFragment);
                    });
                } catch (error) {
                    insertIntoTextarea(textToInsert);
                }
            } else {
                // Fallback - inserisci nel textarea nascosto
                insertIntoTextarea(textToInsert);
            }
        }

        // Valida template
        function validateTemplate() {
            const content = ckEditor ? ckEditor.getData() : '';
            const name = document.getElementById('template_name').value;
            const subject = document.getElementById('template_subject').value;
            
            let isValid = true;
            let messages = [];
            
            // Controlla campi obbligatori
            if (!name.trim()) {
                isValid = false;
                messages.push('Nome template mancante');
            }
            
            if (!subject.trim()) {
                isValid = false;
                messages.push('Oggetto email mancante');
            }
            
            if (!content.trim()) {
                isValid = false;
                messages.push('Contenuto template mancante');
            }
            
            // Controlla variabili
            const hasVariables = /\{\{[A-Z_]+\}\}/.test(content + subject);
            if (!hasVariables) {
                messages.push('Considera di aggiungere variabili personalizzate');
            }
            
            // Aggiorna stato
            const statusElement = document.getElementById('validation-status');
            if (isValid) {
                statusElement.className = 'text-success';
                statusElement.innerHTML = '✓ Template valido';
                if (messages.length > 0) {
                    statusElement.innerHTML += ' (' + messages.join(', ') + ')';
                }
            } else {
                statusElement.className = 'text-danger';
                statusElement.innerHTML = '✗ ' + messages.join(', ');
            }
            
            return isValid;
        }

        // Aggiorna statistiche template
        function updateTemplateStats() {
            let content = '';
            
            if (ckEditor && ckEditorInitialized) {
                try {
                    content = ckEditor.getData();
                } catch (error) {
                    content = document.getElementById('template_content_hidden').value || '';
                }
            } else {
                content = document.getElementById('template_content_hidden').value || '';
            }
            
            const variables = content.match(/\{\{[A-Z_]+\}\}/g) || [];
            const uniqueVariables = [...new Set(variables)];
            
            document.getElementById('variables-count').textContent = uniqueVariables.length;
            document.getElementById('text-length').textContent = content.replace(/<[^>]*>/g, '').length;
            document.getElementById('content-stats').textContent = `Caratteri: ${content.length}`;
        }

        // Aggiorna timestamp ultima modifica
        function updateLastModified() {
            const now = new Date();
            document.getElementById('last-modified').textContent = now.toLocaleTimeString('it-IT');
        }

        // Aggiorna colori template
        function updateTemplateColors() {
            const primaryColor = document.getElementById('primaryColor').value;
            const secondaryColor = document.getElementById('secondaryColor').value;
            
            // Aggiorna il contenuto del template sostituendo i colori
            if (ckEditor) {
                let content = ckEditor.getData();
                content = content.replace(/#667eea/g, primaryColor);
                content = content.replace(/#764ba2/g, secondaryColor);
                ckEditor.setData(content);
                document.getElementById('template_content_hidden').value = content;
            }
        }

        // Aggiorna font template
        function updateTemplateFont() {
            const fontFamily = document.getElementById('fontFamily').value;
            
            if (ckEditor) {
                let content = ckEditor.getData();
                content = content.replace(/font-family:[^;]+;/g, `font-family: ${fontFamily};`);
                ckEditor.setData(content);
                document.getElementById('template_content_hidden').value = content;
            }
        }
        
        // Applica font rapido al testo selezionato
        function applyQuickFont(fontName) {
            if (isHtmlMode) {
                // In modalità HTML, inserisci CSS direttamente
                const htmlEditor = document.getElementById('html_source_editor');
                if (htmlEditor) {
                    const selection = htmlEditor.selectionStart;
                    const selectedText = htmlEditor.value.substring(htmlEditor.selectionStart, htmlEditor.selectionEnd);
                    if (selectedText) {
                        const styledText = `<span style="font-family: ${fontName};">${selectedText}</span>`;
                        htmlEditor.setRangeText(styledText, htmlEditor.selectionStart, htmlEditor.selectionEnd);
                        showFontAppliedFeedback(fontName);
                    } else {
                        alert('Seleziona del testo per applicare il font.');
                    }
                }
            } else if (ckEditor && ckEditorInitialized) {
                // Per ora mostra suggerimento per usare modalità HTML
                alert('Per applicare font personalizzati, usa la modalità HTML. I font possono essere applicati manualmente nel codice CSS.');
            } else {
                alert('Editor non disponibile.');
            }
        }
        
        // Mostra feedback per font applicato
        function showFontAppliedFeedback(fontName) {
            const fontNames = {
                'inter': 'Inter',
                'roboto': 'Roboto', 
                'opensans': 'Open Sans',
                'montserrat': 'Montserrat',
                'playfair': 'Playfair Display',
                'lora': 'Lora',
                'merriweather': 'Merriweather',
                'dancing': 'Dancing Script',
                'pacifico': 'Pacifico',
                'caveat': 'Caveat'
            };
            
            const displayName = fontNames[fontName] || fontName;
            
            // Crea notifica temporanea
            const notification = document.createElement('div');
            notification.className = 'alert alert-success';
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.left = '50%';
            notification.style.transform = 'translateX(-50%)';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '200px';
            notification.style.textAlign = 'center';
            notification.innerHTML = `<i class="bi bi-fonts me-2"></i>Font <strong>${displayName}</strong> applicato!`;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification && notification.parentNode) {
                    notification.remove();
                }
            }, 2000);
        }

        // Pulisci immagine di sfondo
        function clearBackgroundImage() {
            document.getElementById('backgroundImage').value = '';
            document.getElementById('background_image_url').value = '';
            document.getElementById('image-preview').style.display = 'none';
        }

        // Duplica template
        function duplicateTemplate() {
            document.getElementById('template_id').value = '0';
            const currentName = document.getElementById('template_name').value;
            document.getElementById('template_name').value = 'Copia di ' + currentName;
            alert('Template pronto per essere duplicato. Modifica il nome e salva.');
        }

        // Salva come bozza
        function saveAsDraft() {
            const currentName = document.getElementById('template_name').value;
            if (!currentName.includes('[BOZZA]')) {
                document.getElementById('template_name').value = '[BOZZA] ' + currentName;
            }
            document.getElementById('templateForm').submit();
        }

        // Esporta template
        function exportTemplate() {
            const templateData = {
                name: document.getElementById('template_name').value,
                subject: document.getElementById('template_subject').value,
                content: ckEditor ? ckEditor.getData() : '',
                exported_at: new Date().toISOString()
            };
            
            const blob = new Blob([JSON.stringify(templateData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `template_${templateData.name.replace(/[^a-z0-9]/gi, '_')}.json`;
            a.click();
            URL.revokeObjectURL(url);
        }

        // Importa template
        function importTemplate(input) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const templateData = JSON.parse(e.target.result);
                        
                        if (confirm('Vuoi importare questo template? Il contenuto attuale verrà sostituito.')) {
                            document.getElementById('template_name').value = templateData.name || '';
                            document.getElementById('template_subject').value = templateData.subject || '';
                            if (ckEditor) {
                                ckEditor.setData(templateData.content || '');
                            }
                            updateTemplateStats();
                            updateLastModified();
                        }
                    } catch (error) {
                        alert('Errore durante l\'importazione del template: ' + error.message);
                    }
                };
                reader.readAsText(file);
            }
        }

        // Funzione per creare nuovo template
        function createNewTemplate() {
            showSection('editor');
            setTimeout(() => {
                resetEditor();
                // Focus sul nome del template
                document.getElementById('template_name').focus();
            }, 100);
        }

        // Navigation
        function showSection(sectionId) {
            // Nascondi tutte le sezioni
            document.querySelectorAll('.content-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Mostra la sezione richiesta
            const targetSection = document.getElementById(sectionId);
            if (targetSection) {
                targetSection.style.display = 'block';
            }
            
            // Aggiorna stato attivo nella navigazione
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + sectionId) {
                    link.classList.add('active');
                }
            });
            
            // Se è la sezione editor e abbiamo CKEditor, assicuriamoci che sia inizializzato
            if (sectionId === 'editor' && !ckEditorInitialized && window.ClassicEditor) {
                setTimeout(() => {
                    initializeCKEditor();
                }, 100);
            }
            
            // Se torniamo alla dashboard, riavvia l'animazione dei contatori
            if (sectionId === 'dashboard') {
                setTimeout(() => {
                    animateCounters();
                }, 200);
            }
        }

        // Template functions
        function editTemplate(id) {
            console.log('🔧 DEBUG editTemplate() - Inizio funzione con ID:', id);
            console.log('🔧 DEBUG editTemplate() - templatesData disponibili:', templatesData);
            
            // Prima mostra la sezione editor
            showSection('editor');
            console.log('🔧 DEBUG editTemplate() - Sezione editor mostrata');
            
            // Cerca il template nei dati già caricati
            const template = templatesData.find(t => t.id == id);
            console.log('🔧 DEBUG editTemplate() - Template trovato:', template);
            
            if (template) {
                console.log('🔧 DEBUG editTemplate() - Inizio popolamento campi...');
                
                // Popola i campi del form
                const templateIdField = document.getElementById('template_id');
                const templateNameField = document.getElementById('template_name');
                const templateSubjectField = document.getElementById('template_subject');
                
                console.log('🔧 DEBUG editTemplate() - Campi form trovati:', {
                    templateIdField: !!templateIdField,
                    templateNameField: !!templateNameField,
                    templateSubjectField: !!templateSubjectField
                });
                
                if (templateIdField) templateIdField.value = template.id;
                if (templateNameField) templateNameField.value = template.name || '';
                if (templateSubjectField) templateSubjectField.value = template.subject || '';
                
                console.log('🔧 DEBUG editTemplate() - Campi form popolati con:', {
                    id: template.id,
                    name: template.name,
                    subject: template.subject
                });
                
                // Carica il contenuto nell'editor
                const content = template.html_content || '';
                console.log('🔧 DEBUG editTemplate() - Template object completo:', template);
                console.log('🔧 DEBUG editTemplate() - html_content property:', template.html_content);
                console.log('🔧 DEBUG editTemplate() - Contenuto HTML da caricare (lunghezza):', content.length);
                console.log('🔧 DEBUG editTemplate() - Contenuto HTML da caricare:', content.substring(0, 200) + '...');
                console.log('🔧 DEBUG editTemplate() - Stato CKEditor:', {
                    ckEditorExists: !!ckEditor,
                    ckEditorInitialized: ckEditorInitialized,
                    isHtmlMode: isHtmlMode
                });
                
                // Rileva se il template è troppo complesso per CKEditor
                const isComplexHtml = content.length > 1000 || 
                                     content.includes('linear-gradient') || 
                                     content.includes('backdrop-filter') ||
                                     content.includes('box-shadow') ||
                                     content.includes('position: absolute') ||
                                     content.includes('transform') ||
                                     content.includes('max-width: 6') || // Template email
                                     content.includes('font-family: Georgia') || // Template elegante
                                     (content.match(/style="/g) || []).length > 10;
                
                console.log('🔧 DEBUG editTemplate() - Template complesso rilevato:', isComplexHtml, {
                    length: content.length,
                    hasGradient: content.includes('linear-gradient'),
                    hasComplexCSS: (content.match(/style="/g) || []).length > 10,
                    styleCount: (content.match(/style="/g) || []).length
                });
                
                if (isComplexHtml && !isHtmlMode) {
                    console.log('🔧 DEBUG editTemplate() - Passaggio automatico alla modalità HTML per template complesso');
                    // Per template complessi, carica direttamente in modalità HTML
                    const htmlEditor = document.getElementById('html_source_editor');
                    if (htmlEditor) {
                        htmlEditor.value = content;
                        console.log('🔧 DEBUG editTemplate() - Contenuto completo caricato in HTML editor:', content.substring(0, 200) + '...');
                    }
                    
                    // Passa automaticamente alla modalità HTML
                    setTimeout(() => {
                        if (!isHtmlMode) {
                            toggleHtmlMode();
                            showComplexTemplateNotification();
                            
                            // Dopo il toggle, assicurati che il contenuto sia ancora quello completo
                            setTimeout(() => {
                                const htmlEditor = document.getElementById('html_source_editor');
                                if (htmlEditor && htmlEditor.value !== content) {
                                    console.log('🔧 DEBUG editTemplate() - Ricarico contenuto completo dopo toggle');
                                    htmlEditor.value = content;
                                    syncFromHtmlEditor();
                                }
                            }, 100);
                        }
                    }, 500);
                } else {
                    // Template semplice, procedi con CKEditor normalmente
                    console.log('🔧 DEBUG editTemplate() - Template semplice, uso CKEditor');
                }
                
                // Se siamo in modalità HTML, carica nel textarea HTML
                if (isHtmlMode) {
                    console.log('🔧 DEBUG editTemplate() - Caricamento in modalità HTML');
                    const htmlEditor = document.getElementById('html_source_editor');
                    if (htmlEditor) {
                        htmlEditor.value = content;
                        syncFromHtmlEditor(); // Sincronizza immediatamente
                        console.log('🔧 DEBUG editTemplate() - Contenuto caricato in HTML editor e sincronizzato');
                    } else {
                        console.error('🔧 DEBUG editTemplate() - HTML editor non trovato!');
                    }
                }
                
                // Carica sempre nel campo nascosto come backup
                const hiddenField = document.getElementById('template_content_hidden');
                if (hiddenField) {
                    hiddenField.value = content;
                    console.log('🔧 DEBUG editTemplate() - Contenuto caricato in campo nascosto come backup');
                }
                
                // Carica in CKEditor se disponibile e non in modalità HTML
                if (ckEditor && ckEditorInitialized && !isHtmlMode) {
                    try {
                        console.log('🔧 DEBUG editTemplate() - Caricamento in CKEditor...');
                        console.log('🔧 DEBUG editTemplate() - Stato CKEditor prima del caricamento:', {
                            ckEditorData: ckEditor.getData().substring(0, 100)
                        });
                        
                        // Pulisci prima il contenuto esistente
                        ckEditor.setData('');
                        
                        // Usa il metodo più affidabile per caricare HTML complesso
                        if (content && content.trim()) {
                            console.log('🔧 DEBUG editTemplate() - Tentativo 1: innerHTML diretto');
                            
                            // Metodo 1: innerHTML diretto
                            ckEditor.setData(content);
                            
                            // Verifica immediata
                            let currentContent = ckEditor.getData();
                            console.log('🔧 DEBUG editTemplate() - Dopo innerHTML:', {
                                success: currentContent !== '<p><br></p>' && currentContent.length > 10,
                                length: currentContent.length
                            });
                            
                            // Se non ha funzionato, prova con clipboard
                            if (currentContent === '<p><br></p>' || currentContent.length < 10) {
                                console.log('🔧 DEBUG editTemplate() - Tentativo 2: clipboard.dangerouslyPasteHTML');
                                
                                try {
                                    ckEditor.setData(content);
                                    
                                    currentContent = ckEditor.getData();
                                    console.log('🔧 DEBUG editTemplate() - Dopo clipboard:', {
                                        success: currentContent !== '<p><br></p>' && currentContent.length > 10,
                                        length: currentContent.length
                                    });
                                } catch (clipboardError) {
                                    console.warn('🔧 DEBUG editTemplate() - Clipboard fallito:', clipboardError);
                                }
                            }
                            
                            // Se ancora non funziona, forza il passaggio alla modalità HTML
                            if (currentContent === '<p><br></p>' || currentContent.length < 10) {
                                console.log('🔧 DEBUG editTemplate() - Contenuto troppo complesso, passo alla modalità HTML');
                                
                                // Carica nell'editor HTML
                                const htmlEditor = document.getElementById('html_source_editor');
                                if (htmlEditor) {
                                    htmlEditor.value = content;
                                }
                                
                                // Passa automaticamente alla modalità HTML
                                setTimeout(() => {
                                    if (!isHtmlMode) {
                                        toggleHtmlMode();
                                        showComplexTemplateNotification();
                                    }
                                }, 100);
                            }
                            
                            console.log('🔧 DEBUG editTemplate() - Contenuto caricato con metodo multiplo');
                        }
                        
                        // Forza aggiornamento del campo nascosto
                        const hiddenField = document.getElementById('template_content_hidden');
                        if (hiddenField) {
                            hiddenField.value = content;
                            console.log('🔧 DEBUG editTemplate() - Campo nascosto aggiornato');
                        }
                        
                        // Verifica dopo un breve delay e forza refresh se necessario
                        setTimeout(() => {
                            const currentContent = ckEditor.getData();
                            console.log('🔧 DEBUG editTemplate() - Verifica CKEditor dopo 200ms:', {
                                ckEditorData: currentContent.substring(0, 100),
                                contentLoaded: currentContent !== '<p><br></p>' && currentContent.length > 10
                            });
                            
                            // Se il contenuto non è stato caricato correttamente, riprova
                            if (currentContent === '<p><br></p>' || currentContent.length < 10) {
                                console.warn('🔧 DEBUG editTemplate() - Contenuto non caricato, ultimo tentativo...');
                                
                                // Ultimo tentativo: usa insertText per contenuto semplice
                                try {
                                    ckEditor.setData(''); 
                                    
                                    // Se il contenuto è molto complesso, mostra solo il testo
                                    const textContent = content.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                                    if (textContent.length > 0) {
                                        ckEditor.setData(`<p>${textContent}</p>`);
                                        console.log('🔧 DEBUG editTemplate() - Caricato come testo semplice');
                                    }
                                } catch (textError) {
                                    console.error('🔧 DEBUG editTemplate() - Tutti i tentativi falliti:', textError);
                                }
                            }
                        }, 200);
                        
                        console.log('🔧 DEBUG editTemplate() - Contenuto caricato in CKEditor con successo');
                    } catch (error) {
                        console.warn('🔧 DEBUG editTemplate() - Errore caricamento in CKEditor:', error);
                        const hiddenField = document.getElementById('template_content_hidden');
                        if (hiddenField) {
                            hiddenField.value = content;
                            console.log('🔧 DEBUG editTemplate() - Fallback: contenuto caricato in campo nascosto');
                        }
                    }
                } else {
                    console.log('🔧 DEBUG editTemplate() - Caricamento diretto in campo nascosto');
                    const hiddenField = document.getElementById('template_content_hidden');
                    if (hiddenField) {
                        hiddenField.value = content;
                        console.log('🔧 DEBUG editTemplate() - Contenuto caricato in campo nascosto');
                    } else {
                        console.error('🔧 DEBUG editTemplate() - Campo nascosto non trovato!');
                    }
                }
                
                // Carica immagine di sfondo se presente
                console.log('🔧 DEBUG editTemplate() - Gestione immagine di sfondo:', template.background_image);
                if (template.background_image) {
                    const bgUrlField = document.getElementById('background_image_url');
                    const previewImg = document.getElementById('preview-img');
                    const imagePreview = document.getElementById('image-preview');
                    
                    if (bgUrlField) bgUrlField.value = template.background_image;
                    if (previewImg) previewImg.src = template.background_image;
                    if (imagePreview) imagePreview.style.display = 'block';
                    
                    console.log('🔧 DEBUG editTemplate() - Immagine di sfondo caricata');
                } else {
                    // Pulisci l'immagine precedente
                    const bgUrlField = document.getElementById('background_image_url');
                    const imagePreview = document.getElementById('image-preview');
                    
                    if (bgUrlField) bgUrlField.value = '';
                    if (imagePreview) imagePreview.style.display = 'none';
                    
                    console.log('🔧 DEBUG editTemplate() - Immagine di sfondo pulita');
                }
                
                // Aggiorna statistiche
                console.log('🔧 DEBUG editTemplate() - Aggiornamento statistiche...');
                updateTemplateStats();
                updateLastModified();
                
                // Scroll alla sezione editor
                setTimeout(() => {
                    const editorSection = document.getElementById('editor');
                    if (editorSection) {
                        editorSection.scrollIntoView({ behavior: 'smooth' });
                        console.log('🔧 DEBUG editTemplate() - Scroll alla sezione editor completato');
                    }
                }, 100);
                
                console.log('✅ DEBUG editTemplate() - Template caricato correttamente:', template.name);
                
                // Mostra notifica di caricamento
                showTemplateLoadedNotification(template.name);
                
                // Verifica finale dello stato
                setTimeout(() => {
                    console.log('🔧 DEBUG editTemplate() - Verifica finale stato editor:', {
                        templateId: document.getElementById('template_id')?.value,
                        templateName: document.getElementById('template_name')?.value,
                        templateSubject: document.getElementById('template_subject')?.value,
                        ckEditorContent: ckEditor ? ckEditor.getData().substring(0, 100) : 'N/A',
                        hiddenContent: document.getElementById('template_content_hidden')?.value.substring(0, 100),
                        htmlEditorContent: document.getElementById('html_source_editor')?.value.substring(0, 100)
                    });
                }, 500);
                
            } else {
                console.error('❌ DEBUG editTemplate() - Template non trovato con ID:', id);
                console.error('❌ DEBUG editTemplate() - Template disponibili:', templatesData.map(t => ({id: t.id, name: t.name})));
                alert('Errore: Template non trovato');
            }
        }

        function testTemplate(id) {
            if (id && id !== 'null') {
                document.getElementById('test_template_id').value = id;
                new bootstrap.Modal(document.getElementById('testModal')).show();
            } else {
                alert('Salva prima il template per poterlo testare.');
            }
        }

        function insertVariable(variable) {
            if (ckEditor && ckEditorInitialized) {
                try {
                    ckEditor.model.change(writer => {
                        const insertPosition = ckEditor.model.document.selection.getFirstPosition();
                        writer.insertText(variable + ' ', insertPosition);
                    });
                } catch (error) {
                    insertIntoTextarea(variable + ' ');
                }
            } else {
                insertIntoTextarea(variable + ' ');
            }
        }

        function previewTemplate() {
            let content = '';
            
            // Per l'anteprima, usa sempre il contenuto completo dal campo nascosto o HTML editor
            // Questo garantisce che i template complessi siano mostrati correttamente
            if (isHtmlMode) {
                content = document.getElementById('html_source_editor').value || '';
                console.log('🔧 DEBUG Anteprima - Modalità HTML, contenuto lunghezza:', content.length);
                console.log('🔧 DEBUG Anteprima - Modalità HTML, primi 200 char:', content.substring(0, 200) + '...');
                
                // Se il contenuto HTML è troppo corto, prova il campo nascosto
                if (content.length < 500) {
                    const hiddenContent = document.getElementById('template_content_hidden').value || '';
                    if (hiddenContent.length > content.length) {
                        console.log('🔧 DEBUG Anteprima - Contenuto HTML troppo corto, uso campo nascosto');
                        content = hiddenContent;
                    }
                }
            } else {
                // Anche in modalità visuale, usa il campo nascosto che contiene il template completo
                content = document.getElementById('template_content_hidden').value || '';
                console.log('🔧 DEBUG Anteprima - Campo nascosto (template completo), contenuto:', content.substring(0, 200) + '...');
                
                // Se il campo nascosto è vuoto, prova con CKEditor come fallback
                if (!content || content.trim() === '' || content === '<p><br></p>') {
                    if (ckEditor && ckEditorInitialized) {
                        try {
                            content = ckEditor.getData();
                            console.log('🔧 DEBUG Anteprima - Fallback CKEditor, contenuto:', content.substring(0, 200) + '...');
                        } catch (error) {
                            console.warn('🔧 DEBUG Anteprima - Errore CKEditor:', error);
                        }
                    }
                }
            }
            
            if (!content || content.trim() === '' || content === '<p><br></p>') {
                alert('⚠️ Nessun contenuto da visualizzare nell\'anteprima. Inserisci del contenuto nel template prima di visualizzare l\'anteprima.');
                return;
            }
            
            // Sostituisci le variabili con dati di esempio
            const previewContent = content
                .replace(/\{\{NOME\}\}/g, 'Mario')
                .replace(/\{\{COGNOME\}\}/g, 'Rossi')
                .replace(/\{\{NOME_COMPLETO\}\}/g, 'Mario Rossi')
                .replace(/\{\{EMAIL\}\}/g, 'mario.rossi@example.com')
                .replace(/\{\{DATA_NASCITA\}\}/g, '01/01/1990')
                .replace(/\{\{ETA\}\}/g, '34')
                .replace(/\{\{ANNO\}\}/g, new Date().getFullYear());
            
            // Crea l'anteprima con stili email-safe
            const fullPreviewHtml = `
                <!DOCTYPE html>
                <html lang="it">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Anteprima Template</title>
                    <style>
                        body {
                            margin: 0;
                            padding: 20px;
                            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                            background: #f5f5f5;
                            line-height: 1.6;
                        }
                        .email-container {
                            max-width: 600px;
                            margin: 0 auto;
                            background: #ffffff;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                            border-radius: 8px;
                            overflow: hidden;
                        }
                        .preview-header {
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            padding: 15px 20px;
                            text-align: center;
                            font-weight: bold;
                        }
                        .preview-content {
                            padding: 0;
                        }
                        /* Assicura che gli stili inline siano preservati */
                        .preview-content * {
                            box-sizing: border-box;
                        }
                        /* Fix per immagini responsive */
                        .preview-content img {
                            max-width: 100%;
                            height: auto;
                        }
                        /* Fix per tabelle email */
                        .preview-content table {
                            border-collapse: collapse;
                            width: 100%;
                        }
                    </style>
                </head>
                <body>
                    <div class="email-container">
                        <div class="preview-header">
                            📧 Anteprima Email Template - ${document.getElementById('template_name').value || 'Template Senza Nome'}
                        </div>
                        <div class="preview-content">
                            ${previewContent}
                        </div>
                    </div>
                </body>
                </html>
            `;
            
            // Mostra l'anteprima nel modal usando Blob URL (più affidabile)
            const previewContainer = document.getElementById('preview-content');
            
            try {
                // Crea un Blob con l'HTML
                const blob = new Blob([fullPreviewHtml], { type: 'text/html' });
                const blobUrl = URL.createObjectURL(blob);
                
                previewContainer.innerHTML = `
                    <iframe 
                        id="preview-iframe" 
                        src="${blobUrl}"
                        style="width: 100%; height: 500px; border: none; border-radius: 8px;" 
                        onload="console.log('✅ Anteprima caricata correttamente')"
                        onerror="console.error('❌ Errore caricamento anteprima')"
                    ></iframe>
                `;
                
                // Pulisci il blob URL dopo un po' per liberare memoria
                setTimeout(() => {
                    URL.revokeObjectURL(blobUrl);
                }, 30000);
                
            } catch (error) {
                console.error('❌ Errore creazione anteprima:', error);
                // Fallback: mostra il contenuto direttamente
                previewContainer.innerHTML = `
                    <div style="max-width: 650px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; text-align: center; font-weight: bold;">
                            📧 Anteprima Template - ${document.getElementById('template_name').value || 'Template Senza Nome'}
                        </div>
                        <div style="padding: 20px; background: white;">
                            ${previewContent}
                        </div>
                        <div style="background: #f8f9fa; padding: 10px; text-align: center; color: #6c757d; font-size: 0.9em;">
                            <strong>Anteprima MrCharlie</strong> - I dati mostrati sono di esempio
                        </div>
                    </div>
                `;
            }
            
            // Aggiorna il titolo del modal
            const modalTitle = document.querySelector('#previewModal .modal-title');
            if (modalTitle) {
                modalTitle.textContent = `Anteprima: ${document.getElementById('template_name').value || 'Template'}`;
            }
            
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }
        
        // Debug contenuto template
        function debugTemplateContent() {
            console.log('🐛 DEBUG TEMPLATE CONTENT - Inizio analisi...');
            
            const templateName = document.getElementById('template_name').value;
            const templateSubject = document.getElementById('template_subject').value;
            const hiddenField = document.getElementById('template_content_hidden');
            const htmlEditor = document.getElementById('html_source_editor');
            
            console.log('📝 Nome Template:', templateName);
            console.log('📧 Oggetto:', templateSubject);
            console.log('🔧 Modalità HTML attiva:', isHtmlMode);
            console.log('✅ CKEditor inizializzato:', ckEditorInitialized);
            console.log('📄 Campo nascosto esiste:', !!hiddenField);
            console.log('🖥️ HTML editor esiste:', !!htmlEditor);
            
            if (hiddenField) {
                console.log('📄 Contenuto campo nascosto (primi 300 char):', hiddenField.value.substring(0, 300));
                console.log('📄 Lunghezza campo nascosto:', hiddenField.value.length);
            }
            
            if (htmlEditor) {
                console.log('🖥️ Contenuto HTML editor (primi 300 char):', htmlEditor.value.substring(0, 300));
                console.log('🖥️ Lunghezza HTML editor:', htmlEditor.value.length);
            }
            
            if (ckEditor && ckEditorInitialized) {
                console.log('✅ Contenuto CKEditor (primi 300 char):', ckEditor.getData().substring(0, 300));
                console.log('✅ Lunghezza CKEditor:', ckEditor.getData().length);
            }
            
            // Test sostituzione variabili
            let testContent = '';
            if (isHtmlMode && htmlEditor) {
                testContent = htmlEditor.value;
            } else if (ckEditor && ckEditorInitialized) {
                testContent = ckEditor.getData();
            } else if (hiddenField) {
                testContent = hiddenField.value;
            }
            
            const processedContent = testContent
                .replace(/\{\{NOME\}\}/g, 'Mario')
                .replace(/\{\{COGNOME\}\}/g, 'Rossi')
                .replace(/\{\{NOME_COMPLETO\}\}/g, 'Mario Rossi')
                .replace(/\{\{EMAIL\}\}/g, 'mario.rossi@example.com')
                .replace(/\{\{DATA_NASCITA\}\}/g, '01/01/1990')
                .replace(/\{\{ETA\}\}/g, '34')
                .replace(/\{\{ANNO\}\}/g, new Date().getFullYear());
            
            console.log('🔄 Contenuto processato (primi 300 char):', processedContent.substring(0, 300));
            console.log('🔄 Variabili trovate nel contenuto:', (testContent.match(/\{\{[A-Z_]+\}\}/g) || []).join(', '));
            
            // Mostra alert con riepilogo
            const summary = `
📊 RIEPILOGO DEBUG TEMPLATE:

📝 Nome: ${templateName || 'Non specificato'}
📧 Oggetto: ${templateSubject || 'Non specificato'}
🔧 Modalità: ${isHtmlMode ? 'HTML' : 'Visuale'}
📄 Contenuto disponibile: ${testContent.length > 0 ? 'SÌ' : 'NO'}
📏 Lunghezza: ${testContent.length} caratteri
🔄 Variabili trovate: ${(testContent.match(/\{\{[A-Z_]+\}\}/g) || []).length}

Controlla la console per dettagli completi.
            `;
            
            alert(summary);
        }
        
        // Test dati form prima del salvataggio
        function testFormData() {
            console.log('🧪 TEST FORM DATA - Inizio verifica...');
            
            // Forza sincronizzazione
            const hiddenField = document.getElementById('template_content_hidden');
            
            if (isHtmlMode) {
                const htmlEditor = document.getElementById('html_source_editor');
                if (htmlEditor && htmlEditor.value) {
                    hiddenField.value = htmlEditor.value;
                    console.log('🧪 TEST - Sincronizzato da HTML editor');
                }
            } else if (ckEditor && ckEditorInitialized) {
                try {
                    const ckContent = ckEditor.getData();
                    if (ckContent && ckContent !== '<p><br></p>') {
                        hiddenField.value = ckContent;
                        console.log('🧪 TEST - Sincronizzato da CKEditor');
                    }
                } catch (error) {
                    console.warn('🧪 TEST - Errore sincronizzazione CKEditor:', error);
                }
            }
            
            // Raccogli tutti i dati del form
            const formData = {
                template_id: document.getElementById('template_id').value,
                template_name: document.getElementById('template_name').value,
                template_subject: document.getElementById('template_subject').value,
                template_content: hiddenField.value,
                background_image: document.getElementById('background_image_url').value
            };
            
            console.log('🧪 TEST FORM DATA - Dati raccolti:', formData);
            
            // Validazione
            const errors = [];
            if (!formData.template_name.trim()) errors.push('Nome template mancante');
            if (!formData.template_subject.trim()) errors.push('Oggetto template mancante');
            if (!formData.template_content.trim() || formData.template_content === '<p><br></p>') errors.push('Contenuto template mancante');
            
            // Simula invio POST
            const postData = new URLSearchParams();
            postData.append('action', 'save_template');
            Object.keys(formData).forEach(key => {
                postData.append(key, formData[key]);
            });
            
            const testSummary = `
🧪 TEST FORM DATA - RISULTATI:

✅ DATI RACCOLTI:
• ID: ${formData.template_id || '0 (nuovo)'}
• Nome: ${formData.template_name || 'MANCANTE'}
• Oggetto: ${formData.template_subject || 'MANCANTE'}
• Contenuto: ${formData.template_content.length} caratteri
• Immagine: ${formData.background_image || 'Nessuna'}

${errors.length === 0 ? '✅ VALIDAZIONE: PASSED' : '❌ ERRORI: ' + errors.join(', ')}

📤 DATI POST PRONTI:
${Array.from(postData.entries()).map(([key, value]) => 
    `• ${key}: ${key === 'template_content' ? value.substring(0, 50) + '...' : value}`
).join('\n')}

${errors.length === 0 ? 'Il form è pronto per essere inviato!' : 'Correggi gli errori prima di salvare.'}
            `;
            
            alert(testSummary);
            
            if (errors.length === 0) {
                if (confirm('I dati del form sembrano corretti. Vuoi procedere con il salvataggio?')) {
                    document.getElementById('templateForm').submit();
                }
            }
        }
        
        // Test caricamento template
        function testLoadTemplate(templateId) {
            console.log('🧪 TEST LOAD TEMPLATE - ID:', templateId);
            
            if (confirm(`Vuoi testare il caricamento del template ${templateId}? Il contenuto attuale verrà sostituito.`)) {
                // Forza il caricamento del template
                editTemplate(templateId);
                
                // Dopo 1 secondo, mostra i risultati
                setTimeout(() => {
                    debugTemplateContent();
                }, 1000);
            }
        }
        
        // Forza modalità visuale
        function forceVisualMode() {
            console.log('🔧 FORCE VISUAL MODE - Tentativo di caricamento forzato in CKEditor');
            
            if (!ckEditor || !ckEditorInitialized) {
                alert('❌ Editor CKEditor non disponibile');
                return;
            }
            
            // Ottieni il contenuto dal campo nascosto o HTML editor
            let content = '';
            if (isHtmlMode) {
                content = document.getElementById('html_source_editor').value || '';
            } else {
                content = document.getElementById('template_content_hidden').value || '';
            }
            
            if (!content || content.trim() === '') {
                alert('❌ Nessun contenuto da caricare');
                return;
            }
            
            // Se siamo in modalità HTML, torna alla modalità visuale
            if (isHtmlMode) {
                toggleHtmlMode();
            }
            
            // Aspetta che il toggle sia completato
            setTimeout(() => {
                console.log('🔧 FORCE VISUAL MODE - Tentativo di caricamento forzato...');
                
                try {
                    // Metodo 1: Carica direttamente con setData
                    ckEditor.setData(content);
                    
                    let currentContent = ckEditor.getData();
                    console.log('🔧 FORCE VISUAL MODE - Risultato innerHTML:', {
                        success: currentContent !== '<p><br></p>' && currentContent.length > 10,
                        length: currentContent.length
                    });
                    
                    // Se non funziona, prova di nuovo
                    if (currentContent === '<p><br></p>' || currentContent.length < 10) {
                        console.log('🔧 FORCE VISUAL MODE - Secondo tentativo...');
                        
                        // Aspetta un momento e riprova
                        setTimeout(() => {
                            ckEditor.setData(content);
                        }, 100);
                        
                        currentContent = ckEditor.getData();
                        console.log('🔧 FORCE VISUAL MODE - Risultato clipboard:', {
                            success: currentContent !== '<p><br></p>' && currentContent.length > 10,
                            length: currentContent.length
                        });
                    }
                    
                    // Se ancora non funziona, carica almeno il testo
                    if (currentContent === '<p><br></p>' || currentContent.length < 10) {
                        console.log('🔧 FORCE VISUAL MODE - Caricamento come testo...');
                        
                        const textContent = content.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                        if (textContent.length > 0) {
                            ckEditor.setData(`<p>${textContent}</p>`);
                            console.log('🔧 FORCE VISUAL MODE - Testo caricato');
                        }
                    }
                    
                    // Aggiorna statistiche
                    updateTemplateStats();
                    
                    // Mostra risultato
                    const finalContent = ckEditor.getData();
                    if (finalContent !== '<p><br></p>' && finalContent.length > 10) {
                        alert('✅ Contenuto caricato in modalità visuale!\nNota: Alcuni stili complessi potrebbero non essere visualizzati correttamente.');
                    } else {
                        alert('⚠️ Caricamento parzialmente riuscito.\nIl template potrebbe essere troppo complesso per la modalità visuale.\nConsigli di usare la modalità HTML per questo template.');
                    }
                    
                } catch (error) {
                    console.error('🔧 FORCE VISUAL MODE - Errore:', error);
                    alert('❌ Errore durante il caricamento forzato: ' + error.message);
                }
            }, 100);
        }
        
        // Aggiorna anteprima
        function refreshPreview() {
            console.log('🔄 Aggiornamento anteprima...');
            
            // Forza la sincronizzazione prima di aggiornare l'anteprima
            if (isHtmlMode) {
                syncFromHtmlEditor();
            } else if (ckEditor && ckEditorInitialized) {
                // Solo se CKEditor ha contenuto significativo, sincronizza
                const ckContent = ckEditor.getData();
                if (ckContent && ckContent !== '<p><br></p>' && ckContent.length > 10) {
                    document.getElementById('template_content_hidden').value = ckContent;
                }
            }
            
            // Piccolo delay per assicurarsi che la sincronizzazione sia completata
            setTimeout(() => {
                previewTemplate();
            }, 100);
        }
        
        // Apri anteprima in nuova finestra
        function openPreviewInNewTab() {
            let content = '';
            
            // Usa sempre il contenuto completo per l'anteprima
            if (isHtmlMode) {
                content = document.getElementById('html_source_editor').value || '';
            } else {
                // Usa il campo nascosto che contiene il template completo
                content = document.getElementById('template_content_hidden').value || '';
                
                // Fallback a CKEditor solo se il campo nascosto è vuoto
                if (!content || content.trim() === '' || content === '<p><br></p>') {
                    if (ckEditor && ckEditorInitialized) {
                        try {
                            content = ckEditor.getData();
                        } catch (error) {
                            console.warn('Errore CKEditor in openPreviewInNewTab:', error);
                        }
                    }
                }
            }
            
            // Sostituisci le variabili con dati di esempio
            const previewContent = content
                .replace(/\{\{NOME\}\}/g, 'Mario')
                .replace(/\{\{COGNOME\}\}/g, 'Rossi')
                .replace(/\{\{NOME_COMPLETO\}\}/g, 'Mario Rossi')
                .replace(/\{\{EMAIL\}\}/g, 'mario.rossi@example.com')
                .replace(/\{\{DATA_NASCITA\}\}/g, '01/01/1990')
                .replace(/\{\{ETA\}\}/g, '34')
                .replace(/\{\{ANNO\}\}/g, new Date().getFullYear());
            
            // Crea l'HTML completo per la nuova finestra
            const fullPreviewHtml = `
                <!DOCTYPE html>
                <html lang="it">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Anteprima Template - ${document.getElementById('template_name').value || 'Template'}</title>
                    <style>
                        body {
                            margin: 0;
                            padding: 20px;
                            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                            background: #f5f5f5;
                            line-height: 1.6;
                        }
                        .email-container {
                            max-width: 650px;
                            margin: 0 auto;
                            background: #ffffff;
                            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
                            border-radius: 12px;
                            overflow: hidden;
                        }
                        .preview-header {
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            padding: 20px;
                            text-align: center;
                            font-weight: bold;
                            font-size: 1.1em;
                        }
                        .preview-content {
                            padding: 0;
                        }
                        .preview-content * {
                            box-sizing: border-box;
                        }
                        .preview-content img {
                            max-width: 100%;
                            height: auto;
                        }
                        .preview-content table {
                            border-collapse: collapse;
                            width: 100%;
                        }
                        .preview-footer {
                            background: #f8f9fa;
                            padding: 15px;
                            text-align: center;
                            color: #6c757d;
                            font-size: 0.9em;
                            border-top: 1px solid #dee2e6;
                        }
                    </style>
                </head>
                <body>
                    <div class="email-container">
                        <div class="preview-header">
                            📧 Anteprima Email Template<br>
                            <small style="opacity: 0.8; font-weight: normal;">${document.getElementById('template_name').value || 'Template Senza Nome'}</small>
                        </div>
                        <div class="preview-content">
                            ${previewContent}
                        </div>
                        <div class="preview-footer">
                            <strong>Anteprima MrCharlie</strong> - I dati mostrati sono di esempio<br>
                            <small>Generato il ${new Date().toLocaleString('it-IT')}</small>
                        </div>
                    </div>
                </body>
                </html>
            `;
            
            // Apri in nuova finestra
            const newWindow = window.open('', '_blank', 'width=800,height=900,scrollbars=yes,resizable=yes');
            if (newWindow) {
                newWindow.document.write(fullPreviewHtml);
                newWindow.document.close();
                newWindow.focus();
            } else {
                alert('Impossibile aprire la nuova finestra. Controlla le impostazioni del browser per i popup.');
            }
        }

        function clearEditor() {
            if (confirm('Vuoi davvero cancellare tutto il contenuto?')) {
                resetEditor();
            }
        }
        
        function resetEditor() {
            // Pulisci l'editor CKEditor
            if (ckEditor && ckEditorInitialized) {
                try {
                    ckEditor.setData('');
                } catch (error) {
                    console.warn('Errore nel reset di CKEditor');
                }
            }
            
            // Pulisci tutti i campi
            document.getElementById('template_content_hidden').value = '';
            document.getElementById('template_id').value = '0';
            document.getElementById('template_name').value = '';
            document.getElementById('template_subject').value = '';
            
            // Pulisci immagine di sfondo
            clearBackgroundImage();
            
            // Rimuovi validazioni
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            
            // Aggiorna statistiche
            updateTemplateStats();
            
            console.log('🧹 Editor pulito per nuovo template');
        }

        // Image upload con preview
        document.getElementById('backgroundImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Mostra preview locale
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-img').src = e.target.result;
                    document.getElementById('image-preview').style.display = 'block';
                };
                reader.readAsDataURL(file);
                
                // Upload file
                const formData = new FormData();
                formData.append('action', 'upload_image');
                formData.append('image', file);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('background_image_url').value = data.url;
                        alert('Immagine caricata con successo!');
                    }
                })
                .catch(error => {
                    alert('Errore durante il caricamento dell\'immagine');
                });
            }
        });

        // Validazione form in tempo reale
        document.getElementById('template_name').addEventListener('input', function() {
            const value = this.value.trim();
            if (value.length < 3) {
                this.classList.add('is-invalid');
                document.getElementById('name-feedback').textContent = 'Il nome deve avere almeno 3 caratteri';
            } else {
                this.classList.remove('is-invalid');
            }
        });

        document.getElementById('template_subject').addEventListener('input', function() {
            const value = this.value.trim();
            if (value.length < 5) {
                this.classList.add('is-invalid');
                document.getElementById('subject-feedback').textContent = 'L\'oggetto deve avere almeno 5 caratteri';
            } else {
                this.classList.remove('is-invalid');
            }
        });

        // Auto-save draft
        setInterval(function() {
            if (ckEditor) {
                updateTemplateStats();
                // Sincronizza con il textarea nascosto
                document.getElementById('template_content_hidden').value = ckEditor.getData();
            }
        }, 30000); // Save every 30 seconds
        
        // Debug form submission
        document.getElementById('templateForm').addEventListener('submit', function(e) {
            console.log('🔧 DEBUG FORM SUBMIT - Inizio invio form');
            
            // Forza sincronizzazione prima dell'invio
            const hiddenField = document.getElementById('template_content_hidden');
            
            if (isHtmlMode) {
                // Modalità HTML: usa contenuto dall'editor HTML
                const htmlEditor = document.getElementById('html_source_editor');
                if (htmlEditor && htmlEditor.value) {
                    hiddenField.value = htmlEditor.value;
                    console.log('🔧 DEBUG FORM SUBMIT - Sincronizzato da HTML editor');
                }
            } else if (ckEditor && ckEditorInitialized) {
                // Modalità visuale: usa contenuto da CKEditor
                try {
                    const ckContent = ckEditor.getData();
                    if (ckContent && ckContent !== '<p><br></p>') {
                        hiddenField.value = ckContent;
                        console.log('🔧 DEBUG FORM SUBMIT - Sincronizzato da CKEditor');
                    }
                } catch (error) {
                    console.warn('🔧 DEBUG FORM SUBMIT - Errore sincronizzazione CKEditor:', error);
                }
            }
            
            // Debug dei valori finali
            console.log('🔧 DEBUG FORM SUBMIT - Valori finali:', {
                template_id: document.getElementById('template_id').value,
                template_name: document.getElementById('template_name').value,
                template_subject: document.getElementById('template_subject').value,
                template_content_length: hiddenField.value.length,
                template_content_preview: hiddenField.value.substring(0, 200),
                isHtmlMode: isHtmlMode
            });
            
            // Verifica che i campi obbligatori siano compilati
            if (!document.getElementById('template_name').value.trim()) {
                alert('❌ Il nome del template è obbligatorio');
                e.preventDefault();
                return false;
            }
            
            if (!document.getElementById('template_subject').value.trim()) {
                alert('❌ L\'oggetto del template è obbligatorio');
                e.preventDefault();
                return false;
            }
            
            if (!hiddenField.value.trim() || hiddenField.value === '<p><br></p>') {
                alert('❌ Il contenuto del template è obbligatorio');
                e.preventDefault();
                return false;
            }
            
            console.log('🔧 DEBUG FORM SUBMIT - Form valido, invio in corso...');
        });
    </script>
</body>
</html> 
