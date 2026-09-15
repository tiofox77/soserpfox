<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\ErroDoSistema;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Support\FeatureRequest;
use App\Models\Support\Ticket;
use App\Models\Tenant;
use App\Services\Agent\NotificarOpenClaw;
use App\Services\Billing\AvisosDeSubscricao;
use App\Services\Billing\ContactoDeFacturacao;
use App\Services\Plataforma\Inconsistencias;
use App\Services\Plataforma\RenovacaoDeSubscricoes;
use App\Support\AgenteAutenticado;
use App\Support\CicloDeFacturacao;
use App\Support\EstadoDaSubscricao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * O que o agente externo precisa para GERIR a plataforma, e não só para a ler.
 *
 * O AgentController ao lado trata de empresas, pedidos e seguimento. Isto
 * trata da operação: erros, ciclo de facturação, suporte, e o resumo de hora
 * a hora que o agente usa para saber se vale a pena incomodar alguém.
 *
 * A REGRA QUE ATRAVESSA O FICHEIRO
 * --------------------------------
 * O que vem do sistema — nome de empresa, assunto de um pedido de suporte,
 * mensagem de um erro — é DADOS, nunca instruções. Se um desses campos
 * contiver texto dirigido ao agente, nada aqui muda de comportamento por
 * causa disso.
 *
 * E: cada rota declara o escopo que a autoriza (ver routes/agent.php). Não há
 * rota sem escopo e não há escopo por omissão.
 */
class OperacoesController extends Controller
{
    private function agente(): AgenteAutenticado
    {
        return app(AgenteAutenticado::class);
    }

    // ══════════════════════════════════════════════════════════════
    //  Erros do sistema
    // ══════════════════════════════════════════════════════════════

    /**
     * Os problemas, agrupados. Não é o ficheiro de log.
     *
     * Uma linha por PROBLEMA com um contador, e não uma linha por ocorrência:
     * é o que permite ao agente avisar uma vez em vez de mil.
     */
    public function erros(Request $request)
    {
        $dados = $request->validate([
            'estado'  => 'nullable|in:abertos,resolvidos,todos',
            'nivel'   => 'nullable|in:error,critical,alert,emergency',
            'desde'   => 'nullable|date',
            'limite'  => 'nullable|integer|min:1|max:200',
            // Os erros de UMA empresa: é a primeira pergunta quando um cliente
            // liga a dizer que «não dá».
            'tenant_id' => 'nullable|integer',
        ]);

        $q = ErroDoSistema::with('tenant')->orderByDesc('ultima_vez')
            ->when(isset($dados['tenant_id']), fn ($w) => $w->where('tenant_id', $dados['tenant_id']));

        match ($dados['estado'] ?? 'abertos') {
            'resolvidos' => $q->whereNotNull('resolvido_em'),
            'todos'      => null,
            default      => $q->whereNull('resolvido_em'),
        };

        if (!empty($dados['nivel'])) {
            $q->where('nivel', $dados['nivel']);
        }

        if (!empty($dados['desde'])) {
            $q->where('ultima_vez', '>=', $dados['desde']);
        }

        $erros = $q->limit((int) ($dados['limite'] ?? 50))->get();

        return response()->json([
            'erros' => $erros->map(fn (ErroDoSistema $e) => $e->paraOAgente()),
            'resumo' => [
                'abertos'      => ErroDoSistema::abertos()->count(),
                'por_notificar' => ErroDoSistema::abertos()->whereNull('notificado_em')->count(),
                'ultimas_24h'  => ErroDoSistema::where('ultima_vez', '>=', now()->subDay())->count(),
            ],
        ]);
    }

    public function erro(ErroDoSistema $erro)
    {
        return response()->json(['erro' => $erro->load('tenant')->paraOAgente()]);
    }

