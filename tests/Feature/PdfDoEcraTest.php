<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * O PDF feito no browser, a partir da própria pré-visualização.
 *
 * PORQUE EXISTE. O PDF do servidor sai do DomPDF, que não sabe flexbox, e por
 * isso os modelos trazem uma camada de correcções só para ele — o papel nunca
 * é exactamente o que se vê. A forma de não haver dois desenhos é não desenhar
 * duas vezes: fotografa-se a pré-visualização e embrulha-se numa folha A4.
 *
 * A mecânica é JavaScript no aparelho de quem carrega no botão. O que estes
 * ensaios prendem é o CONTRATO: as peças existem, todos os documentos têm o
 * botão, a pré-visualização NÃO desaparece, e os dois defeitos que se pagaram
 * caro não voltam.
 */
class PdfDoEcraTest extends TenantTestCase
{
    private function gerador(): string
    {
        return file_get_contents(public_path('js/pdf-do-documento.js'));
    }

    private function modelos(): array
    {
        return glob(resource_path('views/pdf/invoicing/*.blade.php'));
    }

    /** @test */
    public function o_gerador_existe_e_o_layout_carrega_o(): void
    {
        $this->assertFileExists(public_path('js/pdf-do-documento.js'));

        foreach (['daPreVisualizacao', 'doElemento', 'PdfDoDocumento'] as $peca) {
            $this->assertStringContainsString($peca, $this->gerador(), "falta {$peca}");
        }

        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString('/js/pdf-do-documento.js', $layout,
            'o gerador tem de estar no layout: as listas são Livewire e um @push não sobrevive às actualizações');

        $this->assertFileExists(resource_path('views/components/pdf-descarregar.blade.php'));
    }

    /**
     * AS BIBLIOTECAS SÃO PESADAS E SÓ DESCEM A PEDIDO.
     *
     * São 560 KB. Se viajassem no layout, pesavam em todas as páginas do
     * sistema por causa de um botão que a maioria nunca carrega.
     *
     * @test
     */
    public function as_bibliotecas_descem_so_quando_alguem_carrega(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringNotContainsString('vendor/js/html2canvas', $layout);
        $this->assertStringNotContainsString('vendor/js/jspdf', $layout);

        $g = $this->gerador();
        $this->assertStringContainsString('/vendor/js/html2canvas.min.js', $g);
        $this->assertStringContainsString('/vendor/js/jspdf.umd.min.js', $g);
        $this->assertStringContainsString('garantirBibliotecas', $g);

        // Servidas por nós, não por um CDN: sem rede lá fora, o botão continua.
        $this->assertFileExists(public_path('vendor/js/html2canvas.min.js'));
        $this->assertFileExists(public_path('vendor/js/jspdf.umd.min.js'));
    }

    /**
     * A PRÉ-VISUALIZAÇÃO NÃO SE TOCA.
     *
     * O botão novo ACRESCENTA. Quem confere um documento antes de o mandar
     * abre-o num separador, e é de lá que se imprime.
     *
     * @test
     */
    public function cada_documento_mantem_a_pre_visualizacao_ao_lado_do_pdf(): void
    {
        $listas = array_merge(
            glob(resource_path('views/livewire/invoicing/*/*.blade.php')),
            glob(resource_path('views/livewire/invoicing/*/*/*.blade.php')),
        );

        $comBotao = 0;

        foreach ($listas as $f) {
            $s = file_get_contents($f);

            // Os ecrãs de relatório fotografam a própria página; aqui só
            // interessam os documentos, que saem da sua pré-visualização.
            if (!str_contains($s, '<x-pdf-descarregar :url=')) continue;

            $comBotao++;
            $this->assertMatchesRegularExpression("#route\(\s*'[a-z0-9_.\-]+\.preview'#i", $s,
                basename($f) . ': o botão de PDF ficou, mas a ligação da pré-visualização desapareceu');
        }

        $this->assertGreaterThanOrEqual(20, $comBotao,
            'todos os documentos levam o botão — facturas, proformas, notas, recibos, adiantamentos e orçamentos');
    }

