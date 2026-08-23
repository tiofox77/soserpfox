<?php

namespace App\Livewire\SuperAdmin;

use App\Models\AppUpdate;
use App\Models\AppUpdateTarget;
use App\Models\Tenant;
use App\Services\Licensing\LicenseIssuer;
use App\Services\Licensing\UpdateSigner;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Painel do super admin para o licenciamento offline (F5): emitir licenças,
 * publicar versões e decidir o rollout POR-TENANT com cliques — a mesma coisa
 * que os comandos `licenca:emitir` / `atualizacao:publicar` / `atualizacao:alvo`.
 *
 * Usa as chaves PRIVADAS do servidor (env LICENSE_SIGNING_KEY / update). Se não
 * estiverem configuradas, o painel avisa em vez de rebentar.
 */
#[Layout('layouts.superadmin')]
class Licenciamento extends Component
{
    // Emitir licença
    public $licTenantId = '';
    public $licDias = 365;
    public $licGraca = '';
    public $licBindFp = '';
    public ?string $licToken = null;

    // Publicar versão
    public $verVersao = '';
    public $verMin = '';
    public $verUrl = '';
    public $verSha = '';
    public $verNotas = '';
    public bool $verObrigatorio = false;
    public string $verRollout = 'none';

    // Rollout por-tenant
    public $alvoTenantId = '';
    public $alvoVersao = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isPlatformSuperAdmin(), 403);
    }

    private function chaveLicencas(): ?string
    {
        return config('licensing.signing_key');
    }

    private function chaveUpdates(): ?string
    {
        return config('licensing.update.signing_key');
    }

    public function emitirLicenca(): void
    {
        $this->validate([
            'licTenantId' => 'required|integer|exists:tenants,id',
            'licDias'     => 'required|integer|min:1|max:3650',
            'licGraca'    => 'nullable|integer|min:1|max:365',
            'licBindFp'   => 'nullable|string|max:64',
        ]);

        if (!$this->chaveLicencas()) {
            $this->addError('licToken', 'LICENSE_SIGNING_KEY não configurada no servidor.');

            return;
        }

        $tenant = Tenant::find($this->licTenantId);

        $claims = array_filter([
            'tenant_id' => $tenant->id,
            'empresa'   => $tenant->name,
            'nif'       => $tenant->nif,
            'plano'     => $tenant->activeSubscription?->plan?->name,
            'modulos'   => ['*'],
            'exp'       => CarbonImmutable::now()->addDays((int) $this->licDias)->getTimestamp(),
            'graca'     => $this->licGraca !== '' ? (int) $this->licGraca : null,
            'fp'        => $this->licBindFp ?: null,
            'env'       => 'prod',
        ], fn ($v) => $v !== null && $v !== []);

        try {
            $this->licToken = (new LicenseIssuer())->emitir($claims, $this->chaveLicencas());
            session()->flash('ok', 'Licença emitida para ' . $tenant->name . '. Copie o token abaixo.');
        } catch (\Throwable $e) {
            $this->addError('licToken', 'Falha ao emitir: ' . $e->getMessage());
        }
    }

    public function publicarVersao(): void
    {
        $this->validate([
            'verVersao'  => 'required|string|max:40',
            'verUrl'     => 'required|url',
            'verSha'     => 'required|string|size:64',
            'verMin'     => 'nullable|string|max:40',
            'verNotas'   => 'nullable|string|max:2000',
            'verRollout' => 'required|in:none,all',
        ]);

        if (!$this->chaveUpdates()) {
            $this->addError('verVersao', 'LICENSE_UPDATE_SIGNING_KEY (ou LICENSE_SIGNING_KEY) não configurada.');

            return;
        }

        $claims = array_filter([
            'versao'        => $this->verVersao,
            'min_versao'    => $this->verMin ?: null,
            'notas'         => $this->verNotas ?: null,
            'pacote_url'    => $this->verUrl,
            'pacote_sha256' => strtolower($this->verSha),
            'obrigatorio'   => $this->verObrigatorio,
        ], fn ($v) => $v !== null && $v !== '');

        $manifesto = (new UpdateSigner())->assinar($claims, $this->chaveUpdates());

        AppUpdate::updateOrCreate(
            ['versao' => $this->verVersao],
            [
                'min_versao'    => $this->verMin ?: null,
                'notas'         => $this->verNotas ?: null,
                'pacote_url'    => $this->verUrl,
                'pacote_sha256' => strtolower($this->verSha),
                'obrigatorio'   => $this->verObrigatorio,
                'rollout'       => $this->verRollout,
                'manifesto'     => $manifesto,
            ]
        );

        $this->reset(['verVersao', 'verMin', 'verUrl', 'verSha', 'verNotas', 'verObrigatorio']);
        $this->verRollout = 'none';
        session()->flash('ok', 'Versão publicada e assinada.');
    }

    public function definirRollout(int $id, string $rollout): void
    {
        if (!in_array($rollout, ['none', 'all'], true)) {
            return;
        }
        AppUpdate::whereKey($id)->update(['rollout' => $rollout]);
        session()->flash('ok', 'Rollout atualizado.');
    }

    public function adicionarAlvo(): void
    {
        $this->validate([
            'alvoTenantId' => 'required|integer|exists:tenants,id',
            'alvoVersao'   => 'required|string|exists:app_updates,versao',
        ]);

        AppUpdateTarget::firstOrCreate([
            'tenant_id' => (int) $this->alvoTenantId,
            'versao'    => $this->alvoVersao,
        ]);
        $this->reset(['alvoTenantId', 'alvoVersao']);
        session()->flash('ok', 'Tenant adicionado ao rollout dessa versão.');
    }

    public function removerAlvo(int $id): void
    {
        AppUpdateTarget::whereKey($id)->delete();
        session()->flash('ok', 'Tenant removido do rollout.');
    }

    public function render()
    {
        return view('livewire.super-admin.licenciamento.licenciamento', [
            'tenants'       => Tenant::orderBy('name')->get(['id', 'name']),
            'versoes'       => AppUpdate::with('targets.tenant:id,name')->orderByDesc('id')->get(),
            'chaveLicOk'    => (bool) $this->chaveLicencas(),
            'chaveUpdOk'    => (bool) $this->chaveUpdates(),
        ]);
    }
}
