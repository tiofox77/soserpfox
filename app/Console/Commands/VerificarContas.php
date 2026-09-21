<?php

namespace App\Console\Commands;

use App\Models\Invoicing\Advance;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use App\Models\Treasury\Transaction;
use App\Services\Invoicing\LancamentoDeDinheiro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * O QUE FICOU POR ARRUMAR NAS CONTAS DE CADA EMPRESA.
 *
 * A auditoria de 20/09/2026 encontrou dinheiro que se mexia no mundo real e
 * não nos livros. Os defeitos estão corrigidos daqui para a frente; o que já
 * está gravado continua como estava, e é isso que este comando mostra:
 *
 *  1. DOCUMENTOS PAGOS SEM VALOR PAGO — o restaurante, o hotel e o salão
 *     marcavam `status = 'paid'` e deixavam o `paid_amount` a zero. O saldo
 *     dizia que faltava tudo, e o ecrã dos Recibos oferecia-os para serem
 *     cobrados OUTRA VEZ.
 *
 *  2. RECIBOS SEM MOVIMENTO — os emitidos pelo ecrã dos Recibos antes de
 *     19/09 baixavam a dívida e não entravam na tesouraria.
 *
 *  3. ADIANTAMENTOS SEM MOVIMENTO — dinheiro entregue pelo cliente que a
 *     tesouraria nunca viu.
 *
 *  4. DEVOLUÇÕES POR LANÇAR — notas de crédito sobre facturas pagas: o
 *     dinheiro voltou ao cliente e o saldo da caixa nunca desceu.
 *
 *  5. MOVIMENTOS ÓRFÃOS — de recibos apagados, que deixavam o dinheiro na
 *     tesouraria.
 *
 * SÓ LÊ. Com `--aplicar` escreve — e mesmo assim NUNCA toca em turnos: um
 * turno fechado é uma contagem assinada por alguém, e não se reescreve uma
 * contagem meses depois. Os movimentos entram com a DATA DO DOCUMENTO, o que
 * significa que mexem em mapas de dias já passados: correr isto em produção é
 * uma decisão de quem conhece a casa.
 */
class VerificarContas extends Command
{
    protected $signature = 'contas:verificar
                            {--tenant= : Só esta empresa (id)}
                            {--aplicar : Corrige o que encontrar (por omissão só simula)}';

    protected $description = 'Dinheiro que ficou fora dos livros: documentos pagos sem valor pago, recibos, adiantamentos e devoluções sem movimento (só lê)';

    private bool $aplicar = false;

    public function handle(): int
    {
        $this->aplicar = (bool) $this->option('aplicar');

        if (! $this->aplicar) {
            $this->line('<comment>SIMULAÇÃO</comment> — nada será gravado. Para corrigir: --aplicar');
            $this->newLine();
        }

        $empresas = Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->whereKey((int) $this->option('tenant')))
            ->orderBy('id')->get();

        $totais = ['pagos' => 0, 'recibos' => 0, 'adiantamentos' => 0, 'devolucoes' => 0, 'orfaos' => 0];

        foreach ($empresas as $empresa) {
            foreach ($this->conferirEmpresa($empresa) as $chave => $quantos) {
                $totais[$chave] += $quantos;
            }
        }

        $this->newLine();

        if (array_sum($totais) === 0) {
            $this->info('Nada a arrumar nas empresas conferidas.');

            return self::SUCCESS;
        }

        $this->line('<options=bold>' . ($this->aplicar ? 'Corrigido' : 'Por corrigir') . '</>');
        $this->table(['O quê', 'Quantos'], [
            ['Documentos pagos sem valor pago', $totais['pagos']],
            ['Recibos sem movimento de tesouraria', $totais['recibos']],
            ['Adiantamentos sem movimento', $totais['adiantamentos']],
            ['Devoluções por lançar', $totais['devolucoes']],
            ['Movimentos de recibos apagados', $totais['orfaos']],
        ]);

        if (! $this->aplicar) {
            $this->warn('Correr com --aplicar lança movimentos com datas PASSADAS. Turnos já fechados não são tocados.');
        }

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function conferirEmpresa(Tenant $empresa): array
    {
        $r = [
            'pagos' => $this->documentosPagosSemValor($empresa),
            'recibos' => $this->recibosSemMovimento($empresa),
            'adiantamentos' => $this->adiantamentosSemMovimento($empresa),
            'devolucoes' => $this->devolucoesPorLancar($empresa),
            'orfaos' => $this->movimentosOrfaos($empresa),
        ];

        if (array_sum($r) > 0) {
            $this->line(sprintf(
                '  #%d %-32s pagos:%d recibos:%d adiant:%d devol:%d órfãos:%d',
                $empresa->id, mb_strimwidth((string) $empresa->name, 0, 32, '…'),
                $r['pagos'], $r['recibos'], $r['adiantamentos'], $r['devolucoes'], $r['orfaos'],
            ));
        }

        return $r;
    }

