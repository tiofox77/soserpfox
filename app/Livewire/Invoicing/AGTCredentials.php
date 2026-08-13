<?php

namespace App\Livewire\Invoicing;

use App\Models\AGT\AGTCaeCode;
use App\Models\Invoicing\InvoicingSettings;
use App\Services\AGT\AGTClient;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

/**
 * Página de configuração AGT POR TENANT (contribuinte).
 *
 * Conforme documentação oficial AGT v1.2:
 *  - Autenticação Basic Auth → GLOBAL do produtor (gerida em Operação AGT pelo Super Admin)
 *  - Chave privada RSA do CONTRIBUINTE (emitida pela AGT, disponível no Portal do Contribuinte)
 *    → usada para: jwsDocumentSignature, jwsSignature
 *  - NIF do contribuinte (taxRegistrationNumber)
 *  - Código do estabelecimento (establishmentNumber)
 *  - Ambiente (homologação/produção)
 *  - Toggles de auto-submissão e validação prévia
 *
 * NOTA: softwareInfo, Basic Auth e chave privada do produtor (jwsSoftwareSignature)
 *       são configurações GLOBAIS geridas pelo Super Admin.
 */
#[Layout('layouts.app')]
#[Title('Configuração AGT — Contribuinte')]
class AGTCredentials extends Component
{
    // === Ambiente ===
    public string $agt_environment = 'sandbox';

    // === Dados do Contribuinte ===
    public string $tax_registration_number = '';
    public string $agt_establishment_number = 'SEDE';

    // === Chave Privada RSA do Contribuinte (PEM) ===
    public string $contributor_private_key = '';
    public bool $hasPrivateKey = false;

    // === Comportamento ===
    public bool $agt_auto_submit = false;
    public bool $agt_require_validation = true;

    // === Notificações de erro AGT (Sprint 4) ===
    public string $agt_notification_emails = '';

    // === Código CAE (DS.120 §4.1 — obrigatório em todas as submissões) ===
    public ?string $agt_eac_code = null;

    // === Estado ===
    public bool $hasGlobalCredentials = false;
    // #[Locked]: sem isto o cliente podia reescrever o tenantId no snapshot e ler
    // /sobrescrever as credenciais AGT (incluindo a CHAVE PRIVADA RSA) de outra empresa.
    #[Locked]
    public ?int $tenantId = null;
    public array $connectionTest = [];
    public bool $showPrivateKey = false;

    public function mount()
    {
        $this->tenantId = activeTenantId();
        $this->loadSettings();
    }

    private function loadSettings(): void
    {
        if (!$this->tenantId) return;

        $s = InvoicingSettings::where('tenant_id', $this->tenantId)->first();
        if (!$s) return;

        $this->agt_environment = $s->agt_environment ?? 'sandbox';
        $this->agt_establishment_number = $s->agt_establishment_number ?? 'SEDE';
        $this->agt_auto_submit = (bool) ($s->agt_auto_submit ?? false);
        $this->agt_require_validation = (bool) ($s->agt_require_validation ?? true);
        $this->agt_notification_emails = (string) ($s->agt_notification_emails ?? '');
        $this->agt_eac_code = $s->agt_eac_code ?? null;

        // NIF do contribuinte (da empresa/tenant)
        $tenant = \App\Models\Tenant::find($this->tenantId);
        $this->tax_registration_number = $tenant->nif ?? $tenant->tax_id ?? '';

        // Verificar se credenciais globais do produtor estão configuradas
        $this->hasGlobalCredentials = !empty(config('services.agt.username')) && !empty(config('services.agt.password'));

        // Verificar se chave privada do contribuinte existe
        $keyPath = "agt/tenants/{$this->tenantId}/private_key.pem";
        $this->hasPrivateKey = Storage::disk('local')->exists($keyPath);
    }

    public function save()
    {
        if (!$this->tenantId) {
            $this->dispatch('notify', type: 'error', message: __('Tenant activo não identificado.'));
            return;
        }

        $this->validate([
            'agt_environment'            => 'required|in:sandbox,production',
            'tax_registration_number'    => 'nullable|string|max:15',
            'agt_establishment_number'   => 'required|string|max:200',
            'contributor_private_key'    => 'nullable|string',
            'agt_notification_emails'    => 'nullable|string|max:1000',
            'agt_eac_code'               => 'nullable|string|max:8',
        ]);

        // Validação leve do CSV de emails
        if (!empty($this->agt_notification_emails)) {
            $list = array_filter(array_map('trim', explode(',', $this->agt_notification_emails)));
            foreach ($list as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->addError('agt_notification_emails', "Email inválido: {$email}");
                    return;
                }
            }
        }

        $s = InvoicingSettings::firstOrCreate(
            ['tenant_id' => $this->tenantId],
            ['default_currency' => 'AOA']
        );

        $data = [
            'agt_environment'            => $this->agt_environment,
            'agt_establishment_number'   => $this->agt_establishment_number,
            'agt_auto_submit'            => $this->agt_auto_submit,
            'agt_require_validation'     => $this->agt_require_validation,
            'agt_notification_emails'    => $this->agt_notification_emails ?: null,
            'agt_eac_code'               => $this->agt_eac_code ?: null,
        ];

        $s->update($data);

        // Guardar NIF no tenant
        if (!empty($this->tax_registration_number)) {
            $tenant = \App\Models\Tenant::find($this->tenantId);
            if ($tenant) {
                $tenant->update(['nif' => $this->tax_registration_number]);
            }
        }

        // Guardar chave privada RSA do contribuinte
        if (!empty($this->contributor_private_key)) {
            $keyPath = "agt/tenants/{$this->tenantId}/private_key.pem";
            Storage::disk('local')->put($keyPath, $this->contributor_private_key);
            $this->hasPrivateKey = true;
            $this->contributor_private_key = '';
        }

        $this->dispatch('notify', type: 'success', message: __('Configuração AGT guardada com sucesso.'));
    }

    public function testConnection()
    {
        if (!$this->tenantId) {
            $this->dispatch('notify', type: 'error', message: __('Tenant activo não identificado.'));
            return;
        }

        try {
            $client = new AGTClient($this->tenantId);
            $this->connectionTest = $client->testConnection();
            if ($this->connectionTest['success'] ?? false) {
                $this->dispatch('notify', type: 'success', message: __('Conexão AGT estabelecida com sucesso.'));
            } else {
                $this->dispatch('notify', type: 'error', message: $this->connectionTest['error'] ?? 'Falha na conexão');
            }
        } catch (\Exception $e) {
            $this->connectionTest = ['success' => false, 'error' => $e->getMessage()];
            $this->dispatch('notify', type: 'error', message: __('Erro: :detalhe', ['detalhe' => $e->getMessage()]));
        }
    }

    public function clearPrivateKey()
    {
        if (!$this->tenantId) return;

        $keyPath = "agt/tenants/{$this->tenantId}/private_key.pem";
        Storage::disk('local')->delete($keyPath);
        $this->hasPrivateKey = false;
        $this->contributor_private_key = '';
        $this->dispatch('notify', type: 'success', message: __('Chave privada do contribuinte removida.'));
    }

    public function render()
    {
        // Códigos CAE oficiais (DS.120 Anexo) — 21 secções + 36 divisões/classes
        $caeCodes = AGTCaeCode::query()
            ->where('is_active', true)
            ->orderByRaw("FIELD(level, 'section','division','group','class','subclass')")
            ->orderBy('code')
            ->get();

        return view('livewire.invoicing.agt-credentials', compact('caeCodes'));
    }
}
