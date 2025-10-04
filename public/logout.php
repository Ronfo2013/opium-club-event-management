<?php
// Abilita visualizzazione errori (ambienti di test)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

session_unset();
session_destroy();
header("Location: /login.php");
exit;