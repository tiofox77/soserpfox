<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use SoftDeletes;

    protected $table = 'invoicing_categories';

    protected $fillable = [
        'tenant_id', 'parent_id', 'name', 'slug', 'description',
        'icon', 'color', 'order', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Boot para gerar slug automaticamente
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    // Relacionamentos
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('order');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Accessors
    public function getIsSubcategoryAttribute()
    {
        return !is_null($this->parent_id);
    }

    public function getFullNameAttribute()
    {
        if ($this->parent) {
            return $this->parent->name . ' > ' . $this->name;
        }
        return $this->name;
    }

    public static function seedRestaurantDefaults(int $tenantId): void
    {
        if (self::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->exists()) return;
        foreach ([
            ['Geral', 'fa-layer-group', '#2563EB'], ['Entradas', 'fa-bowl-food', '#F59E0B'],
            ['Pratos Principais', 'fa-utensils', '#EA580C'], ['Bebidas', 'fa-martini-glass-citrus', '#0891B2'],
            ['Sobremesas', 'fa-ice-cream', '#DB2777'],
        ] as $order => [$name, $icon, $color]) {
            self::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => Str::slug($name)],
                ['name' => $name, 'icon' => $icon, 'color' => $color, 'order' => $order, 'is_active' => true]
            );
        }
    }

    // Scopes
    public function scopeMainCategories($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeSubcategories($query)
    {
        return $query->whereNotNull('parent_id');
    }
}
