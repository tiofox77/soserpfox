<?php

namespace Database\Seeders\Accounting;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Accounting\IntegrationMapping;
use App\Models\Accounting\Account;
use App\Models\Accounting\Journal;

class IntegrationMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔗 Criando mapeamentos de integração...');

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->createMappingsForTenant($tenant);
        }

        $this->command->info('✅ Mapeamentos de integração criados com sucesso!');
    }

    protected function createMappingsForTenant(Tenant $tenant)
    {
        $tenantId = $tenant->id;

        // Buscar contas
        $clientesAccount = Account::where('tenant_id', $tenantId)->where('code', 'like', '211%')->first();
        $fornecedoresAccount = Account::where('tenant_id', $tenantId)->where('code', 'like', '221%')->first();
        $vendasAccount = Account::where('tenant_id', $tenantId)->where('code', 'like', '71%')->first();
        $comprasAccount = Account::where('tenant_id', $tenantId)->where('code', 'like', '61%')->first();
        $caixaAccount = Account::where('tenant_id', $tenantId)->where('code', 'like', '11%')->first();
        $bancoAccount = Account::where('tenant_id', $tenantId)->where('code', 'like', '12%')->first();
        $ivaLiquidadoAccount = Account::where('tenant_id', $tenantId)->where('code', 'like', '2433%')->first();
        $ivaDedativelAccount = Account::where('tenant_id', $tenantId)->where('code', 'like', '2432%')->first();

        // Buscar diários
        $salesJournal = Journal::where('tenant_id', $tenantId)->where('type', 'sales')->first();
        $purchasesJournal = Journal::where('tenant_id', $tenantId)->where('type', 'purchases')->first();
        $cashJournal = Journal::where('tenant_id', $tenantId)->where('type', 'cash')->first();
        $bankJournal = Journal::where('tenant_id', $tenantId)->where('type', 'bank')->first();

        $mappings = [];

        // 1. Fatura de Venda (FT/FR)
        if ($salesJournal && $clientesAccount && $vendasAccount && $ivaLiquidadoAccount) {
            $mappings[] = [
                'tenant_id' => $tenantId,
                'event' => 'invoice',
                'journal_id' => $salesJournal->id,
                'debit_account_id' => $clientesAccount->id, // Clientes
                'credit_account_id' => $vendasAccount->id, // Vendas
                'vat_account_id' => $ivaLiquidadoAccount->id, // IVA Liquidado
                'auto_post' => true,
                'active' => true,
            ];
        }

        // 2. Recebimento (RC)
        if ($cashJournal && $caixaAccount && $clientesAccount) {
            $mappings[] = [
                'tenant_id' => $tenantId,
                'event' => 'receipt_cash',
                'journal_id' => $cashJournal->id,
                'debit_account_id' => $caixaAccount->id, // Caixa
                'credit_account_id' => $clientesAccount->id, // Clientes
                'auto_post' => true,
                'active' => true,
            ];
        }

        if ($bankJournal && $bancoAccount && $clientesAccount) {
            $mappings[] = [
                'tenant_id' => $tenantId,
                'event' => 'receipt_bank',
                'journal_id' => $bankJournal->id,
                'debit_account_id' => $bancoAccount->id, // Banco
                'credit_account_id' => $clientesAccount->id, // Clientes
                'auto_post' => true,
                'active' => true,
            ];
        }

        // 3. Compra
        if ($purchasesJournal && $comprasAccount && $fornecedoresAccount && $ivaDedativelAccount) {
            $mappings[] = [
                'tenant_id' => $tenantId,
                'event' => 'purchase',
                'journal_id' => $purchasesJournal->id,
                'debit_account_id' => $comprasAccount->id, // Compras
                'credit_account_id' => $fornecedoresAccount->id, // Fornecedores
                'vat_account_id' => $ivaDedativelAccount->id, // IVA Dedutível
                'auto_post' => true,
                'active' => true,
            ];
        }

        // 4. Pagamento
        if ($bankJournal && $fornecedoresAccount && $bancoAccount) {
            $mappings[] = [
                'tenant_id' => $tenantId,
                'event' => 'payment_bank',
                'journal_id' => $bankJournal->id,
                'debit_account_id' => $fornecedoresAccount->id, // Fornecedores
                'credit_account_id' => $bancoAccount->id, // Banco
                'auto_post' => true,
                'active' => true,
            ];
        }

        if ($cashJournal && $fornecedoresAccount && $caixaAccount) {
            $mappings[] = [
                'tenant_id' => $tenantId,
                'event' => 'payment_cash',
                'journal_id' => $cashJournal->id,
                'debit_account_id' => $fornecedoresAccount->id, // Fornecedores
                'credit_account_id' => $caixaAccount->id, // Caixa
                'auto_post' => true,
                'active' => true,
            ];
        }

        // Inserir mapeamentos
        foreach ($mappings as $mapping) {
            IntegrationMapping::updateOrCreate(
                [
                    'tenant_id' => $mapping['tenant_id'],
                    'event' => $mapping['event'],
                ],
                $mapping
            );
        }

        $this->command->info("  ✓ {$tenant->name}: " . count($mappings) . " mapeamentos criados");
    }
}
