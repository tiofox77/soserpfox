<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Mostra artigos de uma empresa. Só lê — não escreve nada.
 *
 * Serve para conferir em produção o que ficou mesmo gravado, sem ter de
 * acreditar no que a importação disse que fez.
 *
 *   php artisan artigos:ver --tenant=57 --procura=GOGYNAX
 *   php artisan artigos:ver --tenant=57 --zeros
 */
class VerArtigos extends Command
{
    protected $signature = 'artigos:ver
                            {--tenant= : id da empresa}
                            {--procura= : parte do nome ou do código}
                            {--zeros : só os que têm o código a começar por zero}
                            {--limite=40 : quantos mostrar}
                            {--pos : diz se cada artigo aparece no POS, e porquê (activo, módulo, stock por armazém)}
                            {--sem-armazem : artigos com stock no total e sem linha em armazém nenhum (sem --tenant: todas as empresas)}';

    protected $description = 'Mostra artigos de uma empresa (só leitura)';

    public function handle(): int
    {
        if ($this->option('sem-armazem')) {
            return $this->semArmazem();
        }

        $empresa = Tenant::find($this->option('tenant'));

        if (!$empresa) {
            $this->error('Empresa não encontrada: --tenant=' . $this->option('tenant'));

            return self::FAILURE;
        }

        $q = Product::query()->where('tenant_id', $empresa->id);

        if ($termo = $this->option('procura')) {
            $q->where(function ($w) use ($termo) {
                $w->where('name', 'like', "%{$termo}%")
                    ->orWhere('barcode', 'like', "%{$termo}%")
                    ->orWhere('code', 'like', "%{$termo}%");
            });
        }

        if ($this->option('zeros')) {
            $q->where('barcode', 'like', '0%');
        }

        $total = (clone $q)->count();
        $apagados = Product::onlyTrashed()->where('tenant_id', $empresa->id)->count();

        $this->newLine();
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line(sprintf(' artigos vivos no total: %d   na reciclagem: %d   nesta procura: %d',
            Product::query()->where('tenant_id', $empresa->id)->count(), $apagados, $total));
        $this->newLine();

        $this->table(
            ['id', 'barcode', 'code', 'sku', 'nome', 'compra', 'venda'],
            $q->limit((int) $this->option('limite'))->get()
                ->map(fn ($p) => [
                    $p->id,
                    // Entre plicas para se ver se há zeros à frente ou espaços.
                    "'" . $p->barcode . "'",
                    "'" . $p->code . "'",
                    "'" . $p->sku . "'",
                    mb_substr((string) $p->name, 0, 34),
                    $p->cost,
                    $p->price,
                ])->all()
        );

        if ($this->option('pos')) {
            $this->noPos($empresa, (clone $q)->limit((int) $this->option('limite'))->get());
        }

        return self::SUCCESS;
    }

    /**
     * STOCK SÓ NO TOTAL: a quantidade inicial de um artigo novo gravava-se no
     * agregado (`stock_quantity`) sem linha em armazém nem movimento. Para o
     * POS e as vendas, que lêem por armazém, o artigo está a zero (Tecstore,
     * 25/09/2026: quatro telemóveis escondidos do balcão).
     */
    private function semArmazem(): int
    {
        $artigos = \Illuminate\Support\Facades\DB::table('invoicing_products as p')
            ->whereNull('p.deleted_at')
            ->where('p.type', '!=', 'servico')
            ->where('p.stock_quantity', '>', 0)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('invoicing_stocks as s')->whereColumn('s.product_id', 'p.id'))
            ->when(filled($this->option('tenant')), fn ($q) => $q->where('p.tenant_id', (int) $this->option('tenant')))
            ->orderBy('p.tenant_id')->orderBy('p.id')
            ->get(['p.id', 'p.tenant_id', 'p.name', 'p.code', 'p.stock_quantity', 'p.manage_stock', 'p.is_active', 'p.created_at']);

