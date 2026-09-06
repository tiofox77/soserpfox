<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoicing\PaymentTerm;
use App\Models\Invoicing\SalesInvoice;
use Tests\TenantTestCase;

/**
 * Condições de pagamento por cliente: catálogo por empresa (com padrões), e o
 * vencimento da factura sai da condição do cliente.
 *
 * O ECRÃ DA FACTURA É AGORA EM REACT. O que o `selectClient` do Livewire fazia
 * — preencher o vencimento assim que se escolhia o cliente — passou para dois
 * sítios que TÊM de concordar: as opções do ecrã trazem os dias da condição de
 * cada cliente (é com eles que o campo se preenche à frente de quem emite), e
 * o servidor volta a aplicá-los ao gravar quando o pedido não traz vencimento
 * nenhum. Sem a segunda metade, uma factura emitida pela API nascia sem prazo
 * e aparecia vencida no próprio dia nas contas a receber.
 */
class CondicaoPagamentoTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/factura';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.sales.invoices.create')->comModulo('invoicing');
    }

    private function clienteCom(int $dias, string $nome): Client
    {
        $termo = PaymentTerm::create([
            'tenant_id' => $this->tenant->id, 'name' => $nome . ' termo', 'days' => $dias,
        ]);

        return Client::create([
            'tenant_id'       => $this->tenant->id,
            'name'            => $nome,
            'nif'             => (string) random_int(100000000, 199999999),
            'type'            => 'pessoa_juridica',
            'payment_term_id' => $termo->id,
        ]);
    }

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
        $cliente = $this->clienteCom(30, 'ACME');

        $this->assertSame(30, $cliente->paymentTerm->days);
    }

    /**
     * O ECRÃ SABE O PRAZO DE CADA CLIENTE.
     *
     * As opções trazem os dias da condição junto de cada cliente: é com eles
     * que o campo do vencimento se preenche mal se escolhe o cliente, como o
     * `selectClient` fazia.
     */
    public function test_as_opcoes_trazem_os_dias_da_condicao_de_cada_cliente(): void
    {
        $trinta = $this->clienteCom(30, 'Cliente 30d');
        $pronto = $this->clienteCom(0, 'Cliente Pronto');

        $clientes = collect($this->getJson(self::RAIZ . '/opcoes')->assertOk()->json('clientes'))->keyBy('id');

        $this->assertSame(30, $clientes[$trinta->id]['payment_term_days']);
        $this->assertSame(0, $clientes[$pronto->id]['payment_term_days']);
    }

    public function test_o_cliente_escolhido_define_o_vencimento_da_factura(): void
    {
        $cliente = $this->clienteCom(30, 'Cliente 30d');

        $id = $this->emitirPara($cliente, '2026-01-01');

        $this->assertSame('2026-01-31', SalesInvoice::findOrFail($id)->due_date->format('Y-m-d')); // +30 dias
    }

    public function test_pronto_pagamento_vence_no_proprio_dia(): void
    {
        $cliente = $this->clienteCom(0, 'Cliente Pronto');

        $id = $this->emitirPara($cliente, '2026-03-10');

        $this->assertSame('2026-03-10', SalesInvoice::findOrFail($id)->due_date->format('Y-m-d'));
    }

    /** Um vencimento escrito à mão no ecrã é deliberado — não se corrige. */
    public function test_o_vencimento_escolhido_a_mao_ganha_a_condicao(): void
    {
        $cliente = $this->clienteCom(30, 'Cliente 30d');

        $id = $this->emitirPara($cliente, '2026-01-01', ['due_date' => '2026-02-15']);

        $this->assertSame('2026-02-15', SalesInvoice::findOrFail($id)->due_date->format('Y-m-d'));
    }

    private function emitirPara(Client $cliente, string $data, array $por = []): int
    {
        return $this->postJson(self::RAIZ, array_merge([
            'client_id'    => $cliente->id,
            'warehouse_id' => $this->armazem->id,
            'invoice_type' => 'FT',
            'invoice_date' => $data,
            'status'       => 'draft',
            'linhas'       => [['product_id' => $this->produtoComStock(5, 1000)->id, 'quantity' => 1, 'price' => 1000]],
        ], $por))->assertCreated()->json('id');
    }
}
