<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationController extends Controller
{
    /** Colonnes événement chargées avec les inscriptions participant. */
    private function registrationEventSelect(): array
    {
        return [
            'id',
            'event_request_id',
            'title',
            'description',
            'start_at',
            'end_at',
            'location',
            'room',
            'ticket_price',
            'status',
            'image_path',
        ];
    }

    private function registrationEventWith(): array
    {
        return [
            'event' => fn ($q) => $q->select($this->registrationEventSelect()),
            'event.eventRequest' => fn ($q) => $q->select('id', 'image_path'),
        ];
    }

    public function store(Request $request, Event $event)
    {
        abort_unless($request->user()->role === \App\Models\User::ROLE_PARTICIPANT, 403);

        if ($event->status !== 'published') {
            return response()->json(['message' => 'Événement non ouvert aux inscriptions.'], 422);
        }

        return DB::transaction(function () use ($request, $event) {
            $event->refresh();
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($locked->registered_count >= $locked->capacity) {
                return response()->json(['message' => 'Événement complet.'], 422);
            }

            $existing = Registration::where('event_id', $locked->id)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($existing) {
                return response()->json(['message' => 'Déjà inscrit.', 'registration' => $existing], 422);
            }

            $registration = Registration::create([
                'event_id' => $locked->id,
                'user_id' => $request->user()->id,
                'status' => 'registered',
                'payment_status' => (float) $locked->ticket_price <= 0 ? 'paid' : 'pending',
                'ticket_code' => (string) Str::uuid(),
                'amount' => $locked->ticket_price,
                'paid_at' => (float) $locked->ticket_price <= 0 ? now() : null,
            ]);

            $locked->increment('registered_count');

            if ((float) $locked->ticket_price <= 0) {
                Payment::create([
                    'registration_id' => $registration->id,
                    'amount' => 0,
                    'currency' => 'EUR',
                    'status' => 'completed',
                    'method' => 'free',
                    'meta' => ['note' => 'Gratuit'],
                ]);
            }

            $registration->load('event', 'user');
            NotificationService::participantRegistered($registration);

            return response()->json($registration, 201);
        });
    }

    public function pay(Request $request, Registration $registration)
    {
        $user = $request->user();
        abort_unless($registration->user_id === $user->id, 403);
        abort_unless($user->role === \App\Models\User::ROLE_PARTICIPANT, 403);

        if ($registration->payment_status === 'paid') {
            return response()->json(['message' => 'Déjà payé.', 'registration' => $registration]);
        }

        $amount = (float) $registration->amount;

        return DB::transaction(function () use ($registration, $amount) {
            $registration->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            Payment::create([
                'registration_id' => $registration->id,
                'amount' => $amount,
                'currency' => 'EUR',
                'status' => 'completed',
                'method' => 'card_mock',
                'meta' => ['simulated' => true],
            ]);

            $registration = $registration->fresh($this->registrationEventWith());
            NotificationService::participantPaid($registration);

            return response()->json($registration);
        });
    }

    public function destroy(Request $request, Registration $registration)
    {
        $user = $request->user();
        abort_unless($registration->user_id === $user->id, 403);
        abort_unless($user->role === \App\Models\User::ROLE_PARTICIPANT, 403);

        if ($registration->payment_status === 'paid') {
            return response()->json([
                'message' => 'Impossible d\'annuler une inscription déjà payée.',
            ], 422);
        }

        return DB::transaction(function () use ($registration) {
            $event = Event::query()->whereKey($registration->event_id)->lockForUpdate()->firstOrFail();
            $registration->delete();

            if ($event->registered_count > 0) {
                $event->decrement('registered_count');
            }

            return response()->json(['message' => 'Inscription annulée.']);
        });
    }

    public function myRegistrationForEvent(Request $request, Event $event)
    {
        abort_unless($request->user()->role === \App\Models\User::ROLE_PARTICIPANT, 403);

        $registration = Registration::query()
            ->where('user_id', $request->user()->id)
            ->where('event_id', $event->id)
            ->with($this->registrationEventWith())
            ->first();

        return response()->json(['registration' => $registration]);
    }

    public function myRegistrations(Request $request)
    {
        $query = Registration::query()
            ->where('user_id', $request->user()->id)
            ->with($this->registrationEventWith())
            ->latest();

        if ($request->filled('payment_status')) {
            $status = $request->string('payment_status')->toString();
            if (in_array($status, ['paid', 'pending'], true)) {
                $query->where('payment_status', $status);
            }
        }

        return response()->json($query->paginate(20));
    }

    public function ticket(Request $request, Registration $registration): StreamedResponse
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        abort_unless($registration->payment_status === 'paid', 422, 'Paiement requis pour le billet.');

        $registration->load('event', 'user');
        $payload = [
            'ticket' => $registration->ticket_code,
            'event' => $registration->event->title,
            'participant' => $registration->user->name,
            'starts_at' => $registration->event->start_at->toIso8601String(),
            'location' => $registration->event->location,
        ];

        $filename = 'billet-'.$registration->id.'.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
