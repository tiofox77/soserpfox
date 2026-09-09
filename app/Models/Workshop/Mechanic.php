<?php

namespace App\Models\Workshop;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O MECÂNICO PERTENCE A UMA EMPRESA — e o escopo global é que o garante.
 *
 * Os componentes filtravam por `tenant_id` em quase todo o lado e esqueciam-se
 * num ou noutro: `Mechanic::find($this->editingId)->update(...)` sobre uma
 * propriedade pública do Livewire, que o browser define, e um
 * `findOrFail($id)` no «ver». Filtrar os sítios à mão deixa o próximo aberto;
 * o escopo fecha-os todos e os que vierem.
 */
class Mechanic extends Model
{
    use SoftDeletes, BelongsToTenant;
    
    protected $table = 'workshop_mechanics';
    
    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'email',
        'phone',
        'mobile',
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
    
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
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
