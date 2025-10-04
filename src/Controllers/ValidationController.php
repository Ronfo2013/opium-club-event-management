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
        $token = trim($token);
        if (empty($token)) {
            echo "Token non valido.";
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT id, validato, nome, cognome FROM utenti WHERE token = :token");
            $stmt->execute([':token' => $token]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                echo "Token non trovato.";
                return;
            }

            if ($user['validato'] == 1) {
                echo "Token già validato.";
                return;
            }

            $updateStmt = $this->db->prepare("UPDATE utenti SET validato = 1, validated_at = NOW() WHERE id = :id");
            $updateStmt->execute([':id' => $user['id']]);

            echo "Accesso valido. Benvenuto, " . htmlspecialchars($user['nome'] . " " . $user['cognome']) . ".";

        } catch (\PDOException $e) {
            // Log dell'errore reale (es: file di log), qui messaggio generico
            echo "Si è verificato un errore durante la validazione.";
        }
    }
}