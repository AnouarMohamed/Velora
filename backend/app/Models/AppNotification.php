<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class AppNotification extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
