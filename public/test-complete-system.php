<?php
// Test del sistema completo identico a save_form.php
error_log('[TEST-COMPLETE] Inizio test sistema completo');

// Simula i dati POST e metodo HTTP
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'nome' => 'Test',
    'cognome' => 'Sistema',
    'email' => 'test.sistema@test.com',
    'telefono' => '1234567890',
    'data-nascita' => '1990-01-01',
    'evento' => '10',
    'test_mode' => 'true'
];

// Includi il file save_form.php originale per confronto
echo "=== TEST SISTEMA COMPLETO ===\n";
echo "Dati di test:\n";
echo "- Nome: " . $_POST['nome'] . "\n";
echo "- Cognome: " . $_POST['cognome'] . "\n";
echo "- Email: " . $_POST['email'] . "\n";
echo "- Telefono: " . $_POST['telefono'] . "\n";
echo "- Data nascita: " . $_POST['data-nascita'] . "\n";
echo "- Evento: " . $_POST['evento'] . "\n";
echo "- Test mode: " . $_POST['test_mode'] . "\n\n";

echo "=== FASE 1: VALIDAZIONE ===\n";
ob_start();
include __DIR__ . '/api/save-form-complete.php';
$validationOutput = ob_get_clean();
echo "Output validazione: " . $validationOutput . "\n\n";

$validationResponse = json_decode($validationOutput, true);
if (!$validationResponse || !$validationResponse['success']) {
    echo "ERRORE: Validazione fallita\n";
    exit;
}

echo "=== FASE 2: GENERAZIONE PDF ===\n";
// Simula input per generazione PDF
$_POST = json_encode($validationResponse['data']);
ob_start();
include __DIR__ . '/api/generate-pdf-complete.php';
$pdfOutput = ob_get_clean();
echo "Output generazione PDF: " . $pdfOutput . "\n\n";

$pdfResponse = json_decode($pdfOutput, true);
if (!$pdfResponse || !$pdfResponse['success']) {
    echo "ERRORE: Generazione PDF fallita\n";
    exit;
}

echo "=== FASE 3: INVIO EMAIL ===\n";
// Simula input per invio email
$emailData = [
    'user_id' => $pdfResponse['data']['user_id'],
    'pdf_path' => $pdfResponse['data']['pdf_path'],
    'test_mode' => $validationResponse['data']['test_mode']
];
$_POST = json_encode($emailData);
ob_start();
include __DIR__ . '/api/send-email-complete.php';
$emailOutput = ob_get_clean();
echo "Output invio email: " . $emailOutput . "\n\n";

$emailResponse = json_decode($emailOutput, true);

echo "=== RISULTATO FINALE ===\n";
if ($emailResponse && $emailResponse['success']) {
    echo "✅ SUCCESSO: Sistema completo funzionante!\n";
    echo "Dettagli:\n";
    echo "- User ID: " . $pdfResponse['data']['user_id'] . "\n";
    echo "- Token: " . $pdfResponse['data']['token'] . "\n";
    echo "- PDF URL: " . $pdfResponse['data']['pdf_url'] . "\n";
    echo "- Email: " . $validationResponse['data']['email'] . "\n";
} else {
    echo "❌ ERRORE: Invio email fallito\n";
    echo "Dettagli: " . $emailOutput . "\n";
}

echo "\n=== TEST COMPLETATO ===\n";
?>