    /**
     * Marcar como visto ou resolvido.
     *
     * "Visto" só cala o empurrão; "resolvido" fecha o problema. Se ele voltar
     * a acontecer, o RegistoDeErros reabre-o sozinho — um erro dado por
     * resolvido que regressa não pode ficar calado para sempre.
     */
    public function fecharErro(Request $request, ErroDoSistema $erro)
    {
        $dados = $request->validate([
            'accao' => 'required|in:visto,resolvido,reabrir',
            'nota'  => 'nullable|string|max:1000',
        ]);

        match ($dados['accao']) {
            'visto' => $erro->forceFill([
                'notificado_em' => now(),
                'nota'          => $dados['nota'] ?? $erro->nota,
            ])->save(),

            'resolvido' => $erro->forceFill([
                'resolvido_em'  => now(),
                'resolvido_por' => 'agente:' . $this->agente()->nome(),
                'notificado_em' => $erro->notificado_em ?? now(),
                'nota'          => $dados['nota'] ?? $erro->nota,
            ])->save(),

            'reabrir' => $erro->reabrir(),
        };

        return response()->json(['erro' => $erro->refresh()->paraOAgente()]);
    }

    // ══════════════════════════════════════════════════════════════
    //  Contactos reais
    // ══════════════════════════════════════════════════════════════

