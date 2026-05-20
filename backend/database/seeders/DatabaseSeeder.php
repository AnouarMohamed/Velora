<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventActivity;
use App\Models\EventRequest;
use App\Models\EventTask;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->demoUser('admin@demo.local', 'Administrateur', User::ROLE_ADMIN);
        $organizer = $this->demoUser('organisateur@demo.local', 'Organisateur', User::ROLE_ORGANIZER);
        $this->demoUser('participant@demo.local', 'Participant', User::ROLE_PARTICIPANT);
        $client = $this->demoUser('client@demo.local', 'Client', User::ROLE_CLIENT);

        $event = Event::query()->firstOrCreate([
            'title' => 'Salon Tech & Innovation',
        ], [
            'event_request_id' => null,
            'organizer_id' => $organizer->id,
            'created_by' => $organizer->id,
            'description' => 'Une journée de conférences et ateliers autour des outils modernes.',
            'location' => 'Paris, Hall A',
            'start_at' => now()->addWeeks(2),
            'end_at' => now()->addWeeks(2)->addHours(6),
            'capacity' => 200,
            'registered_count' => 0,
            'ticket_price' => 15,
            'status' => 'published',
        ]);

        EventTask::query()->updateOrCreate([
            'event_id' => $event->id,
            'title' => 'Valider le catering',
        ], [
            'description' => null,
            'is_done' => false,
            'due_at' => now()->addWeek(),
        ]);

        EventTask::query()->updateOrCreate([
            'event_id' => $event->id,
            'title' => 'Brief équipe accueil',
        ], [
            'description' => null,
            'is_done' => true,
            'due_at' => now()->addDays(5),
        ]);

        EventActivity::query()->updateOrCreate([
            'event_id' => $event->id,
            'title' => 'Accueil & café',
        ], [
            'starts_at' => $event->start_at,
            'ends_at' => $event->start_at?->copy()->addHour(),
            'sort_order' => 1,
        ]);

        EventActivity::query()->updateOrCreate([
            'event_id' => $event->id,
            'title' => 'Keynote',
        ], [
            'starts_at' => $event->start_at?->copy()->addHour(),
            'ends_at' => $event->start_at?->copy()->addHours(3),
            'sort_order' => 2,
        ]);

        EventRequest::query()->firstOrCreate([
            'title' => 'Gala de fin d’année',
            'contact_email' => 'client@demo.local',
        ], [
            'user_id' => $client->id,
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

    private function demoUser(string $email, string $name, string $role): User
    {
        return User::query()->updateOrCreate([
            'email' => $email,
        ], [
            'name' => $name,
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }
}
