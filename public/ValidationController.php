<?php

namespace App\Controllers;

class ValidationController {
    private $db;
    private $config;
    
    public function __construct($db, $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Valida il token ricevuto.
     * Se il token esiste e non è stato ancora validato, imposta validato = 1.
     * Restituisce un messaggio di conferma, altrimenti segnala l'errore.
     */
    public function validateToken($token) {
        // Sanitize il token (a seconda del formato previsto)
        $token = trim($token);
        if (empty($token)) {
            echo "Token non valido.";
            return;
        }
        
        // Cerca il token nella tabella 'utenti'
        $stmt = $this->db->prepare("SELECT id, validato, nome, cognome FROM utenti WHERE token = :token");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo "Token non trovato.";
            return;
        }
        
        // Se il token è già stato validato, comunica all'utente
        if ($user['validato'] == 1) {
            echo "Token già validato.";
            return;
        }
        
        // Altrimenti, aggiorna il record per impostare validato = 1
        $updateStmt = $this->db->prepare("UPDATE utenti SET validato = 1 WHERE id = :id");
        $updateStmt->execute([':id' => $user['id']]);
        
        echo "Accesso valido. Benvenuto, " . htmlspecialchars($user['nome'] . " " . $user['cognome']) . ".";
    }
}