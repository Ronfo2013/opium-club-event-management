<?php

namespace App\Models;

class Form {
    private $db;
    public $id;
    public $nome;
    public $cognome;
    public $email;
    public $telefono;
    public $data_nascita;
    public $evento;
    public $qr_code_path;
    public $token; // Aggiungi questa proprietà
    public $created_at;

    public function __construct($db) {
        $this->db = $db;
    }

    public function save() {
        $sql = "INSERT INTO utenti (nome, cognome, email, telefono, data_nascita, evento, qr_code_path, token) 
                VALUES (:nome, :cognome, :email, :telefono, :data_nascita, :evento, :qr_code_path, :token)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nome' => $this->nome,
            ':cognome' => $this->cognome,
            ':email' => $this->email,
            ':telefono' => $this->telefono,
            ':data_nascita' => $this->data_nascita,
            ':evento' => $this->evento,
            ':qr_code_path' => $this->qr_code_path,
            ':token' => $this->token
        ]);

        $this->id = $this->db->lastInsertId();
        return true;
    }

    public function findById($id) {
        $sql = "SELECT * FROM utenti WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $result = $stmt->fetch();
        if ($result) {
            foreach ($result as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
            return true;
        }
        return false;
    }
}