<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'currency_from_id',
        'currency_to_id',
        'date',
        'rate',
        'source',
    ];

    protected $casts = [
        'date' => 'date',
        'rate' => 'decimal:6',
    ];

    public function currencyFrom()
    {
        return $this->belongsTo(Currency::class, 'currency_from_id');
    }

    public function currencyTo()
    {
        return $this->belongsTo(Currency::class, 'currency_to_id');
    }
}
