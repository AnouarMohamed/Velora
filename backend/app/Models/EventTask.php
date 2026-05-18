<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTask extends Model
{
    protected $table = 'event_tasks';

    protected $fillable = [
        'event_id',
        'title',
        'description',
        'is_done',
        'due_at',
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
}
