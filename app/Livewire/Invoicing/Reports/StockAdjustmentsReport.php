<?php

namespace App\Livewire\Invoicing\Reports;

use App\Livewire\Invoicing\Reports\Concerns\HasReportFilters;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Seguimento dos ajustes de stock.
 *
 * Cobre só o que foi mexido À MÃO — entradas e saídas em lote, ajustes de
 * inventário, transferências entre armazéns e desperdício. As saídas de uma
 * venda e as entradas de uma compra ficam de fora de propósito: essas já têm
 * um documento por trás e os mapas de vendas e compras respondem por elas. O
 * que este mapa serve para vigiar é o que não tem documento — quem alterou
 * existências sem que houvesse uma transacção comercial a justificá-lo.
 */
#[Layout('layouts.app')]
#[Title('Ajustes de Stock')]
class StockAdjustmentsReport extends Component
{
    use HasReportFilters;
    use WithPagination;

    public string $warehouseFilter = '';
    public string $typeFilter      = '';
    public string $userFilter      = '';
    public string $search          = '';
    public int    $perPage         = 25;

    /**
     * Origens consideradas MANUAIS.
     *
     * Allowlist e não denylist: com uma lista de exclusões, um tipo de
     * documento novo passaria a aparecer aqui em silêncio, e um mapa de
     * controlo que se enche de movimentos automáticos deixa de se ler.
     *
     * `null` é o caso normal — o ecrã de stock não carimba origem. Os restantes
     * são marcas internas de operações manuais em lote.
     */
    private const ORIGENS_MANUAIS = ['transfer_batch', 'adjustment_batch', 'restaurant_waste'];

    public function mount(): void
    {
        $this->initFilters('month');
    }

    public function updatingWarehouseFilter(): void { $this->resetPage(); }
    public function updatingTypeFilter(): void      { $this->resetPage(); }
    public function updatingUserFilter(): void      { $this->resetPage(); }
    public function updatingSearch(): void          { $this->resetPage(); }
    public function updatingDateFrom(): void        { $this->resetPage(); }
    public function updatingDateTo(): void          { $this->resetPage(); }

    public function limparFiltros(): void
    {
        $this->warehouseFilter = '';
        $this->typeFilter      = '';
        $this->userFilter      = '';
        $this->search          = '';
        $this->resetPage();
    }

