<?php

namespace App\Models\CRM;

use App\Models\Tenant;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ligação de UMA empresa ao Meta (Facebook, Instagram, WhatsApp).
 *
 * Uma linha por empresa. Os segredos (app_secret, tokens) são cifrados em
 * repouso pelo cast 'encrypted' — quem olhar para a base não os lê. Descifram-se
 * sozinhos ao ler o atributo, com a APP_KEY da instalação.
 */
class MetaIntegration extends Model
{
    use BelongsToTenant;

    protected $table = 'meta_integrations';

    protected $fillable = [
        'tenant_id',
        'app_id', 'app_secret', 'webhook_verify_token',
        'facebook_enabled', 'facebook_page_id', 'facebook_page_name', 'facebook_page_token',
        'instagram_enabled', 'instagram_account_id', 'instagram_username',
        'whatsapp_enabled', 'whatsapp_phone_number_id', 'whatsapp_business_account_id',
        'whatsapp_display_number', 'whatsapp_token',
        'lead_ads_enabled', 'criar_leads', 'ultimo_evento_em',
    ];

    protected $casts = [
        // OS SEGREDOS CIFRADOS. Nunca em claro na base.
        'app_secret' => 'encrypted',
        'facebook_page_token' => 'encrypted',
        'whatsapp_token' => 'encrypted',

        'facebook_enabled' => 'boolean',
        'instagram_enabled' => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'lead_ads_enabled' => 'boolean',
        'criar_leads' => 'boolean',
        'ultimo_evento_em' => 'datetime',
    ];

    /** A ligação desta empresa (cria uma vazia se ainda não existe). */
    public static function paraTenant(int $tenantId): self
    {
        return static::firstOrCreate(['tenant_id' => $tenantId]);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** O endereço que se cola na configuração do webhook, no painel do Meta. */
    public function urlDoWebhook(): string
    {
        return url('/webhooks/meta/'.$this->tenant_id);
    }

    /* ── "Está mesmo ligado?" por canal ─────────────────────────────── */

    public function whatsappActivo(): bool
    {
        return $this->whatsapp_enabled
            && ! empty($this->whatsapp_phone_number_id)
            && ! empty($this->whatsapp_token);
    }

    public function facebookActivo(): bool
    {
        return $this->facebook_enabled
            && ! empty($this->facebook_page_id)
            && ! empty($this->facebook_page_token);
    }

    public function instagramActivo(): bool
    {
        return $this->instagram_enabled
            && ! empty($this->instagram_account_id);
    }

    /** Algum canal ligado? */
    public function algumActivo(): bool
    {
        return $this->whatsappActivo() || $this->facebookActivo() || $this->instagramActivo();
    }
}
