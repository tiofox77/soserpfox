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
        return file_get_contents(resource_path('js/casca/pdfDoDocumento.ts'))
            . file_get_contents(resource_path('js/casca/bibliotecas.ts'));
    }

    private function modelos(): array
    {
        return glob(resource_path('views/pdf/invoicing/*.blade.php'));
    }

    /**
     * O GERADOR EXISTE E ESTÁ LIGADO ONDE HÁ BOTÕES.
     *
     * Era o `public/js/pdf-do-documento.js`, carregado pelo layout. Passou para
     * dentro do pacote do React: a peça `casca/sistema` liga-o em todas as
     * páginas, e o próprio botão (`PdfDoEcra`) liga-o onde quer que apareça —
     * ligar duas vezes não faz nada.
     *
     * @test
     */
    public function o_gerador_existe_e_esta_ligado(): void
    {
        foreach (['daPreVisualizacao', 'doElemento', 'PdfDoDocumento'] as $peca) {
            $this->assertStringContainsString($peca, $this->gerador(), "falta {$peca}");
        }

        $this->assertStringContainsString('ligarPdfDoDocumento()', file_get_contents(resource_path('js/ecras/casca/Sistema.tsx')));
        $this->assertStringContainsString('ligarPdfDoDocumento()', file_get_contents(resource_path('js/ui/PdfDoEcra.tsx')));

        $this->assertMatchesRegularExpression('/data-peca="casca\/sistema"/', $this->get('/home')->assertOk()->getContent(),
            'a peça que liga o gerador tem de estar no layout');

        $this->assertFileDoesNotExist(public_path('js/pdf-do-documento.js'), 'uma segunda cópia ia divergir da primeira');
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
     * A FICHA E O HISTÓRICO TAMBÉM DESCARREGAM — não só a linha.
     *
     * Em Blade, o modal de ver e o de histórico dos orçamentos, das proformas
     * de venda e de compra e das facturas de compra levavam o botão do PDF. A
     * migração deixou a ficha só com a pré-visualização e o histórico só com a
     * ligação no número: quem abria a ficha para mandar um orçamento ao
     * cliente tinha de a fechar e voltar à linha.
     *
     * A ficha oferece os DOIS papéis (o do servidor e o do ecrã), e cada
     * factura do histórico também. As moradas saem da `rota` que o servidor
     * manda — a da lista na ficha, a de cada factura no histórico.
     *
     * @test
     */
    public function a_ficha_e_o_historico_levam_ao_pdf(): void
    {
        $s = file_get_contents(resource_path('js/ecras/facturacao/ListaDeDocumentos.tsx'));

        $ficha = $this->pedaco($s, 'function FichaDoDocumento(', 'function Soma(');
        $this->assertStringContainsString('${rota}/${documento.id}/pdf', $ficha, 'a ficha tem de levar ao PDF do servidor');
        $this->assertStringContainsString('<PdfDoEcra', $ficha, 'a ficha tem de levar o PDF do ecrã');
        $this->assertStringContainsString('${rota}/${documento.id}/preview', $ficha, 'a pré-visualização continua na ficha');

        $historico = $this->pedaco($s, 'function HistoricoDeConversoes(', 'const TOM_DO_ESTADO');
        $this->assertStringContainsString('${f.rota}/${f.id}/pdf', $historico, 'cada factura do histórico tem de levar ao PDF');
        $this->assertStringContainsString('<PdfDoEcra', $historico, 'e ao PDF do ecrã');

        // E as moradas existem para todos os documentos da lista, e para as
        // duas espécies de factura que o histórico mostra.
        $uris = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri())->all();

        $rotas = collect(\App\Services\Invoicing\TiposDeDocumento::todos())
            ->pluck('rota')
            ->push('/invoicing/sales/invoices', '/invoicing/purchases/invoices');

        foreach ($rotas as $rota) {
            $esperada = ltrim($rota, '/') . '/{id}/pdf';

            $this->assertContains($esperada, $uris, "sem {$esperada} o botão do PDF da ficha leva ao vazio");
        }
    }

    /** O texto de um ficheiro entre duas marcas — para olhar só para uma peça. */
    private function pedaco(string $s, string $de, string $ate): string
    {
        $inicio = strpos($s, $de);
        $this->assertNotFalse($inicio, "não encontrei «{$de}»");

        $fim = strpos($s, $ate, $inicio);
        $this->assertNotFalse($fim, "não encontrei «{$ate}» depois de «{$de}»");

        return substr($s, $inicio, $fim - $inicio);
    }

    /**
     * O BOTÃO É SÓ OS SEUS ATRIBUTOS.
     *
     * O gerador ouve o clique por DELEGAÇÃO no documento e não sabe nada de
     * React: reconhece um botão pelos seus `data-*`. Enquanto a peça escrever
     * os atributos que o gerador lê, as linhas que o React troca a cada filtro
     * continuam a ter botão.
     *
     * @test
     */
    public function a_peca_em_react_escreve_os_atributos_que_o_gerador_le(): void
    {
        $peca = resource_path('js/ui/PdfDoEcra.tsx');

        $this->assertFileExists($peca, 'a peça partilhada dos ecrãs em React');

        $tsx = file_get_contents($peca);

        // O gerador lê-os pelo `dataset`, onde o traço vira maiúscula.
        $atributos = [
            'data-pdf-preview' => 'pdfPreview',
            'data-pdf-nome' => 'pdfNome',
            'data-pdf-erro' => 'pdfErro',
        ];

        foreach ($atributos as $atributo => $noDataset) {
            $this->assertStringContainsString($atributo, $tsx, "falta {$atributo} na peça em React");
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

    /**
     * O RELATÓRIO DE VENDAS PASSOU A REACT — e o papel dele é o do SERVIDOR.
     *
     * Enquanto foi Livewire, o PDF era uma fotografia do próprio ecrã
     * (html2canvas), com a barra de botões marcada `data-pdf-fora` para não
     * sair no papel. Em React deixou de ser preciso fotografar nada: o ecrã
     * leva às exportações de sempre, que já saem do mesmo
     * `PosSalesReportQuery` da lista — e um PDF gerado no servidor não corta
     * linhas nem depende do tamanho da janela de quem carregou no botão.
     *
     * O que aqui se prende é que essas ligações NÃO SE PERDERAM, e que levam
     * os filtros que estão à vista: um PDF do mês inteiro quando o ecrã mostra
     * uma semana é pior do que PDF nenhum.
     *
     * @test
     */
    public function os_relatorios_do_pos_levam_ao_papel_do_servidor(): void
    {
        $ecra = file_get_contents(resource_path('js/ecras/facturacao/pos/RelatorioDoPos.tsx'));

        foreach (['pdf', 'excel'] as $formato) {
            $this->assertStringContainsString("/invoicing/pos/export/sales-report/{$formato}?", $ecra,
                "falta a ligação ao {$formato} do servidor");
        }

        $this->assertStringContainsString('new URLSearchParams(filtros', $ecra,
            'as exportações têm de levar os filtros que estão à vista');

        // E as rotas existem mesmo — uma ligação para o vazio é pior do que
        // ligação nenhuma.
        $uris = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())->all();

        foreach (['pdf', 'excel'] as $formato) {
            $this->assertContains("invoicing/pos/export/sales-report/{$formato}", $uris);
        }
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
            'o dos telemóveis' => file_get_contents(resource_path('js/pwa/papel/saida.ts')),
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