    /** Base do mapa: movimentos manuais da empresa, no período escolhido. */
    private function consulta()
    {
        // Todas as colunas qualificadas com a tabela, e o global scope do tenant
        // desligado — o filtro por empresa é feito aqui, à mão e qualificado.
        //
        // O `users` do resumo por operador tem colunas com os mesmos nomes,
        // `tenant_id` e `created_at` incluídos. O global scope BelongsToTenant
        // acrescenta um `where tenant_id = X` SEM qualificar a tabela, e com o
        // join a consulta rebentava com "Column 'tenant_id' is ambiguous" —
        // a mesma armadilha que o render() do ecrã de stock já documenta.
        $t = 'invoicing_stock_movements';

        $q = StockMovement::withoutGlobalScopes()
            ->where("{$t}.tenant_id", activeTenantId())
            ->where(function ($q) use ($t) {
                $q->whereNull("{$t}.reference_type")
                  ->orWhereIn("{$t}.reference_type", self::ORIGENS_MANUAIS);
            })
            // O dia final tem de entrar inteiro: `created_at` é um timestamp, e
            // um `whereBetween` com a data seca corta tudo o que aconteceu
            // depois da meia-noite do último dia.
            ->whereBetween("{$t}.created_at", [
                $this->dateFrom . ' 00:00:00',
                $this->dateTo . ' 23:59:59',
            ]);

        if ($this->warehouseFilter) {
            $q->where("{$t}.warehouse_id", $this->warehouseFilter);
        }

        if ($this->typeFilter) {
            $q->where("{$t}.type", $this->typeFilter);
        }

        if ($this->userFilter) {
            $q->where("{$t}.user_id", $this->userFilter);
        }

        if ($term = trim($this->search)) {
            $q->where(function ($q) use ($term, $t) {
                $q->where("{$t}.batch_reference", 'like', "%{$term}%")
                  ->orWhere("{$t}.notes", 'like', "%{$term}%")
                  // Também sem global scope, e pela mesma razão: dentro da
                  // subconsulta as duas tabelas estão em jogo e o `tenant_id`
                  // que o scope acrescenta não diz de qual é.
                  ->orWhereHas('product', function ($p) use ($term) {
                      $p->withoutGlobalScopes()
                        ->where('invoicing_products.tenant_id', activeTenantId())
                        ->where(function ($p) use ($term) {
                            $p->where('invoicing_products.name', 'like', "%{$term}%")
                              ->orWhere('invoicing_products.code', 'like', "%{$term}%")
                              ->orWhere('invoicing_products.barcode', 'like', "%{$term}%");
                        });
                  });
            });
        }

        return $q;
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $movimentos = $this->consulta()
            ->with([
                // withTrashed: um artigo retirado do catálogo não pode fazer
                // desaparecer do mapa o movimento que o mexeu.
                'product' => fn ($p) => $p->withTrashed(),
                'user',
                'warehouse',
                'toWarehouse',
            ])
            ->orderByDesc("invoicing_stock_movements.created_at")
            ->orderByDesc("invoicing_stock_movements.id")
            ->paginate($this->perPage);

        return view('livewire.invoicing.reports.stock-adjustments-report', [
            'movimentos' => $movimentos,
            'resumo'     => $this->resumo(),
            'porUtilizador' => $this->porUtilizador(),
            'armazens'   => Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get(),
            'operadores' => User::whereIn('id', (clone $this->consulta())->distinct()->pluck('invoicing_stock_movements.user_id')->filter())
                ->orderBy('name')->get(),
        ]);
    }

