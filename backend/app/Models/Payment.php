<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class Payment extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'payments';

    protected $fillable = [
        'registration_id',
        'amount',
        'currency',
        'status',
        'transaction_id',
        'method',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
