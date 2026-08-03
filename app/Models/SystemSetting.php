<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
    ];

    /**
     * Get a setting value by key
     */
    /** Memória do pedido: evita ir ao cache N vezes pela mesma definição. */
    protected static array $memoria = [];

    public static function get($key, $default = null)
    {
        // O driver de cache é a base de dados, logo cada Cache::remember() é uma
        // QUERY. Uma vista que chame app_logo() por cada cartão de produto fazia
        // ~93 consultas à tabela `cache` num único ecrã do POS — a lentidão que
        // se sentia ao clicar. Dentro do mesmo pedido o valor não muda.
        if (array_key_exists($key, static::$memoria)) {
            return static::$memoria[$key];
        }

        return static::$memoria[$key] = Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /** Esquecer a memória do pedido (usado ao gravar/limpar definições). */
    public static function forgetMemoria(?string $key = null): void
    {
        if ($key === null) {
            static::$memoria = [];
            return;
        }

        unset(static::$memoria[$key]);
    }

    /**
     * Set a setting value
     */
    public static function set($key, $value)
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget("setting_{$key}");
        static::forgetMemoria($key);

        return $setting;
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup($group)
    {
        return static::where('group', $group)->get()->pluck('value', 'key');
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache()
    {
        Cache::flush();
        static::forgetMemoria();
    }
}
