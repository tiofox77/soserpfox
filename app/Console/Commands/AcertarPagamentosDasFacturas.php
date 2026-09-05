<?php

namespace App\Console\Commands;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Põe o «já pago» das facturas a par dos recibos que existem.
 *
 * PORQUE EXISTE. Havia dois caminhos para receber e cada um fazia a sua conta:
 * o modal da factura somava ao `paid_amount` e criava o recibo; o ecrã dos
 * recibos criava o recibo e não tocava na factura. A factura ficava a pedir o
 * valor todo outra vez, o painel continuava a contar a dívida, e o recibo
 * seguinte voltava a propor o total inteiro.
 *
 * O defeito está corrigido — os recibos passaram a manter a factura a par, e a
 * conta vive num sítio só (`SalesInvoice::recalcularPago`). Isto endireita o
 * que ficou para trás.
 *
 * Só lê e conta. Escreve com `--aplicar`, e mostra sempre o que vai mudar.
 */
class AcertarPagamentosDasFacturas extends Command
{
    protected $signature = 'facturas:acertar-pagos
                            {--tenant= : só esta empresa (id)}
                            {--limite=40 : quantas linhas mostrar}
                            {--aplicar : escreve de facto}';

    protected $description = 'Acerta o pago e o estado das facturas pelo que os recibos e adiantamentos dizem';

    public function handle(): int
    {
        $empresas = Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->where('id', (int) $this->option('tenant')))
            ->orderBy('id')
            ->get(['id', 'name']);

        $totalFora = 0;
        $totalDinheiro = 0.0;
        $mostradas = 0;
        $limite = (int) $this->option('limite');

        foreach ($empresas as $empresa) {
            $facturas = $this->facturasForaDeSitio($empresa->id);

            if ($facturas->isEmpty()) {
                continue;
            }

            $this->newLine();
            $this->line('<fg=cyan>#' . $empresa->id . ' ' . $empresa->name . '</> — ' . $facturas->count() . ' factura(s) com recibos por registar');

            $linhas = [];

            foreach ($facturas as $f) {
                $totalFora++;
                $totalDinheiro += abs($f->certo - (float) $f->paid_amount);

                if ($mostradas < $limite) {
                    $mostradas++;
                    $linhas[] = [
                        $f->invoice_number,
                        $this->kz($f->total),
                        $this->kz($f->paid_amount) . '  →  ' . $this->kz($f->certo),
                        $f->status,
                    ];
                }
            }

            if ($linhas) {
                $this->table(['factura', 'total', 'pago (agora → com os recibos)', 'estado'], $linhas);
            }

            if ($this->option('aplicar')) {
                foreach ($facturas as $f) {
                    // A diferença, e só ela: o que os recibos provam que entrou
                    // e a factura não registou.
                    SalesInvoice::withoutGlobalScopes()->find($f->id)
                        ?->aplicarPagamento(round((float) $f->certo - (float) $f->paid_amount, 2));
                }

                $this->info('  ' . $facturas->count() . ' factura(s) acertada(s).');
            }
        }

        $this->newLine();

        if ($totalFora === 0) {
            $this->info('Nada a acertar: não há recibos emitidos por registar em factura nenhuma.');

            return self::SUCCESS;
        }

        $this->warn($totalFora . ' factura(s) com dinheiro recebido por registar, ' . $this->kz($totalDinheiro) . ' ao todo.');

        if (! $this->option('aplicar')) {
            $this->line('A SECO. Corra com --aplicar para acertar.');
        }

        return self::SUCCESS;
    }

    /**
     * As facturas cujo `paid_amount` não bate com recibos + adiantamentos.
     *
     * Uma consulta por empresa, com as duas somas em subconsulta — percorrer
     * factura a factura numa casa com milhares seria uma ida à base por cada.
     */
    private function facturasForaDeSitio(int $tenantId)
    {
        $base = DB::table('invoicing_sales_invoices as f')
            ->leftJoin(DB::raw('(SELECT invoice_id, SUM(amount_paid) AS t
                                 FROM invoicing_receipts
                                 WHERE status = \'issued\' AND deleted_at IS NULL
                                 GROUP BY invoice_id) rec'), 'rec.invoice_id', '=', 'f.id')
            ->leftJoin(DB::raw('(SELECT invoice_id, SUM(amount_used) AS t
                                 FROM invoicing_advance_usages
                                 WHERE invoice_type = \'SalesInvoice\'
                                 GROUP BY invoice_id) adi'), 'adi.invoice_id', '=', 'f.id')
            ->where('f.tenant_id', $tenantId)
            ->whereNull('f.deleted_at')
            ->selectRaw('f.id, f.invoice_number, f.total, COALESCE(f.paid_amount, 0) AS paid_amount, f.status,
                         ROUND(COALESCE(rec.t, 0) + COALESCE(adi.t, 0), 2) AS certo');

        /*
         * O FILTRO POR FORA, e não num HAVING.
         *
         * O MySQL da produção corre com ONLY_FULL_GROUP_BY e recusa um HAVING
         * sobre uma coluna que não está agrupada: «Non-grouping field
         * paid_amount is used in HAVING clause» (1463). Localmente passava —
         * que é a maneira mais fácil de mandar para produção uma consulta que
         * lá não corre.
         *
         * E só as que têm recibos ou adiantamentos: uma factura-recibo do
         * balcão paga-se no acto e nunca tem recibo à parte. Sem esta
         * condição, o acerto punha 1220 facturas já pagas a dever tudo.
         */
        return DB::query()
            ->fromSub($base, 'x')
            ->where('certo', '>', 0)
            // SÓ O QUE FALTA REGISTAR, nunca o que sobra. Uma factura com
            // mais pago do que os recibos explicam tem dinheiro entrado por
            // outro caminho — o balcão, o modal de antes de haver recibos,
            // uma importação. Baixá-la apagava pagamentos verdadeiros.
            ->whereRaw('certo - ROUND(paid_amount, 2) > 0.01')
            ->orderByDesc('id')
            ->get();
    }

    private function kz($v): string
    {
        return number_format((float) $v, 2, ',', '.') . ' Kz';
    }
}
