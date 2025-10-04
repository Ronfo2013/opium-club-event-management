<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Event;
use App\Services\QRCodeService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    protected $qrCodeService;
    protected $emailService;

    public function __construct(QRCodeService $qrCodeService, EmailService $emailService)
    {
        $this->qrCodeService = $qrCodeService;
        $this->emailService = $emailService;
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
            'data' => $users
        ]);
    }

    /**
     * Store a newly created user registration.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:50',
                'birth_date' => 'required|date|before:today',
                'event_id' => 'required|exists:events,id',
            ]);

            // Check if event is still open
            $event = Event::findOrFail($validated['event_id']);
            if ($event->is_closed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le registrazioni per questo evento sono chiuse'
                ], 422);
            }

            // Check if email already exists for this event
            $existingUser = User::where('email', $validated['email'])
                ->where('event_id', $validated['event_id'])
                ->first();

            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email già registrata per questo evento'
                ], 422);
            }

            // Generate QR token
            $qrToken = User::generateQrToken();

            // Create user
            $user = User::create([
                ...$validated,
                'qr_token' => $qrToken,
                'qr_code_path' => '', // Will be set after QR generation
                'email_status' => 'pending',
            ]);

            // Generate QR code
            $qrCodePath = $this->qrCodeService->generateQRCode($user);

            // Update user with QR code path
            $user->update(['qr_code_path' => $qrCodePath]);

            // Send welcome email with QR code
            $this->emailService->sendWelcomeEmail($user);

            return response()->json([
                'success' => true,
                'data' => $user->load('event'),
                'message' => 'Registrazione completata con successo'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dati non validi',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('event');

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Validate a user's QR code.
     */
    public function validateQR(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'qr_token' => 'required|string',
            ]);

            $user = User::where('qr_token', $validated['qr_token'])->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR code non trovato'
                ], 404);
            }

            if ($user->is_validated) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR code già validato',
                    'data' => $user
                ], 422);
            }

            $user->markAsValidated();

            return response()->json([
                'success' => true,
                'message' => 'QR code validato con successo',
                'data' => $user
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dati non validi',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Resend email to user.
     */
    public function resendEmail(User $user): JsonResponse
    {
        try {
            $this->emailService->sendWelcomeEmail($user);

            return response()->json([
                'success' => true,
                'message' => 'Email reinviata con successo'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'invio dell\'email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        try {
            $validated = $request->validate([
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255',
                'phone' => 'sometimes|string|max:50',
                'birth_date' => 'sometimes|date|before:today',
                'is_validated' => 'boolean',
            ]);

            $user->update($validated);

            return response()->json([
                'success' => true,
                'data' => $user->load('event'),
                'message' => 'Utente aggiornato con successo'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dati non validi',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        // Delete QR code file if exists
        if ($user->qr_code_path && Storage::exists($user->qr_code_path)) {
            Storage::delete($user->qr_code_path);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utente eliminato con successo'
        ]);
    }

    /**
     * Get user statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'validated_users' => User::validated()->count(),
            'pending_validation' => User::pendingValidation()->count(),
            'emails_sent' => User::emailStatus('sent')->count(),
            'emails_failed' => User::emailStatus('failed')->count(),
            'emails_pending' => User::emailStatus('pending')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}





