<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    public const STATUS_PENDING_PUBLICATION = 'pending_publication';

    public function indexAll(Request $request)
    {
        $q = Event::query()->with(['organizer', 'eventRequest', 'creator:id,name,role'])->latest();

        if ($search = $request->query('q')) {
            $q->where(function ($query) use ($search) {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('location', 'like', '%'.$search.'%');
            });
        }

        return response()->json($q->paginate(30));
    }

    public function indexMine(Request $request)
    {
        $user = $request->user();
        $events = Event::query()
            ->where(function ($q) use ($user) {
                $q->where('organizer_id', $user->id)
                    ->orWhere('created_by', $user->id);
            })
            ->with(['eventRequest', 'organizer'])
            ->latest()
            ->paginate(30);

        return response()->json($events);
    }

    /** Événements assignés à un organisateur ou créés par un organisateur. */
    public function indexOrganizerSpace(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $events = Event::query()
            ->where(function ($q) {
                $q->whereHas('organizer', fn ($q) => $q->where('role', User::ROLE_ORGANIZER))
                    ->orWhereHas('creator', fn ($q) => $q->where('role', User::ROLE_ORGANIZER));
            })
            ->with(['organizer', 'eventRequest', 'creator:id,name,role'])
            ->latest()
            ->paginate(30);

        return response()->json($events);
    }

    /** Événements assignés à l'admin ou créés par l'admin. */
    public function indexAssignedToMe(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        $events = Event::query()
            ->where(function ($q) use ($user) {
                $q->where('organizer_id', $user->id)
                    ->orWhere('created_by', $user->id);
            })
            ->with(['eventRequest', 'organizer'])
            ->latest()
            ->paginate(30);

        return response()->json($events);
    }

    public function browsePublished(Request $request)
    {
        $q = Event::query()
            ->where('status', 'published')
            ->where('start_at', '>=', now()->subDay())
            ->with(['organizer', 'eventRequest'])
            ->orderBy('start_at');

        if ($search = $request->query('q')) {
            $q->where(function ($query) use ($search) {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('location', 'like', '%'.$search.'%');
            });
        }

        return response()->json($q->paginate(20));
    }

    public function show(Request $request, Event $event)
    {
        if ($event->status !== 'published' && ! $this->canManage($request, $event)) {
            abort(404);
        }

        return response()->json($event->load(['organizer', 'eventRequest', 'tasks', 'activities']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'capacity' => ['required', 'integer', 'min:1'],
            'ticket_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:draft,published,cancelled,pending_publication'],
            'image_data' => ['nullable', 'string'],
            'image_mime' => ['nullable', 'string', 'in:image/jpeg,image/png,image/webp,image/gif'],
        ]);

        try {
            $imagePath = $this->resolveStoreImagePath($request);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        $user = $request->user();
        $status = $data['status'] ?? 'draft';
        if (! $user->isAdmin()) {
            $status = $status === self::STATUS_PENDING_PUBLICATION
                ? self::STATUS_PENDING_PUBLICATION
                : 'draft';
        }

        $event = Event::create([
            'event_request_id' => null,
            'organizer_id' => $user->id,
            'created_by' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'image_path' => $imagePath,
            'location' => $data['location'] ?? null,
            'room' => $data['room'] ?? null,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'capacity' => $data['capacity'],
            'registered_count' => 0,
            'ticket_price' => $data['ticket_price'] ?? 0,
            'status' => $status,
        ]);

        NotificationService::organizerEventCreated($event, $user);

        if ($status === 'published') {
            NotificationService::eventPublished($event);
        }

        return response()->json($event, 201);
    }

    /** Image envoyée en base64 (data URL), comme pour les demandes d'événement. */
    private function resolveStoreImagePath(Request $request): ?string
    {
        if (! $request->filled('image_data')) {
            return null;
        }

        $raw = $request->input('image_data');
        if (str_contains($raw, ',')) {
            $raw = explode(',', $raw, 2)[1];
        }

        $bytes = base64_decode($raw, true);
        if ($bytes === false) {
            throw ValidationException::withMessages([
                'image_data' => ['Image invalide.'],
            ]);
        }

        if (strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'image_data' => ['L\'image ne doit pas dépasser 2 Mo.'],
            ]);
        }

        $mime = $request->input('image_mime', 'image/jpeg');
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $path = 'events/'.Str::uuid().'.'.$ext;
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    public function update(Request $request, Event $event)
    {
        abort_unless($this->canManage($request, $event), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date', 'after:start_at'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'ticket_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:draft,published,completed,cancelled,pending_publication'],
        ]);

        $user = $request->user();
        if (isset($data['status']) && ! $user->isAdmin()) {
            if ($data['status'] === 'published') {
                return response()->json([
                    'message' => 'Seul un administrateur peut publier l’événement. Envoyez une demande de publication.',
                ], 422);
            }
            if (! in_array($data['status'], ['draft', self::STATUS_PENDING_PUBLICATION, 'cancelled'], true)) {
                unset($data['status']);
            }
        }

        if (isset($data['capacity']) && $data['capacity'] < $event->registered_count) {
            return response()->json([
                'message' => 'La capacité ne peut pas être inférieure au nombre d’inscrits.',
            ], 422);
        }

        $wasPublished = $event->status === 'published';
        $previousStatus = $event->status;

        $event->update($data);
        $event->refresh();

        if ($user->isAdmin() && NotificationService::organizerIdsForEvent($event) !== []) {
            NotificationService::eventUpdatedByAdmin($event);
        }

        if (! $wasPublished && $event->status === 'published') {
            if ($previousStatus === self::STATUS_PENDING_PUBLICATION) {
                NotificationService::publicationApproved($event);
            } else {
                NotificationService::eventPublished($event);
            }
        }

        return response()->json($event);
    }

    public function updateCapacity(Request $request, Event $event)
    {
        abort_unless($this->canManage($request, $event), 403);

        $data = $request->validate([
            'capacity' => ['required', 'integer', 'min:1'],
        ]);

        if ($data['capacity'] < $event->registered_count) {
            return response()->json([
                'message' => 'La capacité ne peut pas être inférieure au nombre d’inscrits.',
            ], 422);
        }

        $event->update(['capacity' => $data['capacity']]);

        return response()->json($event);
    }

    public function assignOrganizer(Request $request, Event $event)
    {
        $data = $request->validate([
            'organizer_id' => ['required', 'string'],
        ]);

        $organizer = User::query()
            ->whereKey($data['organizer_id'])
            ->whereIn('role', [User::ROLE_ORGANIZER, User::ROLE_ADMIN])
            ->firstOrFail();

        $event->update(['organizer_id' => $organizer->id]);
        $event->refresh();

        if ($organizer->role === User::ROLE_ORGANIZER) {
            NotificationService::eventAssigned($event, $organizer);
        }

        return response()->json($event->load('organizer'));
    }

    public function destroy(Request $request, Event $event)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $event->delete();

        return response()->json(null, 204);
    }

    /** Organisateur : soumet l'événement à validation admin avant mise en ligne. */
    public function requestPublication(Request $request, Event $event)
    {
        abort_unless($this->canManage($request, $event), 403);
        abort_if($request->user()->isAdmin(), 422, 'Publiez directement depuis l’espace administrateur.');

        if (! in_array($event->status, ['draft', self::STATUS_PENDING_PUBLICATION], true)) {
            return response()->json([
                'message' => 'Cet événement ne peut pas être soumis à publication.',
            ], 422);
        }

        $event->update(['status' => self::STATUS_PENDING_PUBLICATION]);

        NotificationService::publicationRequested($event->fresh(), $request->user());

        return response()->json($event->fresh());
    }

    /** Admin : approuve la demande de publication d'un organisateur. */
    public function approvePublication(Request $request, Event $event)
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($event->status !== self::STATUS_PENDING_PUBLICATION) {
            return response()->json([
                'message' => 'Aucune demande de publication en attente pour cet événement.',
            ], 422);
        }

        $event->update(['status' => 'published']);

        NotificationService::publicationApproved($event->fresh());

        return response()->json($event->fresh());
    }

    private function canManage(Request $request, Event $event): bool
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return true;
        }

        return $event->isOrganizer($user);
    }
}