    /**
     * Marcado como pago e com zero recebido. É inequívoco: quem recebeu por
     * recibo tem o `paid_amount` preenchido pelos ganchos do `Receipt`.
     */
    private function documentosPagosSemValor(Tenant $empresa): int
    {
        $query = SalesInvoice::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->where('status', 'paid')
            ->where('total', '>', 0)
            ->where(fn ($q) => $q->whereNull('paid_amount')->orWhere('paid_amount', '<=', 0));

        $quantos = (clone $query)->count();

        if ($quantos > 0 && $this->aplicar) {
            $query->update(['paid_amount' => DB::raw('total')]);
        }

        return $quantos;
    }

    private function recibosSemMovimento(Tenant $empresa): int
    {
        $recibos = Receipt::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->where('amount_paid', '>', 0)
            ->whereNotIn('status', ['cancelled'])
            ->whereNotExists($this->semMovimento(Receipt::class))
            ->get();

        if ($this->aplicar) {
            $porta = app(\App\Services\Invoicing\LancamentoDoRecibo::class);

            foreach ($recibos as $recibo) {
                // Sem turno: o de então já fechou, e uma contagem assinada não
                // se reescreve. Só a tesouraria.
                $porta->lancar($recibo, [], $recibo->created_by, comTurno: false);
            }
        }

        return $recibos->count();
    }

    private function adiantamentosSemMovimento(Tenant $empresa): int
    {
        $adiantamentos = Advance::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->where('amount', '>', 0)
            ->whereNotExists($this->semMovimento(Advance::class))
            ->get();

        if ($this->aplicar) {
            $dinheiro = app(LancamentoDeDinheiro::class);

            foreach ($adiantamentos as $a) {
                $numero = $a->advance_number ?: ('#' . $a->id);

                $dinheiro->lancar($a, [
                    'valor' => (float) $a->amount,
                    'forma' => (string) $a->payment_method,
                    'sentido' => 'income',
                    'categoria' => 'customer_payment',
                    'data' => $a->payment_date,
                    'referencia' => 'Adiantamento ' . $numero,
                    'descricao' => 'Adiantamento ' . $numero . ' (reposto)',
                ], $a->created_by);
            }
        }

        return $adiantamentos->count();
    }

    /**
     * Notas de crédito sobre facturas que foram pagas: o dinheiro voltou ao
     * cliente e a tesouraria não sabe. Devolve-se o que a factura recebeu e
     * nada mais — anular uma factura por pagar não move um cêntimo.
     */
    private function devolucoesPorLancar(Tenant $empresa): int
    {
        $notas = CreditNote::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNotNull('invoice_id')
            ->whereNotExists($this->semMovimento(CreditNote::class))
            ->with('invoice')
            ->get()
            ->filter(fn ($n) => (float) ($n->invoice->paid_amount ?? 0) > 0);

        if ($this->aplicar) {
            $dinheiro = app(LancamentoDeDinheiro::class);

            foreach ($notas as $nota) {
                $jaDevolvido = (float) Transaction::withoutGlobalScopes()
                    ->where('tenant_id', $empresa->id)
                    ->where('related_type', CreditNote::class)
                    ->where('invoice_id', $nota->invoice_id)->sum('amount');

                $valor = round(max(0, min(
                    (float) $nota->total,
                    (float) $nota->invoice->paid_amount - $jaDevolvido,
                )), 2);

                if ($valor <= 0) {
                    continue;
                }

                $numero = $nota->credit_note_number ?: ('#' . $nota->id);

                $dinheiro->lancar($nota, [
                    'valor' => $valor,
                    'forma' => (string) ($nota->invoice->payment_method ?: 'other'),
                    'sentido' => 'expense',
                    'categoria' => 'credit_note',
                    'data' => $nota->issue_date,
                    'invoice_id' => $nota->invoice_id,
                    'referencia' => $numero,
                    'descricao' => 'Devolução ' . $numero . ' (reposta)',
                ], $nota->created_by);
            }
        }

        return $notas->count();
    }

    /**
     * Movimentos de recibos que já não existem. O recibo apaga-se em soft
     * delete, por isso ainda se sabe qual era.
     */
    private function movimentosOrfaos(Tenant $empresa): int
    {
        $orfaos = Transaction::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->where('related_type', Receipt::class)
            ->whereExists(fn ($q) => $q->selectRaw(1)->from('invoicing_receipts')
                ->whereColumn('invoicing_receipts.id', 'treasury_transactions.related_id')
                ->whereNotNull('invoicing_receipts.deleted_at'))
            ->get();

        if ($this->aplicar) {
            $tesouraria = app(\App\Services\Treasury\TreasuryMovementService::class);

            foreach ($orfaos as $m) {
                DB::transaction(function () use ($tesouraria, $m) {
                    if (($m->status ?? 'completed') === 'completed') {
                        $tesouraria->apply($m, -1);
                    }
                    $m->delete();
                });
            }
        }

        return $orfaos->count();
    }

    /** O subquery «não tem movimento de tesouraria». */
    private function semMovimento(string $modelo): \Closure
    {
        return fn ($q) => $q->selectRaw(1)->from('treasury_transactions')
            ->whereColumn('treasury_transactions.related_id', DB::raw($this->tabelaDe($modelo) . '.id'))
            ->where('treasury_transactions.related_type', $modelo);
    }

    private function tabelaDe(string $modelo): string
    {
        return (new $modelo())->getTable();
    }

}
