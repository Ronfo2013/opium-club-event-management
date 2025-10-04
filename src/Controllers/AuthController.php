<?php

namespace App\Controllers;

class AuthController {
    private ?\PDO $pdo = null;
    private string $correctPassword = 'admin123';

    public function __construct($pdo, $config) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // anche se non usi ancora PDO, questo previene errori futuri
        if ($pdo instanceof \PDO) {
            $this->pdo = $pdo;
        }
        // leggi password admin da configurazione/ambiente se disponibile
        if (is_array($config) && isset($config['login']['password']) && $config['login']['password']) {
            $this->correctPassword = (string)$config['login']['password'];
        } elseif (!empty($_ENV['ADMIN_PASSWORD'])) {
            $this->correctPassword = (string)$_ENV['ADMIN_PASSWORD'];
        }
    }

    public function showLoginPage() {
        if ($this->isLoggedIn()) {
            header("Location: /admin");
            exit;
        }

        require_once __DIR__ . '/../Views/login.php';
    }

    public function login() {
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $password = $_POST["password"] ?? '';

            if ($password === $this->correctPassword) {
                $_SESSION["logged_in"] = true;
                header("Location: /admin");
                exit;
            } else {
                $_SESSION['error'] = "Password errata.";
                header("Location: /login");
                exit;
            }
        } else {
            // Se richiesta non POST, mostra login
            $this->showLoginPage();
        }
    }

    public function logout() {
        session_unset();
        session_destroy();
        header("Location: /login");
        exit;
    }

    private function isLoggedIn() {
        return isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true;
    }
}
