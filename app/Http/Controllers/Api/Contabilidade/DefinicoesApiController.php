<?php

namespace App\Http\Controllers\Api\Contabilidade;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\IntegrationMapping;
use App\Models\Accounting\Journal;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS DEFINIÇÕES DA CONTABILIDADE — montar o módulo e ligar a facturação.
 *
 * O QUE ESTAVA PARTIDO, e é o mesmo defeito das notificações: A PÁGINA ABRIA COM
 * `accounting.settings.view` E TODAS AS ESCRITAS ERAM LIVRES. Só o botão de
 * apagar os dados verificava `accounting.settings.edit`. Quem pudesse VER podia:
 *
 *  · correr os seeders todos (plano de contas, diários, impostos, períodos);
 *  · LIGAR E DESLIGAR a integração automática — que decide se cada factura,
 *    recebimento e pagamento gera lançamentos na contabilidade;
 *  · e reescrever os MAPEAMENTOS, que dizem contra que contas esses lançamentos
 *    saem. Uma conta trocada aqui envenena todos os lançamentos seguintes.
 *
 * Aqui ver é `accounting.settings.view` e mexer é `accounting.settings.edit`,
 * sem excepção.
 *
 * AS SINCRONIZAÇÕES SÃO INCREMENTAIS e idempotentes: acrescentam o que falta e
 * nunca alteram nem apagam o que a empresa já tem.
 */
class DefinicoesApiController extends Controller
{
    /** Os eventos da facturação que geram contabilidade. */
    public const EVENTOS = [
        'invoice' => 'Fatura de venda (FT/FR)',
        'credit_note' => 'Nota de Crédito',
        'debit_note' => 'Nota de Débito',
        'receipt_cash' => 'Recebimento em caixa',
        'receipt_bank' => 'Recebimento em banco',
        'purchase' => 'Fatura de compra',
        'payment_cash' => 'Pagamento em caixa',
        'payment_bank' => 'Pagamento em banco',
    ];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function tenantId(): int
    {
        return (int) activeTenantId();
    }

