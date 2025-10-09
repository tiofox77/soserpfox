<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EquipmentSet extends Model
{
    use BelongsToTenant, SoftDeletes;
    
    protected $table = 'events_equipment_sets';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'category_id',
        'image_path',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relacionamentos
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'category_id');
    }

    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'events_equipment_set_items')
            ->withPivot('quantity', 'notes')
            ->withTimestamps();
    }

    public function setItems(): HasMany
    {
        return $this->hasMany(EquipmentSetItem::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_equipment')
            ->withPivot('quantity', 'start_datetime', 'end_datetime', 'status', 'notes')
            ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Métodos
    public function getTotalEquipmentsAttribute(): int
    {
        return $this->setItems()->sum('quantity');
    }

    public function isAvailable(): bool
    {
        return $this->is_active && $this->equipments->every(fn($eq) => $eq->status === 'disponivel');
    }
}
