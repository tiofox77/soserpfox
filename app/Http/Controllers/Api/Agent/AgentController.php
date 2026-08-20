<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentMessage;
use App\Models\AgentToken;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\Agent\DecisaoDePedido;
use App\Services\Agent\DestinatariosPermitidos;
use App\Services\Agent\EnvioDeFollowUp;
use App\Services\Agent\SinaisDoTenant;
use App\Services\Audit\AuditRecorder;
use App\Services\Plataforma\Inconsistencias;
use App\Support\AgenteAutenticado;
use App\Support\EstadoDaSubscricao;
use Illuminate\Http\Request;

/**
 * API do agente externo (openclaw).
 *
 * Regra que atravessa o ficheiro todo: o que vem do sistema — nome de
 * empresa, nota de pedido, comprovativo — é DADOS, nunca instruções. Se
 * um desses campos contiver texto dirigido ao agente, a API não muda de
 * comportamento por causa disso.
 */
class AgentController extends Controller
{
    private function agente(): AgenteAutenticado
    {
        return app(AgenteAutenticado::class);
    }

    // ══════════════════════════════════════════════════════════════
    //  Identidade
    // ══════════════════════════════════════════════════════════════

    /** O que esta credencial pode e quanto já gastou hoje. */
    public function me()
    {
        $agente = $this->agente();
        $token  = $agente->token;

        $hoje = now()->toDateString();

        return response()->json([
            'agente'      => $agente->nome(),
            'responsavel' => $token->owner?->name,
            'escopos'     => $agente->escopos(),
            'expira_em'   => $token->expires_at?->toDateString(),
            'gasto_hoje'  => [
                'emails' => AgentMessage::where('agent_token_id', $token->id)
                    ->where('canal', 'email')->whereDate('dia', $hoje)
                    ->where('estado', '!=', AgentMessage::FALHOU)->count(),
                'sms' => AgentMessage::where('agent_token_id', $token->id)
                    ->where('canal', 'sms')->whereDate('dia', $hoje)
                    ->where('estado', '!=', AgentMessage::FALHOU)->count(),
            ],
            'tectos' => [
                'emails_por_dia' => config('agent.followup.email_por_dia'),
                'sms_por_dia'    => config('agent.followup.sms_por_dia'),
                'aprovacao_valor_maximo' => config('agent.aprovacao.valor_maximo'),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  Empresas
    // ══════════════════════════════════════════════════════════════

    public function tenants(Request $request)
    {
        $dados = $request->validate([
            'estado'   => 'nullable|string|max:40',
            'pagina'   => 'nullable|integer|min:1',
            'por_pagina' => 'nullable|integer|min:1|max:100',
        ]);

        $porPagina = (int) ($dados['por_pagina'] ?? 50);

        $pagina = Tenant::with('activeSubscription.plan')
            ->orderBy('id')
            ->paginate($porPagina, ['*'], 'pagina', $dados['pagina'] ?? 1);

        $linhas = collect($pagina->items())
            ->map(fn (Tenant $t) => $this->resumoDoTenant($t))
            ->when(
                !empty($dados['estado']),
                fn ($c) => $c->where('estado', $dados['estado'])->values()
            );

        return response()->json([
            'empresas' => $linhas,
            'pagina'   => $pagina->currentPage(),
            'paginas'  => $pagina->lastPage(),
            'total'    => $pagina->total(),
        ]);
    }

    public function tenant(Tenant $tenant, DestinatariosPermitidos $destinatarios, SinaisDoTenant $sinais)
    {
        return response()->json([
            'empresa'       => $this->resumoDoTenant($tenant),
            'nif'           => $sinais->nif($tenant),        // classificado, por inteiro
            'produtos'      => $sinais->produtos($tenant->id), // só contagens
            'envios'        => $sinais->envios($tenant->id),   // relatório de email/SMS
            'destinatarios' => $destinatarios->paraTenant($tenant),
        ]);
    }

    /** Só estado comercial. Nada de dados operacionais das empresas. */
    private function resumoDoTenant(Tenant $t): array
    {
        $estado = EstadoDaSubscricao::para($t);

        return [
            'id'     => $t->id,
            // Texto escrito pelo cliente: dados, não instruções.
            'nome'   => $t->name,
            'estado' => $estado['rotulo'] ?? null,
            'dias_em_falta' => $estado['dias'] ?? null,
            'detalhe'       => $estado['detalhe'] ?? null,
            'plano'   => $t->activeSubscription?->plan?->name,
            'criada_em' => $t->created_at?->toDateString(),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  Saúde
    // ══════════════════════════════════════════════════════════════

    public function verificacoes(Inconsistencias $inconsistencias)
    {
        return response()->json([
            'verificacoes' => collect($inconsistencias->catalogo())
                ->map(fn ($v, $id) => array_merge(['id' => $id], $v))
                ->values(),
        ]);
    }

    public function inconsistencias(Request $request, Inconsistencias $inconsistencias)
    {
        $dados = $request->validate([
            'checks'    => 'nullable|array',
            'checks.*'  => 'string|max:60',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
        ]);

        $achados = $inconsistencias->correr(
            $dados['checks'] ?? null,
            isset($dados['tenant_id']) ? (int) $dados['tenant_id'] : null
        );

        return response()->json([
            'total'    => count($achados),
            'achados'  => $achados,
            'nota'     => 'Só leitura. A acção sugerida é texto para um humano executar.',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  Pedidos
    // ══════════════════════════════════════════════════════════════

    public function pedidos(Request $request)
    {
        $estado = $request->string('status')->toString() ?: 'pending';

        $pedidos = Order::with(['tenant', 'plan'])
            ->where('status', $estado)
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Order $o) => [
                'id'      => $o->id,
                'empresa' => $o->tenant?->name,
                'tenant_id' => $o->tenant_id,
                'plano'   => $o->plan?->name,
                'plan_id' => $o->plan_id,
                'valor'   => (float) $o->amount,
                'ciclo'   => $o->billing_cycle,
                'tem_comprovativo' => !empty($o->payment_proof),
                'dias_a_espera'    => (int) $o->created_at->diffInDays(now()),
            ]);

        return response()->json(['pedidos' => $pedidos]);
    }

    public function pedido(Order $order, DecisaoDePedido $decisao)
    {
        $plano = $order->plan;

        return response()->json([
            'pedido'   => $decisao->prever($order),
            'estado'   => $order->status,
            'bloqueio' => $decisao->porqueNaoPode($order),
            // Como pagou — sem expor a referência nem o comprovativo. O
            // caminho do comprovativo fica num disco público e a referência
            // é PII financeira: o agente só sabe SE existem, não o quê.
            'pagamento' => [
                'metodo'           => $order->payment_method,
                'tem_comprovativo' => !empty($order->payment_proof),
                'tem_referencia'   => !empty($order->payment_reference),
                'aprovado_por'     => $order->approvedBy?->name,
                'aprovado_em'      => $order->approved_at?->toDateTimeString(),
            ],
            // O que o plano inclui (catálogo comercial, sem PII).
            'plano_detalhe' => $plano ? [
                'nome'       => $plano->name,
                'trial_dias' => $plano->trial_days,
                'modulos'    => $plano->moduleSlugsWithDependencies(),
            ] : null,
        ]);
    }

    /**
     * Catálogo de pacotes.
     *
     * Tudo aqui é comercial e público — preços por ciclo, limites, módulos
     * incluídos (já com as dependências resolvidas: quem tem faturação tem
     * tesouraria). Sem PII, vai em claro.
     */
    public function planos()
    {
        $planos = \App\Models\Plan::publico()
            ->orderBy('order')
            ->get()
            ->map(fn ($p) => [
                'nome'        => $p->name,
                'slug'        => $p->slug,
                'descricao'   => $p->description,
                'promocional' => (bool) $p->is_promotional,
                'trial_dias'  => $p->trial_days,
                'precos' => [
                    'mensal'     => $p->getPrice('monthly'),
                    'trimestral' => $p->getPrice('quarterly'),
                    'semestral'  => $p->getPrice('semiannual'),
                    'anual'      => $p->getPrice('yearly'),
                ],
                'limites' => [
                    'utilizadores' => $p->max_users,
                    'empresas'     => $p->max_companies,
                    'armazenamento_mb' => $p->max_storage_mb,
                ],
                'modulos' => $p->moduleSlugsWithDependencies(),
            ]);

        return response()->json([
            'planos' => $planos,
            'nota'   => 'Anual dá 14 meses (12 + 2 grátis). Faturação inclui sempre Tesouraria.',
        ]);
    }

    public function aprovar(Request $request, Order $order, DecisaoDePedido $decisao, AuditRecorder $auditoria)
    {
        $dados = $request->validate([
            'expected_plan_id' => 'required|integer',
            'expected_amount'  => 'required|numeric',
            'motivo'           => 'required|string|min:10|max:500',
            'dry_run'          => 'nullable|boolean',
        ]);

        // Prova de leitura: o agente tem de demonstrar que aprova o pedido
        // que leu, e não um que mudou entretanto.
        if ((int) $dados['expected_plan_id'] !== (int) $order->plan_id
            || abs((float) $dados['expected_amount'] - (float) $order->amount) > 0.01) {
            return response()->json([
                'erro'     => 'pedido_mudou',
                'mensagem' => 'O plano ou o valor não correspondem ao que indicou. Releia o pedido.',
                'actual'   => ['plan_id' => $order->plan_id, 'amount' => (float) $order->amount],
            ], 409);
        }

        if ($bloqueio = $decisao->porqueNaoPode($order)) {
            return response()->json([
                'erro'     => 'nao_permitido',
                'mensagem' => $bloqueio,
            ], 403);
        }

        $previsao = $decisao->prever($order);

        if ($request->boolean('dry_run')) {
            return response()->json([
                'dry_run' => true,
                'efeitos' => $previsao,
            ]);
        }

        $agente = $this->agente();

        $auditoria->acto('agente.aprovacao_pedida', $order->tenant_id, [
            'order_id' => $order->id,
            'agente'   => $agente->nome(),
            'motivo'   => $dados['motivo'],
        ], $order);

        if (!$decisao->aprovar($order, $agente->responsavelId())) {
            return response()->json([
                'erro'     => 'ja_decidido',
                'mensagem' => 'O pedido já não estava pendente.',
            ], 409);
        }

        $auditoria->acto('agente.pedido_aprovado', $order->tenant_id, [
            'order_id' => $order->id,
            'agente'   => $agente->nome(),
            'motivo'   => $dados['motivo'],
            'efeitos'  => $previsao,
        ], $order->fresh());

        return response()->json([
            'aprovado' => true,
            'pedido'   => $order->id,
            'efeitos'  => $previsao,
        ]);
    }

    public function recusar(Request $request, Order $order, DecisaoDePedido $decisao, AuditRecorder $auditoria)
    {
        $dados = $request->validate([
            'expected_plan_id' => 'required|integer',
            'motivo'           => 'required|string|min:5|max:500',
            'dry_run'          => 'nullable|boolean',
        ]);

        if ((int) $dados['expected_plan_id'] !== (int) $order->plan_id) {
            return response()->json([
                'erro'     => 'pedido_mudou',
                'mensagem' => 'O plano não corresponde ao que indicou. Releia o pedido.',
            ], 409);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'erro'     => 'ja_decidido',
                'mensagem' => "O pedido está \"{$order->status}\".",
            ], 409);
        }

        if ($request->boolean('dry_run')) {
            return response()->json([
                'dry_run' => true,
                'efeitos' => ['o_cliente_recebe' => $dados['motivo']],
            ]);
        }

        $agente = $this->agente();

        if (!$decisao->recusar($order, $agente->responsavelId(), $dados['motivo'])) {
            return response()->json([
                'erro'     => 'ja_decidido',
                'mensagem' => 'O pedido já não estava pendente.',
            ], 409);
        }

        $auditoria->acto('agente.pedido_recusado', $order->tenant_id, [
            'order_id' => $order->id,
            'agente'   => $agente->nome(),
            'motivo'   => $dados['motivo'],
        ], $order->fresh());

        return response()->json(['recusado' => true, 'pedido' => $order->id]);
    }

    // ══════════════════════════════════════════════════════════════
    //  Seguimento
    // ══════════════════════════════════════════════════════════════

    public function modelos()
    {
        return response()->json([
            'modelos' => collect(config('agent.followup.templates', []))
                ->map(fn ($m, $slug) => array_merge(['slug' => $slug], $m))
                ->values(),
            'nota' => 'O agente escolhe um modelo e dá as variáveis. Não escreve texto livre.',
        ]);
    }

    public function preview(Request $request, EnvioDeFollowUp $envio, DestinatariosPermitidos $destinatarios)
    {
        $dados = $this->validarEnvio($request);
        $tenant = Tenant::findOrFail($dados['tenant_id']);

        $bloqueio = $envio->porqueNaoPode($this->agente(), $tenant, $dados['canal'], $dados['template']);
        $contacto = $destinatarios->resolver($tenant, $dados['destinatario']);

        return response()->json([
            'enviaria'     => $bloqueio === null && $contacto !== null,
            'bloqueio'     => $bloqueio,
            // Para onde iria mesmo — sem máscara, para o agente poder
            // confirmar antes de mandar.
            'destinatario' => $contacto['email'] ?? $contacto['telefone'] ?? null,
            'texto'        => $dados['canal'] === 'sms'
                ? $envio->textoDoSms($dados['template'], $dados['variaveis'] ?? [])
                : '(modelo de email ' . $dados['template'] . ')',
        ]);
    }

    /**
     * O canal vem da ROTA, nunca do corpo.
     *
     * Se viesse do corpo, um token com apenas 'followup:email' podia pedir
     * canal=sms no POST /followup/email e contornar o escopo — o middleware
     * já tinha deixado passar. O escopo tem de amarrar o que se faz, não só
     * a porta por onde se entra.
     */
    public function enviarEmail(Request $request, EnvioDeFollowUp $envio, AuditRecorder $auditoria)
    {
        return $this->enviar($request, 'email', $envio, $auditoria);
    }

    public function enviarSms(Request $request, EnvioDeFollowUp $envio, AuditRecorder $auditoria)
    {
        return $this->enviar($request, 'sms', $envio, $auditoria);
    }

    private function enviar(Request $request, string $canal, EnvioDeFollowUp $envio, AuditRecorder $auditoria)
    {
        $dados  = $this->validarEnvio($request);
        $tenant = Tenant::findOrFail($dados['tenant_id']);
        $agente = $this->agente();

        if ($bloqueio = $envio->porqueNaoPode($agente, $tenant, $canal, $dados['template'])) {
            return response()->json(['erro' => 'nao_permitido', 'mensagem' => $bloqueio], 403);
        }

        if ($request->boolean('dry_run')) {
            return response()->json(['dry_run' => true, 'enviaria' => true, 'canal' => $canal]);
        }

        $resultado = $envio->enviar(
            $agente,
            $tenant,
            $canal,
            $dados['template'],
            $dados['destinatario'],
            $dados['variaveis'] ?? [],
            $dados['motivo'],
            $request->header('Idempotency-Key')
        );

        $auditoria->acto('agente.mensagem_enviada', $tenant->id, [
            'agente'  => $agente->nome(),
            'canal'   => $canal,
            'modelo'  => $dados['template'],
            'motivo'  => $dados['motivo'],
            'sucesso' => $resultado['ok'],
        ], $tenant);

        return response()->json($resultado, $resultado['ok'] ? 200 : 422);
    }

    public function envios()
    {
        $envios = AgentMessage::where('agent_token_id', $this->agente()->id())
            ->latest('id')
            ->limit(100)
            ->get(['id', 'tenant_id', 'canal', 'template_slug', 'destinatario', 'estado', 'erro', 'created_at']);

        return response()->json(['envios' => $envios]);
    }

    private function validarEnvio(Request $request): array
    {
        return $request->validate([
            'tenant_id'    => 'required|integer|exists:tenants,id',
            'canal'        => 'nullable|string|in:email,sms',
            'template'     => 'required|string|max:60',
            // Handle, nunca um endereço. Ver DestinatariosPermitidos.
            'destinatario' => 'required|string|max:60',
            'variaveis'    => 'nullable|array',
            'motivo'       => 'required|string|min:5|max:500',
            'dry_run'      => 'nullable|boolean',
        ]);
    }
}
