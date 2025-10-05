<?php

namespace App\Models\Invoicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportHistory extends Model
{
    protected $table = 'invoicing_import_history';
    
    protected $fillable = [
        'import_id',
        'user_id',
        'event_type',
        'old_status',
        'new_status',
        'description',
        'metadata',
    ];
    
    protected $casts = [
        'metadata' => 'array',
    ];
    
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
