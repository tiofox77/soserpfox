<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use Tests\TenantTestCase;

/**
 * Oficina: stock das peças e faturação da Ordem de Serviço.
 *
 * Cada teste corresponde a um defeito encontrado em auditoria — desde o
 * `markAsCompleted()` que nunca era chamado (o único controlo de estado na
 * interface é o formulário) até ao duplo desconto ao faturar.
 */
class WorkshopStockTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina/ordens';

    private function veiculo(): Vehicle
    {
        return Vehicle::create([
            'tenant_id'      => $this->tenant->id,
            'plate'          => 'T-' . strtoupper(substr(uniqid(), -7)),
            'vehicle_number' => 'V-' . strtoupper(substr(uniqid(), -7)),
            'owner_name'     => 'Dono de Teste',
            'brand'          => 'Toyota',
            'model'          => 'Corolla',
            'status'         => 'active',
        ]);
    }

    private function ordemComPeca(\App\Models\Product $peca, float $quantidade = 2): WorkOrder
    {
        $os = WorkOrder::createWithTenantNumber([
            'tenant_id'           => $this->tenant->id,
            'vehicle_id'          => $this->veiculo()->id,
            'received_at'         => now(),
            'problem_description' => 'Teste',
            'status'              => 'pending',
            'priority'            => 'normal',
        ], 'order_number', 'OS-');

        WorkOrderItem::create([
            'work_order_id' => $os->id,
            'type'          => 'part',
            'product_id'    => $peca->id,
            'name'          => $peca->name,
            'quantity'      => $quantidade,
            'unit_price'    => $peca->price,
        ]);

        return $os->fresh();
    }

    private function stockDe(\App\Models\Product $peca): float
    {
        return (float) Stock::where('tenant_id', $this->tenant->id)
            ->where('warehouse_id', $this->armazem->id)
            ->where('product_id', $peca->id)
            ->value('quantity');
    }

    /**
     * CONCLUIR PELO FORMULÁRIO DA FICHA DESCONTA AS PEÇAS.
     *
     * Havia dois caminhos para mudar o estado — o botão e o formulário — e o
     * segundo gravava a coluna directamente, saltando a baixa de stock. Hoje
     * são duas rotas da API que entram pelo mesmo `aplicarEstado()`, e é isso
     * que este ensaio guarda: o `PUT` da ficha desconta como o botão.
     */
    public function test_concluir_a_os_pelo_formulario_desconta_as_pecas(): void
    {
        $this->comModulo('oficina')->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $peca = $this->produtoComStock(10);
        $os   = $this->ordemComPeca($peca, 2);

        $this->putJson(self::API . "/{$os->id}", [
            'vehicle_id' => $os->vehicle_id,
            'received_at' => $os->received_at->toDateTimeString(),
            'problem_description' => $os->problem_description,
            'status' => 'completed',
            'priority' => 'normal',
        ])->assertOk();

        $this->assertEquals(8, $this->stockDe($peca), 'As peças têm de sair do stock ao concluir');
    }

    public function test_duas_linhas_do_mesmo_produto_descontam_as_duas(): void
    {
        $peca = $this->produtoComStock(10);
        $os   = $this->ordemComPeca($peca, 2);

        WorkOrderItem::create([
            'work_order_id' => $os->id, 'type' => 'part', 'product_id' => $peca->id,
            'name' => $peca->name, 'quantity' => 1, 'unit_price' => $peca->price,
        ]);

        $os->fresh()->markAsCompleted();

        $this->assertEquals(7, $this->stockDe($peca), '2 + 1 unidades, não só a primeira linha');
    }

    public function test_processar_duas_vezes_nao_desconta_a_dobrar(): void
    {
        $peca = $this->produtoComStock(10);
        $os   = $this->ordemComPeca($peca, 2);

        $os->markAsCompleted();
        $os->fresh()->processStockMovement();

        $this->assertEquals(8, $this->stockDe($peca));
    }

    public function test_faturar_a_os_nao_desconta_o_stock_outra_vez(): void
    {
        $peca = $this->produtoComStock(10);
        $os   = $this->ordemComPeca($peca, 2);

        $os->markAsCompleted();
        $factura = $os->fresh()->convertToInvoice();

        // Emitir a factura dispara o SalesInvoiceObserver.
        $factura->status = 'paid';
        $factura->save();

        $this->assertEquals(8, $this->stockDe($peca), 'A peça já saiu na conclusão da OS');
    }

    public function test_cancelar_a_os_devolve_as_pecas(): void
    {
        $this->comModulo('oficina')->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $peca = $this->produtoComStock(10);
        $os   = $this->ordemComPeca($peca, 2);

        $this->postJson(self::API . "/{$os->id}/estado", ['estado' => 'completed'])->assertOk();
        $this->assertEquals(8, $this->stockDe($peca));

        $this->postJson(self::API . "/{$os->id}/estado", ['estado' => 'cancelled'])->assertOk();
        $this->assertEquals(10, $this->stockDe($peca), 'Anular a OS devolve o que saiu');
    }

    public function test_falta_de_stock_nao_grava_movimento_orfao(): void
    {
        $peca = $this->produtoComStock(1);      // só 1 em stock
        $os   = $this->ordemComPeca($peca, 5);  // pedem-se 5

        $falhas = $os->markAsCompleted();

        $this->assertNotEmpty($falhas, 'A falha tem de chegar ao utilizador, não só ao log');

        // Movimento e baixa são atómicos: sem transacção ficava a linha de
        // movimento gravada, a OS era saltada para sempre e um cancelamento
        // posterior INFLACIONAVA o stock com peças que nunca saíram.
        $this->assertSame(0, StockMovement::where('reference_type', 'WorkOrder')
            ->where('reference_id', $os->id)->count());
    }

    /**
     * ESCOLHER A PEÇA TRAZ O PREÇO DELA — e a existência do armazém de saída.
     *
     * A coluna é `price`; o ecrã lia `selling_price`, que não existe. O campo
     * do preço ficava vazio e o `required` recusava a peça: escolher uma peça
     * nunca trazia o preço.
     */
    public function test_escolher_a_peca_carrega_o_preco(): void
    {
        $this->comModulo('oficina')->comPermissoes('workshop.work-orders.view');

        $peca = $this->produtoComStock(10, 8500);

        $linha = collect($this->getJson(self::API . '/artigos')->assertOk()->json('data'))
            ->firstWhere('valor', (string) $peca->id);

        $this->assertNotNull($linha, 'a peça tem de aparecer na procura');
        $this->assertEquals(8500, $linha['preco']);
        $this->assertEquals(10, $linha['stock']);
    }

    public function test_a_mesma_os_nao_e_faturada_duas_vezes(): void
    {
        $peca = $this->produtoComStock(10);
        $os   = $this->ordemComPeca($peca, 1);

        $os->convertToInvoice();

        $this->expectException(\Exception::class);
        $os->fresh()->convertToInvoice();
    }

    public function test_fatura_da_os_retem_irt_so_a_cliente_empresa(): void
    {
        $peca = $this->produtoComStock(10);

        // A retenção incide sobre a MÃO-DE-OBRA, por isso a OS tem de a ter.
        $comMaoDeObra = function (WorkOrder $os) {
            WorkOrderItem::create([
                'work_order_id' => $os->id,
                'type'          => 'service',
                'name'          => 'Mão-de-obra',
                'quantity'      => 1,
                'unit_price'    => 10000,
            ]);

            return $os->fresh();
        };

        // Particular: não retém.
        $os = $comMaoDeObra($this->ordemComPeca($peca, 1));
        $os->vehicle->update(['client_id' => $this->cliente->id]);
        $particular = $os->fresh()->convertToInvoice();
        $this->assertEquals(0.0, (float) $particular->irt_amount);

        // Empresa: retém 6,5% sobre a mão-de-obra (650 de 10.000).
        $os2 = $comMaoDeObra($this->ordemComPeca($peca, 1));
        $os2->vehicle->update(['client_id' => $this->clienteEmpresa()->id]);
        $empresa = $os2->fresh()->convertToInvoice();
        $this->assertEquals(650, round((float) $empresa->irt_amount, 2));
    }
}
