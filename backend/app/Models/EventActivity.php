<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * EventActivity Model
 *
 * Represents a specific activity or schedule item within an event.
 * Activities help break down an event into a timeline for participants.
 *
 * @property string $_id MongoDB document ID
 * @property string $event_id ID of the parent event
 * @property string $title Title of the activity
 * @property string|null $description Detailed description of the activity
 * @property Carbon $starts_at Start time of the activity
 * @property Carbon|null $ends_at End time of the activity
 * @property int $sort_order Priority order for displaying the activity
 * @property string|null $location Specific location within the venue for this activity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event Parent event this activity belongs to
 */
class EventActivity extends Model
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
    protected $table = 'event_activities';

    /**
     * Attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'sort_order',
        'location',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Define the relationship for the parent event.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
