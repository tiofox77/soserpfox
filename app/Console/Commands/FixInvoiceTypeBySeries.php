<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Alinha `invoicing_sales_invoices.invoice_type` com o tipo da SÉRIE que numerou
 * o documento.
 *
 * O POS online nunca gravava o tipo, pelo que documentos numerados na série FR
 * (Fatura-Recibo) ficaram gravados como 'FT'. O tipo é o que a AGT recebe e o
 * que o PDF imprime — números FR com tipo FT são incoerentes.
 *
 * NÃO altera valores, impostos, numeração, datas nem o hash SAFT (o hash é
 * calculado sobre data + nº + total + hash anterior; o tipo não entra).
 * Documentos JÁ SUBMETIDOS à AGT são deixados intactos e apenas reportados.
 *
 *   php artisan invoices:fix-type-by-series --dry-run
 *   php artisan invoices:fix-type-by-series --tenant=17
 */
class FixInvoiceTypeBySeries extends Command
{
    protected $signature = 'invoices:fix-type-by-series
                            {--tenant= : Limitar a um tenant}
                            {--dry-run : Apenas mostra o que seria alterado}';

    protected $description = 'Corrige invoice_type das faturas para coincidir com o tipo da série (ex.: série FR gravada como FT)';

    /** document_type da série → invoice_type esperado */
    protected array $map = [
        'pos'     => 'FR',
        'invoice' => 'FT',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->info('=== Tipo de documento vs série ' . ($dry ? '(dry-run)' : '(REAL)') . ' ===');
        $this->line('  O hash SAFT não inclui o tipo — corrigir não invalida assinaturas.');
        $this->newLine();

        $total = 0;
        $skippedAgt = 0;

        foreach ($this->map as $seriesType => $expected) {
            $q = DB::table('invoicing_sales_invoices as f')
                ->join('invoicing_series as s', 's.id', '=', 'f.series_id')
                ->where('s.document_type', $seriesType)
                ->where(function ($w) use ($expected) {
                    $w->where('f.invoice_type', '<>', $expected)->orWhereNull('f.invoice_type');
                });

            if ($t = $this->option('tenant')) {
                $q->where('f.tenant_id', $t);
            }

            $rows = $q->select('f.id', 'f.tenant_id', 'f.invoice_number', 'f.invoice_type', 'f.agt_submitted_at', 's.prefix')
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            // Nunca mexer em documentos já comunicados à AGT
            $submetidos = $rows->filter(fn($r) => !empty($r->agt_submitted_at));
            $corrigir   = $rows->filter(fn($r) => empty($r->agt_submitted_at));

            foreach ($corrigir->groupBy('tenant_id') as $tid => $group) {
                $this->line("   • t{$tid}: {$group->count()} documento(s) da série "
                    . ($group->first()->prefix ?? $seriesType)
                    . " com tipo '" . ($group->first()->invoice_type ?? 'NULL') . "' → '{$expected}'");
                $this->line('     ex.: ' . $group->take(3)->pluck('invoice_number')->implode(', ')
                    . ($group->count() > 3 ? ' …' : ''));

                if (!$dry) {
                    $ids = $group->pluck('id')->all();
                    DB::table('invoicing_sales_invoices')->whereIn('id', $ids)
                        ->update(['invoice_type' => $expected]);

                    Log::info('FixInvoiceTypeBySeries: tipo corrigido', [
                        'tenant_id' => (int) $tid,
                        'to'        => $expected,
                        'documents' => count($ids),
                    ]);
                }
                $total += $group->count();
            }

            if ($submetidos->isNotEmpty()) {
                $skippedAgt += $submetidos->count();
                $this->warn("   ! {$submetidos->count()} documento(s) já submetidos à AGT — NÃO alterados "
                    . '(o tipo foi comunicado; corrigir exigiria nota de crédito).');
            }
        }

        $this->newLine();
        if ($total === 0 && $skippedAgt === 0) {
            $this->info('✓ Todos os documentos têm o tipo coerente com a série.');
            return self::SUCCESS;
        }

        $this->info('   Documentos ' . ($dry ? 'a corrigir' : 'corrigidos') . ": {$total}");
        if ($skippedAgt) {
            $this->warn("   Ignorados (já na AGT): {$skippedAgt}");
        }
        if ($dry) {
            $this->info('(dry-run) Nada foi alterado. Remova --dry-run para aplicar.');
        }

        return self::SUCCESS;
    }
}
