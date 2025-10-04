<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailText;

class EmailTextSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $emailTexts = [
            'subject' => 'Iscrizione Confermata - {evento}',
            'header_title' => 'Iscrizione Confermata',
            'header_subtitle' => 'Opium Club Pordenone',
            'greeting_message' => 'La tua registrazione è stata completata con successo. Tutti i dettagli sono confermati.',
            'qr_title' => 'Codice QR di Accesso',
            'qr_description' => 'Il QR Code ti servirà per l\'accesso all\'evento',
            'qr_note' => 'Conserva il PDF allegato e presentalo all\'ingresso',
            'instructions_title' => 'Informazioni Importanti',
            'instruction_1' => 'Porta con te il PDF allegato (digitale o stampato)',
            'instruction_2' => 'Arriva in tempo per l\'ingresso',
            'instruction_3' => 'Il QR Code è personale e non trasferibile',
            'instruction_4' => 'Per modifiche o cancellazioni, contattaci immediatamente',
            'status_message' => 'Tutto pronto per l\'evento',
            'footer_title' => 'Opium Club Pordenone',
            'footer_subtitle' => 'Il tuo locale di fiducia per eventi indimenticabili',
            'footer_email' => 'info@opiumpordenone.com',
            'footer_location' => 'Pordenone, Italia',
            'footer_disclaimer' => 'Questa email è stata generata automaticamente. Per assistenza, rispondi a questa email.',
        ];

        foreach ($emailTexts as $key => $value) {
            EmailText::create([
                'text_key' => $key,
                'text_value' => $value,
            ]);
        }
    }
}






