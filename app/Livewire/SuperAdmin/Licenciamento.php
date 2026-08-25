<?php

namespace App\Livewire\SuperAdmin;

use App\Models\AppUpdate;
use App\Models\AppUpdateTarget;
use App\Models\LicencaEmitida;
use App\Models\LicenseRequest;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Licensing\LicenseIssuer;
use App\Services\Licensing\UpdateSigner;
use Illuminate\Support\Facades\DB;
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
    public $licMaxUsers = '';
    public array $licModulos = [];
    public bool $licTodosModulos = true;
    public ?string $licToken = null;

    // Aprovação de pedidos
    public $pedidoId = null;
    public $pedPlanoId = '';
    public $pedDias = 365;
    public $pedMaxUsers = '';
    public array $pedModulos = [];
    public bool $pedTodosModulos = true;
    public bool $pedPrenderMaquina = true;
    public $pedMotivoRecusa = '';

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

    /**
     * A chave configurada é mesmo uma chave privada Ed25519?
     * Devolve null se estiver boa, ou a explicação do que está errado — para o
     * painel avisar ANTES de alguém carregar em Emitir e levar um erro 500.
     */
    private function problemaDaChave(): ?string
    {
        $k = $this->chaveLicencas();
        if (!$k) {
            return 'LICENSE_SIGNING_KEY não está definida no .env do servidor.';
        }

        $b = base64_decode(trim($k), true);
        if ($b === false) {
            return 'LICENSE_SIGNING_KEY não é Base64 válido (copie a chave privada inteira, sem espaços).';
        }

        $n = strlen($b);
        if ($n === 32) {
            return 'LICENSE_SIGNING_KEY tem a chave PÚBLICA (32 bytes). É preciso a chave PRIVADA — '
                . 'a linha de 88 caracteres do ficheiro de chaves.';
        }
        if ($n !== 64) {
            return 'LICENSE_SIGNING_KEY tem ' . $n . ' bytes; uma chave privada Ed25519 tem 64 '
                . '(88 caracteres em Base64). Parece truncada.';
        }

        return null;
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
            'modulos'   => $this->licTodosModulos ? ['*'] : array_values($this->licModulos),
            'max_users' => $this->licMaxUsers !== '' ? (int) $this->licMaxUsers : null,
            'exp'       => CarbonImmutable::now()->addDays((int) $this->licDias)->getTimestamp(),
            'graca'     => $this->licGraca !== '' ? (int) $this->licGraca : null,
            'fp'        => $this->licBindFp ?: null,
            'env'       => 'prod',
        ], fn ($v) => $v !== null && $v !== []);

        try {
            $this->licToken = (new LicenseIssuer())->emitir($claims, $this->chaveLicencas());
            $this->registarInstalacao($tenant, $claims, null, $this->licToken);
            session()->flash('ok', 'Licença emitida para ' . $tenant->name . '. Copie o token abaixo.');
        } catch (\Throwable $e) {
            $this->addError('licToken', 'Falha ao emitir: ' . $e->getMessage());
        }
    }

    /**
     * Deixa registada a instalação a que esta licença se destina, para o painel
     * poder listar os clientes offline. Uma instalação = empresa + máquina; uma
     * renovação actualiza a mesma linha.
     */
    private function registarInstalacao(Tenant $tenant, array $claims, ?int $pedidoId = null, ?string $token = null): void
    {
        LicencaEmitida::updateOrCreate(
            ['tenant_id' => $tenant->id, 'fingerprint' => $claims['fp'] ?? null],
            [
                'license_request_id' => $pedidoId,
                'plano'      => $claims['plano'] ?? null,
                'modulos'    => $claims['modulos'] ?? null,
                'max_users'  => $claims['max_users'] ?? null,
                // Guardado para se poder reenviar a quem a perdeu, sem emitir
                // outra. É assinado e só vale na máquina a que se destina.
                'token'      => $token,
                'emitida_em' => now(),
                'expira_em'  => isset($claims['exp']) ? CarbonImmutable::createFromTimestamp($claims['exp']) : null,
            ]
        );
    }

    // ── Clientes offline: ver, editar e renovar ─────────────────────────
    public $instalacaoId = null;
    public $edNome = '';
    public $edNif = '';
    public $edEmail = '';
    public $edTelefone = '';
    public $edDias = 365;
    public $msgTexto = '';

    /** Abre a ficha de uma instalação (licença, máquina, contactos, histórico). */
    public function verInstalacao(int $id): void
    {
        $this->instalacaoId = $id;
        $i = LicencaEmitida::with('tenant')->find($id);
        $this->edNome     = $i?->tenant?->name ?? '';
        $this->edNif      = $i?->tenant?->nif ?? '';
        $this->edEmail    = $i?->tenant?->email ?? '';
        $this->edTelefone = $i?->tenant?->phone ?? '';
        $this->edDias     = $this->diasDaLicenca($i);
        $this->msgTexto   = '';
    }

    /**
     * Quantos dias mostrar na caixa "Renovar por": o que ainda falta desta
     * licenca. So cai para 365 quando nao ha nada de onde tirar - abrir sempre
     * em 365 escondia que o cliente so tinha 1 dia.
     */
    private function diasDaLicenca(?LicencaEmitida $i): int
    {
        if (!$i || !$i->expira_em) {
            return 365;
        }

        $faltam = (int) ceil(now()->floatDiffInDays($i->expira_em, false));
        if ($faltam >= 1) {
            return min($faltam, 3650);
        }

        // Ja expirou: propor o mesmo periodo que lhe foi vendido da ultima vez.
        if ($i->emitida_em) {
            $periodo = (int) ceil($i->emitida_em->floatDiffInDays($i->expira_em, false));
            if ($periodo >= 1) {
                return min($periodo, 3650);
            }
        }

        return 365;
    }

    /**
     * Renovar com o numero de dias escrito na caixa. Existe porque wire:click
     * so aceita valores literais - nao consegue ler $edDias sozinho.
     */
    public function renovarComDias(int $id): void
    {
        $dias = (int) $this->edDias;

        if ($dias < 1 || $dias > 3650) {
            session()->flash('erro', 'Indique entre 1 e 3650 dias.');

            return;
        }

        $this->renovarInstalacao($id, $dias);
    }

    /** Corrigir a ficha da empresa sem sair daqui. */
    public function guardarEmpresa(): void
    {
        $this->validate([
            'edNome'     => 'required|string|min:2|max:255',
            'edNif'      => 'nullable|string|max:30',
            'edEmail'    => 'nullable|email|max:255',
            'edTelefone' => 'nullable|string|max:30',
        ]);

        $i = LicencaEmitida::with('tenant')->findOrFail($this->instalacaoId);
        if (!$i->tenant) { session()->flash('erro', 'A empresa já não existe.'); return; }

        $i->tenant->update([
            'name'  => $this->edNome,
            'nif'   => $this->edNif ?: null,
            'email' => $this->edEmail ?: null,
            'phone' => $this->edTelefone ?: null,
        ]);

        session()->flash('ok', 'Ficha da empresa actualizada. O nome novo entra na próxima licença emitida.');
    }

    /**
     * Suspender corta o acesso a TODA a gente desta empresa: o check-in
     * seguinte manda a instalação bloquear. Reactivar devolve-o.
     */
    public function alternarSuspensao(): void
    {
        $i = LicencaEmitida::with('tenant')->findOrFail($this->instalacaoId);
        if (!$i->tenant) { session()->flash('erro', 'A empresa já não existe.'); return; }

        $activa = !$i->tenant->is_active;
        $i->tenant->update(['is_active' => $activa]);

        session()->flash('ok', $activa
            ? 'Empresa reactivada. A instalação volta a funcionar no próximo check-in.'
            : 'Empresa suspensa. A instalação bloqueia no próximo check-in.');
    }

    /** Aviso ao cliente por SMS (usa o contacto da empresa). */
    public function enviarAviso(): void
    {
        $this->validate(['msgTexto' => 'required|string|min:5|max:300']);

        $i = LicencaEmitida::with('tenant')->findOrFail($this->instalacaoId);
        $telefone = $i->tenant?->phone;

        if (!$telefone) { session()->flash('erro', 'Esta empresa não tem telefone na ficha.'); return; }

        try {
            // tenantId nulo: credenciais da PLATAFORMA. Com o id da empresa, o
            // aviso que lhe mandamos saía da conta dela.
            $r = app(\App\Services\SmsService::class)
                ->send($telefone, $this->msgTexto, 'aviso_licenca', null, null);

            if (!($r['success'] ?? false)) { throw new \RuntimeException($r['error'] ?? 'recusado'); }

            $this->msgTexto = '';
            session()->flash('ok', 'Aviso enviado para ' . $telefone . '.');
        } catch (\Throwable $e) {
            session()->flash('erro', 'Não foi possível enviar: ' . $e->getMessage());
        }
    }

    public function fecharInstalacao(): void
    {
        $this->instalacaoId = null;
    }

    /**
     * Renova a licença de uma instalação já conhecida, sem obrigar a repetir
     * escolhas: reaproveita plano, módulos, tecto e a máquina a que está presa.
     */
    public function renovarInstalacao(int $id, int $dias = 365): void
    {
        if ($problema = $this->problemaDaChave()) {
            session()->flash('erro', $problema);

            return;
        }

        $i = LicencaEmitida::with('tenant')->findOrFail($id);
        if (!$i->tenant) {
            session()->flash('erro', 'A empresa desta instalação já não existe.');

            return;
        }

        $claims = array_filter([
            'tenant_id' => $i->tenant_id,
            'empresa'   => $i->tenant->name,
            'nif'       => $i->tenant->nif,
            'plano'     => $i->plano ?? $i->tenant->activeSubscription?->plan?->name,
            'modulos'   => $i->modulos ?: ['*'],
            'max_users' => $i->max_users ?: null,
            'fp'        => $i->fingerprint ?: null,
            'exp'       => CarbonImmutable::now()->addDays($dias)->getTimestamp(),
            'env'       => 'prod',
        ], fn ($v) => $v !== null && $v !== []);

        try {
            $token = (new LicenseIssuer())->emitir($claims, $this->chaveLicencas());
            $i->forceFill([
                'token'      => $token,
                'emitida_em' => now(),
                'expira_em'  => CarbonImmutable::now()->addDays($dias),
            ])->save();

            $this->instalacaoId = $i->id;
            session()->flash('ok', 'Licença renovada por ' . $dias . ' dias. '
                . 'A instalação recebe-a no próximo check-in — ou copie o token abaixo.');
        } catch (\Throwable $e) {
            session()->flash('erro', 'Falha ao renovar: ' . $e->getMessage());
        }
    }

    /** Abre o painel de aprovação de um pedido, pré-preenchido. */
    public function abrirPedido(int $id): void
    {
        $p = LicenseRequest::findOrFail($id);
        $this->pedidoId = $p->id;
        $this->pedMaxUsers = $p->utilizadores ?: '';
        $this->pedPlanoId = Plan::query()->value('id');
        $this->pedTodosModulos = true;
        $this->pedModulos = [];
        $this->pedMotivoRecusa = '';
    }

    public function fecharPedido(): void
    {
        $this->reset(['pedidoId', 'pedMotivoRecusa']);
    }

    /**
     * Aprova o pedido: cria a EMPRESA (billing) na cloud, dá-lhe o plano, e
     * emite a licença já com módulos e tecto de utilizadores. A instalação vai
     * buscá-la sozinha na próxima verificação.
     */
    public function aprovarPedido(): void
    {
        $this->validate([
            'pedPlanoId'  => 'required|exists:plans,id',
            'pedDias'     => 'required|integer|min:1|max:3650',
            'pedMaxUsers' => 'nullable|integer|min:1|max:500',
        ]);

        if (!$this->chaveLicencas()) {
            $this->addError('pedidoId', 'LICENSE_SIGNING_KEY não configurada no servidor.');

            return;
        }

        $p = LicenseRequest::findOrFail($this->pedidoId);
        $plano = Plan::find($this->pedPlanoId);

        DB::transaction(function () use ($p, $plano) {
            // Reaproveita a empresa se o pedido já tiver uma; senão cria.
            $tenant = $p->tenant_id ? Tenant::find($p->tenant_id) : null;

            if (!$tenant) {
                $tenant = Tenant::create([
                    'name'      => $p->empresa,
                    'nif'       => $p->nif,
                    'email'     => $p->email,
                    'phone'     => $p->telefone,
                    'is_active' => true,
                    'max_users' => $this->pedMaxUsers !== '' ? (int) $this->pedMaxUsers : null,
                ]);

                $fim = CarbonImmutable::now()->addDays((int) $this->pedDias);
                $tenant->subscriptions()->create([
                    'plan_id'              => $plano->id,
                    'status'               => 'active',
                    'current_period_start' => CarbonImmutable::now(),
                    'current_period_end'   => $fim,
                    'ends_at'              => $fim,
                    'amount'               => $plano->price_monthly ?? 0,  // NOT NULL
                    'billing_cycle'        => 'monthly',
                ]);
            }

            $claims = array_filter([
                'tenant_id' => $tenant->id,
                'empresa'   => $tenant->name,
                'nif'       => $tenant->nif,
                'plano'     => $plano->name,
                'modulos'   => $this->pedTodosModulos ? ['*'] : array_values($this->pedModulos),
                'max_users' => $this->pedMaxUsers !== '' ? (int) $this->pedMaxUsers : null,
                // Prende à máquina que fez o pedido — é para ela que é.
                'fp'        => $this->pedPrenderMaquina ? $p->fingerprint : null,
                'exp'       => CarbonImmutable::now()->addDays((int) $this->pedDias)->getTimestamp(),
                'env'       => 'prod',
            ], fn ($v) => $v !== null && $v !== []);

            // Assinada UMA vez e reutilizada: assinar duas vezes dava dois
            // tokens diferentes (o `iat` muda), e o que o cliente ia buscar
            // deixava de ser o que ficou registado na instalação.
            $token = (new LicenseIssuer())->emitir($claims, $this->chaveLicencas());
            $this->registarInstalacao($tenant, $claims, $p->id, $token);

            $p->forceFill([
                'estado'      => LicenseRequest::APROVADO,
                'tenant_id'   => $tenant->id,
                'licenca'     => $token,
                'aprovado_em' => now(),
            ])->save();
        });

        $this->fecharPedido();
        session()->flash('ok', 'Pedido aprovado: empresa criada e licença emitida. '
            . 'A instalação vai buscá-la na próxima verificação.');
    }

    public function recusarPedido(): void
    {
        $this->validate(['pedMotivoRecusa' => 'required|string|min:5|max:255']);

        LicenseRequest::whereKey($this->pedidoId)->update([
            'estado'        => LicenseRequest::RECUSADO,
            'motivo_recusa' => $this->pedMotivoRecusa,
        ]);

        $this->fecharPedido();
        session()->flash('ok', 'Pedido recusado.');
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
            'criptoOk'      => LicenseIssuer::criptoDisponivel(),
            'problemaChave' => $this->problemaDaChave(),
            'instalacoes'   => $instalacoes = LicencaEmitida::with('tenant:id,name,is_active,nif,email,phone')
                                  ->orderByRaw('ultimo_checkin IS NULL DESC')
                                  ->orderByDesc('ultimo_checkin')->limit(50)->get(),
            'instalacao'    => $this->instalacaoId
                                  ? LicencaEmitida::with('tenant', 'pedido')->find($this->instalacaoId)
                                  : null,
            // Números de topo: o que se quer saber num relance é quantas
            // instalações há e quantas estão em apuros.
            'resumo'        => [
                'total'       => $instalacoes->count(),
                'activas'     => $instalacoes->filter(fn ($i) => $i->situacao() === 'activa')->count(),
                'silenciosas' => $instalacoes->filter(fn ($i) => $i->situacao() === 'silenciosa')->count(),
                'expiradas'   => $instalacoes->filter(fn ($i) => $i->situacao() === 'expirada')->count(),
                'por_ligar'   => $instalacoes->filter(fn ($i) => $i->situacao() === 'nunca_ligou')->count(),
            ],
            'pedidos'       => LicenseRequest::orderByRaw("FIELD(estado,'pendente','aprovado','recusado')")
                                  ->orderByDesc('id')->limit(30)->get(),
            'planos'        => Plan::orderBy('name')->get(['id', 'name']),
            'modulos'       => Module::orderBy('name')->get(['slug', 'name']),
        ]);
    }
}
