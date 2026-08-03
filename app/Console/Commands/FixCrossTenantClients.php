<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repõe a coerência de empresa nos documentos: encontra documentos cujo
 * client_id aponta para um cliente de OUTRA empresa (efeito do `orWhere` que
 * quebrava o filtro de tenant no POS) e repõe o cliente equivalente da PRÓPRIA
 * empresa (mesmo NIF).
 *
 * NÃO altera valores, impostos, numeração nem o hash SAFT (o hash é calculado
 * sobre data + nº documento + total + hash anterior — o cliente não entra).
 * O documento impresso fica igual: mesmo nome e mesmo NIF.
 *
 * Só repõe quando existe (ou pode ser criado) um cliente equivalente com o
 * MESMO NIF na empresa correta. Casos ambíguos são apenas reportados.
 *
 *   php artisan invoices:fix-cross-tenant-client --dry-run
 *   php artisan invoices:fix-cross-tenant-client --tenant=17
 */
class FixCrossTenantClients extends Command
{
    protected $signature = 'invoices:fix-cross-tenant-client
                            {--tenant= : Limitar a um tenant}
                            {--dry-run : Apenas mostra o que seria alterado}';

    protected $description = 'Repõe o cliente da própria empresa em documentos que apontam para cliente de outro tenant';

    /** Documentos com client_id (tabela => coluna de nº para relatório). */
    protected array $docs = [
        'invoicing_sales_invoices'  => 'invoice_number',
        'invoicing_credit_notes'    => 'credit_note_number',
        'invoicing_debit_notes'     => 'debit_note_number',
        'invoicing_receipts'        => 'receipt_number',
        'invoicing_sales_proformas' => 'proforma_number',
    ];

    public function handle(): int
    {
        $dry      = (bool) $this->option('dry-run');
        $tenantId = $this->option('tenant');

        $this->info('=== Documentos com cliente de outra empresa ' . ($dry ? '(dry-run)' : '(REAL)') . ' ===');
        $this->line('  O hash SAFT não inclui o cliente — repor não invalida assinaturas.');
        $this->newLine();

        $totalFixed = 0;
        $totalSkipped = 0;

        foreach ($this->docs as $table => $numberCol) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($table)
                || !\Illuminate\Support\Facades\Schema::hasColumn($table, 'client_id')) {
                continue;
            }

            $q = DB::table("$table as d")
                ->join('invoicing_clients as c', 'c.id', '=', 'd.client_id')
                ->whereColumn('d.tenant_id', '<>', 'c.tenant_id');

            if ($tenantId) {
                $q->where('d.tenant_id', $tenantId);
            }

            $rows = $q->select(
                'd.id', 'd.tenant_id', 'd.client_id',
                DB::raw("d.$numberCol as doc_number"),
                'c.tenant_id as client_tenant', 'c.nif as client_nif', 'c.name as client_name'
            )->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $this->line("<fg=cyan>{$table}: {$rows->count()} documento(s)</>");

            // Agrupar por (tenant do documento, cliente errado) — a correção é a mesma
            foreach ($rows->groupBy(fn($r) => $r->tenant_id . '|' . $r->client_id) as $key => $group) {
                [$docTenant, $wrongClientId] = explode('|', $key);
                $sample = $group->first();

                $correct = $this->resolveCorrectClient((int) $docTenant, $sample, $dry);

                if (!$correct) {
                    $this->warn("   ! t{$docTenant}: {$group->count()} doc(s) apontam para o cliente #{$wrongClientId} "
                        . "\"{$sample->client_name}\" (NIF {$sample->client_nif}) do t{$sample->client_tenant}, "
                        . 'mas não há equivalente com o mesmo NIF nesta empresa — NÃO alterado (requer decisão manual).');
                    $totalSkipped += $group->count();
                    continue;
                }

                $this->line("   • t{$docTenant}: {$group->count()} doc(s) — cliente #{$wrongClientId} (do t{$sample->client_tenant}) "
                    . "→ #{$correct->id} \"{$correct->name}\" (NIF {$correct->nif}) da própria empresa");
                $this->line('     ex.: ' . $group->take(3)->pluck('doc_number')->implode(', ')
                    . ($group->count() > 3 ? ' …' : ''));

                if (!$dry) {
                    $ids = $group->pluck('id')->all();
                    DB::table($table)->whereIn('id', $ids)->update(['client_id' => $correct->id]);

                    Log::info('FixCrossTenantClients: cliente reposto', [
                        'table'        => $table,
                        'tenant_id'    => (int) $docTenant,
                        'from_client'  => (int) $wrongClientId,
                        'to_client'    => $correct->id,
                        'documents'    => count($ids),
                        'document_ids' => $ids,
                    ]);
                }
                $totalFixed += $group->count();
            }
        }

        $this->newLine();
        if ($totalFixed === 0 && $totalSkipped === 0) {
            $this->info('✓ Nenhum documento com cliente de outra empresa. Tudo coerente.');
            return self::SUCCESS;
        }

        $this->info("   Documentos " . ($dry ? 'a corrigir' : 'corrigidos') . ": {$totalFixed}");
        if ($totalSkipped > 0) {
            $this->warn("   Documentos por decidir manualmente: {$totalSkipped}");
        }
        if ($dry) {
            $this->info('(dry-run) Nada foi alterado. Remova --dry-run para aplicar.');
        }

        return self::SUCCESS;
    }

    /**
     * Cliente equivalente NA EMPRESA CORRETA (mesmo NIF). Para o Consumidor Final
     * genérico (999999999) cria-o se ainda não existir — é o cliente por omissão
     * do POS e a criação é idempotente por (tenant_id, nif).
     */
    protected function resolveCorrectClient(int $tenantId, object $sample, bool $dry): ?object
    {
        $nif = trim((string) $sample->client_nif);

        if ($nif !== '') {
            $found = DB::table('invoicing_clients')
                ->where('tenant_id', $tenantId)
                ->where('nif', $nif)
                ->first(['id', 'name', 'nif']);
            if ($found) {
                return $found;
            }
        }

        // Consumidor Final genérico — pode ser criado com segurança
        $isConsumidorFinal = $nif === '999999999'
            || str_contains(mb_strtolower((string) $sample->client_name), 'consumidor final');

        if (!$isConsumidorFinal) {
            return null;   // cliente nominal de outra empresa → decisão humana
        }

        if ($dry) {
            return (object) ['id' => '(a criar)', 'name' => 'Consumidor Final', 'nif' => '999999999'];
        }

        $client = Client::firstOrCreate(
            ['tenant_id' => $tenantId, 'nif' => '999999999'],
            [
                'type'           => 'pessoa_fisica',
                'name'           => 'Consumidor Final',
                'country'        => 'Angola',
                'tax_regime'     => 'geral',
                'is_iva_subject' => false,
                'is_active'      => true,
            ]
        );

        return (object) ['id' => $client->id, 'name' => $client->name, 'nif' => $client->nif];
    }
}
