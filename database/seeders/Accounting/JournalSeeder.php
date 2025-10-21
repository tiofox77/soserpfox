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
                // Diários Principais
                [
                    'code' => '01',
                    'name' => 'Diário Geral',
                    'type' => 'general',
                    'sequence_prefix' => 'DG-',
                    'last_number' => 0,
                    'default_debit_account_id' => null,
                    'default_credit_account_id' => null,
                    'active' => true,
                ],
                [
                    'code' => '02',
                    'name' => 'Diário de Caixa',
                    'type' => 'cash',
                    'sequence_prefix' => 'CX-',
                    'last_number' => 0,
                    'default_debit_account_id' => $caixaConta?->id,
                    'default_credit_account_id' => $caixaConta?->id,
                    'active' => true,
                ],
                [
                    'code' => '03',
                    'name' => 'Diário de Bancos',
                    'type' => 'bank',
                    'sequence_prefix' => 'BC-',
                    'last_number' => 0,
                    'default_debit_account_id' => $bancoConta?->id,
                    'default_credit_account_id' => $bancoConta?->id,
                    'active' => true,
                ],
                [
                    'code' => '04',
                    'name' => 'Diário de Vendas',
                    'type' => 'sale',
                    'sequence_prefix' => 'VD-',
                    'last_number' => 0,
                    'default_debit_account_id' => $clientesConta?->id,
                    'default_credit_account_id' => null,
                    'active' => true,
                ],
                [
                    'code' => '05',
                    'name' => 'Diário de Compras',
                    'type' => 'purchase',
                    'sequence_prefix' => 'CP-',
                    'last_number' => 0,
                    'default_debit_account_id' => null,
                    'default_credit_account_id' => $fornecedoresConta?->id,
                    'active' => true,
                ],
                
                // Diários de Controle e Gestão
                [
                    'code' => '06',
                    'name' => 'Diário de Salários e Ordenados',
                    'type' => 'payroll',
                    'sequence_prefix' => 'SAL-',
                    'last_number' => 0,
                    'default_debit_account_id' => null,
                    'default_credit_account_id' => null,
                    'active' => true,
                ],
                [
                    'code' => '07',
                    'name' => 'Diário de IVA',
                    'type' => 'tax',
                    'sequence_prefix' => 'IVA-',
                    'last_number' => 0,
                    'default_debit_account_id' => null,
                    'default_credit_account_id' => null,
                    'active' => true,
                ],
                [
                    'code' => '08',
                    'name' => 'Diário de Depreciações e Amortizações',
                    'type' => 'depreciation',
                    'sequence_prefix' => 'DEP-',
                    'last_number' => 0,
                    'default_debit_account_id' => null,
                    'default_credit_account_id' => null,
                    'active' => true,
                ],
                
                // Diários Especiais
                [
                    'code' => '09',
                    'name' => 'Diário de Operações Diversas',
                    'type' => 'miscellaneous',
                    'sequence_prefix' => 'OD-',
                    'last_number' => 0,
                    'default_debit_account_id' => null,
                    'default_credit_account_id' => null,
                    'active' => true,
                ],
                [
                    'code' => '10',
                    'name' => 'Diário de Ajustes e Correções',
                    'type' => 'adjustment',
                    'sequence_prefix' => 'AJ-',
                    'last_number' => 0,
                    'default_debit_account_id' => null,
                    'default_credit_account_id' => null,
                    'active' => true,
                ],
                [
                    'code' => '11',
                    'name' => 'Diário de Regularização',
                    'type' => 'regularization',
                    'sequence_prefix' => 'REG-',
                    'last_number' => 0,
                    'default_debit_account_id' => null,
                    'default_credit_account_id' => null,
                    'active' => true,
                ],
                [
                    'code' => '12',
                    'name' => 'Diário de Abertura',
                    'type' => 'opening',
                    'sequence_prefix' => 'ABT-',
                    'last_number' => 0,
                    'default_debit_account_id' => null,
                    'default_credit_account_id' => null,
                    'active' => true,
                ],
                [
                    'code' => '13',
                    'name' => 'Diário de Encerramento',
                    'type' => 'closing',
                    'sequence_prefix' => 'ENC-',
                    'last_number' => 0,
                    'default_debit_account_id' => null,
                    'default_credit_account_id' => null,
                    'active' => true,
                ],
            ];
            
            // Limpar diários antigos (exceto os que já têm lançamentos)
            Journal::where('tenant_id', $tenant->id)
                ->doesntHave('moves')
                ->delete();
            
            // Criar/atualizar diários padrão
            foreach ($journals as $journalData) {
                Journal::updateOrInsert(
                    [
                        'tenant_id' => $tenant->id,
                        'code' => $journalData['code'],
                    ],
                    $journalData + ['tenant_id' => $tenant->id]
                );
            }
            
            echo "✅ Criados 13 diários contabilísticos para {$tenant->name}\n";
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
            // Diários Principais
            [
                'code' => '01',
                'name' => 'Diário Geral',
                'type' => 'general',
                'sequence_prefix' => 'DG-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '02',
                'name' => 'Diário de Caixa',
                'type' => 'cash',
                'sequence_prefix' => 'CX-',
                'last_number' => 0,
                'default_debit_account_id' => $caixaConta?->id,
                'default_credit_account_id' => $caixaConta?->id,
                'active' => true,
            ],
            [
                'code' => '03',
                'name' => 'Diário de Bancos',
                'type' => 'bank',
                'sequence_prefix' => 'BC-',
                'last_number' => 0,
                'default_debit_account_id' => $bancoConta?->id,
                'default_credit_account_id' => $bancoConta?->id,
                'active' => true,
            ],
            [
                'code' => '04',
                'name' => 'Diário de Vendas',
                'type' => 'sale',
                'sequence_prefix' => 'VD-',
                'last_number' => 0,
                'default_debit_account_id' => $clientesConta?->id,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '05',
                'name' => 'Diário de Compras',
                'type' => 'purchase',
                'sequence_prefix' => 'CP-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => $fornecedoresConta?->id,
                'active' => true,
            ],
            
            // Diários de Controle e Gestão
            [
                'code' => '06',
                'name' => 'Diário de Salários e Ordenados',
                'type' => 'payroll',
                'sequence_prefix' => 'SAL-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '07',
                'name' => 'Diário de IVA',
                'type' => 'tax',
                'sequence_prefix' => 'IVA-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '08',
                'name' => 'Diário de Depreciações e Amortizações',
                'type' => 'depreciation',
                'sequence_prefix' => 'DEP-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            
            // Diários Especiais
            [
                'code' => '09',
                'name' => 'Diário de Operações Diversas',
                'type' => 'miscellaneous',
                'sequence_prefix' => 'OD-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '10',
                'name' => 'Diário de Ajustes e Correções',
                'type' => 'adjustment',
                'sequence_prefix' => 'AJ-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '11',
                'name' => 'Diário de Regularização',
                'type' => 'regularization',
                'sequence_prefix' => 'REG-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '12',
                'name' => 'Diário de Abertura',
                'type' => 'opening',
                'sequence_prefix' => 'ABT-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
            [
                'code' => '13',
                'name' => 'Diário de Encerramento',
                'type' => 'closing',
                'sequence_prefix' => 'ENC-',
                'last_number' => 0,
                'default_debit_account_id' => null,
                'default_credit_account_id' => null,
                'active' => true,
            ],
        ];
        
        foreach ($journals as $journalData) {
            Journal::updateOrInsert(
                [
                    'tenant_id' => $tenantId,
                    'code' => $journalData['code'],
                ],
                $journalData + ['tenant_id' => $tenantId]
            );
        }
    }
}
