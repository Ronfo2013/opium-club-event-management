<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = [
            [
                'event_date' => now()->addDays(7)->format('Y-m-d'),
                'title' => 'Evento Test Capodanno 2025',
                'background_image' => null,
                'is_closed' => false,
            ],
            [
                'event_date' => now()->addDays(14)->format('Y-m-d'),
                'title' => 'Natale Mr.Charlie',
                'background_image' => null,
                'is_closed' => false,
            ],
            [
                'event_date' => now()->addDays(21)->format('Y-m-d'),
                'title' => 'Winter Party',
                'background_image' => null,
                'is_closed' => false,
            ],
            [
                'event_date' => now()->addDays(30)->format('Y-m-d'),
                'title' => 'San Valentino',
                'background_image' => null,
                'is_closed' => false,
            ],
            [
                'event_date' => now()->subDays(5)->format('Y-m-d'),
                'title' => 'Halloween (Evento Passato)',
                'background_image' => null,
                'is_closed' => true,
            ],
        ];

        foreach ($events as $eventData) {
            Event::create($eventData);
        }
    }
}






