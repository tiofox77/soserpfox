<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Sales\InvoiceCreate;
use App\Models\Client;
use App\Models\Invoicing\PaymentTerm;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Condições de pagamento por cliente: catálogo por empresa (com padrões), e o
 * vencimento da factura sai da condição do cliente.
 */
class CondicaoPagamentoTest extends TenantTestCase
{
    public function test_provisiona_os_padroes_e_e_idempotente(): void
    {
        $criadas = PaymentTerm::provisionarPadroes($this->tenant->id);
        $this->assertSame(4, $criadas);

        $total = PaymentTerm::where('tenant_id', $this->tenant->id)->count();
        $this->assertSame(4, $total);

        // Correr de novo não duplica.
        $this->assertSame(0, PaymentTerm::provisionarPadroes($this->tenant->id));
        $this->assertSame(4, PaymentTerm::where('tenant_id', $this->tenant->id)->count());

        // Há exactamente uma padrão (Pronto Pagamento, 0 dias).
        $padrao = PaymentTerm::where('tenant_id', $this->tenant->id)->where('is_default', true)->get();
        $this->assertCount(1, $padrao);
        $this->assertSame(0, $padrao->first()->days);
    }

    public function test_cliente_liga_a_condicao(): void
    {
        $term = PaymentTerm::create([
            'tenant_id' => $this->tenant->id, 'name' => '30 dias', 'days' => 30,
        ]);
        $cliente = Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ACME',
            'nif' => (string) random_int(100000000, 199999999),
            'type' => 'pessoa_juridica',
            'payment_term_id' => $term->id,
        ]);

        $this->assertSame(30, $cliente->paymentTerm->days);
    }

    public function test_selecionar_cliente_define_o_vencimento(): void
    {
        $term = PaymentTerm::create([
            'tenant_id' => $this->tenant->id, 'name' => '30 dias', 'days' => 30,
        ]);
        $cliente = Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente 30d',
            'nif' => (string) random_int(100000000, 199999999),
            'type' => 'pessoa_juridica',
            'payment_term_id' => $term->id,
        ]);

        Livewire::test(InvoiceCreate::class)
            ->set('invoice_date', '2026-01-01')
            ->call('selectClient', $cliente->id)
            ->assertSet('due_date', '2026-01-31'); // +30 dias
    }

    public function test_pronto_pagamento_vence_no_proprio_dia(): void
    {
        $term = PaymentTerm::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Pronto', 'days' => 0,
        ]);
        $cliente = Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Pronto',
            'nif' => (string) random_int(100000000, 199999999),
            'type' => 'pessoa_juridica',
            'payment_term_id' => $term->id,
        ]);

        Livewire::test(InvoiceCreate::class)
            ->set('invoice_date', '2026-03-10')
            ->call('selectClient', $cliente->id)
            ->assertSet('due_date', '2026-03-10');
    }
}
