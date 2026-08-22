<?php

namespace App\Console\Commands\AGT;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Services\AGT\SeriesService;
use Illuminate\Console\Command;

class SyncOneSeriesCommand extends Command
{
    protected $signature = 'agt:sync-series
        {--tenant= : ID da empresa}
        {--tipo=FT : Tipo AGT: FT, FR, RC, NC ou ND}
        {--aplicar : Envia efectivamente o pedido à AGT}';

    protected $description = 'Testa uma única série pendente antes da sincronização em lote';

    private const TIPOS = [
        'FT' => 'invoice', 'FR' => 'pos', 'RC' => 'receipt',
        'NC' => 'credit_note', 'ND' => 'debit_note',
    ];

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');
        $tipo = strtoupper(trim((string) $this->option('tipo')));
        $documentType = self::TIPOS[$tipo] ?? null;
        if (!$tenantId || !$documentType) {
            $this->error('Use --tenant=<id> e --tipo=FT|FR|RC|NC|ND.');
            return self::FAILURE;
        }

        $serie = InvoicingSeries::where('tenant_id', $tenantId)
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->whereNull('agt_series_id')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if (!$serie) {
            $this->info("Não existe série {$tipo} activa e pendente no tenant {$tenantId}.");
            return self::SUCCESS;
        }

        $settings = InvoicingSettings::forTenant($tenantId);
        $this->line("Tenant {$tenantId}; ambiente {$settings->agt_environment}; série #{$serie->id} {$serie->series_code}.");

        try {
            $service = new SeriesService($settings);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if (!$this->option('aplicar')) {
            $detail = $service->buildPayload($serie)['softwareInfo']['softwareInfoDetail'] ?? [];
            $this->line('SIMULAÇÃO: ' . json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->comment('Nada enviado. Acrescente --aplicar.');
            return self::SUCCESS;
        }

        $result = $service->request($serie);
        if ($result['ok']) {
            $this->info("ACEITE pela AGT: {$result['seriesCode']}");
            return self::SUCCESS;
        }

        $this->error('RECUSADA pela AGT: ' . ($result['error'] ?: 'sem detalhe'));
        return self::FAILURE;
    }
}
