<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\AppUpdate;
use App\Models\AppUpdateTarget;
use App\Models\LicencaEmitida;
use App\Models\LicenseRequest;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Licensing\LicenseIssuer;
use App\Services\Licensing\UpdateSigner;
use App\Services\SmsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * O LICENCIAMENTO OFFLINE — emitir licenças, aprovar pedidos, acompanhar as
 * instalações, publicar versões e decidir o rollout por empresa.
 *
 * Usa as chaves PRIVADAS do servidor (LICENSE_SIGNING_KEY e a das
 * actualizações). Nunca as devolve: diz só se estão configuradas e, se não
 * servirem, porquê.
 *
 * O que muda em relação ao componente:
 *
 *  · UM PEDIDO SÓ SE APROVA OU RECUSA UMA VEZ. Aprovar um já recusado criava
 *    uma empresa nova e uma licença para quem tinha sido recusado; recusar um
 *    já aprovado deixava a licença emitida e o pedido a dizer «recusado».
 *  · A CHAVE É VERIFICADA ANTES DE ASSINAR em todas as portas. Só a renovação o
 *    fazia; emitir e aprovar com a chave pública lá posta davam erro 500.
 *  · UMA FALHA A ASSINAR A VERSÃO devolve o erro, em vez de rebentar.
 */
