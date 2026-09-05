<?php

namespace Tests\Feature\Pwa;

use Tests\TenantTestCase;

/**
 * A impressão de documentos no PWA — proforma, factura, factura-recibo.
 *
 * PORQUE EXISTE. O POS sempre imprimiu o talão com ou sem rede; quem emitia
 * um documento pelos Documentos ficava de mãos vazias — não havia impressão
 * NENHUMA. A queixa foi literal: «não consigo imprimir a proforma ou factura
 * após fazer a mesma».
 *
 * Isto corre sobre os ficheiros porque a mecânica é JavaScript puro num
 * aparelho — o que os ensaios de servidor conseguem prender é o CONTRATO:
 * as peças existem, os ecrãs chamam-nas, e o texto fiscal obedece à lei.
 */
class ImprimirDocumentoOfflineTest extends TenantTestCase
{
    private function talao(): string
    {
        return file_get_contents(public_path('js/pos-offline-ticket.js'));
    }

    private function motor(): string
    {
        return file_get_contents(public_path('js/pwa-invoicing.js'));
    }

    /** @test */
    public function o_impressor_sabe_construir_documentos(): void
    {
        $talao = $this->talao();

        $this->assertStringContainsString('function buildDocumentHtml', $talao);
        $this->assertStringContainsString('printDocument', $talao);

        // Os três títulos que o formulário emite, mais a NC.
        foreach (['FACTURA PROFORMA', 'FACTURA RECIBO', 'NOTA DE CRÉDITO'] as $titulo) {
            $this->assertStringContainsString($titulo, $talao, "falta o título {$titulo}");
        }
    }

    /**
     * CADA PAPEL NO SEU FORMATO. A proforma e a factura de venda são
     * documentos e saem em A4; o talão de 80mm é o formato da Factura-Recibo.
     * A primeira versão mandava tudo para o talão e a proforma saía uma tira
     * minúscula ao canto de uma folha A4 — foi a queixa, com fotografia.
     *
     * @test
     */
    public function a_proforma_e_a_factura_saem_em_a4_e_a_fr_no_talao(): void
    {
        $talao = $this->talao();

        // O CSS do documento é A4; o do talão continua 80mm.
        $this->assertStringContainsString('size: A4', $talao);
        $this->assertStringContainsString('size: 80mm auto', $talao);

        // E o printDocument encaminha: FR para o talão, o resto para o A4.
        $ini = strpos($talao, 'printDocument(doc, company, extras)');
        $this->assertNotFalse($ini, 'o impressor recebe o molde do servidor como terceiro argumento');
        $fim = strpos($talao, 'printShiftReport', $ini);
        $corpo = substr($talao, $ini, $fim - $ini);

        // COM MOLDE, o papel é o do servidor — igual à pré-visualização — e
        // isso decide-se ANTES de qualquer desvio por tipo: com molde, até a
        // FR dos Documentos sai em A4, como sai no servidor. Sem molde (um
        // aparelho que ainda não sincronizou) fica o desenho de recurso.
        $molde = strpos($corpo, 'imprimirDocumentoHtml(preencherMolde(extras.molde, doc, extras)');
        $fr = strpos($corpo, "=== 'FR'");
        $this->assertNotFalse($molde, 'com molde, o papel é o modelo do servidor preenchido');
        $this->assertNotFalse($fr, 'sem molde, a FR tem de ser desviada para o talão');
        $this->assertLessThan($fr, $molde, 'o molde manda: a FR só vai ao talão quando não há molde');

        $this->assertStringContainsString('buildTicketHtml', $corpo);
        $this->assertStringContainsString('DOCUMENT_CSS', $corpo, 'os documentos de recurso têm de sair com o CSS A4');
    }

    /**
     * A PROVA FISCAL: o papel diz sempre a verdade sobre o que é.
     *
     * Um documento por sincronizar não tem numeração fiscal — o papel tem de
     * o gritar, senão passa por factura. E a proforma nunca é documento
     * fiscal — o aviso dela é permanente e é outro.
     *
     * @test
     */
    public function o_papel_diz_a_verdade_sobre_o_que_e(): void
    {
        $talao = $this->talao();

        $this->assertStringContainsString('DOCUMENTO PROVISÓRIO', $talao);
        $this->assertStringContainsString('A numeração fiscal é atribuída na sincronização', $talao);
        $this->assertStringContainsString('ESTE DOCUMENTO NÃO SERVE DE FACTURA', $talao);
        $this->assertStringContainsString('Documento sem valor fiscal', $talao);
    }

