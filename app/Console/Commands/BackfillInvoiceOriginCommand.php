<?php

namespace App\Console\Commands;

use App\Models\Invoicing\SalesInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Preenche a origem (source_module / source_reference) das facturas antigas.
 *
 * A ligação durável factura↔documento de negócio só passou a ser gravada a
 * partir da alteração de 2026-08-02. Os documentos anteriores ficam sem ela, e
 * sem isso não é possível listar todas as facturas de uma reserva nem deduzir
 * adiantamentos no check-out.
 *
 * SEGURANÇA: escreve EXCLUSIVAMENTE as duas colunas novas. Nunca toca em
 * total, net_total, gross_total, tax_payable, saft_hash, atcud, invoice_number
 * nem invoice_status — os documentos já emitidos ficam fiscalmente idênticos.
 */
class BackfillInvoiceOriginCommand extends Command
{
    protected $signature = 'invoices:backfill-origem
                            {--dry-run : Mostra o que faria, sem gravar}
                            {--tenant= : Limitar a uma empresa}';

    protected $description = 'Liga facturas antigas à reserva / ordem de serviço que as originou';

    /**
     * Ids já contabilizados. Só serve ao --dry-run: como aí nada é gravado, o
     * `whereNull('source_module')` do nível 2 continuaria a apanhar o que o
     * nível 1 diria que ia marcar, e a simulação contava o mesmo documento
     * duas vezes.
     */
    private array $jaContados = [];

    public function handle(): int
    {
        $simulacao = (bool) $this->option('dry-run');
        $tenant    = $this->option('tenant');

        if ($simulacao) {
            $this->warn('MODO SIMULAÇÃO — nada será gravado.');
        }

        $total = 0;

        // ── Nível 1: determinístico, pelo invoice_id que cada documento guarda ──
        $total += $this->porVinculoDirecto(
            'hotel_reservations', 'reservation_number', 'hotel', $tenant, $simulacao
        );
        $total += $this->porVinculoDirecto(
            'workshop_work_orders', 'order_number', 'oficina', $tenant, $simulacao
        );

        // ── Nível 2: heurístico, pelo número deixado nas notas ──
        // Só onde o nível 1 não chegou. É o rasto que existia antes: as duas
        // origens escrevem o número do documento nas notas da factura.
        $total += $this->porNotas('hotel_reservations', 'reservation_number', 'hotel', $tenant, $simulacao);
        $total += $this->porNotas('workshop_work_orders', 'order_number', 'oficina', $tenant, $simulacao);

        $this->newLine();
        $this->info(($simulacao ? 'Marcaria ' : 'Marcadas ') . $total . ' factura(s).');

        $porLigar = SalesInvoice::whereNull('source_module')
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant))
            ->count();

        $this->line("Ficam {$porLigar} factura(s) sem origem (vendas directas, POS e o que não foi possível casar).");

        return self::SUCCESS;
    }

    /** Facturas alcançáveis pelo invoice_id do próprio documento de negócio. */
    private function porVinculoDirecto(string $tabela, string $coluna, string $modulo, $tenant, bool $simulacao): int
    {
        $n = 0;

        DB::table($tabela)
            ->whereNotNull('invoice_id')
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant))
            ->orderBy('id')
            ->chunkById(500, function ($linhas) use (&$n, $coluna, $modulo, $simulacao) {
                foreach ($linhas as $linha) {
                    $consulta = SalesInvoice::where('id', $linha->invoice_id)
                        ->where('tenant_id', $linha->tenant_id)
                        ->whereNull('source_module');

                    $n += $simulacao
                        ? $this->contarSimulado($consulta)
                        : $consulta->update([
                            'source_module'    => $modulo,
                            'source_reference' => $linha->{$coluna},
                        ]);
                }
            });

        $this->line("  {$modulo} (vínculo directo): {$n}");

        return $n;
    }

    /** Facturas que só têm o número da origem escrito nas notas. */
    private function porNotas(string $tabela, string $coluna, string $modulo, $tenant, bool $simulacao): int
    {
        $n = 0;
        $semCasar = 0;

        DB::table($tabela)
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant))
            ->orderBy('id')
            ->chunkById(500, function ($linhas) use (&$n, &$semCasar, $coluna, $modulo, $simulacao) {
                foreach ($linhas as $linha) {
                    $referencia = $linha->{$coluna};
                    if (!$referencia) {
                        continue;
                    }

                    $consulta = SalesInvoice::where('tenant_id', $linha->tenant_id)
                        ->whereNull('source_module')
                        ->where('notes', 'like', '%' . $referencia . '%');

                    if ($simulacao) {
                        $n += $this->contarSimulado($consulta);
                        continue;
                    }

                    $afectadas = $consulta->update([
                        'source_module'    => $modulo,
                        'source_reference' => $referencia,
                    ]);

                    if ($afectadas === 0) {
                        $semCasar++;
                    }

                    $n += $afectadas;
                }
            });

        $this->line("  {$modulo} (pelas notas): {$n}" . ($semCasar ? "  ({$semCasar} sem correspondência)" : ''));

        return $n;
    }

    /** Conta sem repetir o que outro nível já teria marcado (só --dry-run). */
    private function contarSimulado($consulta): int
    {
        $novos = $consulta->pluck('id')
            ->reject(fn ($id) => isset($this->jaContados[$id]))
            ->values();

        foreach ($novos as $id) {
            $this->jaContados[$id] = true;
        }

        return $novos->count();
    }
}
