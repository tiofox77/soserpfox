<?php

namespace Tests\Feature;

use App\Models\Invoicing\PaymentTerm;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use App\Models\Treasury\PaymentMethod;
use App\Support\FormaDePagamento;
use Tests\TenantTestCase;

/**
 * A FORMA DE PAGAMENTO NO PAPEL — ao lado da hora de emissão e do vencimento.
 *
 * Pedido de 15/09/2026. A coluna diz o NOME da forma de pagamento do documento
 * (do catálogo da empresa, não o código «TRANSFER»); um documento sem ela diz a
 * condição de pagamento do cliente. E o vencimento da factura sai — lia um campo
 * que a factura não tem e dizia sempre N/A.
 */
class FormaDePagamentoNoPapelTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FormaDePagamento::esquecer();

        $this->comModulo('invoicing')->comPermissoes(
            'invoicing.sales.invoices.create', 'invoicing.sales.invoices.view',
            'invoicing.sales.proformas.create', 'invoicing.sales.proformas.view',
        );
    }

    private function texto(string $url): string
    {
        return preg_replace('/\s+/', ' ', strip_tags($this->get($url)->assertOk()->getContent()));
    }

    public function test_o_nome_vem_do_catalogo_da_empresa_e_um_codigo_desconhecido_fica_em_maiusculas(): void
    {
        PaymentMethod::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $this->tenant->id, 'code' => 'TRANSFER'], ['name' => 'Transferência BAI', 'type' => 'bank_transfer', 'is_active' => true]);

        $this->assertSame('Transferência BAI', FormaDePagamento::nome('transfer', $this->tenant->id));
        $this->assertSame('Dinheiro', FormaDePagamento::nome('cash', $this->tenant->id));
        $this->assertSame('PAGAMENTO_TOKEN', FormaDePagamento::nome('pagamento_token', $this->tenant->id), 'o molde do PWA procura o código em maiúsculas');
        $this->assertNull(FormaDePagamento::nome('', $this->tenant->id));
    }

    public function test_a_fatura_recibo_mostra_a_forma_de_pagamento_pelo_nome_na_coluna_e_no_resumo(): void
    {
        $produto = $this->produtoComStock(50, 1000);

        $id = $this->postJson('/api/v1/invoicing/react/factura', [
            'client_id' => $this->clienteEmpresa()->id, 'warehouse_id' => $this->armazem->id, 'invoice_type' => 'FR',
            'invoice_date' => now()->toDateString(), 'status' => 'draft', 'payment_method' => 'TRANSFER',
            'linhas' => [['product_id' => $produto->id, 'quantity' => 1, 'price' => 1000]],
        ])->assertCreated()->json('id');
        // Grava-se a forma directamente: este ensaio é sobre o papel, não sobre o emissor.
        SalesInvoice::findOrFail($id)->forceFill(['payment_method' => 'TRANSFER'])->saveQuietly();

        $texto = $this->texto('/invoicing/sales/invoices/' . $id . '/preview');
        $nome = FormaDePagamento::nome('TRANSFER', $this->tenant->id);

        $this->assertStringContainsString('Hora De Emissão Data de Venc. Método de Pagamento Operador', $texto);
        $this->assertSame(2, substr_count($texto, $nome), 'na coluna do cabeçalho e no resumo da fatura-recibo');
        $this->assertStringNotContainsString('TRANSFER ', $texto, 'o código cru já não sai no papel');
    }

    public function test_a_factura_a_prazo_mostra_o_vencimento_e_a_condicao_do_cliente(): void
    {
        $produto = $this->produtoComStock(50, 1000);
        $cliente = $this->clienteEmpresa();
        $condicao = PaymentTerm::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'Pagamento a 30 dias', 'days' => 30, 'is_active' => true]);
        $cliente->forceFill(['payment_term_id' => $condicao->id, 'payment_term_days' => 30])->save();

        $id = $this->postJson('/api/v1/invoicing/react/factura', [
            'client_id' => $cliente->id, 'warehouse_id' => $this->armazem->id, 'invoice_type' => 'FT',
            'invoice_date' => now()->toDateString(), 'status' => 'draft',
            'linhas' => [['product_id' => $produto->id, 'quantity' => 1, 'price' => 1000]],
        ])->assertCreated()->json('id');

        $factura = SalesInvoice::findOrFail($id);
        $factura->forceFill(['payment_method' => null, 'due_date' => now()->addDays(30)])->save();

        $texto = $this->texto('/invoicing/sales/invoices/' . $id . '/preview');

        $this->assertStringContainsString('Pagamento a 30 dias', $texto);
        $this->assertStringContainsString(now()->addDays(30)->format('d/m/Y'), $texto, 'o vencimento sai; dizia sempre N/A');
    }

    public function test_o_nif_de_uma_conta_de_testes_grava_se_e_os_travoes_seguram(): void
    {
        $empresa = Tenant::create(['name' => 'Testes ' . uniqid(), 'slug' => 't-' . uniqid(), 'email' => 't' . uniqid() . '@x.ao', 'is_active' => true]);
        $nif = '5' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);

        $this->artisan('empresas:nif', ['--tenant' => $empresa->id, '--nif' => $nif])->expectsOutputToContain('SIMULAÇÃO')->assertExitCode(0);
        $this->assertNull($empresa->fresh()->nif);

        $this->artisan('empresas:nif', ['--tenant' => $empresa->id, '--nif' => '1234'])->assertExitCode(1);
        $this->artisan('empresas:nif', ['--tenant' => $empresa->id, '--nif' => $nif, '--aplicar' => true])->assertExitCode(0);
        $this->assertSame($nif, $empresa->fresh()->nif);

        // Outra empresa com o mesmo NIF: recusado.
        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $this->artisan('empresas:nif', ['--tenant' => $outra->id, '--nif' => $nif, '--aplicar' => true])->expectsOutputToContain('já tem este NIF')->assertExitCode(1);
    }
}
