<?php

namespace App\Livewire\Company;

use App\Models\Tenant;
use App\Services\Tenant\TaxRegimeSyncer;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Dados da Empresa — página única onde o utilizador vê e atualiza toda a
 * informação da sua empresa (identificação, contactos, endereço, logótipo) e
 * o REGIME FISCAL AGT.
 *
 * O regime é o campo mais sensível: mudar de regime propaga-se aos impostos,
 * às configurações de faturação e aos produtos (via TaxRegimeSyncer), por isso
 * exige confirmação explícita e mostra as consequências antes de aplicar.
 */
#[Layout('layouts.app')]
#[Title('Dados da Empresa')]
class CompanyProfile extends Component
{
    use WithFileUploads;

    // ── Identificação ──
    public $name = '';
    public $company_name = '';
    public $nif = '';
    public $email = '';
    public $phone = '';

    // ── Endereço ──
    public $address = '';
    public $postal_code = '';
    public $city = '';
    public $country = 'Angola';

    // ── Regime fiscal ──
    public $regime = Tenant::REGIME_GERAL;
    /** Regime guardado na BD (canonicalizado) — para detetar alteração. */
    public $currentRegime = Tenant::REGIME_GERAL;
    public $showRegimeConfirm = false;

    // ── Logótipo ──
    public $logo;              // upload
    public $currentLogo = null;

    // ── Contabilidade ──
    public $accounting_integration_enabled = false;

    public function mount()
    {
        $tenant = $this->tenant();
        abort_if(!$tenant, 404, 'Empresa não encontrada.');

        $this->name         = $tenant->name;
        $this->company_name = $tenant->company_name;
        $this->nif          = $tenant->nif;
        $this->email        = $tenant->email;
        $this->phone        = $tenant->phone;
        $this->address      = $tenant->address;
        $this->postal_code  = $tenant->postal_code;
        $this->city         = $tenant->city;
        $this->country      = $tenant->country ?: 'Angola';
        $this->currentLogo  = $tenant->logo;

        // Canonicalizar: tenants antigos podem ter 'regime_isencao'/'regime_misto'
        $this->regime = $this->currentRegime = Tenant::canonicalRegime($tenant->regime);

        $this->accounting_integration_enabled = (bool) $tenant->accounting_integration_enabled;
    }

    protected function tenant(): ?Tenant
    {
        return Tenant::find(activeTenantId());
    }

    protected function rules(): array
    {
        return [
            'name'         => 'required|string|min:2|max:255',
            'company_name' => 'nullable|string|max:255',
            // NIF angolano: 9-14 alfanuméricos (pessoa coletiva termina em letras)
            // O NIF de EMPRESA, e nao o que este campo aceitava: o
            // [A-Za-z0-9]{5,20} deixava passar o numero do bilhete de
            // identidade, com letras e tudo — no proprio ecra onde se vem
            // corrigir isso.
            'nif'          => ['required', new \App\Rules\NifDeEmpresa()],
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:50',
            'address'      => 'nullable|string|max:500',
            'postal_code'  => 'nullable|string|max:20',
            'city'         => 'nullable|string|max:100',
            'country'      => 'required|string|max:100',
            'regime'       => 'required|in:' . implode(',', array_keys(Tenant::REGIMES)),
            'logo'         => 'nullable|image|max:2048',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required'    => 'O nome da empresa é obrigatório.',
            'nif.regex'        => 'O NIF deve ter entre 5 e 20 caracteres alfanuméricos (sem espaços ou pontos).',
            'email.email'      => 'Introduza um email válido.',
            'country.required' => 'O país é obrigatório.',
            'regime.in'        => 'Selecione um dos regimes fiscais da AGT.',
            'logo.image'       => 'O logótipo deve ser uma imagem.',
            'logo.max'         => 'O logótipo não pode exceder 2 MB.',
        ];
    }

    /** Metadados do regime atualmente selecionado no formulário (para a UI). */
    public function getSelectedRegimeMetaProperty(): array
    {
        return Tenant::REGIMES[Tenant::canonicalRegime($this->regime)];
    }

    public function getRegimeChangedProperty(): bool
    {
        return Tenant::canonicalRegime($this->regime) !== $this->currentRegime;
    }

