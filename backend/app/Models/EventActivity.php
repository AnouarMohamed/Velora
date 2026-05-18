<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class EventActivity extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'event_activities';

    protected $fillable = [
        'event_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'sort_order',
        'location',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
