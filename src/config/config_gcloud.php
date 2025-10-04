<?php
/**
 * Configurazione per Google Cloud Platform
 * Sostituisce config.php per l'ambiente di produzione
 */

return [
    'db' => [
        'host'     => getenv('DB_HOST') ?: '/cloudsql/premium-origin-471808-u0:us-central1:opium-db',
        'dbname'   => getenv('DB_NAME') ?: 'form_qrcode',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: 'Camilla',
        'port'     => 3306,
        'unix_socket' => getenv('DB_HOST') ?: '/cloudsql/premium-origin-471808-u0:us-central1:opium-db', // Per Cloud SQL
        'charset'  => 'utf8mb4',
        'options'  => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],
    'email' => [
        'host'       => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'username'   => getenv('SMTP_USER') ?: 'info@mrcharlie.net',
        'password'   => getenv('SMTP_PASS') ?: 'syex tkyy xfzx yssy',
        'port'       => (int)(getenv('SMTP_PORT') ?: 587),
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls'
    ],
    'paths' => [
        'qr_code_dir'         => '/tmp/qrcodes/',
        'assets_dir'          => __DIR__ . '/../../public/assets/',
        'uploads_dir'         => '/tmp/uploads/',
        'generated_images_dir'=> '/tmp/generated_images/',
        'generated_pdfs_dir'  => '/tmp/generated_pdfs/'
    ],
    'gcs' => [
        'bucket' => getenv('GCS_BUCKET') ?: null,
        'enabled' => (bool) getenv('GCS_BUCKET'),
        'uploads_prefix' => 'uploads/',
        'hero_prefix' => 'hero_images/'
    ],
    'login' => [
        'password' => getenv('ADMIN_PASSWORD') ?: 'Cami'
    ],
    'app' => [
        'env' => getenv('APP_ENV') ?: 'production',
        'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'url' => getenv('APP_URL') ?: 'https://your-app-id.appspot.com'
    ]
];
