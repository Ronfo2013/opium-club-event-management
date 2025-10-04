<?php
/**
 * Override di configurazione per forzare Google Cloud in produzione
 */

// Log per debugging
error_log('[CONFIG_OVERRIDE] HTTP_HOST: ' . ($_SERVER['HTTP_HOST'] ?? 'non-set'));
error_log('[CONFIG_OVERRIDE] GAE_APPLICATION originale: ' . ($_SERVER['GAE_APPLICATION'] ?? 'non-set'));

// Forza Google Cloud se siamo su eventi.opiumpordenone.com
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'eventi.opiumpordenone.com') !== false) {
    $_SERVER['GAE_APPLICATION'] = 'eventi-opiumpordenone';
    error_log('[CONFIG_OVERRIDE] Forzato GAE_APPLICATION per dominio produzione');
}

error_log('[CONFIG_OVERRIDE] GAE_APPLICATION finale: ' . ($_SERVER['GAE_APPLICATION'] ?? 'non-set'));
?>
