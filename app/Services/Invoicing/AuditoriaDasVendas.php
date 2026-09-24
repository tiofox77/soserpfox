<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\SalesInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A AUDITORIA DAS VENDAS E DO STOCK DE UMA EMPRESA NUM PERÍODO. Só lê.
 *
 * Nasceu das vendas em dobro da Luk Simões (23/09/2026): uma FR duplicada por
 * um duplo clique, o stock que desceu uma vez por duas vendas, e a cadeia de
 * assinaturas bifurcada. Cada secção procura uma dessas marcas, e as que se
 * lhes parecem:
 *
 *  · vendas repetidas (a mesma venda com poucos segundos de diferença);
 *  · o dinheiro: pago ≠ total, entregue < total, venda do balcão sem turno
 *    ou sem tesouraria, turno que não soma o total;
 *  · a AGT e a cadeia de assinaturas; buracos e repetidos na numeração;
 *  · o stock: linhas vendidas sem saída, a cadeia dos saldos partida (o
 *    «antes» de um movimento tem de ser o «depois» do anterior), stock
 *    negativo, e o stock de agora que já não é o do último movimento.
 *
 * Diz o que encontra e onde; não corrige nada.
 */
final class AuditoriaDasVendas
{
    private const MOSTRAR = 40;

    private Command $saida;

    private string $de;

    private string $ate;

    public function __construct(private int $tenantId, string $de, string $ate)
    {
        $this->de = $de . ' 00:00:00';
        $this->ate = $ate . ' 23:59:59';
    }

