<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Helpers\DocumentConfigHelper;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoicing\LineTax;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Traits\DocumentosPorAutor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Treasury\PaymentMethod;
use App\Services\Invoicing\CalculadoraDeDocumento;
use App\Services\Invoicing\DuplicaDocumento;
use App\Services\Invoicing\EmissorDeFacturas;
use App\Services\Invoicing\TaxResolver;
use App\Support\Geografia;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * EMITIR UMA FACTURA DE VENDA (FT ou FR), para o ecrã em React.
 *
 * NÃO HÁ LÓGICA FISCAL AQUI. Descontos, IEC/IS, campos SAFT, FR paga no acto,
 * retenção, hash, stock, tesouraria e fila da AGT vivem no `EmissorDeFacturas`
 * — o mesmo que o componente Livewire chama. Este controlador valida o pedido,
 * monta as linhas com a taxa resolvida no servidor, e chama.
 *
 * A TAXA DE CADA LINHA VEM DO `TaxResolver`, nunca do pedido. O preço pode
 * vir do pedido (é o preço de venda desta factura), a taxa não.
 */
class FacturaApiController extends Controller
{
    use DocumentosPorAutor;

    protected function modeloDoDocumento(): string
    {
        return SalesInvoice::class;
    }

    /**
     * A factura como o editor a precisa: cabeçalho e linhas, e se ainda se
     * pode mexer. Um rascunho edita-se; uma factura emitida abre-se só para
     * ler — rectifica-se com nota de crédito (Decreto 71/25).
     */
    public function abrir(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.invoices.view');

        return response()->json($this->paraEditor($this->baseDoAutor()->with('items')->findOrFail($id)));
    }

    /**
     * DUPLICAR: o conteúdo desta factura, para o editor abrir em branco.
     *
     * Não grava nada e não devolve documento nenhum — devolve o CONTEÚDO
     * COMERCIAL, e o editor abre com ele como se a pessoa o tivesse escrito.
     * O número, a série, o hash, o ATCUD, o estado e o pagamento ficam para
     * trás: quem decide o que viaja é o `DuplicaDocumento`, num sítio só.
     *
     * Vale para QUALQUER factura, incluindo as já emitidas — copiar o
     * conteúdo de um documento fiscal para um novo não lhe toca. A permissão
     * é a de CRIAR, porque é isso que isto começa.
     */
    public function duplicar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.invoices.create');

        $f = $this->baseDoAutor()->with('items')->findOrFail($id);
        $aberta = $this->paraEditor($f);
        $hoje = now()->toDateString();