    private function contar(string $tabela, int $tenantId): int
    {
        return (int) DB::table($tabela)->where('tenant_id', $tenantId)->count();
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.settings.view');

        $tenantId = $this->tenantId();
        $ano = (int) now()->year;

        $tenant = Tenant::find($tenantId);

        $mapeamentos = IntegrationMapping::where('tenant_id', $tenantId)->get()->keyBy('event');

        $contas = Account::where('tenant_id', $tenantId)->where('is_view', false)
            ->orderBy('code')->get(['id', 'code', 'name']);

        return response()->json([
            'montagem' => [
                'contas' => $this->contar('accounting_accounts', $tenantId),
                'diarios' => $this->contar('accounting_journals', $tenantId),
                'periodos' => $this->contar('accounting_periods', $tenantId),
                'periodos_do_ano' => (int) DB::table('accounting_periods')
                    ->where('tenant_id', $tenantId)->where('code', 'like', '%/'.$ano)->count(),
                'tipos_de_documento' => $this->contar('accounting_document_types', $tenantId),
                'impostos' => $this->contar('accounting_taxes', $tenantId),
                'centros_de_custo' => $this->contar('cost_centers', $tenantId),
                'lancamentos' => $this->contar('accounting_moves', $tenantId),
            ],

            'ano' => $ano,

            'integracao' => [
                'ligada' => (bool) ($tenant->accounting_integration_enabled ?? false),
                'mapeamentos_activos' => IntegrationMapping::where('tenant_id', $tenantId)
                    ->where('active', true)->count(),
            ],

            'eventos' => collect(self::EVENTOS)->map(function ($rotulo, $evento) use ($mapeamentos) {
                $m = $mapeamentos[$evento] ?? null;

                return [
                    'evento' => $evento,
                    'rotulo' => __($rotulo),
                    'configurado' => $m !== null,
                    'activo' => (bool) ($m->active ?? false),
                    'confirma_sozinho' => (bool) ($m->auto_post ?? false),
                    'diario_id' => $m->journal_id ?? null,
                    'debito_id' => $m->debit_account_id ?? null,
                    'credito_id' => $m->credit_account_id ?? null,
                    'imposto_id' => $m->vat_account_id ?? null,
                ];
            })->values(),

            'contas' => $contas->map(fn ($c) => [
                'valor' => (string) $c->id, 'rotulo' => $c->code.' · '.$c->name,
            ])->values(),

            'diarios' => Journal::where('tenant_id', $tenantId)->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn ($d) => ['valor' => (string) $d->id, 'rotulo' => $d->code.' · '.$d->name])->values(),

            'permissoes' => [
                // VER E MEXER SÃO DIREITOS DIFERENTES, e era isso que faltava.
                'editar' => (bool) $request->user()?->can('accounting.settings.edit'),
            ],
        ]);
    }

    /**
     * SINCRONIZAR — acrescentar o que falta, sem tocar no que existe.
     *
     * Uma acção por peça, mais o `tudo` pela ordem das dependências.
     */
    public function sincronizar(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.settings.edit');

        $dados = $request->validate([
            'peca' => ['required', Rule::in([
                'contas', 'diarios', 'impostos', 'centros-de-custo',
                'tipos-de-documento', 'periodos', 'tudo',
            ])],
            'ano' => ['nullable', 'integer', 'min:1900', 'max:2200'],
        ]);

        $tenantId = $this->tenantId();
        $ano = (int) ($dados['ano'] ?? now()->year);

        try {
            $mensagem = match ($dados['peca']) {
                'contas' => $this->sincronizarContas($tenantId),
                'diarios' => $this->sincronizarDiarios($tenantId),
                'impostos' => $this->sincronizarImpostos($tenantId),
                'centros-de-custo' => $this->sincronizarCentros($tenantId),
                'tipos-de-documento' => $this->sincronizarTipos($tenantId),
                'periodos' => $this->sincronizarPeriodos($tenantId, $ano),
                'tudo' => $this->sincronizarTudo($tenantId, $ano),
            };
        } catch (\Throwable $e) {
            /*
             * APANHA-SE `Throwable` e não só `Exception`: um TypeError dentro de
             * um seeder escapava e dava página de erro em vez de mensagem.
             */
            Log::error('Falha a sincronizar a contabilidade', [
                'tenant_id' => $tenantId, 'peca' => $dados['peca'],
                'error' => $e->getMessage(),
                'ficheiro' => basename($e->getFile()).':'.$e->getLine(),
            ]);

            throw ValidationException::withMessages(['geral' => [$e->getMessage()]]);
        }

        return response()->json(['message' => $mensagem]);
    }

    private function sincronizarContas(int $tenantId): string
    {
        $antes = $this->contar('accounting_accounts', $tenantId);
        $criadas = (new \Database\Seeders\Accounting\AccountSeeder())->runForTenant($tenantId);

        return $criadas > 0
            ? __(':n conta(s) do PGC-AO acrescentada(s). Total: :total.', ['n' => $criadas, 'total' => $antes + $criadas])
            : __('O plano de contas já está completo (:total contas). Nada a acrescentar.', ['total' => $antes]);
    }

    private function sincronizarDiarios(int $tenantId): string
    {
        $antes = $this->contar('accounting_journals', $tenantId);
        Artisan::call('accounting:sync-journals', ['--tenant' => $tenantId]);
        $depois = $this->contar('accounting_journals', $tenantId);

        return $depois > $antes
            ? __(':n diário(s) criado(s). Total: :total.', ['n' => $depois - $antes, 'total' => $depois])
            : __('Os diários já estão sincronizados (:total). Nada a acrescentar.', ['total' => $depois]);
    }

    private function sincronizarImpostos(int $tenantId): string
    {
        $antes = $this->contar('accounting_taxes', $tenantId);
        (new \Database\Seeders\Accounting\TaxSeeder())->seedForTenant($tenantId);
        $depois = $this->contar('accounting_taxes', $tenantId);

        return $depois > $antes
            ? __(':n imposto(s) criado(s). Total: :total.', ['n' => $depois - $antes, 'total' => $depois])
            : __('Impostos actualizados (:total). Nenhum em falta.', ['total' => $depois]);
    }

    private function sincronizarCentros(int $tenantId): string
    {
        $antes = $this->contar('cost_centers', $tenantId);
        (new \Database\Seeders\CostCenterSeeder())->seedForTenant($tenantId);
        $depois = $this->contar('cost_centers', $tenantId);

        return $depois > $antes
            ? __(':n centro(s) de custo criado(s). Total: :total.', ['n' => $depois - $antes, 'total' => $depois])
            : __('Centros de custo actualizados (:total). Nenhum em falta.', ['total' => $depois]);
    }

    private function sincronizarTipos(int $tenantId): string
    {
        $antes = $this->contar('accounting_document_types', $tenantId);
        (new \Database\Seeders\Accounting\DocumentTypeSeeder())->runForTenant($tenantId);
        $depois = $this->contar('accounting_document_types', $tenantId);

        return $depois > $antes
            ? __(':n tipo(s) de documento criado(s). Total: :total.', ['n' => $depois - $antes, 'total' => $depois])
            : __('Tipos de documento actualizados (:total). Nenhum em falta.', ['total' => $depois]);
    }

    private function sincronizarPeriodos(int $tenantId, int $ano): string
    {
        $criados = (new \Database\Seeders\Accounting\PeriodSeeder())->runForTenant($tenantId, $ano);

        return $criados > 0
            ? __(':n período(s) de :ano criado(s).', ['n' => $criados, 'ano' => $ano])
            : __('O exercício de :ano já tem os 12 períodos. Nada a acrescentar.', ['ano' => $ano]);
    }

    /** Tudo, pela ordem das dependências: as contas primeiro, os períodos no fim. */
    private function sincronizarTudo(int $tenantId, int $ano): string
    {
        $resumo = [];

        $resumo[] = __(':n conta(s)', ['n' => (new \Database\Seeders\Accounting\AccountSeeder())->runForTenant($tenantId)]);

        $antes = $this->contar('accounting_journals', $tenantId);
        Artisan::call('accounting:sync-journals', ['--tenant' => $tenantId]);
        $resumo[] = __(':n diário(s)', ['n' => $this->contar('accounting_journals', $tenantId) - $antes]);

        $antes = $this->contar('accounting_taxes', $tenantId);
        (new \Database\Seeders\Accounting\TaxSeeder())->seedForTenant($tenantId);
        $resumo[] = __(':n imposto(s)', ['n' => $this->contar('accounting_taxes', $tenantId) - $antes]);

        $antes = $this->contar('cost_centers', $tenantId);
        (new \Database\Seeders\CostCenterSeeder())->seedForTenant($tenantId);
        $resumo[] = __(':n centro(s) de custo', ['n' => $this->contar('cost_centers', $tenantId) - $antes]);

        $antes = $this->contar('accounting_document_types', $tenantId);
        (new \Database\Seeders\Accounting\DocumentTypeSeeder())->runForTenant($tenantId);
        $resumo[] = __(':n tipo(s) de documento', ['n' => $this->contar('accounting_document_types', $tenantId) - $antes]);

        $resumo[] = __(':n período(s) de :ano', [
            'n' => (new \Database\Seeders\Accounting\PeriodSeeder())->runForTenant($tenantId, $ano),
            'ano' => $ano,
        ]);

        return __('Acrescentado: :resumo.', ['resumo' => implode(', ', $resumo)]);
    }

    /**
     * LIGAR OU DESLIGAR A INTEGRAÇÃO AUTOMÁTICA.
     *
     * Decide se cada factura, recebimento e pagamento gera lançamentos. Era uma
     * escrita LIVRE para quem pudesse abrir a página.
     */
    public function integracao(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.settings.edit');

        $dados = $request->validate(['ligada' => ['required', 'boolean']]);

        $tenantId = $this->tenantId();
        $tenant = Tenant::findOrFail($tenantId);

        $tenant->accounting_integration_enabled = (bool) $dados['ligada'];
        $tenant->save();

        if (! $tenant->accounting_integration_enabled) {
            return response()->json(['message' => __('Integração automática desactivada.')]);
        }

        /*
         * LIGAR SEM MAPEAMENTOS NÃO PRODUZ LANÇAMENTO NENHUM, e o ecrã prometia
         * o contrário. Dizer a verdade em vez de falhar em silêncio.
         */
        $mapeamentos = IntegrationMapping::where('tenant_id', $tenantId)->where('active', true)->count();

        return response()->json([
            'message' => $mapeamentos > 0
                ? __('Integração automática activada (:n mapeamento(s) activo(s)).', ['n' => $mapeamentos])
                : __('Integração activada — mas ainda não há mapeamentos configurados, pelo que nenhum lançamento será criado. Configure as contas de destino abaixo.'),
            'aviso' => $mapeamentos === 0,
        ]);
    }

    /**
     * O MAPEAMENTO DE UM EVENTO: por que diário e contra que contas.
     *
     * A resolução automática por chave de integração garante a classe e o sinal
     * certos, mas não a conta exacta: num plano importado de 1.500 contas aterra
     * nos cabeçalhos de classe («31 CLIENTES» em vez de «311 Clientes
     * correntes»). Até haver este ecrã, só se corrigia com SQL directo.
     */
    public function mapeamento(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.settings.edit');

        $tenantId = $this->tenantId();

        $dados = $request->validate([
            'event' => ['required', Rule::in(array_keys(self::EVENTOS))],
            'journal_id' => ['required', 'integer'],
            'debit_account_id' => ['required', 'integer'],
            'credit_account_id' => ['required', 'integer'],
            'vat_account_id' => ['nullable', 'integer'],
            'auto_post' => ['boolean'],
            'active' => ['boolean'],
        ], [], [
            'journal_id' => __('diário'),
            'debit_account_id' => __('conta a débito'),
            'credit_account_id' => __('conta a crédito'),
        ]);

        // TUDO PRESO À EMPRESA: um id de outra companhia punha os lançamentos a
        // sair contra contas alheias.
        if (! Journal::where('tenant_id', $tenantId)->whereKey($dados['journal_id'])->exists()) {
            throw ValidationException::withMessages([
                'journal_id' => [__('Diário não encontrado nesta empresa.')],
            ]);
        }

        foreach (['debit_account_id', 'credit_account_id', 'vat_account_id'] as $campo) {
            $id = $dados[$campo] ?? null;

            if (! $id) {
                continue;
            }

            $conta = Account::where('tenant_id', $tenantId)->find($id);

            if (! $conta) {
                throw ValidationException::withMessages([
                    $campo => [__('Conta não encontrada nesta empresa.')],
                ]);
            }

            /*
             * E NÃO PODE SER DE AGREGAÇÃO: é o caso concreto que este ecrã veio
             * resolver — a resolução automática aterra no cabeçalho de classe, e
             * lançar contra ele conta o valor duas vezes no balanço.
             */
            if ($conta->is_view) {
                throw ValidationException::withMessages([
                    $campo => [__('A conta :conta é de agregação: escolha a conta de movimento por baixo dela.', [
                        'conta' => $conta->code.' · '.$conta->name,
                    ])],
                ]);
            }
        }

        IntegrationMapping::updateOrCreate(
            ['tenant_id' => $tenantId, 'event' => $dados['event']],
            [
                'journal_id' => $dados['journal_id'],
                'debit_account_id' => $dados['debit_account_id'],
                'credit_account_id' => $dados['credit_account_id'],
                'vat_account_id' => ($dados['vat_account_id'] ?? null) ?: null,
                'auto_post' => (bool) ($dados['auto_post'] ?? true),
                'active' => (bool) ($dados['active'] ?? true),
            ],
        );

        return response()->json([
            'message' => __('Mapeamento de :evento guardado.', ['evento' => __(self::EVENTOS[$dados['event']])]),
        ]);
    }

    /**
     * APAGAR OS DADOS CONTABILÍSTICOS — a acção destrutiva.
     *
     * Só com `accounting.settings.edit` (era a única que já verificava), e só se
     * não houver LANÇAMENTO nenhum: apagar o plano de contas por baixo dos
     * lançamentos deixava-os a apontar para contas que não existem.
     *
     * OS CENTROS DE CUSTO são partilhados com outros módulos (orçamentos,
     * analítica, RH): só se apagam os que ninguém referencia.
     */
    public function apagarTudo(Request $request): JsonResponse
    {
        $this->exigir($request, 'accounting.settings.edit');

        $tenantId = $this->tenantId();

        $lancamentos = $this->contar('accounting_moves', $tenantId);

        if ($lancamentos > 0) {
            throw ValidationException::withMessages([
                'geral' => [__('Não é possível apagar: existem :n lançamento(s) registado(s). Reponha apenas o que falta com «Sincronizar».', [
                    'n' => $lancamentos,
                ])],
            ]);
        }

        $emUso = $this->centrosDeCustoEmUso($tenantId);

        DB::transaction(function () use ($tenantId, $emUso) {
            DB::table('accounting_journals')->where('tenant_id', $tenantId)->delete();
            DB::table('accounting_accounts')->where('tenant_id', $tenantId)->delete();
            DB::table('accounting_taxes')->where('tenant_id', $tenantId)->delete();

            $q = DB::table('cost_centers')->where('tenant_id', $tenantId);

            if (! empty($emUso)) {
                $q->whereNotIn('id', $emUso);
            }

            $q->delete();
        });

        return response()->json([
            'message' => empty($emUso)
                ? __('Dados contabilísticos apagados. Pode agora sincronizar outra vez.')
                : __('Dados contabilísticos apagados. :n centro(s) de custo ficaram por estarem em uso.', [
                    'n' => count($emUso),
                ]),
        ]);
    }

    /**
     * Os centros de custo que outra coisa referencia.
     *
     * @return array<int, int>
     */
    private function centrosDeCustoEmUso(int $tenantId): array
    {
        $ids = [];

        /*
         * As tabelas com chave estrangeira REAL para `cost_centers`: apagar um
         * centro em uso dava erro de integridade e abortava a operação toda. O
         * `accounting_accounts` também aponta para cá, mas é apagado antes na
         * mesma transacção — incluí-lo aqui preservaria centros sem razão.
         */
        foreach ([
            'budgets' => 'cost_center_id',
            'move_line_analytics' => 'cost_center_id',
        ] as $tabela => $coluna) {
            try {
                if (! DB::getSchemaBuilder()->hasColumn($tabela, $coluna)) {
                    continue;
                }

                $ids = array_merge($ids, DB::table($tabela)
                    ->whereNotNull($coluna)->distinct()->pluck($coluna)->all());
            } catch (\Throwable) {
                // Tabela inexistente nesta instalação — ignorar.
            }
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}
