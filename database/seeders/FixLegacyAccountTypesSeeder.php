<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Corrige a coluna `type` do plano de contas LEGADO (empresas antigas).
 *
 * O plano legado JÁ É estruturalmente PGC-AO (classe 1 imobilizado, 2 existências,
 * 3 terceiros, 4 monetários, 5 capital, 6 proveitos, 7 custos, 8 resultados), mas a
 * coluna `type` foi mal preenchida:
 *   - Classe 6 (Vendas/Serviços = PROVEITOS) estava marcada como `expense`
 *   - Classe 7 (Custos) estava marcada como `revenue`   → as duas TROCADAS
 *   - Toda a Classe 3 (Terceiros) estava marcada como `asset` — fornecedores (32),
 *     empréstimos (33), Estado a pagar (34) e pessoal (36) são PASSIVO.
 *
 * As demonstrações financeiras (BalanceSheetService / IncomeStatementNatureService)
 * agregam por `type`, por isso esta correção é o que faz o balanço e a DR
 * apresentarem cada conta no lado certo.
 *
 * IDEMPOTENTE: só actua em tenants com a "assinatura" do bug (alguma conta da classe
 * 6 marcada como `expense`). Depois de corrigido, classe 6 = `revenue`, logo re-correr
 * não faz nada. NÃO toca nos tenants novos (plano PGC-AO já com types certos).
 *
 * Regras por PREFIXO de código (convenção universal do plano). Casos-fronteira de baixa
 * materialidade (348 subsídios, 3454 regularizações, 377 transitórias) ficam no default
 * da subclasse — devem ser afinados pelo contabilista se necessário.
 */
class FixLegacyAccountTypesSeeder extends Seeder
{
    public function run(): void
    {
        // Tenants com a assinatura do bug: classe 6 marcada como expense.
        $tenantIds = DB::table('accounting_accounts')
            ->whereRaw("LEFT(code,1) = '6'")
            ->where('type', 'expense')
            ->distinct()
            ->pluck('tenant_id');

        if ($tenantIds->isEmpty()) {
            $this->command?->info('Nenhum tenant legado a corrigir (todos já com types certos).');
            return;
        }

        foreach ($tenantIds as $tid) {
            $before = $this->typeDist($tid);
            $this->fixTenant($tid);
            $after = $this->typeDist($tid);
            $this->command?->info("Tenant #{$tid}: " . json_encode($before) . " -> " . json_encode($after));
        }
    }

    private function typeDist($tid): array
    {
        return DB::table('accounting_accounts')->where('tenant_id', $tid)
            ->groupBy('type')->selectRaw('type, COUNT(*) c')->pluck('c', 'type')->toArray();
    }

    private function fixTenant($tid): void
    {
        $set = function (array $where, array $values) use ($tid) {
            $q = DB::table('accounting_accounts')->where('tenant_id', $tid);
            foreach ($where as $w) {
                $q->whereRaw($w);
            }
            $q->update($values);
        };

        DB::transaction(function () use ($set) {
            // ---- Classes de saldo directo ----
            $set(["LEFT(code,1) IN ('1','2','4')"], ['type' => 'asset']);
            $set(["LEFT(code,1) = '5'"], ['type' => 'equity']);
            $set(["LEFT(code,1) = '8'"], ['type' => 'equity']);
            // Classe 6 = PROVEITOS (revenue) ; Classe 7 = CUSTOS (expense) — estavam trocadas
            $set(["LEFT(code,1) = '6'"], ['type' => 'revenue', 'nature' => 'credit']);
            $set(["LEFT(code,1) = '7'"], ['type' => 'expense', 'nature' => 'debit']);

            // ---- Classe 3 (Terceiros): baseline asset, depois overrides ----
            $set(["LEFT(code,1) = '3'"], ['type' => 'asset']);
            // Passivos: fornecedores(32), empréstimos(33), provisões riscos e encargos(39)
            $set(["LEFT(code,2) IN ('32','33','39')"], ['type' => 'liability']);
            // Estado(34) → passivo por defeito (impostos a pagar)
            $set(["LEFT(code,2) = '34'"], ['type' => 'liability']);
            // IVA dedutível/suportado/a recuperar/reembolsos e crédito fiscal a compensar → ASSET
            $set(["LEFT(code,4) IN ('3451','3452','3457','3458')"], ['type' => 'asset']);
            $set(["LEFT(code,3) = '346'"], ['type' => 'asset']);
            // Pessoal(36) → passivo, excepto adiantamentos ao pessoal(363) → asset
            $set(["LEFT(code,2) = '36'"], ['type' => 'liability']);
            $set(["LEFT(code,3) = '363'"], ['type' => 'asset']);
            // Outros valores a receber/pagar(37): baseline asset; a-pagar/diferido passivo → liability
            $set(["LEFT(code,2) = '37'"], ['type' => 'asset']);
            $set(["LEFT(code,3) IN ('371','375','376')"], ['type' => 'liability']);
        });
    }
}
