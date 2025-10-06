<?php

namespace App\Models\Events;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    use BelongsToTenant;

    protected $table = 'events_equipment';

    protected $fillable = [
        'tenant_id', 'name', 'code', 'category', 'specifications',
        'daily_price', 'quantity', 'quantity_available', 'status', 'notes',
    ];

    protected $casts = [
        'daily_price' => 'decimal:2',
    ];

    public function eventEquipment() { return $this->hasMany(EventEquipment::class); }

    public function getCategoryLabelAttribute()
    {
        return match($this->category) {
            'audio' => 'Áudio',
            'video' => 'Vídeo',
            'iluminacao' => 'Iluminação',
            'streaming' => 'Streaming',
            'led' => 'LED',
            'estrutura' => 'Estrutura',
            'outros' => 'Outros',
            default => $this->category,
        };
    }
}
