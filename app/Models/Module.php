<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'version',
        'is_core',
        'is_active',
        'order',
        'dependencies',
    ];

    protected $casts = [
        'dependencies' => 'array',
        'is_core' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($module) {
            if (empty($module->slug)) {
                $module->slug = Str::slug($module->name);
            }
        });
    }

    // Relacionamentos
    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'tenant_module')
            ->withPivot('is_active', 'activated_at')
            ->withTimestamps();
    }

    public function permissions()
    {
        return \Spatie\Permission\Models\Permission::where('name', 'like', $this->slug . '.%')->get();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCore($query)
    {
        return $query->where('is_core', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    // Métodos auxiliares
    public function hasDependencies()
    {
        return !empty($this->dependencies);
    }

    public function getDependencies()
    {
        if (!$this->hasDependencies()) {
            return collect();
        }

        return Module::whereIn('slug', $this->dependencies)->get();
    }
}
