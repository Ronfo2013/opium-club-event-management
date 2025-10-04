-- Script di inizializzazione database completo per Docker
-- Basato sull'analisi del codice sorgente dell'applicazione
-- Questo file viene eseguito automaticamente quando il container MySQL parte per la prima volta

CREATE DATABASE IF NOT EXISTS opium_events;
USE opium_events;

-- Tabella principale: UTENTI (iscritti agli eventi)
CREATE TABLE IF NOT EXISTS utenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cognome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telefono VARCHAR(50) NOT NULL,
    data_nascita DATE NOT NULL,
    evento INT NOT NULL,                          -- FK verso events.id
    qr_code_path VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    validato BOOLEAN DEFAULT FALSE,               -- Se il QR code è stato validato
    validated_at TIMESTAMP NULL,                  -- Quando è stato validato
    email_inviata TIMESTAMP NULL,                 -- Quando è stata inviata l'email
    email_status ENUM('inviata', 'errore', 'in_attesa') DEFAULT 'in_attesa',
    email_error TEXT NULL,                        -- Messaggio di errore se invio fallito
    
    INDEX idx_email (email),
    INDEX idx_evento (evento),
    INDEX idx_token (token),
    INDEX idx_email_status (email_status),
    INDEX idx_validato (validato),
    INDEX idx_created_at (created_at)
);

-- Tabella EVENTS (eventi organizzati)
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_date DATE NOT NULL,
    titolo VARCHAR(255) NOT NULL,
    background_image VARCHAR(500) NULL,           -- Immagine di sfondo per l'evento
    chiuso BOOLEAN DEFAULT FALSE,                 -- Se l'evento è chiuso alle iscrizioni
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_event_date (event_date),
    INDEX idx_chiuso (chiuso)
);

-- Tabella SUBMISSIONS (form submissions legacy - ancora usata?)
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    qr_code_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabella EMAIL_TEXTS (testi personalizzabili per le email)
CREATE TABLE IF NOT EXISTS email_texts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    text_key VARCHAR(100) NOT NULL UNIQUE,
    text_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_text_key (text_key)
);

-- Tabella BIRTHDAY_TEMPLATES (sistema compleanno)
CREATE TABLE IF NOT EXISTS birthday_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    html_content TEXT NOT NULL,
    background_image VARCHAR(500) DEFAULT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_is_active (is_active)
);

-- Tabella BIRTHDAY_SENT (tracking auguri inviati)
CREATE TABLE IF NOT EXISTS birthday_sent (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_email VARCHAR(255) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    birthday_date DATE NOT NULL,
    sent_year YEAR NOT NULL,
    template_id INT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_birthday_year (user_email, sent_year),
    FOREIGN KEY (template_id) REFERENCES birthday_templates(id),
    INDEX idx_user_email (user_email),
    INDEX idx_sent_year (sent_year)
);

-- Tabella BIRTHDAY_ASSETS (immagini per sistema compleanno)
CREATE TABLE IF NOT EXISTS birthday_assets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_filename (filename)
);

-- Foreign Key constraint per utenti -> events
ALTER TABLE utenti ADD CONSTRAINT fk_utenti_evento 
    FOREIGN KEY (evento) REFERENCES events(id) ON DELETE CASCADE;

-- Dati di test per sviluppo
INSERT IGNORE INTO events (id, event_date, titolo, chiuso, background_image) VALUES 
(1, '2024-12-31', 'Evento Test Capodanno 2025', FALSE, NULL),
(2, '2024-12-25', 'Natale Mr.Charlie', FALSE, NULL),
(3, '2024-11-01', 'Halloween (Evento Passato)', TRUE, NULL),
(4, '2025-01-15', 'Winter Party', FALSE, NULL),
(5, '2025-02-14', 'San Valentino', FALSE, NULL);

-- Dati di test per email_texts (testi predefiniti)
INSERT IGNORE INTO email_texts (text_key, text_value) VALUES 
('subject', 'Iscrizione Confermata - {evento}'),
('header_title', 'Iscrizione Confermata'),
('header_subtitle', 'Mr.Charlie Lignano Sabbiadoro'),
('greeting_message', 'La tua registrazione è stata completata con successo. Tutti i dettagli sono confermati.'),
('qr_title', 'Codice QR di Accesso'),
('qr_description', 'Il QR Code ti servirà per l\'accesso all\'evento'),
('qr_note', 'Conserva il PDF allegato e presentalo all\'ingresso'),
('instructions_title', 'Informazioni Importanti'),
('instruction_1', 'Porta con te il PDF allegato (digitale o stampato)'),
('instruction_2', 'Arriva in tempo per l\'ingresso'),
('instruction_3', 'Il QR Code è personale e non trasferibile'),
('instruction_4', 'Per modifiche o cancellazioni, contattaci immediatamente'),
('status_message', 'Tutto pronto per l\'evento'),
('footer_title', 'Mr.Charlie Lignano Sabbiadoro'),
('footer_subtitle', 'Il tuo locale di fiducia per eventi indimenticabili'),
('footer_email', 'info@mrcharlie.it'),
('footer_location', 'Lignano Sabbiadoro, Italia'),
('footer_disclaimer', 'Questa email è stata generata automaticamente. Per assistenza, rispondi a questa email.');

-- Utenti di test per sviluppo (con token unici)
INSERT IGNORE INTO utenti (id, nome, cognome, email, telefono, data_nascita, evento, qr_code_path, token, validato) VALUES 
(1, 'Mario', 'Rossi', 'mario.rossi@test.com', '3331234567', '1990-05-15', 1, 'qr_test_mario.png', 'test_token_mario_123', FALSE),
(2, 'Giulia', 'Bianchi', 'giulia.bianchi@test.com', '3339876543', '1995-08-22', 1, 'qr_test_giulia.png', 'test_token_giulia_456', TRUE),
(3, 'Luca', 'Verdi', 'luca.verdi@test.com', '3335555555', '1988-12-03', 2, 'qr_test_luca.png', 'test_token_luca_789', FALSE);

-- Update per utenti di test: simula email inviate e validazioni
UPDATE utenti SET 
    email_inviata = NOW() - INTERVAL 1 DAY,
    email_status = 'inviata',
    validated_at = NOW() - INTERVAL 2 HOUR
WHERE id = 2;

UPDATE utenti SET 
    email_inviata = NOW() - INTERVAL 6 HOUR,
    email_status = 'inviata'
WHERE id IN (1, 3);

-- Crea utente aggiuntivo per l'app (per maggiore sicurezza)
CREATE USER IF NOT EXISTS 'app_user'@'%' IDENTIFIED BY 'app_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON form_qrcode.* TO 'app_user'@'%';
FLUSH PRIVILEGES;

-- Informazioni finali
SELECT 'Database Docker inizializzato con successo!' as Status;
SELECT COUNT(*) as 'Eventi creati' FROM events;
SELECT COUNT(*) as 'Utenti test' FROM utenti;
SELECT COUNT(*) as 'Testi email' FROM email_texts;
