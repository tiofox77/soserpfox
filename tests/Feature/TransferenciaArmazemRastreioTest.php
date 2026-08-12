<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\WarehouseTransfer;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Rastreio das transferências entre armazéns.
 *
 * O ecrã movia stock e deixava um rasto que não servia para nada: a referência
 * do lote era um `crc32(uniqid())` — um inteiro opaco que ninguém diz ao
 * telefone nem escreve num papel —, não havia documento nenhum para imprimir, e
 * os saldos ficavam por gravar de um dos lados.
 *
 * O pior era esse último: `from_warehouse_id` e `to_warehouse_id` nunca eram
 * preenchidos, e sem eles o carimbo de saldos não distingue as duas pernas da
 * transferência. Derivava o saldo anterior do DESTINO somando em vez de
 * subtrair — e escrevia no histórico um "antes" que nunca existiu.
 */
class TransferenciaArmazemRastreioTest extends TenantTestCase
{
    protected Warehouse $destino;
    protected Product $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->destino = Warehouse::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Armazém Secundário',
            'code'       => 'SEC',
            'is_active'  => true,
            'is_default' => false,
        ]);

        $this->produto = Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'AMIDOL Paracetamol 500mg',
            'sku'            => 'AMI-500',
            'code'           => 'AMI-500',
            'price'          => 300,
            'cost'           => 150,
            'type'           => 'produto',
            'manage_stock'   => true,
            'stock_quantity' => 0,
        ]);

        $this->comPermissoes('invoicing.warehouse-transfer.create', 'invoicing.stock.edit')
             ->comModulo('invoicing');
    }

    private function comStock(Warehouse $armazem, float $q): Stock
    {
        return Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $armazem->id,
            'product_id'   => $this->produto->id,
            'quantity'     => $q,
        ]);
    }

    /** Transfere $q unidades do armazém principal para o secundário. */
    private function transferir(float $q, string $nota = 'Reposição de loja')
    {
        return Livewire::test(WarehouseTransfer::class)
            ->set('transferFromWarehouse', $this->armazem->id)
            ->set('transferToWarehouse', $this->destino->id)
            ->set('transferNotes', $nota)
            ->set('transferItems', [[
                'product_id'   => $this->produto->id,
                'product_name' => $this->produto->name,
                'product_code' => $this->produto->code,
                'quantity'     => $q,
            ]])
            ->call('saveTransfer');
    }

    public function test_a_transferencia_recebe_uma_referencia_legivel(): void
    {
        $this->comStock($this->armazem, 20);

        $this->transferir(5)->assertSet('batchReference', fn ($ref) => $ref !== null);

        $referencia = StockMovement::where('product_id', $this->produto->id)->value('batch_reference');

        $this->assertMatchesRegularExpression(
            '#^MOV/\d{4}/\d{6}$#',
            (string) $referencia,
            'a referência do lote tem de ser MOV/AAAA/NNNNNN, como no ecrã de movimentação de stock'
        );
    }

    public function test_as_duas_pernas_partilham_a_mesma_referencia(): void
    {
        $this->comStock($this->armazem, 20);

        $this->transferir(5);

        $referencias = StockMovement::where('product_id', $this->produto->id)
            ->pluck('batch_reference')
            ->unique();

        $this->assertCount(2, StockMovement::where('product_id', $this->produto->id)->get(), 'saída e entrada');
        $this->assertCount(1, $referencias, 'as duas pernas são o mesmo documento');
    }

    public function test_os_quatro_saldos_ficam_gravados(): void
    {
        // 20 na origem, 3 no destino, transferem-se 5.
        $this->comStock($this->armazem, 20);
        $this->comStock($this->destino, 3);

        $this->transferir(5);

        $saida = StockMovement::where('warehouse_id', $this->armazem->id)
            ->where('product_id', $this->produto->id)->first();

        $entrada = StockMovement::where('warehouse_id', $this->destino->id)
            ->where('product_id', $this->produto->id)->first();

        $this->assertSame(20.0, (float) $saida->balance_before, 'origem antes');
        $this->assertSame(15.0, (float) $saida->balance_after, 'origem depois');

        $this->assertSame(3.0, (float) $entrada->balance_before, 'destino antes');
        $this->assertSame(8.0, (float) $entrada->balance_after, 'destino depois');
    }

    public function test_o_saldo_anterior_do_destino_nao_e_inflacionado(): void
    {
        // A regressão exacta: sem from/to_warehouse_id, carimbarSaldos() não
        // sabia qual das pernas estava a olhar e SOMAVA a quantidade ao saldo
        // do destino em vez de a subtrair. Com 3 no destino e 5 transferidos
        // gravava "antes = 13" — um saldo que nunca existiu — em vez de 3.
        $this->comStock($this->armazem, 20);
        $this->comStock($this->destino, 3);

        $this->transferir(5);

        $entrada = StockMovement::where('warehouse_id', $this->destino->id)
            ->where('product_id', $this->produto->id)->first();

        $this->assertNotSame(13.0, (float) $entrada->balance_before, 'o saldo somado em vez de subtraído');
        $this->assertSame(
            (float) $entrada->balance_after - 5.0,
            (float) $entrada->balance_before,
            'antes + transferido = depois'
        );
    }

    public function test_as_pernas_dizem_de_onde_para_onde(): void
    {
        $this->comStock($this->armazem, 20);

        $this->transferir(5);

        foreach (StockMovement::where('product_id', $this->produto->id)->get() as $m) {
            $this->assertSame($this->armazem->id, (int) $m->from_warehouse_id);
            $this->assertSame($this->destino->id, (int) $m->to_warehouse_id);
        }
    }

    public function test_fica_registado_quem_transferiu(): void
    {
        $this->comStock($this->armazem, 20);

        $this->transferir(5);

        foreach (StockMovement::where('product_id', $this->produto->id)->get() as $m) {
            $this->assertSame($this->user->id, (int) $m->user_id);
        }
    }

    public function test_o_stock_dos_dois_armazens_bate_com_os_saldos_gravados(): void
    {
        $this->comStock($this->armazem, 20);
        $this->comStock($this->destino, 3);

        $this->transferir(5);

        $origem  = Stock::where('warehouse_id', $this->armazem->id)->where('product_id', $this->produto->id)->first();
        $destino = Stock::where('warehouse_id', $this->destino->id)->where('product_id', $this->produto->id)->first();

        $this->assertSame(15.0, (float) $origem->quantity);
        $this->assertSame(8.0, (float) $destino->quantity);
    }

    public function test_o_documento_do_lote_abre(): void
    {
        $this->comStock($this->armazem, 20);
        $this->comStock($this->destino, 3);

        $this->transferir(5);

        $referencia = StockMovement::where('product_id', $this->produto->id)->value('batch_reference');

        $resposta = $this->get(route('invoicing.stock.batch-preview', ['reference' => $referencia]));

        $resposta->assertOk();
        $resposta->assertSee('Transferência de Stock');
        $resposta->assertSee($referencia);
        $resposta->assertSee('Armazém Secundário');
        // Os saldos dos dois lados no papel: 20 → 15 na origem, 3 → 8 no destino.
        $resposta->assertSee('Armazém de origem');
        $resposta->assertSee('Armazém de destino');
    }

    public function test_uma_transferencia_nao_usa_o_documento_de_entradas_e_saidas(): void
    {
        // O documento de entradas/saídas tem UM armazém e uma linha por
        // movimento. Numa transferência isso daria o mesmo artigo duas vezes,
        // com sinais opostos e sem dizer de onde para onde foi.
        $this->comStock($this->armazem, 20);

        $this->transferir(5);

        $referencia = StockMovement::where('product_id', $this->produto->id)->value('batch_reference');

        $this->get(route('invoicing.stock.batch-preview', ['reference' => $referencia]))
            ->assertDontSee('Movimentação de Stock');
    }

    public function test_o_ajuste_grava_o_saldo_anterior(): void
    {
        // Num ajuste a quantidade não diz de quanto para quanto: sem o saldo
        // anterior, "+6" no histórico é ilegível.
        $this->comStock($this->armazem, 12);

        Livewire::test(WarehouseTransfer::class)
            ->set('adjustWarehouse', $this->armazem->id)
            ->set('adjustType', 'in')
            ->set('adjustReason', 'Contagem física')
            ->set('adjustItems', [[
                'product_id'   => $this->produto->id,
                'product_name' => $this->produto->name,
                'product_code' => $this->produto->code,
                'quantity'     => 6,
            ]])
            ->call('saveAdjust');

        $m = StockMovement::where('product_id', $this->produto->id)->first();

        $this->assertSame(12.0, (float) $m->balance_before);
        $this->assertSame(18.0, (float) $m->balance_after);
        $this->assertMatchesRegularExpression('#^MOV/\d{4}/\d{6}$#', (string) $m->batch_reference);
    }

    public function test_o_documento_do_ajuste_soma_as_linhas(): void
    {
        // As linhas de ajuste gravam a quantidade COM SINAL e ficam todas em
        // `type = adjustment`. O documento classificava por `type`, e por isso
        // elas apareciam na tabela sem entrar em nenhum dos totais.
        $this->comStock($this->armazem, 12);

        Livewire::test(WarehouseTransfer::class)
            ->set('adjustWarehouse', $this->armazem->id)
            ->set('adjustType', 'out')
            ->set('adjustReason', 'Quebra')
            ->set('adjustItems', [[
                'product_id'   => $this->produto->id,
                'product_name' => $this->produto->name,
                'product_code' => $this->produto->code,
                'quantity'     => 4,
            ]])
            ->call('saveAdjust');

        $referencia = StockMovement::where('product_id', $this->produto->id)->value('batch_reference');

        $this->get(route('invoicing.stock.batch-preview', ['reference' => $referencia]))
            ->assertOk()
            ->assertSee('Total saídas (1)');
    }

    public function test_o_detalhe_traz_as_duas_pernas_com_os_saldos(): void
    {
        $this->comStock($this->armazem, 20);
        $this->comStock($this->destino, 3);

        $this->transferir(5);

        $referencia = StockMovement::where('product_id', $this->produto->id)->value('batch_reference');

        $detalhe = Livewire::test(WarehouseTransfer::class)
            ->set('selectedBatchRef', $referencia)
            ->call('openDetailsModal')
            ->get('selectedBatchDetails');

        $this->assertCount(2, $detalhe, 'saída e entrada');

        $saldos = array_map(fn ($d) => [(float) $d['balance_before'], (float) $d['balance_after']], $detalhe);

        $this->assertContains([20.0, 15.0], $saldos, 'a perna da origem');
        $this->assertContains([3.0, 8.0], $saldos, 'a perna do destino');
    }

    public function test_o_detalhe_de_um_lote_nao_apanha_o_de_outro(): void
    {
        // O `reference_id` é um crc32: dois lotes diferentes da mesma empresa
        // podem cair no mesmo inteiro e o detalhe misturaria as duas
        // movimentações. Procurar pela referência MOV/ fecha essa porta.
        $this->comStock($this->armazem, 40);

        $this->transferir(5);
        $primeira = StockMovement::where('product_id', $this->produto->id)->value('batch_reference');

        $this->transferir(7);

        $detalhe = Livewire::test(WarehouseTransfer::class)
            ->set('selectedBatchRef', $primeira)
            ->call('openDetailsModal')
            ->get('selectedBatchDetails');

        $this->assertCount(2, $detalhe);

        foreach ($detalhe as $linha) {
            $this->assertSame($primeira, $linha['batch_reference']);
            $this->assertSame(5.0, abs((float) $linha['quantity']), 'só as linhas do primeiro lote');
        }
    }

    /** Cria $n artigos com stock no armazém principal. */
    private function catalogo(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $p = Product::create([
                'tenant_id'      => $this->tenant->id,
                'name'           => sprintf('ARTIGO %03d', $i),
                'sku'            => "ART-{$i}",
                'code'           => "ART-{$i}",
                'barcode'        => '789' . str_pad((string) $i, 10, '0', STR_PAD_LEFT),
                'price'          => 100,
                'cost'           => 50,
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
    }

    public function test_a_grelha_de_artigos_nao_custa_uma_consulta_por_artigo(): void
    {
        // A razão de o ecrã ser inutilizável numa farmácia: carregava o
        // catálogo INTEIRO em cada render e a vista fazia mais uma consulta de
        // stock POR ARTIGO. Com 5.729 artigos eram perto de 5.730 consultas a
        // cada tecla premida no campo de pesquisa.
        $this->catalogo(60);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();

        Livewire::test(WarehouseTransfer::class)
            ->set('showTransferModal', true)
            ->set('transferFromWarehouse', $this->armazem->id)
            ->set('productSearch', 'ARTIGO 01');

        $consultas = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertLessThan(
            40,
            $consultas,
            "60 artigos deram {$consultas} consultas — o stock voltou a ser lido artigo a artigo"
        );
    }

    public function test_a_grelha_vem_com_o_stock_do_armazem_na_mesma_consulta(): void
    {
        $this->catalogo(3);

        $artigos = Livewire::test(WarehouseTransfer::class)
            ->set('showTransferModal', true)
            ->set('transferFromWarehouse', $this->armazem->id)
            ->viewData('products');

        $this->assertNotEmpty($artigos);

        foreach ($artigos as $a) {
            $this->assertSame(10.0, (float) $a->stock_no_armazem, 'o stock tem de vir na consulta dos artigos');
        }
    }

    public function test_a_grelha_e_limitada(): void
    {
        // Sem limite eram 5.729 cartões no DOM.
        $this->catalogo(60);

        $artigos = Livewire::test(WarehouseTransfer::class)
            ->set('showTransferModal', true)
            ->set('transferFromWarehouse', $this->armazem->id)
            ->viewData('products');

        $this->assertLessThanOrEqual(50, $artigos->count());
    }

    public function test_sem_modal_aberto_nao_se_carrega_catalogo_nenhum(): void
    {
        // A listagem do histórico não precisa de artigo nenhum, e carregava o
        // catálogo à mesma só para o deitar fora.
        $this->catalogo(10);

        $artigos = Livewire::test(WarehouseTransfer::class)->viewData('products');

        $this->assertCount(0, $artigos);
    }

    public function test_a_pesquisa_encontra_pelo_codigo_de_barras(): void
    {
        // Numa farmácia lê-se o artigo pelo leitor; a pesquisa antiga só olhava
        // para o nome e o código.
        $this->catalogo(3);

        $artigos = Livewire::test(WarehouseTransfer::class)
            ->set('showTransferModal', true)
            ->set('transferFromWarehouse', $this->armazem->id)
            ->set('productSearch', '7890000000001')
            ->viewData('products');

        $this->assertCount(1, $artigos);
        $this->assertSame('ARTIGO 001', $artigos->first()->name);
    }

    public function test_sem_pesquisa_a_transferencia_so_mostra_o_que_ha_na_origem(): void
    {
        // Não se transfere o que não existe na origem. Sem isto, os cinquenta
        // primeiros por ordem alfabética eram quase todos artigos a zero.
        $this->catalogo(2);

        // Um artigo do catálogo sem stock nenhum neste armazém.
        Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'AAA SEM STOCK',
            'sku'            => 'SEM-1',
            'code'           => 'SEM-1',
            'price'          => 100,
            'type'           => 'produto',
            'manage_stock'   => true,
            'stock_quantity' => 0,
        ]);

        $artigos = Livewire::test(WarehouseTransfer::class)
            ->set('showTransferModal', true)
            ->set('transferFromWarehouse', $this->armazem->id)
            ->viewData('products');

        $nomes = $artigos->pluck('name')->all();

        $this->assertNotContains('AAA SEM STOCK', $nomes, 'não se transfere o que não existe na origem');
        $this->assertContains('ARTIGO 000', $nomes);
    }

    public function test_com_pesquisa_encontra_mesmo_o_que_esta_a_zero(): void
    {
        // Quem procura um artigo pelo nome tem de o encontrar, nem que seja
        // para ver que está a zero. Escondê-lo faria parecer que não existe.
        $this->catalogo(1);

        Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'AAA SEM STOCK',
            'sku'            => 'SEM-1',
            'code'           => 'SEM-1',
            'price'          => 100,
            'type'           => 'produto',
            'manage_stock'   => true,
            'stock_quantity' => 0,
        ]);

        $artigos = Livewire::test(WarehouseTransfer::class)
            ->set('showTransferModal', true)
            ->set('transferFromWarehouse', $this->armazem->id)
            ->set('productSearch', 'SEM STOCK')
            ->viewData('products');

        $this->assertCount(1, $artigos);
        $this->assertSame('AAA SEM STOCK', $artigos->first()->name);
        $this->assertNull($artigos->first()->stock_no_armazem, 'sem linha de stock neste armazém');
    }

    /** O ecrã com um artigo já no carrinho, pronto a ter a quantidade corrigida. */
    private function comCarrinho(float $q = 5)
    {
        return Livewire::test(WarehouseTransfer::class)
            ->set('showTransferModal', true)
            ->set('transferFromWarehouse', $this->armazem->id)
            ->set('transferToWarehouse', $this->destino->id)
            ->set('transferItems', [[
                'product_id'    => $this->produto->id,
                'product_name'  => $this->produto->name,
                'product_code'  => $this->produto->code,
                'quantity'      => $q,
                'ultima_valida' => $q,
            ]]);
    }

    public function test_corrigir_a_quantidade_no_carrinho(): void
    {
        // O erro mais banal deste ecrã, e a única saída era apagar a linha e
        // voltar a procurar o artigo no meio de cinco mil.
        $this->comStock($this->armazem, 20);

        $this->comCarrinho(5)
            ->set('transferItems.0.quantity', 8)
            ->assertSet('transferItems.0.quantity', 8.0);
    }

    public function test_a_correcao_e_o_que_fica_gravado(): void
    {
        $this->comStock($this->armazem, 20);

        $this->comCarrinho(5)
            ->set('transferItems.0.quantity', 8)
            ->call('saveTransfer');

        $saida = StockMovement::where('warehouse_id', $this->armazem->id)
            ->where('product_id', $this->produto->id)->first();

        $this->assertSame(-8.0, (float) $saida->quantity);
        $this->assertSame(12.0, (float) $saida->balance_after, '20 − 8');
    }

    public function test_corrigir_acima_do_stock_limita_ao_disponivel(): void
    {
        // Quem escreve 50 quando há 9 quer transferir o que houver: dizer-lhe
        // quanto é mais útil do que recusar. E sem este tecto o erro só
        // aparecia lá ao fundo, no gravar, com o modal já fechado.
        $this->comStock($this->armazem, 9);

        $this->comCarrinho(5)
            ->set('transferItems.0.quantity', 50)
            ->assertSet('transferItems.0.quantity', 9.0);
    }

    public function test_uma_quantidade_negativa_nao_passa_pela_correcao(): void
    {
        // Isto é o que mais me preocupa nesta alteração: a adição já recusava
        // negativos porque um negativo INVERTE a transferência — aumenta a
        // origem e diminui o destino. A edição não pode ser a porta por onde
        // ele entra.
        $this->comStock($this->armazem, 20);

        $this->comCarrinho(5)
            ->set('transferItems.0.quantity', -3)
            ->assertSet('transferItems.0.quantity', 5.0, 'repõe o valor anterior');
    }

    public function test_apagar_o_campo_repoe_o_valor_anterior(): void
    {
        // Uma linha de carrinho sem quantidade é um documento por gravar à
        // espera de rebentar mais à frente.
        $this->comStock($this->armazem, 20);

        $this->comCarrinho(5)
            ->set('transferItems.0.quantity', '')
            ->assertSet('transferItems.0.quantity', 5.0);
    }

    public function test_zero_nao_passa(): void
    {
        $this->comStock($this->armazem, 20);

        $this->comCarrinho(5)
            ->set('transferItems.0.quantity', 0)
            ->assertSet('transferItems.0.quantity', 5.0);
    }

    public function test_corrigir_a_quantidade_de_um_ajuste_de_saida(): void
    {
        $this->comStock($this->armazem, 12);

        Livewire::test(WarehouseTransfer::class)
            ->set('showAdjustModal', true)
            ->set('adjustWarehouse', $this->armazem->id)
            ->set('adjustType', 'out')
            ->set('adjustItems', [[
                'product_id'    => $this->produto->id,
                'product_name'  => $this->produto->name,
                'product_code'  => $this->produto->code,
                'quantity'      => 4,
                'ultima_valida' => 4,
            ]])
            ->set('adjustItems.0.quantity', 30)
            ->assertSet('adjustItems.0.quantity', 12.0, 'não se tira mais do que há');
    }

    public function test_num_ajuste_de_entrada_nao_ha_tecto(): void
    {
        // Limitar uma ENTRADA ao stock actual impediria justamente a correcção
        // mais comum, que é dar entrada do que faltava.
        $this->comStock($this->armazem, 2);

        Livewire::test(WarehouseTransfer::class)
            ->set('showAdjustModal', true)
            ->set('adjustWarehouse', $this->armazem->id)
            ->set('adjustType', 'in')
            ->set('adjustItems', [[
                'product_id'    => $this->produto->id,
                'product_name'  => $this->produto->name,
                'product_code'  => $this->produto->code,
                'quantity'      => 4,
                'ultima_valida' => 4,
            ]])
            ->set('adjustItems.0.quantity', 500)
            ->assertSet('adjustItems.0.quantity', 500.0);
    }

    public function test_stock_insuficiente_nao_deixa_nada_gravado(): void
    {
        $this->comStock($this->armazem, 2);

        $this->transferir(5);

        $this->assertSame(
            0,
            StockMovement::where('product_id', $this->produto->id)->count(),
            'nem movimento, nem referência queimada'
        );

        $this->assertSame(
            2.0,
            (float) Stock::where('warehouse_id', $this->armazem->id)->where('product_id', $this->produto->id)->value('quantity'),
            'o stock da origem não mexe'
        );
    }
}
