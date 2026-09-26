<?php

namespace App\Services\POS;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
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
 *
 * OS DOCUMENTOS DO ECRÃ «DOCUMENTOS» (26/09/2026), quando a empresa os quer no
 * fecho (App\Services\POS\DocumentosNoTurno): a FR A4 entra como qualquer
 * venda; a FT, a ND e a NC sem devolução entram como A PRAZO — na lista e nos
 * artigos, com o total à parte em `totais.a_prazo`, porque não passaram pela
 * gaveta.
 */
class ProdutosDoTurno
{
    private const ANULADAS = ['cancelled', 'draft'];

    /**
     * @return array{
     *   produtos: list<array{chave:string, nome:string, codigo:?string, quantidade:float, devolvida:float, liquida:float, preco_medio:float, total:float, devolvido:float, liquido:float, documentos:int, peso:float}>,
     *   totais: array{artigos:int, quantidade:float, bruto:float, descontos:float, devolvido:float, liquido:float, imposto:float, facturas:int, anuladas:int, notas:int, ticket_medio:float, a_prazo:float, documentos_a_prazo:int, debitos:int},
     *   documentos: list<array{tipo:string, numero:string, hora:?string, cliente:?string, meio:string, artigos:int, total:float, anulada:bool, a_prazo:bool}>,
     * }
     */
    public static function de(PosShift $turno): array
    {
        $movimentos = $turno->relationLoaded('transactions') ? $turno->transactions : $turno->transactions()->get();

        $aPrazo = $movimentos->where('type', 'a_prazo');
        $ids = fn (Collection $ms, string $classe) => $ms->where('reference_type', $classe)->pluck('reference_id')->filter()->unique()->values();

        $idsFacturas = $ids($movimentos->where('type', 'invoice'), SalesInvoice::class)->merge($ids($aPrazo, SalesInvoice::class))->unique()->values();
        $idsNotas = $movimentos->where('type', 'credit_note')->pluck('reference_id')->filter()->merge($ids($aPrazo, CreditNote::class))->unique()->values();
        $idsDebito = $ids($aPrazo, DebitNote::class);

        // Quais entraram A PRAZO, por documento: não passaram pela gaveta.
        $saoAPrazo = $aPrazo->map(fn ($m) => class_basename((string) $m->reference_type) . ':' . $m->reference_id)->flip();

        // O meio de cada documento sai dos movimentos DESTE turno: numa venda
        // dividida são dois, e a factura só diz «multiple». A chave é o
        // DOCUMENTO (classe e id), porque uma factura e uma nota podem ter o
        // mesmo id.
        $meios = $movimentos->groupBy(fn ($m) => class_basename((string) $m->reference_type) . ':' . $m->reference_id)
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

        $debitos = $idsDebito->isEmpty() ? collect() : DebitNote::withoutGlobalScope('tenant')
            ->where('tenant_id', $turno->tenant_id)
            ->whereIn('id', $idsDebito)
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
                // O desconto que NÃO está nas linhas: no balcão é o desconto
                // todo; a factura A4 já o reparte pelas linhas, e somar o
                // `discount_amount` dela contava-o duas vezes.
                $descontos += max(0.0, (float) $f->discount_amount - (float) $f->items->sum('discount_amount'));
            }

            $documentos[] = [
                'tipo' => 'factura',
                // A série INTERNA em cima e a da AGT por baixo, como nas listas.
                'numero' => $f->numeroInterno(),
                'numero_agt' => $f->numeroInterno() !== (string) $f->invoice_number ? (string) $f->invoice_number : null,
                'hora' => optional($f->created_at)->format('H:i'),
                'quando' => $f->created_at?->toIso8601String(),
                'cliente' => $f->client?->name,
                'meio' => $meios->get('SalesInvoice:' . $f->id) ?: posPaymentMethodLabel($f->payment_method),
                'artigos' => $f->items->count(),
                'total' => round((float) $f->total, 2),
                'anulada' => $anulada,
                'a_prazo' => $saoAPrazo->has('SalesInvoice:' . $f->id),
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
                'meio' => $meios->get('CreditNote:' . $n->id) ?: '—',
                'artigos' => $n->items->count(),
                'total' => -round((float) $n->total, 2),
                'anulada' => $anulada,
                'a_prazo' => $saoAPrazo->has('CreditNote:' . $n->id),
            ];
        }

        // As notas de débito (sempre a prazo): acrescentam ao que se facturou.
        $validasDebito = 0;

        foreach ($debitos as $d) {
            $anulada = in_array($d->status, self::ANULADAS, true);

            if (! $anulada) {
                $validasDebito++;
                foreach ($d->items as $item) {
                    $k = $linha($item, null);
                    $produtos[$k]['quantidade'] += (float) $item->quantity;
                    $produtos[$k]['total'] += (float) $item->total;
                    $produtos[$k]['documentos']['nd' . $d->id] = true;
                    $bruto += (float) $item->total;
                    $imposto += (float) $item->tax_amount;
                }
            }

            $documentos[] = [
                'tipo' => 'debito',
                'numero' => $d->numeroInterno(),
                'numero_agt' => $d->numeroInterno() !== (string) $d->debit_note_number ? (string) $d->debit_note_number : null,
                'hora' => optional($d->created_at)->format('H:i'),
                'quando' => $d->created_at?->toIso8601String(),
                'cliente' => $d->client?->name,
                'meio' => $meios->get('DebitNote:' . $d->id) ?: __('A prazo'),
                'artigos' => $d->items->count(),
                'total' => round((float) $d->total, 2),
                'anulada' => $anulada,
                'a_prazo' => true,
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
                // O que se emitiu a prazo pelos Documentos: está no líquido,
                // mas não na gaveta.
                'a_prazo' => round((float) $aPrazo->sum('amount'), 2),
                'documentos_a_prazo' => $aPrazo->count(),
                'debitos' => $validasDebito,
            ],
            'documentos' => array_map(fn ($d) => array_diff_key($d, ['quando' => true]), $documentos),
        ];
    }
}
