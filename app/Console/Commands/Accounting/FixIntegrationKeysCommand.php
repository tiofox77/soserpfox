<?php

namespace App\Console\Commands\Accounting;

use App\Models\Accounting\Account;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Limpa integration_key atribuída por engano nos planos importados.
 *
 * A inferência do ImportChartOfAccounts é por nome e apanhava falsos positivos:
 *
 *   · /cliente/i        marcava "Adiantamentos de Clientes", "Provisões para
 *                       Clientes", "Descontos a clientes" e "Garantias dadas a
 *                       clientes" como `receivables`;
 *   · /iva.*liquid/i    marcava "IVA Dedutível — ... — Autoliquidação — 14%"
 *                       como `vat_collected`, porque "autoliquidação" contém
 *                       "liquid" — uma conta de IVA dedutível a fazer de IVA
 *                       liquidado inverte o sinal das vendas.
 *
 * Como o mapeamento da integração escolhe uma destas contas, as facturas, notas
 * e recibos lançavam contra contas erradas. Este comando remove a chave das
 * contas que não são a conta corrente / de liquidação, deixando as legítimas.
 *
 * AVISO sobre --limpar: as integration_key servem DUAS coisas — escolher a conta
 * do mapeamento E ancorar as linhas dos relatórios, que somam a subárvore por
 * prefixo de código. Limpar falsos positivos tira do balanço e do Mapa de IVA
 * contas cujo código não começa pelo de nenhuma âncora sobrevivente (ex.:
 * "42 DEPOSITOS A PRAZO", "3459 IVA Liquidações Oficiosas"). Não é preciso:
 * IntegrationMapping::resolveAccount() já ignora os falsos positivos em memória.
 * Use --restaurar para desfazer uma limpeza anterior.
 *
 *   php artisan accounting:fix-integration-keys --restaurar --completar
 *   php artisan accounting:fix-integration-keys --restaurar --completar --fix
 */
class FixIntegrationKeysCommand extends Command
{
    protected $signature = 'accounting:fix-integration-keys
        {--tenant= : Limitar a uma empresa}
        {--completar : Atribui as chaves em FALTA (ex.: cogs)}
        {--restaurar : Repõe as chaves do plano importado (desfaz limpezas)}
        {--limpar : Remove falsos positivos. NAO recomendado (ver docblock)}
        {--fix : Aplica as correcções (por omissão só mostra)}';

    protected $description = 'Remove integration_key atribuída por engano (adiantamentos, provisões, IVA autoliquidação)';

    /**
     * Padrões e tipos esperados vivem no IntegrationMapping, para o seeder da
     * integração e este comando não divergirem.
     */
    private const EXCLUSOES = \App\Models\Accounting\IntegrationMapping::EXCLUSOES_NOME;

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');

        $tenants = $this->option('tenant')
            ? Tenant::where('id', $this->option('tenant'))->get()
            : Tenant::all();

        $this->info('=== integration_key: contas indevidamente marcadas ===');
        $this->line($fix ? '<fg=yellow>modo REAL — as chaves serão limpas</>' : 'simulação (use --fix para aplicar)');
        $this->newLine();

        $totalLimpas = 0;

        $totalPreenchidas = 0;
        $totalRepostas = 0;

        foreach ($tenants as $tenant) {
            $limpasTenant = 0;

            if ($this->option('restaurar')) {
                $totalRepostas += $this->restaurarDoPlano($tenant, $fix);
            }

            if ($this->option('completar')) {
                $totalPreenchidas += $this->completarEmFalta($tenant, $fix);
            }

            if (!$this->option('limpar')) {
                continue;
            }

            foreach (self::EXCLUSOES as $chave => $padrao) {
                $contas = Account::where('tenant_id', $tenant->id)
                    ->where('integration_key', $chave)
                    ->get(['id', 'code', 'name', 'integration_key']);

                $erradas = $contas->filter(fn ($c) => preg_match($padrao, (string) $c->name));

                if ($erradas->isEmpty()) {
                    continue;
                }

                if ($limpasTenant === 0) {
                    $this->line("<fg=white;options=bold>{$tenant->name}</>");
                }

                $this->line("  {$chave}: remove " . $erradas->count() . ' de ' . $contas->count());
                foreach ($erradas->take(4) as $c) {
                    $this->line("    - {$c->code} {$c->name}");
                }
                if ($erradas->count() > 4) {
                    $this->line('    - … +' . ($erradas->count() - 4));
                }

                if ($fix) {
                    Account::whereIn('id', $erradas->pluck('id'))->update(['integration_key' => null]);
                }

                $limpasTenant += $erradas->count();
            }

            // Conta que a integração passa a usar para cada chave
            if ($limpasTenant > 0) {
                foreach (array_keys(\App\Models\Accounting\IntegrationMapping::TIPO_ESPERADO) as $chave) {
                    $conta = \App\Models\Accounting\IntegrationMapping::resolveAccount($tenant->id, $chave);
                    $this->line("  → " . str_pad($chave, 14) . ' '
                        . ($conta ? "{$conta->code} {$conta->name}" : '<fg=red>NENHUMA</>'));
                }
                $this->newLine();
            }

            $totalLimpas += $limpasTenant;
        }

