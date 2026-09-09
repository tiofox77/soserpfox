<?php

namespace App\Models\Salon;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ServiceCategory extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'salon_service_categories';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'icon',
        'color',
        'description',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = activeTenantId();
            }
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
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

    // Relationships
    /**
     * Quantos serviços tem cada categoria — a sério.
     *
     * `withCount('services')` dá SEMPRE zero: a relação abaixo é um `hasMany`
     * pela coluna `category_id`, e a categoria de um serviço do salão não vive
     * nessa coluna (tem chave estrangeira para as categorias da facturação) —
     * vive no JSON do `description`, e o modelo expõe-a por um acessor com o
     * mesmo nome.
     *
     * Uma consulta, e a conta feita em PHP pelo acessor: um salão tem dezenas
     * de serviços, não milhares.
     *
     * @return array<int,int>  id da categoria => nº de serviços
     */
    public static function contagens(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?: activeTenantId();

        return Service::where('tenant_id', $tenantId)
            ->get(['id', 'description'])
            ->groupBy(fn ($s) => $s->category_id)
            ->map->count()
            ->all();
    }

    /**
     * Põe `services_count` numa colecção de categorias, sem ir à base por cada.
     *
     * @param  iterable<self>  $categorias
     */
    public static function comContagens($categorias, ?int $tenantId = null)
    {
        $contagens = self::contagens($tenantId);

        foreach ($categorias as $c) {
            $c->services_count = $contagens[$c->id] ?? 0;
        }

        return $categorias;
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    public function activeServices()
    {
        return $this->hasMany(Service::class, 'category_id')->where('is_active', true);
    }
}
