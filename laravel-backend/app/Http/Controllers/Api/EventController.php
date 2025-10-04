<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Display a listing of events.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::query();

        // Filter by date if provided
        if ($request->has('date_from')) {
            $query->where('event_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('event_date', '<=', $request->date_to);
        }

        // Filter by status
        if ($request->has('is_closed')) {
            $query->where('is_closed', $request->boolean('is_closed'));
        }

        // For public access, only show open events
        if (!$request->user()) {
            $query->open()->afterDate(now()->toDateString());
        }

        $events = $query->orderBy('event_date', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Store a newly created event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_date' => 'required|date|after:today',
            'title' => 'required|string|max:255',
            'background_image' => 'nullable|string|max:500',
            'is_closed' => 'boolean',
        ]);

        $event = Event::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Evento creato con successo',
            'data' => $event,
        ], 201);
    }

    /**
     * Display the specified event.
     */
    public function show(Event $event): JsonResponse
    {
        $event->load('users');

        return response()->json([
            'success' => true,
            'data' => $event,
        ]);
    }

    /**
     * Update the specified event.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'event_date' => 'sometimes|date|after:today',
            'title' => 'sometimes|string|max:255',
            'background_image' => 'nullable|string|max:500',
            'is_closed' => 'boolean',
        ]);

        $event->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Evento aggiornato con successo',
            'data' => $event,
        ]);
    }

    /**
     * Remove the specified event.
     */
    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Evento eliminato con successo',
        ]);
    }

    /**
     * Close an event.
     */
    public function close(Event $event): JsonResponse
    {
        $event->update(['is_closed' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Evento chiuso con successo',
            'data' => $event,
        ]);
    }

    /**
     * Reopen an event.
     */
    public function reopen(Event $event): JsonResponse
    {
        $event->update(['is_closed' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Evento riaperto con successo',
            'data' => $event,
        ]);
    }

    /**
     * Get event statistics.
     */
    public function stats(Event $event): JsonResponse
    {
        $stats = $event->stats;
        $stats['hourly_validations'] = $this->getHourlyValidationStats($event);
        $stats['recent_registrations'] = $event->users()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Send bulk emails for an event.
     */
    public function sendBulkEmails(Event $event): JsonResponse
    {
        $results = $this->emailService->sendBulkEventEmails($event);

        return response()->json([
            'success' => true,
            'message' => 'Invio email completato',
            'data' => $results,
        ]);
    }

    /**
     * Get hourly validation statistics.
     */
    private function getHourlyValidationStats(Event $event): array
    {
        $validations = $event->users()
            ->whereNotNull('validated_at')
            ->selectRaw('HOUR(validated_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $stats = [];
        for ($i = 0; $i < 24; $i++) {
            $stats[$i] = $validations->where('hour', $i)->first()->count ?? 0;
        }

        return $stats;
    }
}






