<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * AppNotification Model
 *
 * Represents an in-app notification sent to a specific user. This model
 * handles the storage and state (read/unread) of notifications within the application.
 *
 * @property string $_id MongoDB document ID
 * @property string $user_id ID of the recipient user
 * @property string $type Type of notification for categorization
 * @property string $title Notification title
 * @property string $message Detailed notification content
 * @property array|null $data Additional metadata associated with the notification
 * @property Carbon|null $read_at Timestamp when the notification was read
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user Recipient of the notification
 */
class AppNotification extends Model
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
    protected $table = 'app_notifications';

    /**
     * Attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Define the relationship for the recipient user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
