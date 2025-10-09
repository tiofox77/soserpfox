<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Technician extends Model
{
    use SoftDeletes;
    
    protected $table = 'events_technicians';
    
    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'email',
        'phone',
        'document',
        'address',
        'specialties',
        'level',
        'hourly_rate',
        'daily_rate',
        'is_active',
        'is_available',
        'notes',
        'photo',
        'birth_date',
        'hire_date',
    ];
    
    protected $casts = [
        'specialties' => 'array',
        'hourly_rate' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'birth_date' => 'date',
        'hire_date' => 'date',
    ];
    
    // Relacionamentos
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
    
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'events_team_members')
            ->withPivot('role')
            ->withTimestamps();
    }
    
    public function movements(): HasMany
    {
        return $this->hasMany(EquipmentMovement::class);
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }
    
    public function scopeBySpecialty($query, $specialty)
    {
        return $query->whereJsonContains('specialties', $specialty);
    }
    
    // Métodos auxiliares
    public function getFullNameAttribute(): string
    {
        return $this->name;
    }
    
    public function hasSpecialty(string $specialty): bool
    {
        return in_array($specialty, $this->specialties ?? []);
    }
}
