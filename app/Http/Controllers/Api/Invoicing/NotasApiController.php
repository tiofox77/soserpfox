<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Product;
use App\Services\Invoicing\EmissorDeNotas;
use App\Services\Invoicing\TaxResolver;
use App\Traits\DocumentosPorAutor;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * NOTAS DE CRÉDITO E DE DÉBITO, para o ecrã em React.
 *
 * NÃO HÁ LÓGICA FISCAL AQUI. Tudo — o travão do E43, os campos SAFT, o IEC/IS
 * herdado, o hash, a fila da AGT — vive no `EmissorDeNotas`, que é o mesmo que
 * os componentes Livewire chamam. Este controlador valida o pedido, monta as
 * linhas e chama.
 *
 * A REGRA DAS LINHAS: uma linha que venha de uma factura traz `origem_line_id`
 * e o servidor HERDA da linha original a taxa, o código SAFT, a região e o
 * motivo de isenção — o browser manda a quantidade, e mais nada que a AGT vá
 * comparar. Uma linha livre (sem origem) resolve o imposto pelo `TaxResolver`,
 * como qualquer outro documento.
 */
class NotasApiController extends Controller
{
    use DocumentosPorAutor;

    protected function modeloDoDocumento(): string
    {
        return SalesInvoice::class;
    }

