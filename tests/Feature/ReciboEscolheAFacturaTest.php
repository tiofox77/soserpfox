<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use Tests\TenantTestCase;

/**
 * Passar recibo a uma factura que ainda não foi paga.
 *
 * O selector do recibo mostrava só `pending` e `partially_paid`. Uma factura
 * EMITIDA e por pagar tem estado `sent`, e uma atrasada `overdue`: nenhuma
 * aparecia, e portanto não havia como lhe passar recibo. Medido na base de
 * desenvolvimento: 42 facturas na lista contra **317 com saldo por cobrar**,
 * num total de 15,4 milhões.
 *
 * A regra certa não é o nome do estado — é o SALDO, a mesma do painel da
 * facturação e do portal do cliente. O ecrã é hoje React e pede a lista a
 * `GET /api/v1/invoicing/react/recibos/facturas`; a regra é a mesma e é aí
 * que se prova.
 */
class ReciboEscolheAFacturaTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/recibos';

    // O nome `$cliente` já existe no TenantTestCase; aqui é o cliente das
    // facturas deste ensaio, e tem de ser o MESMO em todas elas.
    private \App\Models\Client $facturado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        // UM cliente, e sempre o mesmo: `clienteEmpresa()` cria um novo a
        // cada chamada, e as facturas ficavam espalhadas por clientes
        // diferentes daquele que o ecrã tinha escolhido.
        $this->facturado = $this->clienteEmpresa();
    }

    private function factura(string $estado, float $total, float $pago = 0): SalesInvoice
    {
        $f = new SalesInvoice([
            'client_id'      => $this->facturado->id,
            'invoice_number' => 'FT ENSAIO/' . random_int(100000, 999999),
            'invoice_date'   => now(),
            'invoice_type'   => 'FT',
            'status'         => $estado,
            'subtotal'       => $total,
            'total'          => $total,
            'paid_amount'    => $pago,
            'created_by'     => $this->user->id,
        ]);

        $f->tenant_id = $this->tenant->id;
        $f->save();

        return $f;
    }

    /** A lista que o ecrã pede, já filtrada pelo cliente escolhido. */
    private function lista(): \Illuminate\Support\Collection
    {
        return collect(
            $this->getJson(self::RAIZ . '/facturas?tipo=sale&parte_id=' . $this->facturado->id)
                ->assertOk()
                ->json('data')
        );
    }

    /**
     * UMA FACTURA EMITIDA E POR PAGAR TEM DE ESTAR NA LISTA.
     *
     * @test
     */
    public function o_recibo_ve_as_facturas_com_saldo_seja_qual_for_o_nome_do_estado(): void
    {
        $this->comPermissoes('invoicing.receipts.view');

        $enviada = $this->factura('sent', 100000);
        $atrasada = $this->factura('overdue', 50000);
        $parcial  = $this->factura('partially_paid', 80000, 30000);

        // Estas não têm nada a receber.
        $paga     = $this->factura('paid', 20000, 20000);
        $anulada  = $this->factura('cancelled', 90000);
        $rascunho = $this->factura('draft', 70000);

        $ids = $this->lista()->pluck('id');

        $this->assertTrue($ids->contains($enviada->id), 'uma factura enviada e por pagar recebe-se');
        $this->assertTrue($ids->contains($atrasada->id), 'uma factura vencida também');
        $this->assertTrue($ids->contains($parcial->id), 'e uma parcialmente paga, pelo que falta');

        $this->assertFalse($ids->contains($paga->id), 'uma factura paga não tem nada a receber');
        $this->assertFalse($ids->contains($anulada->id), 'uma anulada não se recebe');
        $this->assertFalse($ids->contains($rascunho->id), 'um rascunho ainda não foi emitido');
    }

    /**
     * VINDO DA LISTA DE FACTURAS, FICA TUDO ESCOLHIDO.
     *
     * O botão de receber leva `?invoice=`, e o ecrã abre com o cliente e a
     * factura escolhidos. Quem resolve é o SERVIDOR (`FacturaNaMorada`), e de
     * propósito: o React não sabe de que cliente é a factura, e a lista de
     * facturas do ecrã pede-se por cliente.
     *
     * @test
     */
    public function a_factura_do_endereco_vem_escolhida_com_o_cliente_dela(): void
    {
        $this->comPermissoes('invoicing.receipts.view', 'invoicing.receipts.create');

        $f = $this->factura('sent', 120000, 20000);

        $this->get('/invoicing/receipts/create?invoice=' . $f->id)
            ->assertOk()
            // O `data-props` vai como JSON escapado pelo Blade.
            ->assertSee('facturaId&quot;:' . $f->id, false)
            ->assertSee('clienteId&quot;:' . $this->facturado->id, false);
    }

    /**
     * E O VALOR PROPOSTO É O QUE FALTA, NÃO O TOTAL.
     *
     * É o que a caixa recebe quase sempre. A conta é do servidor: o ecrã lê o
     * `falta` da linha da factura e propõe-no.
     *
     * @test
     */
    public function a_factura_do_endereco_aparece_na_lista_com_o_valor_em_falta(): void
    {
        $this->comPermissoes('invoicing.receipts.view');

        $f = $this->factura('sent', 120000, 20000);

        $linha = $this->lista()->firstWhere('id', $f->id);

        $this->assertNotNull($linha,
            'a factura que veio no endereço tem de estar entre as opções — sem ela o recibo saía sem factura nenhuma');

        $this->assertEqualsWithDelta(100000, $linha['falta'], 0.01,
            'o valor proposto é o que falta receber, não o total da factura');
    }

    /** O ecrã do recibo continua a abrir sem factura nenhuma. @test */
    public function o_ecra_abre_sem_factura(): void
    {
        $this->comPermissoes('invoicing.receipts.view', 'invoicing.receipts.create');

        $this->get('/invoicing/receipts/create')
            ->assertOk()
            ->assertSee('data-ecra="facturacao/registar-recibo"', false);
    }
}
