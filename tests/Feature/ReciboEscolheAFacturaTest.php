<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Receipts\ReceiptCreate;
use App\Models\Invoicing\SalesInvoice;
use Livewire\Livewire;
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
 * facturação e do portal do cliente.
 */
class ReciboEscolheAFacturaTest extends TenantTestCase
{
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

    private function lista(array $parametros = []): \Illuminate\Support\Collection
    {
        return collect(
            Livewire::test(ReceiptCreate::class, $parametros)
                ->set('type', 'sale')
                ->set('client_id', $this->facturado->id)
                ->viewData('invoices')
        );
    }

    /**
     * UMA FACTURA EMITIDA E POR PAGAR TEM DE ESTAR NA LISTA.
     *
     * @test
     */
    public function o_recibo_ve_as_facturas_com_saldo_seja_qual_for_o_nome_do_estado(): void
    {
        $enviada = $this->factura('sent', 100000);
        $atrasada = $this->factura('overdue', 50000);
        $parcial  = $this->factura('partially_paid', 80000, 30000);

        // Estas não têm nada a receber.
        $paga     = $this->factura('paid', 20000, 20000);
        $anulada  = $this->factura('cancelled', 90000);
        $rascunho = $this->factura('draft', 70000);

        foreach ([$enviada, $atrasada, $parcial, $paga, $anulada, $rascunho] as $f) {
            $f->update(['client_id' => $this->facturado->id]);
        }

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
     * O botão de receber leva `?invoice=`, e o ecrã abre com o cliente, a
     * factura e o valor em falta — que é o que a caixa recebe quase sempre.
     *
     * @test
     */
    public function a_factura_do_endereco_vem_escolhida_e_com_o_valor_em_falta(): void
    {
        $f = $this->factura('sent', 120000, 20000);
        $f->update(['client_id' => $this->facturado->id]);

        $ecra = Livewire::test(ReceiptCreate::class, ['invoice' => $f->id]);

        $this->assertSame('sale', $ecra->get('type'));
        $this->assertSame($this->facturado->id, $ecra->get('client_id'));
        $this->assertSame($f->id, $ecra->get('invoice_id'));

        $this->assertEqualsWithDelta(100000, (float) $ecra->get('amount_paid'), 0.01,
            'o valor proposto é o que falta receber, não o total da factura');
    }

    /**
     * A FACTURA DO ENDEREÇO ENTRA SEMPRE NA LISTA.
     *
     * Sem a opção no `<select>`, o `wire:model.live` devolvia vazio e o recibo
     * ficava sem factura nenhuma — foi assim que as notas de crédito nasceram
     * sem referência ao documento que corrigem.
     *
     * @test
     */
    public function a_factura_do_endereco_aparece_no_selector(): void
    {
        $f = $this->factura('sent', 60000);
        $f->update(['client_id' => $this->facturado->id]);

        $this->assertTrue(
            $this->lista(['invoice' => $f->id])->pluck('id')->contains($f->id),
            'a factura que veio no endereço tem de estar entre as opções'
        );
    }

    /** O ecrã do recibo continua a abrir sem factura nenhuma. @test */
    public function o_ecra_abre_sem_factura(): void
    {
        Livewire::test(ReceiptCreate::class)->assertOk();
    }
}