    public function opcoes(Request $request, string $tipo): JsonResponse
    {
        $this->exigir($request, $tipo, 'view');

        return response()->json([
            'titulo' => $tipo === 'credito' ? __('Nota de Crédito') : __('Nota de Débito'),
            'clientes' => Client::where('tenant_id', activeTenantId())
                ->orderBy('name')->limit(500)->get(['id', 'name', 'nif']),
            'artigos' => Product::where('tenant_id', activeTenantId())
                ->where('is_active', true)->orderBy('name')->limit(500)
                ->get(['id', 'name', 'code', 'price', 'unit']),
            'motivos' => $tipo === 'credito'
                ? [
                    ['valor' => 'return', 'rotulo' => __('Devolução')],
                    ['valor' => 'discount', 'rotulo' => __('Desconto')],
                    ['valor' => 'correction', 'rotulo' => __('Correcção')],
                    ['valor' => 'other', 'rotulo' => __('Outro')],
                ]
                : [
                    ['valor' => 'correction', 'rotulo' => __('Correcção')],
                    ['valor' => 'interest', 'rotulo' => __('Juros')],
                    ['valor' => 'other', 'rotulo' => __('Outro')],
                ],
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can($this->permissao($tipo, 'create')),
            ],
        ]);
    }

    /**
     * As facturas de um cliente que ainda se podem creditar.
     *
     * Pelo SALDO: uma factura já inteiramente anulada não se oferece — a AGT
     * recusaria com E43. Para a nota de débito não há tecto, e entram todas.
     */
    public function facturas(Request $request, string $tipo): JsonResponse
    {
        $this->exigir($request, $tipo, 'view');

        $dados = $request->validate(['cliente_id' => ['nullable', 'integer']]);

        $lista = $this->baseDoAutor()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when($dados['cliente_id'] ?? null, fn ($q, $v) => $q->where('client_id', $v))
            ->comCreditado()
            ->orderByDesc('invoice_date')
            ->limit(200)
            ->get()
            ->filter(fn ($f) => $tipo === 'debito' || ! $f->jaTotalmenteCreditada())
            ->values()
            ->map(fn ($f) => [
                'id' => $f->id,
                'numero' => $f->invoice_number,
                'data' => optional($f->invoice_date)->toDateString(),
                'total' => round((float) $f->total, 2),
                'por_creditar' => $f->porCreditar(),
            ]);

        return response()->json(['data' => $lista]);
    }

    /** As linhas de uma factura, prontas a entrar na nota. */
    public function linhas(Request $request, string $tipo, int $factura, EmissorDeNotas $emissor): JsonResponse
    {
        $this->exigir($request, $tipo, 'view');

        $f = $this->baseDoAutor()->with('items')->findOrFail($factura);

        return response()->json([
            'data' => $emissor->linhasDaFactura($f)->map(fn ($l) => [
                'origem_line_id' => $l->attributes['origem_line_id'],
                'product_id' => $l->id,
                'nome' => $l->name,
                'quantity' => $l->quantity,
                'price' => $l->price,
                'discount_percent' => $l->attributes['discount_percent'],
                'tax_rate' => $l->attributes['tax_rate'],
                'tax_country_region' => $l->attributes['tax_country_region'],
            ])->values(),
        ]);
    }

    public function guardar(Request $request, string $tipo, EmissorDeNotas $emissor): JsonResponse
    {
        $this->exigir($request, $tipo, 'create');

        $dados = $request->validate([
            'client_id' => ['required', 'integer'],
            'invoice_id' => ['nullable', 'integer'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:30'],
            'type' => ['nullable', 'in:total,partial'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.origem_line_id' => ['nullable', 'integer'],
            'linhas.*.product_id' => ['nullable', 'integer'],
            'linhas.*.description' => ['nullable', 'string', 'max:500'],
            'linhas.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'linhas.*.price' => ['nullable', 'numeric', 'min:0'],
            'linhas.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'linhas.required' => __('Uma nota sem linhas não é uma nota.'),
            'linhas.min' => __('Uma nota sem linhas não é uma nota.'),
        ]);

        $factura = null;

        if (! empty($dados['invoice_id'])) {
            // Escopada ao autor: uma nota nasce de uma factura, e se a factura
            // não é sua, a nota também não.
            $factura = $this->baseDoAutor()->with('items')->find($dados['invoice_id']);

            abort_unless($factura, 422, __('Factura não encontrada nesta empresa.'));
        }

        $linhas = $this->linhasDoPedido($factura, $dados['linhas'], $emissor, (int) $dados['client_id']);

        try {
            $r = $tipo === 'credito'
                ? $emissor->emitirCredito([
                    'client_id' => $dados['client_id'],
                    'invoice_id' => $factura?->id,
                    'issue_date' => $dados['issue_date'],
                    'reason' => $dados['reason'],
                    'type' => $dados['type'] ?? 'partial',
                    'notes' => $dados['notes'] ?? null,
                ], $linhas)
                : $emissor->emitirDebito([
                    'client_id' => $dados['client_id'],
                    'invoice_id' => $factura?->id,
                    'issue_date' => $dados['issue_date'],
                    'due_date' => $dados['due_date'] ?? null,
                    'reason' => $dados['reason'],
                    'notes' => $dados['notes'] ?? null,
                ], $linhas);
        } catch (DomainException $e) {
            // O travão falou — a nota não nasceu. 422 com a razão, no campo
            // das linhas, que é onde a pessoa tem de olhar.
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['linhas' => [$e->getMessage()]],
            ], 422);
        }

        $nota = $r['nota'];
        $numero = $tipo === 'credito' ? $nota->credit_note_number : $nota->debit_note_number;

        return response()->json([
            'id' => $nota->id,
            'numero' => $numero,
            'total' => round((float) $nota->total, 2),
            'agt' => $r['fila']['enfileirado'] ? __('A comunicar à AGT.') : null,
            'abrir' => $tipo === 'credito' ? '/invoicing/credit-notes' : '/invoicing/debit-notes',
            'message' => __(':n criada.', ['n' => $numero]),
        ], 201);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * As linhas do pedido, na forma que o emissor espera.
     *
     * Com `origem_line_id`, HERDA-SE tudo da linha original — o browser só
     * manda a quantidade. Sem origem, a linha é livre e o imposto resolve-se
     * pelo `TaxResolver`; o preço vem do pedido (ou do artigo).
     *
     * A REGIÃO DE UMA LINHA LIVRE É A DO ADQUIRENTE. Estava fixa em 'AO', e
     * isso declarava como continental uma nota a cliente de Cabinda, que tem
     * regime próprio (AO-CAB). O ecrã Livewire já resolvia pelo cliente; a API
     * não, e a nota saía assinada e comunicada com a região errada.
     */
    private function linhasDoPedido(?SalesInvoice $factura, array $pedidas, EmissorDeNotas $emissor, int $clienteId): Collection
    {
        $daFactura = $factura ? $emissor->linhasDaFactura($factura)->keyBy(fn ($l) => $l->attributes['origem_line_id']) : collect();

        // Uma vez por pedido: a região é do cliente, não da linha.
        $regiao = TaxResolver::regionForClient(
            Client::where('tenant_id', activeTenantId())->find($clienteId)
        );

        return collect($pedidas)->map(function (array $p) use ($daFactura, $regiao) {
            $origem = $p['origem_line_id'] ?? null;

            if ($origem && $daFactura->has($origem)) {
                $l = clone $daFactura->get($origem);
                $l->quantity = (float) $p['quantity'];

                return $l;
            }

            $artigo = ! empty($p['product_id'])
                ? Product::where('tenant_id', activeTenantId())->find($p['product_id'])
                : null;

            $imposto = TaxResolver::forProduct($artigo, activeTenantId());

            return (object) [
                'id' => $artigo?->id,
                'name' => $artigo?->name ?? ($p['description'] ?? ''),
                'price' => round((float) ($p['price'] ?? $artigo?->price ?? 0), 2),
                'quantity' => (float) $p['quantity'],
                'attributes' => [
                    'tax_rate' => (float) $imposto['rate'],
                    'tax_type' => $imposto['type'],
                    'exemption_reason' => $imposto['exemption_code'],
                    'discount_percent' => (float) ($p['discount_percent'] ?? 0),
                    'tax_code' => $imposto['tax_code'],
                    'tax_country_region' => $regiao,
                ],
            ];
        })->values();
    }

    private function permissao(string $tipo, string $verbo): string
    {
        return ($tipo === 'credito' ? 'invoicing.credit-notes.' : 'invoicing.debit-notes.') . $verbo;
    }

    /** Uma nota aberta para consulta. Emitida, não se edita — rectifica-se com outra. */
    public function mostrar(Request $request, string $tipo, int $id): JsonResponse
    {
        $this->exigir($request, $tipo, 'view');

        $modelo = $tipo === 'credito' ? CreditNote::class : DebitNote::class;
        $n = $this->baseDoAutor($modelo)->with(['items', 'client', 'invoice'])->findOrFail($id);
        $data = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($v ? substr((string) $v, 0, 10) : null);

        return response()->json(['nota' => [
            'id' => $n->id,
            'numero' => $tipo === 'credito' ? $n->credit_note_number : $n->debit_note_number,
            'estado' => $n->status,
            'cliente' => $n->client?->name,
            'factura' => $n->invoice?->invoice_number,
            'issue_date' => $data($n->issue_date),
            'due_date' => $data($n->due_date ?? null),
            'reason' => $n->reason,
            'type' => $n->type ?? null,
            'notes' => $n->notes,
            'total' => (float) $n->total,
            'pdf' => url('invoicing/' . ($tipo === 'credito' ? 'credit-notes' : 'debit-notes') . '/' . $n->id . '/pdf'),
            'linhas' => $n->items->map(fn ($i) => [
                'nome' => $i->description ?: ($i->product_name ?? ''),
                'quantity' => (float) $i->quantity,
                'price' => (float) $i->unit_price,
                'tax_rate' => (float) ($i->tax_rate ?? 0),
                'total' => (float) ($i->total ?? 0),
            ])->values(),
        ]]);
    }

    private function exigir(Request $request, string $tipo, string $verbo): void
    {
        abort_unless(in_array($tipo, ['credito', 'debito'], true), 404);
        abort_unless($request->user()?->can($this->permissao($tipo, $verbo)), 403, __('Sem permissão para esta operação.'));
    }
}