    /**
     * Guardar. Se o regime mudou, pede confirmação primeiro (a mudança altera
     * impostos e produtos em massa).
     */
    public function save()
    {
        $this->validate();

        if ($this->regimeChanged && !$this->showRegimeConfirm) {
            $this->showRegimeConfirm = true;
            return;
        }

        $tenant = $this->tenant();
        if (!$tenant) {
            $this->dispatch('error', message: 'Empresa não encontrada.');
            return;
        }

        $previousRegime = $tenant->regime;
        $newRegime      = Tenant::canonicalRegime($this->regime);

        $data = [
            'name'         => trim($this->name),
            'company_name' => $this->company_name ? trim($this->company_name) : null,
            'nif'          => $this->nif ? strtoupper(trim($this->nif)) : null,
            'email'        => $this->email ? trim($this->email) : null,
            'phone'        => $this->phone ? trim($this->phone) : null,
            'address'      => $this->address ? trim($this->address) : null,
            'postal_code'  => $this->postal_code ? trim($this->postal_code) : null,
            'city'         => $this->city ? trim($this->city) : null,
            'country'      => trim($this->country),
        ];

        // Só escrever `regime` quando muda de facto. Assim, guardar contactos num
        // tenant com valor legado ('regime_isencao') não reescreve silenciosamente
        // o campo — a canonicalização acontece na leitura (Tenant::canonicalRegime).
        $regimeChanged = $newRegime !== Tenant::canonicalRegime($previousRegime);
        if ($regimeChanged) {
            $data['regime'] = $newRegime;
        }

        // Logótipo novo
        if ($this->logo) {
            $ext  = $this->logo->getClientOriginalExtension();
            $path = $this->logo->storeAs('tenants/' . $tenant->id, 'logo.' . $ext, 'public');
            // Remover o anterior se mudou de extensão
            if ($tenant->logo && $tenant->logo !== $path && Storage::disk('public')->exists($tenant->logo)) {
                Storage::disk('public')->delete($tenant->logo);
            }
            $data['logo'] = $path;
        }

        $tenant->update($data);

        // Propagar o regime a impostos / configurações / produtos
        $syncMsg = '';
        if ($regimeChanged) {
            try {
                $result = (new TaxRegimeSyncer())->sync($tenant->fresh(), $previousRegime);
                if (!empty($result['changed'])) {
                    $meta = Tenant::REGIMES[$newRegime];
                    $syncMsg = ' Regime aplicado: ' . $meta['label'] . '.';
                    if (!empty($result['products_count'])) {
                        $syncMsg .= ' ' . $result['products_count'] . ' produto(s) atualizado(s).';
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('CompanyProfile: falha ao sincronizar regime fiscal', [
                    'tenant_id' => $tenant->id,
                    'from'      => $previousRegime,
                    'to'        => $newRegime,
                    'error'     => $e->getMessage(),
                ]);
                $this->dispatch('warning', message: 'Dados guardados, mas a sincronização fiscal falhou: ' . $e->getMessage());
            }
        }

        $this->currentRegime     = $newRegime;
        $this->regime            = $newRegime;
        $this->showRegimeConfirm = false;
        $this->logo              = null;
        $this->currentLogo       = $tenant->fresh()->logo;

        $this->dispatch('success', message: 'Dados da empresa atualizados com sucesso!' . $syncMsg);
    }

    public function cancelRegimeChange()
    {
        $this->regime            = $this->currentRegime;
        $this->showRegimeConfirm = false;
    }

    public function removeLogo()
    {
        $tenant = $this->tenant();
        if ($tenant && $tenant->logo) {
            if (Storage::disk('public')->exists($tenant->logo)) {
                Storage::disk('public')->delete($tenant->logo);
            }
            $tenant->update(['logo' => null]);
        }
        $this->logo        = null;
        $this->currentLogo = null;
        $this->dispatch('success', message: 'Logótipo removido.');
    }

    public function render()
    {
        $tenant = $this->tenant();

        // Resumo fiscal atual — mostra ao utilizador o efeito prático do regime
        $taxDefault = \App\Models\Invoicing\Tax::where('tenant_id', $tenant?->id)
            ->where('is_default', true)
            ->first();

        $settings = \App\Models\Invoicing\InvoicingSettings::where('tenant_id', $tenant?->id)->first();

        $productStats = [
            'total'          => \App\Models\Product::withoutGlobalScopes()->where('tenant_id', $tenant?->id)->count(),
            'com_iva'        => \App\Models\Product::withoutGlobalScopes()->where('tenant_id', $tenant?->id)->where('tax_type', 'iva')->count(),
            'isentos'        => \App\Models\Product::withoutGlobalScopes()->where('tenant_id', $tenant?->id)->where('tax_type', 'isento')->count(),
            'isentos_s_cod'  => \App\Models\Product::withoutGlobalScopes()->where('tenant_id', $tenant?->id)
                ->where('tax_type', 'isento')
                ->where(function ($q) {
                    $q->whereNull('exemption_reason')->orWhere('exemption_reason', '');
                })->count(),
        ];

        return view('livewire.company.company-profile', [
            'tenant'       => $tenant,
            'taxDefault'   => $taxDefault,
            'settings'     => $settings,
            'productStats' => $productStats,
        ]);
    }
}
