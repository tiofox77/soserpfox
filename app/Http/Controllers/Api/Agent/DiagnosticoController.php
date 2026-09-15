<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\AGT\AGTSubmission;
use App\Models\AuditTrail;
use App\Models\ErroDoSistema;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AGT\GestaoAgt;
use App\Services\Plataforma\DiagnosticoDaEmpresa;
use App\Support\AgenteAutenticado;
use App\Support\Deploy\Pacote;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * DIAGNÓSTICO E ACESSOS PARA O AGENTE — tudo só de leitura.
 *
 * Pedido de 2026-09-15: o agente precisava de responder, sem ninguém abrir a
 * consola do servidor, a perguntas que até aqui só se respondiam com uma rota
 * de manutenção e um humano a ler texto:
 *
 *  · «esta empresa ficou completa?» — o `empresa:diagnostico`, em JSON;
 *  · «há submissões à AGT paradas?» — por ambiente, as antigas por confirmar;
 *  · «o que está a correr em produção?» — versão do último pacote, migrações
 *    por correr, relógios (o MySQL de produção está uma hora à frente do PHP),
 *    OPcache, disco, log, filas;
 *  · «quem está dentro agora, e onde?» — sessões e a última página vista;
 *  · «quem entra nesta empresa?» — o último acesso de cada pessoa NESTA empresa
 *    (tenant_user.ultimo_acesso_em), entradas e falhas;
 *  · «alguém anda a martelar a porta?» — falhas agrupadas por email e por IP;
 *  · «quem entrou em nome de quem?» — as personificações do super admin.
 *
 * NADA DAQUI ESCREVE. Os escopos são os que já existiam (health:read,
 * logs:read, system:read), e `GET catalogo` diz ao agente, rota a rota, o que
 * esta credencial pode chamar.
 */
class DiagnosticoController extends Controller
{
    /** O que faz cada rota, para o catálogo. O que não está aqui aparece sem descrição. */
    private const DESCRICOES = [
        'GET me' => 'Identidade da credencial, escopos e o gasto do dia',
        'GET catalogo' => 'Esta lista: todas as rotas, o escopo que exigem e se esta credencial o tem',
        'GET tenants' => 'Empresas com filtros, estado da subscrição e sinais de utilização',
        'GET tenants/{tenant}' => 'Uma empresa: subscrição, NIF, catálogo, facturas, acessos, envios e sinais de vida',
        'GET tenants/{tenant}/diagnostico' => 'Ficou completa? Dono, papéis, subscrição, módulos, permissões, impostos, armazém, séries, erros — e o que falta',
        'GET tenants/{tenant}/acessos' => 'Quem entra nesta empresa: último acesso de cada pessoa aqui, entradas e falhas em 30 dias, IP, online',
        'GET tenants/{tenant}/eliminacao' => 'O que se perde ao apagar a empresa (não apaga)',
        'GET tenants/{tenant}/contacts' => 'Contactos reais e logins dos utilizadores da empresa',
        'GET health/checks' => 'Verificações de saúde disponíveis',
        'GET health/inconsistencias' => 'Inconsistências encontradas (subscrições, pedidos, NIF, AGT, stock)',
        'GET health/agt' => 'Submissões à AGT por empresa e ambiente; paradas por serem de outro ambiente, por confirmar há mais de 24h, recusadas em 7 dias',
        'GET system/status' => 'Estado técnico curto: base, cache, filas, erros abertos',
        'GET system/diagnostico' => 'Estado técnico completo: versão do deploy, migrações por correr, relógios, OPcache, disco, log, sessões',
        'GET status/resumo' => 'O resumo de hora a hora: precisa de atenção e porquê',
        'GET logs/errors' => 'Erros agrupados por problema (filtro por empresa, nível, estado)',
        'GET logs/audit' => 'Trilha de auditoria (filtros: empresa, evento, actor, personificado)',
        'GET logs/acessos' => 'Entradas, saídas e falhas de entrada, evento a evento',
        'GET logs/acessos/resumo' => 'Entradas e falhas agrupadas: por dia, por email, por IP e os suspeitos de força bruta',
        'GET logs/acessos/online' => 'Quem está dentro do sistema agora, em que empresa e em que página',
        'GET logs/personificacoes' => 'Quando o super admin entrou numa empresa em nome de alguém, e quando saiu',
        'GET logs/agt-falhas' => 'Documentos que falharam na AGT agrupados por código; ?documento= dá a resposta crua',
        'GET logs/agent' => 'Os pedidos feitos por esta API',
        'GET analytics/overview' => 'Visão geral: empresas por estado, utilizadores, erros, planos',
        'GET analytics/users' => 'Utilizadores com filtros, online e empresas de cada um',
        'GET analytics/recommendations' => 'Sugestões determinísticas de retenção e arranque',
        'GET analytics/site' => 'Analytics do site público: visitantes, páginas, origens, países, aparelhos, funil, ao vivo',
        'GET analytics/site/visitantes/{visitante}' => 'O percurso de um visitante do site',
        'GET analytics/uso' => 'Utilização do ERP: por empresa, pessoas, ecrãs mais usados',
        'GET reports/plataforma' => 'Relatório da plataforma: empresas novas, receita por mês, subscrições, MRR, testes a acabar, estados',
        'GET reports/documentos' => 'Documentos de venda emitidos em toda a plataforma, por dia e por empresa',
        'GET reports/tenants/{tenant}/vendas' => 'Vendas de uma empresa por mês e tipo de documento, estado AGT e formas de pagamento',
        'GET billing/ciclo' => 'Períodos a acabar e facturas da plataforma por pagar',
        'GET support/tickets' => 'Pedidos de suporte',
        'GET support/feedback' => 'Sugestões e mensagens de contacto',
    ];

