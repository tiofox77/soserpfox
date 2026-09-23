<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesQuote;
use App\Models\Invoicing\SalesQuoteItem;
use App\Models\Product;
use App\Services\Invoicing\DescricaoRica;
use Tests\TenantTestCase;

/**
 * A DESCRIÇÃO FORMATADA DAS LINHAS DE PROFORMAS E ORÇAMENTOS (23/09/2026).
 *
 * Escreve-se num editor, grava-se limpa (o HTML vem do browser), sai assim no
 * PDF e passa a texto quando a proposta vira factura, porque a linha da
 * factura vai à AGT e ao SAF-T.
 */
class DescricaoRicaDasPropostasTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/emissor';

    private function produto(): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Edição de vídeo', 'sku' => 'SRV-' . uniqid(),
            'price' => 1000, 'cost' => 0, 'type' => 'servico', 'manage_stock' => false,
        ]);
    }

    private function orcamentoCom(string $descricao): SalesQuote
    {
        $produto = $this->produto();
        $q = SalesQuote::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $this->cliente->id, 'created_by' => $this->user->id,
            'quote_date' => now()->toDateString(), 'status' => 'draft',
        ]);
        SalesQuoteItem::create([
            'sales_quote_id' => $q->id, 'product_id' => $produto->id, 'product_name' => $produto->name,
            'description' => $descricao, 'quantity' => 1, 'unit' => 'UN', 'unit_price' => 1000,
            'subtotal' => 1000, 'tax_rate' => 14, 'total' => 1140, 'order' => 1,
        ]);

        return $q->fresh();
    }

    /* ─── A limpeza ───────────────────────────────────────────────────── */

    public function test_fica_a_formatacao_e_sai_o_que_e_perigoso(): void
    {
        $limpo = DescricaoRica::limpar(
            '<h2>Âmbito</h2>'
            . '<p onclick="roubar()" style="text-align: center; color: red">Olá <strong>mundo</strong><script>alert(1)</script></p>'
            . '<img src="x" onerror="alert(1)"><a href="javascript:alert(1)">ligação</a>'
            . '<ul><li><p>um</p></li></ul>'
            . '<table><tbody><tr><td colspan="2" style="width: 9px">célula</td></tr></tbody></table>'
            . '<!-- comentário --><div><span>embrulhado</span></div>'
        );

        $this->assertStringContainsString('<h2>Âmbito</h2>', $limpo);
        $this->assertStringContainsString('<p style="text-align: center">Olá <strong>mundo</strong></p>', $limpo);
        $this->assertStringContainsString('<ul><li><p>um</p></li></ul>', $limpo);
        $this->assertStringContainsString('<td colspan="2">célula</td>', $limpo);
        // A ligação e o embrulho saem; o texto fica.
        $this->assertStringContainsString('ligação', $limpo);
        $this->assertStringContainsString('embrulhado', $limpo);

        foreach (['script', 'alert', 'onclick', 'onerror', '<img', 'href', 'javascript', 'color', 'width', '<a ', '<div', '<span', 'comentário'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $limpo, "«{$proibido}» não pode sobreviver à limpeza");
        }
    }

    public function test_o_editor_vazio_e_sem_descricao_e_o_texto_antigo_passa_como_esta(): void
    {
        $this->assertSame('', DescricaoRica::limpar('<p></p>'));
        // «A < B» é texto: uma etiqueta não tem espaço depois do «<».
        $this->assertFalse(DescricaoRica::eRica('Potência A < B e C > D'));
        $this->assertTrue(DescricaoRica::eRica('<p>Olá</p>'));
        $this->assertSame("linha 1\nlinha < 2", DescricaoRica::limpar("linha 1\nlinha < 2"));
        $this->assertSame("a<br />\nb &lt; c", DescricaoRica::paraImprimir("a\nb < c"));
    }

    public function test_para_a_factura_vai_o_texto_com_as_listas_em_linhas(): void
    {
        $this->assertSame(
            "Fases\n• Levantamento\n• Execução & entrega\nFim",
            DescricaoRica::emTexto('<h3>Fases</h3><ul><li><p>Levantamento</p></li><li><p>Execução &amp; entrega</p></li></ul><p>Fim</p>')
        );

        $longo = DescricaoRica::emTexto(str_repeat('a', 600));
        $this->assertSame(500, mb_strlen($longo));
        $this->assertStringEndsWith('…', $longo);
    }

    /* ─── A gravação ──────────────────────────────────────────────────── */

    public function test_o_orcamento_grava_a_descricao_formatada_limpa_e_longa(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.sales.quotes.create');

        $descricao = '<p><strong>Âmbito</strong></p><script>roubar()</script>' . str_repeat('<p>Filmagem, edição e entrega em 4K.</p>', 30);

        $id = $this->postJson(self::RAIZ . '/orcamentos', [
            'parte_id' => $this->cliente->id,
            'data' => now()->toDateString(),
            'linhas' => [['product_id' => $this->produto()->id, 'description' => $descricao, 'quantity' => 1, 'price' => 1000]],
        ])->assertCreated()->json('id');

        $gravada = SalesQuoteItem::where('sales_quote_id', $id)->value('description');

        $this->assertGreaterThan(500, mb_strlen($gravada), 'uma descrição de proposta passa das 500 letras');
        $this->assertStringStartsWith('<p><strong>Âmbito</strong></p>', $gravada);
        $this->assertStringNotContainsString('script', $gravada);
    }

    public function test_as_opcoes_dizem_quem_abre_o_editor(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.sales.quotes.view', 'invoicing.sales.proformas.view', 'invoicing.purchases.proformas.view');

        $this->getJson(self::RAIZ . '/orcamentos/opcoes')->assertOk()->assertJsonPath('descricao_rica', true);
        $this->getJson(self::RAIZ . '/proformas-venda/opcoes')->assertOk()->assertJsonPath('descricao_rica', true);
        $this->getJson(self::RAIZ . '/proformas-compra/opcoes')->assertOk()->assertJsonPath('descricao_rica', false);
    }

    public function test_a_proforma_de_compra_continua_com_uma_frase(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.purchases.proformas.create');

        $this->postJson(self::RAIZ . '/proformas-compra', [
            'parte_id' => 0,
            'data' => now()->toDateString(),
            'linhas' => [['product_id' => $this->produto()->id, 'description' => str_repeat('a', 600), 'quantity' => 1, 'price' => 10]],
        ])->assertStatus(422)->assertJsonValidationErrors('linhas.0.description');
    }

    /* ─── O papel e a factura ─────────────────────────────────────────── */

    public function test_o_papel_do_orcamento_sai_formatado(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.sales.quotes.view');

        $q = $this->orcamentoCom('<h3>Âmbito</h3><ul><li><p>Filmagem <strong>4K</strong></p></li></ul>');

        $this->get("/invoicing/sales/quotes/{$q->id}/preview")
            ->assertOk()
            ->assertSee('class="descricao-rica"', false)
            ->assertSee('<h3>Âmbito</h3>', false)
            ->assertSee('<li><p>Filmagem <strong>4K</strong></p></li>', false);
    }

    public function test_a_factura_convertida_leva_so_o_texto(): void
    {
        $q = $this->orcamentoCom('<h3>Âmbito</h3><ul><li><p>Filmagem</p></li><li><p>Edição</p></li></ul>');

        $linha = $q->convertToInvoice()->items()->first();

        $this->assertSame("Âmbito\n• Filmagem\n• Edição", $linha->description);
    }
}
