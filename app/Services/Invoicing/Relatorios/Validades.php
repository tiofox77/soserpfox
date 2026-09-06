<?php

namespace App\Services\Invoicing\Relatorios;

use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Os lotes a expirar, os expirados, e o valor em risco. */
class Validades extends Base
{
    public const TIPOS = ['expiring_soon' => 'Expirando em Breve', 'expired' => 'Já Expirados', 'all' => 'Todos com Validade'];

    public const DIAS = ['7' => '7 dias', '15' => '15 dias', '30' => '30 dias', '60' => '60 dias', '90' => '90 dias'];

    public function esquema(): array
    {
        return [
            'slug' => 'expiry-report',
            'titulo' => 'Validade de Produtos',
            'descricao' => 'Lotes próximos da validade ou já expirados, com o valor em risco.',
            'periodo' => null,
            'filtros' => [
                ['nome' => 'reportType', 'rotulo' => 'Mostrar', 'tipo' => 'select', 'opcoes' => self::opcoes(self::TIPOS), 'omissao' => 'expiring_soon'],
                ['nome' => 'daysFilter', 'rotulo' => 'Janela', 'tipo' => 'select', 'opcoes' => self::opcoes(self::DIAS), 'omissao' => '30'],
                ['nome' => 'warehouseFilter', 'rotulo' => 'Armazém', 'tipo' => 'select', 'opcoes' => 'warehouses'],
                ['nome' => 'categoryFilter', 'rotulo' => 'Categoria', 'tipo' => 'select', 'opcoes' => 'categories'],
                ['nome' => 'searchFilter', 'rotulo' => 'Procurar', 'tipo' => 'text'],
            ],
            'cartoes' => [
                self::cartao('Com validade', 'stats.total_with_expiry', 'inteiro', 'blue'),
                self::cartao('Expiram em 7 dias', 'stats.expiring_7_days', 'inteiro', 'orange'),
                self::cartao('Expiram em 30 dias', 'stats.expiring_30_days', 'inteiro', 'yellow'),
                self::cartao('Já expirados', 'stats.expired', 'inteiro', 'red'),
                self::cartao('Valor em risco', 'stats.value_at_risk', 'dinheiro', 'purple'),
                self::cartao('Valor perdido', 'stats.value_lost', 'dinheiro', 'gray'),
            ],
            'tabelas' => [[
                'chave' => 'batches',
                'colunas' => [
                    self::col('Produto', 'product.name'), self::col('Categoria', 'product.category.name'), self::col('Lote', 'batch_number'), self::col('Armazém', 'warehouse.name'),
                    self::col('Validade', 'expiry_date', 'data'), self::col('Dias', 'dias', 'dias'), self::col('Qtd Disp.', 'quantity_available', 'numero'),
                    self::col('Valor', 'valor', 'dinheiro'), self::col('Estado', 'status', 'estado', 'centro'),
                ],
                'vazio' => 'Nenhum lote nestas condições.',
            ]],
            'csv' => true,
        ];
    }

    /** A consulta filtrada, sem paginação — a exportação leva EXACTAMENTE o que está no ecrã. */
    public function consulta(int $tenantId, array $f)
    {
        $tipo = $this->filtro($f, 'reportType') ?? 'expiring_soon';
        $dias = (int) ($this->filtro($f, 'daysFilter') ?? 30);

        $query = ProductBatch::with(['product.category', 'warehouse'])
            ->where('invoicing_product_batches.tenant_id', $tenantId)
            ->where('quantity_available', '>', 0);

        switch ($tipo) {
            case 'expired':
                $query->whereDate('expiry_date', '<', Carbon::now());
                break;
            case 'all':
                $query->whereNotNull('expiry_date');
                break;
            default:
                $query->where('status', 'active')
                    ->whereDate('expiry_date', '<=', Carbon::now()->addDays($dias))
                    ->whereDate('expiry_date', '>=', Carbon::now());
        }

        if ($armazem = $this->filtro($f, 'warehouseFilter')) {
            $query->where('warehouse_id', $armazem);
        }
        if ($categoria = $this->filtro($f, 'categoryFilter')) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $categoria));
        }
        if ($procura = $this->filtro($f, 'searchFilter')) {
            $query->where(function ($q) use ($procura) {
                $q->where('batch_number', 'like', '%' . $procura . '%')
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%' . $procura . '%'));
            });
        }

        return $query->orderBy('expiry_date', 'asc');
    }

    public function dados(int $tenantId, array $f): array
    {
        $consulta = $this->consulta($tenantId, $f);

        // O ecrã de sempre pagina; o genérico leva uma lista com tecto e os
        // campos calculados já feitos.
        $batches = !empty($f['paginar'])
            ? $consulta->paginate(20)
            : $consulta->limit(500)->get()->each(function ($lote) {
                $lote->dias = $lote->days_until_expiry;
                $lote->valor = (float) $lote->quantity_available * (float) $lote->cost_price;
            });

        return [
            'batches' => $batches,
            'stats' => $this->estatisticas($tenantId),
            'warehouses' => Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'categories' => DB::table('invoicing_categories')->where('tenant_id', $tenantId)->orderBy('name')->get(),
        ];
    }

    private function estatisticas(int $tenantId): array
    {
        $base = fn () => ProductBatch::where('tenant_id', $tenantId)->where('quantity_available', '>', 0);
        $aExpirar = fn (int $dias) => $base()->where('status', 'active')
            ->whereDate('expiry_date', '<=', Carbon::now()->addDays($dias))
            ->whereDate('expiry_date', '>=', Carbon::now());

        return [
            'total_with_expiry' => $base()->whereNotNull('expiry_date')->count(),
            'expiring_7_days' => $aExpirar(7)->count(),
            'expiring_30_days' => $aExpirar(30)->count(),
            'expired' => $base()->whereDate('expiry_date', '<', Carbon::now())->count(),
            'value_at_risk' => $aExpirar(30)->sum(DB::raw('quantity_available * cost_price')),
            'value_lost' => $base()->whereDate('expiry_date', '<', Carbon::now())->sum(DB::raw('quantity_available * cost_price')),
        ];
    }
}
