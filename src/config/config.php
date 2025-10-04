<?php

$env = static function (string $key, $default = null) {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
};

$gcsBucket = $env('GCS_BUCKET');

return [
    'db' => [
        'host'        => $env('DB_HOST', 'localhost'),
        'dbname'      => $env('DB_DATABASE', 'form_qrcode'),
        'username'    => $env('DB_USERNAME', 'root'),
        'password'    => $env('DB_PASSWORD', ''),
        'port'        => (int) $env('DB_PORT', 3306),
        'charset'     => 'utf8mb4'
    ],
    'email' => [
        'host'       => $env('SMTP_HOST', 'smtp.ionos.it'),
        'username'   => $env('SMTP_USER', ''),
        'password'   => $env('SMTP_PASS', ''),
        'port'       => (int) $env('SMTP_PORT', 587),
        'encryption' => $env('SMTP_ENCRYPTION', 'tls')
    ],
    'paths' => [
        'qr_code_dir'          => isset($_SERVER['GAE_APPLICATION']) ? '/tmp/qrcodes/' : __DIR__ . '/../../public/qrcodes/',
        'assets_dir'           => __DIR__ . '/../../public/assets/',
        'uploads_dir'          => isset($_SERVER['GAE_APPLICATION']) ? '/tmp/uploads/' : __DIR__ . '/../../public/uploads/',
        'generated_images_dir' => isset($_SERVER['GAE_APPLICATION']) ? '/tmp/generated_images/' : __DIR__ . '/../../public/generated_images/',
        'generated_pdfs_dir'   => isset($_SERVER['GAE_APPLICATION']) ? '/tmp/generated_pdfs/' : __DIR__ . '/../../public/generated_pdfs/'
    ],
    'gcs' => [
        'bucket'         => $gcsBucket,
        'enabled'        => isset($_SERVER['GAE_APPLICATION']) && !empty($gcsBucket),
        'uploads_prefix' => 'uploads/',
        'hero_prefix'    => 'hero_images/'
    ],
    'login' => [
        'password' => $env('ADMIN_PASSWORD', 'Cami')
    ]
];
