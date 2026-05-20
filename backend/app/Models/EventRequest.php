<?php

namespace App\Models;

use App\Models\Concerns\HasPublicImage;
use App\Models\Concerns\StoresMoneyAsCents;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * EventRequest Model
 *
 * Represents a request made by a client for a new event to be organized.
 * These requests are reviewed by admins and can be approved to create an Event.
 *
 * @property string $_id MongoDB document ID
 * @property string $user_id ID of the client who submitted the request
 * @property string $title Requested event title
 * @property string $description Detailed description of the requested event
 * @property string|null $image_path Path to the requested event banner/image
 * @property Carbon|null $preferred_start Preferred start date and time
 * @property Carbon|null $preferred_end Preferred end date and time
 * @property string|null $location Requested venue or general location
 * @property int $ticket_price_cents Proposed ticket price in cents
 * @property string $contact_name Contact person name
 * @property string $contact_email Contact person email address
 * @property string $contact_phone Contact person phone number
 * @property string $status Request status (pending, approved, rejected)
 * @property string|null $rejection_reason Explanation if the request was rejected
 * @property Carbon|null $reviewed_at Timestamp when the request was reviewed
 * @property string|null $reviewed_by_id ID of the admin who reviewed the request
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $image_url Computed absolute URL for the event image
 * @property-read float $ticket_price Computed ticket price in main currency units
 * @property-read User $user Client who submitted the request
 * @property-read User|null $reviewer Admin who reviewed the request
 * @property-read Event|null $event The actual event created from this request
 */
class EventRequest extends Model
{
    use HasPublicImage;
    use StoresMoneyAsCents;

    /** Status constants */
    public const STATUS_APPROVED = 'approved';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

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
    protected $table = 'event_requests';

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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_start' => 'datetime',
            'preferred_end' => 'datetime',
            'reviewed_at' => 'datetime',
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
     * Define the relationship for the client who made the request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Define the relationship for the admin who reviewed the request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * Define the relationship for the resulting event if approved.
     */
    public function event(): HasOne
    {
        return $this->hasOne(Event::class);
    }
}
