<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'is_active',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'is_active' => 'boolean',
    ];

    public function exchangeRatesFrom()
    {
        return $this->hasMany(ExchangeRate::class, 'currency_from_id');
    }

    public function exchangeRatesTo()
    {
        return $this->hasMany(ExchangeRate::class, 'currency_to_id');
    }
}
