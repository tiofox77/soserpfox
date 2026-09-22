<?php

namespace App\Services\Invoicing\Relatorios;

use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * O QUE É PRECISO REPOR (22/09/2026).
 *
 * Os artigos que geram stock e estão EM FALTA (existência a zero ou abaixo,
 * com ou sem linha no armazém) ou ABAIXO DO MÍNIMO, com o que saiu nos
 * últimos 30 dias, quantos dias a existência ainda aguenta e quanto
 * encomendar. As regras são as do ecrã de Stock (StockApiController) — o
 * mínimo vive no artigo (`stock_min`), e «baixo» é existência <= mínimo —
 * para o mapa e o ecrã nunca contarem artigos diferentes.
 *
 * É uma fotografia de agora, sem período: o stock que falta é o de hoje.
 */
class StockEmFalta extends Base
{
    public const SITUACOES = [
        'repor' => 'Em falta e abaixo do mínimo',
        'falta' => 'Só em falta',
        'baixo' => 'Só abaixo do mínimo',
        'sem_minimo' => 'Sem mínimo definido',
    ];

    /** Os dias de consumo que se olham para trás — a mesma janela do cartão. */
    public const DIAS_DE_CONSUMO = 30;

    /** Tecto da lista no ecrã genérico (o CSV leva o mesmo). */
    public const LIMITE = 2000;

    public function esquema(): array
    {
        return [
            'slug' => 'stock-levels',
            'titulo' => 'Stock em Falta e Abaixo do Mínimo',
            'descricao' => 'O que falta e o que está abaixo do mínimo, com o consumo dos últimos 30 dias, os dias que ainda aguenta e quanto encomendar.',
            'periodo' => null,
            'filtros' => [
                ['nome' => 'situacao', 'rotulo' => 'Mostrar', 'tipo' => 'select', 'opcoes' => self::opcoes(self::SITUACOES), 'omissao' => 'repor'],
                ['nome' => 'warehouseFilter', 'rotulo' => 'Armazém', 'tipo' => 'select', 'opcoes' => 'armazens'],
                ['nome' => 'categoryFilter', 'rotulo' => 'Categoria', 'tipo' => 'select', 'opcoes' => 'categorias'],
                ['nome' => 'supplierFilter', 'rotulo' => 'Fornecedor', 'tipo' => 'select', 'opcoes' => 'fornecedores'],
                ['nome' => 'search', 'rotulo' => 'Procurar', 'tipo' => 'text'],
            ],
            'cartoes' => [
                self::cartao('Em falta', 'resumo.em_falta', 'inteiro', 'red'),
                self::cartao('Abaixo do mínimo', 'resumo.abaixo_do_minimo', 'inteiro', 'orange'),
                self::cartao('Sem mínimo definido', 'resumo.sem_minimo', 'inteiro', 'gray'),
                self::cartao('Valor a repor', 'resumo.valor_a_repor', 'dinheiro', 'purple'),
            ],
            'tabelas' => [[
                'chave' => 'artigos',
                'colunas' => [
                    self::col('Situação', 'situacao'),
                    self::col('Artigo', 'nome'),
                    self::col('Código', 'codigo'),
                    self::col('Categoria', 'categoria'),
                    self::col('Fornecedor', 'fornecedor'),
                    self::col('Existência', 'existencia', 'numero'),
                    self::col('Mínimo', 'minimo', 'numero'),
                    self::col('Máximo', 'maximo', 'numero'),
                    self::col('Saídas (30 dias)', 'saidas_30_dias', 'numero'),
                    self::col('Aguenta', 'cobertura_dias', 'dias'),
                    self::col('A encomendar', 'a_encomendar', 'numero'),
                    self::col('Custo (Kz)', 'custo', 'dinheiro'),
                    self::col('Valor a repor (Kz)', 'valor_a_repor', 'dinheiro'),
                ],
                'rodape' => ['valor_a_repor' => 'resumo.valor_a_repor'],
                'vazio' => 'Nenhum artigo nesta situação.',
            ]],
            'csv' => true,
        ];
    }

