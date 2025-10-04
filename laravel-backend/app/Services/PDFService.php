<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use TCPDF;

class PDFService
{
    /**
     * Generate PDF with user information and QR code.
     */
    public function generateUserPDF(User $user): string
    {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Opium Club');
        $pdf->SetAuthor('Opium Club Pordenone');
        $pdf->SetTitle('Biglietto Evento - ' . $user->event->title);
        $pdf->SetSubject('Biglietto per evento');

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 10, 'OPIUM CLUB PORDENONE', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $user->event->title, 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Data: ' . $user->event->event_date->format('d/m/Y'), 0, 1, 'C');

        // Add some space
        $pdf->Ln(10);

        // User information
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'INFORMAZIONI UTENTE', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 6, 'Nome: ' . $user->first_name . ' ' . $user->last_name, 0, 1, 'L');
        $pdf->Cell(0, 6, 'Email: ' . $user->email, 0, 1, 'L');
        $pdf->Cell(0, 6, 'Telefono: ' . $user->phone, 0, 1, 'L');
        $pdf->Cell(0, 6, 'Data di nascita: ' . $user->birth_date->format('d/m/Y'), 0, 1, 'L');

        // Add QR code
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'QR CODE PER L\'ACCESSO', 0, 1, 'C');

        // Get QR code image
        $qrCodeService = app(QRCodeService::class);
        $qrCodeUrl = $qrCodeService->getQRCodeUrl($user);
        
        if ($qrCodeUrl) {
            $qrCodePath = storage_path('app/public/' . $user->qr_code_path);
            if (file_exists($qrCodePath)) {
                $pdf->Image($qrCodePath, 75, $pdf->GetY(), 60, 60, 'PNG');
            }
        }

        // Add footer
        $pdf->SetY(-30);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'Questo biglietto è valido solo per l\'evento specificato.', 0, 0, 'C');
        $pdf->Ln();
        $pdf->Cell(0, 10, 'Opium Club Pordenone - Sistema di gestione eventi', 0, 0, 'C');

        // Generate filename
        $filename = 'biglietto_' . $user->id . '_' . time() . '.pdf';
        $filepath = storage_path('app/temp/' . $filename);

        // Create temp directory if it doesn't exist
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        // Output PDF
        $pdf->Output($filepath, 'F');

        return $filepath;
    }

    /**
     * Generate birthday PDF for user.
     */
    public function generateBirthdayPDF(User $user): string
    {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Opium Club');
        $pdf->SetAuthor('Opium Club Pordenone');
        $pdf->SetTitle('Auguri di Compleanno - ' . $user->first_name);
        $pdf->SetSubject('Auguri di compleanno');

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->Cell(0, 15, '🎉 BUON COMPLEANNO! 🎉', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'Caro/a ' . $user->first_name, 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 14);
        $pdf->Cell(0, 8, 'Ti auguriamo un fantastico compleanno!', 0, 1, 'C');
        $pdf->Cell(0, 8, 'Grazie per essere parte della famiglia Opium Club.', 0, 1, 'C');

        // Add some space
        $pdf->Ln(20);

        // Birthday message
        $pdf->SetFont('helvetica', '', 12);
        $message = "Il team di Opium Club Pordenone ti augura un meraviglioso compleanno! " .
                  "Speriamo di vederti presto ai nostri eventi per celebrare insieme questa giornata speciale.";
        
        $pdf->MultiCell(0, 6, $message, 0, 'J');

        // Add footer
        $pdf->SetY(-30);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'Opium Club Pordenone - Auguri di Compleanno', 0, 0, 'C');

        // Generate filename
        $filename = 'compleanno_' . $user->id . '_' . time() . '.pdf';
        $filepath = storage_path('app/temp/' . $filename);

        // Create temp directory if it doesn't exist
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        // Output PDF
        $pdf->Output($filepath, 'F');

        return $filepath;
    }

    /**
     * Generate event statistics PDF.
     */
    public function generateEventStatsPDF(Event $event): string
    {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Opium Club');
        $pdf->SetAuthor('Opium Club Pordenone');
        $pdf->SetTitle('Statistiche Evento - ' . $event->title);
        $pdf->SetSubject('Statistiche evento');

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'STATISTICHE EVENTO', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, $event->title, 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 6, 'Data: ' . $event->event_date->format('d/m/Y'), 0, 1, 'C');

        // Add some space
        $pdf->Ln(10);

        // Get statistics
        $stats = $event->stats;

        // Statistics table
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'STATISTICHE REGISTRAZIONI', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(100, 6, 'Totale registrazioni:', 0, 0, 'L');
        $pdf->Cell(0, 6, $stats['total_registrations'], 0, 1, 'L');
        
        $pdf->Cell(100, 6, 'Registrazioni validate:', 0, 0, 'L');
        $pdf->Cell(0, 6, $stats['validated_registrations'], 0, 1, 'L');
        
        $pdf->Cell(100, 6, 'Registrazioni in attesa:', 0, 0, 'L');
        $pdf->Cell(0, 6, $stats['pending_registrations'], 0, 1, 'L');
        
        $pdf->Cell(100, 6, 'Email inviate:', 0, 0, 'L');
        $pdf->Cell(0, 6, $stats['emails_sent'], 0, 1, 'L');
        
        $pdf->Cell(100, 6, 'Email fallite:', 0, 0, 'L');
        $pdf->Cell(0, 6, $stats['emails_failed'], 0, 1, 'L');

        // Add footer
        $pdf->SetY(-30);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'Opium Club Pordenone - Statistiche Evento', 0, 0, 'C');

        // Generate filename
        $filename = 'stats_evento_' . $event->id . '_' . time() . '.pdf';
        $filepath = storage_path('app/temp/' . $filename);

        // Create temp directory if it doesn't exist
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        // Output PDF
        $pdf->Output($filepath, 'F');

        return $filepath;
    }
}