    /**
     * Totais do período.
     *
     * Entradas e saídas somam-se; ajustes e transferências NÃO.
     *
     * Num ajuste, `quantity` é o saldo FINAL e não uma variação (ver
     * StockMovement::createAdjustment) — somá-la daria um número sem
     * significado nenhum. E uma transferência muda o artigo de armazém sem
     * alterar o que a empresa tem. Contam-se à parte, pelo número de
     * ocorrências, que é o que interessa vigiar.
     */
    private function resumo(): array
    {
        // ABS na quantidade: as transferências gravam a perna de saída com
        // valor negativo, e há registos antigos de outros tipos com o mesmo
        // hábito. Sem isto, uma soma de saídas podia sair negativa e ler-se ao
        // contrário do que é.
        $porTipo = (clone $this->consulta())
            ->selectRaw('
                invoicing_stock_movements.type,
                COUNT(*) n,
                COALESCE(SUM(ABS(quantity)), 0) qtd,
                COALESCE(SUM(ABS(quantity) * COALESCE(unit_cost, 0)), 0) valor
            ')
            ->groupBy('invoicing_stock_movements.type')
            ->get()
            ->keyBy('type');

        $entrada = $porTipo->get('in');
        $saida   = $porTipo->get('out');

        return [
            'entradas_n'     => (int)   ($entrada->n ?? 0),
            'entradas_qtd'   => (float) ($entrada->qtd ?? 0),
            'entradas_valor' => (float) ($entrada->valor ?? 0),
            'saidas_n'       => (int)   ($saida->n ?? 0),
            'saidas_qtd'     => (float) ($saida->qtd ?? 0),
            'saidas_valor'   => (float) ($saida->valor ?? 0),
            'ajustes_n'      => (int)   ($porTipo->get('adjustment')->n ?? 0),
            'transferencias_n' => (int) ($porTipo->get('transfer')->n ?? 0),
            'total_n'        => (int) $porTipo->sum('n'),
            'lotes_n'        => (int) (clone $this->consulta())
                ->whereNotNull('invoicing_stock_movements.batch_reference')
                ->distinct()->count('invoicing_stock_movements.batch_reference'),
        ];
    }

    /**
     * Quem mexeu no stock, e quanto.
     *
     * É esta tabela que dá o seguimento: o movimento diz o que mudou, mas é
     * aqui que se vê a concentração — um operador com 300 ajustes num mês é
     * uma pergunta a fazer, mesmo que cada um deles esteja certo.
     */
    private function porUtilizador()
    {
        return (clone $this->consulta())
            ->leftJoin('users', 'users.id', '=', 'invoicing_stock_movements.user_id')
            ->selectRaw('
                invoicing_stock_movements.user_id,
                COALESCE(users.name, "(sistema)") AS nome,
                COUNT(*) AS n,
                SUM(CASE WHEN type = "in"  THEN ABS(quantity) ELSE 0 END) AS qtd_entrada,
                SUM(CASE WHEN type = "out" THEN ABS(quantity) ELSE 0 END) AS qtd_saida,
                SUM(CASE WHEN type = "adjustment" THEN 1 ELSE 0 END) AS ajustes,
                SUM(CASE WHEN type = "in" THEN ABS(quantity) * COALESCE(unit_cost,0) ELSE 0 END) AS valor_entrada
            ')
            ->groupBy('invoicing_stock_movements.user_id', 'users.name')
            ->orderByDesc('n')
            ->get();
    }

    /** Exporta o período filtrado, para conferência fora do sistema. */
    public function exportarCsv()
    {
        $linhas = (clone $this->consulta())
            ->with(['product' => fn ($p) => $p->withTrashed(), 'user', 'warehouse'])
            ->orderBy('invoicing_stock_movements.created_at')
            ->get();

        $ficheiro = 'ajustes-stock_' . $this->dateFrom . '_a_' . $this->dateTo . '.csv';

        return response()->streamDownload(function () use ($linhas) {
            $saida = fopen('php://output', 'w');

            // BOM: sem ele o Excel em português abre os acentos trocados.
            fwrite($saida, "\xEF\xBB\xBF");

            fputcsv($saida, [
                'Data', 'Hora', 'Tipo', 'Armazém', 'Código', 'Produto',
                'Quantidade', 'Saldo após', 'Custo unit.', 'Valor',
                'Lote', 'Nota', 'Operador',
            ], ';');

            foreach ($linhas as $m) {
                // Mesmas regras do ecrã, para os dois baterem certo: num ajuste
                // a quantidade é o saldo final e não há valor de movimento; nas
                // transferências a perna de saída vem negativa.
                $valor = $m->type === 'adjustment'
                    ? null
                    : abs((float) $m->quantity) * (float) ($m->unit_cost ?? 0);

                fputcsv($saida, [
                    $m->created_at?->format('d/m/Y'),
                    $m->created_at?->format('H:i'),
                    $this->rotuloTipo($m->type),
                    $m->warehouse->name ?? '—',
                    $m->product->code ?? '',
                    $m->product->name ?? '(artigo removido)',
                    number_format((float) $m->quantity, 2, ',', ''),
                    $m->balance_after !== null ? number_format((float) $m->balance_after, 2, ',', '') : '',
                    $m->unit_cost !== null ? number_format((float) $m->unit_cost, 2, ',', '') : '',
                    $valor !== null ? number_format($valor, 2, ',', '') : '',
                    $m->batch_reference ?? '',
                    $m->notes ?? '',
                    $m->user->name ?? '(sistema)',
                ], ';');
            }

            fclose($saida);
        }, $ficheiro, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function rotuloTipo(?string $tipo): string
    {
        return match ($tipo) {
            'in'         => 'Entrada',
            'out'        => 'Saída',
            'adjustment' => 'Ajuste',
            'transfer'   => 'Transferência',
            default      => (string) $tipo,
        };
    }
}
