<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Invoicing\CalculadoraDeDocumento;
use App\Services\Invoicing\TiposDeDocumento;
use App\Traits\DocumentosPorAutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EMITIR PROPOSTAS — proformas de venda e de compra, e orçamentos.
 *
 * TRÊS REGRAS, E SÃO ELAS QUE JUSTIFICAM ESTE FICHEIRO EXISTIR:
 *
 * 1. A CONTA É FEITA AQUI, SEMPRE. O ecrã pergunta os totais a cada alteração
 *    (`/calcular`) e o servidor volta a fazê-los ao gravar, ignorando o que o
 *    browser tenha mandado. Uma cópia da matemática do imposto em TypeScript
 *    divergiria ao primeiro ajuste, e a divergência aparece como um cêntimo
 *    numa factura que a AGT recusa.
 *
 * 2. A TAXA VEM DO `TaxResolver`, nunca do pedido. Mandar `tax_rate: 0` numa
 *    linha de um artigo a 14% não muda nada.
 *
 * 3. O NÚMERO É DO MODELO. Cada documento numera-se a si próprio no `creating`,
 *    com a série da empresa. Escrever o número aqui era uma segunda sequência
 *    a competir com a primeira — o defeito que já mordeu na tesouraria.
 *
 * SÓ PROPOSTAS. Facturas, recibos e notas ficam de fora até terem cada um o
 * seu travão — ver `TiposDeDocumento::editaveis()`.
 */
class EmissorApiController extends Controller
{
    use DocumentosPorAutor;

    private string $modeloActual = '';

    protected function modeloDoDocumento(): string
    {
        return $this->modeloActual;
    }

    /** Os totais, para o ecrã mostrar enquanto se escreve. Não grava nada. */
    public function calcular(Request $request, string $tipo, CalculadoraDeDocumento $calculadora): JsonResponse
    {
        $this->definicao($request, $tipo, 'view');

        $dados = $request->validate([
            'linhas' => ['array'],
            'linhas.*.product_id' => ['nullable', 'integer'],
            'linhas.*.description' => ['nullable', 'string', 'max:500'],
            'linhas.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'linhas.*.price' => ['nullable', 'numeric', 'min:0'],
            'linhas.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'desconto_comercial' => ['nullable', 'numeric', 'min:0'],
            'desconto_financeiro' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json($calculadora->calcular(
            $dados['linhas'] ?? [],
            (float) ($dados['desconto_comercial'] ?? 0),
            0,
            (float) ($dados['desconto_financeiro'] ?? 0)
        ));
    }

    /** O que o editor precisa de saber ao abrir. */
    public function opcoes(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($request, $tipo, 'view');
        $editor = TiposDeDocumento::editaveis()[$tipo];

        $partes = $editor['parte_id'] === 'supplier_id'
            ? Supplier::where('tenant_id', activeTenantId())->where('is_active', true)
                ->orderBy('name')->limit(500)->get(['id', 'name', 'nif'])
            : Client::where('tenant_id', activeTenantId())
                ->orderBy('name')->limit(500)->get(['id', 'name', 'nif']);

        return response()->json([
            'titulo' => $def['titulo'],
            'parte' => $def['parte'],
            'rota' => $def['rota'],
            'partes' => $partes,
            'artigos' => Product::where('tenant_id', activeTenantId())
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'code', 'price', 'unit']),
            'permissoes' => [
                'pode_criar' => (bool) $request->user()?->can(str_replace('.view', '.create', $def['permissao'])),
            ],
        ]);
    }

    /** A proposta como o editor a precisa — e se ainda se pode mexer (só rascunhos). */
    public function abrir(Request $request, string $tipo, int $id): JsonResponse
    {
        $def = $this->definicao($request, $tipo, 'view');
        $editor = TiposDeDocumento::editaveis()[$tipo];

        $d = $this->baseDoAutor()->findOrFail($id);
        $linhas = $editor['itens']::where($editor['chave'], $d->id)->orderBy('order')->get();
        $data = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($v ? substr((string) $v, 0, 10) : null);

        return response()->json([
            'documento' => [
                'id' => $d->id,
                'numero' => $d->{$def['numero']},
                'estado' => $d->status,
                'pode_editar' => $d->status === 'draft',
                'parte_id' => $d->{$editor['parte_id']},
                'data' => $data($d->{$def['data']}),
                'valido_ate' => $data($d->valid_until ?? null),
                'notas' => $d->notes,
                'pdf' => url(ltrim($def['rota'], '/') . '/' . $d->id . '/pdf'),
            ],
            'linhas' => $linhas->map(fn ($l) => [
                'product_id' => $l->product_id,
                'description' => $l->description ?? '',
                'quantity' => (float) $l->quantity,
                'price' => (float) $l->unit_price,
                'discount_percent' => (float) ($l->discount_percent ?? 0),
            ])->values(),
        ]);
    }

    /** Grava a proposta. Recalcula tudo, ignorando os totais do pedido. */
    public function guardar(Request $request, string $tipo, CalculadoraDeDocumento $calculadora): JsonResponse
    {
        $this->definicao($request, $tipo, 'create');

        return $this->gravarPedido($request, $tipo, $calculadora, null);
    }

