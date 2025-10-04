<?php
/**
 * Sistema di Auguri di Compleanno Automatico - MrCharlie
 * Gestisce l'invio automatico di messaggi di auguri personalizzati
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class BirthdaySystem
{
    private $pdo;
    private $config;
    
    public function __construct($pdo, $config)
    {
        if (!$pdo instanceof PDO) {
            throw new InvalidArgumentException('Il primo parametro deve essere un\'istanza PDO valida');
        }
        
        if (!is_array($config) || empty($config)) {
            throw new InvalidArgumentException('Il secondo parametro deve essere un array di configurazione valido');
        }
        
        $this->pdo = $pdo;
        $this->config = $config;
        $this->initializeDatabase();
        $this->setDefaultTemplate();
    }
    
    private function initializeDatabase()
    {
        // Tabella per template messaggi compleanno
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS birthday_templates (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                html_content TEXT NOT NULL,
                background_image VARCHAR(500) DEFAULT NULL,
                is_active BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        // Tabella per tracciare auguri inviati
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS birthday_sent (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_email VARCHAR(255) NOT NULL,
                user_name VARCHAR(255) NOT NULL,
                birthday_date DATE NOT NULL,
                sent_year YEAR NOT NULL,
                template_id INT,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_birthday_year (user_email, sent_year),
                FOREIGN KEY (template_id) REFERENCES birthday_templates(id)
            )
        ");

        // Tabella per immagini personalizzate
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS birthday_assets (
                id INT PRIMARY KEY AUTO_INCREMENT,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_type VARCHAR(100) NOT NULL,
                file_size INT NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    private function setDefaultTemplate()
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM birthday_templates");
        $count = $stmt->fetch()['count'];

        if ($count == 0) {
            $this->createDefaultTemplate();
        }
        
        // Aggiungi template eleganti se non esistono già
        $this->createAdvancedTemplatesIfNeeded();
    }
    
    private function createAdvancedTemplatesIfNeeded()
    {
        // Controlla se esistono già template avanzati
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM birthday_templates WHERE name LIKE '%Elegante%' OR name LIKE '%Smart%'");
        $count = $stmt->fetch()['count'];
        
        if ($count == 0) {
            $this->createElegantTemplate();
            $this->createSmartTemplate();
            $this->createMinimalTemplate();
        }
    }
    
    private function createDefaultTemplate()
    {
        $defaultHtml = '
        <div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 20px;">
            <div style="text-align: center; color: white;">
                <h1 style="font-size: 2.5em; margin-bottom: 20px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">
                    🎉 Buon Compleanno! 🎂
                </h1>
                
                <div style="background: rgba(255,255,255,0.1); border-radius: 15px; padding: 25px; margin: 20px 0;">
                    <h2 style="color: #fff; margin-bottom: 15px;">Caro {{NOME}},</h2>
                    <p style="font-size: 1.2em; line-height: 1.6; color: #fff; margin-bottom: 20px;">
                        Oggi è il tuo giorno speciale! 🎈<br>
                        Tutto il team di <strong>MrCharlie</strong> ti augura un compleanno fantastico e un anno ricco di momenti indimenticabili!
                    </p>
                    
                    <div style="background: rgba(255,255,255,0.2); border-radius: 10px; padding: 20px; margin: 20px 0;">
                        <h3 style="color: #fff; margin-bottom: 10px;">🎁 Sorpresa Speciale!</h3>
                        <p style="color: #fff;">
                            Come regalo di compleanno, hai diritto a <strong>un ingresso omaggio VIP</strong> per il prossimo evento MrCharlie!
                        </p>
                    </div>
                    
                    <p style="font-size: 1.1em; color: #fff; margin-top: 20px;">
                        Grazie per essere parte della famiglia MrCharlie! 💜
                    </p>
                </div>
                
                <div style="margin-top: 30px;">
                    <p style="color: rgba(255,255,255,0.8); font-size: 0.9em;">
                        Con affetto,<br>
                        <strong>Il Team MrCharlie</strong><br>
                        Lignano Sabbiadoro
                    </p>
                </div>
                
                <div style="margin-top: 20px; font-size: 2em;">
                    🎊 🎈 🎂 🎁 🎉
                </div>
            </div>
        </div>';

        $stmt = $this->pdo->prepare("
            INSERT INTO birthday_templates (name, subject, html_content, is_active) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            'Template Default MrCharlie',
            '🎉 Buon Compleanno da MrCharlie! 🎂',
            $defaultHtml,
            1
        ]);
    }
    
    private function createElegantTemplate()
    {
        $elegantHtml = '
        <div style="max-width: 650px; margin: 0 auto; font-family: Georgia, serif; background: linear-gradient(145deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); border-radius: 20px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <!-- Header con pattern geometrico -->
            <div style="background: linear-gradient(135deg, #ffd700 0%, #ff6b6b 25%, #667eea 50%, #764ba2 75%, #ffd700 100%); height: 8px;"></div>
            
            <!-- Contenuto principale -->
            <div style="padding: 40px 30px; text-align: center; position: relative;">
                <!-- Decorazione geometrica di sfondo -->
                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.05; background-image: radial-gradient(circle at 25% 25%, #ffd700 2px, transparent 2px), radial-gradient(circle at 75% 75%, #667eea 2px, transparent 2px); background-size: 40px 40px;"></div>
                
                <!-- Logo/Corona decorativa -->
                <div style="position: relative; margin-bottom: 30px;">
                    <div style="font-size: 3.5em; color: #ffd700; text-shadow: 0 0 20px rgba(255, 215, 0, 0.5); margin-bottom: 10px;">👑</div>
                    <h1 style="color: #ffd700; font-size: 2.2em; margin: 0; font-weight: normal; letter-spacing: 2px; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                        Buon Compleanno
                    </h1>
                    <div style="width: 80px; height: 2px; background: linear-gradient(90deg, transparent, #ffd700, transparent); margin: 15px auto;"></div>
                </div>
                
                <!-- Contenuto personalizzato -->
                <div style="background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255,215,0,0.2); border-radius: 15px; padding: 30px; margin: 25px 0; position: relative;">
                    <h2 style="color: #ffffff; font-size: 1.8em; margin: 0 0 20px 0; font-weight: normal;">
                        Caro <span style="color: #ffd700;">{{NOME}}</span>
                    </h2>
                    
                    <p style="color: #e8e8e8; font-size: 1.15em; line-height: 1.8; margin-bottom: 25px; font-style: italic;">
                        Oggi segni un altro capitolo della tua storia: <strong style="color: #ffd700;">{{ETA}} anni</strong> di vita, esperienze e ricordi preziosi.
                    </p>
                    
                    <p style="color: #ffffff; font-size: 1.1em; line-height: 1.7; margin-bottom: 30px;">
                        Il team di <strong style="color: #667eea;">MrCharlie</strong> ti augura una giornata magica, 
                        circondata dalle persone che ami, con musica che scalda il cuore e momenti che resteranno per sempre.
                    </p>
                    
                    <!-- Box regalo elegante -->
                    <div style="background: linear-gradient(135deg, rgba(255,215,0,0.15) 0%, rgba(102,126,234,0.15) 100%); border: 2px solid rgba(255,215,0,0.3); border-radius: 12px; padding: 25px; margin: 25px 0; position: relative; overflow: hidden;">
                        <div style="position: absolute; top: -10px; right: -10px; font-size: 4em; opacity: 0.1; color: #ffd700;">🎁</div>
                        <h3 style="color: #ffd700; margin: 0 0 15px 0; font-size: 1.3em; font-weight: normal;">✨ Esperienza Esclusiva</h3>
                        <p style="color: #ffffff; margin: 0; font-size: 1.05em; line-height: 1.6;">
                            Un <strong style="color: #ffd700;">ingresso VIP speciale</strong> ti aspetta per vivere la prossima serata MrCharlie 
                            in modo unico ed esclusivo.
                        </p>
                        <div style="margin-top: 15px; font-size: 0.9em; color: rgba(255,255,255,0.7);">
                            <em>Presenta questa email in reception</em>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <p style="color: #e8e8e8; font-size: 1.1em; margin: 0; font-style: italic;">
                            Con affetto e stima sincera
                        </p>
                    </div>
                </div>
                
                <!-- Footer elegante -->
                <div style="margin-top: 35px; padding-top: 25px; border-top: 1px solid rgba(255,215,0,0.2);">
                    <div style="color: #ffd700; font-size: 2.2em; margin-bottom: 20px; letter-spacing: 8px;">
                        ✨ 🎊 ✨
                    </div>
                    
                    <div style="background: rgba(0,0,0,0.2); border-radius: 10px; padding: 20px; margin-top: 20px;">
                        <p style="color: rgba(255,255,255,0.9); font-size: 0.95em; margin: 0; line-height: 1.5;">
                            <strong style="color: #ffd700;">MrCharlie Team</strong><br>
                            Lignano Sabbiadoro - Il tuo locale del cuore<br>
                            📍 Via Tagliamento, 2 | 📧 info@mrcharlie.net<br>
                            <span style="font-size: 0.85em; color: rgba(255,255,255,0.7);">Dove ogni serata diventa un ricordo speciale</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>';

        $stmt = $this->pdo->prepare("
            INSERT INTO birthday_templates (name, subject, html_content, is_active) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            'Template Elegante Premium',
            '👑 {{NOME}}, oggi è il tuo giorno speciale - MrCharlie',
            $elegantHtml,
            0
        ]);
    }
    
    private function createSmartTemplate()
    {
        $smartHtml = '
        <div style="max-width: 600px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
            <!-- Header dinamico con gradiente -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%); padding: 40px 30px; text-align: center; position: relative; overflow: hidden;">
                <div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 20px 20px; animation: float 20s infinite linear;"></div>
                
                <div style="position: relative; z-index: 2;">
                    <div style="font-size: 3em; margin-bottom: 15px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));">🎉</div>
                    <h1 style="color: white; font-size: 2.4em; margin: 0 0 10px 0; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                        Buon Compleanno!
                    </h1>
                    <div style="background: rgba(255,255,255,0.2); height: 2px; width: 60px; margin: 0 auto; border-radius: 1px;"></div>
                </div>
            </div>
            
            <!-- Contenuto intelligente -->
            <div style="padding: 40px 30px;">
                <!-- Sezione personalizzata -->
                <div style="background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%); border-left: 4px solid #667eea; border-radius: 12px; padding: 25px; margin-bottom: 30px; position: relative;">
                    <div style="position: absolute; top: 15px; right: 20px; font-size: 2em; opacity: 0.1;">🎂</div>
                    
                    <h2 style="color: #333; font-size: 1.6em; margin: 0 0 15px 0; font-weight: 500;">
                        Ciao <span style="color: #667eea;">{{NOME}}</span>! 👋
                    </h2>
                    
                    <p style="color: #555; font-size: 1.1em; line-height: 1.7; margin-bottom: 20px;">
                        Oggi non è un giorno qualunque: compi <strong style="color: #667eea;">{{ETA}} anni</strong>! 
                        È il momento perfetto per celebrare chi sei e tutto quello che hai raggiunto.
                    </p>
                    
                    <div style="background: #ffffff; border: 1px solid #e1e8ff; border-radius: 8px; padding: 15px; margin: 20px 0;">
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 1.5em; margin-right: 10px;">📊</span>
                            <strong style="color: #333; font-size: 1em;">I tuoi numeri speciali:</strong>
                        </div>
                        <div style="color: #666; font-size: 0.95em; line-height: 1.5;">
                            🎯 <strong>{{ETA}}</strong> anni di esperienze<br>
                            📅 Nato il <strong>{{DATA_NASCITA}}</strong><br>
                            🎊 Compleanno <strong>{{ANNO}}</strong> con MrCharlie
                        </div>
                    </div>
                </div>
                
                <!-- Offerta smart -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 30px; margin: 30px 0; color: white; text-align: center; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -10px; right: -10px; font-size: 5em; opacity: 0.1;">🎁</div>
                    
                    <div style="position: relative;">
                        <h3 style="margin: 0 0 15px 0; font-size: 1.4em; font-weight: 600;">🚀 Accesso VIP Esclusivo</h3>
                        
                        <p style="margin: 0 0 20px 0; font-size: 1.05em; line-height: 1.6; opacity: 0.95;">
                            Il team MrCharlie ha preparato qualcosa di speciale per te: 
                            <strong>un pass VIP gratuito</strong> per vivere la prossima serata come una vera star!
                        </p>
                        
                        <div style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 15px; margin: 20px 0;">
                            <div style="font-size: 0.9em; opacity: 0.9;">
                                ✨ Ingresso prioritario<br>
                                🥂 Welcome drink incluso<br>
                                📱 Mostra questa email in reception
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px; font-size: 0.85em; opacity: 0.8;">
                            <em>Valido per i prossimi eventi MrCharlie</em>
                        </div>
                    </div>
                </div>
                
                <!-- Call to action moderna -->
                <div style="text-align: center; margin: 35px 0;">
                    <div style="background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 25px; padding: 3px; display: inline-block;">
                        <div style="background: white; border-radius: 22px; padding: 12px 25px; color: #667eea; font-weight: 600; font-size: 1em;">
                            🎊 Festeggia con Noi!
                        </div>
                    </div>
                </div>
                
                <!-- Footer intelligente -->
                <div style="border-top: 1px solid #e8e8e8; padding-top: 25px; margin-top: 35px; text-align: center;">
                    <div style="color: #667eea; font-size: 1.8em; margin-bottom: 15px;">✨</div>
                    
                    <p style="color: #666; font-size: 0.95em; margin: 0 0 15px 0; line-height: 1.5;">
                        Con affetto e i migliori auguri,<br>
                        <strong style="color: #333;">Il Team MrCharlie</strong>
                    </p>
                    
                    <div style="background: #f8f9ff; border-radius: 8px; padding: 15px; margin-top: 20px;">
                        <div style="font-size: 0.85em; color: #666; line-height: 1.4;">
                            📍 <strong>MrCharlie</strong> - Lignano Sabbiadoro<br>
                            📧 info@mrcharlie.net | 🌐 www.mrcharlie.net<br>
                            <span style="color: #999;">Dove ogni serata è unica come te</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        @keyframes float {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        </style>';

        $stmt = $this->pdo->prepare("
            INSERT INTO birthday_templates (name, subject, html_content, is_active) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            'Template Smart & Moderno',
            '🎉 {{NOME}}, è il tuo giorno! Ecco il tuo regalo VIP 🎁',
            $smartHtml,
            0
        ]);
    }
    
    private function createMinimalTemplate()
    {
        $minimalHtml = '
        <div style="max-width: 580px; margin: 0 auto; font-family: Inter, -apple-system, sans-serif; background: #ffffff; border: 1px solid #f0f0f0; border-radius: 8px;">
            <!-- Header minimalista -->
            <div style="background: #667eea; padding: 30px; text-align: center;">
                <h1 style="color: white; font-size: 1.8em; margin: 0; font-weight: 400; letter-spacing: 1px;">
                    Buon Compleanno {{NOME}}
                </h1>
            </div>
            
            <!-- Contenuto pulito -->
            <div style="padding: 40px 30px;">
                <p style="color: #333; font-size: 1.1em; line-height: 1.8; margin-bottom: 25px;">
                    Oggi compi <strong>{{ETA}} anni</strong> e vogliamo celebrare questo momento speciale con te.
                </p>
                
                <div style="background: #f8f9fa; border-left: 3px solid #667eea; padding: 20px; margin: 25px 0;">
                    <p style="color: #333; margin: 0; font-size: 1em; line-height: 1.6;">
                        <strong>Il tuo regalo:</strong> Ingresso VIP gratuito per il prossimo evento MrCharlie.
                    </p>
                </div>
                
                <p style="color: #666; font-size: 0.95em; margin-top: 30px; text-align: center;">
                    Buon compleanno dal team MrCharlie<br>
                    Lignano Sabbiadoro
                </p>
            </div>
        </div>';

        $stmt = $this->pdo->prepare("
            INSERT INTO birthday_templates (name, subject, html_content, is_active) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            'Template Minimal Clean',
            'Buon Compleanno {{NOME}} - MrCharlie',
            $minimalHtml,
            0
        ]);
    }
    
    public function checkAndSendBirthdayWishes()
    {
        $today = date('m-d');
        $currentYear = date('Y');
        
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT u.nome, u.cognome, u.email, u.data_nascita
            FROM utenti u
            LEFT JOIN birthday_sent bs ON u.email = bs.user_email AND bs.sent_year = ?
            WHERE DATE_FORMAT(u.data_nascita, '%m-%d') = ? 
            AND bs.id IS NULL
            AND u.data_nascita IS NOT NULL
            AND u.data_nascita >= '1000-01-01'
        ");
        
        $stmt->execute([$currentYear, $today]);
        $birthdayUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sentCount = 0;
        foreach ($birthdayUsers as $user) {
            if ($this->sendBirthdayWish($user)) {
                $sentCount++;
            }
        }
        
        return [
            'total_found' => count($birthdayUsers),
            'total_sent' => $sentCount,
            'date' => date('d/m/Y')
        ];
    }
    
    private function sendBirthdayWish($user)
    {
        try {
            $template = $this->getActiveTemplate();
            if (!$template) {
                error_log("Nessun template attivo trovato per compleanno");
                return false;
            }

            $personalizedContent = $this->personalizeTemplate($template['html_content'], $user);
            $personalizedSubject = $this->personalizeTemplate($template['subject'], $user);

            if ($this->sendEmail($user['email'], $user['nome'] . ' ' . $user['cognome'], $personalizedSubject, $personalizedContent)) {
                $this->recordSentBirthday($user, $template['id']);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Errore invio auguri compleanno per {$user['email']}: " . $e->getMessage());
            return false;
        }
    }
    
    private function personalizeTemplate($content, $user)
    {
        $replacements = [
            '{{NOME}}' => $user['nome'],
            '{{COGNOME}}' => $user['cognome'],
            '{{NOME_COMPLETO}}' => $user['nome'] . ' ' . $user['cognome'],
            '{{EMAIL}}' => $user['email'],
            '{{DATA_NASCITA}}' => date('d/m/Y', strtotime($user['data_nascita'])),
            '{{ETA}}' => $this->calculateAge($user['data_nascita']),
            '{{ANNO}}' => date('Y')
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
    
    private function calculateAge($birthdate)
    {
        $today = new DateTime();
        $birthday = new DateTime($birthdate);
        return $today->diff($birthday)->y;
    }
    
    private function getActiveTemplate()
    {
        $stmt = $this->pdo->query("SELECT * FROM birthday_templates WHERE is_active = 1 LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function recordSentBirthday($user, $templateId)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO birthday_sent (user_email, user_name, birthday_date, sent_year, template_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user['email'],
            $user['nome'] . ' ' . $user['cognome'],
            $user['data_nascita'],
            date('Y'),
            $templateId
        ]);
    }
    
    private function sendEmail($email, $name, $subject, $htmlContent)
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->config['email']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['email']['username'];
            $mail->Password = $this->config['email']['password'];
            $mail->SMTPSecure = $this->config['email']['encryption'];
            $mail->Port = $this->config['email']['port'];

            $mail->setFrom($this->config['email']['username'], 'MrCharlie Team');
            $mail->addAddress($email, $name);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlContent;
            $mail->CharSet = 'UTF-8';

            return $mail->send();

        } catch (Exception $e) {
            error_log("Errore invio email compleanno: " . $e->getMessage());
            return false;
        }
    }
    
    // API METHODS
    
    public function getAllTemplates()
    {
        $stmt = $this->pdo->query("
            SELECT *, 
                   (SELECT COUNT(*) FROM birthday_sent WHERE template_id = birthday_templates.id) as times_used
            FROM birthday_templates 
            ORDER BY is_active DESC, created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getTemplate($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM birthday_templates WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function saveTemplate($data)
    {
        if (isset($data['id']) && $data['id'] > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE birthday_templates 
                SET name = ?, subject = ?, html_content = ?, background_image = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $result = $stmt->execute([
                $data['name'],
                $data['subject'],
                $data['html_content'],
                $data['background_image'] ?? null,
                $data['id']
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO birthday_templates (name, subject, html_content, background_image) 
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $data['name'],
                $data['subject'],
                $data['html_content'],
                $data['background_image'] ?? null
            ]);
        }

        return $result;
    }
    
    public function activateTemplate($id)
    {
        $this->pdo->beginTransaction();
        
        try {
            $this->pdo->exec("UPDATE birthday_templates SET is_active = 0");
            $stmt = $this->pdo->prepare("UPDATE birthday_templates SET is_active = 1 WHERE id = ?");
            $stmt->execute([$id]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
    
    public function deleteTemplate($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN id = ? THEN 1 ELSE 0 END) as is_target,
                   SUM(CASE WHEN id = ? AND is_active = 1 THEN 1 ELSE 0 END) as is_active
            FROM birthday_templates
        ");
        $stmt->execute([$id, $id]);
        $check = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($check['total'] <= 1) {
            throw new Exception("Non puoi eliminare l'ultimo template");
        }

        if ($check['is_active'] > 0) {
            throw new Exception("Non puoi eliminare il template attivo");
        }

        $stmt = $this->pdo->prepare("DELETE FROM birthday_templates WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function uploadImage($file)
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception("Tipo file non supportato");
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception("File troppo grande (max 5MB)");
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'birthday_' . uniqid() . '.' . $extension;

        // Carica su GCS se configurato
        $config = $this->config ?? null;
        $useGcs = isset($config['gcs']['enabled']) && $config['gcs']['enabled'] === true && !empty($config['gcs']['bucket']);

        if ($useGcs) {
            $prefix = $config['gcs']['uploads_prefix'] ?? 'uploads/';
            $dest = rtrim($prefix, '/') . '/' . $filename;
            $uploader = new \App\Lib\GcsUploader($config['gcs']['bucket']);
            $url = $uploader->upload($dest, $file['tmp_name'], $file['type']);

            $stmt = $this->pdo->prepare("
                INSERT INTO birthday_assets (filename, original_name, file_path, file_type, file_size) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $filename,
                $file['name'],
                $url,
                $file['type'],
                $file['size']
            ]);

            return [
                'filename' => $filename,
                'url' => $url,
                'id' => $this->pdo->lastInsertId()
            ];
        }

        // Fallback: salvataggio locale (sviluppo)
        $uploadDir = __DIR__ . '/birthday_assets/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $filepath = $uploadDir . $filename;
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $stmt = $this->pdo->prepare("
                INSERT INTO birthday_assets (filename, original_name, file_path, file_type, file_size) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $filename,
                $file['name'],
                '/birthday_assets/' . $filename,
                $file['type'],
                $file['size']
            ]);

            return [
                'filename' => $filename,
                'url' => '/birthday_assets/' . $filename,
                'id' => $this->pdo->lastInsertId()
            ];
        }

        throw new Exception("Errore durante l'upload");
    }
    
    public function getBirthdayStats()
    {
        $today = date('m-d');
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as today_birthdays 
            FROM utenti 
            WHERE DATE_FORMAT(data_nascita, '%m-%d') = ? 
            AND data_nascita IS NOT NULL 
            AND data_nascita >= '1000-01-01'
        ");
        $stmt->execute([$today]);
        $todayBirthdays = $stmt->fetch()['today_birthdays'];

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as sent_this_year 
            FROM birthday_sent 
            WHERE sent_year = ?
        ");
        $stmt->execute([date('Y')]);
        $sentThisYear = $stmt->fetch()['sent_this_year'];

        $stmt = $this->pdo->query("
            SELECT COUNT(*) as upcoming_birthdays
            FROM utenti 
            WHERE (
                (DAYOFYEAR(STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(data_nascita), '-', DAY(data_nascita)), '%Y-%m-%d')) 
                BETWEEN DAYOFYEAR(CURDATE()) AND DAYOFYEAR(CURDATE()) + 30)
                OR 
                (DAYOFYEAR(STR_TO_DATE(CONCAT(YEAR(CURDATE()) + 1, '-', MONTH(data_nascita), '-', DAY(data_nascita)), '%Y-%m-%d')) 
                BETWEEN 1 AND (DAYOFYEAR(CURDATE()) + 30 - DAYOFYEAR(STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-12-31'), '%Y-%m-%d'))))
            )
            AND data_nascita IS NOT NULL 
            AND data_nascita >= '1000-01-01'
        ");
        $upcomingBirthdays = $stmt->fetch()['upcoming_birthdays'];

        return [
            'today_birthdays' => $todayBirthdays,
            'sent_this_year' => $sentThisYear,
            'upcoming_birthdays' => $upcomingBirthdays,
            'total_templates' => count($this->getAllTemplates())
        ];
    }
    
    public function getUpcomingBirthdays($days = 30)
    {
        $stmt = $this->pdo->prepare("
            SELECT nome, cognome, email, data_nascita,
                   DATE_FORMAT(data_nascita, '%d/%m') as birthday_display,
                   CASE 
                       WHEN DAYOFYEAR(STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(data_nascita), '-', DAY(data_nascita)), '%Y-%m-%d')) >= DAYOFYEAR(CURDATE())
                       THEN DAYOFYEAR(STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(data_nascita), '-', DAY(data_nascita)), '%Y-%m-%d')) - DAYOFYEAR(CURDATE())
                       ELSE DAYOFYEAR(STR_TO_DATE(CONCAT(YEAR(CURDATE()) + 1, '-', MONTH(data_nascita), '-', DAY(data_nascita)), '%Y-%m-%d')) - DAYOFYEAR(CURDATE()) + DAYOFYEAR(STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-12-31'), '%Y-%m-%d'))
                   END as days_until
            FROM utenti 
            WHERE data_nascita IS NOT NULL 
            AND data_nascita >= '1000-01-01'
            HAVING days_until <= ?
            ORDER BY days_until ASC, MONTH(data_nascita), DAY(data_nascita)
            LIMIT 50
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function testTemplate($templateId, $testEmail)
    {
        $template = $this->getTemplate($templateId);
        if (!$template) {
            return false;
        }

        $testUser = [
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'email' => $testEmail,
            'data_nascita' => '1990-01-01'
        ];

        $personalizedContent = $this->personalizeTemplate($template['html_content'], $testUser);
        $personalizedSubject = '[TEST] ' . $this->personalizeTemplate($template['subject'], $testUser);

        return $this->sendEmail($testEmail, 'Test User', $personalizedSubject, $personalizedContent);
    }
    
    public function createAdvancedTemplates()
    {
        // Forza la creazione dei template avanzati
        try {
            $this->createElegantTemplate();
            $this->createSmartTemplate();
            $this->createMinimalTemplate();
            return ['success' => true, 'message' => 'Template avanzati creati con successo!'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Errore nella creazione dei template: ' . $e->getMessage()];
        }
    }
}

function runBirthdayCheck()
{
    try {
        $bootstrap = require_once __DIR__ . '/../src/bootstrap.php';
        $birthdaySystem = new BirthdaySystem($bootstrap['db'], $bootstrap['config']);
        
        $result = $birthdaySystem->checkAndSendBirthdayWishes();
        
        // Solo output se eseguito da CLI
        if (php_sapi_name() === 'cli') {
            echo "Birthday check completato:\n";
            echo "- Data: {$result['date']}\n";
            echo "- Compleanni trovati: {$result['total_found']}\n";
            echo "- Auguri inviati: {$result['total_sent']}\n";
        }
        
        return $result;
        
    } catch (Exception $e) {
        if (php_sapi_name() === 'cli') {
            echo "Errore durante controllo compleanni: " . $e->getMessage() . "\n";
        }
        error_log("Birthday system error: " . $e->getMessage());
        return false;
    }
}

// Esegui solo se chiamato direttamente da CLI
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    runBirthdayCheck();
} 
