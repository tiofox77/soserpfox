<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'content',
        'variables',
        'is_active',
        'description',
        'tenant_id',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Tenant relationship
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Renderizar template com variáveis
     */
    public function render(array $data): string
    {
        $content = $this->content;

        foreach ($data as $key => $value) {
            $content = str_replace("{{" . $key . "}}", $value, $content);
        }

        return $content;
    }

    /**
     * Get template by slug (with fallback to global)
     */
    public static function getBySlug($slug, $tenantId = null)
    {
        // Buscar por tenant específico primeiro
        if ($tenantId) {
            $template = self::where('slug', $slug)
                          ->where('tenant_id', $tenantId)
                          ->where('is_active', true)
                          ->first();
            
            if ($template) {
                return $template;
            }
        }
        
        // Fallback para template global
        return self::where('slug', $slug)
                   ->whereNull('tenant_id')
                   ->where('is_active', true)
                   ->first();
    }
}
