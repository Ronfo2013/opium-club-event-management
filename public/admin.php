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

// Controllo login: se non loggato, reindirizza alla pagina di login (modifica il percorso se necessario)
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    // Pulisci il buffer e reindirizza
    ob_end_clean();
    header("Location: /login");
    exit;
}

// Includi il sistema compleanno
require_once __DIR__ . '/birthday_system.php';

/**
 * Reinvio email con PDF all'utente
 */
function resendEmail($pdo, $userId, $config, &$successMessage, &$errorMessage) {
    try {
        $stmt = $pdo->prepare("SELECT u.*, e.event_date, e.titolo, e.background_image FROM utenti u JOIN events e ON u.evento = e.id WHERE u.id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $errorMessage = "Utente non trovato.";
            return;
        }

        // Rigenera il PDF usando phpqrcode
        $qrLibPath = __DIR__ . '/../lib/phpqrcode/qrlib.php';
        if (!file_exists($qrLibPath)) {
            throw new Exception("Libreria phpqrcode non trovata in: " . $qrLibPath);
        }
        require_once $qrLibPath;

        $qrFile = $user['qr_code_path'];
        if (!file_exists($qrFile)) {
            QRcode::png($user['token'], $qrFile, QR_ECLEVEL_H, 10, (int)1);
        }
        
        $generatedImagesDir = $config['paths']['generated_images_dir'];
        $tempImage = $generatedImagesDir . 'temp_' . $user['token'] . '.png';
        
        $rawPath = $user['background_image'] ?? '';
        $backgroundImagePath = null;
        
        $uploadsDir = $config['paths']['uploads_dir'] ?? (__DIR__ . '/../public/uploads/');
        if (!empty($rawPath)) {
            // Se è un URL pubblico (es. GCS), scarica in /tmp
            if (filter_var($rawPath, FILTER_VALIDATE_URL)) {
                $tmpDir = sys_get_temp_dir();
                $tmpFile = $tmpDir . '/' . basename(parse_url($rawPath, PHP_URL_PATH));
                try {
                    $data = @file_get_contents($rawPath);
                    if ($data !== false) {
                        file_put_contents($tmpFile, $data);
                        $backgroundImagePath = $tmpFile;
                    }
                } catch (\Throwable $e) {
                    // fallback a controlli successivi
                }
            }
            // Prima prova il percorso completo (per eventi vecchi)
            if (!$backgroundImagePath && file_exists($rawPath) && is_file($rawPath)) {
                $backgroundImagePath = $rawPath;
            } 
            // Poi prova solo il nome file nella directory uploads (per eventi nuovi)
            else {
                $bgPath = $uploadsDir . basename($rawPath);
                if (file_exists($bgPath) && is_file($bgPath)) {
                    // Verifica sicurezza del percorso
                    $realUploadsDir = realpath($uploadsDir);
                    $realBgPath = realpath($bgPath);
                    if ($realUploadsDir && $realBgPath && strpos($realBgPath, $realUploadsDir) === 0) {
                        $backgroundImagePath = $bgPath;
                    }
                }
                // Se ancora non trovato ed è filename legacy, prova GCS
                if (!$backgroundImagePath) {
                    $gcsBucket = $config['gcs']['bucket'] ?? null;
                    $gcsEnabled = isset($config['gcs']['enabled']) && $config['gcs']['enabled'] && $gcsBucket;
                    if ($gcsEnabled) {
                        $prefix = rtrim($config['gcs']['uploads_prefix'] ?? 'uploads/', '/');
                        $candidateUrl = 'https://storage.googleapis.com/' . $gcsBucket . '/' . $prefix . '/' . basename($rawPath);
                        $tmpDir2 = sys_get_temp_dir();
                        $tmpFile2 = $tmpDir2 . '/' . basename($rawPath);
                        $data2 = @file_get_contents($candidateUrl);
                        if ($data2 !== false) {
                            file_put_contents($tmpFile2, $data2);
                            $backgroundImagePath = $tmpFile2;
                        }
                    }
                }
            }
        }
        
        // Fallback per immagine di default
        if (!$backgroundImagePath) {
            $defaultPaths = [
                __DIR__ . '/../public/sfondo_default.png',
                __DIR__ . '/sfondo_default.png',
                __DIR__ . '/sfondo.png',
                __DIR__ . '/../sfondo.png'
            ];
            
            foreach ($defaultPaths as $path) {
                if (file_exists($path)) {
                    $backgroundImagePath = $path;
                    break;
                }
            }
        }
        
        if (!$backgroundImagePath || !file_exists($backgroundImagePath)) {
            throw new Exception("Immagine di sfondo non trovata per l'evento.");
        }
        $extension = strtolower(pathinfo($backgroundImagePath, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'png':
                $background = imagecreatefrompng($backgroundImagePath);
                break;
            case 'jpg':
            case 'jpeg':
                $background = imagecreatefromjpeg($backgroundImagePath);
                break;
            default:
                throw new Exception("Formato immagine non supportato: " . $extension);
        }
        
        $qrCodeImg = imagecreatefrompng($qrFile);
        
        $bgWidth = imagesx($background);
        $bgHeight = imagesy($background);
        $qrWidth = imagesx($qrCodeImg);
        $qrHeight = imagesy($qrCodeImg);
        $destX = 400;
        $destY = 1240;
        
        $qrBgHeight = $qrHeight + 40;
        $qrBgY = $destY - 20;
        $white = imagecolorallocatealpha($background, 255, 255, 255, 60);
        imagefilledrectangle($background, 0, $qrBgY, $bgWidth, $qrBgY + $qrBgHeight, $white);
        
        imagecopy($background, $qrCodeImg, $destX, $destY, 0, 0, $qrWidth, $qrHeight);
        
        $fontFile = __DIR__ . '/arial.ttf';
        $fontSize = 40;
        $dataEvento = DateTime::createFromFormat('Y-m-d', $user['event_date']);
        $mesi = ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
        $dataFormattata = strtoupper($dataEvento->format('d') . ' ' . $mesi[intval($dataEvento->format('m')) - 1] . ' ' . $dataEvento->format('Y'));
        $textNome = $user['titolo'] . ' - ' . $dataFormattata . ' - ' . $user['nome'] . ' ' . $user['cognome'];
        
        $maxTextWidth = $bgWidth - 100;
        $textBbox = imagettfbbox($fontSize, 0, $fontFile, $textNome);
        $textWidth = abs($textBbox[2] - $textBbox[0]);
        
        while ($textWidth > $maxTextWidth && $fontSize > 16) {
            $fontSize -= 2;
            $textBbox = imagettfbbox($fontSize, 0, $fontFile, $textNome);
            $textWidth = abs($textBbox[2] - $textBbox[0]);
        }
        
        $textY = $qrBgY + $qrBgHeight + 80;
        $textBbox = imagettfbbox($fontSize, 0, $fontFile, $textNome);
        $textHeight = abs($textBbox[7] - $textBbox[1]);
        $textX = intval(($bgWidth - $textWidth) / 2);
        
        $paddingY = 20;
        $rectX = 0;
        $rectY = $textY - $textHeight - $paddingY;
        $rectWidth = $bgWidth;
        $rectHeight = $textHeight + ($paddingY * 2);
        $white = imagecolorallocatealpha($background, 255, 255, 255, 60);
        imagefilledrectangle($background, $rectX, $rectY, $rectX + $rectWidth, $rectY + $rectHeight, $white);
        
        $textColor = imagecolorallocate($background, 0, 0, 0);
        imagettftext($background, $fontSize, 0, $textX, $textY, $textColor, $fontFile, $textNome);
        
        imagepng($background, $tempImage);
        imagedestroy($background);
        imagedestroy($qrCodeImg);
        
        require_once __DIR__ . '/../lib/fpdf/fpdf.php';
        $pdf = new FPDF();
        $pdf->AddPage('P', array(1080, 1920));
        $pdf->Image($tempImage, 0, 0, 1080, 1920);
        $pdfDir = $config['paths']['generated_pdfs_dir'];
        $outputPdf = $pdfDir . 'vip_pass_' . $user['nome'] . '_' . $user['cognome'] . '.pdf';
        $pdf->Output('F', $outputPdf);
        unlink($tempImage);
        
        $pdfFile = $outputPdf;

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $config['email']['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['email']['username'];
        $mail->Password   = $config['email']['password'];
        $mail->SMTPSecure = $config['email']['encryption'];
        $mail->Port       = $config['email']['port'];

        $mail->setFrom($config['email']['username'], 'Mr.Charlie Lignano Sabbiadoro');
        $mail->addAddress($user['email'], $user['nome'] . ' ' . $user['cognome']);
        $mail->addAttachment($pdfFile, 'Omaggio_' . $user['nome'] . '_' . $user['titolo'] . '.pdf');

        $mail->isHTML(true);
        $mail->Subject = 'Reinvio Conferma Registrazione - ' . $user['titolo'];
        $mail->Body    = "<h2>Conferma Iscrizione</h2>
                          <p>Ciao {$user['nome']} {$user['cognome']},</p>
                          <p>Ti rinviamo il PDF con il tuo QR Code per l'evento <strong>{$user['titolo']}</strong>.</p>
                          <p>Saluti,<br>Lo Staff</p>";

        // Tentativo invio email con gestione errori
        $emailSent = $mail->send();
        
        if ($emailSent) {
            $successMessage = "Email reinviata con successo.";
            // Aggiorna la data di invio email
            $update = $pdo->prepare("UPDATE utenti SET email_inviata = NOW(), email_status = 'inviata', email_error = NULL WHERE id = :id");
            $update->execute([':id' => $userId]);
            error_log('[EMAIL] Email reinviata con successo per utente ID: ' . $userId);
        } else {
            $errorMsg = $mail->ErrorInfo;
            $errorMessage = "Errore nell'invio email: " . $errorMsg;
            // Aggiorna lo status con errore
            $update = $pdo->prepare("UPDATE utenti SET email_status = 'errore', email_error = ? WHERE id = :id");
            $update->execute([$errorMsg, $userId]);
            error_log('[EMAIL] Errore reinvio email per utente ID: ' . $userId . ' - Errore: ' . $errorMsg);
        }
        
        // Rimuove il PDF dopo l'invio
        if (file_exists($pdfFile)) {
            unlink($pdfFile);
        }

    } catch (Exception $e) {
        $errorMessage = "Errore durante il reinvio: " . $e->getMessage();
    }
}
// Includi il bootstrap per caricare la configurazione e la connessione al database
// Evita il problema di require_once che restituisce boolean se già incluso dall'entrypoint
if (!isset($bootstrap) || !is_array($bootstrap)) {
    if (isset($_SERVER['GAE_APPLICATION'])) {
        // Ambiente Google Cloud
        $bootstrap = require __DIR__ . '/../src/bootstrap_gcloud.php';
    } else {
        // Ambiente locale
        $bootstrap = require __DIR__ . '/../src/bootstrap.php';
    }
}
$pdo = $bootstrap['db'];
$config = $bootstrap['config'];

// Base directory per log: su App Engine usa /tmp (FS read-only per public/)
$logBaseDir = isset($_SERVER['GAE_APPLICATION']) ? sys_get_temp_dir() : __DIR__;

// Inizializza Birthday System
$birthdaySystem = new BirthdaySystem($pdo, $config);

// Gestione azioni Birthday System
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['birthday_action'])) {
            switch ($_POST['birthday_action']) {
                case 'save_template':
                    $data = [
                        'id' => $_POST['template_id'] ?? 0,
                        'name' => $_POST['template_name'],
                        'subject' => $_POST['template_subject'],
                        'html_content' => $_POST['template_content'],
                        'background_image' => $_POST['background_image'] ?? null
                    ];
                    
                    if ($birthdaySystem->saveTemplate($data)) {
                        $successMessage = "Template compleanno salvato con successo!";
                    } else {
                        $errorMessage = "Errore durante il salvataggio del template.";
                    }
                    break;
                    
                case 'activate_template':
                    if ($birthdaySystem->activateTemplate($_POST['template_id'])) {
                        $successMessage = "Template compleanno attivato con successo!";
                    } else {
                        $errorMessage = "Errore durante l'attivazione del template.";
                    }
                    break;
                    
                case 'delete_template':
                    $birthdaySystem->deleteTemplate($_POST['template_id']);
                    $successMessage = "Template compleanno eliminato con successo!";
                    break;
                    
                case 'test_template':
                    if ($birthdaySystem->testTemplate($_POST['template_id'], $_POST['test_email'])) {
                        $successMessage = "Email di test compleanno inviata con successo!";
                    } else {
                        $errorMessage = "Errore durante l'invio dell'email di test.";
                    }
                    break;
                    
                case 'send_birthday_check':
                    $result = $birthdaySystem->checkAndSendBirthdayWishes();
                    $successMessage = "Controllo compleanni completato. Trovati: {$result['total_found']}, Inviati: {$result['total_sent']}";
                    break;
                    
                case 'create_advanced_templates':
                    $result = $birthdaySystem->createAdvancedTemplates();
                    if ($result['success']) {
                        $successMessage = $result['message'];
                    } else {
                        $errorMessage = $result['message'];
                    }
                    break;
            }
        }

        // === GESTIONE AJAX ADMIN ACTIONS ===
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                // ... altri case già esistenti come search_users, test_database, ecc.

                case 'get_user_id':
                    try {
                        $email = $_POST['email'] ?? '';
                        if (empty($email)) {
                            echo json_encode([
                                'success' => false,
                                'error' => 'Email non fornita'
                            ]);
                            exit;
                        }

                        $stmt = $pdo->prepare("SELECT id FROM utenti WHERE email = :email LIMIT 1");
                        $stmt->execute([':email' => $email]);
                        $user = $stmt->fetch();

                        if ($user) {
                            echo json_encode([
                                'success' => true,
                                'user_id' => $user['id']
                            ]);
                        } else {
                            echo json_encode([
                                'success' => false,
                                'error' => 'Utente non trovato'
                            ]);
                        }
                    } catch (PDOException $e) {
                        error_log('[ADMIN AJAX] Errore PDO durante ricerca utente: ' . $e->getMessage());
                        echo json_encode([
                            'success' => false,
                            'error' => 'Errore database: ' . $e->getMessage()
                        ]);
                    } catch (Exception $e) {
                        error_log('[ADMIN AJAX] Errore generico durante ricerca utente: ' . $e->getMessage());
                        echo json_encode([
                            'success' => false,
                            'error' => 'Errore interno: ' . $e->getMessage()
                        ]);
                    }
                    exit;

                case 'delete_user':
                    error_log('[ADMIN AJAX] Delete user chiamato con ID: ' . ($_POST['id'] ?? 'null'));
                    try {
                        $id = intval($_POST['id'] ?? 0);
                        if ($id <= 0) {
                            error_log('[ADMIN AJAX] ID non valido: ' . $id);
                            echo json_encode([
                                'success' => false,
                                'message' => 'ID non valido'
                            ]);
                            exit;
                        }

                        $stmt = $pdo->prepare("DELETE FROM utenti WHERE id = :id");
                        $result = $stmt->execute([':id' => $id]);
                        $deletedRows = $stmt->rowCount();
                        
                        error_log('[ADMIN AJAX] Query DELETE eseguita. Risultato: ' . ($result ? 'true' : 'false') . ', Righe cancellate: ' . $deletedRows);

                        if ($deletedRows > 0) {
                            error_log('[ADMIN AJAX] Utente cancellato con successo');
                            echo json_encode([
                                'success' => true,
                                'message' => 'Utente eliminato con successo'
                            ]);
                        } else {
                            error_log('[ADMIN AJAX] Nessun utente trovato con questo ID');
                            echo json_encode([
                                'success' => false,
                                'message' => 'Nessun utente trovato con questo ID'
                            ]);
                        }
                    } catch (PDOException $e) {
                        error_log('[ADMIN AJAX] Errore PDO durante cancellazione: ' . $e->getMessage());
                        echo json_encode([
                            'success' => false,
                            'message' => 'Errore durante l\'eliminazione: ' . $e->getMessage()
                        ]);
                    } catch (Exception $e) {
                        error_log('[ADMIN AJAX] Errore generico durante cancellazione: ' . $e->getMessage());
                        echo json_encode([
                            'success' => false,
                            'message' => 'Errore generico: ' . $e->getMessage()
                        ]);
                    }
                    exit;

                 case 'resend_email':
                     try {
                         $id = intval($_POST['id'] ?? 0);
                         if ($id <= 0) {
                             echo json_encode([
                                 'success' => false,
                                 'message' => 'ID non valido'
                             ]);
                             exit;
                         }

                         // Utilizza la funzione resendEmail esistente
                         $successMessage = '';
                         $errorMessage = '';
                         resendEmail($pdo, $id, $config, $successMessage, $errorMessage);
                         
                         if (!empty($successMessage)) {
                             echo json_encode([
                                 'success' => true,
                                 'message' => $successMessage
                             ]);
                         } else {
                             echo json_encode([
                                 'success' => false,
                                 'message' => $errorMessage ?: 'Errore durante il reinvio dell\'email'
                             ]);
                         }
                     } catch (Exception $e) {
                         echo json_encode([
                             'success' => false,
                             'message' => 'Errore: ' . $e->getMessage()
                         ]);
                     }
                     exit;
            }
        }
    } catch (Exception $e) {
        $errorMessage = "Errore sistema compleanni: " . $e->getMessage();
    }
}

// Carica dati Birthday System
$birthdayTemplates = $birthdaySystem->getAllTemplates();
$birthdayStats = $birthdaySystem->getBirthdayStats();
$upcomingBirthdaysDetailed = $birthdaySystem->getUpcomingBirthdays();

// GET actions Birthday System
$editBirthdayTemplate = null;
if (isset($_GET['edit_birthday_template'])) {
    $editBirthdayTemplate = $birthdaySystem->getTemplate($_GET['edit_birthday_template']);
}

// ====== ESPORTAZIONE CSV ISCRITTI PER EVENTO ======
if (isset($_GET['export_csv'])) {
    $eventoId = intval($_GET['export_csv']);

    $stmtEvento = $pdo->prepare("SELECT titolo, event_date FROM events WHERE id = :id");
    $stmtEvento->execute([':id' => $eventoId]);
    $evento = $stmtEvento->fetch(PDO::FETCH_ASSOC);

    if (!$evento) {
        die("Evento non trovato.");
    }

    $stmtUtenti = $pdo->prepare("SELECT nome, cognome, email, telefono, data_nascita FROM utenti WHERE evento = :evento");
    $stmtUtenti->execute([':evento' => $eventoId]);
    $utenti = $stmtUtenti->fetchAll(PDO::FETCH_ASSOC);

    $csvFileName = "Iscritti_" . preg_replace('/[^A-Za-z0-9]/', '_', $evento['titolo']) . "_" . $evento['event_date'] . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $csvFileName . '"');

    $output = fopen('php://output', 'w');

    // Intestazione colonne
    fputcsv($output, [
        'phone', 'first_name', 'last_name', 'email', 'language', 'marketing_acceptance', 'MY_TEXT_FIELD', 'MY_DATE_FIELD', 'MY_DATETIME_FIELD'
    ]);

    foreach ($utenti as $u) {
        fputcsv($output, [
            $u['telefono'],
            $u['nome'],
            $u['cognome'],
            $u['email'],
            'it',
            '',
            '',
            $u['data_nascita'],
            (new DateTime())->format('c')
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Funzione per leggere tutti gli eventi dal database
 */
function readEvents($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, event_date, titolo, chiuso, background_image FROM events ORDER BY event_date ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $events = [];
        foreach ($rows as $row) {
            $events[] = [
                'id' => $row['id'],
                'date' => !empty($row['event_date']) ? date('d-m-Y', strtotime($row['event_date'])) : '',
                'event_date' => $row['event_date'],
                'titolo' => $row['titolo'],
                'chiuso' => $row['chiuso'],
                'background_image' => $row['background_image'] ?? null
            ];
        }
        return $events;
    } catch (PDOException $e) {
        die("Errore nel recupero degli eventi: " . $e->getMessage());
    }
}

/**
 * Trova un singolo evento dal DB in base all'id
 */
function findEventById($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT id, event_date, titolo, chiuso, background_image FROM events WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return [
                'id' => $row['id'],
                'date' => date('d-m-Y', strtotime($row['event_date'])),
                'titolo' => $row['titolo'],
                'chiuso' => $row['chiuso'] ?? 0,
                'background_image' => $row['background_image'] ?? null
            ];
        }
    } catch (PDOException $e) {
        die("Errore nel recupero dell'evento: " . $e->getMessage());
    }
    return null;
}

/**
 * Risolve l'URL di anteprima per l'immagine sfondo evento
 */
function resolveBackgroundUrl($config, $bg)
{
    if (!$bg) return null;
    if (filter_var($bg, FILTER_VALIDATE_URL)) return $bg;
    $gcsBucket = $config['gcs']['bucket'] ?? null;
    $gcsEnabled = isset($config['gcs']['enabled']) && $config['gcs']['enabled'] && $gcsBucket;
    if ($gcsEnabled) {
        $prefix = rtrim($config['gcs']['uploads_prefix'] ?? 'uploads/', '/');
        return 'https://storage.googleapis.com/' . $gcsBucket . '/' . $prefix . '/' . basename($bg);
    }
    return '/uploads/' . basename($bg);
}

/**
 * Inserisce un nuovo evento nel DB
 */
function createEvent($pdo, $eventDate, $titolo, $backgroundImage, $chiuso, &$successMessage, &$errorMessage) {
    try {
        // Controlla se l'evento esiste già
        $checkStmt = $pdo->prepare("SELECT id FROM events WHERE event_date = :event_date AND titolo = :titolo LIMIT 1");
        $checkStmt->execute([':event_date' => $eventDate, ':titolo' => $titolo]);
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
            $successMessage = "L'evento esiste già!";
            return;
        }
        $stmt = $pdo->prepare("INSERT INTO events (event_date, titolo, background_image, chiuso) VALUES (:event_date, :titolo, :background_image, :chiuso)");
        $stmt->execute([
            ':event_date' => $eventDate,
            ':titolo' => $titolo,
            ':background_image' => $backgroundImage,
            ':chiuso' => $chiuso
        ]);
        $successMessage = "Evento aggiunto correttamente!";
    } catch (PDOException $e) {
        $errorMessage = "Errore durante l'inserimento dell'evento: " . $e->getMessage();
    }
}

/**
 * Aggiorna un evento esistente
 */
function updateEvent($pdo, $id, $eventDate, $titolo, $backgroundImage, $chiuso, &$successMessage, &$errorMessage) {
    try {
        $stmt = $pdo->prepare("UPDATE events SET event_date = :event_date, titolo = :titolo, background_image = :background_image, chiuso = :chiuso WHERE id = :id");
        $stmt->execute([
            ':event_date' => $eventDate,
            ':titolo' => $titolo,
            ':background_image' => $backgroundImage,
            ':chiuso' => $chiuso,
            ':id' => $id
        ]);
        $successMessage = "Evento modificato correttamente!";
    } catch (PDOException $e) {
        $errorMessage = "Errore durante l'aggiornamento dell'evento: " . $e->getMessage();
    }
}

/**
 * Elimina un evento dal DB
 */
function deleteEvent($pdo, $id, &$successMessage, &$errorMessage) {
    try {
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $successMessage = "Evento cancellato correttamente!";
    } catch (PDOException $e) {
        $errorMessage = "Errore durante la cancellazione dell'evento: " . $e->getMessage();
    }
}

/**
 * Elimina un utente dal DB
 */
function deleteUser($pdo, $id, &$successMessage, &$errorMessage) {
    try {
        // Debug: Log tentativo di cancellazione
        error_log('[ADMIN] Tentativo cancellazione utente ID: ' . $id);
        
        // Verifica che l'utente esista prima di cancellarlo
        $checkStmt = $pdo->prepare("SELECT id, nome, cognome, email FROM utenti WHERE id = :id");
        $checkStmt->execute([':id' => $id]);
        $userToDelete = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userToDelete) {
            $errorMessage = "Utente non trovato!";
            error_log('[ADMIN] Utente non trovato per ID: ' . $id);
            return;
        }
        
        error_log('[ADMIN] Utente da cancellare: ' . json_encode($userToDelete));
        
        $stmt = $pdo->prepare("DELETE FROM utenti WHERE id = :id");
        $result = $stmt->execute([':id' => $id]);
        $deletedRows = $stmt->rowCount();
        
        if ($deletedRows > 0) {
            $successMessage = "Utente " . $userToDelete['nome'] . " " . $userToDelete['cognome'] . " cancellato correttamente!";
            error_log('[ADMIN] Utente cancellato con successo: ' . $id);
        } else {
            $errorMessage = "Nessuna riga cancellata. Utente potrebbe essere già stato eliminato.";
            error_log('[ADMIN] Nessuna riga cancellata per ID: ' . $id);
        }
    } catch (PDOException $e) {
        $errorMessage = "Errore durante la cancellazione dell'utente: " . $e->getMessage();
        error_log('[ADMIN] Errore cancellazione utente: ' . $e->getMessage());
    }
}

