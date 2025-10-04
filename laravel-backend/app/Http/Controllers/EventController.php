<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    /**
     * Display a listing of events.
     */
    public function index(): JsonResponse
    {
        $events = Event::with('users')
            ->orderBy('event_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * Get open events for registration.
     */
    public function open(): JsonResponse
    {
        $events = Event::open()
            ->afterDate(now()->toDateString())
            ->orderBy('event_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * Store a newly created event.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'event_date' => 'required|date|after:today',
                'title' => 'required|string|max:255',
                'background_image' => 'nullable|string|max:500',
                'is_closed' => 'boolean',
            ]);

            $event = Event::create($validated);

            return response()->json([
                'success' => true,
                'data' => $event,
                'message' => 'Evento creato con successo'
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
     * Display the specified event.
     */
    public function show(Event $event): JsonResponse
    {
        $event->load('users');

        return response()->json([
            'success' => true,
            'data' => $event
        ]);
    }

    /**
     * Update the specified event.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        try {
            $validated = $request->validate([
                'event_date' => 'sometimes|date',
                'title' => 'sometimes|string|max:255',
                'background_image' => 'nullable|string|max:500',
                'is_closed' => 'boolean',
            ]);

            $event->update($validated);

            return response()->json([
                'success' => true,
                'data' => $event,
                'message' => 'Evento aggiornato con successo'
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
     * Remove the specified event.
     */
    public function destroy(Event $event): JsonResponse
    {
        // Check if event has registrations
        if ($event->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Impossibile eliminare un evento con registrazioni'
            ], 422);
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Evento eliminato con successo'
        ]);
    }

    /**
     * Get event statistics.
     */
    public function stats(Event $event): JsonResponse
    {
        $stats = $event->stats;

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}





