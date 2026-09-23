<?php

namespace App\Services\POS;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\SalesInvoice;
use Illuminate\Support\Collection;

/**
 * O FECHO COM PRODUTOS (16/09/2026) — o que se vendeu no turno, artigo a
 * artigo, com os totais e os documentos.
 *
 * Os documentos são os que o TURNO registou (movimentos `invoice` e
 * `credit_note`), e não os do dia ou do operador: é a mesma fonte do dinheiro
 * esperado, e assim os dois números falam do mesmo balcão. Uma venda paga em
 * dois meios tem dois movimentos e conta uma vez só.
 *
 * As devoluções (notas de crédito do turno) descontam no artigo; as facturas
 * anuladas ficam na lista dos documentos mas não contam para os artigos. O
 * desconto dado ao documento inteiro fica numa linha à parte — é o que faz a
 * soma dos artigos bater com o total das facturas.
 */
class ProdutosDoTurno
{
    private const ANULADAS = ['cancelled', 'draft'];

    /**
     * @return array{
     *   produtos: list<array{chave:string, nome:string, codigo:?string, quantidade:float, devolvida:float, liquida:float, preco_medio:float, total:float, devolvido:float, liquido:float, documentos:int, peso:float}>,
     *   totais: array{artigos:int, quantidade:float, bruto:float, descontos:float, devolvido:float, liquido:float, imposto:float, facturas:int, anuladas:int, notas:int, ticket_medio:float},
     *   documentos: list<array{tipo:string, numero:string, hora:?string, cliente:?string, meio:string, artigos:int, total:float, anulada:bool}>,
     * }
     */
    public static function de(PosShift $turno): array
    {
        $movimentos = $turno->relationLoaded('transactions') ? $turno->transactions : $turno->transactions()->get();

        $idsFacturas = $movimentos->where('type', 'invoice')->where('reference_type', SalesInvoice::class)->pluck('reference_id')->filter()->unique()->values();
        $idsNotas = $movimentos->where('type', 'credit_note')->pluck('reference_id')->filter()->unique()->values();

        // O meio de cada documento sai dos movimentos DESTE turno: numa venda
        // dividida são dois, e a factura só diz «multiple».
        $meios = $movimentos->groupBy(fn ($m) => $m->type . ':' . $m->reference_id)
            ->map(fn (Collection $ms) => $ms->map(fn ($m) => $m->payment_method_label)->unique()->implode(' + '));

        $facturas = $idsFacturas->isEmpty() ? collect() : SalesInvoice::withoutGlobalScope('tenant')
            ->where('tenant_id', $turno->tenant_id)
            ->whereIn('id', $idsFacturas)
            ->with(['items.product:id,code,name', 'client:id,name', 'series:id,prefix,series_code,agt_series_id'])
            ->get();

        $notas = $idsNotas->isEmpty() ? collect() : CreditNote::withoutGlobalScope('tenant')
            ->where('tenant_id', $turno->tenant_id)
            ->whereIn('id', $idsNotas)
            ->with(['items.product:id,code,name', 'client:id,name', 'series:id,prefix,series_code,agt_series_id'])
            ->get();

        $produtos = [];
        $linha = function ($item, ?string $nomeDaLinha) use (&$produtos): string {
            $nome = trim((string) ($nomeDaLinha ?: $item->description ?: $item->product?->name ?: __('Artigo sem nome')));
            $chave = $item->product_id ? 'p' . $item->product_id : 'n' . mb_strtolower($nome);

            $produtos[$chave] ??= [
                'chave' => $chave,
                'nome' => $item->product?->name ?: $nome,
                'codigo' => $item->product?->code,
                'quantidade' => 0.0, 'devolvida' => 0.0,
                'total' => 0.0, 'devolvido' => 0.0,
                'documentos' => [],
            ];

            return $chave;
        };

        $bruto = $descontos = $imposto = 0.0;
        $validas = 0;
        $anuladas = 0;
        $documentos = [];

        foreach ($facturas as $f) {
            $anulada = in_array($f->status, self::ANULADAS, true);
            $anulada ? $anuladas++ : $validas++;

            if (! $anulada) {
                foreach ($f->items as $item) {
                    $k = $linha($item, $item->product_name);
                    $produtos[$k]['quantidade'] += (float) $item->quantity;
                    $produtos[$k]['total'] += (float) $item->total;
                    $produtos[$k]['documentos'][$f->id] = true;
                    $bruto += (float) $item->total;
                    $imposto += (float) $item->tax_amount;
                }
                $descontos += (float) $f->discount_amount;
            }

            $documentos[] = [
                'tipo' => 'factura',
                // A série INTERNA em cima e a da AGT por baixo, como nas listas.
                'numero' => $f->numeroInterno(),
                'numero_agt' => $f->numeroInterno() !== (string) $f->invoice_number ? (string) $f->invoice_number : null,
                'hora' => optional($f->created_at)->format('H:i'),
                'quando' => $f->created_at?->toIso8601String(),
                'cliente' => $f->client?->name,
                'meio' => $meios->get('invoice:' . $f->id) ?: posPaymentMethodLabel($f->payment_method),
                'artigos' => $f->items->count(),
                'total' => round((float) $f->total, 2),
                'anulada' => $anulada,
            ];
        }

        $devolvido = 0.0;
        $validasNotas = 0;

        foreach ($notas as $n) {
            $anulada = in_array($n->status, self::ANULADAS, true);

            if (! $anulada) {
                $validasNotas++;
                foreach ($n->items as $item) {
                    $k = $linha($item, null);
                    $produtos[$k]['devolvida'] += (float) $item->quantity;
                    $produtos[$k]['devolvido'] += (float) $item->total;
                    $imposto -= (float) $item->tax_amount;
                }
                $devolvido += (float) $n->total;
            }

            $documentos[] = [
                'tipo' => 'nota',
                'numero' => $n->numeroInterno(),
                'numero_agt' => $n->numeroInterno() !== (string) $n->credit_note_number ? (string) $n->credit_note_number : null,
                'hora' => optional($n->created_at)->format('H:i'),
                'quando' => $n->created_at?->toIso8601String(),
                'cliente' => $n->client?->name,
                'meio' => $meios->get('credit_note:' . $n->id) ?: '—',
                'artigos' => $n->items->count(),
                'total' => -round((float) $n->total, 2),
                'anulada' => $anulada,
            ];
        }

        $somaLiquida = array_sum(array_map(fn ($p) => $p['total'] - $p['devolvido'], $produtos));

        $lista = collect($produtos)->map(function (array $p) use ($somaLiquida) {
            $liquido = round($p['total'] - $p['devolvido'], 2);

            return [
                'chave' => $p['chave'],
                'nome' => $p['nome'],
                'codigo' => $p['codigo'],
                'quantidade' => round($p['quantidade'], 3),
                'devolvida' => round($p['devolvida'], 3),
                'liquida' => round($p['quantidade'] - $p['devolvida'], 3),
                'preco_medio' => $p['quantidade'] > 0 ? round($p['total'] / $p['quantidade'], 2) : 0.0,
                'total' => round($p['total'], 2),
                'devolvido' => round($p['devolvido'], 2),
                'liquido' => $liquido,
                'documentos' => count($p['documentos']),
                'peso' => $somaLiquida > 0 ? round($liquido / $somaLiquida * 100, 1) : 0.0,
            ];
        })->sortByDesc('liquido')->values()->all();

        usort($documentos, fn ($a, $b) => strcmp((string) $a['quando'], (string) $b['quando']));

        $liquido = round($bruto - $descontos - $devolvido, 2);

        return [
            'produtos' => $lista,
            'totais' => [
                'artigos' => count($lista),
                'quantidade' => round(array_sum(array_column($lista, 'liquida')), 3),
                'bruto' => round($bruto, 2),
                'descontos' => round($descontos, 2),
                'devolvido' => round($devolvido, 2),
                'liquido' => $liquido,
                'imposto' => round($imposto, 2),
                'facturas' => $validas,
                'anuladas' => $anuladas,
                'notas' => $validasNotas,
                'ticket_medio' => $validas > 0 ? round(($bruto - $descontos) / $validas, 2) : 0.0,
            ],
            'documentos' => array_map(fn ($d) => array_diff_key($d, ['quando' => true]), $documentos),
        ];
    }
}
