<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsSetting extends Model
{
    protected $hidden = ['api_token', 'telco_api_key_qas'];

    protected $fillable = [
        'provider',
        'api_url',
        'api_token',
        'telco_api_key_qas',
        'sender_id',
        'report_url',
        'is_active',
        'config',
        'tenant_id',
    ];

    protected $casts = [
        'api_token' => \App\Casts\SegredoCifrado::class,
        'telco_api_key_qas' => \App\Casts\SegredoCifrado::class,
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    /**
     * Tenant relationship
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get SMS setting for tenant (or global if null)
     */
    public static function getForTenant($tenantId = null)
    {
        // Buscar por tenant específico primeiro
        if ($tenantId) {
            $setting = self::where('tenant_id', $tenantId)
                          ->where('is_active', true)
                          ->first();
            
            if ($setting) {
                return $setting;
            }
        }
        
        // Fallback para configuração global (tenant_id = null)
        return self::whereNull('tenant_id')
                   ->where('is_active', true)
                   ->first();
    }

    public function telcoApiKey(): ?string
    {
        if ($this->provider !== 'telcosms') {
            return $this->api_token;
        }

        return data_get($this->config, 'telco_application') === 'soserp_qas'
            ? $this->telco_api_key_qas
            : $this->api_token;
    }
}
