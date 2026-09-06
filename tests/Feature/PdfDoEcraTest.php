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

    /** As duas listas em React que mostram documentos da facturação. */
    private function listasEmReact(): array
    {
        return [
            'js/ecras/facturacao/vendas/ListaDeFacturas.tsx',
            'js/ecras/facturacao/ListaDeDocumentos.tsx',
        ];
    }

    /**
     * CADA DOCUMENTO CONTINUA A TER O SEU PAPEL À MÃO — OS DOIS PAPÉIS.
     *
     * As listas da facturação eram Blade e cada uma levava o botão do PDF do
     * ecrã ao lado da ligação ao PDF do servidor. Passaram a React e, durante
     * um tempo, só o do servidor sobreviveu: o desenho aprovado (a
     * pré-visualização) deixou de poder sair em papel tal como se vê.
     *
     * O que se prende aqui é que os DOIS continuam oferecidos, lado a lado:
     * nenhum substitui o outro. O do servidor tem texto para copiar; o do ecrã
     * é a própria pré-visualização, e por isso nunca diverge dela.
     *
     * @test
     */
    public function cada_documento_mantem_a_ligacao_ao_seu_pdf(): void
    {
        foreach ($this->listasEmReact() as $ficheiro) {
            $s = file_get_contents(resource_path($ficheiro));
            $nome = basename($ficheiro);

            $this->assertStringContainsString('/pdf', $s,
                "{$nome}: a lista tem de deixar chegar ao papel do documento");

            $this->assertStringContainsString('PdfDoEcra', $s,
                "{$nome}: falta o botão do PDF feito do próprio ecrã");

            // O botão vive da pré-visualização, não de uma rota nova.
            $this->assertStringContainsString('/preview', $s,
                "{$nome}: o PDF do ecrã fotografa a PRÉ-VISUALIZAÇÃO — é esse o endereço que leva");
        }
    }

    /**
     * O CONTRATO DO BOTÃO É O MESMO EM BLADE E EM REACT.
     *
     * O gerador (`/js/pdf-do-documento.js`) ouve o clique por DELEGAÇÃO no
     * documento e não sabe nada de React nem de Livewire: reconhece um botão
     * pelos seus `data-*`. Enquanto a peça em React escrever os mesmos
     * atributos que o `<x-pdf-descarregar>`, os dois mundos partilham a mesma
     * mecânica — e é por isso que não houve nada a reescrever.
     *
     * @test
     */
    public function a_peca_em_react_escreve_os_mesmos_atributos_do_componente_blade(): void
    {
        $peca = resource_path('js/ui/PdfDoEcra.tsx');

        $this->assertFileExists($peca, 'a peça partilhada dos ecrãs em React');

        $tsx = file_get_contents($peca);
        $blade = file_get_contents(resource_path('views/components/pdf-descarregar.blade.php'));

        // O gerador lê-os pelo `dataset`, onde o traço vira maiúscula.
        $atributos = [
            'data-pdf-preview' => 'pdfPreview',
            'data-pdf-nome' => 'pdfNome',
            'data-pdf-erro' => 'pdfErro',
        ];

        foreach ($atributos as $atributo => $noDataset) {
            $this->assertStringContainsString($atributo, $tsx, "falta {$atributo} na peça em React");
            $this->assertStringContainsString($atributo, $blade, "falta {$atributo} no componente Blade");
            $this->assertStringContainsString($noDataset, $this->gerador(),
                "o gerador tem de reconhecer {$atributo}");
        }

        // Sem `onClick`: quem trata do clique é o ouvinte por delegação. Um
        // manipulador próprio no React seria uma segunda mecânica a divergir.
        $this->assertStringNotContainsString('onClick', $tsx,
            'o clique é do ouvinte por delegação — não se duplica aqui');
    }

    /**
     * A PRÉ-VISUALIZAÇÃO EXISTE PARA TODOS OS DOCUMENTOS QUE A LISTA MOSTRA.
     *
     * O botão do PDF do ecrã aponta para `{rota}/{id}/preview`, construído a
     * partir da `rota` que o servidor manda em `TiposDeDocumento`. Se um tipo
     * novo entrar nesse registo sem ter pré-visualização, o botão aparece na
     * lista e falha ao ser carregado — este ensaio apanha isso antes.
     *
     * @test
     */
    public function todos_os_documentos_da_lista_tem_pre_visualizacao(): void
    {
        $uris = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri())->all();

        $rotas = collect(\App\Services\Invoicing\TiposDeDocumento::todos())
            ->pluck('rota')
            // As facturas de venda têm lista própria, mas o botão é o mesmo.
            ->push('/invoicing/sales/invoices');

        foreach ($rotas as $rota) {
            $esperada = ltrim($rota, '/') . '/{id}/preview';

            $this->assertContains($esperada, $uris,
                "sem {$esperada} o botão do PDF do ecrã não tem o que fotografar");
        }
    }

    /** @test */
    public function os_relatorios_do_pos_tem_o_botao_e_deixam_a_barra_de_fora(): void
    {
        // O POS continua em Livewire — o gestor de turnos, esse, passou a
        // React e leva a ligação ao PDF do servidor (ver TurnosDoPos.tsx).
        $relatorio = file_get_contents(resource_path('views/livewire/p-o-s/sales-report.blade.php'));

        foreach ([['relatório de vendas', $relatorio, 'relatorio-pos']] as [$nome, $s, $alvo]) {
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
