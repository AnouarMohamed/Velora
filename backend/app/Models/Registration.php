<?php

namespace App\Models;

use App\Models\Concerns\StoresMoneyAsCents;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Registration Model
 *
 * Represents a participant's registration for a specific event.
 * It tracks the ticket details, payment status, and attendance information.
 *
 * @property string $_id MongoDB document ID
 * @property string $event_id ID of the event
 * @property string $user_id ID of the participant user
 * @property string $ticket_type Type of ticket (e.g., early_bird, general, vip)
 * @property string $status Registration status (e.g., pending, confirmed, cancelled)
 * @property string $payment_status Payment status (e.g., unpaid, paid, refunded)
 * @property string|null $ticket_code Unique code assigned to the ticket
 * @property int $amount_cents Total amount for the registration in cents
 * @property Carbon|null $paid_at Timestamp when the registration was fully paid
 * @property Carbon $registered_at Timestamp when the registration was created
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read float $amount Computed registration amount in main currency units
 * @property-read Event|null $event Event associated with this registration
 * @property-read User|null $user Participant who registered
 * @property-read Payment|null $payment The primary payment record
 * @property-read Collection|Payment[] $payments All payment records associated with this registration
 */
class Registration extends Model
{
    use StoresMoneyAsCents;

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
    protected $table = 'registrations';

    /**
     * Accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['amount'];

    /**
     * Attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['amount_cents'];

    /**
     * Attributes that are mass assignable.
     *
     * @var list<string>
     */
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    /**
     * Accessor for the registration amount, converting cents to decimal.
     */
    protected function amount(): Attribute
    {
        return $this->moneyAttribute('amount_cents');
    }

    /**
     * Define the relationship for the associated event.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Define the relationship for the registered participant.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Define the relationship for the primary payment record.
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Define the relationship for all associated payment records.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
