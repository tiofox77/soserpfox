<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Brand extends Model
{
    use SoftDeletes;

    protected $table = 'invoicing_brands';

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description', 'icon',
        'logo', 'website', 'order', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Boot para gerar slug automaticamente
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($brand) {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
        });
    }

    // Relacionamentos
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
