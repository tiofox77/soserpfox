<?php

namespace App\Models\Salon;

use App\Models\Product;

/**
 * Salon Service - Usa a tabela invoicing_products diretamente (type='servico')
 * Compatível com SAFT e faturação
 * Campos específicos do salão são guardados no campo 'description' como JSON
 */
class Service extends Product
{
    protected $appends = ['duration', 'duration_formatted', 'commission_percent', 'commission_fixed', 'online_booking', 'salon_data', 'category_id', 'category', 'text_description'];

    /**
     * Identificador do módulo
     */
    const MODULE = 'salon';

    /**
     * Boot do modelo - filtra apenas serviços do salão
     */
    protected static function boot()
    {
        parent::boot();

        // Sempre filtrar por type='servico' E module='salon'
        static::addGlobalScope('salon_services', function ($builder) {
            $builder->where('type', 'servico')
                    ->where('module', self::MODULE);
        });

        // Ao criar, definir type e module
        static::creating(function ($model) {
            $model->type = 'servico';
            $model->module = self::MODULE;
            $model->manage_stock = false;
        });
    }

    /**
     * Dados específicos do salão guardados no description como JSON
     */
    public function getSalonDataAttribute()
    {
        $desc = $this->attributes['description'] ?? '';
        if ($desc && str_starts_with($desc, '{"salon":')) {
            $data = json_decode($desc, true);
            return $data['salon'] ?? [];
        }
        return [];
    }

    public function getTextDescriptionAttribute()
    {
        $desc = $this->attributes['description'] ?? '';
        if ($desc && str_starts_with($desc, '{"salon":')) {
            $data = json_decode($desc, true);
            return $data['text'] ?? '';
        }
        return $desc;
    }

    public function getDurationAttribute()
    {
        return $this->salon_data['duration'] ?? 30;
    }

    public function getDurationFormattedAttribute()
    {
        $duration = $this->duration;
        $hours = floor($duration / 60);
        $minutes = $duration % 60;
        
        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}min";
        } elseif ($hours > 0) {
            return "{$hours}h";
        }
        return "{$minutes}min";
    }

    public function getCommissionPercentAttribute()
    {
        return $this->salon_data['commission_percent'] ?? 0;
    }

    public function getCommissionFixedAttribute()
    {
        return $this->salon_data['commission_fixed'] ?? 0;
    }

    public function getOnlineBookingAttribute()
    {
        return $this->salon_data['online_booking'] ?? true;
    }

    public function getCategoryIdAttribute()
    {
        return $this->salon_data['category_id'] ?? null;
    }

    /**
     * Atualizar dados específicos do salão
     */
    public function updateSalonData(array $data)
    {
        $salonData = $this->salon_data;
        $salonData = array_merge($salonData, $data);
        
        $this->description = json_encode([
            'salon' => $salonData,
            'text' => $this->text_description,
        ]);
        $this->save();
        return $this;
    }

    /**
     * Criar serviço com dados do salão
     */
    public static function createService(array $data)
    {
        $salonFields = [
            'duration' => $data['duration'] ?? 30,
            'commission_percent' => $data['commission_percent'] ?? 0,
            'commission_fixed' => $data['commission_fixed'] ?? 0,
            'online_booking' => $data['online_booking'] ?? true,
            'category_id' => $data['category_id'] ?? null,
        ];

        $description = json_encode([
            'salon' => $salonFields,
            'text' => $data['text_description'] ?? '',
        ]);

        return self::create([
            'tenant_id' => $data['tenant_id'] ?? activeTenantId(),
            'name' => $data['name'],
            'description' => $description,
            'price' => $data['price'] ?? 0,
            'cost' => $data['cost'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'type' => 'servico',
            'manage_stock' => false,
        ]);
    }

    // Scopes
    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?? activeTenantId());
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnlineBooking($query)
    {
        return $query->where('description', 'like', '%"online_booking":true%');
    }

    public function scopeForCategory($query, $categoryId)
    {
        return $query->where('description', 'like', '%"category_id":' . $categoryId . '%');
    }

    // Relationships
    /**
     * Obter a categoria do serviço
     * Como category_id está no JSON, não podemos usar belongsTo
     */
    public function getCategoryAttribute()
    {
        $categoryId = $this->category_id;
        if (!$categoryId) return null;
        
        return ServiceCategory::find($categoryId);
    }

    /**
     * Carregar categorias para múltiplos serviços (evitar N+1)
     */
    public function scopeWithCategory($query)
    {
        return $query;
    }

    public function professionals()
    {
        return $this->belongsToMany(Professional::class, 'salon_professional_services', 'service_id', 'professional_id')
            ->withPivot('custom_price', 'custom_duration')
            ->withTimestamps();
    }

    public function appointmentServices()
    {
        return $this->hasMany(AppointmentService::class, 'service_id');
    }
}
