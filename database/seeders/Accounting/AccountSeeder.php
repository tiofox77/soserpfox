<?php

namespace Database\Seeders\Accounting;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = \App\Models\Tenant::where('is_active', true)->get();
        
        if ($tenants->isEmpty()) {
            \Log::warning('❌ Nenhum tenant ativo encontrado!');
            return;
        }
        
        $accounts = $this->getSNCAccounts();
        
        foreach ($tenants as $tenant) {
            $existingCount = DB::table('accounting_accounts')
                ->where('tenant_id', $tenant->id)
                ->count();
            
            if ($existingCount > 0) {
                \Log::info("⚠️  Tenant {$tenant->name} já possui {$existingCount} contas. Pulando...");
                continue;
            }
            
            foreach ($accounts as $account) {
                DB::table('accounting_accounts')->insert(array_merge($account, [
                    'tenant_id' => $tenant->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
            
            \Log::info("✅ Criadas " . count($accounts) . " contas para {$tenant->name}");
        }
    }
    
    /**
     * Run seeder for a specific tenant (used when creating new company)
     */
    public function runForTenant(int $tenantId): void
    {
        $accounts = $this->getSNCAccounts();
        
        foreach ($accounts as $account) {
            DB::table('accounting_accounts')->insert(array_merge($account, [
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
    
    private function getSNCAccounts(): array
    {
        // Carregar contas importadas do Excel (se existir)
        $importedFile = database_path('seeders/Accounting/imported_accounts.php');
        
        if (file_exists($importedFile)) {
            \Log::info('📄 Usando plano de contas importado do Excel');
            return require $importedFile;
        }
        
        // Usar contas padrão embutidas no seeder
        \Log::info('📄 Usando plano de contas padrão embutido');
        return [
            // ===== CLASSE 1 - ACTIVO =====
            // Disponibilidades
            ['code' => '11', 'name' => 'Disponibilidades', 'type' => 'asset', 'nature' => 'debit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '111', 'name' => 'Caixa', 'type' => 'asset', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'cash'],
            ['code' => '1111', 'name' => 'Caixa Principal', 'type' => 'asset', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '112', 'name' => 'Depósitos à Ordem', 'type' => 'asset', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'bank'],
            ['code' => '1121', 'name' => 'Banco BFA', 'type' => 'asset', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '1122', 'name' => 'Banco BAI', 'type' => 'asset', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            
            // Clientes
            ['code' => '21', 'name' => 'Clientes', 'type' => 'asset', 'nature' => 'debit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'receivables'],
            ['code' => '211', 'name' => 'Clientes c/c', 'type' => 'asset', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '2111', 'name' => 'Clientes Gerais', 'type' => 'asset', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '218', 'name' => 'Clientes de Cobrança Duvidosa', 'type' => 'asset', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            
            // Inventários
            ['code' => '31', 'name' => 'Compras', 'type' => 'asset', 'nature' => 'debit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '311', 'name' => 'Mercadorias', 'type' => 'asset', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'inventory'],
            ['code' => '32', 'name' => 'Matérias-Primas', 'type' => 'asset', 'nature' => 'debit', 'level' => 1, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            
            // Imobilizado
            ['code' => '42', 'name' => 'Imobilizações Corpóreas', 'type' => 'asset', 'nature' => 'debit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'fixed_assets'],
            ['code' => '423', 'name' => 'Equipamento Básico', 'type' => 'asset', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '424', 'name' => 'Equipamento de Transporte', 'type' => 'asset', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            
            // ===== CLASSE 2 - PASSIVO =====
            // Fornecedores
            ['code' => '22', 'name' => 'Fornecedores', 'type' => 'liability', 'nature' => 'credit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'payables'],
            ['code' => '221', 'name' => 'Fornecedores c/c', 'type' => 'liability', 'nature' => 'credit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '2211', 'name' => 'Fornecedores Gerais', 'type' => 'liability', 'nature' => 'credit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            
            // Estado e Outros Entes Públicos
            ['code' => '24', 'name' => 'Estado e Outros Entes Públicos', 'type' => 'liability', 'nature' => 'credit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            
            // IVA
            ['code' => '2431', 'name' => 'IVA - Liquidado', 'type' => 'liability', 'nature' => 'credit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'vat_collected'],
            ['code' => '2432', 'name' => 'IVA - Dedutível', 'type' => 'asset', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'vat_paid'],
            ['code' => '2433', 'name' => 'IVA - Apuramento', 'type' => 'liability', 'nature' => 'credit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'vat_settlement'],
            
            // Retenções
            ['code' => '2441', 'name' => 'Retenções na Fonte - IRT', 'type' => 'liability', 'nature' => 'credit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'withholding_irt'],
            ['code' => '2442', 'name' => 'Retenções na Fonte - Serviços', 'type' => 'liability', 'nature' => 'credit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'withholding_services'],
            
            // Segurança Social
            ['code' => '2451', 'name' => 'INSS - Empregado', 'type' => 'liability', 'nature' => 'credit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'inss_employee'],
            ['code' => '2452', 'name' => 'INSS - Empregador', 'type' => 'liability', 'nature' => 'credit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'inss_employer'],
            
            // Pessoal
            ['code' => '23', 'name' => 'Pessoal', 'type' => 'liability', 'nature' => 'credit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '231', 'name' => 'Remunerações a Pagar', 'type' => 'liability', 'nature' => 'credit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'salaries_payable'],
            ['code' => '232', 'name' => 'Adiantamentos', 'type' => 'asset', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            
            // Empréstimos
            ['code' => '25', 'name' => 'Financiamentos Obtidos', 'type' => 'liability', 'nature' => 'credit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '251', 'name' => 'Empréstimos Bancários', 'type' => 'liability', 'nature' => 'credit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            
            // ===== CLASSE 5 - CAPITAL PRÓPRIO =====
            ['code' => '51', 'name' => 'Capital', 'type' => 'equity', 'nature' => 'credit', 'level' => 1, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '511', 'name' => 'Capital Social', 'type' => 'equity', 'nature' => 'credit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'share_capital'],
            ['code' => '56', 'name' => 'Reservas', 'type' => 'equity', 'nature' => 'credit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '561', 'name' => 'Reservas Legais', 'type' => 'equity', 'nature' => 'credit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '59', 'name' => 'Resultados Transitados', 'type' => 'equity', 'nature' => 'credit', 'level' => 1, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'retained_earnings'],
            
            // ===== CLASSE 7 - RENDIMENTOS =====
            ['code' => '71', 'name' => 'Vendas', 'type' => 'revenue', 'nature' => 'credit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'sales'],
            ['code' => '711', 'name' => 'Vendas de Mercadorias', 'type' => 'revenue', 'nature' => 'credit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '7111', 'name' => 'Vendas de Mercadorias - Mercado Interno', 'type' => 'revenue', 'nature' => 'credit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '72', 'name' => 'Prestações de Serviços', 'type' => 'revenue', 'nature' => 'credit', 'level' => 1, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'services'],
            ['code' => '721', 'name' => 'Serviços Prestados', 'type' => 'revenue', 'nature' => 'credit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '78', 'name' => 'Outros Rendimentos', 'type' => 'revenue', 'nature' => 'credit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '781', 'name' => 'Rendimentos Suplementares', 'type' => 'revenue', 'nature' => 'credit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            
            // ===== CLASSE 6 - GASTOS =====
            ['code' => '61', 'name' => 'Custo das Mercadorias Vendidas', 'type' => 'expense', 'nature' => 'debit', 'level' => 1, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'cogs'],
            ['code' => '611', 'name' => 'CMVMC', 'type' => 'expense', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            
            // Fornecimentos e Serviços Externos
            ['code' => '62', 'name' => 'Fornecimentos e Serviços Externos', 'type' => 'expense', 'nature' => 'debit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '621', 'name' => 'Subcontratos', 'type' => 'expense', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '622', 'name' => 'Serviços Especializados', 'type' => 'expense', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '6221', 'name' => 'Trabalhos Especializados', 'type' => 'expense', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '6222', 'name' => 'Publicidade e Propaganda', 'type' => 'expense', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '623', 'name' => 'Materiais', 'type' => 'expense', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '6231', 'name' => 'Ferramentas e Utensílios', 'type' => 'expense', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '6232', 'name' => 'Material de Escritório', 'type' => 'expense', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '625', 'name' => 'Deslocações e Estadas', 'type' => 'expense', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '626', 'name' => 'Serviços Diversos', 'type' => 'expense', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '6261', 'name' => 'Rendas e Alugueres', 'type' => 'expense', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '6262', 'name' => 'Comunicações', 'type' => 'expense', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '6263', 'name' => 'Seguros', 'type' => 'expense', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            
            // Gastos com Pessoal
            ['code' => '63', 'name' => 'Gastos com Pessoal', 'type' => 'expense', 'nature' => 'debit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'payroll'],
            ['code' => '631', 'name' => 'Remunerações do Pessoal', 'type' => 'expense', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '6311', 'name' => 'Remunerações Certas e Permanentes', 'type' => 'expense', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '6312', 'name' => 'Subsídios', 'type' => 'expense', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '635', 'name' => 'Encargos sobre Remunerações', 'type' => 'expense', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '6351', 'name' => 'Encargos INSS', 'type' => 'expense', 'nature' => 'debit', 'level' => 3, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            
            // Depreciações
            ['code' => '64', 'name' => 'Gastos de Depreciação e Amortização', 'type' => 'expense', 'nature' => 'debit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '642', 'name' => 'Depreciações - Imobilizações Corpóreas', 'type' => 'expense', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'depreciation'],
            
            // Outros Gastos
            ['code' => '68', 'name' => 'Outros Gastos', 'type' => 'expense', 'nature' => 'debit', 'level' => 1, 'is_view' => true, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '681', 'name' => 'Impostos', 'type' => 'expense', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            ['code' => '688', 'name' => 'Outros Gastos e Perdas', 'type' => 'expense', 'nature' => 'debit', 'level' => 2, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => null],
            
            // ===== CLASSE 8 - RESULTADOS =====
            ['code' => '81', 'name' => 'Resultado Líquido do Exercício', 'type' => 'equity', 'nature' => 'credit', 'level' => 1, 'is_view' => false, 'blocked' => false, 'parent_id' => null, 'integration_key' => 'net_income'],
        ];
    }
}
