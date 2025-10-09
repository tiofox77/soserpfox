<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $table = 'events_teams';
    
    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'leader_id',
        'type',
        'is_active',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
    ];
    
    // Relacionamentos
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
    
    public function leader(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'leader_id');
    }
    
    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(Technician::class, 'events_team_members')
            ->withPivot('role')
            ->withTimestamps();
    }
    
    public function movements(): HasMany
    {
        return $this->hasMany(EquipmentMovement::class);
    }
    
    public function reports(): HasMany
    {
        return $this->hasMany(EventReport::class);
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
    
    // Métodos auxiliares
    public function getMembersCountAttribute(): int
    {
        return $this->technicians()->count();
    }
}
