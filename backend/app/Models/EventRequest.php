<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRequest extends Model
{
    protected $appends = ['image_url'];

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'image_path',
        'location',
        'start_at',
        'end_at',
        'capacity',
        'budget',
        'status',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'budget' => 'decimal:2',
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! empty($this->attributes['image_path'])) {
            return '/storage/'.ltrim(str_replace('\\', '/', $this->attributes['image_path']), '/');
        }

        return null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

