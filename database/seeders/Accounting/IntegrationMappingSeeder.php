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

        // Buscar contas por integration_key (agnóstico ao plano: SNC ou PGC-AO).
        // A âncora pode estar numa conta-mãe (is_view). Como os lançamentos só podem cair
        // em contas movimentáveis (is_view=false) — senão o balanço, que filtra is_view=false,
        // não as apanharia — resolvemos para uma folha da subárvore (por prefixo de código).
        // Regras de resolução no próprio model, para o comando de reparação
        // (accounting:fix-integration-keys) e este seeder não divergirem.
        $byKey = fn ($key) => IntegrationMapping::resolveAccount($tenantId, $key);
        $clientesAccount     = $byKey('receivables');
        $fornecedoresAccount = $byKey('payables');
        $vendasAccount       = $byKey('sales');
        $comprasAccount      = $byKey('cogs');
        $caixaAccount        = $byKey('cash');
        $bancoAccount        = $byKey('bank');
        $ivaLiquidadoAccount = $byKey('vat_collected');
        $ivaDedativelAccount = $byKey('vat_paid');

        // Buscar diários.
        // O ENUM de accounting_journals.type é SINGULAR ('sale', 'purchase'):
        // procurar 'sales'/'purchases' nunca encontrava nada, pelo que os
        // mapeamentos `invoice` e `purchase` não eram criados em tenant nenhum e
        // as facturas nunca chegavam à contabilidade. Aceita ambos, para o caso
        // de instalações antigas terem gravado o plural.
        $salesJournal = Journal::where('tenant_id', $tenantId)->whereIn('type', ['sale', 'sales'])->first();
        $purchasesJournal = Journal::where('tenant_id', $tenantId)->whereIn('type', ['purchase', 'purchases'])->first();
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

        // 1b. Nota de Crédito (NC) e Nota de Débito (ND)
        // Mesmas contas da factura: o sentido dos lançamentos é decidido no
        // IntegrationService (a NC espelha a factura, a ND acompanha-a). Quem
        // quiser uma conta própria de "Devoluções de vendas" muda o
        // credit_account_id do mapeamento credit_note.
        if ($salesJournal && $clientesAccount && $vendasAccount && $ivaLiquidadoAccount) {
            foreach (['credit_note', 'debit_note'] as $evento) {
                $mappings[] = [
                    'tenant_id' => $tenantId,
                    'event' => $evento,
                    'journal_id' => $salesJournal->id,
                    'debit_account_id' => $clientesAccount->id,   // Clientes
                    'credit_account_id' => $vendasAccount->id,    // Vendas / Devoluções
                    'vat_account_id' => $ivaLiquidadoAccount->id, // IVA Liquidado
                    'auto_post' => true,
                    'active' => true,
                ];
            }
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
