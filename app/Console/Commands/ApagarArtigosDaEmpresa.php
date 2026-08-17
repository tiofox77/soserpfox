<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Apaga da base de dados, a sério, os artigos de UMA empresa.
 *
 * Isto não é o apagar do ecrã de produtos, que só põe na reciclagem. Isto
 * remove mesmo a linha, e não há como voltar atrás. Existe para desfazer uma
 * importação e refazê-la de raiz.
 *
 * NUNCA apaga um artigo que esteja num documento. Dezanove tabelas apontam
 * para os artigos, e algumas são facturas, notas de crédito e compras — o
 * histórico fiscal de uma empresa. Um artigo que já foi vendido fica, e é
 * reportado; só sai o que não está preso a nada.
 *
 * O stock e os movimentos de stock desses artigos saem com eles, porque foi
 * a importação que os criou.
 *
 * SIMULAÇÃO POR OMISSÃO. Sem --aplicar diz o que faria.
 *
 *   php artisan artigos:apagar --tenant=57
 *   php artisan artigos:apagar --tenant=57 --aplicar --confirmo-que-apaga
 */
class ApagarArtigosDaEmpresa extends Command
{
    protected $signature = 'artigos:apagar
                            {--tenant= : id da empresa}
                            {--aplicar : apaga (sem isto é simulação)}
                            {--confirmo-que-apaga : segunda confirmação, obrigatória com --aplicar}';

    protected $description = 'Apaga da base de dados os artigos de uma empresa que não estejam em documentos';

    /**
     * Onde um artigo pode estar preso. Estar em qualquer uma destas é motivo
     * para o artigo ficar: são documentos, receitas e marcações de clientes.
     *
     * @var array<string,string>
     */
    private const LIGACOES = [
        'invoicing_sales_invoice_items'     => 'product_id',
        'invoicing_sales_proforma_items'    => 'product_id',
        'invoicing_credit_note_items'       => 'product_id',
        'invoicing_debit_note_items'        => 'product_id',
        'invoicing_purchase_invoice_items'  => 'product_id',
        'invoicing_purchase_order_items'    => 'product_id',
        'invoicing_purchase_proforma_items' => 'product_id',
        'invoicing_import_items'            => 'product_id',
        'invoicing_product_batches'         => 'product_id',
        'invoicing_batch_allocations'       => 'product_id',
        'restaurant_order_items'            => 'product_id',
        'restaurant_recipes'                => 'product_id',
        'restaurant_recipe_items'           => 'ingredient_product_id',
        'salon_appointment_services'        => 'service_id',
        'salon_package_services'            => 'service_id',
        'salon_professional_services'       => 'service_id',
        'workshop_work_order_items'         => 'product_id',
    ];

    /** Estas saem com o artigo: foi a importação que as criou. */
    private const ARRASTA = [
        'invoicing_stocks'          => 'product_id',
        'invoicing_stock_movements' => 'product_id',
    ];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $empresa = Tenant::find($this->option('tenant'));

        if (!$empresa) {
            $this->error('Empresa não encontrada: --tenant=' . $this->option('tenant'));

            return self::FAILURE;
        }

        if ($aplicar && !$this->option('confirmo-que-apaga')) {
            $this->error('--aplicar exige também --confirmo-que-apaga. Isto apaga da base a sério.');

            return self::FAILURE;
        }

        // Com a reciclagem incluída: quem manda apagar tudo quer tudo fora,
        // e uma linha apagada continua a ocupar o índice único do `code`,
        // o que faria a reimportação rebentar.
        $ids = Product::withTrashed()->where('tenant_id', $empresa->id)->pluck('id');

        $this->newLine();
        $this->line(str_repeat('=', 62));
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line(' Artigos na empresa (com reciclagem): ' . $ids->count());
        $this->line($aplicar ? ' MODO: --aplicar — VAI APAGAR DA BASE.' : ' MODO: simulação — nada é apagado.');
        $this->line(str_repeat('=', 62));

        if ($ids->isEmpty()) {
            $this->warn('  Não há artigos nesta empresa.');

            return self::SUCCESS;
        }

        $presos = collect();

        foreach (self::LIGACOES as $tabela => $coluna) {
            if (!$this->existe($tabela)) {
                continue;
            }

            $usados = collect();

            foreach ($ids->chunk(1000) as $lote) {
                $usados = $usados->merge(
                    DB::table($tabela)->whereIn($coluna, $lote)->distinct()->pluck($coluna)
                );
            }

            if ($usados->isNotEmpty()) {
                $this->warn(sprintf('  presos em %-38s %d', $tabela, $usados->unique()->count()));
                $presos = $presos->merge($usados);
            }
        }

        $presos = $presos->unique();
        $apagaveis = $ids->diff($presos);

        $this->newLine();
        $this->line(sprintf('  ficam (estão em documentos): %d', $presos->count()));
        $this->line(sprintf('  a apagar da base:            %d', $apagaveis->count()));

        foreach (self::ARRASTA as $tabela => $coluna) {
            if (!$this->existe($tabela)) {
                continue;
            }

            $n = 0;

            foreach ($apagaveis->chunk(1000) as $lote) {
                $n += DB::table($tabela)->whereIn($coluna, $lote)->count();
            }

            $this->line(sprintf('    arrasta %-38s %d', $tabela, $n));
        }

        if (!$aplicar) {
            $this->newLine();
            $this->warn('  Nada foi apagado. Repita com --aplicar --confirmo-que-apaga.');

            return self::SUCCESS;
        }

        $apagados = 0;

        foreach ($apagaveis->chunk(500) as $lote) {
            DB::transaction(function () use ($lote, &$apagados) {
                foreach (self::ARRASTA as $tabela => $coluna) {
                    if ($this->existe($tabela)) {
                        DB::table($tabela)->whereIn($coluna, $lote)->delete();
                    }
                }

                $apagados += Product::withTrashed()->whereIn('id', $lote)->forceDelete();
            });

            $this->output->write('.');
        }

        $this->newLine(2);
        $this->info(sprintf('  ✓ %d artigos apagados da base. Ficaram %d por estarem em documentos.',
            $apagados, $presos->count()));

        return self::SUCCESS;
    }

    private function existe(string $tabela): bool
    {
        static $cache = [];

        return $cache[$tabela] ??= DB::getSchemaBuilder()->hasTable($tabela);
    }
}
