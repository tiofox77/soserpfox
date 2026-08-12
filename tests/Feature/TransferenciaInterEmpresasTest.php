<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\InterCompanyTransfer;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Transferência de stock entre EMPRESAS.
 *
 * O mesmo tratamento dado às transferências entre armazéns — referência
 * legível, saldos, documento, quantidade corrigível, grelha que não carrega o
 * catálogo todo — com uma diferença que muda o desenho: são duas empresas, e a
 * numeração MOV/AAAA/NNNNNN é sequencial POR EMPRESA.
 *
 * Daí as DUAS referências. Escrever a da origem nas linhas do destino corrompia
 * a sequência do destino: se ele já tivesse ido mais longe, passavam a existir
 * lá dois documentos com o mesmo número.
 */
class TransferenciaInterEmpresasTest extends TenantTestCase
{
    protected Tenant $empresaB;
    protected Warehouse $armazemB;
    protected Product $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresaB = Tenant::create([
            'name'      => 'Empresa Destino',
            'slug'      => 'empresa-destino-' . uniqid(),
            'nif'       => (string) random_int(600000000, 699999999),
            'email'     => 'destino' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        // O utilizador tem de ter acesso às duas: o ecrã resolve o destino a
        // partir das empresas DO UTILIZADOR, e bem — um id arbitrário permitia
        // escrever stock em qualquer empresa da plataforma.
        $this->user->tenants()->syncWithoutDetaching([$this->empresaB->id]);

        $this->armazemB = Warehouse::withoutGlobalScopes()
            ->where('tenant_id', $this->empresaB->id)
            ->where('is_active', true)
            ->first()
            ?? Warehouse::create([
                'tenant_id'  => $this->empresaB->id,
                'name'       => 'Armazém B',
                'code'       => 'ARM-B',
                'is_active'  => true,
                'is_default' => true,
            ]);

        $this->produto = Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'AMOXICILINA 500MG',
            'sku'            => 'AMOX-500',
            'code'           => 'AMOX-500',
            'barcode'        => '7890000000123',
            'price'          => 1200,
            'cost'           => 700,
            'type'           => 'produto',
            'manage_stock'   => true,
            'stock_quantity' => 0,
        ]);

