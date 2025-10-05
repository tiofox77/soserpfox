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
        
        // Mapeamento de tipos para prefixos AGT
        $documentTypes = [
            'proforma' => ['prefix' => 'PR', 'name' => 'Proforma de Venda'],
            'invoice' => ['prefix' => 'FT', 'name' => 'Fatura de Venda'],
            'pos' => ['prefix' => 'FR', 'name' => 'Fatura-Recibo (POS)'],
            'receipt' => ['prefix' => 'RC', 'name' => 'Recibo'],
            'credit_note' => ['prefix' => 'NC', 'name' => 'Nota de Crédito'],
            'debit_note' => ['prefix' => 'ND', 'name' => 'Nota de Débito'],
            'purchase' => ['prefix' => 'FC', 'name' => 'Fatura de Compra'],
        ];
        
        foreach ($documentTypes as $type => $config) {
            // Verificar se já existe
            $exists = InvoicingSeries::where('tenant_id', $tenantId)
                ->where('document_type', $type)
                ->where('series_code', 'A')
                ->exists();
            
            if ($exists) {
                $this->warn("  ⚠️  {$config['name']} ({$config['prefix']} A) já existe");
                continue;
            }
            
            // Criar série padrão A
            InvoicingSeries::create([
                'tenant_id' => $tenantId,
                'document_type' => $type,
                'series_code' => 'A',
                'name' => "Série {$config['prefix']} A",
                'prefix' => $config['prefix'],
                'include_year' => true,
                'next_number' => 1,
                'number_padding' => 6,
                'is_default' => true,
                'is_active' => true,
                'current_year' => now()->year,
                'reset_yearly' => true,
                'description' => "Série padrão AGT para {$config['name']}",
            ]);
            
            $this->info("  ✅ {$config['name']} ({$config['prefix']} A) criada");
        }
    }
}