    /** Guarda de novo um rascunho: as linhas nascem de novo, o número fica. */
    public function actualizar(Request $request, string $tipo, CalculadoraDeDocumento $calculadora, int $id): JsonResponse
    {
        $this->definicao($request, $tipo, 'edit');

        $existente = $this->baseDoAutor()->findOrFail($id);

        if ($existente->status !== 'draft') {
            return response()->json(['message' => __('Só um rascunho se altera. Este documento já seguiu.')], 422);
        }

        return $this->gravarPedido($request, $tipo, $calculadora, $existente);
    }

    private function gravarPedido(Request $request, string $tipo, CalculadoraDeDocumento $calculadora, $existente): JsonResponse
    {
        $def = TiposDeDocumento::um($tipo);
        $editor = TiposDeDocumento::editaveis()[$tipo];
        $dados = $request->validate([
            'parte_id' => ['required', 'integer'],
            'data' => ['required', 'date'],
            'valido_ate' => ['nullable', 'date', 'after_or_equal:data'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'desconto_comercial' => ['nullable', 'numeric', 'min:0'],
            'desconto_financeiro' => ['nullable', 'numeric', 'min:0'],
            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.product_id' => ['nullable', 'integer', 'exists:invoicing_products,id'],
            'linhas.*.description' => ['nullable', 'string', 'max:500'],
            'linhas.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'linhas.*.price' => ['required', 'numeric', 'min:0'],
            'linhas.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'linhas.required' => __('Um documento sem linhas não é um documento.'),
            'linhas.min' => __('Um documento sem linhas não é um documento.'),
        ]);

        // A CONTA É FEITA AQUI. O que o browser mandou de totais é ignorado.
        $conta = $calculadora->calcular(
            $dados['linhas'],
            (float) ($dados['desconto_comercial'] ?? 0),
            0,
            (float) ($dados['desconto_financeiro'] ?? 0)
        );

        $modelo = $def['modelo'];

        $documento = DB::transaction(function () use ($def, $editor, $dados, $conta, $modelo, $existente) {
            if ($existente) {
                $editor['itens']::where($editor['chave'], $existente->id)->delete();
            }

            $d = $existente ?? new $modelo();

            if (! $existente) {
                $d->tenant_id = activeTenantId();
                $d->created_by = auth()->id();
                $d->status = 'draft';
            }

            $d->{$editor['parte_id']} = $dados['parte_id'];
            $d->{$def['data']} = $dados['data'];            $d->notes = $dados['notas'] ?? null;

            if (in_array('valid_until', $d->getFillable(), true) || ! empty($dados['valido_ate'])) {
                $d->valid_until = $dados['valido_ate'] ?? null;
            }

            $d->subtotal = $conta['totais']['liquido'];
            $d->tax_amount = $conta['totais']['imposto'];
            $d->total = $conta['totais']['total'];

            // O NÚMERO NÃO SE ESCREVE AQUI: o modelo numera-se a si próprio no
            // `creating`, com a série da empresa.
            $d->save();

            $ordem = 0;

            foreach ($conta['linhas'] as $l) {
                $editor['itens']::create([
                    $editor['chave'] => $d->id,
                    'product_id' => $l['product_id'],
                    'product_name' => $l['nome'],
                    'description' => $l['description'],
                    'quantity' => $l['quantity'],
                    'unit' => $l['unit'],
                    'unit_price' => $l['price'],
                    'discount_percent' => $l['discount_percent'],
                    'discount_amount' => $l['desconto'],
                    'subtotal' => $l['base'],
                    'tax_rate' => $l['tax_rate'],
                    'tax_amount' => $l['imposto'],
                    'total' => $l['total'],
                    'order' => ++$ordem,
                ]);
            }

            return $d;
        });

        return response()->json([
            'id' => $documento->id,
            'numero' => $documento->{$def['numero']},
            'total' => round((float) $documento->total, 2),
            'abrir' => $def['rota'] . '/' . $documento->id . '/edit',
            'message' => $existente
                ? __('Documento :n actualizado.', ['n' => $documento->{$def['numero']}])
                : __('Documento :n gravado como rascunho.', ['n' => $documento->{$def['numero']}]),
        ], $existente ? 200 : 201);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /**
     * Resolve o tipo, exige a permissão certa para o verbo, e arma o escopo.
     *
     * Um tipo que exista mas ainda NÃO seja editável dá 404 e não 403: não é
     * uma questão de permissão, é que o editor ainda não sabe emiti-lo.
     */
    private function definicao(Request $request, string $tipo, string $verbo): array
    {
        abort_unless(TiposDeDocumento::eEditavel($tipo), 404, __('Este documento ainda não se emite por aqui.'));

        $def = TiposDeDocumento::um($tipo);

        $permissao = match ($verbo) {
            'create' => str_replace('.view', '.create', $def['permissao']),
            'edit' => str_replace('.view', '.edit', $def['permissao']),
            default => $def['permissao'],
        };

        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));

        $this->modeloActual = $def['modelo'];

        return $def;
    }
}
