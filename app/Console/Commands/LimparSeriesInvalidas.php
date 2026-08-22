<?php

namespace App\Console\Commands;

use App\Models\Invoicing\InvoicingSeries;
use App\Services\Invoicing\SeriesCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remove apenas séries redundantes que nunca produziram um documento.
 *
 * Uma série fiscal usada ou registada na AGT é evidência permanente e nunca é
 * apagada por este comando. A série canónica do tipo também nunca é apagada.
 */
class LimparSeriesInvalidas extends Command
{
    protected $signature = 'series:limpar-invalidas
                            {--tenant= : Limitar a uma empresa}
                            {--aplicar : Apagar as séries redundantes encontradas}';

    protected $description = 'Elimina séries locais redundantes, sem documentos e sem registo AGT';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $tenantId = $this->option('tenant');
        $apagadas = 0;
        $protegidas = 0;

        $query = InvoicingSeries::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', (int) $tenantId))
            ->orderBy('tenant_id')
            ->orderBy('document_type')
            ->orderBy('id');

        foreach ($query->get()->groupBy(fn ($s) => $s->tenant_id . ':' . $s->document_type) as $series) {
            if ($series->count() < 2) {
                continue;
            }

            $tipo = (string) $series->first()->document_type;
            $canonica = SeriesCatalog::paraTipo($tipo);
            if (!$canonica) {
                continue;
            }

            // Só há redundância inequívoca quando a série canónica já existe.
            $principal = $series->firstWhere('series_code', $canonica['code']);
            if (!$principal) {
                continue;
            }

            foreach ($series->where('id', '!=', $principal->id) as $serie) {
                $documentos = max(
                    SeriesCatalog::documentosEmitidos($serie),
                    $this->referencesTo($serie->id)
                );
                $registada = filled($serie->agt_series_id)
                    || filled($serie->atcud_validation_code)
                    || filled($serie->submission_uuid);

                if ($documentos > 0 || $registada) {
                    $protegidas++;
                    $this->warn(sprintf(
                        'PROTEGIDA tenant %d série #%d %s (%d documento(s)%s)',
                        $serie->tenant_id,
                        $serie->id,
                        $serie->series_code,
                        $documentos,
                        $registada ? ', registada na AGT' : ''
                    ));
                    continue;
                }

                $this->line(sprintf(
                    '%s tenant %d série #%d %s; fica #%d %s',
                    $aplicar ? 'APAGADA' : 'APAGARIA',
                    $serie->tenant_id,
                    $serie->id,
                    $serie->series_code,
                    $principal->id,
                    $principal->series_code
                ));

                if ($aplicar) {
                    DB::transaction(fn () => $serie->delete());
                }

                $apagadas++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d série(s) redundante(s); protegidas: %d.',
            $aplicar ? 'Resultado' : 'Simulação',
            $apagadas,
            $protegidas
        ));

        if (!$aplicar) {
            $this->comment('Nada foi alterado. Use --aplicar depois de rever esta lista.');
        }

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function tablesWithSeriesId(): array
    {
        static $tables;
        if ($tables !== null) {
            return $tables;
        }

        $database = DB::getDatabaseName();
        $tables = collect(DB::select(
            'SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = ?',
            [$database, 'series_id']
        ))->pluck('TABLE_NAME')->map(fn ($name) => (string) $name)->all();

        return $tables;
    }

    private function referencesTo(int $seriesId): int
    {
        $total = 0;
        foreach ($this->tablesWithSeriesId() as $table) {
            $total += DB::table($table)->where('series_id', $seriesId)->count();
        }

        return $total;
    }
}
