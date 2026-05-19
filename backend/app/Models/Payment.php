<?php

namespace App\Models;

use App\Models\Concerns\StoresMoneyAsCents;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class Payment extends Model
{
    use StoresMoneyAsCents;

    protected $connection = 'mongodb';

    protected $table = 'payments';

    protected $appends = ['amount'];

    protected $hidden = ['amount_cents'];

    protected $fillable = [
        'registration_id',
        'amount',
        'amount_cents',
        'currency',
        'status',
        'transaction_id',
        'method',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    protected function amount(): Attribute
    {
        return $this->moneyAttribute('amount_cents');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
