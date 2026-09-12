<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class EventType extends Model
{
    use BelongsToTenant;

    protected $table = 'events_types';
    
    protected $fillable = [
        'tenant_id',
        'name',
        'icon',
        'color',
        'description',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    // Relacionamento com tenant
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    // Relacionamento com eventos
    public function events()
    {
        return $this->hasMany(Event::class, 'type_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('name');
    }

    /**
     * O ARGUMENTO PASSOU A SER OPCIONAL.
     *
     * Era o único `forTenant` da casa que EXIGIA o id — todos os outros o
     * assumem da empresa activa —, e quem o chamasse como chama os restantes
     * levava um erro de argumento em falta.
     */
    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?: activeTenantId());
    }
}