    /**
     * O texto fiscal NÃO SE TRADUZ (PLANO-MULTILINGUA.md, decisão 3): o
     * documento sai em português nas três línguas, como a lei angolana manda.
     * Um __() dentro do construtor do documento é fabricar um papel que a
     * AGT não reconhece.
     *
     * @test
     */
    public function o_texto_fiscal_do_documento_nao_passa_pelo_tradutor(): void
    {
        $talao = $this->talao();

        // Dois sítios escrevem texto fiscal: o desenho de recurso e o
        // preenchimento do molde. O gerador de PDF, que fica entre eles,
        // só fala com o operador — esse pode (e deve) ser traduzido.
        $trocos = [
            ['function buildDocumentHtml', 'const DOCUMENT_CSS'],
            ['function preencherMolde', 'window.PosOfflineTicket'],
        ];

        foreach ($trocos as [$de, $ate]) {
            $ini = strpos($talao, $de);
            $this->assertNotFalse($ini, "falta {$de}");
            $fim = strpos($talao, $ate, $ini);
            $this->assertNotFalse($fim, "falta {$ate} depois de {$de}");

            $this->assertStringNotContainsString('__(', substr($talao, $ini, $fim - $ini),
                "{$de}: o documento é fiscal, sai em português nas três línguas, sem tradutor");
        }
    }

    /**
     * UM DESENHO SÓ: o do site.
     *
     * Sincronizado e com rede, o papel é a página de preview que o site já
     * tem (`pdf/invoicing/*.blade.php`) — não uma cópia dela em JavaScript,
     * que divergia à primeira alteração sem ninguém dar por isso. O papel
     * local é RECURSO de offline, marcado como provisório.
     *
     * @test
     */
    public function sincronizado_abre_o_preview_do_site_e_nao_um_desenho_novo(): void
    {
        $motor = $this->motor();

        $this->assertStringContainsString('async imprimirDocumento(', $motor);

        $ini = strpos($motor, 'async imprimirDocumento(');
        $corpo = substr($motor, $ini, 3000);

        // O documento já emitido abre a pré-visualização do servidor. Os
        // endereços vivem em `previewDefinitivo`, que é quem os monta — o
        // impressor só lhe chama. Prende-se o laço inteiro, não uma janela de
        // caracteres a seguir a uma função, que se desfaz ao primeiro arrumo.
        $this->assertStringContainsString('previewDefinitivo(', $corpo,
            'o documento sincronizado tem de ir buscar a pré-visualização do site');

        $preview = strpos($motor, 'async previewDefinitivo(');
        $this->assertNotFalse($preview, 'falta quem monte o endereço da pré-visualização');
        $laco = substr($motor, $preview, 1500);

        // Os dois caminhos do site, tal e qual as rotas.
        $this->assertStringContainsString('/invoicing/sales/proformas/', $laco);
        $this->assertStringContainsString('/invoicing/sales/invoices/', $laco);
        $this->assertStringContainsString('/preview', $laco);

        // E as rotas existem mesmo — um caminho escrito à mão parte em silêncio.
        $this->assertNotNull(route('invoicing.sales.proformas.preview', ['id' => 1], false));
        $this->assertNotNull(route('invoicing.sales.invoices.preview', ['id' => 1], false));

        // A espera pelo número continua a ser a do emitirJa.
        $this->assertStringContainsString('checkRealOnline', $corpo);
        $this->assertStringContainsString('8000', $corpo);
    }

    /** Os dois ecrãs chamam a impressão — sem os botões, a lógica não existe. */
    public function test_os_ecras_dos_documentos_tem_o_botao_de_imprimir(): void
    {
        $lista = file_get_contents(resource_path('views/invoicing/offline/drafts.blade.php'));
        $form = file_get_contents(resource_path('views/invoicing/offline/draft-form.blade.php'));

        $this->assertStringContainsString('imprimirDocumento', $lista);
        $this->assertStringContainsString('imprimirDocumento', $form);

        // E o formulário deixou de fugir para a lista sem oferecer o papel.
        $this->assertStringNotContainsString('setTimeout(() => {', $form,
            'o redireccionamento automático engolia o momento de imprimir');
    }

    /** O layout do PWA carrega o impressor em todas as páginas offline. */
    public function test_o_impressor_esta_no_layout_do_pwa(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/pwa.blade.php'));

        $this->assertStringContainsString('pos-offline-ticket.js', $layout);
    }
}
