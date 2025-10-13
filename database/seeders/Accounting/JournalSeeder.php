<?php

namespace Database\Seeders\Accounting;

use Illuminate\Database\Seeder;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Account;
use App\Models\Tenant;

class JournalSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::where('is_active', true)->get();
        
        foreach ($tenants as $tenant) {
            // Buscar contas para defaults (se existirem)
            $caixaConta = Account::where('tenant_id', $tenant->id)->where('code', '11')->first();
            $bancoConta = Account::where('tenant_id', $tenant->id)->where('code', '12')->first();
            $clientesConta = Account::where('tenant_id', $tenant->id)->where('code', '21')->first();
            $fornecedoresConta = Account::where('tenant_id', $tenant->id)->where('code', '31')->first();
            
            $journals = [
                [
                    'code' => 'VEND',
                    'name' => 'Diário de Vendas',
                    'type' => 'sale',
                    'sequence_prefix' => 'VD-',
                    'last_number' => 0,
                    'default_debit_account_id' => $clientesConta?->id,
                    'default_credit_account_id' => null,
                    'active' => true,
                ],
                [
                    'code' => 'COMP',
                    'name' => 'Diário de Compras',
                    'type' => 'purchase',
                    'sequence_prefix' => 'CP-',
                    'last_number' => 0,
                    'default_debit_account_id' => null,
                    'default_credit_account_id' => $fornecedoresConta?->id,
                    'active' => true,
                ],
                [
                    'code' => 'CX',
                    'name' => 'Diário de Caixa',
                    'type' => 'cash',
                    'sequence_prefix' => 'CX-',
                    'last_number' => 0,
                    'default_debit_account_id' => $caixaConta?->id,
                    'default_credit_account_id' => $caixaConta?->id,
                    'active' => true,
                ],
                [
                    'code' => 'BCO',
                    'name' => 'Diário de Banco',
                    'type' => 'bank',
                    'sequence_prefix' => 'BC-',
                    'last_number' => 0,
                    'default_debit_account_id' => $bancoConta?->id,
                    'default_credit_account_id' => $bancoConta?->id,
                    'active' => true,
                ],
                [
                    'code' => 'SAL',
                    'name' => 'Diário de Salários',
                    'type' => 'payroll',
                    'sequence_prefix' => 'SAL-',
                    'last_number' => 0,
                    'default_debit_account_id' => null,
                    'default_credit_account_id' => null,
                    'active' => true,
                ],
                [
                    'code' => 'AJ',
                    'name' => 'Diário de Ajustes',
                    'type' => 'adjustment',
                    'sequence_prefix' => 'AJ-',
                    'last_number' => 0,
                    'default_debit_account_id' => null,
                    'default_credit_account_id' => null,
                    'active' => true,
                ],
            ];
            
            foreach ($journals as $journalData) {
                Journal::create([
                    'tenant_id' => $tenant->id,
                    ...$journalData
                ]);
            }
            
            echo "✅ Criados 6 diários contabilísticos para {$tenant->name}\n";
        }
    }
    
    /**
     * Run seeder for a specific tenant (used when creating new company)
     */
    public function runForTenant(int $tenantId): void
    {
        $caixaConta = Account::where('tenant_id', $tenantId)->where('code', '111')->first();
        $bancoConta = Account::where('tenant_id', $tenantId)->where('code', '112')->first();
        $clientesConta = Account::where('tenant_id', $tenantId)->where('code', '21')->first();
        $fornecedoresConta = Account::where('tenant_id', $tenantId)->where('code', '22')->first();
        
        $journals = [
            [
                'code' => 'VEND',
                'name' => 'Diário de Vendas',
                'type' => 'sale',
                'sequence_prefix' => 'VD-',
                'last_number' => 0,
                'default_debit_account_id' => $clientesConta?->id,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => 'COMP',
                'name' => 'Diário de Compras',
                'type' => 'purchase',
                'sequence_prefix' => 'CP-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => $fornecedoresConta?->id,
                'active' => true,
            ],
            [
                'code' => 'CX',
                'name' => 'Diário de Caixa',
                'type' => 'cash',
                'sequence_prefix' => 'CX-',
                'last_number' => 0,
                'default_debit_account_id' => $caixaConta?->id,
                'default_credit_account_id' => $caixaConta?->id,
                'active' => true,
            ],
            [
                'code' => 'BCO',
                'name' => 'Diário de Banco',
                'type' => 'bank',
                'sequence_prefix' => 'BC-',
                'last_number' => 0,
                'default_debit_account_id' => $bancoConta?->id,
                'default_credit_account_id' => $bancoConta?->id,
                'active' => true,
            ],
            [
                'code' => 'SAL',
                'name' => 'Diário de Salários',
                'type' => 'payroll',
                'sequence_prefix' => 'SAL-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => 'AJ',
                'name' => 'Diário de Ajustes',
                'type' => 'adjustment',
                'sequence_prefix' => 'AJ-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
        ];
        
        foreach ($journals as $journalData) {
            Journal::create([
                'tenant_id' => $tenantId,
                ...$journalData
            ]);
        }
    }
}