    /**
     * O email e o telefone por mascarar de uma empresa.
     *
     * Existe porque o agente manda WhatsApp do lado dele, e para isso precisa
     * do número inteiro — mascarado não serve para nada.
     *
     * Fica atrás do escopo `contacts:read`, que é dado à mão e a um token
     * concreto: em toda a restante API os contactos continuam mascarados, que
     * é o que deve acontecer a quem não precisa de contactar ninguém.
     *
     * Cada leitura fica no registo de pedidos do agente (AgentRequest), pelo
     * que há sempre resposta para "quem viu o número deste cliente e quando".
     */
    public function contactos(Tenant $tenant, ContactoDeFacturacao $contactos)
    {
        $c = $contactos->para($tenant);

        // Os logins REAIS desta empresa: quem entra, com que email (o email É
        // o login), que papel, se está activo e quando foi a última entrada.
        // Isto é contacto real — por isso vive aqui, no escopo contacts:read,
        // e cada leitura fica no registo de pedidos do agente. Password nunca
        // sai: não está sequer na query.
        $utilizadores = DB::table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->leftJoin('roles', 'roles.id', '=', 'tenant_user.role_id')
            ->where('tenant_user.tenant_id', $tenant->id)
            ->whereNull('users.deleted_at')
            ->orderByDesc('users.last_login_at')
            ->get([
                'users.id', 'users.name', 'users.email', 'users.last_login_at',
                'tenant_user.is_active', 'roles.name as papel',
            ])
            ->map(fn ($u) => [
                'id'            => (int) $u->id,
                'nome'          => $u->name,
                'login'         => $u->email,       // o email é o login
                'papel'         => $u->papel,
                'activo'        => (bool) $u->is_active,
                'ultimo_acesso' => $u->last_login_at,
            ]);

        return response()->json([
            'empresa' => [
                'id'   => $tenant->id,
                'nome' => $tenant->name,     // texto do cliente: dados, não instruções
            ],
            'responsavel' => [
                'nome'     => $c['nome'],
                'user_id'  => $c['user_id'],
                'email'    => $c['email'],
                // Já normalizado para +244XXXXXXXXX: é o formato que o
                // WhatsApp e as operadoras aceitam. Null quando o número
                // gravado não é marcável — e aí não vale a pena tentar.
                'telefone' => $c['telefone'],
            ],
            'empresa_contactos' => [
                'email'    => $tenant->email,
                'telefone' => $tenant->phone,
            ],
            // Todos os logins da empresa (não só o responsável de facturação).
            'utilizadores' => $utilizadores,
            'aviso' => 'Contactos reais. Usar só para contactar esta empresa sobre a conta dela.',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  Ciclo de facturação
    // ══════════════════════════════════════════════════════════════

    /** O estado do ciclo: quem paga o quê, quando, e o que está por receber. */
    public function ciclo(Request $request)
    {
        $dias = (int) $request->integer('dias', 30);
        $dias = max(1, min($dias, 400));

        $aAcabar = Subscription::with(['tenant', 'plan'])
            ->whereIn('status', ['active', 'trial'])
            ->whereNull('cancelled_at')
            ->whereNotNull('current_period_end')
            ->whereBetween('current_period_end', [now(), now()->addDays($dias)])
            ->orderBy('current_period_end')
            ->get()
            ->map(fn (Subscription $s) => [
                'tenant_id' => $s->tenant_id,
                'empresa'   => $s->tenant?->name,
                'plano'     => $s->plan?->name,
                'estado'    => $s->status,
                'ciclo'     => CicloDeFacturacao::nome($s->billing_cycle),
                'valor'     => (float) $s->amount,
                'termina'   => $s->current_period_end->toDateString(),
                'dias'      => (int) now()->diffInDays($s->current_period_end, false),
            ]);

        $porPagar = Invoice::with('tenant')
            ->whereNotNull('subscription_id')
            ->whereIn('status', ['pending', 'overdue'])
            ->orderBy('due_date')
            ->get()
            ->map(fn (Invoice $f) => [
                'factura'    => $f->invoice_number,
                'tenant_id'  => $f->tenant_id,
                'empresa'    => $f->tenant?->name,
                'valor'      => (float) $f->total,
                'vencimento' => optional($f->due_date)->toDateString(),
                'vencida'    => $f->due_date && $f->due_date->isPast(),
                'dias'       => $f->due_date ? (int) now()->diffInDays($f->due_date, false) : null,
            ]);

        return response()->json([
            'periodos_a_acabar' => $aAcabar,
            'facturas_por_pagar' => $porPagar,
            'totais' => [
                'por_receber'       => round($porPagar->sum('valor'), 2),
                'vencido'           => round($porPagar->where('vencida', true)->sum('valor'), 2),
                'facturas_vencidas' => $porPagar->where('vencida', true)->count(),
            ],
            'automatismos' => [
                'renovacao_automatica' => (bool) config('billing.renovacao_automatica'),
                'avisos_ao_cliente'    => (bool) config('billing.avisos_ao_cliente'),
                'avisos_sms'           => (bool) config('billing.avisos_sms'),
            ],
        ]);
    }

    /**
     * Emitir as facturas de renovação em falta.
     *
     * `so_ver` é o valor por omissão, e de propósito: uma factura é um
     * documento que o cliente vê e sobre o qual lhe é pedido dinheiro. Para
     * emitir mesmo, o agente tem de o dizer explicitamente.
     */
    public function renovar(Request $request, RenovacaoDeSubscricoes $renovacao)
    {
        $dados = $request->validate([
            'so_ver' => 'nullable|boolean',
            'dias'   => 'nullable|integer|min:1|max:60',
        ]);

        $soVer = (bool) ($dados['so_ver'] ?? true);

        $r = $renovacao->emitirFacturasAVencer(
            (int) ($dados['dias'] ?? config('billing.dias_de_antecedencia', 8)),
            $soVer
        );

        return response()->json([
            'so_ver'    => $soVer,
            'emitidas'  => $r['emitidas'],
            'ignoradas' => $r['ignoradas'],
            'detalhe'   => $r['detalhe'],
        ]);
    }

    /**
     * Fazer sair os avisos de facturação ao cliente.
     *
     * Também aqui `so_ver` por omissão: do outro lado há caixas de correio e
     * telemóveis de pessoas reais, e o SMS é dinheiro.
     */
    public function avisar(Request $request, AvisosDeSubscricao $avisos)
    {
        $dados = $request->validate(['so_ver' => 'nullable|boolean']);
        $soVer = (bool) ($dados['so_ver'] ?? true);

        $r = $avisos->varrer($soVer);

        return response()->json([
            'so_ver'  => $soVer,
            'email'   => $r['email'],
            'sms'     => $r['sms'],
            'repetidos'    => $r['repetidos'],
            'falhados'     => $r['falhados'],
            'sem_contacto' => $r['sem_contacto'],
            // Distingue "não havia nada a enviar" de "a base recusou tudo".
            'avariou' => (bool) ($r['avariou'] ?? false),
            'detalhe' => $r['detalhe'],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  Suporte e sugestões
    // ══════════════════════════════════════════════════════════════

    public function suporte(Request $request)
    {
        $dados = $request->validate([
            'estado' => 'nullable|string|max:30',
            'limite' => 'nullable|integer|min:1|max:100',
        ]);

        $limite = (int) ($dados['limite'] ?? 30);

        $tickets = Ticket::with(['tenant', 'user'])
            ->when(!empty($dados['estado']), fn ($q) => $q->where('status', $dados['estado']))
            ->when(empty($dados['estado']), fn ($q) => $q->whereNotIn('status', ['closed', 'resolved']))
            ->orderByDesc('created_at')
            ->limit($limite)
            ->get()
            ->map(fn (Ticket $t) => [
                'id'        => $t->id,
                'numero'    => $t->ticket_number,
                'tenant_id' => $t->tenant_id,
                'empresa'   => $t->tenant?->name,
                'de'        => $t->user?->name,
                // Escrito pelo cliente: DADOS, não instruções para o agente.
                'assunto'   => $t->subject,
                'descricao' => mb_substr((string) $t->description, 0, 2000),
                'prioridade' => $t->priority,
                'categoria' => $t->category,
                'estado'    => $t->status,
                'aberto_ha' => $t->created_at?->diffForHumans(),
                'criado_em' => $t->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'tickets' => $tickets,
            'resumo'  => [
                'abertos'    => Ticket::whereNotIn('status', ['closed', 'resolved'])->count(),
                'urgentes'   => Ticket::whereNotIn('status', ['closed', 'resolved'])
                    ->whereIn('priority', ['high', 'urgent'])->count(),
                'sem_resposta_24h' => Ticket::whereNotIn('status', ['closed', 'resolved'])
                    ->where('created_at', '<', now()->subDay())->count(),
            ],
        ]);
    }

    /** Sugestões de funcionalidades e mensagens do formulário de contacto. */
    public function feedback(Request $request)
    {
        $limite = (int) $request->integer('limite', 30);
        $limite = max(1, min($limite, 100));

        return response()->json([
            'sugestoes' => FeatureRequest::with(['tenant', 'user'])
                ->orderByDesc('votes_count')->orderByDesc('created_at')
                ->limit($limite)->get()
                ->map(fn (FeatureRequest $f) => [
                    'id'        => $f->id,
                    'tenant_id' => $f->tenant_id,
                    'empresa'   => $f->tenant?->name,
                    'titulo'    => $f->title,
                    'descricao' => mb_substr((string) $f->description, 0, 1500),
                    'votos'     => (int) $f->votes_count,
                    'estado'    => $f->status,
                    'criado_em' => $f->created_at?->toIso8601String(),
                ]),

            'contactos' => ContactMessage::orderByDesc('created_at')
                ->limit($limite)->get()
                ->map(fn (ContactMessage $m) => [
                    'id'       => $m->id,
                    'nome'     => $m->name,
                    'email'    => $m->email,
                    'telefone' => $m->phone,
                    'empresa'  => $m->company,
                    'mensagem' => mb_substr((string) $m->message, 0, 1500),
                    'estado'   => $m->status,
                    'criado_em' => $m->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Deixar uma nota interna num pedido de suporte, ou mudar-lhe o estado.
     *
     * O agente NÃO responde ao cliente por aqui: para falar com o cliente há
     * o seguimento (followup), que tem allowlist de modelos, tectos, silêncio
     * nocturno e arrefecimento. Aqui é trabalho interno.
     */
    public function anotarTicket(Request $request, Ticket $ticket)
    {
        $dados = $request->validate([
            'nota'   => 'required|string|max:2000',
            'estado' => 'nullable|in:open,in_progress,waiting,resolved,closed',
        ]);

        // O prefixo [agente ...] fica na mensagem de propósito: quem ler o
        // fio tem de distinguir uma nota do agente de uma resposta humana.
        $ticket->messages()->create([
            'user_id'  => $this->agente()->responsavelId(),
            'message'  => '[agente ' . $this->agente()->nome() . '] ' . $dados['nota'],
            'is_staff' => true,
        ]);

        if (!empty($dados['estado'])) {
            $ticket->forceFill([
                'status'      => $dados['estado'],
                'resolved_at' => in_array($dados['estado'], ['resolved', 'closed'], true) ? now() : null,
            ])->save();
        }

        return response()->json([
            'ticket' => ['id' => $ticket->id, 'estado' => $ticket->refresh()->status],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  Empresas: suspender e reactivar
    // ══════════════════════════════════════════════════════════════

    /**
     * Suspender ou reactivar uma empresa.
     *
     * Suspender corta o acesso a toda a gente dessa empresa — é a acção mais
     * pesada que o agente pode fazer, e por isso exige motivo escrito e fica
     * registada com o nome do agente.
     *
     * NÃO apaga nada e NÃO mexe na subscrição: é reversível pela mesma rota.
     */
    public function estadoDaEmpresa(Request $request, Tenant $tenant)
    {
        $dados = $request->validate([
            'accao'  => 'required|in:suspender,reactivar',
            'motivo' => 'required|string|min:8|max:500',
        ]);

        $antes = (bool) $tenant->is_active;
        $tenant->forceFill(['is_active' => $dados['accao'] === 'reactivar'])->save();

        \Log::warning('Agente mudou o estado de uma empresa', [
            'agente'    => $this->agente()->nome(),
            'tenant_id' => $tenant->id,
            'de'        => $antes ? 'activa' : 'suspensa',
            'para'      => $tenant->is_active ? 'activa' : 'suspensa',
            'motivo'    => $dados['motivo'],
        ]);

        return response()->json([
            'empresa' => [
                'id'     => $tenant->id,
                'nome'   => $tenant->name,
                'activa' => (bool) $tenant->is_active,
            ],
            'mudou' => $antes !== (bool) $tenant->is_active,
        ]);
    }

    /** Compatibilidade explícita com a spec v2. */
    public function suspenderEmpresa(Request $request, Tenant $tenant)
    {
        return $this->mudarEstado($request, $tenant, false);
    }

    public function reactivarEmpresa(Request $request, Tenant $tenant)
    {
        return $this->mudarEstado($request, $tenant, true);
    }

    private function mudarEstado(Request $request, Tenant $tenant, bool $activo)
    {
        $dados = $request->validate(['motivo' => 'required|string|min:8|max:500']);
        $antes = (bool) $tenant->is_active;
        $tenant->forceFill(['is_active' => $activo])->save();

        \Log::warning('Agente mudou o estado de uma empresa (spec v2)', [
            'agente' => $this->agente()->nome(), 'tenant_id' => $tenant->id,
            'de' => $antes ? 'activa' : 'suspensa', 'para' => $activo ? 'activa' : 'suspensa',
            'motivo' => $dados['motivo'],
        ]);

        return response()->json([
            'empresa' => ['id' => $tenant->id, 'nome' => $tenant->name, 'activa' => $activo,
                'estado' => $activo ? 'Ativo' : 'Suspenso'],
            'mudou' => $antes !== $activo,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  O resumo de hora a hora
    // ══════════════════════════════════════════════════════════════

    /**
     * Tudo o que o agente precisa para decidir se vale a pena incomodar alguém.
     *
     * Uma chamada, não sete. É esta que o agente pede de hora a hora: se
     * `precisa_atencao` for falso, não há nada a dizer e ele fica calado — que
     * é metade do trabalho de quem vigia.
     */
    public function resumo(Request $request, Inconsistencias $inconsistencias)
    {
        $desde = now()->subHours((int) max(1, min($request->integer('horas', 1), 48)));

        $errosNovos = ErroDoSistema::abertos()
            ->where('ultima_vez', '>=', $desde)
            ->orderByDesc('ultima_vez')
            ->limit(10)->get();

        $facturasVencidas = Invoice::whereNotNull('subscription_id')
            ->whereIn('status', ['pending', 'overdue'])
            ->whereDate('due_date', '<', today())
            ->count();

        $periodosAAcabar = Subscription::whereIn('status', ['active', 'trial'])
            ->whereNull('cancelled_at')
            ->whereBetween('current_period_end', [now(), now()->addDays(7)])
            ->count();

        $ticketsAbertos = Ticket::whereNotIn('status', ['closed', 'resolved'])->count();
        $ticketsParados = Ticket::whereNotIn('status', ['closed', 'resolved'])
            ->where('created_at', '<', now()->subDay())->count();

        $pedidosPendentes = \App\Models\Order::where('status', 'pending')->count();

        $resumo = [
            'desde'  => $desde->toIso8601String(),
            'agora'  => now()->toIso8601String(),

            'erros' => [
                'novos_no_periodo' => $errosNovos->count(),
                'abertos_total'    => ErroDoSistema::abertos()->count(),
                'criticos'         => ErroDoSistema::abertos()
                    ->whereIn('nivel', ['critical', 'alert', 'emergency'])->count(),
                'lista' => $errosNovos->map(fn ($e) => [
                    'id'          => $e->id,
                    'nivel'       => $e->nivel,
                    'mensagem'    => mb_substr($e->mensagem, 0, 300),
                    'onde'        => $e->ficheiro ? "{$e->ficheiro}:{$e->linha}" : null,
                    'ocorrencias' => (int) $e->ocorrencias,
                ]),
            ],

            'facturacao' => [
                'facturas_vencidas'  => $facturasVencidas,
                'periodos_a_acabar_7d' => $periodosAAcabar,
                'pedidos_pendentes'  => $pedidosPendentes,
            ],

            'suporte' => [
                'tickets_abertos'    => $ticketsAbertos,
                'sem_resposta_24h'   => $ticketsParados,
            ],

            'saude' => $this->saudeResumida($inconsistencias),
        ];

        // A regra de quando falar. Fica AQUI e não do lado do agente: assim é
        // a plataforma que decide o que é digno de acordar alguém, e muda-se
        // num sítio só.
        $resumo['precisa_atencao'] =
            $resumo['erros']['criticos'] > 0
            || $resumo['erros']['novos_no_periodo'] > 0
            || $facturasVencidas > 0
            || $ticketsParados > 0
            || $pedidosPendentes > 0;

        $resumo['porque'] = $this->porqueAtencao($resumo, $facturasVencidas, $ticketsParados, $pedidosPendentes);

        return response()->json($resumo);
    }

    /** @return string[] frases curtas, prontas a ler a um humano */
    private function porqueAtencao(array $r, int $vencidas, int $parados, int $pendentes): array
    {
        $motivos = [];

        if ($r['erros']['criticos'] > 0) {
            $motivos[] = "{$r['erros']['criticos']} erro(s) crítico(s) por resolver";
        }

        if ($r['erros']['novos_no_periodo'] > 0) {
            $motivos[] = "{$r['erros']['novos_no_periodo']} erro(s) novo(s) no período";
        }

        if ($vencidas > 0) {
            $motivos[] = "{$vencidas} factura(s) de subscrição vencida(s)";
        }

        if ($parados > 0) {
            $motivos[] = "{$parados} pedido(s) de suporte há mais de 24 horas sem resposta";
        }

        if ($pendentes > 0) {
            $motivos[] = "{$pendentes} pedido(s) de plano à espera de decisão";
        }

        return $motivos;
    }

    /** As verificações de saúde, só com contagens. */
    private function saudeResumida(Inconsistencias $inconsistencias): array
    {
        try {
            // correr() devolve uma LISTA PLANA de achados, cada um com o seu
            // 'check'. Aqui só interessa quantos de cada — o agente pede o
            // detalhe em /health/inconsistencias se precisar.
            return collect($inconsistencias->correr())
                ->groupBy('check')
                ->map(fn ($grupo) => $grupo->count())
                ->all();
        } catch (\Throwable $e) {
            return ['erro_ao_verificar' => mb_substr($e->getMessage(), 0, 200)];
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  Empurrão manual
    // ══════════════════════════════════════════════════════════════

    /** Força o envio dos erros por notificar para o webhook do agente. */
    public function empurrarErros(NotificarOpenClaw $notificador)
    {
        return response()->json($notificador->empurrar());
    }
}
