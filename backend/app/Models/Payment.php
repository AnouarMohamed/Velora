<?php

namespace App\Models;

use App\Models\Concerns\StoresMoneyAsCents;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Payment Model
 *
 * Represents a financial transaction associated with an event registration.
 * This model tracks the payment status, amount, and transaction details.
 *
 * @property string $_id MongoDB document ID
 * @property string $registration_id ID of the associated registration
 * @property int $amount_cents Payment amount in cents
 * @property string $currency Currency code (e.g., USD, EUR)
 * @property string $status Payment status (e.g., pending, completed, failed, refunded)
 * @property string|null $transaction_id External transaction ID from the payment provider
 * @property string|null $method Payment method used (e.g., credit_card, paypal)
 * @property array|null $meta Additional metadata from the payment provider
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read float $amount Computed payment amount in main currency units
 * @property-read Registration $registration Registration associated with this payment
 */
class Payment extends Model
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
    protected $table = 'payments';

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
        'registration_id',
        'amount',
        'amount_cents',
        'currency',
        'status',
        'transaction_id',
        'method',
        'meta',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * Accessor for the payment amount, converting cents to decimal.
     */
    protected function amount(): Attribute
    {
        return $this->moneyAttribute('amount_cents');
    }

    /**
     * Define the relationship for the associated registration.
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