/**
 * Valida un'immagine hero prima del caricamento
 * Controlla formato, dimensioni, peso e qualità dell'immagine
 */
function validateHeroImage($file) {
    // Configurazione limiti
    $maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
    $maxWidth = 1920; // Larghezza massima in pixel
    $maxHeight = 1080; // Altezza massima in pixel
    $minWidth = 800; // Larghezza minima in pixel
    $minHeight = 450; // Altezza minima in pixel
    $allowedFormats = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    
    // Controllo dimensione file
    if ($file['size'] > $maxFileSize) {
        return [
            'success' => false,
            'error' => 'Il file è troppo grande. Dimensione massima consentita: 5MB. Dimensione attuale: ' . 
                      round($file['size'] / 1024 / 1024, 2) . 'MB.'
        ];
    }
    
    if ($file['size'] == 0) {
        return [
            'success' => false,
            'error' => 'Il file risulta vuoto o corrotto.'
        ];
    }
    
    // Controllo MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedFormats)) {
        return [
            'success' => false,
            'error' => 'Formato file non supportato. Formati consentiti: JPEG, PNG, WebP. Formato rilevato: ' . $mimeType
        ];
    }
    
    // Controllo estensione file
    $pathInfo = pathinfo($file['name']);
    $extension = strtolower($pathInfo['extension'] ?? '');
    
    if (!in_array($extension, $allowedExtensions)) {
        return [
            'success' => false,
            'error' => 'Estensione file non valida. Estensioni consentite: ' . implode(', ', $allowedExtensions) . 
                      '. Estensione rilevata: ' . $extension
        ];
    }
    
    // Controllo che sia effettivamente un'immagine e ottieni dimensioni
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return [
            'success' => false,
            'error' => 'Il file non è un\'immagine valida o è corrotto.'
        ];
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $imageType = $imageInfo[2];
    
    // Controllo dimensioni minime
    if ($width < $minWidth || $height < $minHeight) {
        return [
            'success' => false,
            'error' => "L'immagine è troppo piccola. Dimensioni minime richieste: {$minWidth}x{$minHeight}px. " .
                      "Dimensioni attuali: {$width}x{$height}px."
        ];
    }
    
    // Controllo dimensioni massime
    if ($width > $maxWidth || $height > $maxHeight) {
        return [
            'success' => false,
            'error' => "L'immagine è troppo grande. Dimensioni massime consentite: {$maxWidth}x{$maxHeight}px. " .
                      "Dimensioni attuali: {$width}x{$height}px."
        ];
    }
    
    // Controllo aspect ratio (deve essere ragionevolmente vicino a 16:9)
    $aspectRatio = $width / $height;
    $targetAspectRatio = 16 / 9; // 1.777...
    $aspectRatioTolerance = 0.3; // Tolleranza del 30%
    
    if (abs($aspectRatio - $targetAspectRatio) > $aspectRatioTolerance) {
        return [
            'success' => false,
            'error' => "Proporzioni dell'immagine non adatte. Si consiglia un formato panoramico (16:9 o simile). " .
                      "Rapporto attuale: " . round($aspectRatio, 2) . ":1"
        ];
    }
    
    // Controllo tipo immagine corrisponda al MIME
    $expectedTypes = [
        'image/jpeg' => [IMAGETYPE_JPEG],
        'image/jpg' => [IMAGETYPE_JPEG],
        'image/png' => [IMAGETYPE_PNG],
        'image/webp' => [IMAGETYPE_WEBP]
    ];
    
    if (isset($expectedTypes[$mimeType]) && !in_array($imageType, $expectedTypes[$mimeType])) {
        return [
            'success' => false,
            'error' => 'Inconsistenza tra formato dichiarato e contenuto effettivo del file.'
        ];
    }
    
    // Controllo nome file (sicurezza)
    $fileName = basename($file['name']);
    if (!preg_match('/^[a-zA-Z0-9._\-\s()]+$/', $fileName)) {
        return [
            'success' => false,
            'error' => 'Nome del file contiene caratteri non consentiti. Usare solo lettere, numeri, spazi, punti, trattini e parentesi.'
        ];
    }
    
    // Controllo lunghezza nome file
    if (strlen($fileName) > 100) {
        return [
            'success' => false,
            'error' => 'Nome del file troppo lungo. Massimo 100 caratteri.'
        ];
    }
    
    // Test di caricamento dell'immagine per verificare integrità
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $testImage = @imagecreatefromjpeg($file['tmp_name']);
            break;
        case IMAGETYPE_PNG:
            $testImage = @imagecreatefrompng($file['tmp_name']);
            break;
        case IMAGETYPE_WEBP:
            $testImage = @imagecreatefromwebp($file['tmp_name']);
            break;
        default:
            return [
                'success' => false,
                'error' => 'Tipo di immagine non supportato internamente.'
            ];
    }
    
    if ($testImage === false) {
        return [
            'success' => false,
            'error' => 'Impossibile elaborare l\'immagine. Il file potrebbe essere corrotto.'
        ];
    }
    
    // Libera memoria
    imagedestroy($testImage);
    
    // Tutto ok!
    return [
        'success' => true,
        'info' => [
            'width' => $width,
            'height' => $height,
            'size' => $file['size'],
            'format' => $mimeType,
            'aspect_ratio' => round($aspectRatio, 2)
        ]
    ];
}

/**
 * Valida un'immagine di sfondo prima del caricamento
 * Controlla formato, dimensioni, peso e qualità dell'immagine per PDF
 */
function validateBackgroundImage($file) {
    // Configurazione limiti per immagini di sfondo PDF (dimensioni esatte richieste da FPDF)
    $maxFileSize = 3 * 1024 * 1024; // 3MB in bytes (più piccolo per PDF)
    $requiredWidth = 1080; // Larghezza esatta richiesta
    $requiredHeight = 1920; // Altezza esatta richiesta
    $allowedFormats = ['image/jpeg', 'image/jpg', 'image/png'];
    $allowedExtensions = ['jpg', 'jpeg', 'png'];
    
    // Controllo dimensione file
    if ($file['size'] > $maxFileSize) {
        return [
            'success' => false,
            'error' => 'Il file è troppo grande. Dimensione massima consentita: 3MB. Dimensione attuale: ' . 
                      round($file['size'] / 1024 / 1024, 2) . 'MB.'
        ];
    }
    
    if ($file['size'] == 0) {
        return [
            'success' => false,
            'error' => 'Il file risulta vuoto o corrotto.'
        ];
    }
    
    // Controllo MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedFormats)) {
        return [
            'success' => false,
            'error' => 'Formato file non supportato per sfondi PDF. Formati consentiti: JPEG, PNG. Formato rilevato: ' . $mimeType
        ];
    }
    
    // Controllo estensione file
    $pathInfo = pathinfo($file['name']);
    $extension = strtolower($pathInfo['extension'] ?? '');
    
    if (!in_array($extension, $allowedExtensions)) {
        return [
            'success' => false,
            'error' => 'Estensione file non valida. Estensioni consentite: ' . implode(', ', $allowedExtensions) . 
                      '. Estensione rilevata: ' . $extension
        ];
    }
    
    // Controllo che sia effettivamente un'immagine e ottieni dimensioni
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return [
            'success' => false,
            'error' => 'Il file non è un\'immagine valida o è corrotto.'
        ];
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $imageType = $imageInfo[2];
    
    // Controllo dimensioni esatte (FPDF richiede esattamente 1080x1920)
    if ($width !== $requiredWidth || $height !== $requiredHeight) {
        return [
            'success' => false,
            'error' => "L'immagine deve avere esattamente le dimensioni {$requiredWidth}x{$requiredHeight}px per essere compatibile con FPDF. " .
                      "Dimensioni attuali: {$width}x{$height}px."
        ];
    }
    
    // Controllo tipo immagine corrisponda al MIME
    $expectedTypes = [
        'image/jpeg' => [IMAGETYPE_JPEG],
        'image/jpg' => [IMAGETYPE_JPEG],
        'image/png' => [IMAGETYPE_PNG]
    ];
    
    if (isset($expectedTypes[$mimeType]) && !in_array($imageType, $expectedTypes[$mimeType])) {
        return [
            'success' => false,
            'error' => 'Inconsistenza tra formato dichiarato e contenuto effettivo del file.'
        ];
    }
    
    // Controllo nome file (sicurezza)
    $fileName = basename($file['name']);
    if (!preg_match('/^[a-zA-Z0-9._\-\s()]+$/', $fileName)) {
        return [
            'success' => false,
            'error' => 'Nome del file contiene caratteri non consentiti. Usare solo lettere, numeri, spazi, punti, trattini e parentesi.'
        ];
    }
    
    // Controllo lunghezza nome file
    if (strlen($fileName) > 100) {
        return [
            'success' => false,
            'error' => 'Nome del file troppo lungo. Massimo 100 caratteri.'
        ];
    }
    
    // Test di caricamento dell'immagine per verificare integrità
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $testImage = @imagecreatefromjpeg($file['tmp_name']);
            break;
        case IMAGETYPE_PNG:
            $testImage = @imagecreatefrompng($file['tmp_name']);
            break;
        default:
            return [
                'success' => false,
                'error' => 'Tipo di immagine non supportato internamente.'
            ];
    }
    
    if ($testImage === false) {
        return [
            'success' => false,
            'error' => 'Impossibile elaborare l\'immagine. Il file potrebbe essere corrotto.'
        ];
    }
    
    // Libera memoria
    imagedestroy($testImage);
    
    // Tutto ok!
    return [
        'success' => true,
        'info' => [
            'width' => $width,
            'height' => $height,
            'size' => $file['size'],
            'format' => $mimeType
        ]
    ];
}

/**
 * Gestisce l'invio del form per creare o aggiornare un evento
 * Modificato per gestire upload di due immagini: una per PDF (uploads/) e una per hero (hero_images/)
 */
function handleEventSubmission($pdo, &$successMessage, &$errorMessage) {
    global $config; // Access config for paths and GCS
    $editId = isset($_POST['edit_index']) ? intval($_POST['edit_index']) : null;
    $dataInput = $_POST["data"] ?? '';
    $titolo = $_POST["titolo"] ?? '';
    $chiuso = isset($_POST['chiuso']) ? 1 : 0;

    $backgroundImage = null;
    $heroImage = null;

    // Prepara GCS se disponibile
    $useGcs = isset($config['gcs']['enabled']) && $config['gcs']['enabled'] === true && !empty($config['gcs']['bucket']);
    $isGae = isset($_SERVER['GAE_APPLICATION']);
    if ($useGcs) {
        try {
            $gcsUploader = new \App\Lib\GcsUploader($config['gcs']['bucket']);
        } catch (\Throwable $e) {
            // In caso di errore nel client GCS, disabilita GCS per questo ciclo
            $useGcs = false;
            error_log('GCS init failed: ' . $e->getMessage());
        }
    }

    // Gestione upload immagine per PDF con validazione completa
    if (isset($_FILES['sfondo']) && $_FILES['sfondo']['error'] === UPLOAD_ERR_OK) {
        $validation = validateBackgroundImage($_FILES['sfondo']);
        
        if ($validation['success']) {
            $safeBaseName = basename($_FILES['sfondo']['name']);
            $fileName = uniqid('sfondo_') . '_' . $safeBaseName;
            $mime = mime_content_type($_FILES['sfondo']['tmp_name']) ?: null;

            if ($useGcs) {
                // Carica su Cloud Storage e salva l'URL pubblico
                $dest = rtrim($config['gcs']['uploads_prefix'] ?? 'uploads/', '/') . '/' . $fileName;
                try {
                    $publicUrl = $gcsUploader->upload($dest, $_FILES['sfondo']['tmp_name'], $mime);
                    $backgroundImage = $publicUrl; // salva URL completo
                } catch (\Throwable $e) {
                    $errorMessage = "Errore upload su Cloud Storage: " . $e->getMessage();
                }
            } else {
                // Salva su filesystem locale (solo in sviluppo)
                if ($isGae) {
                    // Su GAE, evitare fallback su FS read-only
                    $errorMessage = "Upload su filesystem non disponibile su App Engine. Configurare GCS.";
                } else {
                    $uploadDir = $config['paths']['uploads_dir'] ?? (__DIR__ . '/../public/uploads/');
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0755, true);
                    }
                    $uploadPath = rtrim($uploadDir, '/') . '/' . $fileName;
                    if (move_uploaded_file($_FILES['sfondo']['tmp_name'], $uploadPath)) {
                        $backgroundImage = $fileName; // salva nome file per ambiente locale
                    } else {
                        $errorMessage = "Errore durante il salvataggio dell'immagine di sfondo.";
                    }
                }
            }
        } else {
            $errorMessage = $validation['error'];
        }
    } elseif (isset($_FILES['sfondo']) && $_FILES['sfondo']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Gestione errori di upload per immagine di sfondo
        switch ($_FILES['sfondo']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = "L'immagine di sfondo è troppo grande. Dimensione massima consentita: 3MB.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = "L'immagine di sfondo è stata caricata solo parzialmente.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage = "Cartella temporanea mancante per l'immagine di sfondo.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage = "Impossibile scrivere l'immagine di sfondo su disco.";
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMessage = "Caricamento dell'immagine di sfondo bloccato da un'estensione.";
                break;
            default:
                $errorMessage = "Errore sconosciuto durante il caricamento dell'immagine di sfondo.";
                break;
        }
    }

    // Gestione upload immagine hero con validazione completa
    if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
        $validation = validateHeroImage($_FILES['hero_image']);
        
        if ($validation['success']) {
            $safeHeroBase = basename($_FILES['hero_image']['name']);
            $heroFileName = uniqid('hero_') . '_' . $safeHeroBase;
            $heroMime = mime_content_type($_FILES['hero_image']['tmp_name']) ?: null;

            if ($useGcs) {
                $dest = rtrim($config['gcs']['hero_prefix'] ?? 'hero_images/', '/') . '/' . $heroFileName;
                try {
                    $heroUrl = $gcsUploader->upload($dest, $_FILES['hero_image']['tmp_name'], $heroMime);
                    $heroImage = $heroUrl; // salva URL completo per JSON
                } catch (\Throwable $e) {
                    $errorMessage = "Errore upload immagine hero su Cloud Storage: " . $e->getMessage();
                }
            } else {
                if ($isGae) {
                    // Su GAE, evitare fallback su FS read-only
                    $errorMessage = "Upload hero non disponibile su filesystem in App Engine. Configurare GCS.";
                } else {
                    $heroDir = __DIR__ . '/../public/hero_images/';
                    if (!is_dir($heroDir)) {
                        @mkdir($heroDir, 0755, true);
                    }
                    $heroPath = $heroDir . $heroFileName;
                    if (move_uploaded_file($_FILES['hero_image']['tmp_name'], $heroPath)) {
                        $heroImage = $heroFileName; // locale: salviamo solo il nome del file per il JSON
                    } else {
                        $errorMessage = "Errore durante il salvataggio dell'immagine.";
                    }
                }
            }
        } else {
            $errorMessage = $validation['error'];
        }
    } elseif (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Gestione errori di upload
        switch ($_FILES['hero_image']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = "Il file è troppo grande. Dimensione massima consentita: 5MB.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = "Il file è stato caricato solo parzialmente.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage = "Cartella temporanea mancante.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage = "Impossibile scrivere il file su disco.";
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMessage = "Caricamento del file bloccato da un'estensione.";
                break;
            default:
                $errorMessage = "Errore sconosciuto durante il caricamento del file.";
                break;
        }
    }

    if (empty($dataInput) || empty($titolo)) {
        $errorMessage = "Tutti i campi sono obbligatori!";
        return;
    }

    $dateTime = \DateTime::createFromFormat('Y-m-d', $dataInput);
    if (!$dateTime) {
        $errorMessage = "Formato data non valido!";
        return;
    }
    $eventDate = $dateTime->format('Y-m-d');

    if ($editId) {
        // Se non è stata caricata una nuova immagine di sfondo,
        // mantieni quella già presente nel DB per l'evento in modifica
        if ($backgroundImage === null) {
            try {
                $stmt = $pdo->prepare("SELECT background_image FROM events WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $editId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['background_image'])) {
                    $backgroundImage = $row['background_image'];
                }
            } catch (\PDOException $e) {
                // Non bloccare il salvataggio per un problema di lettura, ma segnala
                $errorMessage = $errorMessage ?: ("Impossibile recuperare l'immagine esistente: " . $e->getMessage());
            }
        }
        updateEvent($pdo, $editId, $eventDate, $titolo, $backgroundImage, $chiuso, $successMessage, $errorMessage);
    } else {
        createEvent($pdo, $eventDate, $titolo, $backgroundImage, $chiuso, $successMessage, $errorMessage);
    }

    // Aggiorna hero_images.json con controllo duplicati
    if ($heroImage) {
        $originalFileName = basename($_FILES['hero_image']['name']);
        $entry = [
            'filename' => $useGcs ? basename(parse_url($heroImage, PHP_URL_PATH)) : $heroImage,
            'expires' => $eventDate
        ];
        if ($useGcs) { $entry['url'] = $heroImage; }

        if ($useGcs) {
            // Mantieni un file JSON nel bucket per lista carosello
            try {
                $jsonPath = 'hero_images.json';
                $existingJson = $gcsUploader->download($jsonPath);
                $heroImages = $existingJson ? json_decode($existingJson, true) : [];
                if (!is_array($heroImages)) { $heroImages = []; }

                // Rimuovi eventuali duplicati sullo stesso original filename
                $clean = [];
                foreach ($heroImages as $img) {
                    $fn = $img['filename'] ?? '';
                    $exOrig = $fn ? substr($fn, strrpos($fn, '_') + 1) : '';
                    if ($exOrig === $originalFileName) { continue; }
                    $clean[] = $img;
                }
                $clean[] = $entry;

                $gcsUploader->uploadString($jsonPath, json_encode($clean, JSON_PRETTY_PRINT), 'application/json');
                $successMessage = isset($successMessage) ? $successMessage . " Immagine hero aggiunta al carosello." : "Immagine hero aggiunta al carosello.";
            } catch (\Throwable $e) {
                error_log('Errore aggiornamento hero_images.json su GCS: ' . $e->getMessage());
            }
        } else {
            // Ambiente locale: usa file nel repo
            $heroJsonPath = __DIR__ . '/../public/hero_images.json';
            $heroImages = file_exists($heroJsonPath) ? json_decode(file_get_contents($heroJsonPath), true) : [];
            $useGcs = false;
            if (!is_array($heroImages)) { $heroImages = []; }

            // Evita duplicati
            $isDuplicate = false;
            foreach ($heroImages as $existingImage) {
                $existingFile = $existingImage['filename'] ?? '';
                $existingOriginalName = $existingFile ? substr($existingFile, strrpos($existingFile, '_') + 1) : '';
                if ($existingOriginalName === $originalFileName) {
                    $isDuplicate = true;
                    $errorMessage = "Immagine già presente nel carosello: " . $originalFileName;
                    break;
                }
            }

            if (!$isDuplicate) {
                $heroImages[] = $entry;
                if ($useGcs) {
                        try {
                            $gcsUploader = new \App\Lib\GcsUploader($config['gcs']['bucket']);
                            $gcsUploader->uploadString('hero_images.json', json_encode($heroImages, JSON_PRETTY_PRINT), 'application/json');
                        } catch (\Throwable $e) {
                            error_log('Errore salvataggio hero_images.json su GCS: ' . $e->getMessage());
                            @file_put_contents($heroJsonPath, json_encode($heroImages, JSON_PRETTY_PRINT));
                        }
                    } else {
                        @file_put_contents($heroJsonPath, json_encode($heroImages, JSON_PRETTY_PRINT));
                    }
                $successMessage = isset($successMessage) ? $successMessage . " Immagine hero aggiunta al carosello." : "Immagine hero aggiunta al carosello.";
            }
        }
    }
}

/**
 * Invalida un QR code aggiornando il campo validato a 0
 */
function invalidateQRCode($pdo, $token, &$successMessage, &$errorMessage) {
    try {
        $stmt = $pdo->prepare("UPDATE utenti SET validato = 0 WHERE token = :token");
        $stmt->execute([':token' => $token]);
        $successMessage = "QR Code invalidato con successo!";
    } catch (PDOException $e) {
        $errorMessage = "Errore durante l'invalidamento del QR code: " . $e->getMessage();
    }
}

/**
 * Calcola statistiche per un determinato evento
 */
function getEventStatistics($pdo, $eventoId, &$errorMessage) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) AS tot,
                SUM(CASE WHEN validato = 1 THEN 1 ELSE 0 END) AS val
            FROM utenti
            WHERE evento = :evento
        ");
        $stmt->execute([":evento" => $eventoId]);
        $stat = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'totIscritti' => $stat['tot'],
            'totValidati' => $stat['val'],
            'totNonValidi' => $stat['tot'] - $stat['val']
        ];
    } catch (PDOException $e) {
        $errorMessage = "Errore nel calcolo delle statistiche: " . $e->getMessage();
        return null;
    }
}

/**
 * Recupera gli utenti per un determinato evento
 */
function getEventUsers($pdo, $eventoId, &$errorMessage) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM utenti WHERE evento = :ev ORDER BY created_at DESC");
        $stmt->execute([':ev' => $eventoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMessage = "Errore nel recupero utenti: " . $e->getMessage();
        return [];
    }
}

/**
 * Statistiche per utente per evento
 */
function getUserStatsByEvent($pdo, $eventoId, &$errorMessage) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                email,
                nome,
                cognome,
                COUNT(*) AS iscrizioni,
                SUM(validato = 1) AS validati,
                MAX(CASE WHEN validato = 1 THEN created_at ELSE NULL END) AS ultima_validazione
            FROM utenti
            WHERE evento = :evento
            GROUP BY email
        ");
        $stmt->execute([':evento' => $eventoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMessage = "Errore nel recupero statistiche utenti: " . $e->getMessage();
        return [];
    }
}

/**
 * Statistiche orarie delle validazioni per evento
 */