        $empresas = \Illuminate\Support\Facades\DB::table('tenants')->whereIn('id', $artigos->pluck('tenant_id')->unique())->pluck('name', 'id');

        $this->newLine();
        $this->info(sprintf(' ARTIGOS COM STOCK SÓ NO TOTAL (sem linha em armazém): %d, em %d empresa(s)', $artigos->count(), $empresas->count()));

        foreach ($artigos->groupBy('tenant_id') as $tenant => $lista) {
            $this->newLine();
            $this->line(sprintf(' #%s %s — %d artigo(s), %s unidades', $tenant, $empresas[$tenant] ?? '?', $lista->count(), (float) $lista->sum('stock_quantity')));
            $this->table(['id', 'código', 'nome', 'no total', 'gere stock', 'activo', 'criado'], $lista->take((int) $this->option('limite'))->map(fn ($p) => [
                $p->id, $p->code, mb_substr((string) $p->name, 0, 34), (float) $p->stock_quantity,
                $p->manage_stock ? 'sim' : 'não', $p->is_active ? 'sim' : 'não', substr((string) $p->created_at, 0, 16),
            ])->all());
        }

        return self::SUCCESS;
    }

    /**
     * APARECE NO POS? E se não, porquê — com a regra do próprio balcão
     * (PosApiController::artigos): activo, sem módulo, e, com «esconder sem
     * stock» ligado, serviço, sem gestão de stock ou com stock no armazém do POS.
     */
    private function noPos(Tenant $empresa, $artigos): void
    {
        $armazens = \Illuminate\Support\Facades\DB::table('invoicing_warehouses')->where('tenant_id', $empresa->id)
            ->get(['id', 'name', 'is_default', 'is_active']);
        $doPos = $armazens->where('is_active', true)->firstWhere('is_default', true)
            ?? $armazens->where('is_active', true)->sortBy('name')->first();
        $esconde = (bool) (\Illuminate\Support\Facades\DB::table('invoicing_settings')->where('tenant_id', $empresa->id)->value('pos_hide_out_of_stock') ?? true);

        $this->newLine();
        $this->info(sprintf(' NO POS — armazém do balcão: %s · esconder sem stock: %s', $doPos ? "#{$doPos->id} {$doPos->name}" : 'nenhum', $esconde ? 'sim' : 'não'));

        $stock = \Illuminate\Support\Facades\DB::table('invoicing_stocks')->whereIn('product_id', $artigos->pluck('id'))
            ->get(['product_id', 'warehouse_id', 'quantity'])->groupBy('product_id');
        $nomes = $armazens->pluck('name', 'id');

        $this->table(['id', 'nome', 'activo', 'tipo', 'módulo', 'gere stock', 'stock por armazém', 'no POS?'], $artigos->map(function ($p) use ($stock, $nomes, $doPos, $esconde) {
            $linhas = $stock->get($p->id, collect());
            $noArmazem = $doPos ? (float) $linhas->where('warehouse_id', $doPos->id)->sum('quantity') : (float) $p->stock_quantity;

            $motivo = match (true) {
                ! $p->is_active => 'NÃO — inactivo',
                $p->module !== null => "NÃO — é do módulo {$p->module}",
                $esconde && $p->type !== 'servico' && $p->manage_stock && $noArmazem <= 0 => 'NÃO — sem stock no armazém do balcão (' . $noArmazem . ')',
                default => 'sim',
            };

            return [
                $p->id,
                mb_substr((string) $p->name, 0, 30),
                $p->is_active ? 'sim' : 'não',
                $p->type,
                $p->module ?? '—',
                $p->manage_stock ? 'sim' : 'não',
                $linhas->isEmpty() ? 'sem linhas' : $linhas->map(fn ($s) => mb_substr((string) ($nomes[$s->warehouse_id] ?? "#{$s->warehouse_id}"), 0, 14) . ': ' . (float) $s->quantity)->implode(' · '),
                $motivo,
            ];
        })->all());
    }
}
