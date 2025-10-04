-- Migrazione per aggiungere campi di tracking status email
-- Eseguire questo script sul database per aggiungere i nuovi campi

-- Aggiungi campo per lo status dell'email
ALTER TABLE utenti ADD COLUMN email_status ENUM('inviata', 'errore', 'in_attesa') DEFAULT 'in_attesa' AFTER email_inviata;

-- Aggiungi campo per memorizzare l'errore dell'email
ALTER TABLE utenti ADD COLUMN email_error TEXT NULL AFTER email_status;

-- Aggiorna i record esistenti: se email_inviata è presente, imposta status a 'inviata'
UPDATE utenti SET email_status = 'inviata' WHERE email_inviata IS NOT NULL;

-- Aggiungi indice per migliorare le performance delle query
CREATE INDEX idx_email_status ON utenti(email_status);

-- Commenti sui nuovi campi
-- email_status: 'inviata' = email inviata con successo, 'errore' = errore nell'invio, 'in_attesa' = non ancora inviata
-- email_error: contiene il messaggio di errore se email_status = 'errore'
