<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\AGT\AGTSubmission;
use App\Models\AgentRequest;
use App\Models\AuditTrail;
use App\Models\ErroDoSistema;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenants\SinaisDeVida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Visão global, analítica e operacional da plataforma para o OpenClaw. */
class InteligenciaController extends Controller
{
    public function visaoGeral(Request $request)
    {
        $dias = max(1, min((int) $request->integer('dias', 30), 365));
        $desde = now()->subDays($dias);
        $empresas = Tenant::with('activeSubscription.plan')->get();
        $sinais = SinaisDeVida::para($empresas);

        return response()->json([
            'janela_dias' => $dias,
            'empresas' => [
                'total' => $empresas->count(),
                'activas' => $empresas->where('is_active', true)->count(),
                'suspensas' => $empresas->where('is_active', false)->count(),
                'novas' => $empresas->where('created_at', '>=', $desde)->count(),
                'a_facturar' => $sinais->filter(fn ($s) => ($s->estado['chave'] ?? null) === 'activa')->count(),
                'sem_actividade' => $sinais->filter(fn ($s) => in_array($s->estado['chave'] ?? null, ['adormecida', 'inactiva'], true))->count(),
            ],
            'utilizadores' => $this->resumoUtilizadores($desde),
            'erros' => [
                'abertos' => ErroDoSistema::abertos()->count(),
                'criticos' => ErroDoSistema::abertos()->whereIn('nivel', ['critical', 'alert', 'emergency'])->count(),
                'novos' => ErroDoSistema::where('ultima_vez', '>=', $desde)->count(),
            ],
            'planos' => $empresas->groupBy(fn ($t) => $t->activeSubscription?->plan?->name ?: 'Sem plano')->map->count()->sortDesc(),
        ]);
    }

    public function utilizadores(Request $request)
    {
        $dados = $request->validate([
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'pesquisa' => 'nullable|string|max:120',
            'estado' => 'nullable|in:activo,inactivo,todos',
            'entrou_desde' => 'nullable|date', 'novo_desde' => 'nullable|date',
            'por_pagina' => 'nullable|integer|min:1|max:100', 'pagina' => 'nullable|integer|min:1',
        ]);
        $q = User::query()->with('tenants:id,name')
            ->when(!empty($dados['tenant_id']), fn ($w) => $w->whereHas('tenants', fn ($t) => $t->where('tenants.id', $dados['tenant_id'])))
            ->when(!empty($dados['pesquisa']), function ($w) use ($dados) {
                $termo = '%' . $dados['pesquisa'] . '%';
                $w->where(fn ($x) => $x->where('name', 'like', $termo)->orWhere('email', 'like', $termo));
            })
            ->when(($dados['estado'] ?? 'todos') !== 'todos', fn ($w) => $w->where('is_active', $dados['estado'] === 'activo'))
            ->when(!empty($dados['entrou_desde']), fn ($w) => $w->where('last_login_at', '>=', $dados['entrou_desde']))
            ->when(!empty($dados['novo_desde']), fn ($w) => $w->where('created_at', '>=', $dados['novo_desde']));

        $pagina = $q->orderByDesc('last_login_at')->paginate((int) ($dados['por_pagina'] ?? 50), ['*'], 'pagina', $dados['pagina'] ?? 1);
        $online = Schema::hasTable('sessions') ? DB::table('sessions')->where('last_activity', '>=', now()->subMinutes(15)->timestamp)->pluck('user_id')->filter()->flip() : collect();

        return response()->json([
            'utilizadores' => collect($pagina->items())->map(fn (User $u) => [
                'id' => $u->id, 'nome' => $u->name, 'email' => $u->email,
                'activo' => (bool) $u->is_active, 'super_admin' => (bool) $u->is_super_admin,
                'online_15m' => $online->has($u->id),
                'ultima_entrada' => $u->last_login_at?->toIso8601String(),
                'criado_em' => $u->created_at?->toIso8601String(),
                'empresas' => $u->tenants->map(fn ($t) => ['id' => $t->id, 'nome' => $t->name]),
            ]),
            'pagina' => $pagina->currentPage(), 'paginas' => $pagina->lastPage(), 'total' => $pagina->total(),
        ]);
    }

