<?php

namespace Tests\Feature\Invoicing;

use App\Livewire\Invoicing\PaymentModal;
use App\Livewire\Invoicing\Purchases\Invoices;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\Tenant;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O que aconteceu em produção: o utilizador trocou de empresa com a lista de
 * facturas de compra aberta. O ecrã continuou a mostrar a lista da empresa
 * ANTERIOR e ele carregou em "Registar Pagamento". O filtro por empresa fez o
 * seu trabalho e não encontrou o documento — mas o `findOrFail` transformou
 * isso em "No query results for model [App\Models\Invoicing\PurchaseInvoice]
 * 35" à cara do utilizador (e em 500 nas outras acções da lista).
 *
 * O bloqueio entre empresas continua exactamente igual. O que estes testes
 * fixam é a forma: sem excepção, sem 500, com aviso em português.
 */
class DocumentoDeOutraEmpresaTest extends TenantTestCase
{
    /** Uma factura de compra que pertence a OUTRA empresa. */
    private function facturaDeOutraEmpresa(): PurchaseInvoice
    {
        $outra = Tenant::create([
            'name'      => 'Empresa Vizinha',
            'slug'      => 'vizinha-' . uniqid(),
            'nif'       => (string) random_int(500000000, 599999999),
            'email'     => 'viz' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        // withoutEvents: o global scope de empresa é de LEITURA, mas o
        // `creating` do BelongsToTenant carimbaria aqui a empresa activa do
        // teste — e era justamente isso que se quer evitar.
        return PurchaseInvoice::withoutEvents(function () use ($outra) {
            $fornecedor = Supplier::withoutEvents(fn () => Supplier::create([
                'tenant_id' => $outra->id, 'name' => 'Fornecedor Vizinho', 'is_active' => true,
            ]));

            return PurchaseInvoice::create([
                'tenant_id'      => $outra->id,
                'supplier_id'    => $fornecedor->id,
                'invoice_number' => 'FC-VIZ-' . uniqid(),
                'invoice_date'   => now(),
                'status'         => 'pending',
                'subtotal'       => 7000,
                'total'          => 7000,
                'paid_amount'    => 0,
            ]);
        });
    }

    public function test_modal_de_pagamento_avisa_em_vez_de_rebentar(): void
    {
        $alheia = $this->facturaDeOutraEmpresa();

        Livewire::test(PaymentModal::class)
            ->call('openPaymentModal', 'purchase', $alheia->id)
            ->assertDispatched('recarregar-pagina')
            ->assertSet('show', false);   // e não abre nada
    }

    public function test_anular_documento_de_outra_empresa_nao_rebenta_nem_toca_no_registo(): void
    {
        $alheia = $this->facturaDeOutraEmpresa();

        Livewire::test(Invoices::class)
            ->call('cancelInvoice', $alheia->id)
            ->assertDispatched('recarregar-pagina')
            ->assertOk();

        $this->assertSame('pending', $alheia->refresh()->status);
    }

    public function test_marcar_como_paga_documento_de_outra_empresa_nao_faz_nada(): void
    {
        $alheia = $this->facturaDeOutraEmpresa();

        Livewire::test(Invoices::class)
            ->call('markAsPaid', $alheia->id)
            ->assertDispatched('recarregar-pagina')
            ->assertOk();

        $alheia->refresh();
        $this->assertSame('pending', $alheia->status);
        $this->assertEquals(0, (float) $alheia->paid_amount);
    }

    public function test_ver_documento_de_outra_empresa_nao_abre_a_ficha(): void
    {
        $alheia = $this->facturaDeOutraEmpresa();

        Livewire::test(Invoices::class)
            ->call('viewInvoice', $alheia->id)
            ->assertDispatched('recarregar-pagina')
            ->assertSet('showViewModal', false);
    }

    /** A porta continua aberta para os documentos da própria empresa. */
    public function test_documento_da_propria_empresa_continua_a_funcionar(): void
    {
        $fornecedor = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Fornecedor', 'is_active' => true]);

        $minha = PurchaseInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'supplier_id'    => $fornecedor->id,
            'invoice_number' => 'FC-' . uniqid(),
            'invoice_date'   => now(),
            'status'         => 'pending',
            'subtotal'       => 1000,
            'total'          => 1000,
            'paid_amount'    => 0,
        ]);

        Livewire::test(Invoices::class)
            ->call('viewInvoice', $minha->id)
            ->assertNotDispatched('recarregar-pagina')
            ->assertSet('showViewModal', true);
    }
}