class LicenciamentoApiController extends Controller
{
    public function index(): JsonResponse
    {
        $instalacoes = LicencaEmitida::with('tenant:id,name,is_active')
            ->orderByRaw('ultimo_checkin IS NULL DESC')
            ->orderByDesc('ultimo_checkin')
            ->limit(50)
            ->get();

        return response()->json([
            'estado' => [
                'cripto' => LicenseIssuer::criptoDisponivel(),
                'chave_das_licencas' => (bool) config('licensing.signing_key'),
                'chave_das_versoes' => (bool) config('licensing.update.signing_key'),
                'problema_da_chave' => $this->problemaDaChave(),
            ],
            'resumo' => [
                'total' => $instalacoes->count(),
                'activas' => $instalacoes->filter(fn ($i) => $i->situacao() === 'activa')->count(),
                'silenciosas' => $instalacoes->filter(fn ($i) => $i->situacao() === 'silenciosa')->count(),
                'expiradas' => $instalacoes->filter(fn ($i) => $i->situacao() === 'expirada')->count(),
                'por_ligar' => $instalacoes->filter(fn ($i) => $i->situacao() === 'nunca_ligou')->count(),
            ],
            'instalacoes' => $instalacoes->map(fn (LicencaEmitida $i) => [
                'id' => $i->id,
                'empresa' => $i->tenant?->name ?? '#'.$i->tenant_id,
                'maquina' => $i->fingerprint,
                'plano' => $i->plano,
                'todos_os_modulos' => in_array('*', (array) $i->modulos, true),
                'modulos' => count((array) $i->modulos),
                'max_utilizadores' => $i->max_users,
                'versao' => $i->versao_instalada,
                'ultimo_contacto' => $i->ultimo_checkin?->toIso8601String(),
                'situacao' => $i->situacao(),
                'expira_em' => $i->expira_em?->toIso8601String(),
            ]),
            'pedidos' => LicenseRequest::orderByRaw("FIELD(estado,'pendente','aprovado','recusado')")
                ->orderByDesc('id')->limit(30)->get()
                ->map(fn (LicenseRequest $p) => [
                    'id' => $p->id,
                    'codigo' => $p->codigo,
                    'empresa' => $p->empresa,
                    'nif' => $p->nif,
                    'email' => $p->email,
                    'telefone' => $p->telefone,
                    'responsavel' => $p->responsavel,
                    'utilizadores' => $p->utilizadores,
                    'maquina' => $p->fingerprint,
                    'observacoes' => $p->observacoes,
                    'estado' => $p->estado,
                    'motivo_recusa' => $p->motivo_recusa,
                    'entregue' => (bool) $p->entregue_em,
                    'pedido_em' => $p->created_at?->toIso8601String(),
                ]),
            'versoes' => AppUpdate::with('targets.tenant:id,name')->orderByDesc('id')->get()
                ->map(fn (AppUpdate $v) => [
                    'id' => $v->id,
                    'versao' => $v->versao,
                    'min_versao' => $v->min_versao,
                    'rollout' => $v->rollout,
                    'obrigatorio' => (bool) $v->obrigatorio,
                    'alvos' => $v->targets->map(fn ($a) => ['id' => $a->id, 'empresa' => $a->tenant?->name ?? '#'.$a->tenant_id])->values(),
                ]),
            'opcoes' => [
                'empresas' => Tenant::orderBy('name')->get(['id', 'name'])->map(fn ($t) => ['id' => $t->id, 'nome' => $t->name]),
                'planos' => Plan::orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['id' => $p->id, 'nome' => $p->name]),
                'modulos' => Module::orderBy('name')->get(['slug', 'name'])->map(fn ($m) => ['slug' => $m->slug, 'nome' => $m->name]),
            ],
        ]);
    }

    /** A ficha de uma instalação: licença, máquina, contactos e o pedido que a originou. */
    public function instalacao(int $id): JsonResponse
    {
        $i = LicencaEmitida::with('tenant', 'pedido')->findOrFail($id);

        return response()->json(['instalacao' => [
            'id' => $i->id,
            'empresa' => $i->tenant ? [
                'nome' => $i->tenant->name,
                'nif' => $i->tenant->nif,
                'email' => $i->tenant->email,
                'telefone' => $i->tenant->phone,
                'activa' => (bool) $i->tenant->is_active,
            ] : null,
            'plano' => $i->plano,
            'modulos' => (array) $i->modulos,
            'max_utilizadores' => $i->max_users,
            'emitida_em' => $i->emitida_em?->toIso8601String(),
            'expira_em' => $i->expira_em?->toIso8601String(),
            'dias_sugeridos' => $this->diasDaLicenca($i),
            'maquina' => $i->fingerprint,
            'versao' => $i->versao_instalada,
            'ultimo_ip' => $i->ultimo_ip,
            'ultimo_contacto' => $i->ultimo_checkin?->toIso8601String(),
            'situacao' => $i->situacao(),
            'pedido' => $i->pedido ? ['codigo' => $i->pedido->codigo, 'responsavel' => $i->pedido->responsavel] : null,
            // Assinado e preso à máquina: guardado para se poder reenviar a
            // quem o perdeu sem emitir outro.
            'token' => $i->token,
        ]]);
    }

    public function emitir(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'dias' => ['required', 'integer', 'min:1', 'max:3650'],
            'graca' => ['nullable', 'integer', 'min:1', 'max:365'],
            'fingerprint' => ['nullable', 'string', 'max:64'],
            'max_utilizadores' => ['nullable', 'integer', 'min:1', 'max:500'],
            'todos_os_modulos' => ['boolean'],
            'modulos' => ['array'],
            'modulos.*' => ['string', 'exists:modules,slug'],
        ]);

        $this->exigirChave('tenant_id');

        $tenant = Tenant::findOrFail($dados['tenant_id']);
        $todos = (bool) ($dados['todos_os_modulos'] ?? true);

        if (! $todos && empty($dados['modulos'])) {
            throw ValidationException::withMessages(['modulos' => __('Escolha pelo menos um módulo, ou marque todos.')]);
        }

        $claims = $this->semVazios([
            'tenant_id' => $tenant->id,
            'empresa' => $tenant->name,
            'nif' => $tenant->nif,
            'plano' => $tenant->activeSubscription?->plan?->name,
            'modulos' => $todos ? ['*'] : array_values($dados['modulos']),
            'max_users' => $dados['max_utilizadores'] ?? null,
            'exp' => CarbonImmutable::now()->addDays((int) $dados['dias'])->getTimestamp(),
            'graca' => $dados['graca'] ?? null,
            'fp' => ($dados['fingerprint'] ?? null) ?: null,
            'env' => 'prod',
        ]);

        $token = $this->assinar($claims, 'tenant_id');
        $this->registarInstalacao($tenant, $claims, null, $token);

        return response()->json([
            'message' => __('Licença emitida para :empresa. Copie o token abaixo.', ['empresa' => $tenant->name]),
            'token' => $token,
        ]);
    }

    public function guardarEmpresa(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:2', 'max:255'],
            'nif' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:30'],
        ]);

        $tenant = $this->empresaDaInstalacao($id);

        $tenant->update([
            'name' => $dados['nome'],
            'nif' => ($dados['nif'] ?? null) ?: null,
            'email' => ($dados['email'] ?? null) ?: null,
            'phone' => ($dados['telefone'] ?? null) ?: null,
        ]);

        return response()->json(['message' => __('Ficha da empresa actualizada. O nome novo entra na próxima licença emitida.')]);
    }

    /** Suspender corta o acesso a TODA a gente desta empresa no próximo check-in. */
    public function alternarSuspensao(int $id): JsonResponse
    {
        $tenant = $this->empresaDaInstalacao($id);
        $activa = ! $tenant->is_active;
        $tenant->update(['is_active' => $activa]);

        return response()->json([
            'message' => $activa
                ? __('Empresa reactivada. A instalação volta a funcionar no próximo check-in.')
                : __('Empresa suspensa. A instalação bloqueia no próximo check-in.'),
            'activa' => $activa,
        ]);
    }

    public function avisar(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate(['mensagem' => ['required', 'string', 'min:5', 'max:300']]);
        $telefone = $this->empresaDaInstalacao($id)->phone;

        if (! $telefone) {
            throw ValidationException::withMessages(['mensagem' => __('Esta empresa não tem telefone na ficha.')]);
        }

        try {
            // Sem empresa: sai pelas credenciais da PLATAFORMA. Com o id da
            // empresa, o aviso que lhe mandamos saía da conta dela.
            $r = app(SmsService::class)->send($telefone, $dados['mensagem'], 'aviso_licenca', null, null);

            if (! ($r['success'] ?? false)) {
                throw new \RuntimeException($r['error'] ?? 'recusado');
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => __('Não foi possível enviar: :erro', ['erro' => $e->getMessage()])], 422);
        }

        return response()->json(['message' => __('Aviso enviado para :telefone.', ['telefone' => $telefone])]);
    }

    /** Renova reaproveitando plano, módulos, tecto e a máquina a que está presa. */
    public function renovar(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate(['dias' => ['required', 'integer', 'min:1', 'max:3650']], [
            'dias.*' => __('Indique entre 1 e 3650 dias.'),
        ]);

        $this->exigirChave('dias');

        $i = LicencaEmitida::with('tenant')->findOrFail($id);

        if (! $i->tenant) {
            throw ValidationException::withMessages(['dias' => __('A empresa desta instalação já não existe.')]);
        }

        $dias = (int) $dados['dias'];
        $claims = $this->semVazios([
            'tenant_id' => $i->tenant_id,
            'empresa' => $i->tenant->name,
            'nif' => $i->tenant->nif,
            'plano' => $i->plano ?? $i->tenant->activeSubscription?->plan?->name,
            'modulos' => $i->modulos ?: ['*'],
            'max_users' => $i->max_users ?: null,
            'fp' => $i->fingerprint ?: null,
            'exp' => CarbonImmutable::now()->addDays($dias)->getTimestamp(),
            'env' => 'prod',
        ]);

        $token = $this->assinar($claims, 'dias');

        $i->forceFill(['token' => $token, 'emitida_em' => now(), 'expira_em' => CarbonImmutable::now()->addDays($dias)])->save();

        return response()->json([
            'message' => __('Licença renovada por :dias dias. A instalação recebe-a no próximo check-in — ou copie o token abaixo.', ['dias' => $dias]),
            'token' => $token,
        ]);
    }

    /**
     * Aprova o pedido: cria a empresa (se ainda não houver), dá-lhe o plano e
     * emite a licença com módulos e tecto. A instalação vai buscá-la sozinha.
     */
    public function aprovarPedido(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate([
            'plano_id' => ['required', 'exists:plans,id'],
            'dias' => ['required', 'integer', 'min:1', 'max:3650'],
            'max_utilizadores' => ['nullable', 'integer', 'min:1', 'max:500'],
            'todos_os_modulos' => ['boolean'],
            'modulos' => ['array'],
            'modulos.*' => ['string', 'exists:modules,slug'],
            'prender_a_maquina' => ['boolean'],
        ]);

        $this->exigirChave('plano_id');

        $todos = (bool) ($dados['todos_os_modulos'] ?? true);

        if (! $todos && empty($dados['modulos'])) {
            throw ValidationException::withMessages(['modulos' => __('Escolha pelo menos um módulo, ou marque todos.')]);
        }

        $plano = Plan::findOrFail($dados['plano_id']);
        $dias = (int) $dados['dias'];
        $maxUtilizadores = $dados['max_utilizadores'] ?? null;

        DB::transaction(function () use ($id, $plano, $dias, $maxUtilizadores, $todos, $dados) {
            $p = LicenseRequest::lockForUpdate()->findOrFail($id);

            if ($p->estado !== LicenseRequest::PENDENTE) {
                throw ValidationException::withMessages(['pedido' => __('Este pedido já foi decidido.')]);
            }

            $tenant = $p->tenant_id ? Tenant::find($p->tenant_id) : null;

            if (! $tenant) {
                $tenant = Tenant::create([
                    'name' => $p->empresa,
                    'nif' => $p->nif,
                    'email' => $p->email,
                    'phone' => $p->telefone,
                    'is_active' => true,
                    'max_users' => $maxUtilizadores,
                ]);

                $fim = CarbonImmutable::now()->addDays($dias);
                $tenant->subscriptions()->create([
                    'plan_id' => $plano->id,
                    'status' => 'active',
                    'current_period_start' => CarbonImmutable::now(),
                    'current_period_end' => $fim,
                    'ends_at' => $fim,
                    'amount' => $plano->price_monthly ?? 0,
                    'billing_cycle' => 'monthly',
                ]);
            }

            $claims = $this->semVazios([
                'tenant_id' => $tenant->id,
                'empresa' => $tenant->name,
                'nif' => $tenant->nif,
                'plano' => $plano->name,
                'modulos' => $todos ? ['*'] : array_values($dados['modulos']),
                'max_users' => $maxUtilizadores,
                // Prende à máquina que fez o pedido — é para ela que é.
                'fp' => ($dados['prender_a_maquina'] ?? true) ? $p->fingerprint : null,
                'exp' => CarbonImmutable::now()->addDays($dias)->getTimestamp(),
                'env' => 'prod',
            ]);

            // Assinada UMA vez: assinar duas dava dois tokens diferentes (o
            // `iat` muda), e o que o cliente ia buscar deixava de ser o que
            // ficou registado na instalação.
            $token = $this->assinar($claims, 'plano_id');
            $this->registarInstalacao($tenant, $claims, $p->id, $token);

            $p->forceFill([
                'estado' => LicenseRequest::APROVADO,
                'tenant_id' => $tenant->id,
                'licenca' => $token,
                'aprovado_em' => now(),
            ])->save();
        });

        return response()->json(['message' => __('Pedido aprovado: empresa criada e licença emitida. A instalação vai buscá-la na próxima verificação.')]);
    }

    public function recusarPedido(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:255']]);

        $alterados = LicenseRequest::whereKey($id)->where('estado', LicenseRequest::PENDENTE)->update([
            'estado' => LicenseRequest::RECUSADO,
            'motivo_recusa' => $dados['motivo'],
        ]);

        if ($alterados === 0) {
            LicenseRequest::findOrFail($id);

            throw ValidationException::withMessages(['pedido' => __('Este pedido já foi decidido.')]);
        }

        return response()->json(['message' => __('Pedido recusado.')]);
    }

    public function publicarVersao(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'versao' => ['required', 'string', 'max:40'],
            'min_versao' => ['nullable', 'string', 'max:40'],
            'pacote_url' => ['required', 'url'],
            'pacote_sha256' => ['required', 'string', 'size:64', 'regex:/^[0-9a-fA-F]{64}$/'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'obrigatorio' => ['boolean'],
            'rollout' => ['required', 'in:none,all'],
        ]);

        $chave = config('licensing.update.signing_key');

        if (! $chave) {
            throw ValidationException::withMessages(['versao' => __('LICENSE_UPDATE_SIGNING_KEY (ou LICENSE_SIGNING_KEY) não configurada.')]);
        }

        $claims = array_filter([
            'versao' => $dados['versao'],
            'min_versao' => ($dados['min_versao'] ?? null) ?: null,
            'notas' => ($dados['notas'] ?? null) ?: null,
            'pacote_url' => $dados['pacote_url'],
            'pacote_sha256' => strtolower($dados['pacote_sha256']),
            'obrigatorio' => (bool) ($dados['obrigatorio'] ?? false),
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $manifesto = (new UpdateSigner())->assinar($claims, $chave);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['versao' => __('Falha ao assinar: :erro', ['erro' => $e->getMessage()])]);
        }

        AppUpdate::updateOrCreate(['versao' => $dados['versao']], [
            'min_versao' => $claims['min_versao'] ?? null,
            'notas' => $claims['notas'] ?? null,
            'pacote_url' => $claims['pacote_url'],
            'pacote_sha256' => $claims['pacote_sha256'],
            'obrigatorio' => $claims['obrigatorio'],
            'rollout' => $dados['rollout'],
            'manifesto' => $manifesto,
        ]);

        return response()->json(['message' => __('Versão publicada e assinada.')]);
    }

    public function definirRollout(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate(['rollout' => ['required', 'in:none,all']]);

        AppUpdate::findOrFail($id)->update(['rollout' => $dados['rollout']]);

        return response()->json(['message' => __('Rollout atualizado.')]);
    }

    public function adicionarAlvo(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'versao' => ['required', 'string', 'exists:app_updates,versao'],
        ]);

        AppUpdateTarget::firstOrCreate(['tenant_id' => (int) $dados['tenant_id'], 'versao' => $dados['versao']]);

        return response()->json(['message' => __('Tenant adicionado ao rollout dessa versão.')]);
    }

    public function removerAlvo(int $id): JsonResponse
    {
        AppUpdateTarget::findOrFail($id)->delete();

        return response()->json(['message' => __('Tenant removido do rollout.')]);
    }

    /**
     * A chave configurada é mesmo uma chave privada Ed25519? Null se estiver
     * boa; senão, a explicação — para o painel avisar ANTES de alguém carregar
     * em Emitir. A mensagem nunca leva a chave.
     */
    private function problemaDaChave(): ?string
    {
        $k = config('licensing.signing_key');

        if (! $k) {
            return __('LICENSE_SIGNING_KEY não está definida no .env do servidor.');
        }

        $b = base64_decode(trim($k), true);

        if ($b === false) {
            return __('LICENSE_SIGNING_KEY não é Base64 válido (copie a chave privada inteira, sem espaços).');
        }

        $n = strlen($b);

        if ($n === 32) {
            return __('LICENSE_SIGNING_KEY tem a chave PÚBLICA (32 bytes). É preciso a chave PRIVADA — a linha de 88 caracteres do ficheiro de chaves.');
        }

        if ($n !== 64) {
            return __('LICENSE_SIGNING_KEY tem :n bytes; uma chave privada Ed25519 tem 64 (88 caracteres em Base64). Parece truncada.', ['n' => $n]);
        }

        return null;
    }

    private function exigirChave(string $campo): void
    {
        if ($problema = $this->problemaDaChave()) {
            throw ValidationException::withMessages([$campo => $problema]);
        }
    }

    private function assinar(array $claims, string $campo): string
    {
        try {
            return (new LicenseIssuer())->emitir($claims, (string) config('licensing.signing_key'));
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([$campo => __('Falha ao emitir: :erro', ['erro' => $e->getMessage()])]);
        }
    }

    private function semVazios(array $claims): array
    {
        return array_filter($claims, fn ($v) => $v !== null && $v !== []);
    }

    private function empresaDaInstalacao(int $id): Tenant
    {
        $i = LicencaEmitida::with('tenant')->findOrFail($id);

        if (! $i->tenant) {
            throw ValidationException::withMessages(['empresa' => __('A empresa já não existe.')]);
        }

        return $i->tenant;
    }

    /** Uma instalação = empresa + máquina; renovar actualiza a mesma linha. */
    private function registarInstalacao(Tenant $tenant, array $claims, ?int $pedidoId, string $token): void
    {
        LicencaEmitida::updateOrCreate(
            ['tenant_id' => $tenant->id, 'fingerprint' => $claims['fp'] ?? null],
            [
                'license_request_id' => $pedidoId,
                'plano' => $claims['plano'] ?? null,
                'modulos' => $claims['modulos'] ?? null,
                'max_users' => $claims['max_users'] ?? null,
                'token' => $token,
                'emitida_em' => now(),
                'expira_em' => isset($claims['exp']) ? CarbonImmutable::createFromTimestamp($claims['exp']) : null,
            ]
        );
    }

    /**
     * Quantos dias propor em «Renovar por»: o que ainda falta. Abrir sempre em
     * 365 escondia que o cliente só tinha um dia; já expirada, propõe o
     * período que lhe foi vendido da última vez.
     */
    private function diasDaLicenca(LicencaEmitida $i): int
    {
        if (! $i->expira_em) {
            return 365;
        }

        $faltam = (int) ceil(now()->floatDiffInDays($i->expira_em, false));

        if ($faltam >= 1) {
            return min($faltam, 3650);
        }

        if ($i->emitida_em) {
            $periodo = (int) ceil($i->emitida_em->floatDiffInDays($i->expira_em, false));

            if ($periodo >= 1) {
                return min($periodo, 3650);
            }
        }

        return 365;
    }
}