        $this->comModulo('invoicing');
    }

    private function comStock(float $q): Stock
    {
        return Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->produto->id,
            'quantity'     => $q,
            'unit_cost'    => 700,
        ]);
    }

    private function transferir(float $q = 4)
    {
        return Livewire::test(InterCompanyTransfer::class)
            ->set('warehouseFromId', $this->armazem->id)
            ->set('tenantToId', $this->empresaB->id)
            ->set('warehouseToId', $this->armazemB->id)
            ->set('notes', 'Reposição entre lojas')
            ->set('transferItems', [[
                'product_id'    => $this->produto->id,
                'product_name'  => $this->produto->name,
                'product_code'  => $this->produto->code,
                'quantity'      => $q,
                'unit_cost'     => 700,
                'ultima_valida' => $q,
            ]])
            ->call('saveTransfer');
    }

    /** As duas pernas: a da empresa activa e a da empresa destino. */
    private function pernas(): array
    {
        $saida = StockMovement::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('reference_type', 'inter_company')
            ->latest('id')->first();

        $entrada = StockMovement::withoutGlobalScopes()
            ->where('tenant_id', $this->empresaB->id)
            ->where('reference_type', 'inter_company')
            ->latest('id')->first();

        return [$saida, $entrada];
    }

    public function test_cada_empresa_recebe_a_sua_referencia(): void
    {
        $this->comStock(20);
        $this->transferir(4);

        [$saida, $entrada] = $this->pernas();

        $this->assertMatchesRegularExpression('#^MOV/\d{4}/\d{6}$#', (string) $saida->batch_reference);
        $this->assertMatchesRegularExpression('#^MOV/\d{4}/\d{6}$#', (string) $entrada->batch_reference);
    }

    public function test_a_referencia_do_destino_sai_da_sequencia_do_destino(): void
    {
        // A regressão que isto evita: com uma referência partilhada, a da
        // origem entrava na sequência do destino. Se o destino já lá tivesse
        // chegado, ficavam dois documentos diferentes com o mesmo número — e o
        // ecrã do documento juntava-os num só.
        //
        // Põe-se o destino MUITO à frente e confirma-se que ele continua a sua
        // própria contagem em vez de herdar a da origem.
        StockMovement::semAplicarStock(fn () => StockMovement::withoutGlobalScopes()->create([
            'tenant_id'       => $this->empresaB->id,
            'warehouse_id'    => $this->armazemB->id,
            'product_id'      => $this->produto->id,
            'type'            => 'in',
            'quantity'        => 1,
            'user_id'         => $this->user->id,
            'batch_reference' => 'MOV/' . now()->year . '/000900',
        ]));

        $this->comStock(20);
        $this->transferir(4);

        [$saida, $entrada] = $this->pernas();

        $this->assertSame('MOV/' . now()->year . '/000901', $entrada->batch_reference, 'segue a sequência do destino');
        $this->assertNotSame($saida->batch_reference, $entrada->batch_reference, 'são dois documentos');
    }

    public function test_os_quatro_saldos_ficam_gravados(): void
    {
        // Na perna do destino os saldos TÊM de ir explícitos: o carimbo
        // automático lê o stock com o global scope da empresa activa — a
        // origem — e para o destino não devolvia nada.
        $this->comStock(20);
        $this->transferir(4);

        [$saida, $entrada] = $this->pernas();

        $this->assertSame(20.0, (float) $saida->balance_before);
        $this->assertSame(16.0, (float) $saida->balance_after);

        $this->assertSame(0.0, (float) $entrada->balance_before, 'o destino não tinha nenhum');
        $this->assertSame(4.0, (float) $entrada->balance_after);
    }

    public function test_o_agregado_do_produto_no_destino_e_actualizado(): void
    {
        // O defeito: a linha de stock do destino era gravada com saveQuietly(),
        // que salta o StockObserver — quem mantém `stock_quantity` igual à soma
        // das linhas. Um artigo que chegava pela primeira vez à empresa destino
        // ficava com linha preenchida e agregado a zero: invisível no POS e na
        // gestão de stock.
        $this->comStock(20);
        $this->transferir(4);

        $produtoNoDestino = Product::withoutGlobalScopes()
            ->where('tenant_id', $this->empresaB->id)
            ->where('name', $this->produto->name)
            ->first();

        $this->assertNotNull($produtoNoDestino, 'o artigo é copiado para a empresa destino');

        $linha = (float) DB::table('invoicing_stocks')
            ->where('tenant_id', $this->empresaB->id)
            ->where('product_id', $produtoNoDestino->id)
            ->sum('quantity');

        $this->assertSame(4.0, $linha, 'a linha de stock');
        $this->assertSame(
            4.0,
            (float) $produtoNoDestino->stock_quantity,
            'o agregado tem de acompanhar a linha, senão o artigo é invisível no POS'
        );
    }

    public function test_o_stock_sai_de_uma_empresa_e_entra_na_outra(): void
    {
        $this->comStock(20);
        $this->transferir(4);

        $this->assertSame(
            16.0,
            (float) DB::table('invoicing_stocks')
                ->where('tenant_id', $this->tenant->id)
                ->where('product_id', $this->produto->id)
                ->sum('quantity')
        );

        $this->assertSame(
            4.0,
            (float) DB::table('invoicing_stocks')
                ->where('tenant_id', $this->empresaB->id)
                ->sum('quantity')
        );
    }

    public function test_o_documento_do_lote_abre(): void
    {
        $this->comPermissoes('invoicing.stock.view');

        $this->comStock(20);
        $this->transferir(4);

        [$saida] = $this->pernas();

        // Dentro de cada empresa a movimentação tem UM lado só — a outra perna
        // pertence à outra empresa. Por isso vai no documento de entradas e
        // saídas e não no de transferências, que junta as duas pernas.
        $this->get(route('invoicing.stock.batch-preview', ['reference' => $saida->batch_reference]))
            ->assertOk()
            ->assertSee('Movimentação de Stock')
            ->assertSee($saida->batch_reference)
            ->assertSee('Empresa Destino');   // vem na nota do movimento
    }

    public function test_corrigir_a_quantidade_no_carrinho(): void
    {
        $this->comStock(20);

        Livewire::test(InterCompanyTransfer::class)
            ->set('warehouseFromId', $this->armazem->id)
            ->set('transferItems', [[
                'product_id'    => $this->produto->id,
                'product_name'  => $this->produto->name,
                'product_code'  => $this->produto->code,
                'quantity'      => 4,
                'unit_cost'     => 700,
                'ultima_valida' => 4,
            ]])
            ->set('transferItems.0.quantity', 7)
            ->assertSet('transferItems.0.quantity', 7.0);
    }

    public function test_uma_quantidade_negativa_nao_passa_pela_correcao(): void
    {
        // Um negativo aqui tira stock ao destino e dá-o à origem — o contrário
        // do que se pediu, em duas empresas ao mesmo tempo.
        $this->comStock(20);

        Livewire::test(InterCompanyTransfer::class)
            ->set('warehouseFromId', $this->armazem->id)
            ->set('transferItems', [[
                'product_id'    => $this->produto->id,
                'product_name'  => $this->produto->name,
                'product_code'  => $this->produto->code,
                'quantity'      => 4,
                'unit_cost'     => 700,
                'ultima_valida' => 4,
            ]])
            ->set('transferItems.0.quantity', -2)
            ->assertSet('transferItems.0.quantity', 4.0);
    }

    public function test_corrigir_acima_do_disponivel_limita(): void
    {
        $this->comStock(6);

        Livewire::test(InterCompanyTransfer::class)
            ->set('warehouseFromId', $this->armazem->id)
            ->set('transferItems', [[
                'product_id'    => $this->produto->id,
                'product_name'  => $this->produto->name,
                'product_code'  => $this->produto->code,
                'quantity'      => 4,
                'unit_cost'     => 700,
                'ultima_valida' => 4,
            ]])
            ->set('transferItems.0.quantity', 99)
            ->assertSet('transferItems.0.quantity', 6.0);
    }

    public function test_a_grelha_nao_carrega_o_catalogo_todo(): void
    {
        // Carregava TODOS os artigos com stock e, com `with('stocks')`, todas
        // as linhas de stock de cada um, de todos os armazéns.
        for ($i = 0; $i < 60; $i++) {
            $p = Product::create([
                'tenant_id'      => $this->tenant->id,
                'name'           => sprintf('ARTIGO %03d', $i),
                'sku'            => "ART-{$i}",
                'code'           => "ART-{$i}",
                'price'          => 100,
                'type'           => 'produto',
                'manage_stock'   => true,
                'stock_quantity' => 0,
            ]);

            Stock::create([
                'tenant_id'    => $this->tenant->id,
                'warehouse_id' => $this->armazem->id,
                'product_id'   => $p->id,
                'quantity'     => 10,
            ]);
        }

        $artigos = Livewire::test(InterCompanyTransfer::class)
            ->set('showTransferModal', true)
            ->set('warehouseFromId', $this->armazem->id)
            ->viewData('products');

        $this->assertLessThanOrEqual(50, $artigos->count());
        $this->assertSame(10.0, (float) $artigos->first()->disponivel_na_origem, 'o disponível vem na mesma consulta');
    }

    public function test_sem_modal_aberto_nao_se_carrega_catalogo_nenhum(): void
    {
        $this->comStock(20);

        $this->assertCount(
            0,
            Livewire::test(InterCompanyTransfer::class)->viewData('products')
        );
    }

    public function test_uma_empresa_a_que_o_utilizador_nao_tem_acesso_e_recusada(): void
    {
        // A defesa que já existia e que não pode partir-se: um id arbitrário
        // permitia escrever stock e artigos em qualquer empresa da plataforma.
        $alheia = Tenant::create([
            'name'      => 'Empresa Alheia',
            'slug'      => 'alheia-' . uniqid(),
            'nif'       => (string) random_int(700000000, 799999999),
            'email'     => 'alheia' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        $this->comStock(20);

        Livewire::test(InterCompanyTransfer::class)
            ->set('warehouseFromId', $this->armazem->id)
            ->set('tenantToId', $alheia->id)
            ->set('warehouseToId', $this->armazemB->id)
            ->set('notes', 'tentativa')
            ->set('transferItems', [[
                'product_id'    => $this->produto->id,
                'product_name'  => $this->produto->name,
                'product_code'  => $this->produto->code,
                'quantity'      => 4,
                'unit_cost'     => 700,
                'ultima_valida' => 4,
            ]])
            ->call('saveTransfer');

        $this->assertSame(
            0,
            StockMovement::withoutGlobalScopes()->where('tenant_id', $alheia->id)->count(),
            'nada escrito numa empresa a que o utilizador não tem acesso'
        );

        $this->assertSame(
            20.0,
            (float) DB::table('invoicing_stocks')
                ->where('tenant_id', $this->tenant->id)
                ->where('product_id', $this->produto->id)
                ->sum('quantity'),
            'o stock da origem não mexe'
        );
    }
}
