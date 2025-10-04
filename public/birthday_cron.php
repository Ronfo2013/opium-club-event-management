<?php
/**
 * Script Cron per Controllo Automatico Compleanni
 * Da eseguire giornalmente alle 9:00
 * 
 * Comando cron suggerito:
 * 0 9 * * * /usr/bin/php /path/to/birthday_cron.php
 */

// Impostazioni di base
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Solo da command line
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Accesso negato. Questo script può essere eseguito solo da command line.');
}

// Include sistema compleanni
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/birthday_system.php';

// Funzione logging
function logMessage($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message" . PHP_EOL;
    
    // Log su file
    $logFile = __DIR__ . '/logs/birthday_cron.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Output anche su console
    echo $logEntry;
}

try {
    logMessage("=== INIZIO CONTROLLO COMPLEANNI AUTOMATICO ===");
    
    // Carica configurazione
    $bootstrap = require_once __DIR__ . '/../src/bootstrap.php';
    $pdo = $bootstrap['db'];
    $config = $bootstrap['config'];
    
    // Inizializza sistema compleanni
    $birthdaySystem = new BirthdaySystem($pdo, $config);
    
    logMessage("Sistema compleanni inizializzato correttamente");
    
    // Esegui controllo compleanni
    $result = $birthdaySystem->checkAndSendBirthdayWishes();
    
    // Log risultati
    logMessage("Controllo completato:", 'SUCCESS');
    logMessage("- Data: " . $result['date']);
    logMessage("- Compleanni trovati: " . $result['total_found']);
    logMessage("- Auguri inviati: " . $result['total_sent']);
    
    // Statistiche aggiuntive
    $stats = $birthdaySystem->getBirthdayStats();
    logMessage("Statistiche sistema:");
    logMessage("- Compleanni oggi: " . $stats['today_birthdays']);
    logMessage("- Auguri inviati quest'anno: " . $stats['sent_this_year']);
    logMessage("- Prossimi compleanni (30gg): " . $stats['upcoming_birthdays']);
    logMessage("- Template attivi: " . $stats['total_templates']);
    
    // Notifica se ci sono problemi
    if ($result['total_found'] > 0 && $result['total_sent'] == 0) {
        logMessage("⚠️ ATTENZIONE: Trovati compleanni ma nessun augurio inviato!", 'WARNING');
        
        // Invia notifica amministratore se configurato
        if (isset($config['admin_email'])) {
            $subject = "MrCharlie - Problema Sistema Compleanni";
            $message = "Trovati {$result['total_found']} compleanni oggi ma nessun augurio è stato inviato. Controlla la configurazione.";
            
            mail($config['admin_email'], $subject, $message);
            logMessage("Notifica problema inviata a: " . $config['admin_email']);
        }
    }
    
    // Log prossimi compleanni se presenti
    if ($stats['upcoming_birthdays'] > 0) {
        $upcomingList = $birthdaySystem->getUpcomingBirthdays(7); // Prossimi 7 giorni
        
        if (!empty($upcomingList)) {
            logMessage("Prossimi compleanni (7 giorni):");
            foreach ($upcomingList as $birthday) {
                $daysText = $birthday['days_until'] == 0 ? 'OGGI' : "tra {$birthday['days_until']} giorni";
                logMessage("- {$birthday['nome']} {$birthday['cognome']} ({$birthday['email']}) - {$daysText}");
            }
        }
    }
    
    logMessage("=== FINE CONTROLLO COMPLEANNI ===");
    
    // Exit code 0 = successo
    exit(0);
    
} catch (Exception $e) {
    logMessage("ERRORE FATALE: " . $e->getMessage(), 'ERROR');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    
    // Invia notifica errore se configurato
    if (isset($config['admin_email'])) {
        $subject = "MrCharlie - ERRORE Sistema Compleanni";
        $message = "Errore durante controllo compleanni automatico:\n\n" . $e->getMessage() . "\n\nStack trace:\n" . $e->getTraceAsString();
        
        mail($config['admin_email'], $subject, $message);
        logMessage("Notifica errore inviata a: " . $config['admin_email'], 'ERROR');
    }
    
    // Exit code 1 = errore
    exit(1);
}
?> 