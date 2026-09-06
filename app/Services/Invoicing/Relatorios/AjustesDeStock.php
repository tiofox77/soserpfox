<?php

namespace App\Services\Invoicing\Relatorios;

use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\User;

/**
 * Seguimento dos ajustes de stock.
 *
 * Cobre só o que foi mexido À MÃO — entradas e saídas em lote, ajustes de
 * inventário, transferências entre armazéns e desperdício. As saídas de uma
 * venda e as entradas de uma compra ficam de fora de propósito: essas já têm
 * um documento por trás e os mapas de vendas e compras respondem por elas.
 */
class AjustesDeStock extends Base
{
    /**
     * Origens consideradas MANUAIS. Allowlist e não denylist: com uma lista
     * de exclusões, um tipo de documento novo passaria a aparecer aqui em
     * silêncio. `null` é o caso normal — o ecrã de stock não carimba origem.
     */
    public const ORIGENS_MANUAIS = ['transfer_batch', 'adjustment_batch', 'restaurant_waste'];

    public const TIPOS = ['in' => 'Entrada', 'out' => 'Saída', 'adjustment' => 'Ajuste', 'transfer' => 'Transferência'];

    public static function rotuloTipo(?string $tipo): string
    {
        return self::TIPOS[$tipo] ?? (string) $tipo;
    }

    public function esquema(): array
    {
        return [
            'slug' => 'stock-adjustments',
            'titulo' => 'Ajustes de Stock',
            'descricao' => 'Seguimento do que foi mexido à mão, por operador. Vendas e compras ficam de fora: têm documento.',
            'periodo' => ['omissao' => 'month'],
            'filtros' => [
                ['nome' => 'warehouseFilter', 'rotulo' => 'Armazém', 'tipo' => 'select', 'opcoes' => 'armazens'],
                ['nome' => 'typeFilter', 'rotulo' => 'Tipo', 'tipo' => 'select', 'opcoes' => self::opcoes(self::TIPOS)],
                ['nome' => 'userFilter', 'rotulo' => 'Operador', 'tipo' => 'select', 'opcoes' => 'operadores'],
                ['nome' => 'search', 'rotulo' => 'Procurar', 'tipo' => 'text'],
            ],
            'cartoes' => [
                self::cartao('Entradas', 'resumo.entradas_n', 'inteiro', 'green'),
                self::cartao('Saídas', 'resumo.saidas_n', 'inteiro', 'red'),
                self::cartao('Ajustes', 'resumo.ajustes_n', 'inteiro', 'orange'),
                self::cartao('Transferências', 'resumo.transferencias_n', 'inteiro', 'blue'),
                self::cartao('Valor entrado', 'resumo.entradas_valor', 'dinheiro', 'gray'),
                self::cartao('Valor saído', 'resumo.saidas_valor', 'dinheiro', 'gray'),
            ],
            'tabelas' => [
                ['titulo' => 'Por operador', 'chave' => 'porUtilizador', 'colunas' => [
                    self::col('Operador', 'nome'), self::col('Movimentos', 'n', 'inteiro'), self::col('Qtd. entrada', 'qtd_entrada', 'numero'),
                    self::col('Qtd. saída', 'qtd_saida', 'numero'), self::col('Ajustes', 'ajustes', 'inteiro'), self::col('Valor entrado (Kz)', 'valor_entrada', 'dinheiro'),
                ]],
                ['titulo' => 'Movimentos', 'chave' => 'movimentos', 'colunas' => [
                    self::col('Data', 'created_at', 'data'), self::col('Tipo', 'type', 'estado', 'centro'), self::col('Produto', 'product.name'), self::col('Armazém', 'warehouse.name'),
                    self::col('Qtd.', 'quantity', 'numero'), self::col('Saldo após', 'balance_after', 'numero'), self::col('Valor (Kz)', 'valor', 'dinheiro'),
                    self::col('Documento', 'batch_reference'), self::col('Nota', 'notes'), self::col('Operador', 'user.name'),
                ], 'vazio' => 'Nenhum movimento manual no período.'],
            ],
            'csv' => true,
        ];
    }

