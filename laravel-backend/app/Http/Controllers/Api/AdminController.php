<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Models\EmailText;
use App\Services\BirthdayService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    private BirthdayService $birthdayService;

    public function __construct(BirthdayService $birthdayService)
    {
        $this->birthdayService = $birthdayService;
    }

    /**
     * Get dashboard statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'events' => [
                'total' => Event::count(),
                'open' => Event::open()->count(),
                'closed' => Event::where('is_closed', true)->count(),
                'upcoming' => Event::open()->afterDate(now()->toDateString())->count(),
            ],
            'users' => [
                'total' => User::count(),
                'validated' => User::validated()->count(),
                'pending_validation' => User::pendingValidation()->count(),
                'emails_sent' => User::emailStatus('sent')->count(),
                'emails_failed' => User::emailStatus('failed')->count(),
                'emails_pending' => User::emailStatus('pending')->count(),
            ],
            'recent_activity' => [
                'recent_registrations' => User::with('event')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get(),
                'recent_validations' => User::with('event')
                    ->whereNotNull('validated_at')
                    ->orderBy('validated_at', 'desc')
                    ->limit(10)
                    ->get(),
            ],
            'birthday_stats' => $this->birthdayService->getBirthdayStats(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get email texts.
     */
    public function getEmailTexts(): JsonResponse
    {
        $texts = EmailText::getAllAsArray();

        return response()->json([
            'success' => true,
            'data' => $texts,
        ]);
    }

    /**
     * Update email texts.
     */
    public function updateEmailTexts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'texts' => 'required|array',
            'texts.*' => 'required|string',
        ]);

        foreach ($validated['texts'] as $key => $value) {
            EmailText::setByKey($key, $value);
        }

        return response()->json([
            'success' => true,
            'message' => 'Testi email aggiornati con successo',
        ]);
    }

    /**
     * Send birthday emails.
     */
    public function sendBirthdayEmails(): JsonResponse
    {
        $results = $this->birthdayService->sendBirthdayEmails();

        return response()->json([
            'success' => true,
            'message' => 'Invio email compleanno completato',
            'data' => $results,
        ]);
    }

    /**
     * Get upcoming birthdays.
     */
    public function getUpcomingBirthdays(Request $request): JsonResponse
    {
        $days = $request->get('days', 7);
        $birthdays = $this->birthdayService->getUpcomingBirthdays($days);

        return response()->json([
            'success' => true,
            'data' => $birthdays,
        ]);
    }

    /**
     * Get system health status.
     */
    public function health(): JsonResponse
    {
        $health = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'email' => $this->checkEmail(),
            'queue' => $this->checkQueue(),
        ];

        $overall = !in_array(false, $health);

        return response()->json([
            'success' => $overall,
            'data' => $health,
        ]);
    }

    /**
     * Check database connection.
     */
    private function checkDatabase(): bool
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check storage permissions.
     */
    private function checkStorage(): bool
    {
        try {
            $testFile = 'test_' . time() . '.txt';
            \Storage::disk('public')->put($testFile, 'test');
            \Storage::disk('public')->delete($testFile);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check email configuration.
     */
    private function checkEmail(): bool
    {
        try {
            $emailService = app(\App\Services\EmailService::class);
            return $emailService->testEmailConfiguration();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check queue connection.
     */
    private function checkQueue(): bool
    {
        try {
            \Queue::size();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Export data.
     */
    public function export(Request $request): JsonResponse
    {
        $type = $request->get('type', 'users');
        $eventId = $request->get('event_id');

        switch ($type) {
            case 'users':
                $query = User::with('event');
                if ($eventId) {
                    $query->where('event_id', $eventId);
                }
                $data = $query->get();
                break;

            case 'events':
                $data = Event::with('users')->get();
                break;

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo di export non supportato',
                ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}






