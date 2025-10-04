<?php

namespace App\Models\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    protected $table = 'treasury_banks';
    
    protected $fillable = [
        'name',
        'code',
        'swift_code',
        'country',
        'logo_url',
        'website',
        'phone',
        'is_active',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
    ];
    
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'bank_id');
    }
}
