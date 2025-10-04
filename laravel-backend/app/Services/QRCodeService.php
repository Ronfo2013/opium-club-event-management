<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QRCodeService
{
    /**
     * Generate QR code for a user.
     */
    public function generateQRCode(User $user): string
    {
        // Create directory if it doesn't exist
        $directory = 'qrcodes/' . $user->event_id;
        if (!Storage::exists($directory)) {
            Storage::makeDirectory($directory);
        }

        // Generate filename
        $filename = $user->id . '_' . $user->qr_token . '.png';
        $filepath = $directory . '/' . $filename;

        // Generate QR code
        $qrCode = QrCode::format('png')
            ->size(300)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($user->qr_token);

        // Save to storage
        Storage::put($filepath, $qrCode);

        return $filepath;
    }

    /**
     * Generate QR code as base64 string.
     */
    public function generateQRCodeBase64(string $token): string
    {
        $qrCode = QrCode::format('png')
            ->size(200)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($token);

        return 'data:image/png;base64,' . base64_encode($qrCode);
    }

    /**
     * Get QR code URL for a user.
     */
    public function getQRCodeUrl(User $user): ?string
    {
        if (!$user->qr_code_path || !Storage::exists($user->qr_code_path)) {
            return null;
        }

        return Storage::url($user->qr_code_path);
    }

    /**
     * Delete QR code file.
     */
    public function deleteQRCode(User $user): bool
    {
        if ($user->qr_code_path && Storage::exists($user->qr_code_path)) {
            return Storage::delete($user->qr_code_path);
        }

        return true;
    }

    /**
     * Regenerate QR code for a user.
     */
    public function regenerateQRCode(User $user): string
    {
        // Delete old QR code
        $this->deleteQRCode($user);

        // Generate new one
        return $this->generateQRCode($user);
    }
}