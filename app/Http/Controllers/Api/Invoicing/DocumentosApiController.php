<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\TiposDeDocumento;
use App\Traits\DocumentosPorAutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * As listas de documentos que têm todas a mesma forma.
 *
 * Proformas de venda e de compra, orçamentos, facturas de compra e recibos.
 * O que muda entre elas está no `TiposDeDocumento`; aqui há uma consulta só.
 *
 * O ESCOPO POR AUTOR APLICA-SE A TODOS. Quem só vê os documentos que emitiu
 * continua a ver só os seus, seja qual for o tipo — é o mesmo trait que as
 * listas em Blade usam, e não uma segunda regra escrita aqui.
 */
class DocumentosApiController extends Controller
{
    use DocumentosPorAutor;

    /** Preenchido a cada pedido, a partir do tipo pedido no endereço. */
    private string $modeloActual = '';

    protected function modeloDoDocumento(): string
    {
        return $this->modeloActual;
    }

    public function index(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($request, $tipo);

        $filtros = $request->validate([
            'procura' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'max:30'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $numero = $def['numero'];
        $data = $def['data'];
        $relacao = $def['relacao'];

        $query = $this->baseDoAutor()->with($relacao);

        if ($procura = ($filtros['procura'] ?? null)) {
            $query->where(function ($q) use ($procura, $numero, $relacao) {
                $q->where($numero, 'like', "%{$procura}%")
                    ->orWhereHas($relacao, fn ($p) => $p->where('name', 'like', "%{$procura}%"));
            });
        }

        $query
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('status', $v))
            // Comparação directa e não whereDate: uma função sobre a coluna
            // impede o MySQL de usar o índice.
            ->when($filtros['de'] ?? null, fn ($q, $v) => $q->where($data, '>=', $v))
            ->when($filtros['ate'] ?? null, fn ($q, $v) => $q->where($data, '<=', $v));

        $pagina = $query->orderByDesc($data)->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 15)
            ->withQueryString();

        return response()->json([
            'data' => collect($pagina->items())->map(fn ($d) => $this->linha($d, $def))->values(),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function opcoes(Request $request, string $tipo): JsonResponse
    {
        $def = $this->definicao($request, $tipo);

        return response()->json([
            'titulo' => $def['titulo'],
            'parte' => $def['parte'],
            'rota' => $def['rota'],
            'tem_saldo' => $def['tem_saldo'],
            // Pagar é emitir um recibo: a permissão é essa.
            'pode_pagar' => $def['tem_saldo'] && (bool) $request->user()?->can('invoicing.receipts.create'),

            // Os estados que existem MESMO nesta tabela, e não uma lista
            // inventada: cada documento tem os seus, e um filtro com opções
            // que nunca devolvem nada é pior do que não ter filtro.
            'estados' => $this->baseDoAutor()
                ->select('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->filter()
                ->map(fn ($e) => ['valor' => $e, 'rotulo' => $this->rotuloDoEstado($e)])
                ->values(),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /** Resolve o tipo, exige a permissão dele, e arma o trait do escopo. */
    private function definicao(Request $request, string $tipo): array
    {
        abort_unless(TiposDeDocumento::existe($tipo), 404, __('Tipo de documento desconhecido.'));

        $def = TiposDeDocumento::um($tipo);

        abort_unless(
            $request->user()?->can($def['permissao']),
            403,
            __('Sem permissão para ver estes documentos.')
        );

        $this->modeloActual = $def['modelo'];

        return $def;
    }

    private function linha($d, array $def): array
    {
        $parte = $d->{$def['relacao']};
        $valor = round((float) ($d->{$def['valor']} ?? 0), 2);

        $linha = [
            'id' => $d->id,
            'numero' => $d->{$def['numero']},
            'parte' => $parte?->name ?? __('Consumidor Final'),
            'data' => optional($d->{$def['data']})->toDateString() ?? (string) $d->{$def['data']},
            'estado' => $d->status,
            'estado_rotulo' => $this->rotuloDoEstado($d->status),
            'estado_cor' => $this->corDoEstado($d->status),
            'valor' => $valor,
        ];

        if ($def['tem_saldo']) {
            $pago = round((float) ($d->paid_amount ?? 0), 2);
            $linha['pago'] = $pago;
            // Pelo saldo, como em todo o lado: o que falta, não o total.
            $linha['saldo'] = in_array($d->status, ['paid', 'cancelled'], true)
                ? 0.0
                : max(0.0, round($valor - $pago, 2));
        }

        return $linha;
    }

    private function rotuloDoEstado(?string $estado): string
    {
        return match ($estado) {
            'draft' => __('Rascunho'),
            'sent', 'issued' => __('Emitido'),
            'pending' => __('Pendente'),
            'partially_paid' => __('Parcialmente pago'),
            'paid' => __('Pago'),
            'overdue' => __('Vencido'),
            'accepted' => __('Aceite'),
            'rejected' => __('Recusado'),
            'converted' => __('Convertido'),
            'expired' => __('Expirado'),
            'cancelled' => __('Anulado'),
            default => ucfirst((string) $estado),
        };
    }

    private function corDoEstado(?string $estado): string
    {
        return match ($estado) {
            'paid', 'accepted', 'converted' => 'bom',
            'draft', 'expired' => 'neutra',
            'cancelled', 'rejected' => 'perigo',
            'overdue' => 'aviso',
            default => 'primaria',
        };
    }
}
