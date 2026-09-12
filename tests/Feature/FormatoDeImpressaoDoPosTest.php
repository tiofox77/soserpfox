<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\SalesInvoice;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Em que papel sai a venda do balcão.
 *
 * PORQUE EXISTE. O POS só sabia imprimir talão de 80 mm. Serve uma loja com
 * impressora térmica, e não serve quem vende a empresas e entrega uma factura
 * em A4.
 *
 * TALÃO POR OMISSÃO. Nasceu ao contrário — «A4 imprime em qualquer
 * impressora» — e foi corrigido a 2026-09-05. Quem está ao balcão imprime
 * talão; a factura em A4 é a excepção, para a venda a uma empresa que a leva
 * para a contabilidade.
 *
 * E o A4 é a PRÉ-VISUALIZAÇÃO DO SERVIDOR, a mesma dos Documentos. Não se
 * desenha uma segunda factura só para o balcão: duas versões do mesmo papel
 * divergem à primeira alteração.
 */
class FormatoDeImpressaoDoPosTest extends TenantTestCase
{
    /** @test */
    public function a_definicao_existe_e_o_papel_por_omissao_e_o_talao(): void
    {
        $this->assertTrue(Schema::hasColumn('invoicing_settings', 'pos_formato_impressao'));

        $definicoes = InvoicingSettings::forTenant($this->tenant->id);

        $this->assertSame('talao', $definicoes->pos_formato_impressao,
            'quem não escolher nada imprime talão — é o que o balcão faz');
    }

    /**
     * NULL TAMBÉM É TALÃO.
     *
     * A omissão da coluna não chega: o acessor do modelo respondia 'a4' a tudo
     * o que não fosse 'talao', e enquanto isso durasse mudar o `default` da
     * coluna não mudava nada para ninguém.
     *
     * @test
     */
    public function nada_gravado_continua_a_ser_talao(): void
    {
        // A coluna é NOT NULL, por isso o null só aparece numas definições por
        // gravar — o que forTenant() devolve a quem não está ligado a empresa
        // nenhuma, que é o estado normal do dono da plataforma.
        $semNada = new InvoicingSettings();

        $this->assertSame('talao', $semNada->pos_formato_impressao);

        // E um valor que não seja nenhum dos dois também cai no talão.
        $estranho = new InvoicingSettings(['pos_formato_impressao' => 'xpto']);

        $this->assertSame('talao', $estranho->pos_formato_impressao);
    }

    /** @test */
    public function a_definicao_grava_e_so_aceita_os_dois_papeis(): void
    {
        $definicoes = InvoicingSettings::forTenant($this->tenant->id);
        $definicoes->update(['pos_formato_impressao' => 'a4']);

        $this->assertSame('a4', $definicoes->fresh()->pos_formato_impressao);

        // O que vem do navegador não escolhe o que sai na impressora. A regra
        // vive no serviço, que o Livewire e a API em React partilham.
        $servico = file_get_contents(app_path('Services/Invoicing/DefinicoesDaFacturacao.php'));
        $this->assertStringContainsString("'pos_formato_impressao' => 'nullable|in:a4,talao'", $servico);
        $this->assertStringContainsString(
            "\$valores['pos_formato_impressao'] ?: 'talao'",
            $servico,
            'sem valor, cai no talão'
        );
    }

    /**
     * O ecrã das definições continua a deixar escolher o papel.
     *
     * O ecrã passou a React: a guarda deixou de apontar ao blade (que já não
     * existe) e passou a apontar ao `.tsx` e à API que o serve. A intenção é
     * a mesma — se alguém tirar o selector, o papel deixa de se poder mudar e
     * ninguém dá por isso até alguém ao balcão precisar de uma factura em A4.
     *
     * @test
     */
    public function o_ecra_das_definicoes_deixa_escolher(): void
    {
        $ecra = file_get_contents(resource_path('js/ecras/facturacao/Definicoes.tsx'));

        $this->assertStringContainsString("texto('pos_formato_impressao')", $ecra, 'o selector do papel saiu do ecrã');
        $this->assertStringContainsString('value="talao"', $ecra);
        $this->assertStringContainsString('value="a4"', $ecra);

        // E a API entrega o valor para o ecrã o desenhar já escolhido.
        $this->comModulo('invoicing')->comPermissoes('invoicing.settings.view');

        $this->getJson('/api/v1/invoicing/react/definicoes')
            ->assertOk()
            ->assertJsonPath('definicoes.pos_formato_impressao', 'talao');
    }

    /**
     * O modal abre no papel da casa, e deixa trocar sem ir às definições.
     *
     * Há sempre a venda que precisa do outro papel. Obrigar a mudar a
     * configuração para imprimir uma factura é obrigar a mudá-la outra vez a
     * seguir — e quem está ao balcão com um cliente à frente não vai lá.
     *
     * @test
     */
    public function o_modal_abre_no_papel_da_casa_e_deixa_trocar(): void
    {
        $trait = file_get_contents(app_path('Livewire/Concerns/FormatoDeImpressao.php'));

        $this->assertStringContainsString('public $formatoImpressao', $trait);
        $this->assertStringContainsString('pos_formato_impressao', $trait,
            'o modal abre no papel que a empresa configurou');
        $this->assertStringContainsString('public function trocarFormatoImpressao', $trait);

        // Trocar no momento não muda a configuração da empresa.
        $troca = substr($trait, strpos($trait, 'function trocarFormatoImpressao'), 300);
        $this->assertStringNotContainsString('InvoicingSettings', $troca,
            'trocar no balcão é para esta venda, não para sempre');
    }

