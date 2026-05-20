<?php

namespace App\Models;

use App\Models\Concerns\StoresMoneyAsCents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Event Model
 *
 * Represents an event managed within the system. Events can be created by clients
 * (as requests) and then managed by organizers or admins.
 *
 * @property string $_id MongoDB document ID
 * @property string|null $event_request_id ID of the originating event request
 * @property string|null $organizer_id ID of the assigned organizer
 * @property string $created_by ID of the user who created the event record
 * @property string $title Event title
 * @property string|null $description Detailed event description
 * @property string|null $image_path Path to the event image in storage
 * @property string|null $location Physical location or venue name
 * @property string|null $room Specific room or sub-location
 * @property Carbon|null $start_at Event start date and time
 * @property Carbon|null $end_at Event end date and time
 * @property int $capacity Maximum number of participants
 * @property int $registered_count Current number of registered participants
 * @property int $ticket_price_cents Ticket price in cents
 * @property string $status Event status (draft, pending_publication, published, cancelled, completed)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $image_url Computed absolute URL for the event image
 * @property-read float $ticket_price Computed ticket price in main currency units
 * @property-read EventRequest|null $eventRequest Originating request for this event
 * @property-read User|null $organizer Assigned organizer
 * @property-read User|null $creator User who created the record
 * @property-read Collection|EventTask[] $tasks Associated planning tasks
 * @property-read Collection|EventActivity[] $activities Associated event activities
 * @property-read Collection|Registration[] $registrations Participant registrations
 * @property-read Collection|Feedback[] $feedbacks Participant feedback
 */
class Event extends Model
{
    use StoresMoneyAsCents;

    /** Status constants */
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_PUBLICATION = 'pending_publication';

    public const STATUS_PUBLISHED = 'published';

    /**
     * The database connection used by the model.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    /**
     * The table/collection associated with the model.
     *
     * @var string
     */
    protected $table = 'events';

    /**
     * Accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['image_url', 'ticket_price'];

    /**
     * Attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['ticket_price_cents'];

    /**
     * Attributes that are mass assignable.
     *
     * @var list<string>
     */
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
        'ticket_price_cents',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    /**
     * Accessor for the ticket price, converting cents to decimal.
     */
    protected function ticketPrice(): Attribute
    {
        return $this->moneyAttribute('ticket_price_cents');
    }

    /**
     * Accessor for the image URL.
     */
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

    /**
     * Get the originating event request.
     */
    public function eventRequest(): BelongsTo
    {
        return $this->belongsTo(EventRequest::class);
    }

    /**
     * Get the assigned organizer.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Get the user who created the event record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the associated planning tasks.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(EventTask::class);
    }

    /**
     * Get the associated event activities.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(EventActivity::class);
    }

    /**
     * Get the participant registrations.
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Get the participant feedbacks.
     */
    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Check if a given user is an organizer of this event.
     * Admins are always considered organizers.
     */
    public function isOrganizer(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->organizer_id === $user->id || $this->created_by === $user->id;
    }

    /**
     * Check if the event has already finished based on the end time.
     */
    public function isFinished(): bool
    {
        $endsAt = $this->end_at ?? $this->start_at;

        return $endsAt !== null && $endsAt->lte(now());
    }

    /**
     * Scope a query to only include events that haven't finished yet.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeNotFinished($query)
    {
        return $query->where(function ($q) {
            $q->where('end_at', '>=', now())
                ->orWhere(function ($q2) {
                    $q2->whereNull('end_at')->where('start_at', '>=', now());
                });
        });
    }

    /**
     * Scope a query to only include events that have already finished.
     *
     * @param  Builder  $query
     * @return Builder
     */
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
