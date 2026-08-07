<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * Os quatro ecrãs de criar documento partilham o cabeçalho.
 *
 * Tinham-no copiado, e a cópia já tinha divergido: a página de FACTURAS DE
 * VENDA dizia "Crie orçamentos de compras de Clientees" — texto vindo da
 * proforma de compra, com erro de escrita incluído. Ninguém dá por isso porque
 * cada página se lê sozinha.
 */
class CriarDocumentoCabecalhoTest extends TenantTestCase
{
    public static function ecras(): array
    {
        return [
            'factura de venda'   => ['/invoicing/sales/invoices/create',    'Nova Fatura de Venda',    'invoicing.sales.invoices.create'],
            'proforma de venda'  => ['/invoicing/sales/proformas/create',   'Nova Proforma de Venda',  'invoicing.sales.proformas.create'],
            'factura de compra'  => ['/invoicing/purchases/invoices/create', 'Nova Fatura de Compra',  'invoicing.purchases.invoices.create'],
            'proforma de compra' => ['/invoicing/purchases/proformas/create','Nova Proforma de Compra','invoicing.purchases.proformas.create'],
        ];
    }

    /**
     * @dataProvider ecras
     */
    public function test_cada_ecra_mostra_o_seu_titulo(string $url, string $titulo, string $permissao): void
    {
        $this->comPermissoes($permissao);
        $this->comModulo('invoicing');

        $this->actingAs($this->user)
            ->get($url)
            ->assertOk()
            ->assertSee($titulo)
            ->assertSee('Voltar');
    }

    public function test_a_factura_de_venda_nao_fala_de_compras(): void
    {
        // O texto trocado que a cópia manual deixou lá.
        $this->comPermissoes('invoicing.sales.invoices.create');
        $this->comModulo('invoicing');

        $this->actingAs($this->user)
            ->get('/invoicing/sales/invoices/create')
            ->assertOk()
            ->assertDontSee('orçamentos de compras')
            ->assertDontSee('Clientees');
    }
}
