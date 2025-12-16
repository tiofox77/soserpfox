<?php

namespace App\Models\Salon;

use App\Models\Product as BaseProduct;
use Illuminate\Database\Eloquent\Builder;

class Product extends BaseProduct
{
    protected static function booted()
    {
        static::addGlobalScope('salon', function (Builder $builder) {
            $builder->where('type', 'produto')
                    ->where(function($q) {
                        $q->where('description', 'like', '%"module":"salon"%')
                          ->orWhereNull('description');
                    });
        });

        static::creating(function ($product) {
            $product->type = 'produto';
            if (empty($product->tenant_id)) {
                $product->tenant_id = activeTenantId();
            }
            
            // Adicionar marcador de módulo no description
            $description = json_decode($product->description, true) ?? [];
            $description['module'] = 'salon';
            $product->description = json_encode($description);
        });
    }

    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?? activeTenantId());
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where(function($q) {
            $q->where('manage_stock', false)
              ->orWhere('stock_quantity', '>', 0);
        });
    }

    public function scopeLowStock($query)
    {
        return $query->where('manage_stock', true)
                     ->whereColumn('stock_quantity', '<=', 'minimum_stock');
    }

    // Accessor para dados específicos do salão
    public function getSalonDataAttribute()
    {
        $description = json_decode($this->description, true) ?? [];
        return $description['salon_data'] ?? [];
    }

    public function setSalonDataAttribute($value)
    {
        $description = json_decode($this->description, true) ?? [];
        $description['salon_data'] = $value;
        $description['module'] = 'salon';
        $this->attributes['description'] = json_encode($description);
    }

    // Categoria do produto
    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class, 'category_id');
    }
}
