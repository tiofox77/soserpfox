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
 * Isto corre sobre os ficheiros porque a mecânica é JavaScript num aparelho
 * (`resources/js/pwa/papel` e `resources/js/pwa/motor/documentos.ts`) — o que
 * os ensaios de servidor conseguem prender é o CONTRATO: as peças existem, os
 * ecrãs chamam-nas, e o texto fiscal obedece à lei.
 */
class ImprimirDocumentoOfflineTest extends TenantTestCase
{
    private function papel(string $ficheiro): string
    {
        return file_get_contents(resource_path("js/pwa/papel/{$ficheiro}.ts"));
    }

    private function motor(): string
    {
        return file_get_contents(resource_path('js/pwa/motor/documentos.ts'));
    }

    /** @test */
    public function o_impressor_sabe_construir_documentos(): void
    {
        $documento = $this->papel('documento');

        $this->assertStringContainsString('export function buildDocumentHtml', $documento);
        $this->assertStringContainsString('printDocument(', $this->papel('index'));

        // Os três títulos que o formulário emite, mais a NC.
        foreach (['FACTURA PROFORMA', 'FACTURA RECIBO', 'NOTA DE CRÉDITO'] as $titulo) {
            $this->assertStringContainsString($titulo, $documento, "falta o título {$titulo}");
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
        // O CSS do documento é A4; o do talão continua 80mm.
        $this->assertStringContainsString('size: A4', $this->papel('documento'));
        $this->assertStringContainsString('size: 80mm auto', $this->papel('talao'));

        // E o printDocument encaminha: FR para o talão, o resto para o A4.
        $fachada = $this->papel('index');
        $ini = strpos($fachada, 'printDocument(doc: Registo, company: Registo = {}, extras?: ExtrasDoPapel)');
        $this->assertNotFalse($ini, 'o impressor recebe o molde do servidor como terceiro argumento');
        $fim = strpos($fachada, 'printShiftReport', $ini);
        $corpo = substr($fachada, $ini, $fim - $ini);

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
        $documento = $this->papel('documento');

        $this->assertStringContainsString('DOCUMENTO PROVISÓRIO', $documento);
        $this->assertStringContainsString('A numeração fiscal é atribuída na sincronização', $documento);
        $this->assertStringContainsString('ESTE DOCUMENTO NÃO SERVE DE FACTURA', $documento);
        $this->assertStringContainsString('Documento sem valor fiscal', $documento);
        $this->assertStringContainsString('DOCUMENTO PROVISÓRIO', $this->papel('molde'), 'o molde preenchido sem número também o diz');
    }

    /**
     * O texto fiscal NÃO SE TRADUZ (PLANO-MULTILINGUA.md, decisão 3): o
     * documento sai em português nas três línguas, como a lei angolana manda.
     * Um tradutor dentro do construtor do documento é fabricar um papel que a
     * AGT não reconhece.
     *
     * @test
     */
    public function o_texto_fiscal_do_documento_nao_passa_pelo_tradutor(): void
    {
        // Três sítios escrevem texto fiscal: o desenho de recurso, o talão e o
        // preenchimento do molde. O gerador de PDF e o relatório de fecho só
        // falam com o operador — esses podem (e devem) ser traduzidos.
        foreach (['documento', 'talao', 'molde'] as $ficheiro) {
            $fonte = $this->papel($ficheiro);

            $this->assertStringNotContainsString("from '@/i18n'", $fonte,
                "papel/{$ficheiro}.ts: o documento é fiscal, sai em português nas três línguas, sem tradutor");
            $this->assertDoesNotMatchRegularExpression('/\btn?\(\s*[\'"]/', $fonte,
                "papel/{$ficheiro}.ts chama o tradutor");
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

        $ini = strpos($motor, 'export async function imprimirDocumento(');
        $this->assertNotFalse($ini);
        $corpo = substr($motor, $ini, 1500);

        // O documento já emitido abre a pré-visualização do servidor. Os
        // endereços vivem em `previewDefinitivo`, que é quem os monta.
        $this->assertStringContainsString('previewDefinitivo(', $corpo,
            'o documento sincronizado tem de ir buscar a pré-visualização do site');

        $preview = strpos($motor, 'export async function previewDefinitivo(');
        $this->assertNotFalse($preview, 'falta quem monte o endereço da pré-visualização');
        $laco = substr($motor, $preview, 1500);

        // Os dois caminhos do site, tal e qual as rotas.
        $this->assertStringContainsString('/invoicing/sales/proformas/', $laco);
        $this->assertStringContainsString('/invoicing/sales/invoices/', $laco);
        $this->assertStringContainsString('/preview', $laco);

        // E as rotas existem mesmo — um caminho escrito à mão parte em silêncio.
        $this->assertNotNull(route('invoicing.sales.proformas.preview', ['id' => 1], false));
        $this->assertNotNull(route('invoicing.sales.invoices.preview', ['id' => 1], false));

        // A espera pelo número continua a ser a da rede verdadeira e dos 8 s.
        $this->assertStringContainsString('checkRealOnline', $corpo);
        $this->assertStringContainsString('8000', $corpo);
    }

    /** Os dois ecrãs chamam a impressão — sem os botões, a lógica não existe. */
    public function test_os_ecras_dos_documentos_tem_o_botao_de_imprimir(): void
    {
        $lista = file_get_contents(resource_path('js/pwa/ecras/Documentos.tsx'));
        $form = file_get_contents(resource_path('js/pwa/ecras/NovoDocumento.tsx'));

        $this->assertStringContainsString('imprimirDocumento', $lista);
        $this->assertStringContainsString('imprimirDocumento', $form);

        // E o formulário não foge para a lista sem oferecer o papel: o aviso
        // de guardado tem o botão de imprimir.
        $this->assertStringContainsString('data-ensaio="imprimir-documento"',
            file_get_contents(resource_path('js/pwa/ecras/novo-documento/AvisoDeGuardado.tsx')));
    }

    /** A fachada do impressor fica em todas as páginas: o motor põe-na na janela. */
    public function test_o_impressor_esta_em_todas_as_paginas_do_pwa(): void
    {
        $this->assertStringContainsString('window.PosOfflineTicket = PosOfflineTicket', file_get_contents(resource_path('js/pwa/motor/index.ts')));
        $this->assertStringContainsString('instalarMotor()', file_get_contents(resource_path('js/pwa.tsx')));
    }
}
