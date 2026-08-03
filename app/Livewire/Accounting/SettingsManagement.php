<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Configurações do módulo de Contabilidade.
 *
 * Todas as acções de "sincronizar" são INCREMENTAIS e idempotentes: acrescentam
 * apenas o que ainda não existe e nunca alteram nem apagam o que o cliente já
 * tem. Antes os botões desactivavam-se assim que existisse um único registo, o
 * que impedia empresas antigas de receber dados novos (contas acrescentadas ao
 * PGC-AO, diários novos, períodos do ano seguinte).
 */
#[Layout('layouts.app')]
class SettingsManagement extends Component
{
    /** Exercício alvo ao sincronizar períodos. */
    public ?int $anoPeriodos = null;

    public function mount(): void
    {
        $this->anoPeriodos = (int) now()->year;
    }

    // ─────────────────────────────────────────────────────────────
    //  Sincronizações (incrementais)
    // ─────────────────────────────────────────────────────────────

    public function importAccounts()
    {
        $this->executar('sincronizar o plano de contas', function (int $tenantId) {
            $antes = $this->contar('accounting_accounts', $tenantId);
            $criadas = (new \Database\Seeders\Accounting\AccountSeeder())->runForTenant($tenantId);

            return $criadas > 0
                ? "{$criadas} conta(s) do PGC-AO acrescentada(s). Total: " . ($antes + $criadas) . '.'
                : "O plano de contas já está completo ({$antes} contas). Nada a acrescentar.";
        });
    }

    public function syncJournals()
    {
        $this->executar('sincronizar os diários', function (int $tenantId) {
            $antes = $this->contar('accounting_journals', $tenantId);
            \Artisan::call('accounting:sync-journals', ['--tenant' => $tenantId]);
            $depois = $this->contar('accounting_journals', $tenantId);
            $novos = $depois - $antes;

            return $novos > 0
                ? "{$novos} diário(s) criado(s). Total: {$depois}."
                : "Os diários já estão sincronizados ({$depois}). Nada a acrescentar.";
        });
    }

    public function syncTaxes()
    {
        $this->executar('sincronizar os impostos', function (int $tenantId) {
            $antes = $this->contar('accounting_taxes', $tenantId);
            (new \Database\Seeders\Accounting\TaxSeeder())->seedForTenant($tenantId);
            $depois = $this->contar('accounting_taxes', $tenantId);
            $novos = $depois - $antes;

            return $novos > 0
                ? "{$novos} imposto(s) criado(s). Total: {$depois}."
                : "Impostos actualizados ({$depois}). Nenhum imposto novo em falta.";
        });
    }

    public function syncCostCenters()
    {
        $this->executar('sincronizar os centros de custo', function (int $tenantId) {
            $antes = $this->contar('cost_centers', $tenantId);
            (new \Database\Seeders\CostCenterSeeder())->seedForTenant($tenantId);
            $depois = $this->contar('cost_centers', $tenantId);
            $novos = $depois - $antes;

            return $novos > 0
                ? "{$novos} centro(s) de custo criado(s). Total: {$depois}."
                : "Centros de custo actualizados ({$depois}). Nenhum em falta.";
        });
    }

    public function importPeriods()
    {
        $this->executar('sincronizar os períodos', function (int $tenantId) {
            $ano = $this->anoValido();
            $criados = (new \Database\Seeders\Accounting\PeriodSeeder())->runForTenant($tenantId, $ano);

            return $criados > 0
                ? "{$criados} período(s) de {$ano} criado(s)."
                : "O exercício de {$ano} já tem os 12 períodos. Nada a acrescentar.";
        });
    }

    public function importDocumentTypes()
    {
        $this->executar('sincronizar os tipos de documento', function (int $tenantId) {
            $antes = $this->contar('accounting_document_types', $tenantId);
            (new \Database\Seeders\Accounting\DocumentTypeSeeder())->runForTenant($tenantId);
            $depois = $this->contar('accounting_document_types', $tenantId);
            $novos = $depois - $antes;

            return $novos > 0
                ? "{$novos} tipo(s) de documento criado(s). Total: {$depois}."
                : "Tipos de documento actualizados ({$depois}). Nenhum em falta.";
        });
    }

