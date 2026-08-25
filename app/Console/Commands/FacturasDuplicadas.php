<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Facturas de subscrição duplicadas do MESMO período.
 *
 * Nasceram de um defeito na renovação (ver RenovacaoDeSubscricoes): a
 * verificação de "já facturei este período" não reconhecia a factura acabada
 * de emitir, e a varredura corre de hora a hora — saía uma por hora, cada uma
 * com o seu email e SMS ao cliente.
 *
 * Duplicadas são as que partilham subscrição E vencimento (a marca do período).
 *
 * SIMULAÇÃO por omissão: sem --aplicar não grava nada. Isto mexe em documentos
 * financeiros e nunca deve correr às cegas.
 *
 * Guarda-se SEMPRE a mais antiga (a legítima). Nunca se toca numa factura paga
 * ou com pagamentos registados — se a duplicada foi paga, fica para decisão
 * humana e é reportada.
 */
class FacturasDuplicadas extends Command
{
    protected $signature = 'facturas:duplicadas
        {--aplicar : anula mesmo (sem isto e so simulacao)}
        {--apagar : em vez de anular, APAGA as duplicadas}
        {--tenant= : limitar a uma empresa}';

    protected $description = 'Lista (e opcionalmente anula) facturas de subscricao duplicadas do mesmo periodo';

    public function handle(): int
    {
        $grupos = DB::table('invoices')
            ->selectRaw('subscription_id, due_date, COUNT(*) n, MIN(id) manter')
            ->whereNotNull('subscription_id')
            ->whereNotNull('due_date')
            // Já anuladas não são duplicadas por tratar — sem isto a varredura
            // repetia o mesmo relatório depois de o trabalho estar feito.
            ->where('status', '!=', 'cancelled')
            ->when($this->option('tenant'), fn ($q) => $q->where('tenant_id', $this->option('tenant')))
            ->groupBy('subscription_id', 'due_date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($grupos->isEmpty()) {
            $this->info('Nenhuma factura duplicada.');

            return self::SUCCESS;
        }

        $linhas = [];
        $paraTratar = [];
        $pagasEmRisco = [];

        foreach ($grupos as $g) {
            $facturas = Invoice::where('subscription_id', $g->subscription_id)
                ->whereDate('due_date', $g->due_date)
                ->where('status', '!=', 'cancelled')
                ->orderBy('id')->get();

            $manter = $facturas->first();

            foreach ($facturas->skip(1) as $f) {
                // Uma factura paga não é lixo de um defeito: é dinheiro que
                // entrou. Nunca se mexe — reporta-se.
                //
                // Não se usa a relação payments(): ela aponta para uma tabela
                // que não existe nesta base (invoicing_payments) e rebenta ao
                // ser tocada. O estado e a data de pagamento chegam.
                $temPagamento = $f->status === 'paid' || $f->paid_at !== null;

                if ($temPagamento) {
                    $pagasEmRisco[] = [$f->id, $f->invoice_number, $f->tenant_id, $f->status];
                    continue;
                }

                $paraTratar[] = $f;
            }

            $linhas[] = [
                $g->subscription_id, $g->due_date, $g->n,
                $manter->invoice_number . ' (#' . $manter->id . ')',
                $facturas->count() - 1,
            ];
        }

        $this->table(['subscrição', 'vencimento', 'facturas', 'a MANTER', 'a tratar'], $linhas);

        if ($pagasEmRisco) {
            $this->newLine();
            $this->warn('Duplicadas que estão PAGAS — não se mexe, decida à mão:');
            $this->table(['id', 'número', 'empresa', 'estado'], $pagasEmRisco);
        }

        $this->newLine();
        $accao = $this->option('apagar') ? 'APAGADAS' : 'anuladas';
        $this->line(count($paraTratar) . " factura(s) seriam {$accao}.");

        if (!$this->option('aplicar')) {
            $this->comment('SIMULAÇÃO — nada foi gravado. Use --aplicar para tratar.');

            return self::SUCCESS;
        }

        $feitas = 0;
        DB::transaction(function () use ($paraTratar, &$feitas) {
            foreach ($paraTratar as $f) {
                if ($this->option('apagar')) {
                    $f->delete();
                } else {
                    // Anular preserva o rasto: quem receber o aviso e for ver
                    // encontra a factura, com o motivo à frente.
                    // `notes` não existe nesta tabela; a razão fica na
                    // descrição, que é o que se lê na lista de facturas.
                    $f->update([
                        'status'      => 'cancelled',
                        'description' => trim(($f->description ?? '') . ' [anulada: duplicada]'),
                    ]);
                }
                $feitas++;
            }
        });

        $this->info("{$feitas} factura(s) {$accao}.");

        return self::SUCCESS;
    }
}