    /** Base do mapa: movimentos manuais da empresa, no período escolhido. */
    public function consulta(int $tenantId, array $f)
    {
        [$de, $ate] = $this->intervalo($f, 'month');

        // Todas as colunas qualificadas com a tabela, e o global scope do
        // tenant desligado — o filtro por empresa é feito aqui, à mão e
        // qualificado, porque o join com `users` tem colunas com os mesmos
        // nomes e "Column 'tenant_id' is ambiguous" rebentava a consulta.
        $t = 'invoicing_stock_movements';
        $q = StockMovement::withoutGlobalScopes()
            ->where("{$t}.tenant_id", $tenantId)
            ->where(function ($q) use ($t) {
                $q->whereNull("{$t}.reference_type")
                    ->orWhereIn("{$t}.reference_type", self::ORIGENS_MANUAIS);
            })
            // O dia final tem de entrar inteiro: `created_at` é um timestamp.
            ->whereBetween("{$t}.created_at", [$de . ' 00:00:00', $ate . ' 23:59:59']);

        if ($armazem = $this->filtro($f, 'warehouseFilter')) {
            $q->where("{$t}.warehouse_id", $armazem);
        }
        if ($tipo = $this->filtro($f, 'typeFilter')) {
            $q->where("{$t}.type", $tipo);
        }
        if ($operador = $this->filtro($f, 'userFilter')) {
            $q->where("{$t}.user_id", $operador);
        }
        if ($term = trim((string) ($this->filtro($f, 'search') ?? ''))) {
            $q->where(function ($q) use ($term, $t, $tenantId) {
                $q->where("{$t}.batch_reference", 'like', "%{$term}%")
                    ->orWhere("{$t}.notes", 'like', "%{$term}%")
                    ->orWhereHas('product', function ($p) use ($term, $tenantId) {
                        $p->withoutGlobalScopes()
                            ->where('invoicing_products.tenant_id', $tenantId)
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

    public function dados(int $tenantId, array $f): array
    {
        $movimentos = $this->consulta($tenantId, $f)
            ->with([
                // withTrashed: um artigo retirado do catálogo não pode fazer
                // desaparecer do mapa o movimento que o mexeu.
                'product' => fn ($p) => $p->withTrashed(),
                'user',
                'warehouse',
                'toWarehouse',
            ])
            ->orderByDesc('invoicing_stock_movements.created_at')
            ->orderByDesc('invoicing_stock_movements.id');

        // O ecrã de sempre pagina; o genérico leva uma lista com tecto.
        $movimentos = !empty($f['paginar'])
            ? $movimentos->paginate((int) ($f['perPage'] ?? 25))
            : $movimentos->limit(500)->get()->each(fn ($m) => $m->valor = self::valorDoMovimento($m));

        return [
            'movimentos' => $movimentos,
            'resumo' => $this->resumo($tenantId, $f),
            'porUtilizador' => $this->porUtilizador($tenantId, $f),
            'armazens' => Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get(),
            'operadores' => User::whereIn('id', (clone $this->consulta($tenantId, $f))->distinct()->pluck('invoicing_stock_movements.user_id')->filter())
                ->orderBy('name')->get(),
        ];
    }

    /**
     * O valor de um movimento, pelas regras do ecrã: num ajuste a quantidade
     * é o saldo final e não há valor; nas transferências a perna de saída
     * vem negativa.
     */
    public static function valorDoMovimento(StockMovement $m): ?float
    {
        return $m->type === 'adjustment' ? null : abs((float) $m->quantity) * (float) ($m->unit_cost ?? 0);
    }

    /**
     * Totais do período. Entradas e saídas somam-se; ajustes e
     * transferências NÃO — num ajuste `quantity` é o saldo FINAL e uma
     * transferência não altera o que a empresa tem. Contam-se à parte.
     */
    private function resumo(int $tenantId, array $f): array
    {
        $porTipo = (clone $this->consulta($tenantId, $f))
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
        $saida = $porTipo->get('out');

        return [
            'entradas_n' => (int) ($entrada->n ?? 0),
            'entradas_qtd' => (float) ($entrada->qtd ?? 0),
            'entradas_valor' => (float) ($entrada->valor ?? 0),
            'saidas_n' => (int) ($saida->n ?? 0),
            'saidas_qtd' => (float) ($saida->qtd ?? 0),
            'saidas_valor' => (float) ($saida->valor ?? 0),
            'ajustes_n' => (int) ($porTipo->get('adjustment')->n ?? 0),
            'transferencias_n' => (int) ($porTipo->get('transfer')->n ?? 0),
            'total_n' => (int) $porTipo->sum('n'),
            'lotes_n' => (int) (clone $this->consulta($tenantId, $f))
                ->whereNotNull('invoicing_stock_movements.batch_reference')
                ->distinct()->count('invoicing_stock_movements.batch_reference'),
        ];
    }

    /** Quem mexeu no stock, e quanto — é aqui que se vê a concentração. */
    private function porUtilizador(int $tenantId, array $f)
    {
        return (clone $this->consulta($tenantId, $f))
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
}