    /** Sincroniza tudo de uma vez, pela ordem correcta de dependências. */
    public function syncAll()
    {
        $this->executar('sincronizar a contabilidade', function (int $tenantId) {
            $ano = $this->anoValido();
            $resumo = [];

            $n = (new \Database\Seeders\Accounting\AccountSeeder())->runForTenant($tenantId);
            $resumo[] = "{$n} conta(s)";

            $antes = $this->contar('accounting_journals', $tenantId);
            \Artisan::call('accounting:sync-journals', ['--tenant' => $tenantId]);
            $resumo[] = ($this->contar('accounting_journals', $tenantId) - $antes) . ' diário(s)';

            $antes = $this->contar('accounting_taxes', $tenantId);
            (new \Database\Seeders\Accounting\TaxSeeder())->seedForTenant($tenantId);
            $resumo[] = ($this->contar('accounting_taxes', $tenantId) - $antes) . ' imposto(s)';

            $antes = $this->contar('cost_centers', $tenantId);
            (new \Database\Seeders\CostCenterSeeder())->seedForTenant($tenantId);
            $resumo[] = ($this->contar('cost_centers', $tenantId) - $antes) . ' centro(s) de custo';

            $antes = $this->contar('accounting_document_types', $tenantId);
            (new \Database\Seeders\Accounting\DocumentTypeSeeder())->runForTenant($tenantId);
            $resumo[] = ($this->contar('accounting_document_types', $tenantId) - $antes) . ' tipo(s) de documento';

            $n = (new \Database\Seeders\Accounting\PeriodSeeder())->runForTenant($tenantId, $ano);
            $resumo[] = "{$n} período(s) de {$ano}";

            return 'Acrescentado: ' . implode(', ', $resumo) . '.';
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Integração automática
    // ─────────────────────────────────────────────────────────────

    public function toggleIntegration()
    {
        $this->executar('alterar a integração', function (int $tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);
            if (!$tenant) {
                throw new \RuntimeException('Empresa activa não encontrada.');
            }

            $tenant->accounting_integration_enabled = !$tenant->accounting_integration_enabled;
            $tenant->save();

            if (!$tenant->accounting_integration_enabled) {
                return 'Integração automática desactivada.';
            }

            // Activar sem mapeamentos não produz lançamento nenhum, e o ecrã
            // prometia o contrário. Dizer a verdade em vez de falhar em silêncio.
            $mapeamentos = DB::table('accounting_integration_mappings')
                ->where('tenant_id', $tenantId)->where('active', 1)->count();

            if ($mapeamentos === 0) {
                session()->flash('warning', 'Integração activada, mas ainda não há '
                    . 'mapeamentos configurados — nenhum lançamento será criado até definir '
                    . 'as contas de destino para facturas, recebimentos e pagamentos.');
            }

            return "Integração automática activada ({$mapeamentos} mapeamento(s) activo(s)).";
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Acção destrutiva
    // ─────────────────────────────────────────────────────────────

    public function deleteAllAccountingData()
    {
        $tenantId = activeTenantId();

        // Apagar o plano de contas inteiro não pode estar ao alcance de qualquer
        // utilizador que consiga abrir o ecrã.
        if (!auth()->user()?->can('accounting.settings.edit')) {
            session()->flash('error', 'Não tem permissão para apagar dados contabilísticos.');
            return;
        }

        // Bloqueios ANTES de abrir transação. Em versões anteriores o
        // beginTransaction vinha primeiro e o return deixava a transação aberta.
        $moves = DB::table('accounting_moves')->where('tenant_id', $tenantId)->count();
        if ($moves > 0) {
            session()->flash('error', "Não é possível apagar: existem {$moves} lançamento(s) "
                . 'registado(s). Apague-os primeiro ou reponha apenas o que falta com "Sincronizar".');
            return;
        }

        $emUso = $this->centrosDeCustoEmUso($tenantId);

        try {
            DB::transaction(function () use ($tenantId, $emUso) {
                DB::table('accounting_journals')->where('tenant_id', $tenantId)->delete();
                DB::table('accounting_accounts')->where('tenant_id', $tenantId)->delete();
                DB::table('accounting_taxes')->where('tenant_id', $tenantId)->delete();

                // Centros de custo são partilhados com outros módulos (orçamentos,
                // analítica, RH). Só se apagam os que ninguém referencia.
                $q = DB::table('cost_centers')->where('tenant_id', $tenantId);
                if (!empty($emUso)) {
                    $q->whereNotIn('id', $emUso);
                }
                $q->delete();
            });

            $aviso = empty($emUso) ? '' : ' ' . count($emUso)
                . ' centro(s) de custo foram preservados por estarem em uso.';
            session()->flash('success', 'Dados contabilísticos apagados.' . $aviso
                . ' Pode agora sincronizar novamente.');
        } catch (\Throwable $e) {
            Log::error('Falha ao apagar dados contabilísticos', [
                'tenant_id' => $tenantId, 'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Não foi possível apagar: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Auxiliares
    // ─────────────────────────────────────────────────────────────

    /**
     * Corre uma acção com o tratamento de erros comum.
     *
     * Apanha \Throwable (e não só \Exception): um TypeError dentro de um seeder
     * escapava e dava página de erro em vez de mensagem no ecrã.
     */
    protected function executar(string $descricao, \Closure $accao): void
    {
        $tenantId = activeTenantId();

        if (!$tenantId) {
            session()->flash('error', 'Nenhuma empresa activa na sessão.');
            return;
        }

        try {
            $mensagem = $accao($tenantId);
            session()->flash('success', $mensagem);
        } catch (\Throwable $e) {
            Log::error("Falha ao {$descricao}", [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
                'ficheiro'  => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
            session()->flash('error', "Não foi possível {$descricao}: " . $e->getMessage());
        }
    }

    protected function contar(string $tabela, int $tenantId): int
    {
        return DB::table($tabela)->where('tenant_id', $tenantId)->count();
    }

    /** Só se permite criar o exercício corrente, o anterior e o seguinte. */
    protected function anoValido(): int
    {
        $atual = (int) now()->year;
        $ano = (int) ($this->anoPeriodos ?: $atual);

        return ($ano >= $atual - 1 && $ano <= $atual + 1) ? $ano : $atual;
    }

    /** IDs de centros de custo referenciados por outros registos. */
    protected function centrosDeCustoEmUso(int $tenantId): array
    {
        $ids = [];

        // Tabelas com FK real para cost_centers — apagar um centro em uso daria
        // erro de integridade e abortava toda a operação. (accounting_accounts
        // também aponta para cá, mas é apagada antes na mesma transação.)
        foreach ([
            'budgets'             => 'cost_center_id',
            'move_line_analytics' => 'cost_center_id',
        ] as $tabela => $coluna) {
            try {
                if (!DB::getSchemaBuilder()->hasColumn($tabela, $coluna)) {
                    continue;
                }
                $ids = array_merge($ids, DB::table($tabela)
                    ->whereNotNull($coluna)->distinct()->pluck($coluna)->all());
            } catch (\Throwable $e) {
                // Tabela inexistente nesta instalação — ignorar.
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Editor dos mapeamentos Faturação → Contabilidade.
     *
     * A resolução automática por integration_key garante a CLASSE e o SINAL
     * certos, mas não a conta exacta: num plano importado de 1500+ contas
     * aterra nos cabeçalhos de classe ("31 CLIENTES" em vez de "311 Clientes
     * correntes"). Até aqui só se corrigia com SQL directo.
     */
    public array $mapeamentoEmEdicao = [];

    public const EVENTOS = [
        'invoice'      => 'Fatura de venda (FT/FR)',
        'credit_note'  => 'Nota de Crédito',
        'debit_note'   => 'Nota de Débito',
        'receipt_cash' => 'Recebimento em caixa',
        'receipt_bank' => 'Recebimento em banco',
        'purchase'     => 'Fatura de compra',
        'payment_cash' => 'Pagamento em caixa',
        'payment_bank' => 'Pagamento em banco',
    ];

    public function editarMapeamento(string $evento): void
    {
        $m = \App\Models\Accounting\IntegrationMapping::where('tenant_id', activeTenantId())
            ->where('event', $evento)->first();

        $this->mapeamentoEmEdicao = [
            'event'             => $evento,
            'journal_id'        => $m->journal_id ?? null,
            'debit_account_id'  => $m->debit_account_id ?? null,
            'credit_account_id' => $m->credit_account_id ?? null,
            'vat_account_id'    => $m->vat_account_id ?? null,
            'auto_post'         => (bool) ($m->auto_post ?? true),
            'active'            => (bool) ($m->active ?? true),
        ];
    }

    public function cancelarMapeamento(): void
    {
        $this->mapeamentoEmEdicao = [];
    }

    public function guardarMapeamento(): void
    {
        $dados = $this->mapeamentoEmEdicao;

        if (blank($dados['event'] ?? null) || !isset(self::EVENTOS[$dados['event']])) {
            return;
        }

        $this->validate([
            'mapeamentoEmEdicao.journal_id'        => 'required|integer',
            'mapeamentoEmEdicao.debit_account_id'  => 'required|integer',
            'mapeamentoEmEdicao.credit_account_id' => 'required|integer',
            'mapeamentoEmEdicao.vat_account_id'    => 'nullable|integer',
        ], [], [
            'mapeamentoEmEdicao.journal_id'        => 'diário',
            'mapeamentoEmEdicao.debit_account_id'  => 'conta a débito',
            'mapeamentoEmEdicao.credit_account_id' => 'conta a crédito',
        ]);

        $tenantId = activeTenantId();

        // Tudo scoped ao tenant: um id de outra empresa não pode entrar no
        // mapeamento, senão os lançamentos saíam contra contas alheias.
        $diarioValido = \App\Models\Accounting\Journal::where('tenant_id', $tenantId)
            ->where('id', $dados['journal_id'])->exists();

        $contas = array_filter([
            $dados['debit_account_id'], $dados['credit_account_id'], $dados['vat_account_id'] ?? null,
        ]);
        $contasValidas = \App\Models\Accounting\Account::where('tenant_id', $tenantId)
            ->whereIn('id', $contas)->count() === count(array_unique($contas));

        if (!$diarioValido || !$contasValidas) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Diário ou conta não pertencem a esta empresa.',
            ]);
            return;
        }

        \App\Models\Accounting\IntegrationMapping::updateOrCreate(
            ['tenant_id' => $tenantId, 'event' => $dados['event']],
            [
                'journal_id'        => $dados['journal_id'],
                'debit_account_id'  => $dados['debit_account_id'],
                'credit_account_id' => $dados['credit_account_id'],
                'vat_account_id'    => $dados['vat_account_id'] ?: null,
                'auto_post'         => (bool) ($dados['auto_post'] ?? true),
                'active'            => (bool) ($dados['active'] ?? true),
            ]
        );

        $this->mapeamentoEmEdicao = [];

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Mapeamento de ' . self::EVENTOS[$dados['event']] . ' guardado.',
        ]);
    }

    public function render()
    {
        $tenantId = activeTenantId();
        $ano = $this->anoValido();

        $stats = [
            'accounts'      => $this->contar('accounting_accounts', $tenantId),
            'journals'      => $this->contar('accounting_journals', $tenantId),
            'periods'       => $this->contar('accounting_periods', $tenantId),
            'documentTypes' => $this->contar('accounting_document_types', $tenantId),
            'taxes'         => $this->contar('accounting_taxes', $tenantId),
            'costCenters'   => $this->contar('cost_centers', $tenantId),
            'moves'         => $this->contar('accounting_moves', $tenantId),
            'periodsAno'    => DB::table('accounting_periods')
                ->where('tenant_id', $tenantId)
                ->where('code', 'like', '%/' . $ano)->count(),
            'mappings'      => DB::table('accounting_integration_mappings')
                ->where('tenant_id', $tenantId)->where('active', 1)->count(),
        ];

        $tenant = \App\Models\Tenant::find($tenantId);

        // Mapeamentos por evento, com a conta que a resolução automática
        // sugeriria — para o contabilista ver o que está a ser usado.
        $mapeamentos = \App\Models\Accounting\IntegrationMapping::where('tenant_id', $tenantId)
            ->get()->keyBy('event');

        $contas = \App\Models\Accounting\Account::where('tenant_id', $tenantId)
            ->where('is_view', false)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('livewire.accounting.settings.settings', [
            'stats'              => $stats,
            'integrationEnabled' => (bool) ($tenant->accounting_integration_enabled ?? false),
            'anoAtual'           => (int) now()->year,
            'eventos'            => self::EVENTOS,
            'mapeamentos'        => $mapeamentos,
            'contasDisponiveis'  => $contas,
            'diarios'            => \App\Models\Accounting\Journal::where('tenant_id', $tenantId)
                ->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}