        return response()->json(DuplicaDocumento::resposta(
            $aberta['documento'],
            $aberta['linhas'],
            [
                // Um duplicado é de HOJE, e o vencimento sai outra vez da
                // condição de pagamento do cliente — não do prazo que a
                // factura antiga tinha.
                'invoice_date' => $hoje,
                'due_date' => $this->vencimentoDoCliente((int) $f->client_id, $hoje),
                'delivery_date' => null,
            ],
            $f,
            $f->invoice_number,
        ));
    }

    /**
     * A factura na forma que o editor conhece.
     *
     * Serve o `abrir` e o `duplicar`: uma forma só, para o duplicado herdar
     * exactamente o que a edição herdaria — e mais nada.
     *
     * @return array{documento: array<string,mixed>, linhas: mixed}
     */
    private function paraEditor(SalesInvoice $f): array
    {
        $retencao = DB::table('invoicing_withholding_taxes')->where('document_type', SalesInvoice::class)->where('document_id', $f->id)->first();
        $extras = LineTax::where('line_type', SalesInvoiceItem::class)->whereIn('line_id', $f->items->pluck('id'))->get()->groupBy('line_id');
        $data = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($v ? substr((string) $v, 0, 10) : null);

        return [
            'documento' => [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'estado' => $f->status,
                'pode_editar' => $f->status === 'draft' || ($f->status === 'pending' && empty($f->saft_hash)),
                'client_id' => $f->client_id,
                'warehouse_id' => $f->warehouse_id,
                'invoice_type' => $f->invoice_type ?: 'FT',
                'series_id' => $f->series_id,
                'invoice_date' => $data($f->invoice_date),
                'due_date' => $data($f->due_date),
                'delivery_date' => $data($f->delivery_date),
                // ONDE os bens são entregues: o emissor já o gravava e o
                // editor não o mostrava, por isso reabrir uma factura
                // apagava-o.
                'delivery_location' => $f->delivery_location,
                'tax_country_region' => $f->tax_country_region ?? $f->items->first()?->tax_country_region,
                'payment_method' => $f->payment_method,
                'discount_commercial' => (float) ($f->discount_commercial ?? 0),
                'discount_financial' => (float) ($f->discount_financial ?? 0),
                'withholding_type' => $retencao->withholding_tax_type ?? null,
                'withholding_percentage' => (float) ($retencao->withholding_tax_percentage ?? 0),
                'notes' => $f->notes,
                // As condições que saem no papel — mesma história do local
                // de entrega: gravavam-se e não voltavam.
                'terms' => $f->terms,
                'pdf' => url('invoicing/sales/invoices/' . $f->id . '/pdf'),
            ],
            'linhas' => $f->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'description' => $i->description ?? '',
                'quantity' => (float) $i->quantity,
                'price' => (float) $i->unit_price,
                'discount_percent' => (float) ($i->discount_percent ?? 0),
                'iec' => $extras->get($i->id)?->firstWhere('tax_type', 'IEC')?->pautal_code,
                'is' => $extras->get($i->id)?->firstWhere('tax_type', 'IS')?->verba_no,
            ])->values(),
        ];
    }

    public function opcoes(Request $request): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.invoices.create');

        $tenantId = activeTenantId();

        return response()->json([
            // `payment_term_days` vai junto de propósito: é dele que sai o
            // vencimento quando se escolhe o cliente. O ecrã em Livewire
            // preenchia-o no `selectClient`; sem este campo, o ecrã em React
            // não teria por onde o saber e a factura nascia sem prazo.
            'clientes' => Client::where('tenant_id', $tenantId)->with('paymentTerm')->orderBy('name')->limit(500)
                ->get(['id', 'name', 'nif', 'province', 'payment_term_id', 'payment_term_days'])
                ->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'nif' => $c->nif, 'province' => $c->province,
                    'payment_term_days' => $this->diasDaCondicao($c),
                    /*
                     * A REGIÃO FISCAL QUE ESTE CLIENTE IMPLICA — decidida cá.
                     *
                     * Cabinda tem regime próprio (AO-CAB) e a regra é do
                     * `TaxResolver`. O ecrã mostra o crachá «a aplicar: X» que
                     * o formulário de sempre tinha, sem ter de repetir a regra
                     * em JavaScript — duas versões da mesma regra fiscal
                     * divergem, e esta decide quanto imposto se cobra.
                     */
                    'regiao' => TaxResolver::regionForClient($c),
                ]),
            'artigos' => Product::where('tenant_id', $tenantId)->where('is_active', true)
                ->orderBy('name')->limit(500)->get(['id', 'name', 'code', 'price', 'unit', 'type']),

            /*
             * O IEC E O IMPOSTO DE SELO — as listas OFICIAIS da AGT, da base.
             *
             * São 29 códigos pautais e 67 verbas, e estavam nos dois selectores
             * de cada linha do ecrã de sempre. Ao migrar desapareceram do
             * formulário — o servidor continuava a aceitá-los, mas não havia
             * por onde os escolher, e uma factura de bebidas ou de tabaco
             * deixou de poder levar o imposto especial que a lei manda.
             *
             * Em cache por uma hora: são tabelas fixas do Estado, e relê-las a
             * cada abertura do editor é uma consulta por nada.
             */
            'iec' => Cache::remember('agt_iec_pautal_codes', 3600, fn () => DB::table('agt_iec_pautal_codes')
                ->where('is_active', true)->orderBy('pautal_code')
                ->get(['pautal_code', 'description', 'rate_percentage'])
                ->map(fn ($c) => [
                    'codigo' => $c->pautal_code,
                    'descricao' => $c->description,
                    'taxa' => (float) $c->rate_percentage,
                ])->values()),

            'selo' => Cache::remember('agt_is_verbas', 3600, fn () => DB::table('agt_is_verbas')
                ->where('is_active', true)->orderBy('verba_no')
                ->get(['verba_no', 'description', 'rate', 'rate_type'])
                ->map(fn ($v) => [
                    'codigo' => (string) $v->verba_no,
                    'descricao' => $v->description,
                    'taxa' => (float) $v->rate,
                    // `percentage` ou `fixed`: uma verba fixa são kwanzas, não
                    // uma percentagem, e o ecrã escreve-as de maneira diferente.
                    'tipo' => $v->rate_type,
                ])->values()),
            'armazens' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),

            /*
             * O ARMAZÉM POR OMISSÃO, para o documento nascer com ele.
             *
             * A empresa marca um como padrão e é dele que sai quase tudo:
             * obrigar a escolhê-lo em cada factura é um clique por documento
             * que só existe para repetir uma decisão já tomada — e é o campo
             * que mais vezes ficava esquecido, com a factura a ser recusada
             * no fim por falta dele.
             */
            'armazem_padrao' => Warehouse::getDefault($tenantId)?->id,

            // As séries por tipo: a FR usa a sequência do POS.
            'series' => InvoicingSeries::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereIn('document_type', ['invoice', 'invoice_receipt', 'pos'])
                ->orderBy('series_code')
                ->get(['id', 'series_code', 'name', 'document_type', 'is_default']),

            'formas_de_pagamento' => PaymentMethod::where('tenant_id', $tenantId)
                ->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),

            'retencoes' => collect(EmissorDeFacturas::DESCRICOES_RETENCAO)
                ->map(fn ($d, $c) => ['valor' => $c, 'rotulo' => $d])->values(),

            'regioes' => [
                ['valor' => '', 'rotulo' => __('Pela província do cliente')],
                ['valor' => 'AO', 'rotulo' => 'Angola (continente)'],
                ['valor' => 'AO-CAB', 'rotulo' => 'Cabinda'],
            ],

            /*
             * O CLIENTE RÁPIDO — está-se a emitir, o cliente não existe, e
             * cria-se aqui sem largar o documento a meio.
             *
             * O ecrã não pode adivinhar quem pode criar clientes: é uma
             * permissão diferente da de emitir facturas, e quem não a tem não
             * vê o botão (a `/clients` recusa na mesma). O país por omissão
             * vem daqui e não de uma constante escrita em TypeScript — é o
             * mesmo `Geografia::PAIS_PADRAO` que o formulário de clientes usa.
             */
            'criar_parte' => [
                'tipo' => 'cliente',
                'pode' => (bool) $request->user()?->can('invoicing.clients.create'),
                'pais_padrao' => Geografia::PAIS_PADRAO,
            ],

            'permissoes' => [
                'pode_criar' => true,
            ],

            // «Imprimir automaticamente ao gravar», das definições da empresa:
            // o ecrã abre o PDF assim que a factura é emitida. Ver o mesmo
            // campo no `EmissorApiController`.
            'imprimir_ao_gravar' => DocumentConfigHelper::shouldAutoPrint(),
        ]);
    }

    /** Os totais para o ecrã mostrar. Não grava nada. */
    public function calcular(Request $request, CalculadoraDeDocumento $calculadora): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.invoices.create');

        $dados = $request->validate([
            'linhas' => ['array'],
            'linhas.*.product_id' => ['nullable', 'integer'],
            'linhas.*.description' => ['nullable', 'string', 'max:500'],
            'linhas.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'linhas.*.price' => ['nullable', 'numeric', 'min:0'],
            'linhas.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_commercial' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_financial' => ['nullable', 'numeric', 'min:0'],
            'is_service' => ['nullable', 'boolean'],
        ]);

        return response()->json($calculadora->calcular(
            $dados['linhas'] ?? [],
            (float) ($dados['discount_commercial'] ?? 0),
            (float) ($dados['discount_amount'] ?? 0),
            (float) ($dados['discount_financial'] ?? 0),
            (bool) ($dados['is_service'] ?? false)
        ));
    }

    public function guardar(Request $request, EmissorDeFacturas $emissor): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.invoices.create');

        return $this->emitirPedido($request, $emissor, null);
    }

    /** Guarda de novo um rascunho. O emissor recusa o que já está finalizado. */
    public function actualizar(Request $request, EmissorDeFacturas $emissor, int $id): JsonResponse
    {
        $this->exigir($request, 'invoicing.sales.invoices.create');

        return $this->emitirPedido($request, $emissor, $this->baseDoAutor()->findOrFail($id));
    }

    private function emitirPedido(Request $request, EmissorDeFacturas $emissor, ?SalesInvoice $existente): JsonResponse
    {        $dados = $request->validate([
            // Da EMPRESA: um id de outra casa punha o cliente dela no documento.
            'client_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('invoicing_clients', 'id')->where('tenant_id', activeTenantId())],
            'warehouse_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('invoicing_warehouses', 'id')->where('tenant_id', activeTenantId())],
            'invoice_type' => ['required', 'in:FT,FR'],
            'series_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('invoicing_series', 'id')->where('tenant_id', activeTenantId())],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'delivery_location' => ['nullable', 'string', 'max:255'],
            'is_service' => ['nullable', 'boolean'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_commercial' => ['nullable', 'numeric', 'min:0'],
            'discount_financial' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'tax_country_region' => ['nullable', 'in:AO,AO-CAB'],
            'withholding_type' => ['nullable', 'string', 'max:5'],
            'withholding_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'withholding_amount' => ['nullable', 'numeric', 'min:0'],
            // A Fatura-Recibo é paga no acto — exige a forma de pagamento.
            'payment_method' => ['required_if:invoice_type,FR', 'nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:draft,pending'],
            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.product_id' => ['nullable', 'integer', 'exists:invoicing_products,id'],
            'linhas.*.description' => ['nullable', 'string', 'max:500'],
            'linhas.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'linhas.*.price' => ['required', 'numeric', 'min:0'],
            'linhas.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'linhas.*.iec' => ['nullable', 'string', 'max:20'],
            'linhas.*.is' => ['nullable', 'string', 'max:20'],
        ], [
            'payment_method.required_if' => __('A Fatura-Recibo é paga no acto — selecione a forma de pagamento.'),
            'linhas.required' => __('Uma factura sem linhas não é uma factura.'),
            'linhas.min' => __('Uma factura sem linhas não é uma factura.'),
        ]);

        // O VENCIMENTO SAI DA CONDIÇÃO DE PAGAMENTO DO CLIENTE quando o pedido
        // não traz nenhum. No ecrã em Livewire era o `selectClient` que o
        // preenchia; uma factura gravada sem prazo aparece vencida no próprio
        // dia nas contas a receber e nos avisos.
        if (empty($dados['due_date'])) {
            $dados['due_date'] = $this->vencimentoDoCliente((int) $dados['client_id'], (string) $dados['invoice_date']);
        }

        // O armazém só é obrigatório havendo artigos físicos.
        [$linhas, $iec, $is, $temFisicos] = $this->linhasDoPedido($dados['linhas']);

        if ($temFisicos && empty($dados['warehouse_id'])) {
            return response()->json([
                'message' => __('Selecione um armazém — existem produtos físicos no documento.'),
                'errors' => ['warehouse_id' => [__('Obrigatório com artigos físicos.')]],
            ], 422);
        }

        try {
            $r = $emissor->emitir(
                array_merge($dados, ['status' => $dados['status'] ?? 'pending']),
                $linhas,
                $iec,
                $is,
                $existente
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['linhas' => [$e->getMessage()]]], 422);
        }

        $f = $r['factura'];

        return response()->json([
            'id' => $f->id,
            'numero' => $f->invoice_number,
            'total' => round((float) $f->total, 2),
            'agt' => $r['fila']['enfileirado'] ? __('A comunicar à AGT.') : null,
            'abrir' => '/invoicing/sales/invoices/' . $f->id,
            'pdf' => '/invoicing/sales/invoices/' . $f->id . '/pdf',
            // O estado diz ao ecrã se foi EMITIDA ou só guardada: um rascunho
            // não se imprime sozinho — não é documento para entregar a ninguém.
            'estado' => $f->status,
            'message' => $existente
                ? __('Factura :n actualizada.', ['n' => $f->invoice_number])
                : __('Factura :n emitida.', ['n' => $f->invoice_number]),
        ], $existente ? 200 : 201);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * As linhas na forma do emissor, com a TAXA resolvida aqui.
     *
     * @return array{0: Collection, 1: array, 2: array, 3: bool}  [linhas, IEC por id, IS por id, há físicos]
     */
    private function linhasDoPedido(array $pedidas): array
    {
        return EmissorDeFacturas::linhasDoPedido($pedidas);
    }

    /**
     * OS DIAS DE PRAZO DE UM CLIENTE, numa regra só.
     *
     * A condição ligada manda; `payment_term_days` é o valor legado e só vale
     * quando não há condição nenhuma (clientes antigos, importados). Os dois
     * sítios que precisam disto — a lista que o ecrã recebe e o vencimento que
     * se grava — leem daqui, senão o ecrã propunha um prazo e o servidor
     * gravava outro.
     */
    private function diasDaCondicao(Client $cliente): int
    {
        return $cliente->payment_term_id
            ? (int) ($cliente->paymentTerm?->days ?? 0)
            : (int) ($cliente->payment_term_days ?? 0);
    }

    /**
     * O vencimento que a condição de pagamento do cliente manda.
     *
     * Sem cliente, ou sem condição nenhuma e sem prazo legado, não se inventa
     * data: fica por preencher, como ficava.
     */
    private function vencimentoDoCliente(int $clienteId, string $dataDaFactura): ?string
    {
        $cliente = Client::where('tenant_id', activeTenantId())->find($clienteId);

        if (! $cliente || (! $cliente->payment_term_id && ! $cliente->payment_term_days)) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($dataDaFactura)
            ->addDays($this->diasDaCondicao($cliente))
            ->toDateString();
    }

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }
}