    private function agente(): AgenteAutenticado
    {
        return app(AgenteAutenticado::class);
    }

    /* ══════════════ Catálogo ══════════════ */

    /**
     * O que esta API tem e o que esta credencial pode chamar.
     *
     * Lido do próprio router: uma rota nova aparece aqui sem ninguém se
     * lembrar de actualizar documentação.
     */
    public function catalogo()
    {
        $agente = $this->agente();

        $rotas = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $r) => str_starts_with($r->uri(), 'api/agent/v1/'))
            ->flatMap(function (Route $r) use ($agente) {
                $escopo = collect($r->gatherMiddleware())
                    ->first(fn ($m) => is_string($m) && str_starts_with($m, 'agent.scope:'));
                $escopo = $escopo ? substr($escopo, strlen('agent.scope:')) : null;
                $caminho = substr($r->uri(), strlen('api/agent/v1/'));

                return collect($r->methods())->reject(fn ($m) => $m === 'HEAD')->map(fn ($metodo) => [
                    'metodo' => $metodo,
                    'caminho' => $caminho,
                    'escopo' => $escopo,
                    'pode' => $escopo === null || $agente->pode($escopo),
                    'escrita' => $metodo !== 'GET',
                    'descricao' => self::DESCRICOES["{$metodo} {$caminho}"] ?? null,
                ]);
            })
            ->sortBy(fn ($r) => [$r['escrita'], $r['caminho']])
            ->values();

        return response()->json([
            'base' => '/api/agent/v1',
            'escopos_desta_credencial' => $agente->escopos(),
            'escopos_existentes' => config('agent.escopos'),
            'escrita' => 'Toda a escrita exige o cabeçalho Idempotency-Key.',
            'rotas' => $rotas,
        ]);
    }

    /* ══════════════ Diagnóstico ══════════════ */

    public function empresa(Tenant $tenant, DiagnosticoDaEmpresa $diagnostico)
    {
        return response()->json($diagnostico->para($tenant));
    }

    /**
     * A AGT por empresa e por ambiente.
     *
     * Uma submissão por concluir de OUTRO ambiente que não o activo fica à
     * espera para sempre (as anteriores a Julho foram preenchidas com
     * «sandbox»); uma «submitted» com mais de 24 horas é uma resposta que não
     * voltou. As duas coisas são invisíveis no ecrã da empresa.
     */
    public function agt(Request $request)
    {
        $d = $request->validate(['tenant_id' => 'nullable|integer']);

        $linhas = AGTSubmission::withoutGlobalScopes()
            ->from('agt_submissions as s')
            ->leftJoin('invoicing_settings as d', 'd.tenant_id', '=', 's.tenant_id')
            ->leftJoin('tenants as t', 't.id', '=', 's.tenant_id')
            ->when(isset($d['tenant_id']), fn ($q) => $q->where('s.tenant_id', $d['tenant_id']))
            ->select('s.tenant_id', 't.name as empresa', 'd.agt_environment as activo', 's.agt_environment as da_submissao', 's.status',
                DB::raw('COUNT(*) as n'), DB::raw('MIN(s.created_at) as primeira'), DB::raw('MAX(s.created_at) as ultima'))
            ->groupBy('s.tenant_id', 't.name', 'd.agt_environment', 's.agt_environment', 's.status')
            ->orderBy('s.tenant_id')
            ->get();

        $empresas = $linhas->groupBy('tenant_id')->map(function ($grupo) {
            $primeira = $grupo->first();
            $activo = GestaoAgt::normalizar($primeira->activo);

            return [
                'tenant_id' => (int) $primeira->tenant_id,
                'empresa' => $primeira->empresa,
                'ambiente_activo' => $activo,
                'submissoes' => $grupo->map(fn ($l) => [
                    'ambiente' => $l->da_submissao,
                    'estado' => $l->status,
                    'n' => (int) $l->n,
                    'primeira' => $l->primeira,
                    'ultima' => $l->ultima,
                    'parada_por_ser_de_outro_ambiente' => in_array($l->status, ['pending', 'submitted'], true)
                        && GestaoAgt::normalizar($l->da_submissao) !== $activo,
                ])->values(),
            ];
        })->values();

        $base = AGTSubmission::withoutGlobalScopes()
            ->when(isset($d['tenant_id']), fn ($q) => $q->where('tenant_id', $d['tenant_id']));

        return response()->json([
            'resumo' => [
                'paradas_de_outro_ambiente' => $empresas->sum(fn ($e) => collect($e['submissoes'])->where('parada_por_ser_de_outro_ambiente', true)->sum('n')),
                'por_confirmar_ha_mais_de_24h' => (clone $base)->where('status', AGTSubmission::STATUS_SUBMITTED)->where('created_at', '<', now()->subDay())->count(),
                'pendentes' => (clone $base)->where('status', AGTSubmission::STATUS_PENDING)->count(),
                'recusadas_7d' => (clone $base)->where('status', AGTSubmission::STATUS_REJECTED)->where('created_at', '>=', now()->subDays(7))->count(),
                'validadas_7d' => (clone $base)->where('status', AGTSubmission::STATUS_VALIDATED)->where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'empresas' => $empresas,
            'nota' => 'Só leitura. Repor tentativas ou reenviar é sempre por ordem de um humano.',
        ]);
    }

    /**
     * O estado técnico por inteiro. Sem segredos: nem .env, nem chaves, nem
     * credenciais — só versões, contagens e tamanhos.
     */
    public function sistema()
    {
        $bd = DB::selectOne('SELECT NOW() AS agora, @@session.time_zone AS fuso, VERSION() AS versao');
        $agoraPhp = now();
        $agoraBd = \Carbon\Carbon::parse($bd->agora, config('app.timezone'));

        return response()->json([
            'aplicacao' => [
                'ambiente' => app()->environment(),
                'depuracao' => (bool) config('app.debug'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'mysql' => $bd->versao,
                'manutencao' => app()->isDownForMaintenance(),
                'fuso' => config('app.timezone'),
            ],
            'deploy' => $this->ultimoDeploy(),
            'migracoes_por_correr' => $this->migracoesPorCorrer(),
            'relogios' => [
                'php' => $agoraPhp->format('Y-m-d H:i:s'),
                'base_de_dados' => $bd->agora,
                'fuso_da_sessao_mysql' => $bd->fuso,
                'diferenca_minutos' => (int) round(($agoraBd->getTimestamp() - $agoraPhp->getTimestamp()) / 60),
                'nota' => 'As datas gravadas pelo PHP batem certo; NOW()/CURRENT_TIMESTAMP do SQL levam a diferença.',
            ],
            'opcache' => $this->opcache(),
            'disco' => $this->disco(),
            'log' => $this->log(),
            'sessoes' => Schema::hasTable('sessions') ? [
                'ultimos_15_min' => DB::table('sessions')->where('last_activity', '>=', now()->subMinutes(15)->timestamp)->whereNotNull('user_id')->distinct()->count('user_id'),
                'total' => DB::table('sessions')->count(),
            ] : null,
            'filas' => [
                'falhados' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
                'pendentes' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
                'ultimo_falhado' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->max('failed_at') : null,
            ],
            'servicos' => ['cache' => $this->cacheFunciona()],
            'erros' => [
                'abertos' => ErroDoSistema::abertos()->count(),
                'ultimas_24h' => ErroDoSistema::where('ultima_vez', '>=', now()->subDay())->count(),
                'do_browser_abertos' => ErroDoSistema::abertos()->where('mensagem', 'like', 'Browser [%')->count(),
            ],
        ]);
    }

    /* ══════════════ Acessos ══════════════ */

    /**
     * Quem está dentro agora.
     *
     * A sessão diz QUEM e de onde (IP, browser); o último evento de analytics
     * diz EM QUE EMPRESA e EM QUE PÁGINA — a sessão guarda a empresa activa
     * cifrada no payload, que não se lê daqui.
     */
    public function online(Request $request)
    {
        $minutos = max(1, min((int) $request->integer('minutos', 15), 240));

        if (! Schema::hasTable('sessions')) {
            return response()->json(['minutos' => $minutos, 'pessoas' => [], 'nota' => 'Sessões não estão na base de dados.']);
        }

        $sessoes = DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes($minutos)->timestamp)
            ->whereNotNull('user_id')
            ->orderByDesc('last_activity')
            ->get(['user_id', 'ip_address', 'user_agent', 'last_activity'])
            ->unique('user_id');

        $pessoas = User::withTrashed()->whereIn('id', $sessoes->pluck('user_id'))->get(['id', 'name', 'email', 'tenant_id', 'is_super_admin'])->keyBy('id');

        $ultimos = DB::table('analytics_events')
            ->whereIn('user_id', $sessoes->pluck('user_id'))
            ->where('created_at', '>=', now()->subMinutes($minutos))
            ->select('user_id', DB::raw('MAX(id) as ultimo'))
            ->groupBy('user_id')->pluck('ultimo', 'user_id');
        $eventos = DB::table('analytics_events')->whereIn('id', $ultimos->values())->get(['id', 'user_id', 'tenant_id', 'path', 'created_at'])->keyBy('user_id');
        $empresas = Tenant::withTrashed()->whereIn('id', $eventos->pluck('tenant_id')->merge($pessoas->pluck('tenant_id'))->filter()->unique())->pluck('name', 'id');

        return response()->json([
            'minutos' => $minutos,
            'total' => $sessoes->count(),
            'pessoas' => $sessoes->map(function ($s) use ($pessoas, $eventos, $empresas) {
                $u = $pessoas[$s->user_id] ?? null;
                $e = $eventos[$s->user_id] ?? null;
                $empresa = $e?->tenant_id ?? $u?->tenant_id;

                return [
                    'user_id' => (int) $s->user_id,
                    'nome' => $u?->name,
                    'email' => $u?->email,
                    'super_admin' => (bool) ($u?->is_super_admin),
                    'ultima_actividade' => date('c', (int) $s->last_activity),
                    'ip' => $s->ip_address,
                    'browser' => mb_strimwidth((string) $s->user_agent, 0, 160, '…'),
                    'tenant_id' => $empresa ? (int) $empresa : null,
                    'empresa' => $empresa ? ($empresas[$empresa] ?? null) : null,
                    'pagina' => $e?->path,
                    'pagina_vista_em' => $e?->created_at,
                ];
            })->values(),
        ]);
    }

    /** Quem entra NESTA empresa, pessoa a pessoa. */
    public function acessosDaEmpresa(Tenant $tenant)
    {
        $desde = now()->subDays(30);
        $temAcesso = Schema::hasColumn('tenant_user', 'ultimo_acesso_em');

        $membros = DB::table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->leftJoin('roles', 'roles.id', '=', 'tenant_user.role_id')
            ->where('tenant_user.tenant_id', $tenant->id)
            ->get(array_merge([
                'users.id', 'users.name', 'users.email', 'users.is_active', 'users.last_login_at', 'users.deleted_at',
                'tenant_user.is_active as activo_na_empresa', 'tenant_user.joined_at', 'roles.name as papel',
            ], $temAcesso ? ['tenant_user.ultimo_acesso_em'] : []));

        $ids = $membros->pluck('id');
        $quantas = DB::table('tenant_user')->whereIn('user_id', $ids)->select('user_id', DB::raw('COUNT(*) AS n'))->groupBy('user_id')->pluck('n', 'user_id');
        $logins = AuditTrail::query()->where('event', 'login')->whereIn('user_id', $ids)->where('created_at', '>=', $desde)
            ->select('user_id', DB::raw('COUNT(*) AS n'), DB::raw('MAX(id) AS ultimo'))->groupBy('user_id')->get()->keyBy('user_id');
        $ips = AuditTrail::query()->whereIn('id', $logins->pluck('ultimo'))->pluck('ip_address', 'user_id');
        $emails = $membros->pluck('email')->filter()->map(fn ($e) => mb_strtolower($e));
        $falhas = AuditTrail::query()->where('event', 'login_falhado')->where('created_at', '>=', $desde)
            ->get(['metadata'])
            ->map(fn ($a) => mb_strtolower((string) (($a->metadata ?? [])['email'] ?? '')))
            ->filter(fn ($e) => $emails->contains($e))
            ->countBy();
        $online = Schema::hasTable('sessions')
            ? DB::table('sessions')->whereIn('user_id', $ids)->where('last_activity', '>=', now()->subMinutes(15)->timestamp)->pluck('user_id')->flip()
            : collect();

        $pessoas = $membros->map(function ($m) use ($quantas, $logins, $ips, $falhas, $online) {
            $varias = (int) ($quantas[$m->id] ?? 1) > 1;
            $aqui = $m->ultimo_acesso_em ?? (! $varias ? $m->last_login_at : null);

            return [
                'user_id' => (int) $m->id,
                'nome' => $m->name,
                'email' => $m->email,
                'papel' => $m->papel,
                'conta_activa' => (bool) $m->is_active && $m->deleted_at === null,
                'activo_na_empresa' => (bool) $m->activo_na_empresa,
                'entrou_na_empresa_em' => $m->joined_at,
                'ultimo_acesso_nesta_empresa' => $aqui,
                'ultima_entrada_no_sistema' => $m->last_login_at,
                'empresas_a_que_pertence' => (int) ($quantas[$m->id] ?? 1),
                'entradas_30d' => (int) ($logins[$m->id]->n ?? 0),
                'falhas_de_entrada_30d' => (int) ($falhas[mb_strtolower((string) $m->email)] ?? 0),
                'ultimo_ip' => $ips[$m->id] ?? null,
                'online_15m' => $online->has($m->id),
            ];
        })->sortByDesc(fn ($p) => $p['ultimo_acesso_nesta_empresa'] ?? '')->values();

        return response()->json([
            'empresa' => ['id' => $tenant->id, 'nome' => $tenant->name],
            'resumo' => [
                'pessoas' => $pessoas->count(),
                'entraram_30d' => $pessoas->filter(fn ($p) => $p['ultimo_acesso_nesta_empresa'] && $p['ultimo_acesso_nesta_empresa'] >= $desde->toDateTimeString())->count(),
                'online_15m' => $pessoas->where('online_15m', true)->count(),
                'falhas_de_entrada_30d' => $pessoas->sum('falhas_de_entrada_30d'),
                'ultimo_acesso' => $pessoas->pluck('ultimo_acesso_nesta_empresa')->filter()->max(),
            ],
            'pessoas' => $pessoas,
            'nota' => 'O último acesso por empresa só é gravado desde 2026-09-14; antes disso, quem tem várias empresas aparece sem acesso aqui.',
        ]);
    }

    /**
     * Entradas e falhas agrupadas: é assim que se vê a força bruta.
     *
     * Um evento a evento com duzentas falhas não se lê; «23 falhas do mesmo IP
     * em 10 minutos» sim.
     */
    public function resumoDeAcessos(Request $request)
    {
        $d = $request->validate([
            'horas' => 'nullable|integer|min:1|max:720',
            'tenant_id' => 'nullable|integer',
        ]);
        $horas = (int) ($d['horas'] ?? 24);
        $desde = now()->subHours($horas);

        $base = fn (string $evento) => AuditTrail::query()->where('event', $evento)->where('created_at', '>=', $desde)
            ->when(isset($d['tenant_id']), fn ($q) => $q->where('tenant_id', $d['tenant_id']));

        $falhas = $base('login_falhado')->get(['ip_address', 'metadata', 'created_at']);

        $porEmail = $falhas->groupBy(fn ($f) => mb_strtolower((string) (($f->metadata ?? [])['email'] ?? '(sem email)')))
            ->map(fn ($g, $email) => [
                'email' => $email, 'falhas' => $g->count(), 'ips' => $g->pluck('ip_address')->unique()->values(),
                'primeira' => $g->min('created_at')?->toIso8601String(), 'ultima' => $g->max('created_at')?->toIso8601String(),
            ])->sortByDesc('falhas')->values();

        $porIp = $falhas->groupBy('ip_address')
            ->map(fn ($g, $ip) => [
                'ip' => $ip, 'falhas' => $g->count(),
                'emails' => $g->map(fn ($f) => ($f->metadata ?? [])['email'] ?? null)->filter()->unique()->values(),
                'ultima' => $g->max('created_at')?->toIso8601String(),
            ])->sortByDesc('falhas')->values();

        $porDia = AuditTrail::query()->whereIn('event', ['login', 'login_falhado'])
            ->where('created_at', '>=', now()->subDays(14)->startOfDay())
            ->when(isset($d['tenant_id']), fn ($q) => $q->where('tenant_id', $d['tenant_id']))
            ->select(DB::raw('DATE(created_at) AS dia'), 'event', DB::raw('COUNT(*) AS n'))
            ->groupBy('dia', 'event')->orderBy('dia')->get()
            ->groupBy('dia')->map(fn ($g, $dia) => [
                'dia' => $dia,
                'entradas' => (int) ($g->firstWhere('event', 'login')->n ?? 0),
                'falhas' => (int) ($g->firstWhere('event', 'login_falhado')->n ?? 0),
            ])->values();

        return response()->json([
            'janela_horas' => $horas,
            'totais' => [
                'entradas' => $base('login')->count(),
                'saidas' => $base('logout')->count(),
                'falhas' => $falhas->count(),
                'pessoas_que_entraram' => $base('login')->distinct()->count('user_id'),
                'personificacoes' => $base('personificacao.entrou')->count(),
            ],
            // Cinco falhas é o tecto do throttle de login: a partir daí não é
            // alguém a errar a senha, é alguém a tentar.
            'suspeitos' => [
                'emails' => $porEmail->where('falhas', '>=', 5)->values(),
                'ips' => $porIp->where('falhas', '>=', 5)->values(),
            ],
            'falhas_por_email' => $porEmail->take(20)->values(),
            'falhas_por_ip' => $porIp->take(20)->values(),
            'por_dia_14d' => $porDia,
        ]);
    }

    /** Quem entrou numa empresa em nome de quem, e quando saiu. */
    public function personificacoes(Request $request)
    {
        $d = $request->validate([
            'tenant_id' => 'nullable|integer',
            'desde' => 'nullable|date',
            'limite' => 'nullable|integer|min:1|max:200',
        ]);

        $eventos = AuditTrail::query()->where('event', 'like', 'personificacao.%')
            ->when(isset($d['tenant_id']), fn ($q) => $q->where('tenant_id', $d['tenant_id']))
            ->when(! empty($d['desde']), fn ($q) => $q->where('created_at', '>=', $d['desde']))
            ->orderByDesc('id')->limit((int) ($d['limite'] ?? 100))
            ->get(['id', 'tenant_id', 'user_id', 'impersonator_id', 'event', 'actor_name', 'metadata', 'ip_address', 'created_at']);

        $nomes = User::withTrashed()->whereIn('id', $eventos->pluck('user_id')->merge($eventos->pluck('impersonator_id'))->filter()->unique())->pluck('name', 'id');
        $empresas = Tenant::withTrashed()->whereIn('id', $eventos->pluck('tenant_id')->filter()->unique())->pluck('name', 'id');

        return response()->json([
            'personificacoes' => $eventos->map(fn ($e) => [
                'id' => $e->id,
                'evento' => $e->event,
                'quando' => $e->created_at?->toIso8601String(),
                'tenant_id' => $e->tenant_id,
                'empresa' => $empresas[$e->tenant_id] ?? null,
                'em_nome_de' => ['id' => $e->user_id, 'nome' => $nomes[$e->user_id] ?? $e->actor_name],
                'admin' => ['id' => $e->impersonator_id, 'nome' => $nomes[$e->impersonator_id] ?? null],
                'ip' => $e->ip_address,
                'detalhe' => $e->metadata,
            ])->values(),
        ]);
    }

    /* ══════════════ Peças ══════════════ */

    /** O último pacote aplicado, lido dos instantâneos do deploy. */
    private function ultimoDeploy(): ?array
    {
        $pastas = glob(storage_path('app/deploy/instantaneos') . '/*', GLOB_ONLYDIR) ?: [];
        rsort($pastas);

        foreach ($pastas as $pasta) {
            try {
                $m = Pacote::manifesto($pasta);
            } catch (Throwable) {
                continue;
            }

            return [
                'pacote' => $m['pacote'] ?? null,
                'instantaneo' => basename($pasta),
                'criado_em' => $m['criado_em'] ?? null,
                'aplicado_em' => $m['aplicado_em'] ?? null,
                'restaurado_em' => $m['restaurado_em'] ?? null,
                'com_copia_da_base' => is_file($pasta . '/bd.sql.gz'),
                'instantaneos_guardados' => count($pastas),
            ];
        }

        return null;
    }

    private function migracoesPorCorrer(): array
    {
        try {
            $migrador = app('migrator');
            $ficheiros = array_keys($migrador->getMigrationFiles([database_path('migrations')]));
            $corridas = $migrador->getRepository()->getRan();
            $faltam = array_values(array_diff($ficheiros, $corridas));

            return ['total' => count($faltam), 'nomes' => array_slice($faltam, 0, 30)];
        } catch (Throwable $e) {
            return ['total' => null, 'erro' => $e->getMessage()];
        }
    }

    private function opcache(): ?array
    {
        if (! function_exists('opcache_get_status')) {
            return null;
        }

        $s = @opcache_get_status(false);
        if (! is_array($s)) {
            return ['ligado' => false];
        }

        return [
            'ligado' => (bool) ($s['opcache_enabled'] ?? false),
            'scripts_em_cache' => $s['opcache_statistics']['num_cached_scripts'] ?? null,
            'memoria_usada_mb' => isset($s['memory_usage']['used_memory']) ? round($s['memory_usage']['used_memory'] / 1048576, 1) : null,
            'memoria_livre_mb' => isset($s['memory_usage']['free_memory']) ? round($s['memory_usage']['free_memory'] / 1048576, 1) : null,
            'taxa_de_acerto' => isset($s['opcache_statistics']['opcache_hit_rate']) ? round($s['opcache_statistics']['opcache_hit_rate'], 2) : null,
            'reiniciado_em' => isset($s['opcache_statistics']['last_restart_time']) && $s['opcache_statistics']['last_restart_time']
                ? date('c', $s['opcache_statistics']['last_restart_time']) : null,
        ];
    }

    private function disco(): array
    {
        $pasta = storage_path();

        return [
            'livre_gb' => ($l = @disk_free_space($pasta)) !== false ? round($l / 1073741824, 2) : null,
            'total_gb' => ($t = @disk_total_space($pasta)) !== false ? round($t / 1073741824, 2) : null,
        ];
    }

    private function log(): array
    {
        $ficheiros = glob(storage_path('logs') . '/*.log') ?: [];

        return [
            'ficheiros' => count($ficheiros),
            'total_mb' => round(array_sum(array_map(fn ($f) => (int) @filesize($f), $ficheiros)) / 1048576, 2),
            'maior' => collect($ficheiros)->mapWithKeys(fn ($f) => [basename($f) => round(((int) @filesize($f)) / 1048576, 2)])->sortDesc()->take(3),
        ];
    }

    private function cacheFunciona(): bool
    {
        try {
            $k = 'agent_diag_' . uniqid();
            Cache::put($k, 1, 5);
            $ok = Cache::get($k) === 1;
            Cache::forget($k);

            return $ok;
        } catch (Throwable) {
            return false;
        }
    }
}
