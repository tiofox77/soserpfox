<?php

namespace App\Http\Controllers\Api\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Plan;
use App\Models\SoftwareSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Plataforma\GravarSubscricao;
use App\Support\AcordoDeSubscricao;
use App\Support\CicloDeFacturacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A FACTURAÇÃO DA PLATAFORMA — onde o dono do SaaS cobra aos clientes.
 *
 * Pedidos à espera de aprovação, subscrições e facturas. Uma revisão em sete
 * frentes deu 41 defeitos neste ecrã, e as correcções vêm todas:
 *
 *  · uma empresa apagada deixava ->tenant a null e o ecrã INTEIRO dava 500 —
 *    as relações carregam-se com `withTrashed` e a API diz «empresa apagada»;
 *  · o total da factura é somado aqui e não aceite do formulário;
 *  · uma factura gravada como paga fica com data de pagamento;
 *  · «marcar como paga» não apaga o método nem a referência;
 *  · aprovar ou recusar um pedido que já não está pendente é recusado — um
 *    separador aberto há meia hora ainda o mostra na fila;
 *  · recusar pede o motivo, que é o que o cliente vai ler.
 *
 * O QUE MUDOU: a lista das subscrições vinha INTEIRA, sem paginação, em cada
 * render do ecrã — mesmo com o separador fechado. Agora pagina-se como as
 * facturas, e cada separador pede o que é seu.
 */
class FacturacaoApiController extends Controller
{
    private const ESTADOS_DA_FACTURA = ['pending', 'paid', 'overdue', 'cancelled'];

    private const ESTADOS_DA_SUBSCRICAO = ['active', 'trial', 'pending', 'cancelled', 'expired', 'suspended'];

    private const METODOS = ['bank_transfer', 'cash', 'multicaixa', 'credit_card', 'other'];

