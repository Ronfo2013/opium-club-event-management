<?php
// Abilita visualizzazione errori (ambienti di test)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Avvia la sessione
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Abilita output buffering
ob_start();

// Includi configurazione (rilevamento automatico ambiente)
if (isset($_SERVER['GAE_APPLICATION'])) {
    // Ambiente Google Cloud
    $config = require_once __DIR__ . '/../src/config/config_gcloud.php';
} else {
    // Ambiente locale
    $config = require_once __DIR__ . '/../src/config/config.php';
}

// DEBUG: verifica se il file è stato caricato correttamente
# var_dump($config);
# die();

// Se l'utente è già loggato, reindirizza a admin.php
if (isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true) {
    header("Location: /admin");
    exit;
}

// Se il form viene inviato (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = $_POST["password"] ?? '';
    $correctPassword = $config['login']['password'];

    if ($password === $correctPassword) {
        $_SESSION["logged_in"] = true;
        header("Location: /admin");
        exit;
    } else {
        $_SESSION["error"] = "Password errata.";
        header("Location: /login");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Login Admin</title>
    <style>
    @media (max-width: 600px) {
      .login-container {
        width: 95vw;
        min-width: 0;
        max-width: 97vw;
        padding: 1rem;
        margin: 0 2vw;
      }
      input[type="password"] {
        font-size: 1.2em;
        padding: 14px;
      }
      button {
        font-size: 1.1em;
        padding: 14px;
      }
      h2 {
        font-size: 1.5em;
      }
    }
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 300px;
            text-align: center;
        }
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 1rem 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        .error {
            color: red;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login Admin</h2>
        <?php if (isset($_SESSION["error"])): ?>
            <p class="error"><?php echo htmlspecialchars($_SESSION["error"]); unset($_SESSION["error"]); ?></p>
        <?php endif; ?>
        <form method="POST" action="/login">
            <input type="password" name="password" placeholder="Inserisci la password" required>
            <button type="submit">Accedi</button>
        </form>
    </div>

<?php ob_end_flush(); ?>
</body>
</html>
