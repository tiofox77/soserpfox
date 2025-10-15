<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;

class HRSetting extends Model
{
    use HasFactory;

    protected $table = 'hr_settings';

    protected $fillable = [
        'tenant_id',
        'key',
        'category',
        'label',
        'description',
        'value_type',
        'value',
        'default_value',
        'validation_rules',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    // Métodos estáticos para recuperar configurações
    public static function get(string $key, $default = null)
    {
        $tenantId = auth()->check() ? auth()->user()->tenant_id : 1;
        $cacheKey = 'hr_setting_' . $tenantId . '_' . $key;
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $default, $tenantId) {
            $setting = self::where('tenant_id', $tenantId)
                ->where('key', $key)
                ->where('is_active', true)
                ->first();
            
            if (!$setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->value_type);
        });
    }

    public static function set(string $key, $value): bool
    {
        $tenantId = auth()->check() ? auth()->user()->tenant_id : 1;
        $setting = self::where('tenant_id', $tenantId)
            ->where('key', $key)
            ->first();
        
        if ($setting) {
            $setting->update(['value' => $value]);
            self::clearCache($key);
            return true;
        }
        
        return false;
    }

    public static function clearCache(string $key = null)
    {
        $tenantId = auth()->check() ? auth()->user()->tenant_id : 1;
        if ($key) {
            Cache::forget('hr_setting_' . $tenantId . '_' . $key);
        } else {
            // Limpar todas as configurações do tenant
            $settings = self::where('tenant_id', $tenantId)->get();
            foreach ($settings as $setting) {
                Cache::forget('hr_setting_' . $tenantId . '_' . $setting->key);
            }
        }
    }

    private static function castValue($value, $type)
    {
        return match($type) {
            'integer' => (int) $value,
            'decimal', 'percentage' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    // Accessors
    public function getCastedValueAttribute()
    {
        return self::castValue($this->value, $this->value_type);
    }

    public function getValueTypeNameAttribute()
    {
        return match($this->value_type) {
            'integer' => 'Número Inteiro',
            'decimal' => 'Número Decimal',
            'percentage' => 'Percentual',
            'boolean' => 'Sim/Não',
            'text' => 'Texto',
            'json' => 'JSON',
            default => 'Texto',
        };
    }

    public function getCategoryNameAttribute()
    {
        return match($this->category) {
            'general' => 'Geral',
            'payroll' => 'Folha de Pagamento',
            'vacation' => 'Férias',
            'overtime' => 'Horas Extras',
            'leave' => 'Licenças',
            default => 'Outro',
        };
    }

    // Eventos
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($setting) {
            self::clearCache($setting->key);
        });

        static::deleted(function ($setting) {
            self::clearCache($setting->key);
        });
    }
}
