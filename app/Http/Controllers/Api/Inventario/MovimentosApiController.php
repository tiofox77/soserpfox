<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O HISTÓRICO DE MOVIMENTOS — a resposta a «porque é que mudou?».
 *
 * SÓ LÊ. Tudo o que mexe no stock desta casa passa por aqui — vendas, compras,
 * transferências, quebras, contagens — e este ecrã é onde essa história se
 * consulta. Não há um único botão que escreva: o stock corrige-se onde ele é
 * feito, e não num ecrã de consulta.
 */
class MovimentosApiController extends Controller
{
    /**
     * AS ORIGENS COM NOME DE GENTE.
     *
     * `reference_type` é código de máquina — `restaurant_waste`, `quebra_anulada`
     * — e quem lê a lista não tem de saber o que isso quer dizer.
     */
    public const ORIGENS = [
        'sale' => 'Venda',
        'invoice' => 'Factura',
        'purchase' => 'Compra',
        'transfer' => 'Transferência',
        'quebra' => 'Quebra',
        'quebra_anulada' => 'Quebra anulada',
        'contagem' => 'Contagem física',
        'restaurant_waste' => 'Desperdício (restaurante)',
        'adjustment' => 'Ajuste',
    ];

    public const TIPOS = [
        'in' => 'Entrada',
        'out' => 'Saída',
        'transfer' => 'Transferência',
        'adjustment' => 'Ajuste',
    ];

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('inventario.view'), 403, __('Sem permissão para esta operação.'));

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', 'string', 'max:20'],
            'armazem' => ['nullable', 'integer'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $de = $filtros['de'] ?? now()->subDays(30)->format('Y-m-d');
        $ate = $filtros['ate'] ?? now()->format('Y-m-d');

        $base = fn () => StockMovement::forTenant()
            ->whereBetween('created_at', [$de.' 00:00:00', $ate.' 23:59:59'])
            ->when(($filtros['tipo'] ?? 'todos') !== 'todos', fn ($q) => $q->where('type', $filtros['tipo']))
            /*
             * O ARMAZÉM PODE ESTAR EM TRÊS COLUNAS.
             *
             * Uma transferência não tem `warehouse_id`: tem o de origem e o de
             * destino. Filtrar só pela primeira escondia metade dos movimentos
             * de qualquer armazém que recebesse transferências.
             */
            ->when($filtros['armazem'] ?? null, fn ($q, $a) => $q->where(fn ($w) => $w
                ->where('warehouse_id', $a)
                ->orWhere('from_warehouse_id', $a)
                ->orWhere('to_warehouse_id', $a)))
            ->when(trim($filtros['procura'] ?? '') !== '', function ($q) use ($filtros) {
                $t = '%'.trim($filtros['procura']).'%';

                $q->whereHas('product', fn ($p) => $p->where('name', 'like', $t)->orWhere('code', 'like', $t));
            });

        $lista = $base()
            ->with(['product:id,name,code,unit', 'user:id,name', 'warehouse:id,name'])
            ->latest()
            ->paginate($filtros['por_pagina'] ?? 30);

        return response()->json([
            'de' => $de,
            'ate' => $ate,
            'data' => collect($lista->items())->map(fn (StockMovement $m) => [
                'id' => $m->id,
                'quando' => $m->created_at?->format('Y-m-d H:i'),
                'tipo' => $m->type,
                'tipo_rotulo' => __(self::TIPOS[$m->type] ?? $m->type),
                'artigo' => $m->product?->name,
                'codigo' => $m->product?->code,
                'unidade' => $m->product?->unit,
                'quantidade' => (float) $m->quantity,
                'armazem' => $m->warehouse?->name,
                'origem' => $m->reference_type,
                'origem_rotulo' => __(self::ORIGENS[$m->reference_type] ?? ($m->reference_type ?: '—')),
                'referencia' => $m->reference_id,
                'quem' => $m->user?->name,
                'notas' => $m->notes,
            ])->values(),
            'meta' => [
                'current_page' => $lista->currentPage(),
                'last_page' => $lista->lastPage(),
                'per_page' => $lista->perPage(),
                'total' => $lista->total(),
                'from' => $lista->firstItem(),
                'to' => $lista->lastItem(),
            ],
            'resumo' => [
                'movimentos' => $base()->count(),
                'entradas' => $base()->where('type', 'in')->count(),
                'saidas' => $base()->where('type', 'out')->count(),
            ],
            'opcoes' => [
                'tipos' => collect(self::TIPOS)->map(fn ($r, $v) => ['valor' => $v, 'rotulo' => __($r)])->values(),
                'armazens' => Warehouse::forTenant()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($a) => ['valor' => (string) $a->id, 'rotulo' => $a->name])->values(),
            ],
        ]);
    }
}
