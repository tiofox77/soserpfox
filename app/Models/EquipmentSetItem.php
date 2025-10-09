<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentSetItem extends Model
{
    protected $table = 'events_equipment_set_items';
    
    protected $fillable = [
        'equipment_set_id',
        'equipment_id',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    // Relacionamentos
    public function equipmentSet(): BelongsTo
    {
        return $this->belongsTo(EquipmentSet::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