        if ($totalLimpas === 0 && $totalPreenchidas === 0 && $totalRepostas === 0) {
            $this->info('Nada a corrigir.');
            return self::SUCCESS;
        }

        if ($totalRepostas > 0) {
            $this->line("Total: {$totalRepostas} chave(s) " . ($fix ? 'repostas' : 'a repor'));
        }
        if ($totalLimpas > 0) {
            $this->line("Total: {$totalLimpas} chave(s) " . ($fix ? 'limpas' : 'a limpar'));
        }
        if ($totalPreenchidas > 0) {
            $this->line("Total: {$totalPreenchidas} chave(s) " . ($fix ? 'preenchidas' : 'a preencher'));
        }

        if ($fix) {
            $this->newLine();
            $this->warn('Recrie os mapeamentos para apanharem as contas certas:');
            $this->line('  php artisan db:seed --class="Database\\Seeders\\Accounting\\IntegrationMappingSeeder" --force');
        }

        return self::SUCCESS;
    }

    /**
     * Repõe as integration_key tal como vieram do plano importado.
     *
     * A limpeza de falsos positivos parecia inofensiva, mas as chaves servem
     * DUAS coisas: escolher a conta do mapeamento (integração) e ANCORAR as
     * linhas dos relatórios, que somam a subárvore por prefixo de código. Ao
     * limpar, contas como "42 DEPOSITOS A PRAZO" ou "3459 IVA Liquidações
     * Oficiosas" ficavam sem âncora e desapareciam do balanço e do Mapa de IVA,
     * porque o código delas não começa pelo de nenhuma âncora sobrevivente.
     *
     * A escolha da conta do mapeamento não precisa disto: o
     * IntegrationMapping::resolveAccount() já ignora os falsos positivos em
     * memória.
     */
    private function restaurarDoPlano(Tenant $tenant, bool $fix): int
    {
        static $original = null;

        if ($original === null) {
            $original = [];
            $ficheiro = base_path('database/seeders/Accounting/imported_accounts.php');
            if (is_file($ficheiro)) {
                foreach ((array) require $ficheiro as $conta) {
                    if (!empty($conta['integration_key'])) {
                        $original[$conta['code']] = $conta['integration_key'];
                    }
                }
            }
        }

        if (!$original) {
            $this->line('  (sem plano importado de referência — nada a repor)');
            return 0;
        }

        $repostas = 0;

        foreach ($original as $codigo => $chave) {
            $conta = Account::where('tenant_id', $tenant->id)
                ->where('code', $codigo)
                ->whereNull('integration_key')
                ->first(['id', 'code', 'name']);

            if (!$conta) {
                continue;
            }

            if ($repostas === 0) {
                $this->line("<fg=white;options=bold>{$tenant->name}</> — repor âncoras");
            }
            if ($repostas < 3) {
                $this->line("    {$chave} ← {$conta->code} {$conta->name}");
            }

            if ($fix) {
                Account::where('id', $conta->id)->update(['integration_key' => $chave]);
            }

            $repostas++;
        }

        if ($repostas > 3) {
            $this->line('    … +' . ($repostas - 3));
        }

        return $repostas;
    }

    /**
     * Atribui as chaves que NÃO resolvem para conta nenhuma.
     *
     * A inferência da importação é por nome e deixou papéis por marcar — o mais
     * grave é `cogs`: sem ele o mapeamento `purchase` não é criado e as facturas
     * de compra não chegam à contabilidade. Só toca em contas SEM chave, para
     * não desfazer marcações existentes.
     */
    private function completarEmFalta(Tenant $tenant, bool $fix): int
    {
        $mapping = \App\Models\Accounting\IntegrationMapping::class;
        $preenchidas = 0;

        foreach ($mapping::PADROES_NOME as $chave => $padrao) {
            if ($mapping::resolveAccount($tenant->id, $chave)) {
                continue;   // já resolve
            }

            $tipo = $mapping::TIPO_ESPERADO[$chave] ?? null;

            $candidata = Account::where('tenant_id', $tenant->id)
                ->whereNull('integration_key')
                ->when($tipo, fn ($q) => $q->where('type', $tipo))
                ->orderBy('level')->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->first(fn ($c) => preg_match($padrao, (string) $c->name));

            if (!$candidata) {
                $this->line("  <fg=yellow>{$tenant->name}</>: {$chave} sem candidata — configure à mão");
                continue;
            }

            $this->line("  {$tenant->name}: {$chave} ← {$candidata->code} {$candidata->name}");

            if ($fix) {
                Account::where('id', $candidata->id)->update(['integration_key' => $chave]);
            }

            $preenchidas++;
        }

        return $preenchidas;
    }
}
