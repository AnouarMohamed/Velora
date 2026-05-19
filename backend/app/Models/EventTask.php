<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class EventTask extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'event_tasks';

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

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'due_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
