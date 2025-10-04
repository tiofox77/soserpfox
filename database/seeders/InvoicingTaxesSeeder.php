<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Invoicing\Tax;
use App\Models\Tenant;

class InvoicingTaxesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obter todos os tenants ativos
        $tenants = Tenant::where('is_active', true)->get();
        
        foreach ($tenants as $tenant) {
            // Verificar se já tem impostos criados para este tenant
            $existingTaxes = Tax::where('tenant_id', $tenant->id)->count();
            
            if ($existingTaxes > 0) {
                $this->command->info("Tenant '{$tenant->name}' já possui impostos configurados. Pulando...");
                continue;
            }
            
            $this->command->info("Criando impostos para tenant: {$tenant->name}");
            
            // ========================================
            // IVA NORMAL - 14% (PADRÃO ANGOLA)
            // ========================================
            Tax::create([
                'tenant_id' => $tenant->id,
                'code' => 'IVA14',
                'name' => 'IVA 14% (Normal)',
                'description' => 'Imposto sobre o Valor Acrescentado - Taxa Normal Angola',
                'rate' => 14.00,
                'type' => 'iva',
                'saft_code' => 'NOR',
                'saft_type' => 'NOR',
                'exemption_reason' => null,
                'is_default' => true, // ✅ PADRÃO
                'is_active' => true,
                'include_in_price' => false,
                'compound_tax' => false,
            ]);
            
            // ========================================
            // IVA REDUZIDA - 7%
            // ========================================
            Tax::create([
                'tenant_id' => $tenant->id,
                'code' => 'IVA7',
                'name' => 'IVA 7% (Reduzida)',
                'description' => 'Imposto sobre o Valor Acrescentado - Taxa Reduzida (produtos essenciais, medicamentos, etc.)',
                'rate' => 7.00,
                'type' => 'iva',
                'saft_code' => 'RED',
                'saft_type' => 'RED',
                'exemption_reason' => null,
                'is_default' => false,
                'is_active' => true,
                'include_in_price' => false,
                'compound_tax' => false,
            ]);
            
            // ========================================
            // IVA ZERO - 0% (EXPORTAÇÕES)
            // ========================================
            Tax::create([
                'tenant_id' => $tenant->id,
                'code' => 'IVA0',
                'name' => 'IVA 0% (Exportação)',
                'description' => 'Taxa zero para exportações e operações específicas',
                'rate' => 0.00,
                'type' => 'iva',
                'saft_code' => 'NOR',
                'saft_type' => 'NOR',
                'exemption_reason' => 'Exportação de bens para fora do território nacional',
                'is_default' => false,
                'is_active' => true,
                'include_in_price' => false,
                'compound_tax' => false,
            ]);
            
            // ========================================
            // ISENTO DE IVA
            // ========================================
            Tax::create([
                'tenant_id' => $tenant->id,
                'code' => 'IVAISEN',
                'name' => 'Isento de IVA',
                'description' => 'Operações isentas de IVA (saúde, educação, serviços financeiros, etc.)',
                'rate' => 0.00,
                'type' => 'iva',
                'saft_code' => 'ISE',
                'saft_type' => 'ISE',
                'exemption_reason' => 'Isento nos termos do Código do IVA - Artigo 9º',
                'is_default' => false,
                'is_active' => true,
                'include_in_price' => false,
                'compound_tax' => false,
            ]);
            
            // ========================================
            // NÃO SUJEITO A IVA
            // ========================================
            Tax::create([
                'tenant_id' => $tenant->id,
                'code' => 'IVANS',
                'name' => 'Não Sujeito a IVA',
                'description' => 'Operações não sujeitas ao regime de IVA',
                'rate' => 0.00,
                'type' => 'iva',
                'saft_code' => 'NS',
                'saft_type' => 'NS',
                'exemption_reason' => 'Operação fora do âmbito do IVA',
                'is_default' => false,
                'is_active' => true,
                'include_in_price' => false,
                'compound_tax' => false,
            ]);
            
            // ========================================
            // IRT - IMPOSTO SOBRE RENDIMENTO DO TRABALHO - 6.5%
            // ========================================
            Tax::create([
                'tenant_id' => $tenant->id,
                'code' => 'IRT6.5',
                'name' => 'IRT 6,5% (Retenção)',
                'description' => 'Imposto sobre Rendimento do Trabalho - Retenção na fonte para prestação de serviços',
                'rate' => 6.50,
                'type' => 'irt',
                'saft_code' => 'OUT',
                'saft_type' => 'OUT',
                'exemption_reason' => null,
                'is_default' => false,
                'is_active' => true,
                'include_in_price' => false,
                'compound_tax' => false,
            ]);
            
            $this->command->info("✅ Impostos criados com sucesso para '{$tenant->name}'!");
        }
        
        $this->command->info("
╔══════════════════════════════════════════════════════════════╗
║  ✅ IMPOSTOS DE ANGOLA CRIADOS COM SUCESSO!                ║
╚══════════════════════════════════════════════════════════════╝

📊 Impostos Configurados:

┌─────────────────────────────────────────────────────────────┐
│ IVA - IMPOSTO SOBRE O VALOR ACRESCENTADO:                  │
├─────────────────────────────────────────────────────────────┤
│  ✓ IVA14  - IVA 14% Normal (PADRÃO) 🌟                    │
│  ✓ IVA7   - IVA 7% Reduzida                                │
│  ✓ IVA0   - IVA 0% Exportação                              │
│  ✓ IVAISEN- Isento de IVA                                  │
│  ✓ IVANS  - Não Sujeito a IVA                             │
├─────────────────────────────────────────────────────────────┤
│ IRT - IMPOSTO SOBRE RENDIMENTO DO TRABALHO:                │
├─────────────────────────────────────────────────────────────┤
│  ✓ IRT6.5 - Retenção 6,5% (Serviços)                      │
└─────────────────────────────────────────────────────────────┘

📝 Classificação SAFT-AO:
   • NOR - Normal (14%)
   • RED - Reduzida (7%)
   • ISE - Isento
   • NS  - Não Sujeito
   • OUT - Outro (IRT)

🇦🇴 Conformidade Legal:
   ✓ Lei nº 7/19 - Código do IVA
   ✓ Decreto Presidencial 312/18 - SAFT-AO
   ✓ Portaria nº 31.1/AGT/2020

🎯 Próximos Passos:
   1. Acesse /invoicing/taxes para visualizar
   2. Configure produtos com os impostos corretos
   3. IVA 14% será usado como padrão automaticamente
        ");
    }
}