    public function relatar(Command $saida): int
    {
        $this->saida = $saida;

        $empresa = DB::table('tenants')->where('id', $this->tenantId)->value('name');
        if (! $empresa) {
            $saida->error('Empresa não encontrada: --tenant=' . $this->tenantId);

            return Command::FAILURE;
        }

        $saida->newLine();
        $saida->info(" AUDITORIA #{$this->tenantId} {$empresa} — de {$this->de} a {$this->ate}");

        $facturas = DB::table('invoicing_sales_invoices')
            ->where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$this->de, $this->ate])
            ->orderBy('id')
            ->get();

        $problemas = 0;
        $problemas += $this->resumo($facturas);
        $problemas += $this->repetidas($facturas);
        $problemas += $this->dinheiro($facturas);
        $problemas += $this->agt($facturas);
        $problemas += $this->cadeia($facturas);
        $problemas += $this->numeracao($facturas);
        $problemas += $this->stockDasVendas($facturas);
        $problemas += $this->cadeiaDosSaldos();
        $problemas += $this->notasDeCredito();

        $saida->newLine();
        $problemas === 0
            ? $saida->info(' ✓ Nada de errado no período.')
            : $saida->warn(" ⚠ {$problemas} ponto(s) a ver — detalhe acima.");

        return Command::SUCCESS;
    }

    /* ─── Secções ─────────────────────────────────────────────────────── */

    private function resumo(Collection $facturas): int
    {
        $this->titulo('VENDAS');
        $validas = $facturas->whereNotIn('status', ['cancelled', 'draft']);

        $this->linha(sprintf('%d documentos (%d válidos), total válido %s Kz', $facturas->count(), $validas->count(), $this->kz($validas->sum('total'))));

        foreach ($facturas->groupBy(fn ($f) => ($f->invoice_type ?: '?') . ' · ' . $f->status) as $grupo => $lista) {
            $this->linha(sprintf('   %-24s %5d  %16s Kz', $grupo, $lista->count(), $this->kz($lista->sum('total'))));
        }

        $nomes = DB::table('users')->whereIn('id', $facturas->pluck('created_by')->filter()->unique())->pluck('name', 'id');
        foreach ($validas->groupBy('created_by') as $quem => $lista) {
            $this->linha(sprintf('   operador #%s %-22s %5d  %16s Kz', $quem, mb_strimwidth((string) ($nomes[$quem] ?? '?'), 0, 22), $lista->count(), $this->kz($lista->sum('total'))));
        }

        return 0;
    }

    /** A mesma venda duas vezes: mesmo operador, cliente, total e linhas, a menos de 30 s. */
    private function repetidas(Collection $facturas): int
    {
        $this->titulo('VENDAS REPETIDAS (mesmo operador, cliente, total e linhas, a menos de 30 s)');

        $linhas = $this->linhasDe($facturas->pluck('id'));
        $assinatura = fn ($f) => implode('|', [$f->created_by, $f->client_id, number_format((float) $f->total, 2, '.', ''),
            $linhas->get($f->id, collect())->map(fn ($l) => $l->product_id . 'x' . (float) $l->quantity . '@' . (float) $l->unit_price)->sort()->implode(',')]);

        $pares = [];
        $validas = $facturas->whereNotIn('status', ['cancelled', 'draft'])->values();

        foreach ($validas->groupBy($assinatura) as $grupo) {
            $grupo = $grupo->sortBy('created_at')->values();
            for ($i = 1; $i < $grupo->count(); $i++) {
                $segundos = strtotime($grupo[$i]->created_at) - strtotime($grupo[$i - 1]->created_at);
                if ($segundos <= 30) {
                    $pares[] = [$grupo[$i - 1], $grupo[$i], $segundos];
                }
            }
        }

        foreach (array_slice($pares, 0, self::MOSTRAR) as [$a, $b, $s]) {
            $this->linha(sprintf('   %s (%s) e %s (%s): %d s · %s Kz · operador #%s',
                $a->invoice_number, substr((string) $a->created_at, 11), $b->invoice_number, substr((string) $b->created_at, 11), $s, $this->kz($b->total), $b->created_by));
        }

        return $this->fim(count($pares));
    }

    private function dinheiro(Collection $facturas): int
    {
        $this->titulo('DINHEIRO (pago, entregue, turno e tesouraria)');
        $validas = $facturas->whereNotIn('status', ['cancelled', 'draft']);
        $problemas = 0;

        // Uma factura-recibo nasce paga pelo total: nem a mais (o troco é do entregue), nem a menos.
        $fr = $validas->where('invoice_type', 'FR');
        $mal = $fr->filter(fn ($f) => abs((float) $f->paid_amount - (float) $f->total) > 0.01);
        $problemas += $this->lista($mal, 'FR com o pago diferente do total', fn ($f) => "{$f->invoice_number}: pago {$this->kz($f->paid_amount)} · total {$this->kz($f->total)}");

        $curto = $fr->filter(fn ($f) => $f->amount_received !== null && (float) $f->amount_received + 0.01 < (float) $f->total);
        $problemas += $this->lista($curto, 'FR com o entregue abaixo do total', fn ($f) => "{$f->invoice_number}: entregue {$this->kz($f->amount_received)} · total {$this->kz($f->total)}");

        // As do balcão (source_billing P): entram no turno e na tesouraria.
        $balcao = $validas->where('source_billing', 'P');
        $ids = $balcao->pluck('id');

        $noTurno = DB::table('invoicing_pos_shift_transactions')
            ->where('reference_type', SalesInvoice::class)->whereIn('reference_id', $ids)
            ->selectRaw('reference_id, SUM(amount) soma, COUNT(*) n')->groupBy('reference_id')->get()->keyBy('reference_id');

        $semTurno = $balcao->filter(fn ($f) => ! isset($noTurno[$f->id]));
        $problemas += $this->lista($semTurno, 'vendas do balcão sem movimento no turno', fn ($f) => "{$f->invoice_number} · {$this->kz($f->total)} Kz · às " . substr((string) $f->created_at, 11));

        $turnoErrado = $balcao->filter(fn ($f) => isset($noTurno[$f->id]) && abs((float) $noTurno[$f->id]->soma - (float) $f->total) > 0.01);
        $problemas += $this->lista($turnoErrado, 'vendas cujo turno não soma o total', fn ($f) => "{$f->invoice_number}: turno {$this->kz($noTurno[$f->id]->soma)} ({$noTurno[$f->id]->n} mov.) · total {$this->kz($f->total)}");

        $naTesouraria = DB::table('treasury_transactions')
            ->where('tenant_id', $this->tenantId)
            ->where(fn ($q) => $q->whereIn('invoice_id', $ids)
                ->orWhere(fn ($q) => $q->where('related_type', SalesInvoice::class)->whereIn('related_id', $ids)))
            ->get(['invoice_id', 'related_id', 'related_type', 'amount', 'type']);
        $comTesouraria = $naTesouraria->map(fn ($t) => $t->invoice_id ?: $t->related_id)->filter()->unique()->flip();

        $semTesouraria = $balcao->filter(fn ($f) => ! isset($comTesouraria[$f->id]));
        $problemas += $this->lista($semTesouraria, 'vendas do balcão sem movimento na tesouraria', fn ($f) => "{$f->invoice_number} · {$this->kz($f->total)} Kz · {$f->payment_method}");

        if ($problemas === 0) {
            $this->linha('   ✓ pagas pelo total, no turno e na tesouraria');
        }

        return $problemas;
    }

    private function agt(Collection $facturas): int
    {
        $this->titulo('AGT');
        foreach ($facturas->groupBy(fn ($f) => $f->agt_status ?: 'sem estado') as $estado => $lista) {
            $this->linha(sprintf('   %-14s %5d', $estado, $lista->count()));
        }

        $recusadas = $facturas->where('agt_status', 'rejected');
        $erros = DB::table('agt_submissions')->where('document_type', SalesInvoice::class)->whereIn('document_id', $recusadas->pluck('id'))
            ->orderByDesc('id')->get(['document_id', 'error_code', 'error_message'])->unique('document_id')->keyBy('document_id');

        return $this->lista($recusadas, 'recusadas pela AGT', fn ($f) => $f->invoice_number . ': ' . mb_strimwidth((string) ($erros[$f->id]->error_message ?? $erros[$f->id]->error_code ?? '?'), 0, 90, '…'));
    }

    /** Cada documento assina-se com o anterior da empresa (por id): o `hash_previous` tem de ser o `saft_hash` dele. */
    private function cadeia(Collection $facturas): int
    {
        $this->titulo('CADEIA DE ASSINATURAS');
        $assinadas = $facturas->filter(fn ($f) => ! empty($f->saft_hash))->values();

        if ($assinadas->isEmpty()) {
            $this->linha('   (nenhum documento assinado no período)');

            return 0;
        }

        $anterior = DB::table('invoicing_sales_invoices')->where('tenant_id', $this->tenantId)
            ->where('id', '<', $assinadas->first()->id)->whereNotNull('saft_hash')->orderByDesc('id')->value('saft_hash');

        // Os assinados da empresa no período, por id, incluindo os que não foram criados nele mas estão entre eles.
        $todas = DB::table('invoicing_sales_invoices')->where('tenant_id', $this->tenantId)
            ->whereBetween('id', [$assinadas->first()->id, $assinadas->last()->id])->whereNotNull('saft_hash')
            ->orderBy('id')->get(['id', 'invoice_number', 'saft_hash', 'hash_previous', 'created_at']);

        $partidas = [];
        foreach ($todas as $f) {
            if ((string) $f->hash_previous !== (string) $anterior) {
                $partidas[] = $f;
            }
            $anterior = $f->saft_hash;
        }

        $this->linha(sprintf('   %d assinados no período', $todas->count()));

        return $this->lista(collect($partidas), 'ligações que não batem com o anterior', fn ($f) => "{$f->invoice_number} às " . substr((string) $f->created_at, 11));
    }

    private function numeracao(Collection $facturas): int
    {
        $this->titulo('NUMERAÇÃO (por série)');
        $problemas = 0;

        foreach ($facturas->groupBy('series_id') as $serie => $lista) {
            $numeros = $lista->map(fn ($f) => preg_match('~/(\d+)\s*$~', (string) $f->invoice_number, $m) ? (int) $m[1] : null)->filter()->sort()->values();
            if ($numeros->isEmpty()) {
                continue;
            }

            $repetidos = $numeros->duplicates()->unique()->values();
            $buracos = [];
            for ($i = 1; $i < $numeros->count(); $i++) {
                if ($numeros[$i] - $numeros[$i - 1] > 1) {
                    $buracos[] = ($numeros[$i - 1] + 1) . ($numeros[$i] - $numeros[$i - 1] > 2 ? '–' . ($numeros[$i] - 1) : '');
                }
            }

            $prefixo = preg_replace('~/\d+\s*$~', '', (string) $lista->first()->invoice_number);
            $this->linha(sprintf('   %-24s %d documentos, de %d a %d', $prefixo, $numeros->count(), $numeros->first(), $numeros->last()));

            if ($repetidos->isNotEmpty()) {
                $this->linha('      ⚠ números repetidos: ' . $repetidos->implode(', '));
                $problemas += $repetidos->count();
            }
            if ($buracos) {
                // Um buraco pode ser de outra empresa? Não: a série é da empresa. Pode ser um rascunho apagado.
                $this->linha('      ⚠ números em falta: ' . implode(', ', array_slice($buracos, 0, 20)));
                $problemas += count($buracos);
            }
        }

        return $problemas;
    }

    /** Cada linha vendida de um artigo que gere stock tem de ter a sua saída. */
    private function stockDasVendas(Collection $facturas): int
    {
        $this->titulo('STOCK DAS VENDAS (linhas sem saída de stock)');
        $validas = $facturas->whereNotIn('status', ['cancelled', 'draft']);
        $linhas = $this->linhasDe($validas->pluck('id'));

        $geridos = DB::table('invoicing_products')->whereIn('id', $linhas->flatten()->pluck('product_id')->filter()->unique())
            ->where('manage_stock', true)->where('type', '!=', 'servico')->pluck('name', 'id');

        $saidas = DB::table('invoicing_stock_movements')->where('tenant_id', $this->tenantId)
            ->where('reference_type', SalesInvoice::class)->whereIn('reference_id', $validas->pluck('id'))
            ->where('type', 'out')->get(['reference_id', 'product_id', 'quantity'])
            ->groupBy(fn ($m) => $m->reference_id . ':' . $m->product_id);

        $numeros = $validas->pluck('invoice_number', 'id');
        $faltam = [];
        $aMais = [];

        foreach ($linhas as $factura => $itens) {
            foreach ($itens->groupBy('product_id') as $produto => $doArtigo) {
                if (! isset($geridos[$produto])) {
                    continue;
                }
                $vendido = $doArtigo->sum(fn ($l) => (float) $l->quantity);
                $saiu = ($saidas[$factura . ':' . $produto] ?? collect())->sum(fn ($m) => (float) $m->quantity);

                if ($saiu + 0.0001 < $vendido) {
                    $faltam[] = "{$numeros[$factura]}: {$geridos[$produto]} — vendido " . (float) $vendido . ', saiu ' . (float) $saiu;
                } elseif ($saiu > $vendido + 0.0001) {
                    $aMais[] = "{$numeros[$factura]}: {$geridos[$produto]} — vendido " . (float) $vendido . ', saiu ' . (float) $saiu;
                }
            }
        }

        $p = $this->lista(collect($faltam), 'linhas vendidas sem (toda a) saída de stock', fn ($t) => $t);
        $p += $this->lista(collect($aMais), 'saídas a mais do que o vendido', fn ($t) => $t);

        if ($p === 0) {
            $this->linha('   ✓ cada linha vendida tem a sua saída');
        }

        return $p;
    }

    /**
     * A CADEIA DOS SALDOS: o «antes» de cada movimento tem de ser o «depois» do
     * anterior do mesmo artigo e armazém. Uma quebra é uma baixa perdida (duas
     * vendas que leram o mesmo saldo) ou o stock mudado sem movimento.
     */
    private function cadeiaDosSaldos(): int
    {
        $this->titulo('CADEIA DOS SALDOS DO STOCK (movimentos do período)');

        $movimentos = DB::table('invoicing_stock_movements')->where('tenant_id', $this->tenantId)
            ->whereBetween('created_at', [$this->de, $this->ate])
            ->whereNotNull('balance_before')->whereNotNull('balance_after')
            ->orderBy('id')->get(['id', 'product_id', 'warehouse_id', 'type', 'quantity', 'balance_before', 'balance_after', 'reference_type', 'reference_id', 'created_at']);

        if ($movimentos->isEmpty()) {
            $this->linha('   (sem movimentos com saldo no período)');

            return 0;
        }

        $nomes = DB::table('invoicing_products')->whereIn('id', $movimentos->pluck('product_id')->unique())->pluck('name', 'id');
        $quebras = [];

        foreach ($movimentos->groupBy(fn ($m) => $m->product_id . ':' . $m->warehouse_id) as $lista) {
            $primeiro = $lista->first();
            $antes = DB::table('invoicing_stock_movements')->where('tenant_id', $this->tenantId)
                ->where('product_id', $primeiro->product_id)->where('warehouse_id', $primeiro->warehouse_id)
                ->where('id', '<', $primeiro->id)->whereNotNull('balance_after')->orderByDesc('id')->value('balance_after');

            foreach ($lista as $m) {
                // Os ajustes (contagens) fixam o saldo: não se lhes pede continuidade.
                if ($antes !== null && ! in_array($m->type, ['adjustment', 'ajuste'], true)
                    && abs((float) $m->balance_before - (float) $antes) > 0.0001) {
                    $quebras[] = sprintf('%s (armazém %s): %s às %s esperava %s, leu %s → %s (%s %s)',
                        mb_strimwidth((string) ($nomes[$m->product_id] ?? '#' . $m->product_id), 0, 34, '…'), $m->warehouse_id,
                        $m->type, substr((string) $m->created_at, 5, 11), (float) $antes, (float) $m->balance_before, (float) $m->balance_after,
                        class_basename((string) $m->reference_type), $m->reference_id);
                }
                $antes = $m->balance_after;
            }
        }

        $this->linha(sprintf('   %d movimentos, %d artigos', $movimentos->count(), $movimentos->pluck('product_id')->unique()->count()));
        $p = $this->lista(collect($quebras), 'quebras na cadeia dos saldos', fn ($t) => $t);

        // O stock de agora e o do último movimento: diferentes quer dizer que mudou sem movimento depois.
        $ultimos = $movimentos->groupBy(fn ($m) => $m->product_id . ':' . $m->warehouse_id)->map->last();
        $agora = DB::table('invoicing_stocks')->where('tenant_id', $this->tenantId)
            ->whereIn('product_id', $ultimos->pluck('product_id')->unique())->get(['product_id', 'warehouse_id', 'quantity'])
            ->keyBy(fn ($s) => $s->product_id . ':' . $s->warehouse_id);

        $deriva = [];
        $negativos = [];
        foreach ($ultimos as $chave => $m) {
            $linha = $agora[$chave] ?? null;
            if (! $linha) {
                continue;
            }
            $depoisDoUltimo = DB::table('invoicing_stock_movements')->where('tenant_id', $this->tenantId)
                ->where('product_id', $m->product_id)->where('warehouse_id', $m->warehouse_id)->where('id', '>', $m->id)->exists();
            if (! $depoisDoUltimo && abs((float) $linha->quantity - (float) $m->balance_after) > 0.0001) {
                $deriva[] = sprintf('%s (armazém %s): último movimento deixou %s, o stock diz %s',
                    mb_strimwidth((string) ($nomes[$m->product_id] ?? '#' . $m->product_id), 0, 40, '…'), $m->warehouse_id, (float) $m->balance_after, (float) $linha->quantity);
            }
            if ((float) $linha->quantity < 0) {
                $negativos[] = sprintf('%s (armazém %s): %s', mb_strimwidth((string) ($nomes[$m->product_id] ?? '#' . $m->product_id), 0, 40, '…'), $m->warehouse_id, (float) $linha->quantity);
            }
        }

        $p += $this->lista(collect($deriva), 'stock diferente do último movimento (mudou sem movimento)', fn ($t) => $t);
        $p += $this->lista(collect($negativos), 'stock negativo agora', fn ($t) => $t);

        if ($p === 0) {
            $this->linha('   ✓ saldos encadeados, sem deriva e sem negativos');
        }

        return $p;
    }

    private function notasDeCredito(): int
    {
        $this->titulo('NOTAS DE CRÉDITO');
        $notas = DB::table('invoicing_credit_notes')->where('tenant_id', $this->tenantId)->whereNull('deleted_at')
            ->whereBetween('created_at', [$this->de, $this->ate])->orderBy('id')
            ->get(['id', 'credit_note_number', 'invoice_id', 'status', 'total', 'created_at']);

        $origens = DB::table('invoicing_sales_invoices')->whereIn('id', $notas->pluck('invoice_id')->filter())->pluck('invoice_number', 'id');
        $this->linha(sprintf('   %d no período, %s Kz', $notas->count(), $this->kz($notas->where('status', '!=', 'cancelled')->sum('total'))));
        foreach ($notas->take(self::MOSTRAR) as $n) {
            $this->linha(sprintf('   %s sobre %s · %s Kz · %s', $n->credit_note_number, $origens[$n->invoice_id] ?? '?', $this->kz($n->total), $n->status));
        }

        return 0;
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function linhasDe(Collection $ids): Collection
    {
        return $ids->isEmpty() ? collect() : DB::table('invoicing_sales_invoice_items')
            ->whereIn('sales_invoice_id', $ids)->get(['sales_invoice_id', 'product_id', 'quantity', 'unit_price'])
            ->groupBy('sales_invoice_id');
    }

    private function lista(Collection $coisas, string $rotulo, callable $texto): int
    {
        if ($coisas->isEmpty()) {
            return 0;
        }

        $this->saida->warn(sprintf('   ⚠ %d %s:', $coisas->count(), $rotulo));
        foreach ($coisas->take(self::MOSTRAR) as $c) {
            $this->linha('      ' . $texto($c));
        }
        if ($coisas->count() > self::MOSTRAR) {
            $this->linha('      … e mais ' . ($coisas->count() - self::MOSTRAR));
        }

        return $coisas->count();
    }

    private function fim(int $n): int
    {
        if ($n === 0) {
            $this->linha('   ✓ nenhuma');
        }

        return $n;
    }

    private function titulo(string $t): void
    {
        $this->saida->newLine();
        $this->saida->line(" ── {$t}");
    }

    private function linha(string $t): void
    {
        $this->saida->line($t);
    }

    private function kz($v): string
    {
        return number_format((float) $v, 2, ',', ' ');
    }
}
