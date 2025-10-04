<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Login Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(135deg, #007BFF, #00c6ff);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-card {
            background: #fff;
            width: 100%;
            max-width: 400px;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            text-align: center;
        }
        .login-card h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .login-card p.error {
            color: #e74c3c;
            margin-bottom: 15px;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        form label {
            text-align: left;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        form input {
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        form input:focus {
            border-color: #007BFF;
            outline: none;
        }
        form button {
            padding: 12px;
            background: #007BFF;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        form button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Login Admin</h2>
        <?php if (isset($_SESSION['error'])): ?>
            <p class="error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
        <?php endif; ?>
        <form method="POST" action="./login">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Accedi</button>
        </form>
    </div>
</body>
</html>