    public function dados(int $tenantId, array $f): array
    {
        $situacao = $this->filtro($f, 'situacao') ?? 'repor';
        $armazem = ($a = $this->filtro($f, 'warehouseFilter')) ? (int) $a : null;

        $linhas = $this->linhas($tenantId, $f, $armazem);

        // Os cartões contam SEMPRE o quadro inteiro (com os mesmos filtros de
        // armazém, categoria e fornecedor), para se ver o tamanho do problema
        // mesmo com a lista apertada a uma só situação.
        $resumo = [
            'em_falta' => $linhas->where('chave', 'falta')->count(),
            'abaixo_do_minimo' => $linhas->where('chave', 'baixo')->count(),
            'sem_minimo' => $linhas->where('sem_minimo', true)->count(),
        ];

        $escolhidas = match ($situacao) {
            'falta' => $linhas->where('chave', 'falta'),
            'baixo' => $linhas->where('chave', 'baixo'),
            'sem_minimo' => $linhas->where('sem_minimo', true),
            default => $linhas->whereIn('chave', ['falta', 'baixo']),
        };

        // Em falta primeiro; dentro de cada grupo, o que aguenta menos dias.
        $escolhidas = $escolhidas->sortBy([
            fn ($a, $b) => ($a['chave'] === 'falta' ? 0 : 1) <=> ($b['chave'] === 'falta' ? 0 : 1),
            fn ($a, $b) => ($a['cobertura_dias'] ?? PHP_INT_MAX) <=> ($b['cobertura_dias'] ?? PHP_INT_MAX),
            fn ($a, $b) => strcasecmp((string) $a['nome'], (string) $b['nome']),
        ])->values();

        $resumo['valor_a_repor'] = round((float) $escolhidas->sum('valor_a_repor'), 2);
        $resumo['artigos'] = $escolhidas->count();

        return [
            'artigos' => $escolhidas->take(self::LIMITE)->all(),
            'resumo' => $resumo,
            'armazens' => Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'categorias' => DB::table('invoicing_categories')->where('tenant_id', $tenantId)->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            'fornecedores' => DB::table('invoicing_suppliers')->where('tenant_id', $tenantId)->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Uma linha por artigo que gere stock, com a existência e as saídas já
     * somadas na base — duas subconsultas por artigo, e não um carregamento
     * de todas as linhas de stock e de movimentos para somar em PHP.
     */
    private function linhas(int $tenantId, array $f, ?int $armazem)
    {
        $desde = now()->subDays(self::DIAS_DE_CONSUMO)->toDateTimeString();

        $existencia = 'SELECT COALESCE(SUM(s.quantity), 0) FROM invoicing_stocks s'
            . ' WHERE s.product_id = invoicing_products.id AND s.tenant_id = ?'
            . ($armazem ? ' AND s.warehouse_id = ?' : '');

        $saidas = 'SELECT COALESCE(SUM(ABS(m.quantity)), 0) FROM invoicing_stock_movements m'
            . " WHERE m.product_id = invoicing_products.id AND m.tenant_id = ? AND m.type = 'out' AND m.created_at >= ?"
            . ($armazem ? ' AND m.warehouse_id = ?' : '');

        $q = Product::withoutGlobalScopes()
            ->with(['category:id,name', 'supplier:id,name'])
            ->where('invoicing_products.tenant_id', $tenantId)
            ->whereNull('invoicing_products.deleted_at')
            ->where('invoicing_products.is_active', true)
            // Só o que GERE stock: um serviço não «falta» em armazém nenhum.
            ->where('invoicing_products.manage_stock', true)
            ->select('invoicing_products.*')
            ->selectRaw("({$existencia}) AS existencia_calculada", $armazem ? [$tenantId, $armazem] : [$tenantId])
            ->selectRaw("({$saidas}) AS saidas_calculadas", $armazem ? [$tenantId, $desde, $armazem] : [$tenantId, $desde]);

        if ($categoria = $this->filtro($f, 'categoryFilter')) {
            $q->where('invoicing_products.category_id', (int) $categoria);
        }
        if ($fornecedor = $this->filtro($f, 'supplierFilter')) {
            $q->where('invoicing_products.supplier_id', (int) $fornecedor);
        }
        if ($termo = trim((string) ($this->filtro($f, 'search') ?? ''))) {
            $q->where(function ($p) use ($termo) {
                $p->where('invoicing_products.name', 'like', "%{$termo}%")
                    ->orWhere('invoicing_products.code', 'like', "%{$termo}%")
                    ->orWhere('invoicing_products.barcode', 'like', "%{$termo}%");
            });
        }

        return $q->orderBy('invoicing_products.name')->get()->map(fn (Product $p) => self::linha($p))->values();
    }

    /**
     * A situação e as contas de um artigo.
     *
     * A ENCOMENDAR: até ao máximo, quando há máximo; sem ele, até cobrir o que
     * saiu em 30 dias — ou o mínimo, se for maior. Sem máximo, sem mínimo e
     * sem saídas não há base para sugerir nada, e a coluna fica vazia em vez
     * de inventar um número.
     *
     * @return array<string, mixed>
     */
    public static function linha(Product $p): array
    {
        $existencia = round((float) $p->existencia_calculada, 3);
        $saidas = round((float) $p->saidas_calculadas, 3);
        $minimo = round((float) ($p->stock_min ?? 0), 3);
        $maximo = round((float) ($p->stock_max ?? 0), 3);
        $custo = round((float) ($p->cost ?? 0), 2);

        $chave = match (true) {
            $existencia <= 0 => 'falta',
            $minimo > 0 && $existencia <= $minimo => 'baixo',
            default => 'ok',
        };

        $alvo = $maximo > 0 ? $maximo : max($minimo, $saidas);
        $aEncomendar = $alvo > 0 ? round(max(0, $alvo - $existencia), 3) : null;

        $porDia = $saidas / self::DIAS_DE_CONSUMO;
        $cobertura = match (true) {
            $existencia <= 0 => 0,
            $porDia > 0 => (int) floor($existencia / $porDia),
            default => null,
        };

        return [
            'product_id' => $p->id,
            'chave' => $chave,
            'situacao' => match ($chave) {
                'falta' => __('Em falta'),
                'baixo' => __('Abaixo do mínimo'),
                default => __('Com stock'),
            },
            'sem_minimo' => $minimo <= 0,
            'nome' => $p->name,
            'codigo' => $p->code,
            'categoria' => $p->category?->name,
            'fornecedor' => $p->supplier?->name,
            'unidade' => $p->unit,
            'existencia' => $existencia,
            'minimo' => $minimo,
            'maximo' => $maximo,
            'saidas_30_dias' => $saidas,
            'cobertura_dias' => $cobertura,
            'a_encomendar' => $aEncomendar,
            'custo' => $custo,
            'valor_a_repor' => $aEncomendar !== null ? round($aEncomendar * $custo, 2) : 0.0,
        ];
    }
}