    /** Os números do topo, os pedidos pendentes e as opções dos formulários. */
    public function index(): JsonResponse
    {
        return response()->json([
            'numeros' => [
                'cobrado' => (float) Invoice::where('status', 'paid')->sum('total'),
                'pendente' => (float) Invoice::where('status', 'pending')->sum('total'),
                'vencido' => (float) Invoice::where('status', 'overdue')->sum('total'),
                'facturas' => Invoice::count(),
                'subscricoes' => Subscription::count(),
                'pedidos_pendentes' => Order::where('status', 'pending')->count(),
                'pagamentos_por_confirmar' => Invoice::whereNotNull('payment_submitted_at')->whereIn('status', ['pending', 'overdue'])->count(),
            ],
            'pedidos' => Order::with(['tenant' => fn ($q) => $q->withTrashed(), 'user', 'plan', 'revendedor'])
                ->where('status', 'pending')->latest()->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'empresa' => $o->tenant?->name,
                    'empresa_apagada' => (bool) $o->tenant?->trashed(),
                    'pessoa' => $o->user?->name,
                    'email' => $o->user?->email,
                    'plano' => $o->plan?->name,
                    'valor' => (float) $o->amount,
                    'ciclo' => CicloDeFacturacao::nome($o->billing_cycle),
                    'dia' => $o->created_at?->format('d/m/Y H:i'),
                    'metodo' => $o->payment_method,
                    'referencia' => $o->payment_reference,
                    // A PROVA. Quando não havia comprovativo o ecrã ficava
                    // calado — e silêncio não se distingue de «ainda não vi».
                    'comprovativo' => $o->payment_proof ? Storage::url($o->payment_proof) : null,
                    // Pago pelo revendedor em nome da empresa (programa de revendedores).
                    'revendedor' => $o->revendedor ? ['nome' => $o->revendedor->nomeVisivel(), 'codigo' => $o->revendedor->code] : null,
                ])->values(),
            /*
             * AS FACTURAS COM O PAGAMENTO ENVIADO e por confirmar — hoje só os
             * revendedores as mandam. Confirmar é dar a factura como paga (que
             * estende a subscrição); recusar devolve-a com o motivo.
             */
            'pagamentos' => Invoice::with(['tenant' => fn ($q) => $q->withTrashed(), 'revendedorQuePagou'])
                ->whereNotNull('payment_submitted_at')->whereIn('status', ['pending', 'overdue'])
                ->orderBy('payment_submitted_at')->get()
                ->map(fn (Invoice $i) => [
                    'id' => $i->id,
                    'numero' => $i->invoice_number,
                    'empresa' => $i->tenant?->name,
                    'descricao' => $i->description,
                    'total' => (float) $i->total,
                    'vence' => $i->due_date?->format('d/m/Y'),
                    'enviado' => $i->payment_submitted_at?->format('d/m/Y H:i'),
                    'referencia' => $i->payment_reference,
                    'comprovativo' => $i->payment_proof ? Storage::url($i->payment_proof) : null,
                    'revendedor' => $i->revendedorQuePagou ? ['nome' => $i->revendedorQuePagou->nomeVisivel(), 'codigo' => $i->revendedorQuePagou->code] : null,
                    /*
                     * O QUE DEVE ENTRAR NA CONTA quando foi o revendedor a pagar:
                     * o preço de revendedor, não o total da factura. Sem isto, a
                     * transferência dele parecia um pagamento a menos. A diferença
                     * é a comissão dele, que fica compensada ao confirmar.
                     */
                    'esperado' => $i->revendedorQuePagou
                        ? app(\App\Services\Revenda\ComissoesDoRevendedor::class)->aPagarPeloRevendedor($i->revendedorQuePagou, $i)['preco']
                        : (float) $i->total,
                ])->values(),
            'opcoes' => [
                // AS EMPRESAS DESACTIVADAS CONTINUAM A PODER SER FACTURADAS: é
                // precisamente a elas que se emite a última factura.
                'empresas' => Tenant::orderBy('name')->get(['id', 'name', 'company_name'])
                    ->map(fn ($t) => [
                        'valor' => (string) $t->id,
                        'rotulo' => $t->name.($t->company_name && $t->company_name !== $t->name ? ' — '.$t->company_name : ''),
                    ])->values(),
                'planos' => Plan::with('modules:id')->where('is_active', true)->orderBy('order')->get()
                    ->map(fn (Plan $p) => [
                        'id' => $p->id,
                        'nome' => $p->name,
                        'max_utilizadores' => (int) $p->max_users,
                        'max_espaco_mb' => (int) $p->max_storage_mb,
                        'preco_mensal' => (float) $p->price_monthly,
                        'preco_trimestral' => (float) $p->price_quarterly,
                        'preco_semestral' => (float) $p->price_semiannual,
                        'preco_anual' => (float) $p->price_yearly,
                        'modulos' => $p->modules->count(),
                    ])->values(),
                'metodos' => [
                    ['valor' => 'bank_transfer', 'rotulo' => __('Transferência bancária')],
                    ['valor' => 'multicaixa', 'rotulo' => __('Multicaixa')],
                    ['valor' => 'cash', 'rotulo' => __('Numerário')],
                    ['valor' => 'credit_card', 'rotulo' => __('Cartão')],
                    ['valor' => 'other', 'rotulo' => __('Outro')],
                ],
            ],
            'saft' => [
                'certificado' => (string) softwareSetting('invoicing', 'saft_software_cert', ''),
                'produto' => (string) softwareSetting('invoicing', 'saft_product_id', ''),
                'versao' => (string) softwareSetting('invoicing', 'saft_version', '1.0.0'),
            ],
        ]);
    }

    /* ─── As subscrições ──────────────────────────────────────────────── */

    public function subscricoes(Request $request): JsonResponse
    {
        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(self::ESTADOS_DA_SUBSCRICAO)],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $procura = trim((string) ($f['procura'] ?? ''));

        $pagina = Subscription::with(['tenant' => fn ($q) => $q->withTrashed(), 'plan.modules'])
            ->when($procura !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('tenant', fn ($t) => $t->withTrashed()->where('name', 'like', "%{$procura}%"))
                ->orWhereHas('plan', fn ($p) => $p->where('name', 'like', "%{$procura}%"))))
            ->when(! empty($f['estado']), fn ($q) => $q->where('status', $f['estado']))
            ->latest()
            ->paginate(15, ['*'], 'pagina', $f['pagina'] ?? 1);

        return response()->json([
            'subscricoes' => collect($pagina->items())->map(fn (Subscription $s) => $this->linhaDaSubscricao($s))->values(),
            'paginacao' => ['pagina' => $pagina->currentPage(), 'ultima' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function fichaDaSubscricao(int $id): JsonResponse
    {
        $s = Subscription::with(['tenant' => fn ($q) => $q->withTrashed()])->findOrFail($id);

        return response()->json([
            'ficha' => [
                'id' => $s->id,
                'tenant_id' => $s->tenant_id,
                'empresa' => $s->tenant?->name,
                'plan_id' => $s->plan_id,
                'billing_cycle' => CicloDeFacturacao::normalizar($s->billing_cycle),
                // O ACORDO VEM TAL COMO ESTÁ GRAVADO: editar não pode «esquecer»
                // que esta empresa foi fechada sem oferta ou a N dias.
                'com_oferta' => (bool) ($s->com_oferta ?? true),
                'dias' => $s->dias_personalizados ? (int) $s->dias_personalizados : null,
                'preco_por_utilizador' => $s->preco_por_utilizador !== null ? (float) $s->preco_por_utilizador : null,
                'utilizadores' => $s->utilizadores_cobrados ? (int) $s->utilizadores_cobrados : null,
                'pago' => $s->status === 'active',
            ],
        ]);
    }

    /** O resumo do acordo — pela regra que grava. */
    public function resumo(Request $request): JsonResponse
    {
        $d = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,semiannual,yearly'],
            'com_oferta' => ['boolean'],
            'dias' => ['nullable', 'integer', 'min:1', 'max:3660'],
            'preco_por_utilizador' => ['nullable', 'numeric', 'min:0'],
            'utilizadores' => ['nullable', 'integer', 'min:1'],
        ]);

        $acordo = AcordoDeSubscricao::calcular(Plan::findOrFail($d['plan_id']), $d['billing_cycle'], [
            'com_oferta' => $d['com_oferta'] ?? true,
            'dias' => $d['dias'] ?? null,
            'preco_por_utilizador' => $d['preco_por_utilizador'] ?? null,
            'utilizadores' => $d['utilizadores'] ?? null,
        ]);

        return response()->json([
            'fim' => $acordo['fim']->format('d/m/Y'),
            'dias' => $acordo['dias'],
            'valor' => (float) $acordo['valor'],
            'base' => $acordo['base'],
            'oferta_aplicavel' => (bool) $acordo['oferta_aplicavel'],
            'ciclo_nome' => $acordo['dias_personalizados']
                ? __(':n dias', ['n' => $acordo['dias_personalizados']])
                : CicloDeFacturacao::nome($d['billing_cycle']),
        ]);
    }

    /** Aviso ao escolher a empresa: já tem uma subscrição activa? */
    public function subscricaoActivaDe(int $empresa): JsonResponse
    {
        $s = Subscription::with('plan')->where('tenant_id', $empresa)->where('status', 'active')->first();

        return response()->json([
            'activa' => $s ? ['plano' => $s->plan?->name, 'termina_em' => $s->current_period_end?->format('d/m/Y')] : null,
        ]);
    }

    public function guardarSubscricao(Request $request, GravarSubscricao $servico, ?int $id = null): JsonResponse
    {
        $emEdicao = $id ? Subscription::findOrFail($id) : null;

        $d = $request->validate([
            'tenant_id' => [$emEdicao ? 'nullable' : 'required', 'integer', 'exists:tenants,id'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,semiannual,yearly'],
            'com_oferta' => ['boolean'],
            'dias' => ['nullable', 'integer', 'min:1', 'max:3660'],
            'preco_por_utilizador' => ['nullable', 'numeric', 'min:0'],
            'utilizadores' => ['nullable', 'integer', 'min:1'],
            'pago' => ['boolean'],
            'metodo' => ['nullable', Rule::in(self::METODOS)],
            'referencia' => ['nullable', 'string', 'max:255'],
        ], [], [
            'tenant_id' => __('empresa'),
            'plan_id' => __('plano'),
        ]);

        try {
            $feito = $servico->gravar([
                'tenant_id' => $d['tenant_id'] ?? null,
                'plan_id' => $d['plan_id'],
                'billing_cycle' => $d['billing_cycle'],
                'pago' => $d['pago'] ?? true,
                'metodo' => $d['metodo'] ?? null,
                'referencia' => $d['referencia'] ?? null,
                'com_oferta' => $d['com_oferta'] ?? true,
                'dias' => $d['dias'] ?? null,
                'preco_por_utilizador' => $d['preco_por_utilizador'] ?? null,
                'utilizadores' => $d['utilizadores'] ?? null,
            ], $emEdicao, (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['dias' => $e->getMessage()]);
        }

        return response()->json(['message' => $feito['mensagem'], 'id' => $feito['subscricao']->id], $id ? 200 : 201);
    }

    /**
     * CANCELAR CUMPRE O QUE A CONFIRMAÇÃO PROMETE: acesso até ao fim do período.
     *
     * O `cancel()` do modelo punha o estado em 'cancelled' de imediato e o
     * cliente perdia o sistema no pedido seguinte — com o ano já pago.
     */
    public function cancelarSubscricao(int $id): JsonResponse
    {
        $s = Subscription::findOrFail($id);
        $fim = $s->current_period_end;

        if ($fim && $fim->isFuture()) {
            $s->update(['cancelled_at' => now(), 'ends_at' => $fim]);

            return response()->json([
                'message' => __('Subscrição cancelada. O acesso mantém-se até :dia.', ['dia' => $fim->format('d/m/Y')]),
            ]);
        }

        $s->cancel();

        return response()->json(['message' => __('Subscrição cancelada. O período já tinha terminado: o acesso cessa agora.')]);
    }

    public function apagarSubscricao(int $id): JsonResponse
    {
        $s = Subscription::findOrFail($id);

        // O 'trial' também dá acesso, e apagá-lo levava com ele a prova de que
        // aquela empresa já tinha gasto a sua cortesia.
        if (in_array($s->status, ['active', 'trial'], true)) {
            throw ValidationException::withMessages([
                'id' => __('Uma subscrição :estado não se apaga. Cancele-a primeiro.', ['estado' => $this->nomeDoEstado($s->status)]),
            ]);
        }

        $s->delete();

        return response()->json(['message' => __('Subscrição apagada.')]);
    }

    /* ─── As facturas ─────────────────────────────────────────────────── */

    public function facturas(Request $request): JsonResponse
    {
        $f = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(self::ESTADOS_DA_FACTURA)],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $procura = trim((string) ($f['procura'] ?? ''));

        $pagina = Invoice::with(['tenant' => fn ($q) => $q->withTrashed()])
            ->when($procura !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('invoice_number', 'like', "%{$procura}%")
                ->orWhereHas('tenant', fn ($t) => $t->withTrashed()->where('name', 'like', "%{$procura}%"))))
            ->when(! empty($f['estado']), fn ($q) => $q->where('status', $f['estado']))
            ->latest()
            ->paginate(15, ['*'], 'pagina', $f['pagina'] ?? 1);

        return response()->json([
            'facturas' => collect($pagina->items())->map(fn (Invoice $i) => [
                'id' => $i->id,
                'numero' => $i->invoice_number,
                'empresa' => $i->tenant?->name,
                'empresa_apagada' => (bool) $i->tenant?->trashed(),
                'descricao' => $i->description,
                'dia' => $i->invoice_date?->format('d/m/Y'),
                'vence' => $i->due_date?->format('d/m/Y'),
                'paga_em' => $i->paid_at?->format('d/m/Y'),
                'subtotal' => (float) $i->subtotal,
                'imposto' => (float) $i->tax,
                'total' => (float) $i->total,
                'estado' => $i->status,
                'metodo' => $i->payment_method,
                'referencia' => $i->payment_reference,
            ])->values(),
            'paginacao' => ['pagina' => $pagina->currentPage(), 'ultima' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function fichaDaFactura(int $id): JsonResponse
    {
        $i = Invoice::findOrFail($id);

        return response()->json([
            'ficha' => [
                'id' => $i->id,
                'tenant_id' => $i->tenant_id,
                'invoice_number' => $i->invoice_number,
                'description' => $i->description,
                'invoice_date' => $i->invoice_date?->format('Y-m-d'),
                'due_date' => $i->due_date?->format('Y-m-d'),
                'subtotal' => (float) $i->subtotal,
                'tax' => (float) $i->tax,
                'status' => $i->status,
            ],
        ]);
    }

    /** Um número novo, e as datas de hoje e a trinta dias. */
    public function novaFactura(): JsonResponse
    {
        return response()->json([
            'ficha' => [
                'id' => null,
                'tenant_id' => null,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'description' => '',
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'subtotal' => 0,
                'tax' => 0,
                'status' => 'pending',
            ],
        ]);
    }

    public function guardarFactura(Request $request, ?int $id = null): JsonResponse
    {
        $factura = $id ? Invoice::findOrFail($id) : null;

        $d = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'invoice_number' => ['required', 'string', 'max:60', Rule::unique('invoices', 'invoice_number')->ignore($factura?->id)],
            'description' => ['required', 'string', 'max:500'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(self::ESTADOS_DA_FACTURA)],
        ], [], [
            'tenant_id' => __('empresa'),
            'invoice_number' => __('número'),
            'description' => __('descrição'),
            'due_date' => __('vencimento'),
        ]);

        // O TOTAL É SOMADO AQUI. O campo é só de leitura no ecrã, mas «só de
        // leitura» é uma sugestão ao browser: o valor viaja como qualquer outro.
        $subtotal = round((float) $d['subtotal'], 2);
        $imposto = round((float) $d['tax'], 2);

        $campos = [
            'tenant_id' => $d['tenant_id'],
            'invoice_number' => $d['invoice_number'],
            'description' => $d['description'],
            'invoice_date' => $d['invoice_date'],
            'due_date' => $d['due_date'],
            'subtotal' => $subtotal,
            'tax' => $imposto,
            'total' => round($subtotal + $imposto, 2),
            'status' => $d['status'],
        ];

        // UMA FACTURA PAGA TEM DATA DE PAGAMENTO: sem ela fica «paga» na lista
        // e por pagar em qualquer relatório que use paid_at. E não se reescreve
        // a data de quem já estava paga.
        if ($d['status'] === 'paid' && ! $factura?->paid_at) {
            $campos['paid_at'] = now();
        }

        if ($factura) {
            $factura->update($campos);

            return response()->json(['message' => __('Factura :n guardada.', ['n' => $factura->invoice_number]), 'id' => $factura->id]);
        }

        $factura = Invoice::create($campos);

        return response()->json(['message' => __('Factura :n criada.', ['n' => $factura->invoice_number]), 'id' => $factura->id], 201);
    }

    public function pagarFactura(int $id): JsonResponse
    {
        $i = Invoice::findOrFail($id);

        // `markAsPaid()` sem argumentos apagava o método e a referência que já
        // lá estivessem gravados.
        $i->markAsPaid($i->payment_method, $i->payment_reference);

        return response()->json(['message' => __('Factura :n marcada como paga.', ['n' => $i->invoice_number])]);
    }

    /**
     * RECUSAR O PAGAMENTO ENVIADO de uma factura: o comprovativo sai, o motivo
     * fica, e o revendedor que o mandou é avisado para enviar outro.
     */
    public function recusarPagamentoDaFactura(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']], [
            'motivo.required' => __('Escreva o motivo — é o que o revendedor vai ler.'),
        ]);

        $i = Invoice::with(['tenant' => fn ($q) => $q->withTrashed(), 'revendedorQuePagou'])->findOrFail($id);

        if (! $i->payment_submitted_at || ! in_array($i->status, ['pending', 'overdue'], true)) {
            throw ValidationException::withMessages(['motivo' => __('Esta factura não tem nenhum pagamento por confirmar.')]);
        }

        $revendedor = $i->revendedorQuePagou;
        $i->forceFill(['payment_proof' => null, 'payment_submitted_at' => null, 'payment_rejection_reason' => $d['motivo']])->save();

        if ($revendedor) {
            app(\App\Services\Revenda\AvisosDaRevenda::class)->pagamentoRecusado($revendedor, (string) $i->tenant?->name, __('a factura :n', ['n' => $i->invoice_number]), $d['motivo']);
        }

        return response()->json(['message' => __('Pagamento da factura :n recusado.', ['n' => $i->invoice_number])]);
    }

    public function apagarFactura(int $id): JsonResponse
    {
        $i = Invoice::findOrFail($id);
        $numero = $i->invoice_number;
        $i->delete();

        return response()->json(['message' => __('Factura :n apagada.', ['n' => $numero])]);
    }

    /* ─── Os pedidos ──────────────────────────────────────────────────── */

    public function aprovarPedido(int $id): JsonResponse
    {
        $pedido = Order::with(['tenant', 'plan'])->findOrFail($id);
        $this->aindaPendente($pedido);

        try {
            // A escrita e todo o trabalho do observer numa transacção só: se o
            // observer rebentasse a meio, o pedido ficava 'approved', saía da
            // fila, e o cliente ficava sem plano sem ninguém dar por isso.
            DB::transaction(fn () => $pedido->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]));
        } catch (\Throwable $e) {
            Log::error('Aprovar pedido falhou', ['order_id' => $id, 'erro' => $e->getMessage()]);

            throw ValidationException::withMessages(['id' => __('O pedido não foi aprovado: :erro', ['erro' => $e->getMessage()])]);
        }

        return response()->json(['message' => __('Pedido aprovado. O cliente foi avisado por email.')]);
    }

    public function recusarPedido(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'motivo.required' => __('Escreva o motivo — é o que o cliente vai ler.'),
            'motivo.min' => __('O motivo tem de dizer alguma coisa.'),
        ]);

        $pedido = Order::with(['tenant', 'user', 'plan'])->findOrFail($id);
        $this->aindaPendente($pedido);

        DB::transaction(fn () => $pedido->update([
            'status' => 'rejected',
            'rejection_reason' => $d['motivo'],
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
        ]));

        // O pedido feito pelo revendedor: é ele que tem de enviar outro comprovativo.
        if ($pedido->reseller_id && ($revendedor = \App\Models\Reseller::find($pedido->reseller_id))) {
            app(\App\Services\Revenda\AvisosDaRevenda::class)->pagamentoRecusado($revendedor, (string) $pedido->tenant?->name, __('o pedido do plano :plano', ['plano' => $pedido->plan?->name]), $d['motivo']);
        }

        return response()->json(['message' => __('Pedido recusado. O cliente foi avisado com o motivo.')]);
    }

    /* ─── O SAF-T ─────────────────────────────────────────────────────── */

    public function guardarSaft(Request $request): JsonResponse
    {
        $d = $request->validate([
            'certificado' => ['nullable', 'string', 'max:100'],
            'produto' => ['nullable', 'string', 'max:100'],
            'versao' => ['required', 'string', 'max:20'],
        ]);

        SoftwareSetting::set('invoicing', 'saft_software_cert', (string) ($d['certificado'] ?? ''), 'string', 'Certificado Software AGT (SAFT-AO)');
        SoftwareSetting::set('invoicing', 'saft_product_id', (string) ($d['produto'] ?? ''), 'string', 'Product ID do software (SAFT-AO)');
        SoftwareSetting::set('invoicing', 'saft_version', $d['versao'], 'string', 'Versão do formato SAFT-AO');

        return response()->json(['message' => __('Configuração do SAF-T guardada.')]);
    }

    /* ─── As peças ────────────────────────────────────────────────────── */

    /** Um separador aberto há meia hora ainda mostra o pedido na fila. */
    private function aindaPendente(Order $pedido): void
    {
        if ($pedido->status !== 'pending') {
            throw ValidationException::withMessages([
                'id' => __('Este pedido já não está pendente (está «:estado»). Actualize a lista.', ['estado' => $pedido->status]),
            ]);
        }
    }

    private function linhaDaSubscricao(Subscription $s): array
    {
        $fim = $s->current_period_end;
        $dias = $fim ? (int) floor(now()->startOfDay()->diffInDays($fim->copy()->startOfDay(), false)) : null;

        return [
            'id' => $s->id,
            'empresa' => $s->tenant?->name,
            'empresa_apagada' => (bool) $s->tenant?->trashed(),
            'plano' => $s->plan?->name,
            'estado' => $s->status,
            'valor' => (float) $s->amount,
            'ciclo' => CicloDeFacturacao::nome($s->billing_cycle),
            'inicio' => $s->current_period_start?->format('d/m/Y'),
            'renovacao' => $fim?->format('d/m/Y'),
            // DIAS INTEIROS E COM SINAL: negativo = já passou. Imprimia-se o float
            // cru — «12.208333 dias» — e um período vencido aparecia a verde.
            'dias' => $dias,
            'cancelada_em' => $s->cancelled_at?->format('d/m/Y'),
            'com_oferta' => (bool) ($s->com_oferta ?? true),
            'dias_personalizados' => $s->dias_personalizados ? (int) $s->dias_personalizados : null,
            'preco_por_utilizador' => $s->preco_por_utilizador !== null ? (float) $s->preco_por_utilizador : null,
            'utilizadores_cobrados' => $s->utilizadores_cobrados ? (int) $s->utilizadores_cobrados : null,
            'limites' => $s->plan ? [
                'utilizadores' => (int) $s->plan->max_users,
                'empresas' => (int) $s->plan->max_companies,
                'espaco_mb' => (int) $s->plan->max_storage_mb,
                'dias_de_ensaio' => (int) $s->plan->trial_days,
            ] : null,
            'modulos' => $s->plan ? $s->plan->modules->map(fn ($m) => [
                'nome' => $m->name,
                'icone' => str_starts_with((string) $m->icon, 'fa-') ? $m->icon : 'fa-'.($m->icon ?: 'puzzle-piece'),
            ])->values() : [],
            'funcionalidades' => array_values($s->plan?->features ?? []),
            'pode_apagar' => ! in_array($s->status, ['active', 'trial'], true),
        ];
    }

    private function nomeDoEstado(string $estado): string
    {
        return match ($estado) {
            'active' => __('activa'),
            'trial' => __('em ensaio'),
            'pending' => __('pendente'),
            'cancelled' => __('cancelada'),
            'expired' => __('expirada'),
            'suspended' => __('suspensa'),
            default => $estado,
        };
    }
}
