<?php

namespace App\Livewire\Invoicing;

use App\Models\AGT\AGTCaeCode;
use App\Services\AGT\GestaoAgt;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * CONFIGURAÇÃO AGT DO CONTRIBUINTE — o ecrã de sempre.
 *
 * O que é da EMPRESA (por tenant):
 *  - NIF do contribuinte (taxRegistrationNumber)
 *  - Código do estabelecimento (establishmentNumber)
 *  - Ambiente (homologação/produção)
 *  - Toggles de auto-submissão e validação prévia
 *  - Emails de aviso e código CAE
 *  - Chave privada RSA do modo antigo (sem ambiente)
 *
 * NOTA: softwareInfo, Basic Auth e chave privada do produtor
 *       (jwsSoftwareSignature) são configurações GLOBAIS do Super Admin.
 *
 * A ficha grava-se pela `GestaoAgt`, a mesma do ecrã em React. Mudar de
 * ambiente passa pela activação deliberada, com a regra das chaves: este
 * ecrã pedia-o no mesmo Guardar, e a regra vale na mesma.
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

    // === Chave Privada RSA do Contribuinte (PEM), modo antigo ===
    public string $contributor_private_key = '';
    public bool $hasPrivateKey = false;

    // === Comportamento ===
    public bool $agt_auto_submit = false;
    public bool $agt_require_validation = true;

    // === Notificações de erro AGT ===
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

    private function gestao(): GestaoAgt
    {
        return new GestaoAgt((int) $this->tenantId);
    }

    private function loadSettings(): void
    {
        if (!$this->tenantId) {
            return;
        }

        $d = $this->gestao()->lerContribuinte();
        $this->agt_environment = $d['agt_environment'];
        $this->tax_registration_number = $d['tax_registration_number'];
        $this->agt_establishment_number = $d['agt_establishment_number'];
        $this->agt_auto_submit = $d['agt_auto_submit'];
        $this->agt_require_validation = $d['agt_require_validation'];
        $this->agt_notification_emails = $d['agt_notification_emails'];
        $this->agt_eac_code = $d['agt_eac_code'];
        $this->hasGlobalCredentials = $d['produtor'];
        $this->hasPrivateKey = $d['chave_legado'];
    }

    public function save()
    {
        if (!$this->tenantId) {
            $this->dispatch('notify', type: 'error', message: __('Tenant activo não identificado.'));

            return;
        }

        $this->validate(GestaoAgt::regrasDoContribuinte() + [
            'agt_environment' => ['required', 'in:sandbox,production'],
            'contributor_private_key' => ['nullable', 'string'],
        ]);

        $g = $this->gestao();

        // Um e-mail inválido sai do serviço como erro de validação, no campo.
        $g->guardarContribuinte([
            'tax_registration_number' => $this->tax_registration_number,
            'agt_establishment_number' => $this->agt_establishment_number,
            'agt_auto_submit' => $this->agt_auto_submit,
            'agt_require_validation' => $this->agt_require_validation,
            'agt_notification_emails' => $this->agt_notification_emails,
            'agt_eac_code' => $this->agt_eac_code,
        ]);

        if ($this->agt_environment !== $g->ambienteActivo()) {
            try {
                $g->activarAmbiente($this->agt_environment);
            } catch (DomainException $e) {
                $this->agt_environment = $g->ambienteActivo();
                $this->dispatch('notify', type: 'error', message: $e->getMessage());

                return;
            }
        }

        if (!empty($this->contributor_private_key)) {
            $g->guardarChaveLegado($this->contributor_private_key);
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

        $g = $this->gestao();

        try {
            $this->connectionTest = $g->testarLigacao($g->ambienteActivo());
        } catch (DomainException $e) {
            $this->connectionTest = ['success' => false, 'error' => $e->getMessage()];
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        }

        if ($this->connectionTest['success'] ?? false) {
            $this->dispatch('notify', type: 'success', message: __('Conexão AGT estabelecida com sucesso.'));
        } else {
            $this->dispatch('notify', type: 'error', message: $this->connectionTest['error'] ?? 'Falha na conexão');
        }
    }

    public function clearPrivateKey()
    {
        if (!$this->tenantId) {
            return;
        }

        $this->gestao()->removerChaveLegado();
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
