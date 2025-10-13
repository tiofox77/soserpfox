<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Period extends Model
{
    protected $table = 'accounting_periods';
    
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'date_start',
        'date_end',
        'state',
        'closed_by',
        'closed_at',
    ];
    
    protected $casts = [
        'date_start' => 'date',
        'date_end' => 'date',
        'closed_at' => 'datetime',
    ];
    
    // Relações
    public function moves(): HasMany
    {
        return $this->hasMany(Move::class);
    }
    
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
