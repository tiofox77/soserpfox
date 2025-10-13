<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SoftwareSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'setting_key',
        'setting_value',
        'setting_type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Obter valor de uma configuração
     */
    public static function get(string $module, string $key, $default = null)
    {
        $cacheKey = "software_setting_{$module}_{$key}";
        
        return Cache::remember($cacheKey, 3600, function () use ($module, $key, $default) {
            $setting = self::where('module', $module)
                ->where('setting_key', $key)
                ->where('is_active', true)
                ->first();
            
            if (!$setting) {
                return $default;
            }
            
            return self::castValue($setting->setting_value, $setting->setting_type);
        });
    }

    /**
     * Definir valor de uma configuração
     */
    public static function set(string $module, string $key, $value, string $type = 'boolean', ?string $description = null)
    {
        $setting = self::updateOrCreate(
            [
                'module' => $module,
                'setting_key' => $key,
            ],
            [
                'setting_value' => is_bool($value) ? ($value ? 'true' : 'false') : $value,
                'setting_type' => $type,
                'description' => $description,
                'is_active' => true,
            ]
        );
        
        // Limpar cache
        Cache::forget("software_setting_{$module}_{$key}");
        
        return $setting;
    }

    /**
     * Verificar se exclusão está bloqueada para um tipo de documento
     */
    public static function isDeleteBlocked(string $documentType): bool
    {
        $key = "block_delete_{$documentType}";
        return (bool) self::get('invoicing', $key, false);
    }

    /**
     * Converter valor conforme o tipo
     */
    private static function castValue($value, $type)
    {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            
            case 'integer':
                return (int) $value;
            
            case 'float':
                return (float) $value;
            
            case 'json':
                return json_decode($value, true);
            
            default:
                return $value;
        }
    }

    /**
     * Obter todas configurações de um módulo
     */
    public static function getModuleSettings(string $module)
    {
        return self::where('module', $module)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [
                    $setting->setting_key => self::castValue(
                        $setting->setting_value,
                        $setting->setting_type
                    )
                ];
            });
    }

    /**
     * Limpar todo cache de configurações
     */
    public static function clearCache()
    {
        Cache::flush(); // ou implementar limpeza seletiva
    }
}