    public function auditoria(Request $request)
    {
        $d = $request->validate([
            'tenant_id' => 'nullable|integer', 'evento' => 'nullable|string|max:100',
            'actor' => 'nullable|string|max:120', 'desde' => 'nullable|date',
            'limite' => 'nullable|integer|min:1|max:200',
        ]);
        $q = AuditTrail::query()->orderByDesc('id')
            ->when(isset($d['tenant_id']), fn ($w) => $w->where('tenant_id', $d['tenant_id']))
            ->when(!empty($d['evento']), fn ($w) => $w->where('event', 'like', '%' . $d['evento'] . '%'))
            ->when(!empty($d['actor']), fn ($w) => $w->where('actor_name', 'like', '%' . $d['actor'] . '%'))
            ->when(!empty($d['desde']), fn ($w) => $w->where('created_at', '>=', $d['desde']));

        return response()->json(['eventos' => $q->limit((int) ($d['limite'] ?? 100))->get([
            'id', 'tenant_id', 'event', 'actor_type', 'actor_name', 'channel', 'auditable_type',
            'auditable_id', 'auditable_label', 'metadata', 'ip_address', 'route', 'request_id', 'created_at',
        ])]);
    }

    /**
     * Registo de ENTRADAS e SAÍDAS: quem entrou, quem falhou a entrar, quem
     * saiu — tirado da trilha de auditoria (eventos login/logout/login_falhado,
     * gravados em AppServiceProvider).
     *
     * Responde a duas perguntas que um dono de plataforma faz sempre: "quem
     * esteve dentro desta conta e a que horas" e "há alguém a martelar a porta"
     * (uma corrida de login_falhado do mesmo email/IP). O email tentado numa
     * falha vem em `metadata`; a password nunca é registada.
     */
    public function acessos(Request $request)
    {
        $d = $request->validate([
            'tenant_id' => 'nullable|integer',
            'tipo'      => 'nullable|in:login,logout,login_falhado',
            'actor'     => 'nullable|string|max:120',
            'desde'     => 'nullable|date',
            'limite'    => 'nullable|integer|min:1|max:200',
        ]);

        $eventos = isset($d['tipo']) ? [$d['tipo']] : ['login', 'logout', 'login_falhado'];

        $q = AuditTrail::query()->orderByDesc('id')
            ->whereIn('event', $eventos)
            ->when(isset($d['tenant_id']), fn ($w) => $w->where('tenant_id', $d['tenant_id']))
            ->when(!empty($d['actor']), fn ($w) => $w->where('actor_name', 'like', '%' . $d['actor'] . '%'))
            ->when(!empty($d['desde']), fn ($w) => $w->where('created_at', '>=', $d['desde']));

        return response()->json(['acessos' => $q->limit((int) ($d['limite'] ?? 100))->get([
            'id', 'tenant_id', 'event', 'actor_type', 'actor_name', 'channel',
            'ip_address', 'metadata', 'created_at',
        ])]);
    }

