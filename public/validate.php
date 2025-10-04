<?php
// public/validate.php

// Carica il bootstrap appropriato per l'ambiente
if (isset($_SERVER['GAE_APPLICATION'])) {
    // Ambiente Google Cloud
    $bootstrap = require_once __DIR__ . '/../src/bootstrap_gcloud.php';
} else {
    // Ambiente locale
    $bootstrap = require_once __DIR__ . '/../src/bootstrap.php';
}

$db     = $bootstrap['db'];
$config = $bootstrap['config'];

// Verifica che il token sia passato tramite GET
if (!isset($_GET['token'])) {
    echo "Token mancante.";
    exit;
}

$token = $_GET['token'];

// Inizializza il controller di validazione e valida il token
$validationController = new \App\Controllers\ValidationController($db, $config);
$validationController->validateToken($token);