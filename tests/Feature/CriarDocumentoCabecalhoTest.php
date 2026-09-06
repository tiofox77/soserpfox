<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * Os quatro ecrãs de criar documento têm cada um o SEU cabeçalho.
 *
 * Tinham-no copiado, e a cópia já tinha divergido: a página de FACTURAS DE
 * VENDA dizia "Crie orçamentos de compras de Clientees" — texto vindo da
 * proforma de compra, com erro de escrita incluído. Ninguém dá por isso porque
 * cada página se lê sozinha.
 *
 * O QUE MUDOU COM O REACT: o miolo destes ecrãs deixou de vir do servidor, mas
 * o cabeçalho da página não — continua a ser o layout do Laravel a desenhá-lo,
 * com o título que a rota lhe dá (`App\Support\EcraReact`). É aí que a
 * confusão de textos se veria outra vez, e é aí que isto continua a olhar.
 * O resto do ecrã — botões, «Voltar», linhas — prova-se no browser, em
 * `tests/browser/react.factura.spec.js` e vizinhos.
 */
class CriarDocumentoCabecalhoTest extends TenantTestCase
{
    public static function ecras(): array
    {
        return [
            'factura de venda'   => ['/invoicing/sales/invoices/create',     'Fatura de Venda',            'facturacao/emitir-factura',            'invoicing.sales.invoices.create'],
            'proforma de venda'  => ['/invoicing/sales/proformas/create',    'Emitir · Proformas de Venda', 'facturacao/emitir-proposta',           'invoicing.sales.proformas.create'],
            'factura de compra'  => ['/invoicing/purchases/invoices/create', 'Fatura de Compra',           'facturacao/emitir-factura-de-compra',  'invoicing.purchases.invoices.create'],
            'proforma de compra' => ['/invoicing/purchases/proformas/create','Emitir · Proformas de Compra','facturacao/emitir-proposta',          'invoicing.purchases.proformas.create'],
        ];
    }

    /**
     * @dataProvider ecras
     */
    public function test_cada_ecra_mostra_o_seu_titulo(string $url, string $titulo, string $ecra, string $permissao): void
    {
        $this->comPermissoes($permissao);
        $this->comModulo('invoicing');

        $this->actingAs($this->user)
            ->get($url)
            ->assertOk()
            ->assertSee($titulo)
            // E abre o ecrã certo: um título certo por cima do ecrã errado
            // seria a mesma confusão, escondida.
            ->assertSee('data-ecra="' . $ecra . '"', false);
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

    /**
     * DOIS ECRÃS NUNCA PODEM DIZER O MESMO.
     *
     * É o defeito original, guardado directamente: se alguém copiar uma rota
     * para criar a seguinte e se esquecer de trocar o título, isto acusa.
     */
    public function test_nao_ha_dois_ecras_com_o_mesmo_titulo(): void
    {
        $titulos = array_map(fn ($e) => $e[1], self::ecras());

        $this->assertSame(count($titulos), count(array_unique($titulos)),
            'dois ecrãs de criar documento partilham o título — foi assim que a factura de venda passou a falar de compras');
    }
}
