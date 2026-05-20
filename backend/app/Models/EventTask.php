<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * EventTask Model
 *
 * Represents a planning or execution task associated with an event.
 * Tasks are assigned to organizers or staff members to ensure event preparation.
 *
 * @property string $_id MongoDB document ID
 * @property string $event_id ID of the associated event
 * @property string|null $assigned_to ID of the user assigned to this task
 * @property string $title Task title
 * @property string|null $description Detailed task description
 * @property bool $is_done Whether the task has been completed
 * @property Carbon|null $due_at Task deadline
 * @property string $status Current status of the task
 * @property string $priority Task priority level (low, medium, high)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event Event this task belongs to
 * @property-read User|null $assignee User responsible for the task
 */
class EventTask extends Model
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
    protected $table = 'event_tasks';

    /**
     * Attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'assigned_to',
        'title',
        'description',
        'is_done',
        'due_at',
        'status',
        'priority',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'due_at' => 'datetime',
        ];
    }

    /**
     * Define the relationship for the parent event.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Define the relationship for the assigned user.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