    /**
     * QUEM INCLUI O MODAL TEM DE TER O QUE ELE PEDE.
     *
     * O partial é partilhado por três ecrãs. Quando o selector do papel
     * entrou, entrou só no POS — e /invoicing/pos/reports passou a dar 500
     * («Undefined variable $formatoImpressao») a quem abrisse o talão de uma
     * venda. O trait é a fonte única; este teste é o que garante que ninguém
     * volta a incluir o modal sem ele.
     *
     * @test
     */
    public function todos_os_ecras_que_incluem_o_modal_sabem_o_papel(): void
    {
        $consumidores = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($it as $ficheiro) {
            if ($ficheiro->isDir() || ! str_ends_with($ficheiro->getFilename(), '.blade.php')) {
                continue;
            }

            if (str_contains(file_get_contents($ficheiro->getPathname()), 'pos.partials.print-modal')) {
                $consumidores[] = $ficheiro->getPathname();
            }
        }

        // O próprio partial e o comentário nos scripts não contam.
        $consumidores = array_values(array_filter(
            $consumidores,
            fn ($c) => ! str_contains($c, 'print-modal.blade.php') && ! str_contains($c, 'scripts.blade.php')
        ));

        $this->assertNotEmpty($consumidores, 'o modal deixou de ser incluído em lado nenhum?');

        /*
         * Os componentes por trás dessas vistas.
         *
         * SAÍRAM DOIS DESTA LISTA, e pela mesma razão: o relatório de vendas e
         * o balcão do salão passaram a React, onde o papel vem no
         * `meta.formato` da própria consulta — não há componente Livewire a
         * quem dar o trait. Ver `RelatorioDoPos.tsx`, e `/salon/pos`, que hoje
         * monta o POS da facturação.
         */
        $componentes = [
            \App\Livewire\POS\POSSystem::class,
        ];

        $this->assertCount(count($componentes), $consumidores,
            'apareceu um ecrã novo a incluir o modal: junte o componente a esta lista e dê-lhe o trait');

        foreach ($componentes as $classe) {
            $reflexao = new \ReflectionClass($classe);

            $this->assertTrue($reflexao->hasProperty('formatoImpressao'),
                "{$classe} inclui o modal e não sabe em que papel imprime");
            $this->assertTrue($reflexao->hasMethod('trocarFormatoImpressao'),
                "{$classe} não deixa trocar de papel");
            $this->assertTrue($reflexao->hasMethod('closePrintModal'),
                "{$classe} não fecha o talão");
        }
    }

    /**
     * O 500 verdadeiro, reproduzido: abrir o talão no relatório de vendas.
     *
     * Em React o papel não é estado do ecrã — vem no `meta.formato` da própria
     * consulta, e a linha traz as DUAS moradas do papel. O que aqui se prende é
     * que elas existem e ABREM: era o 500 de sempre.
     *
     * @test
     */
    public function o_relatorio_de_vendas_abre_o_talao_sem_rebentar(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.pos.reports');

        $factura = SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->clienteEmpresa()->id,
            'invoice_number' => 'FR TESTE/' . random_int(1000, 9999),
            'invoice_date'   => now(),
            'status'         => 'paid',
            'total'          => 1500,
            'created_by'     => $this->user->id,
        ]);

        $mapa = $this->getJson('/api/v1/invoicing/react/pos/relatorio?'.http_build_query([
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]))->assertOk()->json();

        $this->assertSame('talao', $mapa['meta']['formato'], 'o papel por omissão é o talão');

        $linha = collect($mapa['data'])->firstWhere('numero', $factura->invoice_number);

        $this->assertNotNull($linha, 'a factura tem de aparecer no mapa');
        $this->assertNotNull($linha['papeis'], 'sem as moradas do papel não há botão de imprimir');

        $this->comPermissoes('invoicing.sales.invoices.view');

        $this->get($linha['papeis']['talao'])->assertOk();
        $this->get($linha['papeis']['a4'])->assertOk();
    }

    /** @test */
    public function o_a4_e_a_pre_visualizacao_do_servidor(): void
    {
        $modal = file_get_contents(resource_path('views/livewire/pos/partials/print-modal.blade.php'));

        $this->assertStringContainsString("route('invoicing.sales.invoices.preview', \$lastInvoice->id)", $modal,
            'o A4 é o MESMO papel dos Documentos, não um segundo desenho');

        $this->assertStringContainsString('trocarFormatoImpressao', $modal, 'faltam os dois botões');
        $this->assertStringContainsString('imprimirA4()', $modal);
        $this->assertStringContainsString('printTicket()', $modal, 'o talão continua onde estava');

        // O botão imprime o que está à frente, não sempre o talão.
        $this->assertStringContainsString("\$formatoImpressao === 'a4' ? 'imprimirA4()' : 'printTicket()'", $modal);
    }

    /** @test */
    public function o_talao_continua_a_nao_ser_traduzido(): void
    {
        $modal = file_get_contents(resource_path('views/livewire/pos/partials/print-modal.blade.php'));

        $ini = strpos($modal, 'id="ticket-print"');
        $fim = strpos($modal, '{{-- Botões --}}');

        $this->assertNotFalse($ini);

        // O talão é uma factura-recibo: documento fiscal angolano, em
        // português por lei. Traduzir uma menção legal é entregar ao cliente um
        // documento que a AGT não reconhece.
        $this->assertStringNotContainsString('__(', substr($modal, $ini, $fim - $ini));
    }
}
