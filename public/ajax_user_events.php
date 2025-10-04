<?php
/**
 * AJAX endpoint per caricare gli eventi di un utente specifico
 * Chiamato da admin_scripts.js nella funzione loadUserEvents()
 */

// Disabilita la visualizzazione degli errori per AJAX
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Override configurazione per produzione
require_once __DIR__ . '/config_override.php';

// Imposta header JSON
header('Content-Type: application/json; charset=utf-8');

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

// Includi il bootstrap per DB e config
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

// Verifica che sia una richiesta GET con email
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parametri non validi']);
    exit;
}

$email = trim($_GET['email']);

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email non valida']);
    exit;
}

try {
    // Query per ottenere tutti gli eventi dell'utente
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.nome,
            u.cognome,
            u.email,
            u.telefono,
            u.data_nascita,
            u.created_at,
            u.validato,
            u.validated_at,
            u.email_inviata,
            u.token,
            e.id as evento_id,
            e.titolo as evento_titolo,
            e.event_date,
            e.chiuso as evento_chiuso
        FROM utenti u
        LEFT JOIN events e ON u.evento = e.id
        WHERE u.email = :email
        ORDER BY e.event_date DESC, u.created_at DESC
    ");
    
    $stmt->execute([':email' => $email]);
    $eventi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($eventi)) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'Nessun evento trovato per questo utente'
        ]);
        exit;
    }
    
    // Calcola statistiche utente
    $totalEventi = count($eventi);
    $eventiValidati = 0;
    $ultimaPresenza = null;
    $primaIscrizione = null;
    
    foreach ($eventi as $evento) {
        if ($evento['validato'] == 1) {
            $eventiValidati++;
            if (!$ultimaPresenza || $evento['validated_at'] > $ultimaPresenza) {
                $ultimaPresenza = $evento['validated_at'];
            }
        }
        
        if (!$primaIscrizione || $evento['created_at'] < $primaIscrizione) {
            $primaIscrizione = $evento['created_at'];
        }
    }
    
    $tassoPresenza = $totalEventi > 0 ? round(($eventiValidati / $totalEventi) * 100, 1) : 0;
    
    // Formatta i dati per il frontend
    $eventiFormattati = [];
    foreach ($eventi as $evento) {
        $eventiFormattati[] = [
            'id' => $evento['id'],
            'evento_id' => $evento['evento_id'],
            'evento_titolo' => $evento['evento_titolo'],
            'event_date' => $evento['event_date'],
            'event_date_formatted' => $evento['event_date'] ? date('d/m/Y', strtotime($evento['event_date'])) : 'N/A',
            'created_at' => $evento['created_at'],
            'created_at_formatted' => $evento['created_at'] ? date('d/m/Y H:i', strtotime($evento['created_at'])) : 'N/A',
            'validato' => (bool)$evento['validato'],
            'validated_at' => $evento['validated_at'],
            'validated_at_formatted' => $evento['validated_at'] ? date('d/m/Y H:i', strtotime($evento['validated_at'])) : null,
            'email_inviata' => $evento['email_inviata'],
            'email_inviata_formatted' => $evento['email_inviata'] ? date('d/m/Y H:i', strtotime($evento['email_inviata'])) : null,
            'token' => $evento['token'],
            'evento_chiuso' => (bool)$evento['evento_chiuso']
        ];
    }
    
    // Genera HTML per la visualizzazione con layout adminmobile perfetto
    $html = '<div class="user-events-details p-4" style="
        background: linear-gradient(135deg, 
            rgba(255, 255, 255, 0.1) 0%, 
            rgba(255, 255, 255, 0.05) 100%);
        backdrop-filter: blur(24px) saturate(180%);
        -webkit-backdrop-filter: blur(24px) saturate(180%);
        border-radius: 28px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06), 
                    0 16px 48px rgba(0, 0, 0, 0.1);
        color: rgba(255, 255, 255, 0.9);
        font-family: -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
        position: relative;
        overflow: hidden;
        animation: fadeInUp 0.6s ease-out;
    ">';
    
    // Effetto gradient top
    $html .= '<div style="
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, 
            transparent 0%, 
            rgba(107, 115, 255, 0.15) 50%, 
            transparent 100%);
    "></div>';
    
    // Header elegante con effetto glass - stile adminmobile
    $html .= '<div class="d-flex justify-content-between align-items-center mb-4" style="
        background: linear-gradient(135deg, 
            rgba(255, 255, 255, 0.08) 0%, 
            rgba(255, 255, 255, 0.04) 100%);
        backdrop-filter: blur(24px) saturate(180%);
        border-radius: 20px;
        padding: 1.5rem;
        border: 1px solid rgba(255, 255, 255, 0.08);
        position: relative;
        overflow: hidden;
    ">';
    
    // Avatar e info utente
    $html .= '<div class="d-flex align-items-center">';
    
    // Avatar circolare con gradient
    $initials = strtoupper(substr($eventi[0]['nome'], 0, 1) . substr($eventi[0]['cognome'], 0, 1));
    $html .= '<div style="
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6B73FF 0%, #81D4FA 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: white;
        margin-right: 1rem;
        font-size: 1.4rem;
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    ">' . $initials . '</div>';
    
    // Info utente
    $html .= '<div>';
    $html .= '<h5 class="mb-1" style="
        color: rgba(255, 255, 255, 0.95);
        font-weight: 600;
        font-size: 1.3rem;
        margin: 0;
    ">' . htmlspecialchars($eventi[0]['nome'] . ' ' . $eventi[0]['cognome']) . '</h5>';
    $html .= '<small style="
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9rem;
    ">' . htmlspecialchars($eventi[0]['email']) . '</small>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Badge con numero eventi
    $html .= '<div style="
        background: linear-gradient(135deg, #6B73FF 0%, #9BB5FF 100%);
        color: white;
        padding: 0.6rem 1.2rem;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        box-shadow: 0 4px 20px rgba(107, 115, 255, 0.3);
        border: 1px solid rgba(107, 115, 255, 0.15);
        backdrop-filter: blur(10px);
    ">';
    $html .= '<i class="bi bi-calendar-event me-2"></i>' . $totalEventi . ' Eventi';
    $html .= '</div>';
    
    $html .= '</div>'; // Fine header
    
    // Statistiche inline con design adminmobile
    $html .= '<div class="row g-3 mb-4">';
    
    // Card Iscrizioni
    $html .= '<div class="col-md-3">';
    $html .= '<div class="stats-card" style="
        background: linear-gradient(135deg, 
            rgba(255, 255, 255, 0.08) 0%, 
            rgba(255, 255, 255, 0.04) 100%);
        border-radius: 20px;
        padding: 1.2rem;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.08);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    ">
        <div style="font-size: 1.8rem; font-weight: 700; background: linear-gradient(135deg, #6B73FF 0%, #9BB5FF 100%); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.3rem;">' . $totalEventi . '</div>
        <div style="color: rgba(255, 255, 255, 0.8); font-size: 0.8rem; font-weight: 500;">Iscrizioni</div>
    </div>';
    $html .= '</div>';
    
    // Card Presenze
    $html .= '<div class="col-md-3">';
    $html .= '<div class="stats-card" style="
        background: linear-gradient(135deg, 
            rgba(126, 211, 33, 0.15) 0%, 
            rgba(168, 226, 72, 0.05) 100%);
        border-radius: 20px;
        padding: 1.2rem;
        text-align: center;
        border: 1px solid rgba(126, 211, 33, 0.15);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    ">
        <div style="font-size: 1.8rem; font-weight: 700; color: #7ED321; margin-bottom: 0.3rem;">' . $eventiValidati . '</div>
        <div style="color: rgba(126, 211, 33, 0.8); font-size: 0.8rem; font-weight: 500;">Presenze</div>
    </div>';
    $html .= '</div>';
    
    // Card Tasso
    $tassoColore = $tassoPresenza >= 80 ? '#7ED321' : ($tassoPresenza >= 60 ? '#FFB74D' : '#FF8A80');
    $tassoGlass = $tassoPresenza >= 80 ? 'rgba(126, 211, 33, 0.15)' : ($tassoPresenza >= 60 ? 'rgba(255, 183, 77, 0.15)' : 'rgba(255, 138, 128, 0.15)');
    
    $html .= '<div class="col-md-3">';
    $html .= '<div class="stats-card" style="
        background: linear-gradient(135deg, 
            ' . $tassoGlass . ' 0%, 
            rgba(255, 255, 255, 0.02) 100%);
        border-radius: 20px;
        padding: 1.2rem;
        text-align: center;
        border: 1px solid ' . $tassoGlass . ';
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    ">
        <div style="font-size: 1.8rem; font-weight: 700; color: ' . $tassoColore . '; margin-bottom: 0.3rem;">' . $tassoPresenza . '%</div>
        <div style="color: rgba(255, 255, 255, 0.8); font-size: 0.8rem; font-weight: 500;">Tasso</div>
    </div>';
    $html .= '</div>';
    
    // Card Dal (prima iscrizione)
    $html .= '<div class="col-md-3">';
    $html .= '<div class="stats-card" style="
        background: linear-gradient(135deg, 
            rgba(129, 212, 250, 0.15) 0%, 
            rgba(179, 229, 252, 0.05) 100%);
        border-radius: 20px;
        padding: 1.2rem;
        text-align: center;
        border: 1px solid rgba(129, 212, 250, 0.15);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    ">
        <div style="font-size: 1.1rem; font-weight: 600; color: #81D4FA; margin-bottom: 0.3rem;">' . 
        (isset($primaIscrizione) && $primaIscrizione ? 
            date('M Y', strtotime($primaIscrizione)) : 'N/A') . '</div>
        <div style="color: rgba(129, 212, 250, 0.8); font-size: 0.8rem; font-weight: 500;">Dal</div>
    </div>';
    $html .= '</div>';
    
    $html .= '</div>'; // Fine row statistiche
    
    // Tabella eventi con design adminmobile
    if (!empty($eventiFormattati)) {
        $html .= '<div style="
            background: linear-gradient(135deg, 
                rgba(255, 255, 255, 0.05) 0%, 
                rgba(255, 255, 255, 0.02) 100%);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            overflow: hidden;
            backdrop-filter: blur(20px);
        ">';
        
        // Header tabella
        $html .= '<div style="
            background: linear-gradient(135deg, 
                rgba(255, 255, 255, 0.08) 0%, 
                rgba(255, 255, 255, 0.04) 100%);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-weight: 600;
            color: rgba(255, 255, 255, 0.95);
            font-size: 1.1rem;
        ">';
        $html .= '<i class="bi bi-calendar-event me-2" style="color: #6B73FF;"></i>Cronologia Eventi';
        $html .= '</div>';
        
        // Contenuto tabella
        $html .= '<div style="max-height: 400px; overflow-y: auto;">';
        
        foreach ($eventiFormattati as $index => $evento) {
            $statusColor = $evento['validato'] ? '#7ED321' : '#FF8A80';
            $statusBg = $evento['validato'] ? 'rgba(126, 211, 33, 0.15)' : 'rgba(255, 138, 128, 0.15)';
            $statusText = $evento['validato'] ? 'Presente' : 'Assente';
            $statusIcon = $evento['validato'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
            
            $html .= '<div class="event-row" style="
                padding: 1rem 1.5rem;
                border-bottom: 1px solid rgba(255, 255, 255, 0.03);
                transition: all 0.3s ease;
                position: relative;
            ">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center flex-grow-1">
                        <!-- Icona evento -->
                        <div style="
                            width: 40px;
                            height: 40px;
                            border-radius: 50%;
                            background: linear-gradient(135deg, #6B73FF 0%, #81D4FA 100%);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-right: 1rem;
                            font-size: 0.9rem;
                            color: white;
                            font-weight: 600;
                        ">' . date('d', strtotime($evento['event_date'])) . '</div>
                        
                        <!-- Info evento -->
                        <div class="flex-grow-1">
                            <div style="
                                font-weight: 600;
                                color: rgba(255, 255, 255, 0.95);
                                font-size: 1rem;
                                margin-bottom: 0.2rem;
                            ">' . htmlspecialchars($evento['evento_titolo'] ?: 'N/A') . '</div>
                            <div style="
                                font-size: 0.8rem;
                                color: rgba(255, 255, 255, 0.6);
                            ">
                                <i class="bi bi-calendar3 me-1"></i>' . htmlspecialchars($evento['event_date_formatted']) . '
                                <span style="margin: 0 0.5rem;">•</span>
                                <i class="bi bi-person-plus me-1"></i>Iscritto: ' . htmlspecialchars(date('d/m', strtotime($evento['created_at']))) . '
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status badge -->
                    <div style="
                        background: ' . $statusBg . ';
                        color: ' . $statusColor . ';
                        padding: 0.4rem 0.8rem;
                        border-radius: 16px;
                        font-size: 0.8rem;
                        font-weight: 600;
                        border: 1px solid ' . $statusBg . ';
                        backdrop-filter: blur(10px);
                        display: flex;
                        align-items: center;
                    ">
                        <i class="bi ' . $statusIcon . ' me-1"></i>' . $statusText . '
                    </div>
                </div>
            </div>';
        }
        
        $html .= '</div>'; // Fine contenuto tabella
        $html .= '</div>'; // Fine tabella
        
    } else {
        // Empty state elegante
        $html .= '<div style="
            text-align: center;
            padding: 3rem 2rem;
            color: rgba(255, 255, 255, 0.7);
        ">';
        $html .= '<i class="bi bi-calendar-x" style="
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.6;
            background: linear-gradient(135deg, #6B73FF, #81D4FA);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: brightness(1.2);
        "></i>';
        $html .= '<h4 style="
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        ">Nessun evento trovato</h4>';
        $html .= '<p style="
            font-size: 0.9rem;
            opacity: 0.8;
        ">Questo utente non ha ancora partecipato a nessun evento.</p>';
        $html .= '</div>';
    }
    
    // Aggiungi animazioni CSS inline
    $html .= '<style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.1);
            border-color: rgba(255, 255, 255, 0.12);
        }
        
        .event-row:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
            transform: translateY(-1px);
        }
        
        /* Scrollbar personalizzata */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #6B73FF 0%, #9BB5FF 100%);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #9BB5FF 0%, #6B73FF 100%);
        }
    </style>';
    
    $html .= '</div>'; // Fine container principale
    
    // Risposta JSON di successo
    echo json_encode([
        'success' => true,
        'data' => $eventiFormattati,
        'html' => $html,
        'stats' => [
            'total_eventi' => $totalEventi,
            'eventi_validati' => $eventiValidati,
            'tasso_presenza' => $tassoPresenza,
            'prima_iscrizione' => $primaIscrizione,
            'ultima_presenza' => $ultimaPresenza
        ],
        'user_info' => [
            'nome' => $eventi[0]['nome'],
            'cognome' => $eventi[0]['cognome'],
            'email' => $eventi[0]['email'],
            'telefono' => $eventi[0]['telefono'],
            'data_nascita' => $eventi[0]['data_nascita']
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore database: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore generale: ' . $e->getMessage()
    ]);
}
?> 