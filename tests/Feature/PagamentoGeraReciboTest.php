<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Receipts\ReceiptCreate;
use App\Models\Client;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O circuito do dinheiro: factura → recibo → factura a par.
 *
 * Havia dois caminhos para receber e cada um fazia a sua conta. O modal da
 * factura somava ao `paid_amount` e criava o recibo; o ecrã dos recibos criava
 * o recibo e NÃO tocava na factura — o gancho do modelo punha só o ESTADO e
 * nunca o valor. Medido na base de desenvolvimento: em 5 facturas com recibos,
 * 4 tinham o `paid_amount` fora do que os recibos diziam, e 4.560,00 Kz de
 * dinheiro recebido não estavam registados em factura nenhuma.
 *
 * Agora a conta vive num sítio só — `SalesInvoice::recalcularPago()` — e é
 * refeita sempre que um recibo nasce, muda ou é anulado.
 */
class PagamentoGeraReciboTest extends TenantTestCase
{
    private Client $facturado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        $this->facturado = $this->clienteEmpresa();

        // Sem série de RECIBOS não se emite recibo nenhum — e o TenantTestCase
        // só semeia as de factura e de POS.
        \App\Models\Invoicing\InvoicingSeries::create([
            'tenant_id'       => $this->tenant->id,
            'series_code'     => 'RC',
            'name'            => 'RC (teste)',
            'document_type'   => 'receipt',
            'agt_environment' => 'sandbox',
            'is_default'      => true,
            'is_active'       => true,
        ]);
    }

    private function factura(float $total, float $pago = 0, string $estado = 'sent'): SalesInvoice
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

    private function receber(SalesInvoice $f, float $valor): void
    {
        $ecra = Livewire::test(ReceiptCreate::class, ['invoice' => $f->id])
            ->set('amount_paid', $valor)
            ->call('save');

        $ecra->assertHasNoErrors();

        // O save() apanha as excepcoes e avisa por notify: sem isto, um erro
        // interno passava por "sem erros de validacao" e o ensaio falhava
        // mais a frente, longe da causa.
        $recibo = Receipt::where('invoice_id', $f->id)->latest('id')->first();

        if (! $recibo) {
            $avisos = collect($ecra->effects['dispatches'] ?? [])
                ->map(fn ($d) => json_encode($d['params'] ?? $d))->implode(' | ');

            $this->fail('o recibo não foi criado. Avisos: ' . $avisos);
        }
    }

    /**
     * RECEBER PELO ECRÃ DOS RECIBOS PÕE A FACTURA A PAR.
     *
     * Era o buraco: o recibo nascia e a factura continuava a pedir tudo.
     *
     * @test
     */
    public function um_recibo_desconta_na_factura(): void
    {
        $f = $this->factura(100000);

        $this->receber($f, 40000);

        $f->refresh();

        $this->assertEqualsWithDelta(40000, (float) $f->paid_amount, 0.01,
            'o dinheiro recebido tem de ficar registado na factura');

        $this->assertSame('partially_paid', $f->status);
    }

    /**
     * A SEGUNDA PRESTAÇÃO JÁ SABE QUANTO FALTA.
     *
     * É o que quem está na caixa precisa: abrir e ver o que falta, sem contas
     * de cabeça. O ecrã propõe-o e mostra os três números.
     *
     * @test
     */
    public function o_ecra_propoe_o_que_falta_receber(): void
    {
        $f = $this->factura(100000);

        $this->receber($f, 40000);

        $ecra = Livewire::test(ReceiptCreate::class, ['invoice' => $f->id]);

        $this->assertEqualsWithDelta(60000, (float) $ecra->get('amount_paid'), 0.01,
            'o valor proposto é o que falta, não o total');

        $saldo = $ecra->instance()->saldoDaFactura;

        $this->assertEqualsWithDelta(100000, $saldo['total'], 0.01);
        $this->assertEqualsWithDelta(40000, $saldo['pago'], 0.01);
        $this->assertEqualsWithDelta(60000, $saldo['falta'], 0.01);

        // E o resto fecha a factura.
        $this->receber($f, 60000);

        $f->refresh();

        $this->assertSame('paid', $f->status);
        $this->assertEqualsWithDelta(100000, (float) $f->paid_amount, 0.01);
    }

    /**
     * UM RECIBO ANULADO DEVOLVE A DÍVIDA.
     *
     * Só havia o gancho do `created`: um recibo corrigido ou anulado deixava a
     * factura com dinheiro que já não existe.
     *
     * @test
     */
    public function anular_o_recibo_volta_a_pôr_a_factura_a_dever(): void
    {
        $f = $this->factura(50000);

        $this->receber($f, 50000);

        $this->assertSame('paid', $f->fresh()->status);

        Receipt::where('invoice_id', $f->id)->first()->update(['status' => 'cancelled']);

        $f->refresh();

        $this->assertEqualsWithDelta(0, (float) $f->paid_amount, 0.01,
            'o dinheiro do recibo anulado deixa de contar');

        $this->assertSame('sent', $f->status,
            'volta a dever-se — e sem inventar um estado que a factura nunca teve');
    }

    /**
     * UMA FACTURA-RECIBO DO BALCÃO NÃO SE TOCA.
     *
     * Paga-se no acto e nunca tem recibo à parte: o documento É o recibo.
     * Recalcular «pago = soma dos recibos» punha 1220 facturas desta base a
     * zero e a dever 17,7 milhões que já estão na caixa.
     *
     * @test
     */
    public function uma_factura_paga_sem_recibo_nenhum_fica_como_esta(): void
    {
        $fr = $this->factura(30000, 30000, 'paid');

        // O gancho de um recibo qualquer noutra factura nao pode tocar nesta.
        $fr->acertarEstadoPeloPago();

        $fr->refresh();

        $this->assertEqualsWithDelta(30000, (float) $fr->paid_amount, 0.01,
            'sem recibos não há nada a recalcular — e o dinheiro do balcão fica onde está');

        $this->assertSame('paid', $fr->status);
    }

    /**
     * UM RECIBO NOVO NÃO APAGA O PAGAMENTO QUE JÁ LÁ ESTAVA.
     *
     * É o ensaio que guarda o que quase se perdeu. Há facturas antigas com o
     * pagamento registado por outro caminho — o balcão, o modal de antes de
     * haver recibos, uma importação — e um recibo pequeno por cima. Recalcular
     * «pago = soma dos recibos» apagava-lhes o pagamento verdadeiro: no ensaio
     * a seco em produção eram 6,8 milhões de Kz só numa empresa.
     *
     * Por isso se lança por DIFERENÇA e nunca se recalcula do zero.
     *
     * @test
     */
    public function um_recibo_novo_nao_apaga_o_pagamento_que_ja_estava_registado(): void
    {
        // Uma factura antiga: 500.000 recebidos sem recibo nenhum.
        $f = $this->factura(600000, 500000, 'partially_paid');

        // Agora emite-se um recibo pequeno, do resto.
        $this->receber($f, 100000);

        $f->refresh();

        $this->assertEqualsWithDelta(600000, (float) $f->paid_amount, 0.01,
            'o recibo SOMA-SE ao que já estava; não o substitui');

        $this->assertSame('paid', $f->status);
    }

    /** O acerto do que ficou para trás não toca no que está certo. @test */
    public function o_acerto_so_mexe_no_que_esta_fora(): void
    {
        $balcao = $this->factura(30000, 30000, 'paid');
        $torta  = $this->factura(20000);

        $this->receber($torta, 20000);

        // Simula o estado antigo: recibo emitido, factura por actualizar.
        SalesInvoice::where('id', $torta->id)->update(['paid_amount' => 0]);

        $this->artisan('facturas:acertar-pagos', ['--tenant' => $this->tenant->id, '--aplicar' => true])
            ->assertSuccessful();

        $this->assertEqualsWithDelta(20000, (float) $torta->fresh()->paid_amount, 0.01,
            'a que estava fora fica certa');

        $this->assertEqualsWithDelta(30000, (float) $balcao->fresh()->paid_amount, 0.01,
            'e a do balcão continua intacta');
    }
}
