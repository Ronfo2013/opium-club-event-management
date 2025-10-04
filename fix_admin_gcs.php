<?php
// Script per correggere il bug GCS in admin.php
$adminFile = __DIR__ . '/public/admin.php';
$content = file_get_contents($adminFile);

// 1. Aggiungi useGcs = false nella sezione locale (riga ~1212)
$content = preg_replace('/(\$heroImages = file_exists\(\$heroJsonPath\) \? json_decode\(file_get_contents\(\$heroJsonPath\), true\) : \[\];)/', "$1\n            \$useGcs = false;", $content);

// 2. Sostituisci file_put_contents con logica condizionale (riga 1229)
$content = preg_replace('/(@file_put_contents\(\$heroJsonPath, json_encode\(\$heroImages, JSON_PRETTY_PRINT\)\);)/', 
"if (\$useGcs) {\n                        try {\n                            \$gcsUploader = new \\App\\Lib\\GcsUploader(\$config['gcs']['bucket']);\n                            \$gcsUploader->uploadString('hero_images.json', json_encode(\$heroImages, JSON_PRETTY_PRINT), 'application/json');\n                        } catch (\\Throwable \$e) {\n                            error_log('Errore salvataggio hero_images.json su GCS: ' . \$e->getMessage());\n                            @file_put_contents(\$heroJsonPath, json_encode(\$heroImages, JSON_PRETTY_PRINT));\n                        }\n                    } else {\n                        $1\n                    }", $content);

// 3. Stessa correzione per la seconda posizione (riga ~1819)
$content = preg_replace('/(\@file_put_contents\(\$heroJsonPath, json_encode\(\$heroImages, JSON_PRETTY_PRINT\)\);\s+echo json_encode)/', 
"if (\$useGcs) {\n                        try {\n                            \$gcsUploader = new \\App\\Lib\\GcsUploader(\$config['gcs']['bucket']);\n                            \$gcsUploader->uploadString('hero_images.json', json_encode(\$heroImages, JSON_PRETTY_PRINT), 'application/json');\n                        } catch (\\Throwable \$e) {\n                            error_log('Errore salvataggio hero_images.json su GCS: ' . \$e->getMessage());\n                            @file_put_contents(\$heroJsonPath, json_encode(\$heroImages, JSON_PRETTY_PRINT));\n                        }\n                    } else {\n                        @file_put_contents(\$heroJsonPath, json_encode(\$heroImages, JSON_PRETTY_PRINT));\n                    }\n                    echo json_encode", $content);

file_put_contents($adminFile, $content);
echo "Correzioni applicate con successo!\n";
