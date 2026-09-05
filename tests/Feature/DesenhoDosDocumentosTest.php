<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * Os documentos de venda e de compra seguem o mesmo desenho.
 *
 * A REFERÊNCIA É A FACTURA DE VENDA. É o modelo mais trabalhado, e a queixa foi
 * literal: «o PDF aparece com o css meio distorcido; usa como referência pelo
 * menos a factura de venda, ela está mais organizada».
 *
 * Duas coisas distorciam de facto:
 *
 *   · a linha do total saía −10 px numa caixa com 8 px de espaçamento, e ia
 *     pintar por cima da moldura azul — no browser mal se via, no PDF via-se o
 *     realce a transbordar do quadro;
 *   · a célula da descrição não partia palavras longas em quatro modelos, e um
 *     nome como «Bancada em aço inox com duas portas de correr 1100x600x900»
 *     esticava a coluna e desalinhava a tabela inteira.
 */
class DesenhoDosDocumentosTest extends TenantTestCase
{
    private function modelos(): array
    {
        return glob(resource_path('views/pdf/invoicing/*.blade.php'));
    }

    /**
     * O REALCE DO TOTAL ENCOSTA À MOLDURA, NÃO LHE PASSA POR CIMA.
     *
     * A margem negativa existe para o realce ir de ponta a ponta dentro da
     * caixa. Tem de valer exactamente o espaçamento interior: mais do que isso
     * e sai para fora.
     *
     * @test
     */
    public function a_linha_do_total_nao_transborda_da_caixa(): void
    {
        foreach ($this->modelos() as $f) {
            $fonte = file_get_contents($f);

            if (!preg_match('#\.summary-total\s*\{([^}]*)\}#', $fonte, $m)) {
                continue;
            }

            $this->assertStringNotContainsString('margin-left: -10px', $m[1],
                basename($f) . ': a margem passa a moldura e o realce transborda');

            if (str_contains($m[1], 'margin-left:')) {
                $this->assertStringContainsString('margin-left: -8px', $m[1],
                    basename($f) . ': a margem tem de valer o espaçamento da caixa (8px)');
            }
        }
    }

    /**
     * NOMES LONGOS PARTEM A LINHA, NÃO ESTICAM A COLUNA.
     *
     * Quem vende trabalhos à medida tem nomes de artigo de sessenta caracteres.
     * Sem esta regra, a coluna cresce até caber o nome e a tabela desalinha —
     * ou sai da folha.
     *
     * @test
     */
    public function a_descricao_longa_parte_a_linha_em_todos_os_modelos(): void
    {
        foreach ($this->modelos() as $f) {
            $fonte = file_get_contents($f);

            if (!str_contains($fonte, 'class="discriminacao"')) {
                continue;
            }

            $this->assertStringContainsString('max-width: 220px', $fonte,
                basename($f) . ': a descrição estica a coluna e desalinha a tabela');
            $this->assertStringContainsString('overflow-wrap: break-word', $fonte,
                basename($f) . ': falta partir a palavra');
        }
    }

    /**
     * UMA FACTURA NÃO SE DIZ PROFORMA.
     *
     * O modelo da factura de compra nasceu por cópia do da proforma e ficou com
     * os dois textos: «Total da Proforma» na linha do total e «Esta proforma
     * foi processada» no rodapé. Entregue a um fornecedor, era o documento a
     * contradizer-se a si próprio.
     *
     * @test
     */
    public function a_factura_de_compra_nao_se_chama_proforma(): void
    {
        $fonte = file_get_contents(resource_path('views/pdf/invoicing/purchase-invoice.blade.php'));

        $this->assertStringNotContainsString('Total da Proforma', $fonte);
        $this->assertStringNotContainsString('Esta proforma foi processada', $fonte);
        $this->assertStringContainsString('Total da Fatura', $fonte);
        $this->assertStringContainsString('Esta fatura foi processada', $fonte);
    }

    /**
     * NO ECRÃ, TODO O DOCUMENTO É UMA FOLHA A4.
     *
     * A pré-visualização da nota de crédito esticava-se à largura do browser e
     * não se parecia com papel nenhum: o `.page-wrapper` tinha `width: 100%`,
     * sem altura mínima, sem margens e sem sombra, e o corpo era branco sem
     * espaço à volta.
     *
     * A causa foi um engano fácil de repetir: aquelas são as correcções que o
     * DomPDF precisa, e alguém escreveu-as no CSS do ECRÃ em vez de as deixar
     * para o `partials/estilo-dompdf`, que só as aplica ao gerar PDF. O modelo
     * ficou permanentemente em modo impressora.
     *
     * @test
     */
    public function no_ecra_todo_documento_e_uma_folha_a4(): void
    {
        foreach ($this->modelos() as $f) {
            $fonte = file_get_contents($f);

            if (!str_contains($fonte, 'class="page-wrapper"')) {
                continue;
            }

            // A PRIMEIRA regra é a do ecrã; a segunda vive dentro do @media print.
            if (!preg_match('#\.page-wrapper\s*\{([^}]*)\}#', $fonte, $m)) {
                $this->fail(basename($f) . ': não tem regra para a folha');
            }

            $this->assertStringContainsString('width: 210mm', $m[1],
                basename($f) . ': a folha estica-se à largura do browser em vez de ser A4');
            $this->assertStringContainsString('min-height: 297mm', $m[1],
                basename($f) . ': sem altura de folha, o documento encolhe ao conteúdo');
            $this->assertStringContainsString('margin: 0 auto', $m[1],
                basename($f) . ': a folha fica encostada à esquerda em vez de centrada');
        }
    }
    /**
     * Os quatro documentos partilham o esqueleto da referência.
     *
     * @test
     */
    public function venda_e_compra_partilham_as_mesmas_pecas(): void
    {
        $pecas = ['page-wrapper', 'main-content', 'doc-info-table', 'items-table', 'tax-table', 'summary-section', 'system-info'];

        foreach (['sales-invoice', 'proforma', 'purchase-invoice', 'purchase-proforma'] as $modelo) {
            $fonte = file_get_contents(resource_path("views/pdf/invoicing/{$modelo}.blade.php"));

            foreach ($pecas as $peca) {
                $this->assertStringContainsString('class="' . $peca . '"', $fonte,
                    "{$modelo}: falta a peça {$peca} do desenho de referência");
            }

            // E todos passam pela mesma camada de correcções do DomPDF.
            $this->assertStringContainsString('partials.estilo-dompdf', $fonte,
                "{$modelo}: sem a camada de correcções, o PDF sai com outro desenho");
        }
    }
}
