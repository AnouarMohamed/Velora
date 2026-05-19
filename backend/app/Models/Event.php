<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

class Event extends Model
{
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_PUBLICATION = 'pending_publication';

    public const STATUS_PUBLISHED = 'published';

    protected $connection = 'mongodb';

    protected $table = 'events';

    protected $appends = ['image_url'];

    protected $fillable = [
        'event_request_id',
        'organizer_id',
        'created_by',
        'title',
        'description',
        'image_path',
        'location',
        'room',
        'start_at',
        'end_at',
        'capacity',
        'registered_count',
        'ticket_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'ticket_price' => 'decimal:2',
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! empty($this->attributes['image_path'])) {
            return '/storage/'.ltrim(str_replace('\\', '/', $this->attributes['image_path']), '/');
        }

        if ($this->relationLoaded('eventRequest') && $this->eventRequest?->image_path) {
            return $this->eventRequest->image_url;
        }

        return null;
    }

    public function eventRequest(): BelongsTo
    {
        return $this->belongsTo(EventRequest::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(EventTask::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(EventActivity::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function isOrganizer(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->organizer_id === $user->id || $this->created_by === $user->id;
    }

    public function isFinished(): bool
    {
        $endsAt = $this->end_at ?? $this->start_at;

        return $endsAt !== null && $endsAt->lte(now());
    }

    public function scopeNotFinished($query)
    {
        return $query->where(function ($q) {
            $q->where('end_at', '>=', now())
                ->orWhere(function ($q2) {
                    $q2->whereNull('end_at')->where('start_at', '>=', now());
                });
        });
    }

    public function scopeFinished($query)
    {
        return $query->where(function ($q) {
            $q->where('end_at', '<', now())
                ->orWhere(function ($q2) {
                    $q2->whereNull('end_at')->where('start_at', '<', now());
                });
        });
    }
}