    /** @test */
    public function os_relatorios_do_pos_tem_o_botao_e_deixam_a_barra_de_fora(): void
    {
        $relatorio = file_get_contents(resource_path('views/livewire/p-o-s/sales-report.blade.php'));
        $turnos = file_get_contents(resource_path('views/livewire/invoicing/pos/pos-shift-manager.blade.php'));

        foreach ([['relatório de vendas', $relatorio, 'relatorio-pos'], ['gestor de turnos', $turnos, 'turno-pos']] as [$nome, $s, $alvo]) {
            $this->assertStringContainsString('data-pdf-alvo="' . $alvo . '"', $s, "{$nome}: falta o que fotografar");
            $this->assertStringContainsString('x-pdf-descarregar', $s, "{$nome}: falta o botão");
            $this->assertStringContainsString('data-pdf-fora', $s,
                "{$nome}: a barra de botões tem de ficar de fora do papel");
        }

        $this->assertStringContainsString('ignoreElements', $this->gerador(),
            'o gerador tem de saber saltar o que está marcado com data-pdf-fora');
    }

    /**
     * O TECTO DE UMA PÁGINA ESCONDIA OS TOTAIS.
     *
     * `.page-wrapper` tinha max-height:297mm com overflow:hidden. Numa factura
     * com mais de umas duas dezenas de linhas, o resumo de impostos, os totais
     * e o rodapé ficavam FORA da caixa e desapareciam — na pré-visualização e
     * ao imprimir. Medido: 40 linhas mostravam 34 e o total não aparecia. O PDF
     * do servidor, esse, paginava bem: o ecrã e o papel diziam coisas
     * diferentes sobre o mesmo documento.
     *
     * @test
     */
    public function nenhum_modelo_esconde_o_que_passa_de_uma_pagina(): void
    {
        foreach ($this->modelos() as $f) {
            $s = file_get_contents($f);

            if (!preg_match_all('#\.page-wrapper\s*\{([^}]*)\}#', $s, $m)) continue;

            foreach ($m[1] as $bloco) {
                $this->assertStringNotContainsString('max-height: 297mm', $bloco,
                    basename($f) . ': o tecto de uma página volta a cortar os totais');
                $this->assertStringNotContainsString('overflow: hidden', $bloco,
                    basename($f) . ': esconder o que transborda é perder conteúdo do documento');
            }
        }
    }

    /**
     * OS DOIS DEFEITOS DO CORTE DE PÁGINAS.
     *
     * O primeiro: a altura da fatia arredonda para baixo, e um documento que
     * ocupa exactamente uma folha ficava dois pixéis acima do corte — saíam
     * duas páginas, a segunda em branco.
     *
     * O segundo: a moldura escondida nascia com 10 px de altura, o browser
     * metia-lhe uma barra de deslocamento, e a folha passava a medir 779 px em
     * vez dos 794 que são 210 mm. A proporção deixava de ser A4.
     *
     * Os dois geradores partilham a mecânica, por isso os dois têm de estar
     * protegidos — o do browser e o que já corre nos telemóveis sem rede.
     *
     * @test
     */
    public function o_corte_de_paginas_tem_folga_e_a_moldura_nao_tem_barra(): void
    {
        $geradores = [
            'o do browser' => $this->gerador(),
            'o dos telemóveis' => file_get_contents(public_path('js/pos-offline-ticket.js')),
        ];

        foreach ($geradores as $nome => $js) {
            $this->assertStringContainsString('tolerancia', $js,
                "{$nome}: sem folga, um documento de uma página sai com duas");
            $this->assertStringContainsString('topo + tolerancia < canvas.height', $js,
                "{$nome}: a folga tem de estar na condição do ciclo");
            $this->assertStringContainsString('scrollbar-width:none', $js,
                "{$nome}: a barra de deslocamento estraga a proporção da folha");
            $this->assertStringNotContainsString('documentElement.scrollHeight, 1', $js,
                "{$nome}: a altura é a do documento, não a da página que o contém");
        }
    }

    /** @test */
    public function o_pdf_do_servidor_continua_de_pe(): void
    {
        // Não se substituiu nada: quem precisa de texto seleccionável continua
        // a ter o PDF do DomPDF onde sempre esteve.
        $this->assertTrue(
            collect(app('router')->getRoutes())->contains(fn ($r) => $r->getName() === 'invoicing.sales.invoices.pdf'),
            'a rota do PDF do servidor não pode ter desaparecido'
        );

        $this->assertTrue(
            collect(app('router')->getRoutes())->contains(fn ($r) => $r->getName() === 'invoicing.sales.invoices.preview'),
            'a pré-visualização é a origem de tudo: tem de existir sempre'
        );
    }
}