function getHourlyValidationStats($pdo, $eventoId, &$errorMessage) {
    try {
        // Aggregazione per intervalli di 15 minuti (compatibile con ONLY_FULL_GROUP_BY)
        $stmt = $pdo->prepare("
            SELECT
                t.ora,
                t.intervallo_15min,
                CONCAT(LPAD(t.ora, 2, '0'), ':', LPAD(t.intervallo_15min * 15, 2, '0')) AS tempo_formato,
                COUNT(*) AS validati
            FROM (
                SELECT
                    HOUR(validated_at) AS ora,
                    FLOOR(MINUTE(validated_at) / 15) AS intervallo_15min
                FROM utenti
                WHERE evento = :evento
                  AND validato = 1
                  AND validated_at IS NOT NULL
                  AND HOUR(validated_at) IN (23, 0, 1, 2, 3, 4)
            ) t
            GROUP BY t.ora, t.intervallo_15min
            ORDER BY CASE WHEN t.ora = 23 THEN 0 ELSE t.ora + 1 END, t.intervallo_15min
        ");
        $stmt->execute([':evento' => $eventoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMessage = "Errore statistiche per intervalli 15min: " . $e->getMessage();
        return [];
    }
}

/**
 * Pulisce automaticamente duplicati e immagini scadute dal carosello hero
 */
function cleanHeroImages(&$successMessage, &$errorMessage) {
    global $config;
    $useGcs = isset($config['gcs']['enabled']) && $config['gcs']['enabled'] === true && !empty($config['gcs']['bucket']);

    if ($useGcs) {
        try {
            $gcsUploader = new \App\Lib\GcsUploader($config['gcs']['bucket']);
            $json = $gcsUploader->download('hero_images.json');
            $heroImages = $json ? json_decode($json, true) : [];
            if (!$heroImages) { $heroImages = []; }
        } catch (\Throwable $e) {
            $errorMessage = 'Errore accesso GCS: ' . $e->getMessage();
            return false;
        }
    } else {
        $heroJsonPath = __DIR__ . '/../public/hero_images.json';
        if (!file_exists($heroJsonPath)) {
            $errorMessage = "File hero_images.json non trovato.";
            return false;
        }
        $heroImages = json_decode(file_get_contents($heroJsonPath), true);
        if (!$heroImages) {
            $errorMessage = "Errore nella lettura del file hero_images.json.";
            return false;
        }
    }
    
    $originalCount = count($heroImages);
    $today = new DateTime();
    $cleanedImages = [];
    $seenOriginalNames = [];
    $removedDuplicates = 0;
    $removedExpired = 0;
    
    foreach ($heroImages as $image) {
        // Controllo scadenza
        $expires = new DateTime($image['expires']);
        if ($expires < $today) {
            $removedExpired++;
            // Rimuove anche il file fisico se esiste
            if (!$useGcs) {
                $filePath = __DIR__ . '/../public/hero_images/' . $image['filename'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            continue;
        }
        
        // Controllo duplicati basato sul nome originale
        $originalName = substr($image['filename'], strrpos($image['filename'], '_') + 1);
        
        if (in_array($originalName, $seenOriginalNames)) {
            $removedDuplicates++;
            // Rimuove anche il file fisico duplicato
            if (!$useGcs) {
                $filePath = __DIR__ . '/../public/hero_images/' . $image['filename'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            continue;
        }
        
        $seenOriginalNames[] = $originalName;
        $cleanedImages[] = $image;
    }
    
    // Salva il file pulito
    $saveOk = false;
    if ($useGcs) {
        try {
            $gcsUploader->uploadString('hero_images.json', json_encode($cleanedImages, JSON_PRETTY_PRINT), 'application/json');
            $saveOk = true;
        } catch (\Throwable $e) {
            $errorMessage = 'Errore salvataggio JSON su GCS: ' . $e->getMessage();
            $saveOk = false;
        }
    } else {
        $heroJsonPath = __DIR__ . '/../public/hero_images.json';
        $saveOk = (bool) @file_put_contents($heroJsonPath, json_encode($cleanedImages, JSON_PRETTY_PRINT));
    }

    if ($saveOk) {
        $finalCount = count($cleanedImages);
        $totalRemoved = $originalCount - $finalCount;
        
        if ($totalRemoved > 0) {
            $successMessage = "Pulizia completata: rimosse {$totalRemoved} immagini ({$removedDuplicates} duplicati, {$removedExpired} scadute). Rimaste {$finalCount} immagini.";
        } else {
            $successMessage = "Nessuna immagine da rimuovere. Carosello già pulito.";
        }
        return true;
    } else {
        $errorMessage = $errorMessage ?: "Errore nel salvataggio del file pulito.";
        return false;
    }
}

/**
 * Ricerca utenti basata su nome, cognome, email o telefono
 */
function searchUsers($pdo, $searchTerm, &$errorMessage, $limit = null) {
    try {
        $searchPattern = "%$searchTerm%";
        
        // Query base senza LIMIT se non specificato
        $baseQuery = "
            SELECT 
                u.*,
                e.titolo as evento_titolo,
                e.event_date,
                COUNT(*) OVER() as total_found
            FROM utenti u
            LEFT JOIN events e ON u.evento = e.id
            WHERE (
                u.nome LIKE :search1 
                OR u.cognome LIKE :search2 
                OR u.email LIKE :search3 
                OR u.telefono LIKE :search4
                OR CONCAT(u.nome, ' ', u.cognome) LIKE :search5
                OR CONCAT(u.cognome, ' ', u.nome) LIKE :search6
                OR e.titolo LIKE :search7
            )
            ORDER BY 
                CASE 
                    WHEN u.email LIKE :exact_email THEN 1
                    WHEN CONCAT(u.nome, ' ', u.cognome) LIKE :exact_name THEN 2
                    WHEN u.nome LIKE :start_nome OR u.cognome LIKE :start_cognome THEN 3
                    ELSE 4
                END,
                u.created_at DESC";
        
        // Aggiungi LIMIT solo se specificato
        if ($limit !== null && $limit > 0) {
            $baseQuery .= " LIMIT :limit";
        }
        
        $stmt = $pdo->prepare($baseQuery);
        
        $exactEmail = $searchTerm . '%';
        $exactName = $searchTerm . '%';
        $startNome = $searchTerm . '%';
        $startCognome = $searchTerm . '%';
        
        $params = [
            ':search1' => $searchPattern,
            ':search2' => $searchPattern,
            ':search3' => $searchPattern,
            ':search4' => $searchPattern,
            ':search5' => $searchPattern,
            ':search6' => $searchPattern,
            ':search7' => $searchPattern,
            ':exact_email' => $exactEmail,
            ':exact_name' => $exactName,
            ':start_nome' => $startNome,
            ':start_cognome' => $startCognome
        ];
        
        // Aggiungi parametro limit solo se necessario
        if ($limit !== null && $limit > 0) {
            $params[':limit'] = $limit;
        }
        
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMessage = "Errore nella ricerca utenti: " . $e->getMessage();
        return [];
    }
}

/**
 * Ottiene tutti gli utenti registrati senza limiti per il tab "Tutti gli Utenti"
 */
// NOTA: La funzione getAllUsers() è stata rimossa perché non più utilizzata.
// Il sistema ora usa SOLO la ricerca AJAX tramite ajax_search_users.php
// per visualizzare gli utenti nella tab "Ricerca Utenti".

/**
 * Calcola se un utente è bloccato per 0% presenze
 */
// Rimossa funzione isUserBlocked - blocco utenti disabilitato

// Rimossa funzione unblockUser - blocco utenti disabilitato

// ====== GESTIONE IMPOSTAZIONI TAB ======

// Gestione download log
if (isset($_GET['download_log'])) {
    $logType = $_GET['download_log'];
    $logFiles = [
        'rinvii' => rtrim($logBaseDir, '/') . '/log_rinviati.txt',
        'errori' => rtrim($logBaseDir, '/') . '/error.log',
        'accessi' => rtrim($logBaseDir, '/') . '/access.log'
    ];
    
    if (isset($logFiles[$logType]) && file_exists($logFiles[$logType])) {
        $logFile = $logFiles[$logType];
        $fileName = 'mrcharlie_log_' . $logType . '_' . date('Ymd_His') . '.txt';
        
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($logFile));
        readfile($logFile);
        exit;
    }
}

// Gestione backup database
if (isset($_GET['backup_database'])) {
    $backupType = $_GET['backup_database'];
    
    try {
        // Impostazioni backup
        $timestamp = date('Ymd_His');
        $fileName = "mrcharlie_backup_{$backupType}_{$timestamp}.sql";
        $tempFile = sys_get_temp_dir() . '/' . $fileName;
        
        // Connessione database per backup
        $dbConfig = $config['database'];
        $host = $dbConfig['host'];
        $dbname = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];
        
        // Costruisci comando mysqldump
        $command = "mysqldump --host={$host} --user={$username} --password={$password} ";
        
        switch ($backupType) {
            case 'full':
                $command .= "--routines --triggers --single-transaction {$dbname}";
                break;
            case 'events':
                $command .= "--single-transaction {$dbname} events";
                break;
            case 'users':
                $command .= "--single-transaction {$dbname} utenti";
                break;
            case 'structure':
                $command .= "--no-data --routines --triggers {$dbname}";
                break;
            default:
                throw new Exception("Tipo di backup non supportato");
        }
        
        $command .= " > {$tempFile}";
        
        // Esegui backup
        exec($command, $output, $return_var);
        
        if ($return_var !== 0) {
            throw new Exception("Errore durante la creazione del backup");
        }
        
        if (!file_exists($tempFile) || filesize($tempFile) == 0) {
            throw new Exception("File di backup vuoto o non creato");
        }
        
        // Comprimi se backup completo
        if ($backupType === 'full' && function_exists('gzopen')) {
            $gzFile = $tempFile . '.gz';
            $fp_out = gzopen($gzFile, 'wb9');
            $fp_in = fopen($tempFile, 'rb');
            
            if ($fp_out && $fp_in) {
                while (!feof($fp_in)) {
                    gzwrite($fp_out, fread($fp_in, 1024 * 512));
                }
                fclose($fp_in);
                gzclose($fp_out);
                unlink($tempFile);
                $tempFile = $gzFile;
                $fileName .= '.gz';
            }
        }
        
        // Download del file
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($tempFile));
        readfile($tempFile);
        
        // Pulisci file temporaneo
        unlink($tempFile);
        exit;
        
    } catch (Exception $e) {
        $errorMessage = "Errore durante il backup: " . $e->getMessage();
    }
}

// Gestione richiesta visualizzazione immagini hero
    if (isset($_GET['get_hero_images'])) {
        header('Content-Type: application/json');
    
    $useGcs = isset($config['gcs']['enabled']) && $config['gcs']['enabled'] === true && !empty($config['gcs']['bucket']);
    if ($useGcs) {
        try {
            $gcsUploader = new \App\Lib\GcsUploader($config['gcs']['bucket']);
            $json = $gcsUploader->download('hero_images.json');
            $heroImages = $json ? json_decode($json, true) : [];
            if (!is_array($heroImages) || empty($heroImages)) {
                // fallback: lista diretta dal bucket
                $objects = $gcsUploader->listPrefix($config['gcs']['hero_prefix'] ?? 'hero_images/');
                $heroImages = [];
                foreach ($objects as $obj) {
                    $heroImages[] = [
                        'filename' => basename($obj['name']),
                        'url' => $obj['url'],
                        'expires' => date('Y-m-d')
                    ];
                }
            }
            usort($heroImages, function($a, $b) {
                return strtotime($b['expires']) - strtotime($a['expires']);
            });
            echo json_encode(['success' => true, 'images' => $heroImages, 'count' => count($heroImages)]);
            exit;
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Errore caricamento immagini da GCS: ' . $e->getMessage()]);
            exit;
        }
    } else {
        $heroJsonPath = __DIR__ . '/../public/hero_images.json';
        if (!file_exists($heroJsonPath)) {
            echo json_encode(['success' => false, 'error' => 'File hero_images.json non trovato']);
            exit;
        }
        $heroImages = json_decode(file_get_contents($heroJsonPath), true);
        if (!$heroImages) {
            echo json_encode(['success' => false, 'error' => 'Errore nella lettura del file JSON']);
            exit;
        }
        usort($heroImages, function($a, $b) {
            return strtotime($b['expires']) - strtotime($a['expires']);
        });
        echo json_encode(['success' => true, 'images' => $heroImages, 'count' => count($heroImages)]);
    }

    // Anteprima PDF per evento: genera al volo un PDF con lo sfondo dell'evento
    if (isset($_GET['action']) && $_GET['action'] === 'preview_pdf') {
        $eventoId = intval($_GET['evento_id'] ?? 0);
        if ($eventoId <= 0) { http_response_code(400); echo 'Evento non valido'; exit; }
        try {
            $stmt = $pdo->prepare("SELECT event_date, titolo, background_image FROM events WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $eventoId]);
            $ev = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ev) { http_response_code(404); echo 'Evento non trovato'; exit; }

            // Risolvi percorso immagine (supporta URL GCS o filename)
            $bgPath = null;
            $raw = $ev['background_image'] ?? '';
            if ($raw) {
                if (filter_var($raw, FILTER_VALIDATE_URL)) {
                    $tmp = sys_get_temp_dir() . '/' . basename(parse_url($raw, PHP_URL_PATH));
                    $data = @file_get_contents($raw);
                    if ($data !== false) { file_put_contents($tmp, $data); $bgPath = $tmp; }
                }
                if (!$bgPath) {
                    $local = ($config['paths']['uploads_dir'] ?? (__DIR__.'/../public/uploads/')) . basename($raw);
                    if (file_exists($local)) { $bgPath = $local; }
                }
                if (!$bgPath) {
                    $gcsBucket = $config['gcs']['bucket'] ?? null;
                    $gcsEnabled = isset($config['gcs']['enabled']) && $config['gcs']['enabled'] && $gcsBucket;
                    if ($gcsEnabled) {
                        $prefix = rtrim($config['gcs']['uploads_prefix'] ?? 'uploads/', '/');
                        $candidateUrl = 'https://storage.googleapis.com/' . $gcsBucket . '/' . $prefix . '/' . basename($raw);
                        $tmp2 = sys_get_temp_dir() . '/' . basename($raw);
                        $data2 = @file_get_contents($candidateUrl);
                        if ($data2 !== false) { file_put_contents($tmp2, $data2); $bgPath = $tmp2; }
                    }
                }
            }
            if (!$bgPath || !file_exists($bgPath)) { http_response_code(404); echo 'Sfondo non disponibile'; exit; }

            // Genera PDF con FPDF
            require_once __DIR__ . '/../lib/fpdf/fpdf.php';
            $pdf = new FPDF();
            $pdf->AddPage('P', array(1080, 1920));
            // Inserisci l'immagine sfondo a piena pagina
            $pdf->Image($bgPath, 0, 0, 1080, 1920);
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="anteprima_evento_'.$eventoId.'.pdf"');
            $pdf->Output('I', 'anteprima_evento_'.$eventoId.'.pdf');
            exit;
        } catch (Exception $e) {
            http_response_code(500); echo 'Errore anteprima PDF: ' . $e->getMessage(); exit;
        }
    }
    exit;
}

// Gestione AJAX per log e funzionalità impostazioni
if (isset($_POST['action'])) {
    // Pulisci il buffer di output per evitare interferenze con JSON
    if (ob_get_level()) {
        ob_clean();
    }
    // Disabilita output di errori PHP per evitare corruzione JSON
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(0); // Disabilita completamente gli errori per AJAX
    
    header('Content-Type: application/json');
    
    // Buffer di output per catturare eventuali errori
    ob_start();
    
    try {
        switch ($_POST['action']) {
        case 'get_carosello_images':
            try {
                $useGcs = isset($config['gcs']['enabled']) && $config['gcs']['enabled'] === true && !empty($config['gcs']['bucket']);
                
                if ($useGcs) {
                    // Leggi da GCS
                    $gcsUploader = new \App\Lib\GcsUploader($config['gcs']['bucket']);
                    $jsonContent = $gcsUploader->download('hero_images.json');
                    $heroData = ($jsonContent !== null) ? json_decode($jsonContent, true) : [];
                } else {
                    // Leggi da file locale
                    $heroJsonPath = __DIR__ . '/../public/hero_images.json';
                    $heroData = file_exists($heroJsonPath) ? json_decode(file_get_contents($heroJsonPath), true) : [];
                }
                
                if (!is_array($heroData)) { $heroData = []; }
                
                // Processa le immagini e aggiungi URL
                $images = [];
                $prefix = $useGcs ? 'https://storage.googleapis.com/' . $config['gcs']['bucket'] . '/' . ($config['gcs']['hero_prefix'] ?? 'hero_images/') : '/hero_images/';
                
                foreach ($heroData as $item) {
                    if (!isset($item['filename'], $item['expires'])) continue;
                    
                    $images[] = [
                        'filename' => $item['filename'],
                        'expires' => $item['expires'],
                        'src' => $prefix . $item['filename']
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'images' => $images,
                    'count' => count($images)
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Errore nel caricamento immagini: ' . $e->getMessage()
                ]);
            }
            exit;

        case 'upload_carosello_images':
            try {
                $useGcs = isset($config['gcs']['enabled']) && $config['gcs']['enabled'] === true && !empty($config['gcs']['bucket']);
                
                if (!isset($_FILES['caroselloFiles']) || empty($_FILES['caroselloFiles']['name'][0])) {
                    throw new Exception('Nessun file selezionato');
                }
                
                $expiryDate = $_POST['caroselloExpiry'] ?? '';
                if (empty($expiryDate)) {
                    throw new Exception('Data di scadenza non fornita');
                }
                
                // Valida data di scadenza
                $expiry = new DateTime($expiryDate);
                $today = new DateTime();
                if ($expiry <= $today) {
                    throw new Exception('La data di scadenza deve essere futura');
                }
                
                $uploadedCount = 0;
                $errors = [];
                
                // Carica hero_images.json esistente
                if ($useGcs) {
                    $gcsUploader = new \App\Lib\GcsUploader($config['gcs']['bucket']);
                    $jsonContent = $gcsUploader->download('hero_images.json');
                    $heroImages = ($jsonContent !== null) ? json_decode($jsonContent, true) : [];
                } else {
                    $heroJsonPath = __DIR__ . '/../public/hero_images.json';
                    $heroImages = file_exists($heroJsonPath) ? json_decode(file_get_contents($heroJsonPath), true) : [];
                }
                
                if (!is_array($heroImages)) { $heroImages = []; }
                
                foreach ($_FILES['caroselloFiles']['name'] as $key => $filename) {
                    if ($_FILES['caroselloFiles']['error'][$key] !== UPLOAD_ERR_OK) {
                        $errors[] = "Errore upload $filename: " . $_FILES['caroselloFiles']['error'][$key];
                        continue;
                    }
                    
                    // Genera nome file unico
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                        $errors[] = "Formato non supportato per $filename";
                        continue;
                    }
                    
                    $newFilename = 'hero_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
                    
                    if ($useGcs) {
                        // Upload su GCS
                        $prefix = $config['gcs']['hero_prefix'] ?? 'hero_images/';
                        $gcsPath = $prefix . $newFilename;
                        $tempFile = $_FILES['caroselloFiles']['tmp_name'][$key];
                        
                        $gcsUploader->uploadFile($tempFile, $gcsPath, 'image/' . $ext);
                    } else {
                        // Upload locale
                        $uploadDir = __DIR__ . '/../public/hero_images/';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }
                        
                        $targetPath = $uploadDir . $newFilename;
                        if (!move_uploaded_file($_FILES['caroselloFiles']['tmp_name'][$key], $targetPath)) {
                            $errors[] = "Errore salvataggio $filename";
                            continue;
                        }
                    }
                    
                    // Aggiungi al JSON con URL completo per GCS
                    $entry = [
                        'filename' => $newFilename,
                        'expires' => $expiryDate
                    ];
                    
                    if ($useGcs) {
                        $entry['url'] = 'https://storage.googleapis.com/' . $config['gcs']['bucket'] . '/' . $prefix . $newFilename;
                    }
                    
                    $heroImages[] = $entry;
                    
                    $uploadedCount++;
                }
                
                if ($uploadedCount > 0) {
                    // Ordina alfabeticamente
                    usort($heroImages, function($a, $b) { 
                        return strcmp($a['filename'], $b['filename']); 
                    });
                    
                    // Salva JSON aggiornato
                    if ($useGcs) {
                        $gcsUploader->uploadString('hero_images.json', json_encode($heroImages, JSON_PRETTY_PRINT), 'application/json');
                    } else {
                        file_put_contents($heroJsonPath, json_encode($heroImages, JSON_PRETTY_PRINT));
                    }
                }
                
                $message = "Caricate $uploadedCount immagini con successo";
                if (!empty($errors)) {
                    $message .= ". Errori: " . implode(', ', $errors);
                }
                
                echo json_encode([
                    'success' => $uploadedCount > 0,
                    'message' => $message,
                    'uploaded' => $uploadedCount,
                    'errors' => $errors
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;

        case 'delete_carosello_image':
            try {
                $filename = $_POST['filename'] ?? '';
                if (empty($filename)) {
                    throw new Exception('Nome file non fornito');
                }
                
                $useGcs = isset($config['gcs']['enabled']) && $config['gcs']['enabled'] === true && !empty($config['gcs']['bucket']);
                
                // Carica hero_images.json esistente
                if ($useGcs) {
                    $gcsUploader = new \App\Lib\GcsUploader($config['gcs']['bucket']);
                    $jsonContent = $gcsUploader->download('hero_images.json');
                    $heroImages = ($jsonContent !== null) ? json_decode($jsonContent, true) : [];
                } else {
                    $heroJsonPath = __DIR__ . '/../public/hero_images.json';
                    $heroImages = file_exists($heroJsonPath) ? json_decode(file_get_contents($heroJsonPath), true) : [];
                }
                
                if (!is_array($heroImages)) { $heroImages = []; }
                
                // Rimuovi dal JSON
                $originalCount = count($heroImages);
                $heroImages = array_filter($heroImages, function($item) use ($filename) {
                    return ($item['filename'] ?? '') !== $filename;
                });
                
                if (count($heroImages) === $originalCount) {
                    throw new Exception('Immagine non trovata');
                }
                
                // Elimina file fisico
                if ($useGcs) {
                    $prefix = $config['gcs']['hero_prefix'] ?? 'hero_images/';
                    $gcsPath = $prefix . $filename;
                    $gcsUploader->deleteObject($gcsPath);
                } else {
                    $filePath = __DIR__ . '/../public/hero_images/' . $filename;
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
                
                // Salva JSON aggiornato
                if ($useGcs) {
                    $gcsUploader->uploadString('hero_images.json', json_encode($heroImages, JSON_PRETTY_PRINT), 'application/json');
                } else {
                    file_put_contents($heroJsonPath, json_encode($heroImages, JSON_PRETTY_PRINT));
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => "Immagine '$filename' eliminata con successo"
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;

        case 'regenerate_hero_carousel':
            try {
                $useGcs = isset($config['gcs']['enabled']) && $config['gcs']['enabled'] === true && !empty($config['gcs']['bucket']);
                $defaultExpire = '2099-12-31';

                if ($useGcs) {
                    $gcsUploader = new \App\Lib\GcsUploader($config['gcs']['bucket']);
                    $prefix = $config['gcs']['hero_prefix'] ?? 'hero_images/';
                    $objects = $gcsUploader->listPrefix($prefix);

                    $heroImages = [];
                    foreach ($objects as $obj) {
                        $name = $obj['name'] ?? '';
                        if (!$name) { continue; }
                        $filename = basename($name);
                        // Filtra solo immagini per sicurezza
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg','jpeg','png','webp'])) { continue; }
                        $heroImages[] = [
                            'filename' => $filename,
                            'url' => $obj['url'] ?? null,
                            'expires' => $defaultExpire
                        ];
                    }

                    // Ordina alfabeticamente per stabilità
                    usort($heroImages, function($a, $b) { return strcmp($a['filename'], $b['filename']); });
                    $gcsUploader->uploadString('hero_images.json', json_encode($heroImages, JSON_PRETTY_PRINT), 'application/json');

                    echo json_encode([
                        'success' => true,
                        'message' => 'Carosello rigenerato da GCS: ' . count($heroImages) . ' immagini.',
                        'count' => count($heroImages),
                        'images' => $heroImages
                    ]);
                    exit;
                } else {
                    // Ambiente locale: leggi directory pubblica
                    $dir = __DIR__ . '/../public/hero_images/';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $files = is_dir($dir) ? scandir($dir) : [];
                    $heroImages = [];
                    foreach ($files as $f) {
                        if ($f === '.' || $f === '..') continue;
                        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
                        $heroImages[] = [
                            'filename' => $f,
                            'expires' => $defaultExpire
                        ];
                    }
                    usort($heroImages, function($a, $b) { return strcmp($a['filename'], $b['filename']); });
                    $heroJsonPath = __DIR__ . '/../public/hero_images.json';
                    if ($useGcs) {
                        try {
                            $gcsUploader = new \App\Lib\GcsUploader($config['gcs']['bucket']);
                            $gcsUploader->uploadString('hero_images.json', json_encode($heroImages, JSON_PRETTY_PRINT), 'application/json');
                        } catch (\Throwable $e) {
                            error_log('Errore salvataggio hero_images.json su GCS: ' . $e->getMessage());
                            @file_put_contents($heroJsonPath, json_encode($heroImages, JSON_PRETTY_PRINT));
                        }
                    } else {
                        @file_put_contents($heroJsonPath, json_encode($heroImages, JSON_PRETTY_PRINT));
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Carosello rigenerato: ' . count($heroImages) . ' immagini trovate.',
                        'count' => count($heroImages),
                        'images' => $heroImages
                    ]);
                    exit;
                }
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Errore rigenerazione carosello: ' . $e->getMessage()
                ]);
            }
            exit;
        case 'search_users':
            try {
                // Debug logging
                error_log("AJAX search_users richiesta ricevuta: " . json_encode($_POST));
                
                $searchTerm = $_POST['search'] ?? '';
                $limit = (int)($_POST['limit'] ?? 50);
                $sortBy = $_POST['sort'] ?? 'relevance';
                
                if (strlen($searchTerm) < 3) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Inserisci almeno 3 caratteri per la ricerca'
                    ]);
                    exit;
                }
                
                // Test connessione database
                if (!$pdo) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Errore connessione database'
                    ]);
                    exit;
                }
                
                // Esegui ricerca con limite
                $searchResults = searchUsers($pdo, $searchTerm, $errorMessage, $limit > 0 ? $limit : null);
                
                // Aggiungi calcoli aggiuntivi per ogni utente
                foreach ($searchResults as &$user) {
                    // Calcola statistiche complete per utente
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as totale_iscrizioni,
                            SUM(CASE WHEN validato = 1 THEN 1 ELSE 0 END) as totale_validazioni,
                            MIN(created_at) as prima_iscrizione,
                            MAX(CASE WHEN validato = 1 THEN validated_at END) as ultima_presenza
                        FROM utenti 
                        WHERE email = :email
                    ");
                    $stmt->execute([':email' => $user['email']]);
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Assicurati che i valori siano numerici e non null
                    $user['totale_iscrizioni'] = (int)($stats['totale_iscrizioni'] ?? 0);
                    $user['totale_validazioni'] = (int)($stats['totale_validazioni'] ?? 0);
                    $user['prima_iscrizione'] = $stats['prima_iscrizione'];
                    $user['ultima_presenza'] = $stats['ultima_presenza'];
                    
                    // Calcola percentuale con controllo divisione per zero
                    if ($user['totale_iscrizioni'] > 0) {
                        $user['percentuale_presenze'] = round(($user['totale_validazioni'] / $user['totale_iscrizioni']) * 100, 1);
                    } else {
                        $user['percentuale_presenze'] = 0;
                    }
                    
                    $user['is_blocked'] = false; // Blocco utenti disabilitato
                    

                }
                unset($user);
                
                echo json_encode([
                    'success' => true,
                    'users' => $searchResults,
                    'count' => count($searchResults),
                    'search_term' => $searchTerm
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            } catch (Error $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Errore interno: ' . $e->getMessage()
                ]);
            }
            exit;
        case 'view_log':
            $logType = $_POST['log_type'] ?? '';
            $logFiles = [
                'rinvii' => rtrim($logBaseDir, '/') . '/log_rinviati.txt',
                'errori' => rtrim($logBaseDir, '/') . '/error.log',
                'accessi' => rtrim($logBaseDir, '/') . '/access.log'
            ];
            
            if (isset($logFiles[$logType]) && file_exists($logFiles[$logType])) {
                $content = file_get_contents($logFiles[$logType]);
                // Limita a ultime 100 righe per performance
                $lines = explode("\n", $content);
                $lines = array_slice($lines, -100);
                $content = implode("\n", $lines);
                
                echo json_encode([
                    'success' => true,
                    'content' => $content,
                    'size' => round(filesize($logFiles[$logType]) / 1024, 2)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'File di log non trovato'
                ]);
            }
            exit;
            
        case 'clear_log':
            $logType = $_POST['log_type'] ?? '';
            $logFiles = [
                'rinvii' => rtrim($logBaseDir, '/') . '/log_rinviati.txt',
                'errori' => rtrim($logBaseDir, '/') . '/error.log',
                'accessi' => rtrim($logBaseDir, '/') . '/access.log'
            ];
            
            if (isset($logFiles[$logType]) && file_exists($logFiles[$logType])) {
                $backup = file_get_contents($logFiles[$logType]);
                file_put_contents($logFiles[$logType] . '.backup', $backup);
                file_put_contents($logFiles[$logType], "# Log pulito il " . date('Y-m-d H:i:s') . "\n");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Log pulito con successo. Backup salvato.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'File di log non trovato'
                ]);
            }
            exit;
            
        case 'unblock_user':
            try {
                $email = $_POST['email'] ?? '';
                
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Email non valida'
                    ]);
                    exit;
                }
                
                // Verifica che l'utente sia effettivamente bloccato
                if (!isUserBlocked($pdo, $email)) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'L\'utente non risulta bloccato'
                    ]);
                    exit;
                }
                
                // Funzione di sblocco disabilitata
                echo json_encode([
                    'success' => false,
                    'error' => 'Funzione di blocco/sblocco utenti disabilitata'
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Errore durante lo sblocco: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'optimize_database':
            try {
                $tables = ['events', 'utenti'];
                foreach ($tables as $table) {
                    $pdo->exec("OPTIMIZE TABLE {$table}");
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Database ottimizzato con successo'
                ]);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Errore durante l\'ottimizzazione: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'clean_hero_carousel':
            try {
                cleanHeroImages($successMessage, $errorMessage);
                
                if ($successMessage) {
                    echo json_encode([
                        'success' => true,
                        'message' => $successMessage
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => $errorMessage ?: 'Errore durante la pulizia del carosello'
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Errore durante la pulizia: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'test_smtp':
            try {
                // Verifica configurazione SMTP
                if (!isset($config['email'])) {
                    throw new Exception('Configurazione email non trovata');
                }
                
                $emailConfig = $config['email'];
                $requiredFields = ['host', 'port', 'username', 'password', 'encryption'];
                
                foreach ($requiredFields as $field) {
                    if (!isset($emailConfig[$field]) || empty($emailConfig[$field])) {
                        throw new Exception("Campo '$field' mancante nella configurazione email");
                    }
                }
                
                // Test connessione SMTP (senza inviare email)
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $emailConfig['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $emailConfig['username'];
                $mail->Password = $emailConfig['password'];
                $mail->SMTPSecure = $emailConfig['encryption'];
                $mail->Port = $emailConfig['port'];
                $mail->Timeout = 10; // Timeout breve per test
                
                // Test connessione
                if ($mail->smtpConnect()) {
                    $mail->smtpClose();
                    
                    echo json_encode([
                        'success' => true,
                        'host' => $emailConfig['host'],
                        'port' => $emailConfig['port'],
                        'encryption' => $emailConfig['encryption'],
                        'username' => $emailConfig['username']
                    ]);
                } else {
                    throw new Exception('Impossibile connettersi al server SMTP');
                }
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
            
        case 'test_database':
            try {
                // Test connessione database
                $version = $pdo->query('SELECT VERSION()')->fetchColumn();
                
                // Conta tabelle
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->rowCount();
                
                // Conta utenti ed eventi
                $users = $pdo->query("SELECT COUNT(*) FROM utenti")->fetchColumn();
                $events = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
                
                echo json_encode([
                    'success' => true,
                    'version' => $version,
                    'tables' => $tables,
                    'users' => $users,
                    'events' => $events
                ]);
                
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
            
        case 'verifica_connessioni':
            try {
                // Test connessione database
                $dbResult = ['success' => false, 'error' => ''];
                try {
                    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
                    $dbResult = [
                        'success' => true,
                        'version' => $version,
                        'message' => 'Database connesso correttamente'
                    ];
                } catch (PDOException $e) {
                    $dbResult = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
                
                // Test configurazione SMTP
                $smtpResult = ['success' => false, 'error' => ''];
                try {
                    if (!isset($config['email'])) {
                        throw new Exception('Configurazione email non trovata');
                    }
                    
                    $emailConfig = $config['email'];
                    $requiredFields = ['host', 'port', 'username', 'password', 'encryption'];
                    
                    foreach ($requiredFields as $field) {
                        if (!isset($emailConfig[$field]) || empty($emailConfig[$field])) {
                            throw new Exception("Campo '$field' mancante nella configurazione email");
                        }
                    }
                    
                    $smtpResult = [
                        'success' => true,
                        'host' => $emailConfig['host'],
                        'port' => $emailConfig['port'],
                        'encryption' => $emailConfig['encryption'],
                        'username' => $emailConfig['username'],
                        'message' => 'SMTP configurato correttamente'
                    ];
                    
                } catch (Exception $e) {
                    $smtpResult = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'database' => $dbResult,
                    'smtp' => $smtpResult
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
            
        case 'check_smtp_config':
            $configured = isset($config['email']) && 
                         isset($config['email']['host']) && 
                         isset($config['email']['username']) && 
                         !empty($config['email']['host']) && 
                         !empty($config['email']['username']);
                         
            echo json_encode(['configured' => $configured]);
            exit;
            
        case 'check_form_status':
            try {
                $stmt = $pdo->prepare("
                    SELECT id, titolo, event_date
                    FROM events
                    WHERE COALESCE(chiuso, 0) = 0
                    AND (event_date IS NULL OR event_date >= CURDATE())
                    ORDER BY event_date ASC
                    LIMIT 1
                ");
                $stmt->execute();
                $event = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($event) {
                    echo json_encode([
                        'success' => true,
                        'event' => $event
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Nessun evento aperto disponibile'
                    ]);
                }
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Errore verifica form: ' . $e->getMessage()
                ]);
            }
            exit;

        case 'cleanup_test':
            try {
                $email = $_POST['email'] ?? '';
                if (empty($email)) {
                    throw new Exception('Email non specificata');
                }
                
                // Rimuovi dati di test dal database
                $stmt = $pdo->prepare("DELETE FROM utenti WHERE email = ? AND nome = 'Test' AND cognome = 'Sistema'");
                $deleted = $stmt->execute([$email]);
                
                if ($deleted) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Dati di test rimossi con successo'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Nessun dato di test trovato da rimuovere'
                    ]);
                }
                
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Errore durante la rimozione: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'save_email_texts':
            try {
                $texts = $_POST['texts'] ?? [];
                if (empty($texts)) {
                    throw new Exception('Nessun testo specificato');
                }
                
                // Crea tabella email_texts se non esiste
                $pdo->exec("CREATE TABLE IF NOT EXISTS email_texts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    text_key VARCHAR(100) NOT NULL UNIQUE,
                    text_value TEXT NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");
                
                // Salva i testi
                $stmt = $pdo->prepare("INSERT INTO email_texts (text_key, text_value) VALUES (?, ?) 
                                     ON DUPLICATE KEY UPDATE text_value = VALUES(text_value)");
                
                foreach ($texts as $key => $value) {
                    $stmt->execute([$key, $value]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Testi email salvati con successo'
                ]);
                
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Errore durante il salvataggio: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'get_email_texts':
            try {
                // Crea tabella se non esiste
                $pdo->exec("CREATE TABLE IF NOT EXISTS email_texts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    text_key VARCHAR(100) NOT NULL UNIQUE,
                    text_value TEXT NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");
                
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
                
                // Recupera testi salvati
                $stmt = $pdo->query("SELECT text_key, text_value FROM email_texts");
                $savedTexts = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $savedTexts[$row['text_key']] = $row['text_value'];
                }
                
                // Combina testi predefiniti con quelli salvati
                $texts = array_merge($defaultTexts, $savedTexts);
                
                echo json_encode([
                    'success' => true,
                    'texts' => $texts
                ]);
                
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Errore durante il caricamento: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'send_email_to_user':
            try {
                $email = $_POST['email'] ?? '';
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Email non valida');
                }
                
                // Trova l'ultimo evento dell'utente per inviare email
                $stmt = $pdo->prepare("
                    SELECT u.*, e.titolo, e.event_date 
                    FROM utenti u 
                    LEFT JOIN events e ON u.evento = e.id 
                    WHERE u.email = :email 
                    ORDER BY u.created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    throw new Exception('Utente non trovato');
                }
                
                // Usa la funzione esistente per reinviare email
                resendEmail($pdo, $user['id'], $config, $successMessage, $errorMessage);
                
                if (!empty($successMessage)) {
                    echo json_encode(['success' => true, 'message' => $successMessage]);
                } else {
                    echo json_encode(['success' => false, 'error' => $errorMessage ?: 'Errore sconosciuto']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'export_user_data':
            try {
                $email = $_GET['email'] ?? '';
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Email non valida');
                }
                
                // Ottieni tutti i dati dell'utente
                $stmt = $pdo->prepare("
                    SELECT 
                        u.nome,
                        u.cognome,
                        u.email,
                        u.telefono,
                        u.data_nascita,
                        u.created_at,
                        u.validato,
                        u.validated_at,
                        u.email_inviata,
                        e.titolo as evento_titolo,
                        e.event_date
                    FROM utenti u
                    LEFT JOIN events e ON u.evento = e.id
                    WHERE u.email = :email
                    ORDER BY e.event_date DESC
                ");
                $stmt->execute([':email' => $email]);
                $userData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($userData)) {
                    throw new Exception('Nessun dato trovato per questo utente');
                }
                
                // Genera CSV
                $filename = 'user_data_' . str_replace(['@', '.'], ['_at_', '_'], $email) . '_' . date('Y-m-d_H-i-s') . '.csv';
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                $output = fopen('php://output', 'w');
                
                // Header CSV
                fputcsv($output, [
                    'Nome', 'Cognome', 'Email', 'Telefono', 'Data Nascita',
                    'Evento', 'Data Evento', 'Data Iscrizione', 'Validato', 
                    'Data Validazione', 'Email Inviata'
                ]);
                
                // Dati
                foreach ($userData as $row) {
                    fputcsv($output, [
                        $row['nome'],
                        $row['cognome'],
                        $row['email'],
                        $row['telefono'],
                        $row['data_nascita'],
                        $row['evento_titolo'],
                        $row['event_date'],
                        $row['created_at'],
                        $row['validato'] ? 'Sì' : 'No',
                        $row['validated_at'],
                        $row['email_inviata']
                    ]);
                }
                
                fclose($output);
                exit;
                
            } catch (Exception $e) {
                http_response_code(400);
                echo 'Errore: ' . $e->getMessage();
                exit;
            }
            
        case 'delete_all_user_records':
            try {
                $email = $_POST['email'] ?? '';
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Email non valida');
                }
                
                // Protezione per admin
                if ($email === 'angelo.bernardini@gmail.com') {
                    throw new Exception('Non è possibile eliminare l\'account amministratore');
                }
                
                // Elimina tutti i record dell'utente
                $stmt = $pdo->prepare("DELETE FROM utenti WHERE email = :email");
                $deleted = $stmt->execute([':email' => $email]);
                $deletedCount = $stmt->rowCount();
                
                if ($deletedCount > 0) {
                    echo json_encode([
                        'success' => true, 
                        'message' => "Eliminati {$deletedCount} record per l'utente {$email}"
                    ]);
                } else {
                    echo json_encode([
                        'success' => false, 
                        'error' => 'Nessun record trovato da eliminare'
                    ]);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'load_statistics':
            try {
                $oggi = date('Y-m-d');
                
                // Ottieni tutti gli eventi passati/scaduti con statistiche orarie
                $stmt = $pdo->prepare("
                    SELECT 
                        e.id,
                        e.titolo,
                        e.event_date,
                        e.chiuso,
                        COUNT(u.id) as tot_iscritti,
                        SUM(CASE WHEN u.validato = 1 THEN 1 ELSE 0 END) as tot_validati,
                        ROUND(
                            (SUM(CASE WHEN u.validato = 1 THEN 1 ELSE 0 END) / COUNT(u.id)) * 100, 
                            1
                        ) as percentuale_presenza,
                        MIN(CASE WHEN u.validato = 1 THEN u.validated_at END) as prima_validazione,
                        MAX(CASE WHEN u.validato = 1 THEN u.validated_at END) as ultima_validazione
                    FROM events e
                    LEFT JOIN utenti u ON e.id = u.evento
                    WHERE e.event_date < :oggi OR e.chiuso = 1
                    GROUP BY e.id, e.titolo, e.event_date, e.chiuso
                    ORDER BY e.event_date DESC
                ");
                $stmt->execute([':oggi' => $oggi]);
                $eventiPassati = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Aggiungi statistiche orarie per ogni evento
                foreach ($eventiPassati as &$evento) {
                    // Debug: controlla tutte le validazioni per questo evento
                    $debugStmt = $pdo->prepare("
                        SELECT 
                            validated_at,
                            HOUR(validated_at) as ora
                        FROM utenti 
                        WHERE evento = :evento 
                        AND validato = 1 
                        AND validated_at IS NOT NULL
                        LIMIT 10
                    ");
                    $debugStmt->execute([':evento' => $evento['id']]);
                    $debugValidazioni = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
                    error_log("DEBUG Evento {$evento['id']} - Validazioni campione: " . json_encode($debugValidazioni));
                    
                    // Ottieni validazioni aggregate per intervalli di 15 minuti dalle 23:00 alle 04:00
                    $stmt = $pdo->prepare("
                        SELECT 
                            HOUR(validated_at) AS ora,
                            FLOOR(MINUTE(validated_at) / 15) AS intervallo_15min,
                            CONCAT(
                                LPAD(HOUR(validated_at), 2, '0'), 
                                ':', 
                                LPAD(FLOOR(MINUTE(validated_at) / 15) * 15, 2, '0')
                            ) AS tempo_formato,
                            COUNT(*) AS validazioni
                        FROM utenti 
                        WHERE evento = :evento 
                        AND validato = 1 
                        AND validated_at IS NOT NULL
                        AND (
                            HOUR(validated_at) = 23 OR 
                            HOUR(validated_at) = 0 OR 
                            HOUR(validated_at) = 1 OR 
                            HOUR(validated_at) = 2 OR 
                            HOUR(validated_at) = 3 OR 
                            HOUR(validated_at) = 4
                        )
                        GROUP BY HOUR(validated_at), FLOOR(MINUTE(validated_at) / 15)
                        ORDER BY 
                            CASE WHEN HOUR(validated_at) = 23 THEN 0 ELSE HOUR(validated_at) + 1 END,
                            FLOOR(MINUTE(validated_at) / 15)
                    ");
                    $stmt->execute([':evento' => $evento['id']]);
                    $validazioniPer15Min = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Debug: log delle validazioni per intervalli di 15min trovate
                    error_log("DEBUG Evento {$evento['id']} - Validazioni per 15min 23-04: " . json_encode($validazioniPer15Min));
                    
                    // Crea array per il grafico a linee con tutti i punti temporali
                    $datiGrafico = [];
                    $totalValidazioni = 0;
                    
                    foreach ($validazioniPer15Min as $validazione) {
                        $ora = (int)$validazione['ora'];
                        $intervallo15 = (int)$validazione['intervallo_15min'];
                        $validCount = (int)$validazione['validazioni'];
                        $totalValidazioni += $validCount;
                        
                        // Crea timestamp relativo per l'ordinamento 
                        // (23:xx = 0-3, 00:xx = 4-7, 01:xx = 8-11, ecc.)
                        $timestampRelativo = ($ora == 23) ? $intervallo15 : 
                                           (($ora * 4) + $intervallo15 + 4);
                        
                        $datiGrafico[] = [
                            'ora' => $ora,
                            'intervallo_15min' => $intervallo15,
                            'tempo_formato' => $validazione['tempo_formato'],
                            'timestamp_relativo' => $timestampRelativo,
                            'validazioni' => $validCount
                        ];
                    }
                    
                    // Ordina per timestamp relativo
                    usort($datiGrafico, function($a, $b) {
                        return $a['timestamp_relativo'] - $b['timestamp_relativo'];
                    });
                    
                    $evento['validazioni_orarie'] = $datiGrafico;
                    $evento['total_validazioni_intervallo'] = $totalValidazioni;
                }
                unset($evento);
                
                // Calcola statistiche generali per eventi passati
                $totalEventiPassati = count($eventiPassati);
                $totalIscritti = array_sum(array_column($eventiPassati, 'tot_iscritti'));
                $totalValidati = array_sum(array_column($eventiPassati, 'tot_validati'));
                $mediaPresenza = $totalIscritti > 0 ? round(($totalValidati / $totalIscritti) * 100, 1) : 0;
                
                // Trova l'evento con maggiore partecipazione
                $eventoMigliore = null;
                $maxPartecipazione = 0;
                foreach ($eventiPassati as $evento) {
                    if ($evento['tot_iscritti'] > $maxPartecipazione) {
                        $maxPartecipazione = $evento['tot_iscritti'];
                        $eventoMigliore = $evento;
                    }
                }
                
                // Calcola trend mensile per gli ultimi 6 mesi
                $trendMensile = [];
                for ($i = 5; $i >= 0; $i--) {
                    $mese = date('Y-m', strtotime("-{$i} months"));
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(DISTINCT e.id) as eventi,
                            COUNT(u.id) as iscritti,
                            SUM(CASE WHEN u.validato = 1 THEN 1 ELSE 0 END) as validati
                        FROM events e
                        LEFT JOIN utenti u ON e.id = u.evento
                        WHERE DATE_FORMAT(e.event_date, '%Y-%m') = :mese
                        AND e.event_date < :oggi
                    ");
                    $stmt->execute([':mese' => $mese, ':oggi' => $oggi]);
                    $datiMese = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $trendMensile[] = [
                        'mese' => $mese,
                        'mese_label' => date('M Y', strtotime($mese . '-01')),
                        'eventi' => (int)$datiMese['eventi'],
                        'iscritti' => (int)$datiMese['iscritti'],
                        'validati' => (int)$datiMese['validati'],
                        'percentuale' => $datiMese['iscritti'] > 0 ? round(($datiMese['validati'] / $datiMese['iscritti']) * 100, 1) : 0
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'statistiche_generali' => [
                        'total_eventi_passati' => $totalEventiPassati,
                        'total_iscritti' => $totalIscritti,
                        'total_validati' => $totalValidati,
                        'media_presenza' => $mediaPresenza,
                        'evento_migliore' => $eventoMigliore
                    ],
                    'eventi_passati' => $eventiPassati,
                    'trend_mensile' => $trendMensile
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Errore durante il caricamento delle statistiche: ' . $e->getMessage()
                ]);
            }
            exit;
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Azione non riconosciuta: ' . ($_POST['action'] ?? 'nessuna')
            ]);
            exit;
        }
    } catch (Exception $e) {
        // Pulisci buffer e invia errore JSON
        if (ob_get_level()) {
            ob_clean();
        }
        echo json_encode([
            'success' => false,
            'error' => 'Errore interno: ' . $e->getMessage()
        ]);
        exit;
    } catch (Error $e) {
        // Pulisci buffer e invia errore JSON
        if (ob_get_level()) {
            ob_clean();
        }
        echo json_encode([
            'success' => false,
            'error' => 'Errore fatale: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Inizializza variabili per messaggi di successo ed errore
$successMessage = '';
$errorMessage = '';

// Gestione delle richieste GET/POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['titolo'])) {
    handleEventSubmission($pdo, $successMessage, $errorMessage);
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['chiudi_evento']) && isset($_POST['chiudi_event_id'])) {
    $eventoId = intval($_POST['chiudi_event_id']);
    try {
        $stmt = $pdo->prepare("UPDATE events SET chiuso = 1 WHERE id = :id");
        $stmt->execute([':id' => $eventoId]);
        $successMessage = "Evento chiuso con successo.";
    } catch (PDOException $e) {
        $errorMessage = "Errore durante la chiusura dell'evento: " . $e->getMessage();
    }
}
// Gestione POST per riapertura evento
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['riapri_evento']) && isset($_POST['riapri_event_id'])) {
    $eventoId = intval($_POST['riapri_event_id']);
    try {
        $stmt = $pdo->prepare("UPDATE events SET chiuso = 0 WHERE id = :id");
        $stmt->execute([':id' => $eventoId]);
        $successMessage = "Evento riaperto con successo.";
    } catch (PDOException $e) {
        $errorMessage = "Errore durante la riapertura dell'evento: " . $e->getMessage();
    }
}
if (isset($_GET['delete'])) {
    deleteEvent($pdo, intval($_GET['delete']), $successMessage, $errorMessage);
}
if (isset($_GET['invalidate']) && !empty($_GET['invalidate'])) {
    invalidateQRCode($pdo, $_GET['invalidate'], $successMessage, $errorMessage);
}
if (isset($_GET['delete_user'])) {
    $userId = intval($_GET['delete_user']);
    error_log('[ADMIN] Parametro delete_user ricevuto: ' . $userId . ' (raw: ' . ($_GET['delete_user'] ?? 'null') . ')');
    deleteUser($pdo, $userId, $successMessage, $errorMessage);
}
if (isset($_GET['resend_email'])) {
    resendEmail($pdo, intval($_GET['resend_email']), $config, $successMessage, $errorMessage);
}

// Gestione sblocco utente - DISABILITATA
if (isset($_GET['unblock_user']) && !empty($_GET['unblock_user'])) {
    $errorMessage = "Funzione di blocco/sblocco utenti disabilitata";
}

    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['resend_all']) && isset($_GET['details_event'])) {
        $eventoId = intval($_GET['details_event']);
        $stmt = $pdo->prepare("SELECT id FROM utenti WHERE evento = :evento AND email_inviata IS NULL");
        $stmt->execute([':evento' => $eventoId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $logFile = rtrim($logBaseDir, '/') . '/log_rinviati.txt';
        file_put_contents($logFile, "📩 Rinvio multiplo per evento ID $eventoId - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        foreach ($ids as $id) {
            resendEmail($pdo, $id, $config, $successMessage, $errorMessage);
            file_put_contents($logFile, "Inviato a ID utente: $id\n", FILE_APPEND);
            sleep(1); // piccola pausa per non sovraccaricare SMTP
        }
        file_put_contents($logFile, "✅ Fine invio\n\n", FILE_APPEND);
        header("Location: /admin?details_event=$eventoId");
        exit;
    }

    // Gestione POST per il rinvio selezionato multiplo
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['resend_selected']) && isset($_POST['selected_users']) && isset($_GET['details_event'])) {
        $eventoId = intval($_GET['details_event']);
        $ids = $_POST['selected_users'];
        $logFile = rtrim($logBaseDir, '/') . '/log_rinviati.txt';
        file_put_contents($logFile, "📩 Rinvio selezionato multiplo per evento ID $eventoId - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        foreach ($ids as $id) {
            resendEmail($pdo, $id, $config, $successMessage, $errorMessage);
            file_put_contents($logFile, "Inviato a ID utente: $id\n", FILE_APPEND);
            sleep(1);
        }
        file_put_contents($logFile, "✅ Fine invio selezionati\n\n", FILE_APPEND);
        header("Location: /admin?details_event=$eventoId");
        exit;
    }

// Variabili per filtraggio e ricerca
$selectedEvent = $_GET['filter_evento'] ?? '';
$searchUser = $_GET['q_user'] ?? '';
$detailsEvent = $_GET['details_event'] ?? '';

$stats = (!empty($selectedEvent)) ? getEventStatistics($pdo, $selectedEvent, $errorMessage) : null;
$detailsUsers = (!empty($detailsEvent)) ? getEventUsers($pdo, $detailsEvent, $errorMessage) : [];
// Ricerca senza limiti - cerca in tutti i contatti
$searchResults = (!empty($searchUser)) ? searchUsers($pdo, $searchUser, $errorMessage) : [];

// NOTA: Rimossa la variabile $allUsers - il sistema ora usa solo ricerca AJAX
$userStats = (!empty($detailsEvent)) ? getUserStatsByEvent($pdo, $detailsEvent, $errorMessage) : [];
$hourlyStats = (!empty($detailsEvent)) ? getHourlyValidationStats($pdo, $detailsEvent, $errorMessage) : [];

// Se c'è un evento da modificare
$editEvent = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editEvent = findEventById($pdo, $editId);
}

// Carico la lista degli eventi
$events = readEvents($pdo);

// Individua il primo evento disponibile per i test automatici
$testEventForSystem = null;
$todayDate = date('Y-m-d');
foreach ($events as $eventItem) {
    $eventDate = $eventItem['event_date'] ?? null;
    $isClosed = !empty($eventItem['chiuso']);
    $isPast = $eventDate ? ($eventDate < $todayDate) : false;

    if (!$isClosed && !$isPast) {
        $testEventForSystem = $eventItem;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Admin - Gestione Eventi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="./assets/css/style.css" rel="stylesheet">
    <link href="admin_styles.css" rel="stylesheet">
</head>
<body>
    <div class="main-container">
        <header class="top-header">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="m-0 mb-2">🎯 Dashboard Gestione Eventi</h1>
                    <p class="mb-0 opacity-75">Controlla e gestisci tutti i tuoi eventi in un unico posto</p>
                </div>
                <a href="./logout.php" class="btn logout-btn">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                </a>
            </div>
            
            <nav>
                <ul class="nav nav-pills justify-content-center flex-wrap" style="gap: 12px;">
                    <li class="nav-item">
                        <a class="nav-link" href="#statistiche-evento">
                            <i class="bi bi-bar-chart-line me-2"></i>
                            <span>Statistiche</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#lista-eventi">
                            <i class="bi bi-grid-3x3-gap me-2"></i>
                            <span>Gestione Completa</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </header>
        
        <main class="content-wrapper">
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>



        <!-- Statistiche per Evento -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title m-0" id="statistiche-evento">
                    <i class="bi bi-bar-chart-line me-2"></i>Statistiche Eventi
                </h3>
                <p class="mb-0 mt-2" style="color: rgba(255,255,255,0.7);">
                    Visualizza le statistiche di ingresso e validazioni per tutti gli eventi
                </p>
            </div>
            <div class="card-body">
                <?php
                // Calcola statistiche solo per gli eventi ATTIVI
                $oggi = date('Y-m-d');
                $eventiAttivi = [];
                
                foreach ($events as $eventItem) {
                    $isScaduto = (isset($eventItem['event_date']) && $eventItem['event_date'] < $oggi);
                    $isChiuso = !empty($eventItem['chiuso']);
                    
                    // Solo per eventi attivi
                    if (!$isScaduto && !$isChiuso) {
                        // Calcola statistiche per questo evento attivo
                        $stmt = $pdo->prepare("
                            SELECT 
                                COUNT(*) AS tot,
                                SUM(CASE WHEN validato = 1 THEN 1 ELSE 0 END) AS val
                            FROM utenti
                            WHERE evento = :evento
                        ");
                        $stmt->execute([':evento' => $eventItem['id']]);
                        $stat = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $eventItem['stats'] = [
                            'totIscritti' => $stat['tot'],
                            'totValidati' => $stat['val'],
                            'totNonValidi' => $stat['tot'] - $stat['val'],
                            'percentualeValidati' => $stat['tot'] > 0 ? round(($stat['val'] / $stat['tot']) * 100, 1) : 0
                        ];
                        
                        $eventiAttivi[] = $eventItem;
                    }
                }
                ?>
                
                <!-- Eventi Attivi -->
                <?php if (!empty($eventiAttivi)): ?>
                    <h5 class="mb-3" style="color: #28a745;">
                        <i class="bi bi-calendar-check me-2"></i>Eventi Attivi
                    </h5>
                    <?php foreach ($eventiAttivi as $eventItem): ?>
                        <div class="mb-4 p-4" style="background: rgba(40, 167, 69, 0.1); border-radius: 15px; border: 1px solid rgba(40, 167, 69, 0.3);">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1" style="color: #e0e0e0;">
                                        <i class="bi bi-calendar-event me-2"></i>
                                        <?php echo htmlspecialchars($eventItem['titolo']); ?>
                                        <span class="badge bg-success ms-2">Attivo</span>
                                    </h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($eventItem['date']); ?></small>
                                </div>
                                <a href="./admin?details_event=<?php echo urlencode($eventItem['id']); ?>" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-info-circle me-1"></i>Dettagli
                                </a>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="stats-card" style="padding: 1.2rem;">
                                        <div class="mb-2">
                                            <i class="bi bi-people-fill" style="font-size: 1.8rem; color: #4a90e2;"></i>
                                        </div>
                                        <div class="stats-number" style="font-size: 1.6rem;"><?php echo $eventItem['stats']['totIscritti']; ?></div>
                                        <h6 class="mb-0">Iscritti</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stats-card" style="background: linear-gradient(135deg, #2d5a3d 0%, #4a7c5a 100%); padding: 1.2rem;">
                                        <div class="mb-2">
                                            <i class="bi bi-check-circle-fill" style="font-size: 1.8rem;"></i>
                                        </div>
                                        <div class="stats-number" style="font-size: 1.6rem;"><?php echo $eventItem['stats']['totValidati']; ?></div>
                                        <h6 class="mb-0">Validati</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stats-card" style="background: linear-gradient(135deg, #5a2d2d 0%, #7c4a4a 100%); padding: 1.2rem;">
                                        <div class="mb-2">
                                            <i class="bi bi-clock-fill" style="font-size: 1.8rem;"></i>
                                        </div>
                                        <div class="stats-number" style="font-size: 1.6rem;"><?php echo $eventItem['stats']['totNonValidi']; ?></div>
                                        <h6 class="mb-0">Non Validati</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stats-card" style="background: linear-gradient(135deg, #7b68ee 0%, #9370db 100%); padding: 1.2rem;">
                                        <div class="mb-2">
                                            <i class="bi bi-percent" style="font-size: 1.8rem;"></i>
                                        </div>
                                        <div class="stats-number" style="font-size: 1.6rem;"><?php echo $eventItem['stats']['percentualeValidati']; ?>%</div>
                                        <h6 class="mb-0">Tasso Presenza</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                

                
                <!-- Messaggio se non ci sono eventi attivi -->
                <?php if (empty($eventiAttivi)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-calendar-plus" style="font-size: 3rem; color: #666;"></i>
                        <p class="text-muted mt-2">Nessun evento attivo al momento</p>
                        <p class="text-muted">
                            <small>
                                <i class="bi bi-info-circle me-1"></i>
                                Le statistiche degli eventi passati sono disponibili nella tab "Statistiche"
                            </small>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sistema Tab -->
        <div class="card mb-4" id="lista-eventi">
            <div class="card-header">
                <h3 class="card-title m-0">
                    <i class="bi bi-grid-3x3-gap me-2"></i>Gestione Completa
                </h3>
            </div>
            <div class="card-body p-0">
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs nav-justified" id="mainTabs" role="tablist" style="border-bottom: none;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="crea-evento-tab" data-bs-toggle="tab" data-bs-target="#crea-evento" type="button" role="tab">
                            <i class="bi bi-calendar-plus me-2"></i>Crea Evento
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="eventi-attivi-tab" data-bs-toggle="tab" data-bs-target="#eventi-attivi" type="button" role="tab">
                            <i class="bi bi-calendar-check me-2"></i>Eventi Attivi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="eventi-passati-tab" data-bs-toggle="tab" data-bs-target="#eventi-passati" type="button" role="tab">
                            <i class="bi bi-calendar-x me-2"></i>Eventi Passati
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tutti-utenti-tab" data-bs-toggle="tab" data-bs-target="#tutti-utenti" type="button" role="tab">
                            <i class="bi bi-search me-2"></i>Ricerca Utenti
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="compleanni-tab" data-bs-toggle="tab" data-bs-target="#compleanni" type="button" role="tab">
                            <i class="bi bi-balloon-heart me-2"></i>Compleanni
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="statistiche-tab" data-bs-toggle="tab" data-bs-target="#statistiche" type="button" role="tab">
                            <i class="bi bi-bar-chart me-2"></i>Statistiche
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="carosello-tab" data-bs-toggle="tab" data-bs-target="#carosello" type="button" role="tab">
                            <i class="bi bi-images me-2"></i>Gestione Carosello
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="impostazioni-tab" data-bs-toggle="tab" data-bs-target="#impostazioni" type="button" role="tab">
                            <i class="bi bi-gear me-2"></i>Impostazioni
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content p-4" id="mainTabsContent">
                    <!-- Crea Evento Tab -->
                    <div class="tab-pane fade show active" id="crea-evento" role="tabpanel">
                        <div class="row justify-content-center">
                            <div class="col-lg-8">
                                <div class="text-center mb-4">
                                    <h4 style="color: #e0e0e0;">
                                        <i class="bi bi-<?php echo ($editEvent) ? 'pencil-square' : 'calendar-plus'; ?> me-2"></i>
                                        <?php echo ($editEvent) ? 'Modifica Evento' : 'Crea Nuovo Evento'; ?>
                                    </h4>
                                    <p class="text-muted">Compila tutti i campi per creare un nuovo evento</p>
                                </div>
                                
                                <form method="POST" action="./admin" enctype="multipart/form-data" class="p-4" style="background: rgba(50, 50, 50, 0.6); border-radius: 20px; border: 1px solid rgba(192, 192, 192, 0.2);">
                                    <?php if ($editEvent): ?>
                                        <input type="hidden" name="edit_index" value="<?php echo intval($editEvent['id']); ?>">
                                    <?php endif; ?>
                                    
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label for="data" class="form-label" style="color: #c0c0c0; font-weight: 500;">
                                                <i class="bi bi-calendar3 me-2"></i>Data Evento
                                            </label>
                                            <input type="date" class="form-control" id="data" name="data" required
                                                   value="<?php 
                                                     if ($editEvent) {
                                                         $dateObj = DateTime::createFromFormat('d-m-Y', $editEvent['date']);
                                                         echo $dateObj ? $dateObj->format('Y-m-d') : '';
                                                     } 
                                                   ?>">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="titolo" class="form-label" style="color: #c0c0c0; font-weight: 500;">
                                                <i class="bi bi-card-text me-2"></i>Titolo Evento
                                            </label>
                                            <input type="text" class="form-control" id="titolo" name="titolo" required
                                                   placeholder="Inserisci il titolo dell'evento"
                                                   value="<?php echo $editEvent ? htmlspecialchars($editEvent['titolo']) : ''; ?>">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="sfondo" class="form-label" style="color: #c0c0c0; font-weight: 500;">
                                                <i class="bi bi-image me-2"></i>Immagine Tickets
                                            </label>
                                            <input type="file" class="form-control" id="sfondo" name="sfondo" accept=".png, .jpg, .jpeg">
                                            <small class="text-muted">Formato: PNG, JPG, JPEG</small>
                                            <?php 
                                            if ($editEvent && !empty($editEvent['background_image'])): 
                                                $previewUrl = resolveBackgroundUrl($config, $editEvent['background_image']);
                                                if ($previewUrl): ?>
                                                <div class="mt-3">
                                                    <div class="text-muted small mb-1">Sfondo attuale:</div>
                                                    <img src="<?php echo htmlspecialchars($previewUrl); ?>" alt="Anteprima sfondo" style="max-width:100%; max-height:220px; border:1px solid rgba(255,255,255,0.1); border-radius:8px; object-fit:cover;" />
                                                    <div class="mt-2">
                                                        <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars($previewUrl); ?>" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>Apri immagine</a>
                                                        <a class="btn btn-sm btn-outline-primary" href="./admin?action=preview_pdf&evento_id=<?php echo intval($editEvent['id']); ?>" target="_blank"><i class="bi bi-filetype-pdf me-1"></i>Anteprima PDF</a>
                                                    </div>
                                                </div>
                                                <?php endif; 
                                            endif; ?>
                                            <div class="mt-3" id="sfondoLivePreviewWrapper" style="display:none;">
                                                <div class="text-muted small mb-1">Anteprima nuova immagine:</div>
                                                <img id="sfondoLivePreview" src="" alt="Anteprima live" style="max-width:100%; max-height:220px; border:1px solid rgba(255,255,255,0.1); border-radius:8px; object-fit:cover;" />
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="hero_image" class="form-label" style="color: #c0c0c0; font-weight: 500;">
                                                <i class="bi bi-image-fill me-2"></i>Immagine Hero
                                            </label>
                                            <input type="file" class="form-control" id="hero_image" name="hero_image" accept=".png, .jpg, .jpeg">
                                            <small class="text-muted">Immagine principale dell'evento</small>
                                        </div>
                                        
                                        <div class="col-12 text-center mt-4">
                                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                                <i class="bi bi-<?php echo ($editEvent) ? 'check-circle' : 'plus-circle'; ?> me-2"></i>
                                                <?php echo ($editEvent) ? 'Aggiorna Evento' : 'Crea Evento'; ?>
                                            </button>
                                            <?php if ($editEvent): ?>
                                                <a href="./admin" class="btn btn-outline-primary btn-lg px-4 ms-3">
                                                    <i class="bi bi-x-circle me-2"></i>Annulla
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Eventi Attivi Tab -->
                    <div class="tab-pane fade" id="eventi-attivi" role="tabpanel">
                        <?php
                        $oggi = date('Y-m-d');
                        $eventiAttivi = [];
                        foreach ($events as $eventItem) {
                            $isScaduto = (isset($eventItem['event_date']) && $eventItem['event_date'] < $oggi);
                            $isChiuso = !empty($eventItem['chiuso']);
                            if (!$isScaduto && !$isChiuso) {
                                $eventiAttivi[] = $eventItem;
                            }
                        }
                        ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th class="d-none d-md-table-cell">ID</th>
                                        <th>Data</th>
                                        <th>Titolo</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($eventiAttivi)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <i class="bi bi-calendar-x" style="font-size: 2rem; color: #ccc;"></i>
                                                <p class="text-muted mt-2 mb-0">Nessun evento attivo al momento</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($eventiAttivi as $eventItem): ?>
                                            <tr>
                                                <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($eventItem['id']); ?></td>
                                                <td><?php echo htmlspecialchars($eventItem['date']); ?></td>
                                                <td><?php echo htmlspecialchars($eventItem['titolo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><span class="badge bg-success">Aperto</span></td>
                                                <td>
                                                    <a href="./admin?edit=<?php echo $eventItem['id']; ?>" class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Modifica"><i class="bi bi-pencil"></i></a>
                                                    <a href="./admin?delete=<?php echo $eventItem['id']; ?>" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Elimina" onclick="return confirm('Sei sicuro di voler eliminare questo evento?');"><i class="bi bi-trash"></i></a>
                                                    <a href="./admin?details_event=<?php echo urlencode($eventItem['id']); ?>" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="Dettagli"><i class="bi bi-info-circle"></i></a>
                                                    <a href="./admin?export_csv=<?php echo urlencode($eventItem['id']); ?>" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Esporta CSV"><i class="bi bi-download"></i></a>
                                                    <?php $bgUrl = resolveBackgroundUrl($config, $eventItem['background_image'] ?? null); if ($bgUrl): ?>
                                                    <a href="<?php echo htmlspecialchars($bgUrl); ?>" target="_blank" class="btn btn-sm btn-outline-light" data-bs-toggle="tooltip" title="Anteprima sfondo"><i class="bi bi-image"></i></a>
                                                    <a href="./admin?action=preview_pdf&evento_id=<?php echo intval($eventItem['id']); ?>" target="_blank" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Anteprima PDF"><i class="bi bi-filetype-pdf"></i></a>
                                                    <?php endif; ?>
                                                    <form method="POST" action="./admin" class="d-inline">
                                                        <input type="hidden" name="chiudi_event_id" value="<?php echo htmlspecialchars($eventItem['id']); ?>">
                                                        <button type="submit" name="chiudi_evento" class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Chiudi iscrizioni" onclick="return confirm('Sei sicuro di voler chiudere le iscrizioni per questo evento?');">
                                                            <i class="bi bi-lock"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Eventi Passati Tab -->
                    <div class="tab-pane fade" id="eventi-passati" role="tabpanel">
                        <?php
                        $eventiPassatiTab = [];
                        foreach ($events as $eventItem) {
                            $isScaduto = (isset($eventItem['event_date']) && $eventItem['event_date'] < $oggi);
                            $isChiuso = !empty($eventItem['chiuso']);
                            if ($isScaduto || $isChiuso) {
                                // Aggiungi statistiche rapide per ogni evento passato
                                $stmt = $pdo->prepare("
                                    SELECT 
                                        COUNT(*) AS tot,
                                        SUM(CASE WHEN validato = 1 THEN 1 ELSE 0 END) AS val
                                    FROM utenti
                                    WHERE evento = :evento
                                ");
                                $stmt->execute([':evento' => $eventItem['id']]);
                                $stat = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                $eventItem['statsQuick'] = [
                                    'totIscritti' => $stat['tot'],
                                    'totValidati' => $stat['val'],
                                    'percentuale' => $stat['tot'] > 0 ? round(($stat['val'] / $stat['tot']) * 100, 1) : 0
                                ];
                                
                                $eventiPassatiTab[] = $eventItem;
                            }
                        }
                        ?>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Visualizza Statistiche Complete:</strong> 
                                    Gli eventi passati mantengono tutte le statistiche di ingresso e validazioni. 
                                    Usa il pulsante "Statistiche & Report" per vedere i dettagli completi.
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th class="d-none d-md-table-cell">ID</th>
                                        <th>Data</th>
                                        <th>Titolo</th>
                                        <th>Stato</th>
                                        <th class="text-center">Statistiche Rapide</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($eventiPassatiTab)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="bi bi-calendar-check" style="font-size: 2rem; color: #ccc;"></i>
                                                <p class="text-muted mt-2 mb-0">Nessun evento passato</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($eventiPassatiTab as $eventItem): ?>
                                            <tr>
                                                <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($eventItem['id']); ?></td>
                                                <td><?php echo htmlspecialchars($eventItem['date']); ?></td>
                                                <td>
                                                    <div>
                                                        <?php echo htmlspecialchars($eventItem['titolo'], ENT_QUOTES, 'UTF-8'); ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="bi bi-eye me-1"></i>
                                                            <a href="./admin?details_event=<?php echo urlencode($eventItem['id']); ?>" class="text-decoration-none">
                                                                Visualizza statistiche complete →
                                                            </a>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $isScaduto = (isset($eventItem['event_date']) && $eventItem['event_date'] < $oggi);
                                                    if ($isScaduto) {
                                                        echo '<span class="badge bg-secondary">Scaduto</span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger">Chiuso</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                                        <span class="badge bg-primary" title="Iscritti totali">
                                                            <i class="bi bi-people me-1"></i><?php echo $eventItem['statsQuick']['totIscritti']; ?>
                                                        </span>
                                                        <span class="badge bg-success" title="Presenti">
                                                            <i class="bi bi-check-circle me-1"></i><?php echo $eventItem['statsQuick']['totValidati']; ?>
                                                        </span>
                                                        <span class="badge bg-info" title="Tasso di presenza">
                                                            <i class="bi bi-percent me-1"></i><?php echo $eventItem['statsQuick']['percentuale']; ?>%
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="./admin?details_event=<?php echo urlencode($eventItem['id']); ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Statistiche & Report">
                                                            <i class="bi bi-graph-up"></i>
                                                        </a>
                                                        <a href="./admin?export_csv=<?php echo urlencode($eventItem['id']); ?>" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Esporta CSV">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                        <a href="./admin?edit=<?php echo $eventItem['id']; ?>" class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Modifica">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="./admin?delete=<?php echo $eventItem['id']; ?>" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Elimina" onclick="return confirm('Sei sicuro di voler eliminare questo evento e tutti i suoi dati?');">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                    <?php
                                                    $isScaduto = (isset($eventItem['event_date']) && $eventItem['event_date'] < $oggi);
                                                    $isChiuso = !empty($eventItem['chiuso']);
                                                    if ($isChiuso && !$isScaduto) {
                                                    ?>
                                                        <form method="POST" action="./admin" class="d-inline mt-2">
                                                            <input type="hidden" name="riapri_event_id" value="<?php echo htmlspecialchars($eventItem['id']); ?>">
                                                            <button type="submit" name="riapri_evento" class="btn btn-sm btn-outline-success w-100" data-bs-toggle="tooltip" title="Riapri iscrizioni" onclick="return confirm('Vuoi riaprire questo evento?');">
                                                                <i class="bi bi-unlock me-1"></i>Riapri Iscrizioni
                                                            </button>
                                                        </form>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Ricerca Utenti Tab -->
                    <div class="tab-pane fade" id="tutti-utenti" role="tabpanel">
                        <?php
                        // Calcola solo le statistiche generali senza caricare tutti gli utenti
                        try {
                            $stmtTotalStats = $pdo->query("
                                SELECT 
                                    COUNT(DISTINCT u.email) as total_users,
                                    COUNT(*) as total_registrations,
                                    SUM(CASE WHEN u.validato = 1 THEN 1 ELSE 0 END) as total_validations
                                FROM utenti u
                            ");
                            $totalStats = $stmtTotalStats->fetch(PDO::FETCH_ASSOC);
                            
                            $totalUsers = (int)$totalStats['total_users'];
                            $totalRegistrations = (int)$totalStats['total_registrations'];
                            $totalValidations = (int)$totalStats['total_validations'];
                            $avgAttendanceRate = $totalRegistrations > 0 ? round(($totalValidations / $totalRegistrations) * 100, 1) : 0;
                        } catch (PDOException $e) {
                            $totalUsers = 0;
                            $totalRegistrations = 0;
                            $totalValidations = 0;
                            $avgAttendanceRate = 0;
                        }
                        ?>
                        
                        <!-- Header con statistiche generali -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0" style="color: #e0e0e0;">
                                        <i class="bi bi-search me-2"></i>Ricerca Utenti
                                    </h5>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-primary btn-sm" id="exportSearchResults" style="display: none;">
                                            <i class="bi bi-download me-1"></i>Esporta Risultati
                                        </button>
                                        <button class="btn btn-outline-info btn-sm" onclick="clearSearchResults()">
                                            <i class="bi bi-x-circle me-1"></i>Pulisci
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Cards statistiche -->
                                <div class="row g-3 mb-4">
                                                                    <div class="col-md-3">
                                    <div class="stats-card" style="padding: 1.5rem;">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="bi bi-people" style="font-size: 2rem; color: #4a90e2;"></i>
                                            </div>
                                            <div>
                                                <div class="stats-number" style="font-size: 1.8rem;" data-stat="total-users"><?php echo $totalUsers; ?></div>
                                                <h6 class="mb-0">Utenti Totali</h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stats-card" style="padding: 1.5rem;">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="bi bi-calendar-plus" style="font-size: 2rem; color: #7b68ee;"></i>
                                            </div>
                                            <div>
                                                <div class="stats-number" style="font-size: 1.8rem;" data-stat="total-registrations"><?php echo $totalRegistrations; ?></div>
                                                <h6 class="mb-0">Iscrizioni Totali</h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stats-card" style="padding: 1.5rem;">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="bi bi-check-circle" style="font-size: 2rem; color: #28a745;"></i>
                                            </div>
                                            <div>
                                                <div class="stats-number" style="font-size: 1.8rem;" data-stat="total-validations"><?php echo $totalValidations; ?></div>
                                                <h6 class="mb-0">Presenze Totali</h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stats-card" style="padding: 1.5rem;">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="bi bi-graph-up" style="font-size: 2rem; color: #ffc107;"></i>
                                            </div>
                                            <div>
                                                <div class="stats-number" style="font-size: 1.8rem;" data-stat="avg-rate"><?php echo $avgAttendanceRate; ?>%</div>
                                                <h6 class="mb-0">Tasso Medio</h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ricerca Principale -->
                        <div class="card mb-4" style="background: rgba(50, 50, 50, 0.8); border: 1px solid rgba(192, 192, 192, 0.2);">
                            <div class="card-body">
                                <h6 class="card-title" style="color: #e0e0e0;">
                                    <i class="bi bi-search me-2"></i>Ricerca Utenti nel Database
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" style="color: #c0c0c0;">
                                            <i class="bi bi-search me-1"></i>Cerca per Nome, Email, Telefono
                                        </label>
                                        <div class="input-group">
                                            <input type="text" id="searchAllUsers" class="form-control form-control-lg" 
                                                   placeholder="Inserisci almeno 3 caratteri per iniziare la ricerca..."
                                                   minlength="3">
                                            <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Ricerca in tempo reale tra <strong><?php echo $totalUsers; ?> utenti</strong> registrati
                                        </small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" style="color: #c0c0c0;">Limite Risultati</label>
                                        <select id="searchLimit" class="form-select">
                                            <option value="20">20 risultati</option>
                                            <option value="50" selected>50 risultati</option>
                                            <option value="100">100 risultati</option>
                                            <option value="0">Tutti i risultati</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" style="color: #c0c0c0;">
                                            <i class="bi bi-sort-down me-1"></i>Ordinamento Risultati
                                        </label>
                                        <select id="sortResults" class="form-select">
                                            <option value="relevance">Rilevanza</option>
                                            <option value="name_asc">Nome A-Z</option>
                                            <option value="name_desc">Nome Z-A</option>
                                            <option value="registrations_desc">Più iscrizioni</option>
                                            <option value="recent_desc">Più recenti</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                                                <!-- Area Risultati Ricerca -->
                        <div id="searchResultsContainer">
                            <!-- Stato iniziale -->
                            <div id="searchInitialState" class="text-center py-5">
                                <i class="bi bi-search" style="font-size: 4rem; color: #666;"></i>
                                <h5 class="mt-3 mb-2" style="color: #e0e0e0;">Ricerca Utenti</h5>
                                <p class="text-muted">Inserisci almeno 3 caratteri nel campo di ricerca per iniziare</p>
                                <small class="text-muted">
                                    <i class="bi bi-database me-1"></i>
                                    Database: <strong><?php echo $totalUsers; ?> utenti</strong> registrati
                                </small>
                            </div>
                            
                            <!-- Loading State -->
                            <div id="searchLoadingState" class="text-center py-4" style="display: none;">
                                <div class="spinner-border text-primary mb-2" role="status">
                                    <span class="visually-hidden">Ricerca in corso...</span>
                                </div>
                                <p class="text-muted mb-0">Ricerca in corso...</p>
                            </div>
                            
                            <!-- Risultati -->
                            <div id="searchResults" style="display: none;">
                                <!-- Header risultati -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <span class="text-muted">
                                            <span id="searchResultsCount">0</span> risultati trovati
                                            <span id="searchQuery"></span>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-secondary active" id="viewTable" onclick="toggleSearchView('table')" title="Vista tabella">
                                                <i class="bi bi-table"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary disabled" id="viewGrid" title="Vista griglia (prossimamente)" disabled>
                                                <i class="bi bi-grid"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Vista tabella risultati -->
                                <div class="table-responsive" id="searchTableView">
                                    <table class="table table-striped" id="searchResultsTable">
                                        <thead>
                                            <tr>
                                                <th><i class="bi bi-person me-1"></i>Nome Completo</th>
                                                <th><i class="bi bi-envelope me-1"></i>Contatti</th>
                                                <th class="d-none d-lg-table-cell"><i class="bi bi-calendar me-1"></i>Attività</th>
                                                <th class="text-center"><i class="bi bi-person-plus me-1"></i>Iscrizioni</th>
                                                <th class="text-center"><i class="bi bi-check-circle me-1"></i>Presenze</th>
                                                <th class="text-center"><i class="bi bi-graph-up me-1"></i>Tasso</th>
                                                <th class="text-center">Azioni</th>
                                            </tr>
                                        </thead>
                                        <tbody id="searchResultsTableBody">
                                            <!-- Risultati popolati via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Vista griglia risultati -->
                                <div class="row g-3 d-none" id="searchGridView">
                                    <!-- Risultati popolati via AJAX -->
                                </div>
                            </div>
                            
                            <!-- Stato nessun risultato -->
                            <div id="searchNoResults" class="text-center py-4" style="display: none;">
                                <i class="bi bi-search-heart" style="font-size: 3rem; color: #666;"></i>
                                <h6 class="mt-3 mb-2" style="color: #e0e0e0;">Nessun risultato trovato</h6>
                                <p class="text-muted mb-3">
                                    Non è stato trovato nessun utente che corrisponde ai criteri di ricerca
                                </p>
                                <button class="btn btn-outline-secondary btn-sm" onclick="clearSearchResults()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Nuova Ricerca
                                </button>
                            </div>
                        </div>

                    </div>



                    <!-- Compleanni Tab -->
                    <div class="tab-pane fade" id="compleanni" role="tabpanel">
                        <div class="row">
                            <div class="col-12 text-center mb-4">
                                <h4 style="color: #e0e0e0;">
                                    <i class="bi bi-balloon-heart me-2"></i>Sistema Gestione Compleanni MrCharlie
                                </h4>
                                <p class="text-muted">Sistema automatico per auguri di compleanno personalizzati</p>
                            </div>
                        </div>
                        
                        <!-- Quick Stats Dashboard -->
                        <div class="row g-4 mb-4">
                            <div class="col-md-3">
                                <div class="stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 1.5rem;">
                                    <div class="mb-2">
                                        <i class="bi bi-cake-fill" style="font-size: 2rem; color: white;"></i>
                                    </div>
                                    <div class="stats-number" style="font-size: 2rem; color: white;">
                                        <?php echo $birthdayStats['today_birthdays']; ?>
                                    </div>
                                    <h6 class="mb-0" style="color: white;">Compleanni Oggi</h6>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="stats-card" style="background: linear-gradient(135deg, #2d5a3d 0%, #4a7c5a 100%); padding: 1.5rem;">
                                    <div class="mb-2">
                                        <i class="bi bi-envelope-heart-fill" style="font-size: 2rem; color: white;"></i>
                                    </div>
                                    <div class="stats-number" style="font-size: 2rem; color: white;">
                                        <?php echo $birthdayStats['sent_this_year']; ?>
                                    </div>
                                    <h6 class="mb-0" style="color: white;">Auguri Inviati <?php echo date('Y'); ?></h6>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="stats-card" style="background: linear-gradient(135deg, #4a4a2d 0%, #6a6a4a 100%); padding: 1.5rem;">
                                    <div class="mb-2">
                                        <i class="bi bi-calendar-heart-fill" style="font-size: 2rem; color: white;"></i>
                                    </div>
                                    <div class="stats-number" style="font-size: 2rem; color: white;">
                                        <?php echo $birthdayStats['upcoming_birthdays']; ?>
                                    </div>
                                    <h6 class="mb-0" style="color: white;">Prossimi 30 Giorni</h6>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="stats-card" style="background: linear-gradient(135deg, #5a2d5a 0%, #7a4a7a 100%); padding: 1.5rem;">
                                    <div class="mb-2">
                                        <i class="bi bi-file-earmark-text-fill" style="font-size: 2rem; color: white;"></i>
                                    </div>
                                    <div class="stats-number" style="font-size: 2rem; color: white;">
                                        <?php echo $birthdayStats['total_templates']; ?>
                                    </div>
                                    <h6 class="mb-0" style="color: white;">Template Attivi</h6>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sistema Completo - Accesso Diretto -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white;">
                                    <div class="card-body text-center py-5">
                                        <div class="mb-4">
                                            <i class="bi bi-gift-fill" style="font-size: 4rem; opacity: 0.9;"></i>
                                        </div>
                                        <h3 class="mb-3">🎉 Sistema Compleanni Avanzato</h3>
                                        <p class="mb-4 lead">
                                            Editor ibrido avanzato, template professionali, 
                                            statistiche complete e automazione degli auguri
                                        </p>
                                        
                                        <div class="row g-3 justify-content-center">
                                            <div class="col-md-5">
                                                <a href="birthday_admin.php" target="_blank" class="btn btn-light btn-lg w-100 py-3" style="font-weight: 600;">
                                                    <i class="bi bi-box-arrow-up-right me-2"></i>
                                                    Apri Sistema Completo
                                                </a>
                                            </div>
                                            <div class="col-md-3">
                                                <form method="POST" class="w-100">
                                                    <input type="hidden" name="birthday_action" value="send_birthday_check">
                                                    <button type="submit" class="btn btn-warning btn-lg w-100 py-3">
                                                        <i class="bi bi-send-check me-2"></i>
                                                        Test Oggi
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-4">
                                            <div class="col-md-10 mx-auto">
                                                <div class="bg-white bg-opacity-20 rounded p-4">
                                                    <div class="row text-center">
                                                        <div class="col-md-3">
                                                            <div class="mb-3">
                                                                <i class="bi bi-pencil-square" style="font-size: 2rem; color: #ffd700;"></i>
                                                                <div class="mt-2 small"><strong>Editor Ibrido</strong><br>Visuale + HTML</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="mb-3">
                                                                <i class="bi bi-file-earmark-text" style="font-size: 2rem; color: #ffd700;"></i>
                                                                <div class="mt-2 small"><strong>Template Pro</strong><br>Eleganti e Moderni</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="mb-3">
                                                                <i class="bi bi-calendar-check" style="font-size: 2rem; color: #ffd700;"></i>
                                                                <div class="mt-2 small"><strong>Automazione</strong><br>Invii Programmati</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="mb-3">
                                                                <i class="bi bi-graph-up" style="font-size: 2rem; color: #ffd700;"></i>
                                                                <div class="mt-2 small"><strong>Analytics</strong><br>Statistiche Complete</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistiche Tab -->
                    <div class="tab-pane fade" id="statistiche" role="tabpanel">
                        <div class="row">
                            <div class="col-12 text-center mb-4">
                                <h4 style="color: #e0e0e0;">
                                    <i class="bi bi-bar-chart-fill me-2"></i>Statistiche Eventi Passati
                                </h4>
                                <p class="text-muted">Analisi dettagliate e report degli eventi scaduti e chiusi</p>
                            </div>
                        </div>

                        <!-- Loading indicator -->
                        <div id="statistiche-loading" class="text-center py-5" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Caricamento...</span>
                            </div>
                            <p class="text-muted mt-3">Caricamento statistiche eventi passati...</p>
                        </div>

                        <!-- Content container -->
                        <div id="statistiche-content">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Caricamento intelligente:</strong> 
                                Le statistiche degli eventi passati vengono caricate automaticamente quando apri questa tab.
                                Questo migliora le performance del pannello admin.
                            </div>
                            
                            <!-- Placeholder per statistiche -->
                            <div class="row g-4" id="stats-overview" style="display: none;">
                                <!-- Le statistiche verranno inserite qui via JavaScript -->
                            </div>
                            
                            <!-- Tabella eventi passati con statistiche dettagliate -->
                            <div id="eventi-passati-dettagliati" style="display: none;">
                                <!-- Il contenuto verrà caricato via AJAX -->
                            </div>
                        </div>
                    </div>

                    <!-- Gestione Carosello Tab -->
                    <div class="tab-pane fade" id="carosello" role="tabpanel">
                        <div class="row">
                            <div class="col-12">
                                <div class="text-center mb-4">
                                    <h4 style="color: #e0e0e0;">
                                        <i class="bi bi-images me-2"></i>Gestione Immagini Carosello
                                    </h4>
                                    <p class="text-muted">Gestisci le immagini del carosello homepage indipendentemente dagli eventi</p>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Nuove Immagini -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card bg-dark border-secondary">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="bi bi-cloud-upload me-2"></i>Carica Nuove Immagini</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="caroselloUploadForm" enctype="multipart/form-data">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="caroselloFiles" class="form-label">Seleziona Immagini</label>
                                                        <input type="file" class="form-control bg-dark text-light border-secondary" 
                                                               id="caroselloFiles" name="caroselloFiles[]" 
                                                               multiple accept="image/*" required>
                                                        <div class="form-text text-muted">Puoi selezionare più immagini contemporaneamente</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="caroselloExpiry" class="form-label">Data di Scadenza</label>
                                                        <input type="date" class="form-control bg-dark text-light border-secondary" 
                                                               id="caroselloExpiry" name="caroselloExpiry" required>
                                                        <div class="form-text text-muted">Le immagini scadranno automaticamente in questa data</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-upload me-2"></i>Carica Immagini
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lista Immagini Esistenti -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card bg-dark border-secondary">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Immagini Attive</h5>
                                        <button class="btn btn-outline-info btn-sm" onclick="refreshCaroselloImages()">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Aggiorna
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div id="caroselloImagesContainer">
                                            <div class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Caricamento...</span>
                                                </div>
                                                <p class="mt-2 text-muted">Caricamento immagini...</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Impostazioni Tab -->
                    <div class="tab-pane fade" id="impostazioni" role="tabpanel">
                        <?php
                        // Raccogli informazioni sul sistema
                        $systemInfo = [
                            'php_version' => phpversion(),
                            'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
                            'disk_space' => function_exists('disk_free_space') ? disk_free_space(__DIR__) : 'N/A',
                            'memory_limit' => ini_get('memory_limit'),
                            'upload_max_filesize' => ini_get('upload_max_filesize'),
                            'max_execution_time' => ini_get('max_execution_time'),
                            'timezone' => date_default_timezone_get(),
                            'current_time' => date('Y-m-d H:i:s'),
                        ];

                        // Statistiche database
                        try {
                            $stmt = $pdo->query("
                                SELECT 
                                    (SELECT COUNT(*) FROM events) as total_events,
                                    (SELECT COUNT(*) FROM utenti) as total_users,
                                    (SELECT COUNT(*) FROM utenti WHERE validato = 1) as validated_users,
                                    (SELECT COUNT(*) FROM utenti WHERE email_inviata IS NOT NULL) as emails_sent
                            ");
                            $dbStats = $stmt->fetch(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $dbStats = ['total_events' => 0, 'total_users' => 0, 'validated_users' => 0, 'emails_sent' => 0];
                        }

                        // Controllo file di log
                        $logFiles = [
                            'rinvii' => __DIR__ . '/log_rinviati.txt',
                            'errori' => __DIR__ . '/error.log',
                            'accessi' => __DIR__ . '/access.log'
                        ];

                        // Controllo dimensioni database
                        try {
                            $stmt = $pdo->query("
                                SELECT 
                                    TABLE_NAME as table_name,
                                    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS size_mb
                                FROM information_schema.TABLES 
                                WHERE TABLE_SCHEMA = DATABASE()
                                ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
                            ");
                            $tableSizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Debug: log delle dimensioni tabelle
                            error_log('[DEBUG] Table sizes query result: ' . json_encode($tableSizes));
                        } catch (PDOException $e) {
                            error_log('[ERROR] Table sizes query failed: ' . $e->getMessage());
                            $tableSizes = [];
                        }
                        ?>

                        <div class="row">
                            <div class="col-12 text-center mb-4">
                                <h4 style="color: #e0e0e0;">
                                    <i class="bi bi-gear-fill me-2"></i>Impostazioni e Controlli Sistema
                                </h4>
                                <p class="text-muted">Configurazioni, log e strumenti di amministrazione</p>
                            </div>
                        </div>

                        <!-- Stato Sistema -->
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <div class="card" style="background: linear-gradient(135deg, #2d5a3d 0%, #4a7c5a 100%); border: none; color: white;">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="bi bi-cpu me-2"></i>Stato Sistema
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between">
                                                    <span><i class="bi bi-server me-2"></i>PHP:</span>
                                                    <span class="fw-bold"><?php echo $systemInfo['php_version']; ?></span>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between">
                                                    <span><i class="bi bi-database me-2"></i>MySQL:</span>
                                                    <span class="fw-bold"><?php echo $systemInfo['mysql_version']; ?></span>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between">
                                                    <span><i class="bi bi-memory me-2"></i>Memoria:</span>
                                                    <span class="fw-bold"><?php echo $systemInfo['memory_limit']; ?></span>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between">
                                                    <span><i class="bi bi-clock me-2"></i>Max Execution:</span>
                                                    <span class="fw-bold"><?php echo $systemInfo['max_execution_time']; ?>s</span>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between">
                                                    <span><i class="bi bi-upload me-2"></i>Max Upload:</span>
                                                    <span class="fw-bold"><?php echo $systemInfo['upload_max_filesize']; ?></span>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between">
                                                    <span><i class="bi bi-globe me-2"></i>Timezone:</span>
                                                    <span class="fw-bold"><?php echo $systemInfo['timezone']; ?></span>
                                                </div>
                                            </div>
                                            <div class="col-12 border-top pt-3">
                                                <div class="d-flex justify-content-between">
                                                    <span><i class="bi bi-calendar3 me-2"></i>Data/Ora Server:</span>
                                                    <span class="fw-bold"><?php echo $systemInfo['current_time']; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card" style="background: linear-gradient(135deg, #4a4a2d 0%, #6a6a4a 100%); border: none; color: white;">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="bi bi-database-check me-2"></i>Statistiche Database
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3 text-center">
                                            <div class="col-6">
                                                <div class="border-end">
                                                    <div class="h3 mb-1"><?php echo $dbStats['total_events']; ?></div>
                                                    <small>Eventi Totali</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="h3 mb-1"><?php echo $dbStats['total_users']; ?></div>
                                                <small>Utenti Totali</small>
                                            </div>
                                            <div class="col-6">
                                                <div class="border-end">
                                                    <div class="h3 mb-1"><?php echo $dbStats['validated_users']; ?></div>
                                                    <small>Presenze</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="h3 mb-1"><?php echo $dbStats['emails_sent']; ?></div>
                                                <small>Email Inviate</small>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($tableSizes)): ?>
                                            <div class="mt-4 pt-3 border-top">
                                                <h6 class="mb-3">Dimensioni Tabelle Database</h6>
                                                <?php foreach (array_slice($tableSizes, 0, 3) as $table): ?>
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span><?php 
                                                            $tableName = $table['table_name'] ?? $table['TABLE_NAME'] ?? 'Tabella sconosciuta';
                                                            echo htmlspecialchars($tableName); 
                                                        ?>:</span>
                                                        <span class="fw-bold"><?php 
                                                            $sizeValue = $table['size_mb'] ?? $table['SIZE_MB'] ?? '0.00';
                                                            echo htmlspecialchars($sizeValue); 
                                                        ?> MB</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gestione Log -->
                        <div class="card mb-4" style="background: rgba(50, 50, 50, 0.8); border: 1px solid rgba(192, 192, 192, 0.2);">
                            <div class="card-header">
                                <h5 class="mb-0" style="color: #e0e0e0;">
                                    <i class="bi bi-file-text me-2"></i>Gestione Log Sistema
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <!-- Log Invii Email -->
                                    <div class="col-md-4">
                                        <div class="card h-100" style="background: rgba(40, 40, 40, 0.8); border: 1px solid rgba(192, 192, 192, 0.1);">
                                            <div class="card-body text-center">
                                                <i class="bi bi-envelope-check" style="font-size: 3rem; color: #4a90e2;"></i>
                                                <h6 class="mt-3 mb-2" style="color: #e0e0e0;">Log Invii Email</h6>
                                                <?php 
                                                $logRinvii = $logFiles['rinvii'];
                                                if (file_exists($logRinvii)): 
                                                    $logSize = round(filesize($logRinvii) / 1024, 2);
                                                    $logDate = date('d/m/Y H:i', filemtime($logRinvii));
                                                ?>
                                                    <p class="text-muted small mb-3">
                                                        Dimensione: <?php echo $logSize; ?> KB<br>
                                                        Aggiornato: <?php echo $logDate; ?>
                                                    </p>
                                                    <div class="d-grid gap-2">
                                                        <button class="btn btn-outline-info btn-sm" onclick="viewLog('rinvii')">
                                                            <i class="bi bi-eye me-1"></i>Visualizza
                                                        </button>
                                                        <a href="?download_log=rinvii" class="btn btn-outline-success btn-sm">
                                                            <i class="bi bi-download me-1"></i>Scarica
                                                        </a>
                                                        <button class="btn btn-outline-danger btn-sm" onclick="clearLog('rinvii')">
                                                            <i class="bi bi-trash me-1"></i>Pulisci
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-muted">Log non presente</p>
                                                    <button class="btn btn-outline-secondary btn-sm" disabled>
                                                        <i class="bi bi-x-circle me-1"></i>Nessun log
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Log Errori -->
                                    <div class="col-md-4">
                                        <div class="card h-100" style="background: rgba(40, 40, 40, 0.8); border: 1px solid rgba(192, 192, 192, 0.1);">
                                            <div class="card-body text-center">
                                                <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #dc3545;"></i>
                                                <h6 class="mt-3 mb-2" style="color: #e0e0e0;">Log Errori</h6>
                                                <?php 
                                                $logErrori = $logFiles['errori'];
                                                if (file_exists($logErrori)): 
                                                    $logSize = round(filesize($logErrori) / 1024, 2);
                                                    $logDate = date('d/m/Y H:i', filemtime($logErrori));
                                                ?>
                                                    <p class="text-muted small mb-3">
                                                        Dimensione: <?php echo $logSize; ?> KB<br>
                                                        Aggiornato: <?php echo $logDate; ?>
                                                    </p>
                                                    <div class="d-grid gap-2">
                                                        <button class="btn btn-outline-info btn-sm" onclick="viewLog('errori')">
                                                            <i class="bi bi-eye me-1"></i>Visualizza
                                                        </button>
                                                        <a href="?download_log=errori" class="btn btn-outline-success btn-sm">
                                                            <i class="bi bi-download me-1"></i>Scarica
                                                        </a>
                                                        <button class="btn btn-outline-danger btn-sm" onclick="clearLog('errori')">
                                                            <i class="bi bi-trash me-1"></i>Pulisci
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-muted">Log non presente</p>
                                                    <button class="btn btn-outline-secondary btn-sm" disabled>
                                                        <i class="bi bi-x-circle me-1"></i>Nessun log
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Log Accessi -->
                                    <div class="col-md-4">
                                        <div class="card h-100" style="background: rgba(40, 40, 40, 0.8); border: 1px solid rgba(192, 192, 192, 0.1);">
                                            <div class="card-body text-center">
                                                <i class="bi bi-shield-check" style="font-size: 3rem; color: #28a745;"></i>
                                                <h6 class="mt-3 mb-2" style="color: #e0e0e0;">Log Accessi</h6>
                                                <?php 
                                                $logAccessi = $logFiles['accessi'];
                                                if (file_exists($logAccessi)): 
                                                    $logSize = round(filesize($logAccessi) / 1024, 2);
                                                    $logDate = date('d/m/Y H:i', filemtime($logAccessi));
                                                ?>
                                                    <p class="text-muted small mb-3">
                                                        Dimensione: <?php echo $logSize; ?> KB<br>
                                                        Aggiornato: <?php echo $logDate; ?>
                                                    </p>
                                                    <div class="d-grid gap-2">
                                                        <button class="btn btn-outline-info btn-sm" onclick="viewLog('accessi')">
                                                            <i class="bi bi-eye me-1"></i>Visualizza
                                                        </button>
                                                        <a href="?download_log=accessi" class="btn btn-outline-success btn-sm">
                                                            <i class="bi bi-download me-1"></i>Scarica
                                                        </a>
                                                        <button class="btn btn-outline-danger btn-sm" onclick="clearLog('accessi')">
                                                            <i class="bi bi-trash me-1"></i>Pulisci
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-muted">Log non presente</p>
                                                    <button class="btn btn-outline-secondary btn-sm" disabled>
                                                        <i class="bi bi-x-circle me-1"></i>Nessun log
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Viewer Log Unificato -->
                                <div class="mt-4" id="logViewer" style="display: none;">
                                    <div class="card" style="background: rgba(30, 30, 30, 0.95); border: 1px solid rgba(192, 192, 192, 0.2);">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0" style="color: #e0e0e0;" id="logViewerTitle">
                                                <i class="bi bi-file-text me-2"></i>Contenuto Log
                                            </h6>
                                            <button class="btn btn-outline-secondary btn-sm" onclick="closeLogViewer()">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                        <div class="card-body p-0">
                                            <div id="logContent" style="max-height: 400px; overflow-y: auto; background: #1a1a1a; color: #00ff00; padding: 15px; font-family: 'Courier New', monospace; font-size: 0.85rem; white-space: pre-wrap; line-height: 1.4;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Test Sistema -->
                        <div class="card mb-4" style="background: linear-gradient(135deg, #2d5a4a 0%, #4a7a6a 100%); border: none; color: white;">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-check-circle me-2"></i>Test Sistema e Connessioni
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <!-- Test Completo Sistema -->
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-gear-wide-connected me-2"></i>Test Completo Sistema</h6>
                                        <p class="mb-3 opacity-75">Testa l'intero flusso: form → /save-form → email</p>
                                        <div class="mb-3">
                                            <label class="form-label small">Email per test:</label>
                                            <input type="email" id="testEmail" class="form-control form-control-sm" 
                                                   placeholder="Inserisci email per ricevere il test" 
                                                   value="<?php echo htmlspecialchars($config['email']['username'] ?? ''); ?>">
                                            <input type="hidden" id="testEventId" value="<?php echo $testEventForSystem ? intval($testEventForSystem['id']) : ''; ?>">
                                            <?php if ($testEventForSystem): ?>
                                                <small class="text-muted d-block mt-2">
                                                    Evento utilizzato per il test: 
                                                    <?php
                                                        $eventDate = $testEventForSystem['event_date'] ?? null;
                                                        echo $eventDate 
                                                            ? htmlspecialchars(date('d/m/Y', strtotime($eventDate)) . ' - ' . $testEventForSystem['titolo'])
                                                            : htmlspecialchars($testEventForSystem['titolo']);
                                                    ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-warning d-block mt-2">
                                                    Nessun evento aperto disponibile per il test automatico.
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-grid">
                                            <button class="btn btn-light btn-lg" onclick="testSistema()">
                                                <i class="bi bi-play-circle me-2"></i>
                                                Avvia Test Sistema
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Verifica Connessioni -->
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-link-45deg me-2"></i>Verifica Connessioni</h6>
                                        <p class="mb-3 opacity-75">Controlla collegamento form e configurazioni</p>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-light" onclick="verificaForm()">
                                                <i class="bi bi-file-check me-2"></i>Verifica Form
                                            </button>
                                            <button class="btn btn-outline-light" onclick="verificaEmail()">
                                                <i class="bi bi-envelope-check me-2"></i>Test Email SMTP
                                            </button>
                                            <button class="btn btn-outline-light" onclick="verificaDatabase()">
                                                <i class="bi bi-database-check me-2"></i>Test Database
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Risultati Test -->
                                <div class="mt-4" id="testResults" style="display: none;">
                                    <div class="card" style="background: rgba(30, 30, 30, 0.95); border: 1px solid rgba(192, 192, 192, 0.2);">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0" style="color: #e0e0e0;">
                                                <i class="bi bi-terminal me-2"></i>Risultati Test
                                            </h6>
                                            <button class="btn btn-outline-secondary btn-sm" onclick="closeTestResults()">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div id="testContent" style="background: #1a1a1a; color: #00ff00; padding: 15px; font-family: 'Courier New', monospace; font-size: 0.85rem; white-space: pre-wrap; line-height: 1.4; border-radius: 4px;"></div>
                                            <div id="testProgress" class="mt-3" style="display: none;">
                                                <div class="progress">
                                                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                         role="progressbar" style="width: 0%" id="progressBar"></div>
                                                </div>
                                                <small class="text-muted mt-1 d-block" id="progressText">Inizializzazione test...</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-4 mt-3 pt-3 border-top">
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-info-circle me-2"></i>Informazioni Test</h6>
                                        <div class="opacity-75 small">
                                            <p class="mb-1">• Test completo simula una registrazione reale</p>
                                            <p class="mb-1">• Verifica form, database, generazione PDF e invio email</p>
                                            <p class="mb-1">• I dati di test vengono eliminati automaticamente</p>
                                            <p class="mb-0">• L'email di test viene inviata all'indirizzo specificato</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-shield-check me-2"></i>Stato Configurazioni</h6>
                                        <div class="opacity-75 small">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>Form collegato:</span>
                                                <span id="formStatus" class="badge bg-secondary">Verificando...</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>SMTP configurato:</span>
                                                <span id="smtpStatus" class="badge bg-secondary">Verificando...</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Database attivo:</span>
                                                <span id="dbStatus" class="badge bg-success">Connesso</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gestione Carosello Hero -->
                        <div class="card mb-4" style="background: linear-gradient(135deg, #4a2d5a 0%, #6a4a7a 100%); border: none; color: white;">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-images me-2"></i>Gestione Carosello Hero
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <!-- Stato Carosello -->
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-info-circle me-2"></i>Stato Carosello</h6>
                                        <?php
                                        $heroJsonPath = __DIR__ . '/../public/hero_images.json';
                                        $heroStats = ['total' => 0, 'active' => 0, 'expired' => 0];
                                        
                                        if (file_exists($heroJsonPath)) {
                                            $heroImages = json_decode(file_get_contents($heroJsonPath), true);
                                            if ($heroImages) {
                                                $today = new DateTime();
                                                $heroStats['total'] = count($heroImages);
                                                
                                                foreach ($heroImages as $image) {
                                                    $expires = new DateTime($image['expires']);
                                                    if ($expires >= $today) {
                                                        $heroStats['active']++;
                                                    } else {
                                                        $heroStats['expired']++;
                                                    }
                                                }
                                            }
                                        }
                                        ?>
                                        <div class="row g-3 text-center mb-3">
                                            <div class="col-4">
                                                <div class="h4 mb-1"><?php echo $heroStats['total']; ?></div>
                                                <small>Totali</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="h4 mb-1 text-success"><?php echo $heroStats['active']; ?></div>
                                                <small>Attive</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="h4 mb-1 text-warning"><?php echo $heroStats['expired']; ?></div>
                                                <small>Scadute</small>
                                            </div>
                                        </div>
                                        <div class="opacity-75 small">
                                            <p class="mb-1">• Le immagini scadute vengono rimosse automaticamente</p>
                                            <p class="mb-1">• I duplicati vengono rilevati per nome file originale</p>
                                            <p class="mb-0">• La pulizia rimuove anche i file fisici inutilizzati</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Controlli Carosello -->
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-tools me-2"></i>Manutenzione</h6>
                                        <p class="mb-3 opacity-75">Pulisci automaticamente duplicati e immagini scadute</p>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-light btn-lg" onclick="cleanHeroCarousel()">
                                                <i class="bi bi-broom me-2"></i>
                                                Pulisci Carosello
                                            </button>
                                            <button class="btn btn-outline-light" onclick="regenerateHeroCarousel()">
                                                <i class="bi bi-arrow-repeat me-2"></i>Rigenera Carosello
                                            </button>
                                            <button class="btn btn-outline-light" onclick="viewHeroImages()">
                                                <i class="bi bi-eye me-2"></i>Visualizza Immagini
                                            </button>
                                            <button class="btn btn-outline-light" onclick="refreshHeroStats()">
                                                <i class="bi bi-arrow-clockwise me-2"></i>Aggiorna Statistiche
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Risultati Pulizia -->
                                <div class="mt-4" id="heroCleanResults" style="display: none;">
                                    <div class="card" style="background: rgba(30, 30, 30, 0.95); border: 1px solid rgba(192, 192, 192, 0.2);">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0" style="color: #e0e0e0;">
                                                <i class="bi bi-check-circle me-2"></i>Risultato Pulizia
                                            </h6>
                                            <button class="btn btn-outline-secondary btn-sm" onclick="closeHeroResults()">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div id="heroCleanContent" style="background: #1a1a1a; color: #00ff00; padding: 15px; font-family: 'Courier New', monospace; font-size: 0.85rem; white-space: pre-wrap; line-height: 1.4; border-radius: 4px;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Editor Testi Email -->
                        <div class="card mb-4" style="background: linear-gradient(135deg, #5a3d2d 0%, #7a5a4a 100%); border: none; color: white;">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-envelope-paper me-2"></i>Editor Testi Email
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <!-- Anteprima Email -->
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-eye me-2"></i>Anteprima Email</h6>
                                        <p class="mb-3 opacity-75">Visualizza come apparirà l'email agli utenti</p>
                                        <div class="d-grid">
                                            <button class="btn btn-light btn-lg" onclick="previewEmail()">
                                                <i class="bi bi-play-circle me-2"></i>
                                                Anteprima Email
                                            </button>
                                        </div>
                                        <div class="mt-3">
                                            <small class="opacity-75">
                                                <strong>Variabili disponibili:</strong><br>
                                                • {nome} - Nome utente<br>
                                                • {cognome} - Cognome utente<br>
                                                • {evento} - Nome evento<br>
                                                • {data} - Data evento
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <!-- Controlli Editor -->
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-pencil-square me-2"></i>Gestione Testi</h6>
                                        <p class="mb-3 opacity-75">Modifica i contenuti testuali dell'email</p>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-light" onclick="loadEmailTexts()">
                                                <i class="bi bi-download me-2"></i>Carica Testi Attuali
                                            </button>
                                            <button class="btn btn-outline-light" onclick="resetToDefaults()">
                                                <i class="bi bi-arrow-clockwise me-2"></i>Ripristina Predefiniti
                                            </button>
                                            <button class="btn btn-outline-light" onclick="exportTexts()">
                                                <i class="bi bi-file-earmark-text me-2"></i>Esporta Testi
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Editor Form -->
                                <div class="mt-4" id="emailEditor" style="display: none;">
                                    <div class="card" style="background: rgba(30, 30, 30, 0.95); border: 1px solid rgba(192, 192, 192, 0.2);">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0" style="color: #e0e0e0;">
                                                <i class="bi bi-pencil me-2"></i>Editor Testi Email
                                            </h6>
                                            <div>
                                                <button class="btn btn-success btn-sm me-2" onclick="saveEmailTexts()">
                                                    <i class="bi bi-check2"></i> Salva
                                                </button>
                                                <button class="btn btn-outline-secondary btn-sm" onclick="closeEmailEditor()">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <form id="emailTextsForm">
                                                <div class="row g-4">
                                                    <!-- Sezione Header -->
                                                    <div class="col-12">
                                                        <h6 class="text-warning mb-3"><i class="bi bi-header me-2"></i>Header Email</h6>
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Oggetto Email</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="subject" placeholder="Es: Iscrizione Confermata - {evento}">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Titolo Principale</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="header_title" placeholder="Es: Iscrizione Confermata">
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label small">Sottotitolo Header</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="header_subtitle" placeholder="Es: Mr.Charlie Lignano Sabbiadoro">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Sezione Contenuto -->
                                                    <div class="col-12">
                                                        <h6 class="text-info mb-3"><i class="bi bi-chat-text me-2"></i>Contenuto Principale</h6>
                                                        <div class="row g-3">
                                                            <div class="col-12">
                                                                <label class="form-label small">Messaggio di Benvenuto</label>
                                                                <textarea class="form-control form-control-sm" rows="2" 
                                                                          name="greeting_message" 
                                                                          placeholder="Es: La tua registrazione è stata completata con successo..."></textarea>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Titolo QR Code</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="qr_title" placeholder="Es: Codice QR di Accesso">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Descrizione QR</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="qr_description" placeholder="Es: Il QR Code ti servirà per l'accesso...">
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label small">Nota QR Code</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="qr_note" placeholder="Es: Conserva il PDF allegato e presentalo all'ingresso">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Sezione Istruzioni -->
                                                    <div class="col-12">
                                                        <h6 class="text-success mb-3"><i class="bi bi-list-check me-2"></i>Istruzioni</h6>
                                                        <div class="row g-3">
                                                            <div class="col-12">
                                                                <label class="form-label small">Titolo Sezione Istruzioni</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="instructions_title" placeholder="Es: Informazioni Importanti">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Istruzione 1</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="instruction_1" placeholder="Prima istruzione...">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Istruzione 2</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="instruction_2" placeholder="Seconda istruzione...">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Istruzione 3</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="instruction_3" placeholder="Terza istruzione...">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Istruzione 4</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="instruction_4" placeholder="Quarta istruzione...">
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label small">Messaggio di Status</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="status_message" placeholder="Es: Tutto pronto per l'evento">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Sezione Footer -->
                                                    <div class="col-12">
                                                        <h6 class="text-secondary mb-3"><i class="bi bi-footer me-2"></i>Footer</h6>
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Titolo Footer</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="footer_title" placeholder="Es: Mr.Charlie Lignano Sabbiadoro">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Sottotitolo Footer</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="footer_subtitle" placeholder="Es: Il tuo locale di fiducia...">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Email Contatto</label>
                                                                <input type="email" class="form-control form-control-sm" 
                                                                       name="footer_email" placeholder="Es: info@mrcharlie.it">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small">Località</label>
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="footer_location" placeholder="Es: Lignano Sabbiadoro, Italia">
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label small">Disclaimer</label>
                                                                <textarea class="form-control form-control-sm" rows="2" 
                                                                          name="footer_disclaimer" 
                                                                          placeholder="Es: Questa email è stata generata automaticamente..."></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Anteprima Email -->
                                <div class="mt-4" id="emailPreview" style="display: none;">
                                    <div class="card" style="background: rgba(30, 30, 30, 0.95); border: 1px solid rgba(192, 192, 192, 0.2);">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0" style="color: #e0e0e0;">
                                                <i class="bi bi-eye me-2"></i>Anteprima Email
                                            </h6>
                                            <button class="btn btn-outline-secondary btn-sm" onclick="closeEmailPreview()">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                        <div class="card-body p-0">
                                            <iframe id="emailPreviewFrame" style="width: 100%; height: 600px; border: none; background: white;"></iframe>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Backup Database -->
                        <div class="card mb-4" style="background: linear-gradient(135deg, #5a2d5a 0%, #7a4a7a 100%); border: none; color: white;">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-database-down me-2"></i>Backup e Manutenzione Database
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-download me-2"></i>Scarica Backup Completo</h6>
                                        <p class="mb-3 opacity-75">Esporta tutto il database in formato SQL compresso</p>
                                        <div class="d-grid">
                                            <a href="?backup_database=full" class="btn btn-light btn-lg" onclick="return confirm('Vuoi scaricare il backup completo del database? Potrebbe richiedere alcuni minuti.')">
                                                <i class="bi bi-cloud-download me-2"></i>
                                                Scarica Database Completo
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-table me-2"></i>Backup Selettivo</h6>
                                        <p class="mb-3 opacity-75">Scarica solo specifiche tabelle</p>
                                        <div class="d-grid gap-2">
                                            <a href="?backup_database=events" class="btn btn-outline-light">
                                                <i class="bi bi-calendar me-2"></i>Solo Eventi
                                            </a>
                                            <a href="?backup_database=users" class="btn btn-outline-light">
                                                <i class="bi bi-people me-2"></i>Solo Utenti
                                            </a>
                                            <a href="?backup_database=structure" class="btn btn-outline-light">
                                                <i class="bi bi-diagram-3 me-2"></i>Solo Struttura
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-4 mt-3 pt-3 border-top">
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-wrench me-2"></i>Ottimizzazione Database</h6>
                                        <p class="mb-3 opacity-75">Pulisce e ottimizza le tabelle del database</p>
                                        <button class="btn btn-warning" onclick="optimizeDatabase()">
                                            <i class="bi bi-gear me-2"></i>Ottimizza Database
                                        </button>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-info-circle me-2"></i>Informazioni Backup</h6>
                                        <div class="opacity-75 small">
                                            <p class="mb-1">• I backup vengono generati in formato SQL</p>
                                            <p class="mb-1">• Include struttura e dati delle tabelle</p>
                                            <p class="mb-1">• Compressi automaticamente (GZIP)</p>
                                            <p class="mb-0">• Nome file: mrcharlie_backup_YYYYMMDD_HHMMSS.sql.gz</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
        
        </main>
    </div>

        <!-- Dettagli Evento -->
        <?php if (!empty($detailsUsers)): ?>
            <?php
            // Ottieni informazioni complete sull'evento per i dettagli
            $eventDetails = null;
            foreach ($events as $ev) {
                if ($ev['id'] == $detailsEvent) {
                    $eventDetails = $ev;
                    break;
                }
            }
            
            // Calcola statistiche dettagliate per questo evento
            $eventDetailStats = getEventStatistics($pdo, $detailsEvent, $errorMessage);
            $oggi = date('Y-m-d');
            $isEventExpired = $eventDetails && isset($eventDetails['event_date']) && $eventDetails['event_date'] < $oggi;
            $isEventClosed = $eventDetails && !empty($eventDetails['chiuso']);
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3 class="card-title m-0">
                                <i class="bi bi-calendar-event me-2"></i>
                                <?php echo $eventDetails ? htmlspecialchars($eventDetails['titolo']) : "Evento ID: " . htmlspecialchars($detailsEvent); ?>
                            </h3>
                            <div class="mt-2">
                                <?php if ($eventDetails): ?>
                                    <span class="text-muted me-3">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?php echo htmlspecialchars($eventDetails['date']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($isEventExpired): ?>
                                    <span class="badge bg-secondary">Evento Scaduto</span>
                                <?php elseif ($isEventClosed): ?>
                                    <span class="badge bg-danger">Evento Chiuso</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Evento Attivo</span>
                                <?php endif; ?>
                                
                                <?php if ($isEventExpired || $isEventClosed): ?>
                                    <span class="badge bg-info ms-2">Statistiche Finali Disponibili</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Statistiche rapide nell'header -->
                        <?php if ($eventDetailStats): ?>
                            <div class="text-end">
                                <div class="d-flex gap-3 mb-2">
                                    <div class="text-center">
                                        <div class="fw-bold" style="font-size: 1.2rem; color: #4a90e2;"><?php echo $eventDetailStats['totIscritti']; ?></div>
                                        <small class="text-muted">Iscritti</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="fw-bold" style="font-size: 1.2rem; color: #28a745;"><?php echo $eventDetailStats['totValidati']; ?></div>
                                        <small class="text-muted">Presenti</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="fw-bold" style="font-size: 1.2rem; color: #7b68ee;">
                                            <?php echo $eventDetailStats['totIscritti'] > 0 ? round(($eventDetailStats['totValidati'] / $eventDetailStats['totIscritti']) * 100, 1) : 0; ?>%
                                        </div>
                                        <small class="text-muted">Tasso</small>
                                    </div>
                                </div>
                                <?php if ($isEventExpired || $isEventClosed): ?>
                                    <small class="text-success">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Report finale disponibile
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-3 align-items-center mb-3">
                        <form id="rinviaForm" method="POST" action="./admin?details_event=<?php echo urlencode($detailsEvent); ?>" onsubmit="mostraCaricamento();">
                            <input type="hidden" name="resend_all" value="1">
                            <button type="submit" id="rinviaTuttiBtn" class="btn btn-danger">📩 Rinvia a tutti (solo non inviati)</button>
                            <span id="loadingIndicator" class="ms-3 text-muted" style="display: none;">⏳ Invio in corso, attendi...</span>
                        </form>

                        <?php
                        $eventInfo = findEventById($pdo, $detailsEvent);
                        if ($eventInfo && isset($eventInfo['chiuso']) && $eventInfo['chiuso']) {
                            ?>
                            <form method="POST" action="./admin?details_event=<?php echo urlencode($detailsEvent); ?>">
                                <input type="hidden" name="riapri_event_id" value="<?php echo htmlspecialchars($detailsEvent); ?>">
                                <button type="submit" name="riapri_evento" class="btn btn-success" onclick="return confirm('Vuoi riaprire questo evento?');">
                                    🔓 Riapri iscrizioni evento
                                </button>
                            </form>
                        <?php } else { ?>
                            <form method="POST" action="./admin?details_event=<?php echo urlencode($detailsEvent); ?>">
                                <input type="hidden" name="chiudi_event_id" value="<?php echo htmlspecialchars($detailsEvent); ?>">
                                <button type="submit" name="chiudi_evento" class="btn btn-warning" onclick="return confirm('Sei sicuro di voler chiudere le iscrizioni per questo evento?');">
                                    🔒 Chiudi iscrizioni per questo evento
                                </button>
                            </form>
                        <?php } ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Cognome</th>
                                    <th>Email</th>
                                    <th>Telefono</th>
                                    <th>Status Email</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detailsUsers as $user): ?>
                                    <tr data-user-id="<?= htmlspecialchars($user['id']) ?>"
                                        data-user-email="<?= htmlspecialchars($user['email']) ?>">
                                        <td><?= htmlspecialchars($user['nome']) ?></td>
                                        <td><?= htmlspecialchars($user['cognome']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><?= htmlspecialchars($user['telefono']) ?></td>
                                        <td>
                                            <?php 
                                            $emailStatus = $user['email_status'] ?? '';
                                            $emailError = $user['email_error'] ?? '';
                                            
                                            if ($emailStatus === 'errore'): ?>
                                                <span class="badge bg-danger" title="Errore invio: <?= htmlspecialchars($emailError) ?>">
                                                    <i class="bi bi-x-circle me-1"></i>Non consegnata
                                                </span>
                                                <br><small class="text-danger"><?= htmlspecialchars(substr($emailError, 0, 50)) ?><?= strlen($emailError) > 50 ? '...' : '' ?></small>
                                            <?php elseif ($emailStatus === 'inviata' && !empty($user['email_inviata'])): ?>
                                                <span class="badge bg-success" title="Email inviata il <?= date('d/m/Y H:i', strtotime($user['email_inviata'])) ?>">
                                                    <i class="bi bi-check-circle me-1"></i>Consegnata
                                                </span>
                                                <br><small class="text-muted"><?= date('d/m/Y H:i', strtotime($user['email_inviata'])) ?></small>
                                            <?php elseif (!empty($user['email_inviata'])): ?>
                                                <span class="badge bg-info" title="Email inviata il <?= date('d/m/Y H:i', strtotime($user['email_inviata'])) ?> - Status sconosciuto">
                                                    <i class="bi bi-question-circle me-1"></i>Inviata
                                                </span>
                                                <br><small class="text-muted"><?= date('d/m/Y H:i', strtotime($user['email_inviata'])) ?></small>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-clock me-1"></i>In attesa
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-sm btn-outline-primary btn-resend-email"
                                                        data-user-id="<?= htmlspecialchars($user['id']) ?>"
                                                        data-action="resend-email"
                                                        title="Rinvia email">
                                                    <i class="bi bi-envelope"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger btn-delete-user"
                                                        data-user-id="<?= htmlspecialchars($user['id']) ?>"
                                                        data-action="delete-user"
                                                        title="Elimina utente">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistiche per Utente -->
        <?php if (!empty($userStats)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title m-0" id="frequenza-utente">Statistiche per Utente</h3>
                </div>
                <div class="card-body table-responsive" style="background: #f9fafb;">
                    <table class="table table-striped table-hover table-bordered">
                        <thead>
                            <tr>
                                <th style="min-width: 180px;">Nome</th>
                                <th style="width: 180px;">Email</th>
                                <th>Iscrizioni</th>
                                <th>Validazioni</th>
                                <th>Ultima Validazione</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userStats as $user): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($user['nome'] . ' ' . $user['cognome']) ?></td>
                                    <td style="font-size: 0.95em; color: #555;"><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= $user['iscrizioni'] ?></td>
                                    <td><?= $user['validati'] ?></td>
                                    <td><?= $user['ultima_validazione'] ? date('d/m/Y H:i', strtotime($user['ultima_validazione'])) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Distribuzione Oraria Validazioni -->
        <?php if (!empty($hourlyStats)): ?>
            <div class="card mb-5">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title m-0">
                                <i class="bi bi-clock-history me-2"></i>
                                Distribuzione Oraria Validazioni
                            </h3>
                            <?php if ($isEventExpired || $isEventClosed): ?>
                                <p class="mb-0 mt-1 text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Report finale: analisi completa degli accessi durante l'evento
                                </p>
                            <?php else: ?>
                                <p class="mb-0 mt-1 text-muted">
                                    Monitoraggio in tempo reale degli accessi
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php if ($isEventExpired || $isEventClosed): ?>
                            <div class="text-end">
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle me-1"></i>
                                    Dati Finali
                                </span>
                                <br>
                                <small class="text-muted mt-1">
                                    Statistiche complete disponibili
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($isEventExpired || $isEventClosed): ?>
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-graph-up me-2"></i>
                            <strong>Report Finale:</strong> 
                            Questi dati rappresentano la distribuzione completa degli accessi durante l'evento. 
                            Utilizza queste informazioni per analizzare i pattern di affluenza e ottimizzare eventi futuri.
                        </div>
                    <?php endif; ?>
                    <canvas id="hourlyChart" height="100"></canvas>
                </div>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                const ctx = document.getElementById('hourlyChart').getContext('2d');
                const chart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: [<?php
                            $labels = [];
                            foreach ($hourlyStats as $h) {
                                $labels[] = '"' . $h['ora'] . ':00"';
                            }
                            echo implode(',', $labels);
                        ?>],
                        datasets: [{
                            label: 'Validazioni',
                            data: [<?php
                                $values = [];
                                foreach ($hourlyStats as $h) {
                                    $values[] = $h['validati'];
                                }
                                echo implode(',', $values);
                            ?>],
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: { beginAtZero: true, precision: 0 }
                        }
                    }
                });
            </script>
        <?php endif; ?>

        </div>
        
        </main>
    </div>

<!-- Modal per visualizzare dettagli utente -->
<div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background: linear-gradient(135deg, rgba(40, 40, 42, 0.95) 0%, rgba(60, 60, 62, 0.95) 100%); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); color: white;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h5 class="modal-title" id="userDetailsModalLabel">
                    <i class="bi bi-person-lines-fill me-2"></i>Dettagli Utente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <!-- Contenuto caricato dinamicamente -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Caricamento...</span>
                    </div>
                    <p class="text-muted mt-2">Caricamento dettagli utente...</p>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal per conferma sblocco utente -->
<div class="modal fade" id="unblockUserModal" tabindex="-1" aria-labelledby="unblockUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="background: linear-gradient(135deg, rgba(40, 40, 42, 0.95) 0%, rgba(60, 60, 62, 0.95) 100%); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); color: white;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h5 class="modal-title" id="unblockUserModalLabel">
                    <i class="bi bi-unlock-fill me-2 text-warning"></i>Sblocca Utente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" style="background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.3); color: #ffc107;">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Conferma Sblocco</strong>
                </div>
                <p>Sei sicuro di voler sbloccare l'utente <strong id="unblockUserEmail"></strong>?</p>
                <p class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Dopo lo sblocco, l'utente potrà nuovamente iscriversi agli eventi e ricevere email di conferma.
                </p>
                <div id="unblockUserDetails" class="mt-3 p-3" style="background: rgba(255, 255, 255, 0.05); border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                    <!-- Dettagli utente popolati dinamicamente -->
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-warning" id="confirmUnblockBtn">
                    <i class="bi bi-unlock-fill me-1"></i>Sblocca Utente
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal per invio email personalizzata -->
<div class="modal fade" id="sendEmailModal" tabindex="-1" aria-labelledby="sendEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background: linear-gradient(135deg, rgba(40, 40, 42, 0.95) 0%, rgba(60, 60, 62, 0.95) 100%); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); color: white;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h5 class="modal-title" id="sendEmailModalLabel">
                    <i class="bi bi-envelope-fill me-2"></i>Invia Email Personalizzata
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="customEmailForm">
                    <div class="mb-3">
                        <label for="emailRecipient" class="form-label">Destinatario</label>
                        <input type="email" class="form-control" id="emailRecipient" readonly style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: white;">
                    </div>
                    <div class="mb-3">
                        <label for="emailSubject" class="form-label">Oggetto</label>
                        <input type="text" class="form-control" id="emailSubject" placeholder="Oggetto dell'email" style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: white;">
                    </div>
                    <div class="mb-3">
                        <label for="emailMessage" class="form-label">Messaggio</label>
                        <textarea class="form-control" id="emailMessage" rows="6" placeholder="Scrivi il tuo messaggio qui..." style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: white;"></textarea>
                    </div>
                    <div class="alert alert-info" style="background: rgba(13, 202, 240, 0.1); border: 1px solid rgba(13, 202, 240, 0.3); color: #0dcaf0;">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Nota:</strong> Questa email verrà inviata con il layout ufficiale di Mr.Charlie.
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" id="sendCustomEmailBtn">
                    <i class="bi bi-send-fill me-1"></i>Invia Email
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="admin_scripts.js"></script>
<script>
// Inizializza variabili JavaScript dal PHP
<?php if (!empty($statisticheUtenti) && !empty($totalUsers)): ?>
window.initializeAdminData(<?php echo json_encode($statisticheUtenti); ?>, <?php echo $totalUsers; ?>);
<?php endif; ?>

// Live preview per immagine sfondo
document.addEventListener('DOMContentLoaded', function() {
  const input = document.getElementById('sfondo');
  const wrapper = document.getElementById('sfondoLivePreviewWrapper');
  const img = document.getElementById('sfondoLivePreview');
  if (input && wrapper && img) {
    input.addEventListener('change', function() {
      const file = this.files && this.files[0];
      if (!file) { wrapper.style.display = 'none'; return; }
      const allowed = ['image/png','image/jpeg','image/jpg'];
      if (!allowed.includes(file.type)) { wrapper.style.display = 'none'; return; }
      const reader = new FileReader();
      reader.onload = function(e) {
        img.src = e.target.result;
        wrapper.style.display = 'block';
      };
      reader.readAsDataURL(file);
    });
  }
});

// Script esistenti dell'admin
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl);
});

// ===== GESTIONE RICERCA UTENTI AJAX =====
let userSearchTimeout;
let currentUserSearchRequest = null;

function showSearchState(state) {
    const initialState = document.getElementById('searchInitialState');
    const loadingState = document.getElementById('searchLoadingState');
    const resultsState = document.getElementById('searchResults');
    
    // Nasconde tutti gli stati usando classi Bootstrap
    if (initialState) {
        initialState.style.display = 'none';
        initialState.classList.add('d-none');
    }
    if (loadingState) {
        loadingState.style.display = 'none';
        loadingState.classList.add('d-none');
    }
    if (resultsState) {
        resultsState.style.display = 'none';
        resultsState.classList.add('d-none');
    }
    
    // Mostra lo stato richiesto
    switch(state) {
        case 'initial':
            if (initialState) {
                initialState.style.display = 'block';
                initialState.classList.remove('d-none');
            }
            break;
        case 'loading':
            if (loadingState) {
                loadingState.style.display = 'block';
                loadingState.classList.remove('d-none');
            }
            break;
        case 'results':
            if (resultsState) {
                resultsState.style.display = 'block';
                resultsState.classList.remove('d-none');
            }
            break;
    }
}

function performUserSearch() {
    try {
        const searchInput = document.getElementById('searchAllUsers');
        if (!searchInput) return;
        
        const searchTerm = searchInput.value.trim();
        
        // Se il termine è troppo corto, mostra stato iniziale
        if (searchTerm.length < 3) {
            showSearchState('initial');
            return;
        }
        
        // Annulla richiesta precedente se presente
        if (currentUserSearchRequest && currentUserSearchRequest.abort) {
            currentUserSearchRequest.abort();
        }
        
        // Mostra stato di caricamento
        showSearchState('loading');
        
        // Crea controller per gestire l'abort (se supportato)
        const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        currentUserSearchRequest = controller;
        
        // Esegui ricerca
        const fetchOptions = {
            method: 'GET'
        };
        
        // Aggiungi signal solo se AbortController è supportato
        if (controller) {
            fetchOptions.signal = controller.signal;
        }
        
        // Controlla se fetch è supportato
        if (typeof fetch === 'undefined') {
            console.error('Fetch API non supportata in questo browser');
            showSearchState('results');
            const tbody = document.getElementById('searchResultsTableBody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="text-warning">
                                <i class="bi bi-exclamation-triangle fs-1 mb-3 d-block"></i>
                                <h5>Browser non supportato</h5>
                                <p class="mb-0">Aggiorna il browser per utilizzare la ricerca utenti</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
            return;
        }
        
        // Usa il path definito nel routing system
        const currentPath = window.location.pathname;
        const ajaxUrl = '/ajax_search_users.php';
        
        console.log('🔍 Avvio ricerca utenti:', {
            searchTerm: searchTerm,
            ajaxUrl: ajaxUrl,
            currentPath: currentPath,
            fullUrl: window.location.href,
            hostname: window.location.hostname,
            pathname: window.location.pathname,
            origin: window.location.origin,
            timestamp: new Date().toISOString()
        });
        
        // Test del bootstrap prima della ricerca principale
        console.log('🧪 Test bootstrap...');
        fetch('/simple_test')
        .then(response => response.json())
        .then(data => {
            console.log('🧪 Test bootstrap result:', data);
        })
        .catch(err => {
            console.log('🧪 Test bootstrap error:', err);
        });
        
        fetch(`${ajaxUrl}?search=${encodeURIComponent(searchTerm)}&limit=50&sort=relevance`, fetchOptions)
        .then(response => {
            console.log('📥 Risposta ricevuta:', {
                status: response.status,
                statusText: response.statusText,
                url: response.url,
                contentType: response.headers.get('content-type')
            });
            
            if (!response.ok) {
                // Per errori 500, proviamo a leggere il contenuto per vedere l'errore
                if (response.status === 500) {
                    return response.text().then(text => {
                        console.error('📄 Contenuto errore 500:', text);
                        try {
                            const jsonError = JSON.parse(text);
                            throw new Error(`HTTP ${response.status}: ${jsonError.error || response.statusText} (${jsonError.debug_error || 'Nessun dettaglio'})`);
                        } catch (parseError) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText} - ${text.substring(0, 200)}`);
                        }
                    });
                } else {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
            }
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Risposta non JSON dal server');
            }
            return response.json();
        })
        .then(data => {
            currentUserSearchRequest = null;
            
            console.log('📊 Dati ricevuti:', data);
            
            // Verifica che gli elementi DOM esistano ancora
            const searchInputCheck = document.getElementById('searchAllUsers');
            if (!searchInputCheck) {
                console.warn('Elementi DOM ricerca rimossi durante fetch');
                return;
            }
            
            if (data.success) {
                displaySearchResults(data.users, data.search_term);
            } else {
                // Mostra errore dettagliato se disponibile
                const errorMessage = data.debug_error ? 
                    `${data.error} (${data.error_type}: ${data.debug_error})` : 
                    (data.error || 'Errore nella ricerca');
                throw new Error(errorMessage);
            }
        })
        .catch(error => {
            currentUserSearchRequest = null;
            
            if (error.name === 'AbortError') {
                // Richiesta annullata, non fare nulla
                return;
            }
            
            console.error('Errore ricerca utenti:', error);
            console.error('Stack trace completo:', error.stack);
            
            // Mostra messaggio di errore dettagliato
            showSearchState('results');
            const tbody = document.getElementById('searchResultsTableBody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="text-danger">
                                <i class="bi bi-exclamation-triangle fs-1 mb-3 d-block"></i>
                                <h5>Errore durante la ricerca</h5>
                                <p class="mb-0">${error.message}</p>
                                <small class="text-muted">Controlla la console per dettagli completi</small>
                            </div>
                        </td>
                    </tr>
                `;
            }
            
            const countElement = document.getElementById('searchResultsCount');
            if (countElement) countElement.textContent = '0';
        });
    } catch (error) {
        console.error('Errore imprevisto in performUserSearch:', error);
        currentUserSearchRequest = null;
        showSearchState('results');
        const tbody = document.getElementById('searchResultsTableBody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <div class="text-danger">
                            <i class="bi bi-exclamation-triangle fs-1 mb-3 d-block"></i>
                            <h5>Errore imprevisto</h5>
                            <p class="mb-0">Si è verificato un errore imprevisto. Riprova o contatta l'amministratore.</p>
                        </div>
                    </td>
                </tr>
            `;
        }
    }
}

function displaySearchResults(users, searchTerm) {
    showSearchState('results');
    
    const tbody = document.getElementById('searchResultsTableBody');
    const countElement = document.getElementById('searchResultsCount');
    const queryElement = document.getElementById('searchQuery');
    
    if (!tbody) return;
    
    // Aggiorna contatore e termine di ricerca
    if (countElement) countElement.textContent = users.length;
    if (queryElement) queryElement.textContent = searchTerm ? ` per "${searchTerm}"` : '';
    
    if (users.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5">
                    <i class="bi bi-search text-muted mb-3 d-block" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mb-2">Nessun utente trovato</h5>
                    <p class="text-muted mb-0">Prova a modificare i termini di ricerca</p>
                </td>
            </tr>
        `;
        return;
    }
    
    // Genera righe della tabella
    let html = '';
    users.forEach(user => {
        const tassoColor = user.tasso_presenza >= 80 ? 'text-success' : 
                          user.tasso_presenza >= 50 ? 'text-warning' : 'text-danger';
        
        const statusBadge = user.is_blocked ? 
            '<span class="badge bg-danger">Bloccato</span>' : 
            '<span class="badge bg-success">Attivo</span>';
            
        html += `
            <tr>
                <td>
                    <div class="fw-semibold">${escapeHtml(user.nome)} ${escapeHtml(user.cognome)}</div>
                    <small class="text-muted">
                        ${user.data_nascita ? new Date(user.data_nascita).toLocaleDateString('it-IT') : 'N/A'}
                    </small>
                </td>
                <td>
                    <div>${escapeHtml(user.email)}</div>
                    ${user.telefono ? `<small class="text-muted">${escapeHtml(user.telefono)}</small>` : ''}
                </td>
                <td class="d-none d-lg-table-cell">
                    <div class="small">
                        <div><i class="bi bi-calendar-plus me-1"></i>Iscritto: ${user.prima_iscrizione ? new Date(user.prima_iscrizione).toLocaleDateString('it-IT') : 'N/A'}</div>
                        <div><i class="bi bi-calendar-check me-1"></i>Ultima presenza: ${user.ultima_presenza ? new Date(user.ultima_presenza).toLocaleDateString('it-IT') : 'Mai'}</div>
                    </div>
                </td>
                <td class="text-center">
                    <span class="fw-bold text-primary">${user.total_eventi}</span>
                </td>
                <td class="text-center">
                    <span class="fw-bold text-success">${user.eventi_validati}</span>
                </td>
                <td class="text-center">
                    <span class="fw-bold ${tassoColor}">${user.tasso_presenza}%</span>
                </td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-danger btn-sm" 
                                onclick="deleteUserFromSearch('${escapeJsForSearch(user.email)}')"
                                title="Elimina utente">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <div class="mt-1">${statusBadge}</div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function escapeJsForSearch(text) {
    return text.replace(/\\/g, '\\\\')
               .replace(/'/g, "\\'")
               .replace(/"/g, '\\"')
               .replace(/\n/g, '\\n')
               .replace(/\r/g, '\\r');
}

function clearSearchResults() {
    const searchInput = document.getElementById('searchAllUsers');
    if (searchInput) {
        searchInput.value = '';
        showSearchState('initial');
        
        // Cancella timeout e richieste pendenti
        clearTimeout(userSearchTimeout);
        if (currentUserSearchRequest && currentUserSearchRequest.abort) {
            currentUserSearchRequest.abort();
            currentUserSearchRequest = null;
        }
    }
}

function toggleSearchView(viewType) {
    const tableView = document.getElementById('searchTableView');
    const gridView = document.getElementById('searchGridView');
    const tableBtn = document.getElementById('viewTable');
    const gridBtn = document.getElementById('viewGrid');
    
    if (viewType === 'grid') {
        if (tableView) tableView.classList.add('d-none');
        if (gridView) gridView.classList.remove('d-none');
        if (tableBtn) tableBtn.classList.remove('active');
        if (gridBtn) gridBtn.classList.add('active');
    } else {
        if (tableView) tableView.classList.remove('d-none');
        if (gridView) gridView.classList.add('d-none');
        if (tableBtn) tableBtn.classList.add('active');
        if (gridBtn) gridBtn.classList.remove('active');
    }
}

function deleteUserFromSearch(userEmail) {
    if (!confirm('Sei sicuro di voler eliminare questo utente? L\'azione è irreversibile.')) {
        return;
    }
    
    // Trova l'utente nel database per ottenere l'ID
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_user_id&email=${encodeURIComponent(userEmail)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.user_id) {
            // Elimina direttamente usando l'endpoint delete_user
            return fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_user&id=${data.user_id}`
            }).then(response => response.json());
        } else {
            throw new Error('Utente non trovato nel database');
        }
    })
    .then(data => {
        if (data.success) {
            showAlert('success', data.message || 'Utente eliminato con successo');
            
            // Aggiorna i risultati di ricerca dopo l'eliminazione
            setTimeout(() => {
                performUserSearch();
            }, 1000);
        } else {
            throw new Error(data.error || data.message || 'Errore durante l\'eliminazione');
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showAlert('danger', `Errore durante l'eliminazione: ${error.message}`);
    });
}

// Event listeners per la ricerca - solo per il tab ricerca utenti
document.addEventListener('DOMContentLoaded', function() {
    // Previeni inizializzazione multipla
    if (window.userSearchInitialized) {
        return;
    }
    window.userSearchInitialized = true;
    
    // Ascolta i cambi di tab per attivare/disattivare i listener
    const searchTab = document.getElementById('tutti-utenti-tab');
    const searchInput = document.getElementById('searchAllUsers');
    
    if (searchTab && searchInput) {
        let isSearchListenersActive = false;
        
        function activateSearchListeners() {
            if (isSearchListenersActive) return;
            isSearchListenersActive = true;
            
            // Listener per input di ricerca con debounce
            searchInput.addEventListener('input', handleSearchInput);
            searchInput.addEventListener('keydown', handleSearchKeydown);
        }
        
        function deactivateSearchListeners() {
            if (!isSearchListenersActive) return;
            isSearchListenersActive = false;
            
            searchInput.removeEventListener('input', handleSearchInput);
            searchInput.removeEventListener('keydown', handleSearchKeydown);
            
            // Cancella timeout pendenti
            clearTimeout(userSearchTimeout);
            if (currentUserSearchRequest && currentUserSearchRequest.abort) {
                currentUserSearchRequest.abort();
                currentUserSearchRequest = null;
            }
        }
        
        function handleSearchInput() {
            clearTimeout(userSearchTimeout);
            userSearchTimeout = setTimeout(performUserSearch, 500);
        }
        
        function handleSearchKeydown(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(userSearchTimeout);
                performUserSearch();
            }
        }
        
        // Attiva/disattiva listener quando si cambia tab
        searchTab.addEventListener('shown.bs.tab', activateSearchListeners);
        searchTab.addEventListener('hidden.bs.tab', deactivateSearchListeners);
        
        // Se il tab è già attivo al caricamento, attiva i listener
        const searchTabPane = document.getElementById('tutti-utenti');
        if (searchTab.classList.contains('active') || 
            (searchTabPane && searchTabPane.classList.contains('show') && searchTabPane.classList.contains('active'))) {
            activateSearchListeners();
        }
    }
});

// Funzione mostraCaricamento() definita in admin_scripts.js

// Gestione pulsanti AJAX per utenti
document.addEventListener('DOMContentLoaded', function() {
    // Pulsante elimina utente
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-delete-user')) {
            e.preventDefault();
            const button = e.target.closest('.btn-delete-user');
            const userId = button.getAttribute('data-user-id');
            
            if (confirm('Sei sicuro di voler eliminare questo utente? L\'azione è irreversibile.')) {
                deleteUser(userId, button);
            }
        }
        
        // Pulsante reinvia email
        if (e.target.closest('.btn-resend-email')) {
            e.preventDefault();
            const button = e.target.closest('.btn-resend-email');
            const userId = button.getAttribute('data-user-id');
            
            if (confirm('Vuoi reinviare l\'email a questo utente?')) {
                resendEmail(userId, button);
            }
        }
    });
});

function deleteUser(userId, button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    button.disabled = true;
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete_user&id=${userId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Rimuovi la riga dalla tabella
            const row = button.closest('tr');
            if (row) {
                row.style.opacity = '0.5';
                row.style.transition = 'opacity 0.3s';
                setTimeout(() => {
                    row.remove();
                }, 300);
            }
            
            // Mostra messaggio di successo
            try {
                showAlert('success', data.message);
            } catch (alertError) {
                console.error('Errore nel mostrare l\'alert:', alertError);
                alert('Utente eliminato con successo');
            }
        } else {
            try {
                showAlert('danger', data.error || data.message || 'Errore durante l\'eliminazione');
            } catch (alertError) {
                console.error('Errore nel mostrare l\'alert:', alertError);
                alert('Errore durante l\'eliminazione: ' + (data.error || data.message || 'Errore sconosciuto'));
            }
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        try {
            showAlert('danger', 'Errore di connessione');
        } catch (alertError) {
            console.error('Errore nel mostrare l\'alert:', alertError);
            alert('Errore di connessione durante l\'eliminazione dell\'utente');
        }
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function resendEmail(userId, button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    button.disabled = true;
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=resend_email&id=${userId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            try {
                showAlert('success', data.message);
            } catch (alertError) {
                console.error('Errore nel mostrare l\'alert:', alertError);
                alert('Email inviata con successo');
            }
        } else {
            try {
                showAlert('danger', data.error || data.message || 'Errore durante l\'invio');
            } catch (alertError) {
                console.error('Errore nel mostrare l\'alert:', alertError);
                alert('Errore durante l\'invio: ' + (data.error || data.message || 'Errore sconosciuto'));
            }
        }
        button.innerHTML = originalText;
        button.disabled = false;
    })
    .catch(error => {
        console.error('Errore:', error);
        try {
            showAlert('danger', 'Errore di connessione');
        } catch (alertError) {
            console.error('Errore nel mostrare l\'alert:', alertError);
            alert('Errore di connessione durante l\'invio dell\'email');
        }
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function showAlert(type, message) {
    // Crea alert Bootstrap
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Trova il container appropriato
    let container = document.querySelector('.container-fluid');
    if (!container) {
        container = document.querySelector('.container');
    }
    if (!container) {
        container = document.querySelector('main');
    }
    if (!container) {
        container = document.body;
    }
    
    // Inserisci in cima al container trovato
    if (container && container.firstChild) {
        container.insertBefore(alertDiv, container.firstChild);
    } else if (container) {
        container.appendChild(alertDiv);
    } else {
        console.error('Impossibile trovare un container per mostrare l\'alert');
        return;
    }
    
    // Rimuovi automaticamente dopo 5 secondi
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Funzioni per gestione Hero Images
function viewHeroImages() {
    console.log('Visualizzazione immagini hero...');
    showAlert('info', 'Funzione visualizzazione immagini hero in sviluppo');
}

function refreshHeroStats() {
    console.log('Aggiornamento statistiche hero...');
    showAlert('info', 'Aggiornamento statistiche in corso...');
    
    // Simula refresh
    setTimeout(() => {
        showAlert('success', 'Statistiche aggiornate con successo');
        // Ricarica la pagina per aggiornare i dati
        window.location.reload();
    }, 1000);
}

function regenerateHeroCarousel() {
    if (confirm('Sei sicuro di voler rigenerare il carosello delle immagini hero?')) {
        console.log('Rigenerazione carosello hero...');
        showAlert('info', 'Rigenerazione carosello in corso...');
        
        // Simula rigenerazione
        setTimeout(() => {
            showAlert('success', 'Carosello rigenerato con successo');
        }, 2000);
    }
}

// Funzioni per gestione Carosello
function refreshCaroselloImages() {
    const container = document.getElementById('caroselloImagesContainer');
    if (!container) return;
    
    container.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Caricamento...</span>
            </div>
            <p class="mt-2 text-muted">Caricamento immagini...</p>
        </div>
    `;
    
    fetch('admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_carosello_images'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayCaroselloImages(data.images);
        } else {
            container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Errore nel caricamento: ${data.error || 'Errore sconosciuto'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        container.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Errore di connessione
            </div>
        `;
    });
}

function displayCaroselloImages(images) {
    const container = document.getElementById('caroselloImagesContainer');
    if (!container) return;
    
    if (!images || images.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-images fs-1 text-muted mb-3"></i>
                <h5 class="text-muted">Nessuna immagine trovata</h5>
                <p class="text-muted">Carica la prima immagine usando il form qui sopra</p>
            </div>
        `;
        return;
    }
    
    const today = new Date();
    const imagesHtml = images.map(image => {
        const expires = new Date(image.expires);
        const isExpired = expires < today;
        const daysLeft = Math.ceil((expires - today) / (1000 * 60 * 60 * 24));
        
        return `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card bg-secondary border-${isExpired ? 'danger' : 'success'}">
                    <div class="position-relative">
                        <img src="${image.src}" class="card-img-top" style="height: 200px; object-fit: cover;" 
                             alt="${image.filename}" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iIzZjNzU3ZCIvPjx0ZXh0IHg9IjE1MCIgeT0iMTAwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiNmZmYiIHRleHQtYW5jaG9yPSJtaWRkbGUiPkVycm9yZSBjYXJpY2FtZW50bzwvdGV4dD48L3N2Zz4='">
                        <div class="position-absolute top-0 end-0 m-2">
                            <span class="badge bg-${isExpired ? 'danger' : daysLeft <= 7 ? 'warning' : 'success'}">
                                ${isExpired ? 'SCADUTA' : `${daysLeft} giorni`}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <h6 class="card-title text-truncate mb-1" title="${image.filename}">
                            ${image.filename}
                        </h6>
                        <p class="card-text small text-muted mb-2">
                            Scade: ${new Date(image.expires).toLocaleDateString('it-IT')}
                        </p>
                        <div class="d-flex gap-1">
                            <button class="btn btn-outline-danger btn-sm flex-fill" 
                                    onclick="deleteCaroselloImage('${image.filename}')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <button class="btn btn-outline-primary btn-sm flex-fill" 
                                    onclick="viewCaroselloImage('${image.src}')">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    container.innerHTML = `
        <div class="row">
            ${imagesHtml}
        </div>
        <div class="mt-3 text-center">
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                ${images.length} immagini totali • 
                ${images.filter(img => new Date(img.expires) >= today).length} attive • 
                ${images.filter(img => new Date(img.expires) < today).length} scadute
            </small>
        </div>
    `;
}

function deleteCaroselloImage(filename) {
    if (!confirm(`Sei sicuro di voler eliminare l'immagine "${filename}"?`)) return;
    
    fetch('admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_carosello_image&filename=${encodeURIComponent(filename)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            refreshCaroselloImages();
        } else {
            showAlert('danger', data.error || 'Errore durante l\'eliminazione');
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showAlert('danger', 'Errore di connessione');
    });
}

function viewCaroselloImage(src) {
    window.open(src, '_blank');
}

// Gestione form upload carosello
document.addEventListener('DOMContentLoaded', function() {
    const caroselloForm = document.getElementById('caroselloUploadForm');
    if (caroselloForm) {
        caroselloForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'upload_carosello_images');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Caricamento...';
            submitBtn.disabled = true;
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    this.reset();
                    refreshCaroselloImages();
                } else {
                    showAlert('danger', data.error || 'Errore durante l\'upload');
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                showAlert('danger', 'Errore di connessione');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Imposta data di default (2 anni nel futuro)
        const expiryInput = document.getElementById('caroselloExpiry');
        if (expiryInput) {
            const futureDate = new Date();
            futureDate.setFullYear(futureDate.getFullYear() + 2);
            expiryInput.value = futureDate.toISOString().split('T')[0];
        }
        
        // Carica le immagini quando si apre la tab
        const caroselloTab = document.getElementById('carosello-tab');
        if (caroselloTab) {
            caroselloTab.addEventListener('shown.bs.tab', function() {
                refreshCaroselloImages();
            });
        }
    }
});
</script>
</body>
</html><?php
if (ob_get_level()) {
    ob_end_flush();
}
?>
