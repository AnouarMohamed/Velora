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

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

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
}
