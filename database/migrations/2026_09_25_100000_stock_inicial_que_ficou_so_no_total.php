<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * O STOCK INICIAL QUE FICOU SÓ NO TOTAL, por ordem do dono (25/09/2026).
 *
 * Criar um artigo com «Quantidade inicial» gravava-a só no agregado
 * (`stock_quantity`), sem linha em armazém nem movimento (corrigido no
 * ProductApiController). O POS e as vendas lêem por armazém: esses artigos
 * estavam a zero e sumiam do balcão. Eram 13 artigos em 5 empresas.
 *
 * Aqui cada um recebe a linha no armazém padrão da empresa (o mesmo que o
 * POS usa) com a quantidade que ficou no total, e o movimento de entrada que
 * a explica. O total já tem essa quantidade e não se toca; por isso as
 * escritas são directas e não passam pelo StockObserver.
 *
 * Só apanha artigos que continuam SEM linha nenhuma: correr duas vezes não
 * faz nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $artigos = DB::table('invoicing_products as p')
            ->whereNull('p.deleted_at')
            ->where('p.type', '!=', 'servico')
            ->where('p.stock_quantity', '>', 0)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('invoicing_stocks as s')->whereColumn('s.product_id', 'p.id'))
            ->get(['p.id', 'p.tenant_id', 'p.name', 'p.stock_quantity', 'p.cost']);

        $acertados = [];

        DB::transaction(function () use ($artigos, &$acertados) {
            foreach ($artigos as $a) {
                $armazem = DB::table('invoicing_warehouses')->where('tenant_id', $a->tenant_id)->where('is_active', true)
                    ->orderByDesc('is_default')->orderBy('name')->value('id');

                if (! $armazem) {
                    Log::warning('Stock inicial: empresa sem armazém activo; artigo por acertar.', ['tenant_id' => $a->tenant_id, 'produto' => $a->id]);

                    continue;
                }

                // O movimento é de quem criou o artigo (a trilha guarda-o); sem isso, do primeiro da empresa.
                $autor = DB::table('audit_trail')->where('auditable_type', Product::class)->where('auditable_id', $a->id)
                    ->where('event', 'created')->whereNotNull('user_id')->value('user_id')
                    ?? DB::table('tenant_user')->where('tenant_id', $a->tenant_id)->orderBy('id')->value('user_id');

                if (! $autor) {
                    Log::warning('Stock inicial: empresa sem utilizadores; artigo por acertar.', ['tenant_id' => $a->tenant_id, 'produto' => $a->id]);

                    continue;
                }

                $quantidade = (float) $a->stock_quantity;
                $custo = $a->cost !== null ? (float) $a->cost : null;
                $agora = now();

                DB::table('invoicing_stocks')->insert([
                    'tenant_id' => $a->tenant_id,
                    'warehouse_id' => $armazem,
                    'product_id' => $a->id,
                    'quantity' => $quantidade,
                    'reserved_quantity' => 0,
                    'available_quantity' => $quantidade,
                    'unit_cost' => $custo,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);

                DB::table('invoicing_stock_movements')->insert([
                    'tenant_id' => $a->tenant_id,
                    'warehouse_id' => $armazem,
                    'product_id' => $a->id,
                    'type' => 'in',
                    'quantity' => $quantidade,
                    'balance_before' => 0,
                    'balance_after' => $quantidade,
                    'unit_cost' => $custo,
                    'total_cost' => $custo !== null ? round($custo * $quantidade, 2) : null,
                    'reference_type' => Product::class,
                    'reference_id' => $a->id,
                    'user_id' => $autor,
                    'notes' => 'Stock inicial (acerto): a quantidade inicial ficou só no total do artigo',
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);

                $acertados[] = "#{$a->tenant_id}/{$a->id} {$a->name} = {$quantidade} (armazém {$armazem})";
            }
        });

        Log::info('Stock inicial: ' . count($acertados) . ' artigo(s) acertado(s).', ['artigos' => $acertados]);
    }

    public function down(): void
    {
        // Não se desfaz: depois disto já pode ter havido vendas desses artigos.
    }
};
