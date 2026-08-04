<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Purchases\Invoices;
use App\Models\Invoicing\PurchaseInvoice;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Uma factura de compra não se apaga — anula-se.
 *
 * O documento é do FORNECEDOR: deu entrada de stock, criou dívida a pagar e vai
 * para o SAFT-AO. Apagar a linha não desfaz nada disso; desfaz só a prova de
 * que aconteceu, e deixa o stock e as contas a apontar para um documento que já
 * não existe.
 */
class PurchaseInvoiceImutavelTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.purchases.invoices.view', 'invoicing.purchases.invoices.delete')
             ->comModulo('invoicing');
    }

    private function factura(string $estado = 'draft'): PurchaseInvoice
    {
        $fornecedor = \App\Models\Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Fornecedor ' . uniqid(),
            'nif'       => (string) random_int(500000000, 599999999),
            'is_active' => true,
        ]);

        return PurchaseInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'supplier_id'    => $fornecedor->id,
            'invoice_number' => 'FC-' . strtoupper(substr(uniqid(), -8)),
            'invoice_date'   => now()->toDateString(),
            'status'         => $estado,
            'subtotal'       => 1000,
            'total'          => 1140,
        ]);
    }

    public function test_um_rascunho_nao_pode_ser_apagado(): void
    {
        // Mesmo um rascunho: se foi registado, ficou. O que se faz é anular.
        $f = $this->factura('draft');

        Livewire::test(Invoices::class)->call('confirmDelete', $f->id);

        $this->assertDatabaseHas('invoicing_purchase_invoices', ['id' => $f->id]);
    }

    public function test_uma_factura_recebida_nao_pode_ser_apagada(): void
    {
        $f = $this->factura('pending');

        Livewire::test(Invoices::class)->call('deleteInvoice');

        $this->assertDatabaseHas('invoicing_purchase_invoices', ['id' => $f->id]);
    }

    public function test_o_pedido_de_eliminacao_explica_o_caminho_certo(): void
    {
        $f = $this->factura('draft');

        Livewire::test(Invoices::class)
            ->call('confirmDelete', $f->id)
            ->assertDispatched('notify', function (string $evento, array $dados) {
                $carga = $dados[0] ?? $dados;

                return ($carga['type'] ?? null) === 'error'
                    && str_contains($carga['message'] ?? '', 'Anular');
            });
    }

    public function test_o_modal_de_eliminacao_nao_chega_a_abrir(): void
    {
        $f = $this->factura('draft');

        Livewire::test(Invoices::class)
            ->call('confirmDelete', $f->id)
            ->assertSet('showDeleteModal', false);
    }

    public function test_anular_continua_a_funcionar_e_e_o_caminho(): void
    {
        $f = $this->factura('pending');

        Livewire::test(Invoices::class)->call('cancelInvoice', $f->id);

        $this->assertSame('cancelled', $f->fresh()->status);
        $this->assertDatabaseHas('invoicing_purchase_invoices', [
            'id'     => $f->id,
            'status' => 'cancelled',
        ]);
    }
}
