<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Tenant;

class InvoicingSeriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Os prefixos vêm todos de InvoicingSeries::prefixoDe(). Estavam escritos à
     * mão e a proforma dizia 'PRF' — é daqui que vêm as séries 'PRF' que a AGT
     * recusa com E32, porque é o primeiro token do número que ela lê para
     * classificar o documento. Um seeder que grava um código fiscal tem de o
     * pedir ao catálogo, senão volta a divergir dele em silêncio.
     */
    public function run(): void
    {
        // Obter todos os tenants ativos
        $tenants = Tenant::where('is_active', true)->get();
        
        foreach ($tenants as $tenant) {
            // Verificar se já tem séries criadas para este tenant
            $existingSeries = InvoicingSeries::where('tenant_id', $tenant->id)->count();
            
            if ($existingSeries > 0) {
                $this->command->info("Tenant '{$tenant->name}' já possui séries configuradas. Pulando...");
                continue;
            }
            
            $this->command->info("Criando séries para tenant: {$tenant->name}");
            
            // ========================================
            // FATURAS (FT)
            // ========================================
            InvoicingSeries::create([
                'tenant_id' => $tenant->id,
                'document_type' => 'invoice',
                'series_code' => 'A',
                'name' => 'Vendas Loja - Série A',
                'prefix' => InvoicingSeries::prefixoDe('invoice'),
                'include_year' => true,
                'next_number' => 1,
                'number_padding' => 6,
                // Padrão só se aquele tipo ainda não tiver nenhuma. O guarda do
                // topo salta empresas COM séries, mas é por contagem total: uma
                // empresa a quem faltasse só um tipo passava por aqui e ganhava
                // uma segunda padrão nos tipos que já tinha.
                'is_default' => InvoicingSeries::deveNascerPadrao($tenant->id, 'invoice'),
                'is_active' => true,
                'current_year' => now()->year,
                'reset_yearly' => true,
                'description' => 'Série principal para vendas em loja física',
            ]);
            
            InvoicingSeries::create([
                'tenant_id' => $tenant->id,
                'document_type' => 'invoice',
                'series_code' => 'B',
                'name' => 'Vendas Online - Série B',
                'prefix' => InvoicingSeries::prefixoDe('invoice'),
                'include_year' => true,
                'next_number' => 1,
                'number_padding' => 6,
                'is_default' => false,
                'is_active' => true,
                'current_year' => now()->year,
                'reset_yearly' => true,
                'description' => 'Série para vendas através de plataforma online',
            ]);
            
            InvoicingSeries::create([
                'tenant_id' => $tenant->id,
                'document_type' => 'invoice',
                'series_code' => 'C',
                'name' => 'Exportação - Série C',
                'prefix' => InvoicingSeries::prefixoDe('invoice'),
                'include_year' => true,
                'next_number' => 1,
                'number_padding' => 6,
                'is_default' => false,
                'is_active' => true,
                'current_year' => now()->year,
                'reset_yearly' => true,
                'description' => 'Série para faturas de exportação',
            ]);
            
            // ========================================
            // PROFORMAS (PRF)
            // ========================================
            InvoicingSeries::create([
                'tenant_id' => $tenant->id,
                'document_type' => 'proforma',
                'series_code' => '01',
                'name' => 'Orçamentos Gerais',
                'prefix' => InvoicingSeries::prefixoDe('proforma'),
                'include_year' => true,
                'next_number' => 1,
                'number_padding' => 6,
                'is_default' => InvoicingSeries::deveNascerPadrao($tenant->id, 'proforma'),
                'is_active' => true,
                'current_year' => now()->year,
                'reset_yearly' => true,
                'description' => 'Série padrão para proformas e orçamentos',
            ]);
            
            InvoicingSeries::create([
                'tenant_id' => $tenant->id,
                'document_type' => 'proforma',
                'series_code' => '02',
                'name' => 'Projetos Especiais',
                'prefix' => InvoicingSeries::prefixoDe('proforma'),
                'include_year' => true,
                'next_number' => 1,
                'number_padding' => 6,
                'is_default' => false,
                'is_active' => true,
                'current_year' => now()->year,
                'reset_yearly' => true,
                'description' => 'Proformas para projetos de grande porte',
            ]);
            
            // ========================================
            // RECIBOS (RC)
            // ========================================
            InvoicingSeries::create([
                'tenant_id' => $tenant->id,
                'document_type' => 'receipt',
                'series_code' => '01',
                'name' => 'Recibos Gerais',
                'prefix' => InvoicingSeries::prefixoDe('receipt'),
                'include_year' => true,
                'next_number' => 1,
                'number_padding' => 6,
                'is_default' => InvoicingSeries::deveNascerPadrao($tenant->id, 'receipt'),
                'is_active' => true,
                'current_year' => now()->year,
                'reset_yearly' => true,
                'description' => 'Série padrão para recibos de pagamento',
            ]);
            
            InvoicingSeries::create([
                'tenant_id' => $tenant->id,
                'document_type' => 'receipt',
                'series_code' => '02',
                'name' => 'Recibos Adiantamento',
                'prefix' => InvoicingSeries::prefixoDe('receipt'),
                'include_year' => true,
                'next_number' => 1,
                'number_padding' => 6,
                'is_default' => false,
                'is_active' => true,
                'current_year' => now()->year,
                'reset_yearly' => true,
                'description' => 'Recibos para pagamentos adiantados',
            ]);
            
            // ========================================
            // NOTAS DE CRÉDITO (NC)
            // ========================================
            InvoicingSeries::create([
                'tenant_id' => $tenant->id,
                'document_type' => 'credit_note',
                'series_code' => '01',
                'name' => 'Notas de Crédito',
                'prefix' => InvoicingSeries::prefixoDe('credit_note'),
                'include_year' => true,
                'next_number' => 1,
                'number_padding' => 6,
                'is_default' => InvoicingSeries::deveNascerPadrao($tenant->id, 'credit_note'),
                'is_active' => true,
                'current_year' => now()->year,
                'reset_yearly' => true,
                'description' => 'Série para notas de crédito e devoluções',
            ]);
            
            // ========================================
            // NOTAS DE DÉBITO (ND)
            // ========================================
            InvoicingSeries::create([
                'tenant_id' => $tenant->id,
                'document_type' => 'debit_note',
                'series_code' => '01',
                'name' => 'Notas de Débito',
                'prefix' => InvoicingSeries::prefixoDe('debit_note'),
                'include_year' => true,
                'next_number' => 1,
                'number_padding' => 6,
                'is_default' => InvoicingSeries::deveNascerPadrao($tenant->id, 'debit_note'),
                'is_active' => true,
                'current_year' => now()->year,
                'reset_yearly' => true,
                'description' => 'Série para notas de débito e acréscimos',
            ]);
            
            $this->command->info("✅ Séries criadas com sucesso para '{$tenant->name}'!");
        }
        
        $this->command->info("
╔══════════════════════════════════════════════════════════════╗
║  ✅ SÉRIES DE DOCUMENTOS CRIADAS COM SUCESSO!              ║
╚══════════════════════════════════════════════════════════════╝

📊 Resumo das Séries Criadas:
┌─────────────────────────────────────────────────────────────┐
│ FATURAS (FT):                                               │
│  ✓ FT A - Vendas Loja (PADRÃO)                            │
│  ✓ FT B - Vendas Online                                    │
│  ✓ FT C - Exportação                                       │
├─────────────────────────────────────────────────────────────┤
│ PROFORMAS (PRF):                                            │
│  ✓ PRF 01 - Orçamentos Gerais (PADRÃO)                    │
│  ✓ PRF 02 - Projetos Especiais                            │
├─────────────────────────────────────────────────────────────┤
│ RECIBOS (RC):                                               │
│  ✓ RC 01 - Recibos Gerais (PADRÃO)                        │
│  ✓ RC 02 - Recibos Adiantamento                           │
├─────────────────────────────────────────────────────────────┤
│ NOTAS DE CRÉDITO (NC):                                      │
│  ✓ NC 01 - Notas de Crédito (PADRÃO)                      │
├─────────────────────────────────────────────────────────────┤
│ NOTAS DE DÉBITO (ND):                                       │
│  ✓ ND 01 - Notas de Débito (PADRÃO)                       │
└─────────────────────────────────────────────────────────────┘

📝 Formato dos Números:
   FT A/2025/000001
   PRF 01/2025/000001
   RC 01/2025/000001
   NC 01/2025/000001
   ND 01/2025/000001

🎯 Próximos Passos:
   1. Acesse /invoicing/series para visualizar
   2. Edite ou crie novas séries conforme necessário
   3. As séries marcadas como PADRÃO serão usadas automaticamente
        ");
    }
}

