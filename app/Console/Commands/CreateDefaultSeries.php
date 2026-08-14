<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Tenant;

class CreateDefaultSeries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'series:create-defaults {--tenant= : Tenant ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Criar séries padrão AGT para todos os tipos de documentos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->option('tenant');
        
        if (!$tenantId) {
            // Buscar todos os tenants
            $tenants = Tenant::all();
            
            if ($tenants->isEmpty()) {
                $this->error('Nenhum tenant encontrado!');
                return 1;
            }
            
            foreach ($tenants as $tenant) {
                $this->createSeriesForTenant($tenant->id);
            }
        } else {
            $this->createSeriesForTenant($tenantId);
        }
        
        $this->info('✅ Séries padrão criadas com sucesso!');
        return 0;
    }
    
    private function createSeriesForTenant($tenantId)
    {
        $this->info("Criando séries para Tenant ID: {$tenantId}");
        
        // Só os NOMES ficam aqui. O prefixo é um código fiscal e vem sempre de
        // InvoicingSeries::AGT_PREFIXES: esta lista já teve a sua própria cópia
        // do mapa, e uma cópia é uma divergência à espera de acontecer — quem
        // corrigisse o catálogo não corrigia as séries criadas por este comando.
        $documentTypes = [
            'proforma' => 'Proforma de Venda',
            'invoice' => 'Fatura de Venda',
            'pos' => 'Fatura-Recibo (POS)',
            'receipt' => 'Recibo',
            'credit_note' => 'Nota de Crédito',
            'debit_note' => 'Nota de Débito',
            'purchase' => 'Fatura de Compra',
        ];

        foreach ($documentTypes as $type => $name) {
            $prefix = InvoicingSeries::prefixoDe($type);

            // Sem entrada no catálogo não se inventa prefixo: uma série assim
            // emitiria documentos que a AGT não consegue classificar.
            if ($prefix === null) {
                $this->warn("  ⚠️  {$name}: sem prefixo no catálogo AGT — ignorado.");
                continue;
            }

            // Verificar se já existe
            $exists = InvoicingSeries::where('tenant_id', $tenantId)
                ->where('document_type', $type)
                ->where('series_code', 'A')
                ->exists();

            if ($exists) {
                $this->warn("  ⚠️  {$name} ({$prefix} A) já existe");
                continue;
            }

            // Criar série padrão A
            InvoicingSeries::create([
                'tenant_id' => $tenantId,
                'document_type' => $type,
                'series_code' => 'A',
                'name' => "Série {$prefix} A",
                'prefix' => $prefix,
                'include_year' => true,
                'next_number' => 1,
                'number_padding' => 6,
                // Este comando corre sobre empresas que já podem ter séries: a
                // padrão daquele tipo pode existir e estar em uso. Gravar `true`
                // às cegas punha duas a disputar a numeração do mesmo tipo.
                'is_default' => InvoicingSeries::deveNascerPadrao((int) $tenantId, $type),
                'is_active' => true,
                'current_year' => now()->year,
                'reset_yearly' => true,
                'description' => "Série padrão AGT para {$name}",
            ]);

            $this->info("  ✅ {$name} ({$prefix} A) criada");
        }
    }
}
