<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventRequest;
use App\Services\EventRequestReviewService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventRequestController extends Controller
{
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    public function __construct(private readonly EventRequestReviewService $reviews) {}

    public function store(Request $request)
    {
        $user = $request->user();

        $request->merge([
            'preferred_start' => $request->input('preferred_start') ?: null,
            'preferred_end' => $request->input('preferred_end') ?: null,
            'contact_phone' => $request->input('contact_phone') ?: null,
            'contact_email' => $user->email,
            'contact_name' => $request->input('contact_name') ?: $user->name,
        ]);

        $blockReason = EventRequest::clientBlockingReason($user->email);
        if ($blockReason !== null) {
            $message = $blockReason === 'pending'
                ? 'Vous avez déjà une demande en attente. Supprimez-la pour en envoyer une nouvelle.'
                : 'Votre événement est encore en cours. Attendez sa fin pour envoyer une nouvelle demande.';

            return response()->json(['message' => $message, 'block_reason' => $blockReason], 422);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'preferred_start' => ['nullable', 'date'],
            'preferred_end' => ['nullable', 'date', 'after_or_equal:preferred_start'],
            'location' => ['nullable', 'string', 'max:255'],
            'ticket_price' => ['required', 'numeric', 'min:0'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:2048'],
            'image_data' => ['nullable', 'string'],
            'image_mime' => ['nullable', 'string', 'in:image/jpeg,image/png,image/webp,image/gif'],
        ]);

        try {
            $imagePath = $this->resolveImagePath($request);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        unset($data['image'], $data['image_data'], $data['image_mime']);

        $eventRequest = EventRequest::create([
            ...$data,
            'user_id' => $user->id,
            'image_path' => $imagePath,
            'status' => 'pending',
        ]);

        NotificationService::eventRequestSubmitted($eventRequest);

        return response()->json($eventRequest->fresh(), 201);
    }

    public function destroy(Request $request, EventRequest $eventRequest)
    {
        $user = $request->user();

        if ($eventRequest->contact_email !== $user->email) {
            return response()->json(['message' => 'Demande introuvable.'], 404);
        }

        if ($eventRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Seules les demandes en attente peuvent être supprimées.',
            ], 422);
        }

        if ($eventRequest->image_path) {
            Storage::disk('public')->delete($eventRequest->image_path);
        }

        $eventRequest->delete();

        return response()->json(['message' => 'Demande supprimée.']);
    }

    private function resolveImagePath(Request $request): ?string
    {
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            return $request->file('image')->store('event-requests', 'public');
        }

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
                'image' => ['Image invalide.'],
            ]);
        }

        if (strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'image' => ['L\'image ne doit pas dépasser 2 Mo.'],
            ]);
        }

        $mime = $request->input('image_mime', 'image/jpeg');
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $path = 'event-requests/'.Str::uuid().'.'.$ext;
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    public function index(Request $request)
    {
        $query = EventRequest::query()
            ->with('event')
            ->orderBy('created_at', 'desc');

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->paginate(20));
    }

    public function review(Request $request, EventRequest $eventRequest)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'rejection_reason' => ['required_if:decision,rejected', 'nullable', 'string'],
        ]);

        if ($data['decision'] === 'rejected') {
            return response()->json($this->reviews->reject(
                $eventRequest,
                $request->user(),
                $data['rejection_reason'] ?? null,
            ));
        }

        return response()->json($this->reviews->approve($eventRequest, $request->user()));
    }
}
