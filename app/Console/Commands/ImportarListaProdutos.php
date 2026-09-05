<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Tenant\TaxRegimeSyncer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa uma lista de produtos para um tenant, a partir de um JSON simples
 * (categoria, nome, código, preço, custo, stock). Pensado para o arranque de
 * uma empresa com o stock actual — o caso da Tecstore (#39).
 *
 * REGRAS DA CASA que isto respeita:
 *   · fiscalidade pelo REGIME do tenant, nunca 14% fixo (ver taxDefaults);
 *   · manage_stock ligado por omissão — um artigo de retalho gere stock —
 *     mas a lista pode dizer o contrário por linha (`gere_stock`), e um
 *     catálogo de trabalhos à medida não gere existências nenhumas;
 *   · `tipo` distingue produto de serviço, e `preco_no_pos` marca os artigos
 *     cujo preço se escreve no balcão em vez de vir da ficha;
 *   · o stock entra por MOVIMENTO (type=in) e o StockObserver constrói o
 *     agregado invoicing_stocks; no fim ressincroniza-se products.stock_quantity;
 *   · idempotente pelo CÓDIGO (por tenant): correr outra vez não duplica;
 *   · A SECO por omissão — corre tudo numa transacção e faz rollback; só grava
 *     com --aplicar. Assim o «a seco» exercita o caminho verdadeiro (e apanha
 *     erros) sem escrever nada.
 */
class ImportarListaProdutos extends Command
{
    protected $signature = 'produtos:importar-lista
        {--tenant= : id do tenant}
        {--ficheiro= : caminho do JSON (default storage/app/imports/lista-produtos.json)}
        {--aplicar : grava de facto (sem isto, corre a seco)}
        {--apagar-ficheiro : apaga o JSON depois de importar}';

    protected $description = 'Importa produtos (categoria, nome, código, preço, custo, stock) de um JSON para um tenant';

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');
        $ficheiro = $this->option('ficheiro') ?: storage_path('app/imports/lista-produtos.json');
        $aplicar = (bool) $this->option('aplicar');

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant #{$tenantId} não encontrado.");

            return self::FAILURE;
        }

        if (! is_file($ficheiro)) {
            $this->error("Ficheiro não encontrado: {$ficheiro}");

            return self::FAILURE;
        }

        $json = json_decode((string) file_get_contents($ficheiro), true);
        $produtos = $json['produtos'] ?? null;
        if (! is_array($produtos) || ! $produtos) {
            $this->error('JSON inválido ou sem a chave "produtos".');

            return self::FAILURE;
        }

        $warehouse = Warehouse::where('tenant_id', $tenantId)->where('is_default', true)->first()
            ?? Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('id')->first();
        if (! $warehouse) {
            $this->error("Tenant #{$tenantId} não tem armazém — abortado.");

            return self::FAILURE;
        }

        $tax = $this->taxDefaults($tenant);

        // O movimento de stock exige user_id (NOT NULL) e num comando não há
        // sessão — usa-se um utilizador da PRÓPRIA empresa (o dono/primeiro).
        $importUserId = DB::table('tenant_user')->where('tenant_id', $tenantId)->orderBy('user_id')->value('user_id')
            ?? \App\Models\User::where('tenant_id', $tenantId)->orderBy('id')->value('id');
        if (! $importUserId) {
            $this->error("Tenant #{$tenantId} não tem utilizadores — o movimento de stock precisa de um. Abortado.");

            return self::FAILURE;
        }

        $this->line("Tenant:   #{$tenant->id}  {$tenant->name}");
        $this->line("Armazém:  #{$warehouse->id}  {$warehouse->name}");
        $this->line("Utilizador do movimento: #{$importUserId}");
        $this->line('Imposto:  tax_type='.$tax['tax_type'].'  tax_rate_id='.($tax['tax_rate_id'] ?? '—'));
        $this->line("Ficheiro: {$ficheiro}  (".count($produtos).' produtos)');
        $this->line($aplicar ? 'MODO: <fg=red>APLICAR (grava)</>' : 'MODO: A SECO (não grava)');
        $this->newLine();

        $novasCat = [];
        $criados = 0;
        $existentes = 0;
        $comStock = 0;
        $unidades = 0.0;
        $erros = [];

        DB::beginTransaction();
        try {
            foreach ($produtos as $p) {
                $nome = trim((string) ($p['nome'] ?? ''));
                $cod = trim((string) ($p['codigo'] ?? ''));
                $catNome = trim((string) ($p['categoria'] ?? '')) ?: 'Sem categoria';
                $preco = (float) ($p['preco'] ?? 0);
                $custo = (float) ($p['custo'] ?? 0);
                $stock = (float) ($p['stock'] ?? 0);

                // Serviço não é produto e não tem existências. O que a lista
                // não disser fica no comportamento de sempre.
                $tipo = (trim((string) ($p['tipo'] ?? 'produto')) === 'servico') ? 'servico' : 'produto';
                $gereStock = array_key_exists('gere_stock', $p)
                    ? (bool) $p['gere_stock']
                    : ($tipo !== 'servico');
                $precoNoPos = (bool) ($p['preco_no_pos'] ?? false);

                if ($nome === '') {
                    continue;
                }

                // Categoria por (tenant, nome).
                $cat = Category::where('tenant_id', $tenantId)->where('name', $catNome)->first();
                if (! $cat) {
                    $cat = new Category(['name' => $catNome, 'is_active' => true]);
                    $cat->tenant_id = $tenantId;
                    $cat->save();
                    $novasCat[$catNome] = true;
                }

                // Idempotência: por código (ou por nome, se sem código).
                $existe = Product::where('tenant_id', $tenantId)
                    ->when($cod !== '', fn ($q) => $q->where('code', $cod), fn ($q) => $q->where('name', $nome))
                    ->exists();
                if ($existe) {
                    $existentes++;

                    continue;
                }

                try {
                    $prod = new Product([
                        'category_id' => $cat->id,
                        'type' => $tipo,
                        'code' => $cod ?: null,
                        'sku' => $cod ?: null,
                        'barcode' => $cod ?: null,
                        'name' => $nome,
                        'price' => $preco,
                        'cost' => $custo,
                        'tax_type' => $tax['tax_type'],
                        'tax_rate_id' => $tax['tax_rate_id'],
                        'exemption_reason' => $tax['exemption_reason'],
                        'manage_stock' => $gereStock,
                        'preco_no_pos' => $precoNoPos,
                        'stock_quantity' => 0,
                        'stock_min' => 0,
                        'minimum_stock' => 0,
                        'unit' => 'UN',
                        'is_active' => true,
                    ]);
                    $prod->tenant_id = $tenantId;
                    $prod->save();
                    $criados++;

                    // Stock inicial. Num comando não há sessão, e tanto o
                    // Stock::addStock como o createEntry resolvem o tenant por
                    // activeTenantId() (nulo aqui). Por isso escreve-se a linha
                    // invoicing_stocks com o tenant_id EXPLÍCITO — o StockObserver
                    // sincroniza o agregado do produto a partir dela — e o
                    // movimento fica como rasto via semAplicarStock (registado,
                    // sem voltar a debitar o stock que já foi posto acima).
                    if ($stock > 0 && $gereStock) {
                        \App\Models\Invoicing\Stock::create([
                            'tenant_id' => $tenantId,
                            'warehouse_id' => $warehouse->id,
                            'product_id' => $prod->id,
                            'quantity' => $stock,
                            'available_quantity' => $stock,
                            'unit_cost' => $custo,
                        ]);

                        StockMovement::semAplicarStock(fn () => StockMovement::create([
                            'tenant_id' => $tenantId,
                            'warehouse_id' => $warehouse->id,
                            'product_id' => $prod->id,
                            'type' => StockMovement::TYPE_IN,
                            'quantity' => $stock,
                            'unit_cost' => $custo,
                            'reference_type' => 'importacao',
                            'notes' => 'Importação inicial de stock',
                            'user_id' => $importUserId,
                        ]));

                        $comStock++;
                        $unidades += $stock;
                    }
                } catch (\Throwable $e) {
                    $erros[] = "{$nome} ({$cod}): ".$e->getMessage();
                    if (count($erros) > 20) {
                        throw $e; // demasiados erros — pára e mostra
                    }
                }
            }

            if ($aplicar) {
                // Ressincronizar o agregado do produto = SUM(invoicing_stocks).
                DB::update('
                    UPDATE invoicing_products p
                    JOIN (
                        SELECT tenant_id, product_id, SUM(quantity) AS s
                        FROM invoicing_stocks WHERE tenant_id = ?
                        GROUP BY tenant_id, product_id
                    ) a ON a.tenant_id = p.tenant_id AND a.product_id = p.id
                    SET p.stock_quantity = a.s
                    WHERE p.tenant_id = ? AND ROUND(p.stock_quantity,3) <> ROUND(a.s,3)
                ', [$tenantId, $tenantId]);

                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('ERRO (rollback): '.$e->getMessage());
            foreach (array_slice($erros, 0, 10) as $er) {
                $this->line('   · '.$er);
            }

            return self::FAILURE;
        }

        $this->line('Categorias novas:    '.count($novasCat));
        $this->line("Produtos a criar:    {$criados}");
        $this->line("Já existiam (skip):  {$existentes}");
        $this->line("Com stock inicial:   {$comStock}  (".number_format($unidades, 0, ',', '.').' unidades)');
        if ($erros) {
            $this->newLine();
            $this->warn('Erros ('.count($erros).'):');
            foreach (array_slice($erros, 0, 10) as $er) {
                $this->line('   · '.$er);
            }
        }
        $this->newLine();
        $this->info($aplicar ? 'IMPORTADO.' : 'A SECO — nada gravado. Corra com --aplicar para importar.');

        /*
         * O FICHEIRO NÃO FICA NO SERVIDOR.
         *
         * Em produção o site é servido da raiz do projecto — um ficheiro
         * esquecido em storage é um ficheiro que alguém pode descarregar.
         * Importado, apaga-se.
         */
        if ($this->option('apagar-ficheiro') && $aplicar) {
            @unlink($ficheiro);
            $this->line('  Ficheiro apagado do servidor: ' . basename($ficheiro));
        }

        return self::SUCCESS;
    }

    /** Estado fiscal pelo REGIME do tenant (nunca 14% fixo). */
    private function taxDefaults(Tenant $tenant): array
    {
        $meta = $tenant->regimeMeta() ?? Tenant::REGIMES[Tenant::REGIME_GERAL];
        $defTax = Tax::where('tenant_id', $tenant->id)->where('is_default', true)->first();

        return $meta['exempt']
            ? [
                'tax_type' => 'isento',
                'tax_rate_id' => null,
                'exemption_reason' => $defTax->exemption_code ?? $meta['exemption_code'] ?? TaxRegimeSyncer::DEFAULT_EXEMPTION_CODE,
            ]
            : [
                'tax_type' => 'iva',
                'tax_rate_id' => $defTax?->id,
                'exemption_reason' => null,
            ];
    }
}
