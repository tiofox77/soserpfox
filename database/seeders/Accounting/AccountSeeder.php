<?php

namespace Database\Seeders\Accounting;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Plano de contas — PGC-AO (Plano Geral de Contabilidade de Angola, Decreto 82/01).
 *
 * Estrutura de classes (PGC-AO, ao contrário do SNC português):
 *   1 Meios Fixos e Investimentos | 2 Existências | 3 Terceiros
 *   4 Meios Monetários | 5 Capital e Reservas
 *   6 Proveitos e Ganhos por Natureza | 7 Custos e Perdas por Natureza | 8 Resultados
 *
 * O campo `type` (asset/liability/equity/revenue/expense) é a âncora usada pelas
 * demonstrações financeiras (agnósticas ao plano). Os `integration_key` são preservados
 * para que a integração faturas→lançamentos continue a mapear as contas certas.
 *
 * NOTA: os códigos de IVA/retenções (classe 3.4 Estado) seguem uma convenção corrente
 * pós-Lei do IVA 2019 e devem ser validados pelo contabilista da empresa.
 */
class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = \App\Models\Tenant::where('is_active', true)->get();
        if ($tenants->isEmpty()) {
            return;
        }

        $accounts = $this->getPGCAccounts();

        foreach ($tenants as $tenant) {
            $existing = DB::table('accounting_accounts')->where('tenant_id', $tenant->id)->count();
            if ($existing > 0) {
                // Não sobrescreve planos existentes (tenants antigos mantêm o seu plano).
                continue;
            }
            $this->insertFor($tenant->id, $accounts);
        }
    }

    /**
     * Corre para uma empresa específica.
     *
     * INCREMENTAL: acrescenta apenas as contas do PGC-AO que ainda não existem
     * (comparadas pelo código). Nunca toca nas contas já existentes — o cliente
     * pode tê-las renomeado ou ter contas próprias — e nunca apaga nada.
     *
     * Antes desistia se existisse pelo menos uma conta, pelo que empresas
     * antigas nunca recebiam contas novas acrescentadas ao plano.
     *
     * @return int Número de contas efectivamente criadas.
     */
    public function runForTenant(int $tenantId): int
    {
        return $this->insertFor($tenantId, $this->getPGCAccounts());
    }

    private function insertFor(int $tenantId, array $accounts): int
    {
        $existentes = DB::table('accounting_accounts')
            ->where('tenant_id', $tenantId)
            ->pluck('code')
            ->all();
        $existentes = array_flip($existentes);

        $criadas = 0;
        foreach ($accounts as $account) {
            if (isset($existentes[$account['code']])) {
                continue;
            }

            DB::table('accounting_accounts')->insert(array_merge($account, [
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $criadas++;
        }

        return $criadas;
    }

    /**
     * Plano de contas PGC-AO (Angola). type: asset|liability|equity|revenue|expense.
     */
    private function getPGCAccounts(): array
    {
        $a = fn ($code, $name, $type, $nature, $level, $view = false, $key = null) => [
            'code' => $code, 'name' => $name, 'type' => $type, 'nature' => $nature,
            'level' => $level, 'is_view' => $view, 'blocked' => false, 'parent_id' => null,
            'integration_key' => $key,
        ];

        return [
            // ===================== CLASSE 1 — MEIOS FIXOS E INVESTIMENTOS =====================
            $a('11', 'Imobilizações Corpóreas', 'asset', 'debit', 1, true, 'fixed_assets'),
            $a('112', 'Edifícios e Outras Construções', 'asset', 'debit', 2),
            $a('113', 'Equipamento Básico', 'asset', 'debit', 2),
            $a('114', 'Equipamento de Transporte', 'asset', 'debit', 2),
            $a('115', 'Equipamento Administrativo', 'asset', 'debit', 2),
            $a('12', 'Imobilizações Incorpóreas', 'asset', 'debit', 1, true),
            $a('13', 'Investimentos Financeiros', 'asset', 'debit', 1, true),
            $a('18', 'Amortizações Acumuladas', 'asset', 'credit', 1, true, 'depreciation_accumulated'),
            $a('182', 'Amortizações Acum. — Imobilizações Corpóreas', 'asset', 'credit', 2),

            // ===================== CLASSE 2 — EXISTÊNCIAS =====================
            $a('21', 'Compras', 'asset', 'debit', 1, true),
            $a('211', 'Compras de Mercadorias', 'asset', 'debit', 2),
            $a('22', 'Matérias-Primas, Subsidiárias e de Consumo', 'asset', 'debit', 1, true),
            $a('26', 'Mercadorias', 'asset', 'debit', 1, true, 'inventory'),
            $a('261', 'Mercadorias em Armazém', 'asset', 'debit', 2),

            // ===================== CLASSE 3 — TERCEIROS =====================
            $a('31', 'Clientes', 'asset', 'debit', 1, true, 'receivables'),
            $a('311', 'Clientes c/c', 'asset', 'debit', 2),
            $a('3111', 'Clientes Gerais', 'asset', 'debit', 3),
            $a('318', 'Clientes de Cobrança Duvidosa', 'asset', 'debit', 2),
            $a('32', 'Fornecedores', 'liability', 'credit', 1, true, 'payables'),
            $a('321', 'Fornecedores c/c', 'liability', 'credit', 2),
            $a('3211', 'Fornecedores Gerais', 'liability', 'credit', 3),
            $a('33', 'Empréstimos', 'liability', 'credit', 1, true),
            $a('331', 'Empréstimos Bancários', 'liability', 'credit', 2),
            // Estado e Outros Entes Públicos
            $a('34', 'Estado', 'liability', 'credit', 1, true),
            $a('341', 'IVA', 'liability', 'credit', 2, true),
            $a('3411', 'IVA Liquidado', 'liability', 'credit', 3, false, 'vat_collected'),
            $a('3412', 'IVA Dedutível', 'asset', 'debit', 3, false, 'vat_paid'),
            $a('3413', 'IVA Apuramento', 'liability', 'credit', 3, false, 'vat_settlement'),
            $a('342', 'Retenções na Fonte', 'liability', 'credit', 2, true),
            $a('3421', 'Retenção na Fonte — IRT', 'liability', 'credit', 3, false, 'withholding_irt'),
            $a('3422', 'Retenção na Fonte — Serviços', 'liability', 'credit', 3, false, 'withholding_services'),
            $a('343', 'Segurança Social (INSS)', 'liability', 'credit', 2, true),
            $a('3431', 'INSS — Empregado', 'liability', 'credit', 3, false, 'inss_employee'),
            $a('3432', 'INSS — Empregador', 'liability', 'credit', 3, false, 'inss_employer'),
            // Pessoal
            $a('36', 'Pessoal', 'liability', 'credit', 1, true),
            $a('361', 'Remunerações a Pagar', 'liability', 'credit', 2, false, 'salaries_payable'),
            $a('362', 'Adiantamentos ao Pessoal', 'asset', 'debit', 2),

            // ===================== CLASSE 4 — MEIOS MONETÁRIOS =====================
            $a('43', 'Depósitos à Ordem', 'asset', 'debit', 1, true, 'bank'),
            $a('431', 'Banco BFA', 'asset', 'debit', 2),
            $a('432', 'Banco BAI', 'asset', 'debit', 2),
            $a('45', 'Caixa', 'asset', 'debit', 1, true, 'cash'),
            $a('451', 'Caixa Principal', 'asset', 'debit', 2),

            // ===================== CLASSE 5 — CAPITAL E RESERVAS =====================
            $a('51', 'Capital', 'equity', 'credit', 1, true),
            $a('511', 'Capital Social', 'equity', 'credit', 2, false, 'share_capital'),
            $a('55', 'Reservas', 'equity', 'credit', 1, true),
            $a('551', 'Reservas Legais', 'equity', 'credit', 2),
            $a('56', 'Resultados Transitados', 'equity', 'credit', 1, false, 'retained_earnings'),

            // ===================== CLASSE 6 — PROVEITOS E GANHOS POR NATUREZA =====================
            $a('61', 'Vendas', 'revenue', 'credit', 1, true, 'sales'),
            $a('611', 'Vendas de Mercadorias', 'revenue', 'credit', 2),
            $a('62', 'Prestações de Serviços', 'revenue', 'credit', 1, true, 'services'),
            $a('621', 'Serviços Prestados', 'revenue', 'credit', 2),
            $a('68', 'Outros Proveitos e Ganhos', 'revenue', 'credit', 1, true),
            $a('681', 'Proveitos Suplementares', 'revenue', 'credit', 2),

            // ===================== CLASSE 7 — CUSTOS E PERDAS POR NATUREZA =====================
            $a('71', 'Custo das Existências Vendidas e Consumidas', 'expense', 'debit', 1, true, 'cogs'),
            $a('711', 'CEVC — Mercadorias', 'expense', 'debit', 2),
            $a('72', 'Fornecimentos e Serviços de Terceiros', 'expense', 'debit', 1, true),
            $a('721', 'Subcontratos', 'expense', 'debit', 2),
            $a('722', 'Serviços Especializados', 'expense', 'debit', 2),
            $a('7221', 'Trabalhos Especializados', 'expense', 'debit', 3),
            $a('7222', 'Publicidade e Propaganda', 'expense', 'debit', 3),
            $a('723', 'Materiais', 'expense', 'debit', 2),
            $a('725', 'Deslocações e Estadas', 'expense', 'debit', 2),
            $a('726', 'Serviços Diversos', 'expense', 'debit', 2),
            $a('7261', 'Rendas e Alugueres', 'expense', 'debit', 3),
            $a('7262', 'Comunicações', 'expense', 'debit', 3),
            $a('7263', 'Seguros', 'expense', 'debit', 3),
            $a('73', 'Custos com o Pessoal', 'expense', 'debit', 1, true, 'payroll'),
            $a('731', 'Remunerações do Pessoal', 'expense', 'debit', 2),
            $a('7311', 'Remunerações Certas e Permanentes', 'expense', 'debit', 3),
            $a('7312', 'Subsídios', 'expense', 'debit', 3),
            $a('735', 'Encargos sobre Remunerações', 'expense', 'debit', 2),
            $a('7351', 'Encargos INSS (Empregador)', 'expense', 'debit', 3),
            $a('77', 'Amortizações do Exercício', 'expense', 'debit', 1, true, 'depreciation'),
            $a('772', 'Amortizações — Imobilizações Corpóreas', 'expense', 'debit', 2),
            $a('78', 'Outros Custos e Perdas', 'expense', 'debit', 1, true),
            $a('781', 'Impostos', 'expense', 'debit', 2),
            $a('788', 'Outros Custos e Perdas', 'expense', 'debit', 2),

            // ===================== CLASSE 8 — RESULTADOS =====================
            $a('88', 'Resultado Líquido do Exercício', 'equity', 'credit', 1, false, 'net_income'),
        ];
    }
}
