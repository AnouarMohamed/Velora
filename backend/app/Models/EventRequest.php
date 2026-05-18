<?php

namespace App\Models;

use App\Models\Concerns\HasPublicImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventRequest extends Model
{
    use HasPublicImage;

    protected $appends = ['image_url'];

    protected $fillable = [
        'title',
        'description',
        'image_path',
        'preferred_start',
        'preferred_end',
        'location',
        'ticket_price',
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
            'ticket_price' => 'decimal:2',
        ];
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

        $hasActiveApproved = static::query()
            ->where('contact_email', $email)
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereDoesntHave('event')
                    ->orWhereHas('event', function ($e) {
                        $e->where('status', '!=', 'published')
                            ->orWhereRaw('COALESCE(end_at, start_at) >= ?', [now()]);
                    });
            })
            ->exists();

        return $hasActiveApproved ? 'active_event' : null;
    }

    public static function clientHasBlockingRequest(string $email): bool
    {
        return static::clientBlockingReason($email) !== null;
    }
}
