<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinancialStatementMappingSeeder extends Seeder
{
    /**
     * Seed financial statement mappings for Angola SNC
     */
    public function run(): void
    {
        // Limpar tabela
        DB::table('financial_statement_mappings')->truncate();
        
        // BALANÇO - Mapeamento de contas para rubricas
        $balanceSheetMappings = [
            // ACTIVO CORRENTE
            ['statement_type' => 'balance_sheet', 'section' => 'current_assets', 'subsection' => 'cash', 'account_pattern' => '11%', 'order' => 1],
            ['statement_type' => 'balance_sheet', 'section' => 'current_assets', 'subsection' => 'bank', 'account_pattern' => '12%', 'order' => 2],
            ['statement_type' => 'balance_sheet', 'section' => 'current_assets', 'subsection' => 'clients', 'account_pattern' => '21%', 'order' => 3],
            ['statement_type' => 'balance_sheet', 'section' => 'current_assets', 'subsection' => 'inventory', 'account_pattern' => '3%', 'order' => 4],
            ['statement_type' => 'balance_sheet', 'section' => 'current_assets', 'subsection' => 'other_current', 'account_pattern' => '13%', 'order' => 5],
            ['statement_type' => 'balance_sheet', 'section' => 'current_assets', 'subsection' => 'other_current', 'account_pattern' => '14%', 'order' => 6],
            
            // ACTIVO NÃO CORRENTE
            ['statement_type' => 'balance_sheet', 'section' => 'non_current_assets', 'subsection' => 'investments', 'account_pattern' => '41%', 'order' => 10],
            ['statement_type' => 'balance_sheet', 'section' => 'non_current_assets', 'subsection' => 'fixed_assets', 'account_pattern' => '43%', 'order' => 11],
            ['statement_type' => 'balance_sheet', 'section' => 'non_current_assets', 'subsection' => 'intangible', 'account_pattern' => '44%', 'order' => 12],
            
            // PASSIVO CORRENTE
            ['statement_type' => 'balance_sheet', 'section' => 'current_liabilities', 'subsection' => 'suppliers', 'account_pattern' => '22%', 'order' => 20],
            ['statement_type' => 'balance_sheet', 'section' => 'current_liabilities', 'subsection' => 'state', 'account_pattern' => '24%', 'order' => 21],
            ['statement_type' => 'balance_sheet', 'section' => 'current_liabilities', 'subsection' => 'other_current', 'account_pattern' => '23%', 'order' => 22],
            ['statement_type' => 'balance_sheet', 'section' => 'current_liabilities', 'subsection' => 'other_current', 'account_pattern' => '27%', 'order' => 23],
            
            // PASSIVO NÃO CORRENTE
            ['statement_type' => 'balance_sheet', 'section' => 'non_current_liabilities', 'subsection' => 'loans', 'account_pattern' => '25%', 'order' => 30],
            ['statement_type' => 'balance_sheet', 'section' => 'non_current_liabilities', 'subsection' => 'financing', 'account_pattern' => '26%', 'order' => 31],
            ['statement_type' => 'balance_sheet', 'section' => 'non_current_liabilities', 'subsection' => 'provisions', 'account_pattern' => '29%', 'order' => 32],
            
            // CAPITAL PRÓPRIO
            ['statement_type' => 'balance_sheet', 'section' => 'equity', 'subsection' => 'capital', 'account_pattern' => '51%', 'order' => 40],
            ['statement_type' => 'balance_sheet', 'section' => 'equity', 'subsection' => 'reserves', 'account_pattern' => '55%', 'order' => 41],
            ['statement_type' => 'balance_sheet', 'section' => 'equity', 'subsection' => 'retained', 'account_pattern' => '56%', 'order' => 42],
            ['statement_type' => 'balance_sheet', 'section' => 'equity', 'subsection' => 'net_income', 'account_pattern' => '81%', 'order' => 43],
        ];
        
        // DR POR NATUREZA - Mapeamento
        $incomeStatementNatureMappings = [
            // RENDIMENTOS
            ['statement_type' => 'income_nature', 'section' => 'revenues', 'subsection' => 'sales', 'account_pattern' => '71%', 'order' => 1],
            ['statement_type' => 'income_nature', 'section' => 'revenues', 'subsection' => 'services', 'account_pattern' => '72%', 'order' => 2],
            ['statement_type' => 'income_nature', 'section' => 'revenues', 'subsection' => 'subsidies', 'account_pattern' => '75%', 'order' => 3],
            ['statement_type' => 'income_nature', 'section' => 'revenues', 'subsection' => 'other', 'account_pattern' => '74%', 'order' => 4],
            
            // GASTOS
            ['statement_type' => 'income_nature', 'section' => 'expenses', 'subsection' => 'cogs', 'account_pattern' => '61%', 'order' => 10],
            ['statement_type' => 'income_nature', 'section' => 'expenses', 'subsection' => 'fst', 'account_pattern' => '62%', 'order' => 11],
            ['statement_type' => 'income_nature', 'section' => 'expenses', 'subsection' => 'personnel', 'account_pattern' => '63%', 'order' => 12],
            ['statement_type' => 'income_nature', 'section' => 'expenses', 'subsection' => 'depreciation', 'account_pattern' => '64%', 'order' => 13],
            ['statement_type' => 'income_nature', 'section' => 'expenses', 'subsection' => 'impairments', 'account_pattern' => '65%', 'order' => 14],
            ['statement_type' => 'income_nature', 'section' => 'expenses', 'subsection' => 'provisions', 'account_pattern' => '67%', 'order' => 15],
            ['statement_type' => 'income_nature', 'section' => 'expenses', 'subsection' => 'other', 'account_pattern' => '68%', 'order' => 16],
            
            // FINANCEIROS
            ['statement_type' => 'income_nature', 'section' => 'financial', 'subsection' => 'income', 'account_pattern' => '79%', 'order' => 20],
            ['statement_type' => 'income_nature', 'section' => 'financial', 'subsection' => 'expenses', 'account_pattern' => '69%', 'order' => 21],
            
            // IMPOSTOS
            ['statement_type' => 'income_nature', 'section' => 'taxes', 'subsection' => 'income_tax', 'account_pattern' => '89%', 'order' => 30],
        ];
        
        // DR POR FUNÇÕES - Alocações padrão
        $allocationDefaults = [
            // CMVMC - 100% Custo Vendas
            ['account_pattern' => '61%', 'function' => 'sales_cost', 'percent' => 100],
            
            // FST - Distribuído
            ['account_pattern' => '62%', 'function' => 'sales_cost', 'percent' => 30],
            ['account_pattern' => '62%', 'function' => 'distribution', 'percent' => 30],
            ['account_pattern' => '62%', 'function' => 'administrative', 'percent' => 40],
            
            // Pessoal - Distribuído
            ['account_pattern' => '63%', 'function' => 'sales_cost', 'percent' => 25],
            ['account_pattern' => '63%', 'function' => 'distribution', 'percent' => 25],
            ['account_pattern' => '63%', 'function' => 'administrative', 'percent' => 40],
            ['account_pattern' => '63%', 'function' => 'rd', 'percent' => 10],
            
            // Depreciações - Distribuído
            ['account_pattern' => '64%', 'function' => 'sales_cost', 'percent' => 40],
            ['account_pattern' => '64%', 'function' => 'distribution', 'percent' => 20],
            ['account_pattern' => '64%', 'function' => 'administrative', 'percent' => 40],
            
            // Imparidades - 100% Administrativo
            ['account_pattern' => '65%', 'function' => 'administrative', 'percent' => 100],
            
            // Provisões - 100% Administrativo
            ['account_pattern' => '67%', 'function' => 'administrative', 'percent' => 100],
        ];
        
        // FLUXOS DE CAIXA - Identificação de contas
        $cashFlowMappings = [
            // Caixa e Equivalentes
            ['statement_type' => 'cash_flow', 'section' => 'cash', 'subsection' => 'cash', 'account_pattern' => '11%', 'order' => 1],
            ['statement_type' => 'cash_flow', 'section' => 'cash', 'subsection' => 'bank', 'account_pattern' => '12%', 'order' => 2],
            
            // Atividades Operacionais - Ajustamentos
            ['statement_type' => 'cash_flow', 'section' => 'operating', 'subsection' => 'depreciation', 'account_pattern' => '64%', 'order' => 10],
            ['statement_type' => 'cash_flow', 'section' => 'operating', 'subsection' => 'impairments', 'account_pattern' => '65%', 'order' => 11],
            ['statement_type' => 'cash_flow', 'section' => 'operating', 'subsection' => 'provisions', 'account_pattern' => '67%', 'order' => 12],
            
            // Capital Circulante
            ['statement_type' => 'cash_flow', 'section' => 'operating', 'subsection' => 'clients', 'account_pattern' => '21%', 'order' => 20],
            ['statement_type' => 'cash_flow', 'section' => 'operating', 'subsection' => 'inventory', 'account_pattern' => '3%', 'order' => 21],
            ['statement_type' => 'cash_flow', 'section' => 'operating', 'subsection' => 'suppliers', 'account_pattern' => '22%', 'order' => 22],
            ['statement_type' => 'cash_flow', 'section' => 'operating', 'subsection' => 'state', 'account_pattern' => '24%', 'order' => 23],
            
            // Atividades de Investimento
            ['statement_type' => 'cash_flow', 'section' => 'investing', 'subsection' => 'investments', 'account_pattern' => '41%', 'order' => 30],
            ['statement_type' => 'cash_flow', 'section' => 'investing', 'subsection' => 'fixed_assets', 'account_pattern' => '43%', 'order' => 31],
            ['statement_type' => 'cash_flow', 'section' => 'investing', 'subsection' => 'intangible', 'account_pattern' => '44%', 'order' => 32],
            
            // Atividades de Financiamento
            ['statement_type' => 'cash_flow', 'section' => 'financing', 'subsection' => 'loans', 'account_pattern' => '25%', 'order' => 40],
            ['statement_type' => 'cash_flow', 'section' => 'financing', 'subsection' => 'financing', 'account_pattern' => '26%', 'order' => 41],
            ['statement_type' => 'cash_flow', 'section' => 'financing', 'subsection' => 'capital', 'account_pattern' => '51%', 'order' => 42],
            ['statement_type' => 'cash_flow', 'section' => 'financing', 'subsection' => 'dividends', 'account_pattern' => '59%', 'order' => 43],
        ];
        
        // Inserir dados
        foreach (array_merge($balanceSheetMappings, $incomeStatementNatureMappings, $cashFlowMappings) as $mapping) {
            DB::table('financial_statement_mappings')->insert([
                'statement_type' => $mapping['statement_type'],
                'section' => $mapping['section'],
                'subsection' => $mapping['subsection'],
                'account_pattern' => $mapping['account_pattern'],
                'display_order' => $mapping['order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('✅ Financial Statement Mappings seeded successfully!');
        $this->command->info('   - Balance Sheet: ' . count($balanceSheetMappings) . ' mappings');
        $this->command->info('   - Income Nature: ' . count($incomeStatementNatureMappings) . ' mappings');
        $this->command->info('   - Cash Flow: ' . count($cashFlowMappings) . ' mappings');
    }
}
