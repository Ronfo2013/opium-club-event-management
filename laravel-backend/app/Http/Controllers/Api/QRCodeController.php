<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QRCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QRCodeController extends Controller
{
    private QRCodeService $qrCodeService;

    public function __construct(QRCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Validate QR code token.
     */
    public function validateToken(Request $request, string $token): JsonResponse
    {
        $validation = $this->qrCodeService->validateQRCode($token);

        if (!$validation) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code non valido o già utilizzato',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'QR Code valido',
            'data' => $validation,
        ]);
    }

    /**
     * Mark QR code as validated.
     */
    public function markValidated(Request $request, string $token): JsonResponse
    {
        $success = $this->qrCodeService->markAsValidated($token);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code non valido o già utilizzato',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'QR Code validato con successo',
        ]);
    }

    /**
     * Get QR code image.
     */
    public function getImage(string $token): JsonResponse
    {
        $url = $this->qrCodeService->getQRCodeUrl($token);

        if (empty($url)) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code non trovato',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'token' => $token,
            ],
        ]);
    }

    /**
     * Generate new QR code for user.
     */
    public function regenerate(Request $request, string $token): JsonResponse
    {
        $user = \App\Models\User::where('qr_token', $token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utente non trovato',
            ], 404);
        }

        try {
            // Delete old QR code
            $this->qrCodeService->deleteQRCode($user->qr_token);

            // Generate new token and QR code
            $newToken = $this->qrCodeService->generateUniqueToken();
            $newQrCodePath = $this->qrCodeService->generateQRCode($newToken);

            // Update user
            $user->update([
                'qr_token' => $newToken,
                'qr_code_path' => $newQrCodePath,
                'is_validated' => false,
                'validated_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'QR Code rigenerato con successo',
                'data' => [
                    'new_token' => $newToken,
                    'qr_code_url' => $newQrCodePath,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore durante la rigenerazione: ' . $e->getMessage(),
            ], 500);
        }
    }
}
