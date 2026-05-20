<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Feedback Model
 *
 * Represents feedback submitted by a participant after an event has concluded.
 * Feedback includes a numerical rating and optional text comments.
 *
 * @property string $_id MongoDB document ID
 * @property string $event_id ID of the event being rated
 * @property string|null $user_id ID of the participant who submitted the feedback
 * @property int $rating Numerical rating (e.g., 1-5)
 * @property string|null $comment Optional text comments from the participant
 * @property string $status Moderation status (pending, approved)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event|null $event Event associated with this feedback
 * @property-read User|null $user Participant who provided the feedback
 */
class Feedback extends Model
{
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
    protected $table = 'feedbacks';

    /** Status constants */
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    /**
     * Attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'user_id',
        'rating',
        'comment',
        'status',
    ];

    /**
     * Scope a query to only include approved feedback.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Define the relationship for the associated event.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Define the relationship for the participant who provided feedback.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