    /**
     * Documentos que FALHARAM na AGT, por tenant — para diagnóstico.
     *
     * A fonte é `agt_submissions` (uma linha por tentativa de comunicação de um
     * documento): tem o `error_code`, o `error_message` e a resposta crua da
     * AGT. É aqui que se vê o "porquê" de uma rejeição — foi assim que se
     * apanhou o E70.
     *
     * Segue a filosofia dos erros_do_sistema: agrupa por problema (`por_erro`),
     * para que "169 documentos com o mesmo código" seja UMA linha e não 169.
     * A lista detalhada vem ao lado, e `?documento=<id>` devolve a resposta
     * crua da AGT de um só documento, para o diagnóstico fino.
     *
     * Sem `tenant_id` varre a plataforma inteira (o agente é o dono); com ele,
     * fica só numa empresa. `AGTSubmission` não tem global scope de tenant.
     */
    public function agtFalhas(Request $request)
    {
        $d = $request->validate([
            'tenant_id' => 'nullable|integer',
            'tipo'      => 'nullable|string|max:6',   // FT, FR, NC, ND, RC…
            'estado'    => 'nullable|in:falhas,rejeitadas,por_confirmar,todas',
            'desde'     => 'nullable|date',
            'limite'    => 'nullable|integer|min:1|max:200',
            'documento' => 'nullable|integer',        // deep-dive: 1 submissão crua
        ]);

        // Deep-dive: uma submissão por inteiro, com a resposta crua da AGT.
        if (!empty($d['documento'])) {
            $s = AGTSubmission::find($d['documento']);
            if (!$s) {
                return response()->json(['erro' => 'nao_encontrado'], 404);
            }

            return response()->json(['documento' => [
                'id'            => $s->id,
                'tenant_id'     => $s->tenant_id,
                'numero'        => $s->document_number,
                'tipo'          => $s->document_type_code,
                'estado'        => $s->status,
                'error_code'    => $s->error_code,
                'error_message' => $s->error_message,
                'tentativas'    => (int) $s->retry_count,
                'submetido_em'  => $s->submitted_at,
                'rejeitado_em'  => $s->rejected_at,
                'agt_reference' => $s->agt_reference,
                'resposta_agt'  => $s->response_payload,   // crua, para diagnóstico fino
            ]]);
        }

        $estado = $d['estado'] ?? 'falhas';

        // Filtros comuns (tenant/tipo/janela) — clonados a cada agregação.
        $base = AGTSubmission::query()
            ->when(isset($d['tenant_id']), fn ($q) => $q->where('tenant_id', $d['tenant_id']))
            ->when(!empty($d['tipo']), fn ($q) => $q->where('document_type_code', strtoupper($d['tipo'])))
            ->when(!empty($d['desde']), fn ($q) => $q->where('created_at', '>=', $d['desde']));

        // O que conta como "o que interessa ver" segundo o estado pedido.
        $aplicarEstado = fn ($q) => match ($estado) {
            'rejeitadas'    => $q->where('status', AGTSubmission::STATUS_REJECTED),
            'por_confirmar' => $q->where('status', AGTSubmission::STATUS_SUBMITTED),
            'todas'         => $q,
            // 'falhas' (padrão): rejeitadas + pendentes que já registaram erro
            // (ex.: COMMS — a comunicação nem chegou a ir).
            default => $q->where(fn ($w) => $w->where('status', AGTSubmission::STATUS_REJECTED)
                ->orWhere(fn ($e) => $e->where('status', AGTSubmission::STATUS_PENDING)->whereNotNull('error_code'))),
        };

        $resumo = [
            'rejeitadas'        => (clone $base)->where('status', AGTSubmission::STATUS_REJECTED)->count(),
            'por_confirmar'     => (clone $base)->where('status', AGTSubmission::STATUS_SUBMITTED)->count(),
            'validadas'         => (clone $base)->where('status', AGTSubmission::STATUS_VALIDATED)->count(),
            'tenants_afectados' => (clone $base)->where('status', AGTSubmission::STATUS_REJECTED)
                ->distinct()->count('tenant_id'),
        ];

        // Agrupado por código de erro — "um problema, N documentos".
        $porErro = $aplicarEstado(clone $base)
            ->selectRaw('COALESCE(error_code, "sem_codigo") code,
                COUNT(*) documentos, COUNT(DISTINCT tenant_id) tenants, MAX(error_message) exemplo')
            ->groupBy('code')->orderByDesc('documentos')->get()
            ->map(fn ($r) => [
                'error_code' => $r->code,
                'documentos' => (int) $r->documentos,
                'tenants'    => (int) $r->tenants,
                'exemplo'    => $r->exemplo,
            ]);

        $documentos = $aplicarEstado(clone $base)->orderByDesc('id')
            ->limit((int) ($d['limite'] ?? 100))
            ->get(['id', 'tenant_id', 'document_number', 'document_type_code', 'status',
                'error_code', 'error_message', 'retry_count', 'submitted_at', 'rejected_at', 'agt_reference'])
            ->map(fn ($s) => [
                'id'            => $s->id,
                'tenant_id'     => $s->tenant_id,
                'numero'        => $s->document_number,
                'tipo'          => $s->document_type_code,
                'estado'        => $s->status,
                'error_code'    => $s->error_code,
                'error_message' => $s->error_message,
                'tentativas'    => (int) $s->retry_count,
                'submetido_em'  => $s->submitted_at,
                'rejeitado_em'  => $s->rejected_at,
                'agt_reference' => $s->agt_reference,
            ]);

        return response()->json([
            'estado_filtrado' => $estado,
            'resumo'          => $resumo,
            'por_erro'        => $porErro,
            'documentos'      => $documentos,
            'dica'            => 'Resposta crua da AGT de um documento: ?documento=<id>.',
        ]);
    }

    public function pedidosDoAgente(Request $request)
    {
        $limite = max(1, min((int) $request->integer('limite', 100), 200));
        return response()->json(['pedidos' => AgentRequest::with('token:id,name,prefix')->orderByDesc('id')
            ->limit($limite)->get(['id', 'agent_token_id', 'idempotency_key', 'rota', 'http_status', 'created_at'])]);
    }

    public function estadoDoSistema()
    {
        $db = true;
        try { DB::select('SELECT 1'); } catch (\Throwable) { $db = false; }
        return response()->json([
            'aplicacao' => ['ambiente' => app()->environment(), 'laravel' => app()->version(), 'manutencao' => app()->isDownForMaintenance()],
            'servicos' => ['base_de_dados' => $db, 'cache' => $this->cacheFunciona()],
            'filas' => [
                'falhados' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
                'pendentes' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
            ],
            'erros_abertos' => ErroDoSistema::abertos()->count(), 'hora' => now()->toIso8601String(),
        ]);
    }

    public function executarAccao(Request $request)
    {
        $d = $request->validate([
            'accao' => 'required|in:cache_limpar,optimize_limpar,filas_repetir_falhadas',
            'confirmacao' => 'required|in:CONFIRMO', 'motivo' => 'required|string|min:10|max:500',
        ]);
        [$comando, $args] = match ($d['accao']) {
            'cache_limpar' => ['cache:clear', []], 'optimize_limpar' => ['optimize:clear', []],
            'filas_repetir_falhadas' => ['queue:retry', ['id' => ['all']]],
        };
        $codigo = Artisan::call($comando, $args);
        $agente = request()->attributes->get('agente');
        Log::warning('OpenClaw executou acção operacional permitida', [
            'agente' => $agente?->nome(), 'accao' => $d['accao'], 'motivo' => $d['motivo'], 'codigo' => $codigo,
        ]);
        return response()->json(['accao' => $d['accao'], 'codigo' => $codigo, 'sucesso' => $codigo === 0, 'saida' => mb_substr(Artisan::output(), 0, 2000)]);
    }

    public function recomendacoes()
    {
        $empresas = Tenant::all();
        $sinais = SinaisDeVida::para($empresas);
        $r = [];
        foreach ($empresas as $t) {
            $s = $sinais[$t->id] ?? null;
            if (!$s) continue;
            if (in_array($s->estado['chave'] ?? null, ['adormecida', 'inactiva'], true)) {
                $r[] = ['prioridade' => 'alta', 'tenant_id' => $t->id, 'empresa' => $t->name, 'tipo' => 'retencao', 'sugestao' => 'Contactar a empresa e identificar o bloqueio de adopção.', 'evidencia' => (array) $s];
            } elseif (($s->entraram_30d ?? 0) > 0 && ($s->facturas_30d ?? 0) === 0) {
                $r[] = ['prioridade' => 'media', 'tenant_id' => $t->id, 'empresa' => $t->name, 'tipo' => 'onboarding', 'sugestao' => 'Há entradas sem facturação; sugerir configuração assistida.', 'evidencia' => (array) $s];
            }
        }
        if (ErroDoSistema::abertos()->whereIn('nivel', ['critical', 'alert', 'emergency'])->exists()) {
            array_unshift($r, ['prioridade' => 'critica', 'tipo' => 'estabilidade', 'sugestao' => 'Resolver os erros críticos antes de novas alterações funcionais.']);
        }
        return response()->json(['geradas_em' => now()->toIso8601String(), 'recomendacoes' => array_slice($r, 0, 100), 'nota' => 'Sugestões determinísticas; não executam alterações.']);
    }

    private function resumoUtilizadores($desde): array
    {
        return [
            'total' => User::count(), 'activos' => User::where('is_active', true)->count(),
            'novos' => User::where('created_at', '>=', $desde)->count(),
            'entraram' => User::where('last_login_at', '>=', $desde)->count(),
            'online_15m' => Schema::hasTable('sessions') ? DB::table('sessions')->where('last_activity', '>=', now()->subMinutes(15)->timestamp)->whereNotNull('user_id')->distinct()->count('user_id') : null,
        ];
    }

    private function cacheFunciona(): bool
    {
        try { $k = 'agent_health_' . uniqid(); Cache::put($k, 1, 5); $ok = Cache::get($k) === 1; Cache::forget($k); return $ok; }
        catch (\Throwable) { return false; }
    }
}
