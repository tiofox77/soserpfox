<?php

namespace App\Livewire\CRM;

use App\Models\CRM\MetaIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Definições da ligação ao Meta (Facebook, Instagram, WhatsApp) desta empresa.
 *
 * Os TOKENS são segredos: nunca se devolvem ao ecrã. Se já estão configurados,
 * mostra-se «configurado» e o campo só os SUBSTITUI se o utilizador escrever um
 * novo — deixar em branco mantém o que lá está. O resto (ids, números) é
 * identificação pública e vê-se normalmente.
 */
#[Layout('layouts.app')]
#[Title('Integração Meta - CRM')]
class IntegracoesMeta extends Component
{
    public ?int $id = null;

    // App do Meta
    public string $app_id = '';
    public string $app_secret = '';           // só escrita (novo valor)
    public bool $temAppSecret = false;
    public string $webhook_verify_token = '';

    // Facebook
    public bool $facebook_enabled = false;
    public string $facebook_page_id = '';
    public string $facebook_page_name = '';
    public string $facebook_page_token = '';  // só escrita
    public bool $temPageToken = false;

    // Instagram
    public bool $instagram_enabled = false;
    public string $instagram_account_id = '';
    public string $instagram_username = '';

    // WhatsApp Cloud API
    public bool $whatsapp_enabled = false;
    public string $whatsapp_phone_number_id = '';
    public string $whatsapp_business_account_id = '';
    public string $whatsapp_display_number = '';
    public string $whatsapp_token = '';        // só escrita
    public bool $temWaToken = false;

    // Comportamento
    public bool $criar_leads = true;
    public bool $lead_ads_enabled = false;

    public string $urlWebhook = '';
    public ?string $resultadoTeste = null;

    public function mount(): void
    {
        $m = MetaIntegration::paraTenant(activeTenantId());
        $this->id = $m->id;

        $this->app_id = (string) $m->app_id;
        $this->temAppSecret = ! empty($m->app_secret);

        // O token de verificação PERSISTE-SE já, à primeira abertura. Sem isto,
        // ficava só em memória (o `wire:model` é deferido) e o aperto de mão do
        // Meta falhava enquanto o utilizador não carregasse em Guardar. Assim, o
        // webhook fica pronto a verificar mal se copia o URL e o token.
        if (empty($m->webhook_verify_token)) {
            $m->webhook_verify_token = 'sos-'.Str::random(16);
            $m->save();
        }
        $this->webhook_verify_token = $m->webhook_verify_token;

        $this->facebook_enabled = (bool) $m->facebook_enabled;
        $this->facebook_page_id = (string) $m->facebook_page_id;
        $this->facebook_page_name = (string) $m->facebook_page_name;
        $this->temPageToken = ! empty($m->facebook_page_token);

        $this->instagram_enabled = (bool) $m->instagram_enabled;
        $this->instagram_account_id = (string) $m->instagram_account_id;
        $this->instagram_username = (string) $m->instagram_username;

        $this->whatsapp_enabled = (bool) $m->whatsapp_enabled;
        $this->whatsapp_phone_number_id = (string) $m->whatsapp_phone_number_id;
        $this->whatsapp_business_account_id = (string) $m->whatsapp_business_account_id;
        $this->whatsapp_display_number = (string) $m->whatsapp_display_number;
        $this->temWaToken = ! empty($m->whatsapp_token);

        $this->criar_leads = (bool) $m->criar_leads;
        $this->lead_ads_enabled = (bool) $m->lead_ads_enabled;

        $this->urlWebhook = $m->urlDoWebhook();
    }

    public function gerarTokenVerificacao(): void
    {
        $this->webhook_verify_token = 'sos-'.Str::random(16);
    }

    public function save(): void
    {
        $this->validate([
            'app_id' => 'nullable|string|max:100',
            'webhook_verify_token' => 'required|string|min:8|max:100',
            'whatsapp_phone_number_id' => 'nullable|string|max:100',
            'facebook_page_id' => 'nullable|string|max:100',
            'instagram_account_id' => 'nullable|string|max:100',
        ]);

        $m = MetaIntegration::paraTenant(activeTenantId());

        $m->app_id = $this->app_id ?: null;
        $m->webhook_verify_token = $this->webhook_verify_token;

        $m->facebook_enabled = $this->facebook_enabled;
        $m->facebook_page_id = $this->facebook_page_id ?: null;
        $m->facebook_page_name = $this->facebook_page_name ?: null;

        $m->instagram_enabled = $this->instagram_enabled;
        $m->instagram_account_id = $this->instagram_account_id ?: null;
        $m->instagram_username = $this->instagram_username ?: null;

        $m->whatsapp_enabled = $this->whatsapp_enabled;
        $m->whatsapp_phone_number_id = $this->whatsapp_phone_number_id ?: null;
        $m->whatsapp_business_account_id = $this->whatsapp_business_account_id ?: null;
        $m->whatsapp_display_number = $this->whatsapp_display_number ?: null;

        $m->criar_leads = $this->criar_leads;
        $m->lead_ads_enabled = $this->lead_ads_enabled;

        // Segredos: só se sobrescrevem se o utilizador escreveu um valor novo.
        if (trim($this->app_secret) !== '') {
            $m->app_secret = trim($this->app_secret);
        }
        if (trim($this->facebook_page_token) !== '') {
            $m->facebook_page_token = trim($this->facebook_page_token);
        }
        if (trim($this->whatsapp_token) !== '') {
            $m->whatsapp_token = trim($this->whatsapp_token);
        }

        $m->save();

        // Limpar os campos de segredo do ecrã e actualizar os "tem…".
        $this->reset(['app_secret', 'facebook_page_token', 'whatsapp_token']);
        $this->temAppSecret = ! empty($m->app_secret);
        $this->temPageToken = ! empty($m->facebook_page_token);
        $this->temWaToken = ! empty($m->whatsapp_token);

        $this->dispatch('notify', type: 'success', message: 'Integração Meta guardada.');
    }

    /**
     * Testa a ligação do WhatsApp: pergunta ao Graph pelo próprio número.
     * Só confirma que o token + phone_number_id são válidos — não envia nada.
     */
    public function testarWhatsApp(): void
    {
        $this->resultadoTeste = null;
        $m = MetaIntegration::paraTenant(activeTenantId());

        if (empty($m->whatsapp_phone_number_id) || empty($m->whatsapp_token)) {
            $this->resultadoTeste = 'erro:Preencha o Phone Number ID e o Token do WhatsApp e guarde primeiro.';

            return;
        }

        try {
            $r = Http::withToken($m->whatsapp_token)
                ->timeout(15)
                ->get("https://graph.facebook.com/v21.0/{$m->whatsapp_phone_number_id}", [
                    'fields' => 'display_phone_number,verified_name,quality_rating',
                ]);

            if ($r->successful()) {
                $numero = $r->json('display_phone_number') ?? '—';
                $nome = $r->json('verified_name') ?? '—';
                $this->resultadoTeste = "ok:Ligado — {$nome} ({$numero}).";
            } else {
                $msg = $r->json('error.message') ?? ('HTTP '.$r->status());
                $this->resultadoTeste = 'erro:O Meta recusou — '.$msg;
            }
        } catch (\Throwable $e) {
            $this->resultadoTeste = 'erro:Não foi possível contactar o Meta — '.$e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.crm.integracoes-meta');
    }
}
