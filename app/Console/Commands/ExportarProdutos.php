<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Exporta os produtos de um tenant para um JSON (nome, código, categoria,
 * preço, custo, stock). Serve para gerar uma lista de preços fora do sistema
 * (ex.: um PDF) sem sessão de admin no tenant.
 *
 * Só LÊ. Escreve o JSON num ficheiro em storage/app/exports/ (fora da raiz web
 * quando o docroot está bem apontado; é o catálogo do cliente, não é segredo).
 */
class ExportarProdutos extends Command
{
    protected $signature = 'produtos:exportar
        {--tenant= : id do tenant}
        {--saida= : caminho do JSON de saída (default storage/app/exports/produtos-tenant-ID.json)}
        {--so-activos : exporta apenas os produtos activos}';

    protected $description = 'Exporta os produtos de um tenant (nome, código, categoria, preço, custo, stock) para JSON';

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant #{$tenantId} não encontrado.");

            return self::FAILURE;
        }

        $q = DB::table('invoicing_products as p')
            ->leftJoin('invoicing_categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.tenant_id', $tenantId)
            ->where('p.type', 'produto');

        if ($this->option('so-activos')) {
            $q->where('p.is_active', true);
        }

        $produtos = $q->orderBy('c.name')->orderBy('p.name')
            ->get(['p.name', 'p.code', 'p.price', 'p.cost', 'p.stock_quantity', 'p.is_active', 'c.name as categoria'])
            ->map(fn ($r) => [
                'categoria' => $r->categoria ?: 'Sem categoria',
                'nome' => $r->name,
                'codigo' => $r->code,
                'preco' => (float) $r->price,
                'custo' => (float) $r->cost,
                'stock' => (float) $r->stock_quantity,
                'activo' => (bool) $r->is_active,
            ])
            ->values()
            ->all();

        $saida = $this->option('saida') ?: storage_path("app/exports/produtos-tenant-{$tenantId}.json");
        @mkdir(dirname($saida), 0775, true);

        file_put_contents($saida, json_encode([
            'tenant_id' => $tenant->id,
            'empresa' => $tenant->name,
            'nif' => $tenant->nif,
            'gerado_em' => now()->toIso8601String(),
            'total' => count($produtos),
            'produtos' => $produtos,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line("Empresa:  #{$tenant->id}  {$tenant->name}");
        $this->line('Produtos: '.count($produtos));
        $this->line("Ficheiro: {$saida}");
        $this->info('EXPORTADO.');

        return self::SUCCESS;
    }
}
