<?php

namespace App\Models;

use App\Models\Concerns\HasPublicImage;
use App\Models\Concerns\StoresMoneyAsCents;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use MongoDB\Laravel\Eloquent\Model;

class EventRequest extends Model
{
    use HasPublicImage;
    use StoresMoneyAsCents;

    protected $connection = 'mongodb';

    protected $table = 'event_requests';

    protected $appends = ['image_url', 'ticket_price'];

    protected $hidden = ['ticket_price_cents'];

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'image_path',
        'preferred_start',
        'preferred_end',
        'location',
        'ticket_price',
        'ticket_price_cents',
        'contact_name',
        'contact_email',
        'contact_phone',
        'status',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'preferred_start' => 'datetime',
            'preferred_end' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected function ticketPrice(): Attribute
    {
        return $this->moneyAttribute('ticket_price_cents');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function event(): HasOne
    {
        return $this->hasOne(Event::class);
    }

    public static function clientBlockingReason(string $email): ?string
    {
        if (static::query()->where('contact_email', $email)->where('status', 'pending')->exists()) {
            return 'pending';
        }

        $approvedRequestIds = static::query()
            ->where('contact_email', $email)
            ->where('status', 'approved')
            ->pluck('id')
            ->all();

        if ($approvedRequestIds === []) {
            return null;
        }

        $eventsByRequestId = Event::query()
            ->whereIn('event_request_id', $approvedRequestIds)
            ->get()
            ->keyBy(fn ($event) => (string) $event->event_request_id);

        foreach ($approvedRequestIds as $approvedRequestId) {
            $event = $eventsByRequestId->get((string) $approvedRequestId);

            if (! $event || $event->status !== 'published' || ! $event->isFinished()) {
                return 'active_event';
            }
        }

        return null;
    }

    public static function clientHasBlockingRequest(string $email): bool
    {
        return static::clientBlockingReason($email) !== null;
    }
}
