<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Administrateur',
            'email' => 'admin@demo.local',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $organizer = User::create([
            'name' => 'Organisateur',
            'email' => 'organisateur@demo.local',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ORGANIZER,
        ]);

        User::create([
            'name' => 'Participant',
            'email' => 'participant@demo.local',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PARTICIPANT,
        ]);

        User::create([
            'name' => 'Client',
            'email' => 'client@demo.local',
            'password' => Hash::make('password'),
            'role' => User::ROLE_CLIENT,
        ]);

        $event = Event::create([
            'event_request_id' => null,
            'organizer_id' => $organizer->id,
            'created_by' => $organizer->id,
            'title' => 'Salon Tech & Innovation',
            'description' => 'Une journée de conférences et ateliers autour des outils modernes.',
            'location' => 'Paris, Hall A',
            'start_at' => now()->addWeeks(2),
            'end_at' => now()->addWeeks(2)->addHours(6),
            'capacity' => 200,
            'registered_count' => 0,
            'ticket_price' => 15,
            'status' => 'published',
        ]);

        $event->tasks()->createMany([
            ['title' => 'Valider le catering', 'description' => null, 'is_done' => false, 'due_at' => now()->addWeek()],
            ['title' => 'Brief équipe accueil', 'description' => null, 'is_done' => true, 'due_at' => now()->addDays(5)],
        ]);

        $event->activities()->createMany([
            ['title' => 'Accueil & café', 'starts_at' => $event->start_at, 'ends_at' => $event->start_at->copy()->addHour(), 'sort_order' => 1],
            ['title' => 'Keynote', 'starts_at' => $event->start_at->copy()->addHour(), 'ends_at' => $event->start_at->copy()->addHours(3), 'sort_order' => 2],
        ]);

        EventRequest::create([
            'title' => 'Gala de fin d’année',
            'description' => 'Soirée de 150 personnes avec scène et DJ.',
            'preferred_start' => now()->addMonths(2),
            'preferred_end' => now()->addMonths(2)->addHours(5),
            'location' => 'Lyon',
            'contact_name' => 'Client démo',
            'contact_email' => 'client@demo.local',
            'contact_phone' => '+33600000000',
            'status' => 'pending',
        ]);
    }
}
