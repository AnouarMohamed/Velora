<?php

namespace App\Models;

use App\Models\Concerns\StoresMoneyAsCents;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use MongoDB\Laravel\Eloquent\Model;

class Registration extends Model
{
    use StoresMoneyAsCents;

    protected $connection = 'mongodb';

    protected $table = 'registrations';

    protected $appends = ['amount'];

    protected $hidden = ['amount_cents'];

    protected $fillable = [
        'event_id',
        'user_id',
        'ticket_type',
        'status',
        'payment_status',
        'ticket_code',
        'amount',
        'amount_cents',
        'paid_at',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    protected function amount(): Attribute
    {
        return $this->moneyAttribute('amount_cents');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
