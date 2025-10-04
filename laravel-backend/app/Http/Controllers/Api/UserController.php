<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event;
use App\Services\QRCodeService;
use App\Services\PDFService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private QRCodeService $qrCodeService;
    private PDFService $pdfService;
    private EmailService $emailService;

    public function __construct(
        QRCodeService $qrCodeService,
        PDFService $pdfService,
        EmailService $emailService
    ) {
        $this->qrCodeService = $qrCodeService;
        $this->pdfService = $pdfService;
        $this->emailService = $emailService;
    }

    /**
     * Register a new user for an event.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'birth_date' => 'required|date|before:-16 years',
            'event_id' => 'required|exists:events,id',
        ]);

        // Check if event exists and is open
        $event = Event::findOrFail($validated['event_id']);
        if ($event->is_closed) {
            return response()->json([
                'success' => false,
                'message' => 'Evento non disponibile o chiuso',
            ], 400);
        }

        // Check if user is already registered for this event
        $existingUser = User::where('email', $validated['email'])
            ->where('event_id', $validated['event_id'])
            ->first();

        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'Sei già registrato a questo evento',
            ], 400);
        }

        try {
            // Generate QR token
            $qrToken = $this->qrCodeService->generateUniqueToken();

            // Create user
            $user = User::create([
                ...$validated,
                'qr_token' => $qrToken,
                'qr_code_path' => '', // Will be set after QR generation
            ]);

            // Generate QR code
            $qrCodePath = $this->qrCodeService->generateQRCode($qrToken);
            $user->update(['qr_code_path' => $qrCodePath]);

            // Generate PDF
            $pdfPath = $this->pdfService->generateEventPass($user, $event);

            // Send email
            $emailSent = $this->emailService->sendEventRegistration($user, $event, $pdfPath);

            return response()->json([
                'success' => true,
                'message' => 'Iscrizione completata con successo',
                'data' => [
                    'user' => $user,
                    'event' => $event,
                    'email_sent' => $emailSent,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore durante la registrazione: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('event');

        // Filter by event
        if ($request->has('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        // Filter by validation status
        if ($request->has('is_validated')) {
            $query->where('is_validated', $request->boolean('is_validated'));
        }

        // Filter by email status
        if ($request->has('email_status')) {
            $query->where('email_status', $request->email_status);
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('event');

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:50',
            'last_name' => 'sometimes|string|max:50',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|string|max:20',
            'birth_date' => 'sometimes|date|before:-16 years',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Utente aggiornato con successo',
            'data' => $user,
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        // Delete QR code file
        $this->qrCodeService->deleteQRCode($user->qr_token);

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utente eliminato con successo',
        ]);
    }

    /**
     * Resend email for a user.
     */
    public function resendEmail(User $user): JsonResponse
    {
        try {
            $success = $this->emailService->resendEventRegistration($user, $user->event);

            return response()->json([
                'success' => $success,
                'message' => $success ? 'Email inviata con successo' : 'Errore nell\'invio dell\'email',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'invio dell\'email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search users.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        
        if (empty($query)) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $users = User::with('event')
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                  ->orWhere('last_name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Get user statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->has('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        $stats = [
            'total_users' => $query->count(),
            'validated_users' => $query->clone()->where('is_validated', true)->count(),
            'pending_validation' => $query->clone()->where('is_validated', false)->count(),
            'emails_sent' => $query->clone()->where('email_status', 'sent')->count(),
            'emails_failed' => $query->clone()->where('email_status', 'failed')->count(),
            'emails_pending' => $query->clone()->where('email_status', 'pending')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}






