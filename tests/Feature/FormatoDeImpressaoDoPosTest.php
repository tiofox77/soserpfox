<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\SalesInvoice;
use Illuminate\Support\Facades\Schema;
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
        $modal = file_get_contents(resource_path('js/ecras/facturacao/pos/ModalDoTalao.tsx'));

        // Abre no papel que a empresa configurou, que vem na venda fechada.
        $this->assertStringContainsString('porPapel(venda.formato)', $modal,
            'o modal abre no papel que a empresa configurou');

        // E deixa trocar, com os dois papéis à vista.
        $this->assertStringContainsString("'talao'", $modal);
        $this->assertStringContainsString("'a4'", $modal);
        $this->assertStringContainsString("t('configurado')", $modal,
            'qual dos dois é o da casa tem de se ver');

        /*
         * TROCAR NO MOMENTO NÃO MUDA A CONFIGURAÇÃO DA EMPRESA.
         *
         * Há sempre a venda que precisa do outro papel. O papel escolhido é
         * estado do modal e mais nada — nenhuma chamada ao servidor a gravar.
         */
        $this->assertStringNotContainsString('definicoes', $modal,
            'trocar no balcão é para esta venda, não para sempre');
    }

    /**
     * O MODAL EM BLADE FOI-SE, E COM ELE O 500 QUE O PERSEGUIA.
     *
     * O partial `pos.partials.print-modal` era partilhado por três ecrãs, e
     * quando o selector do papel entrou, entrou só no POS: /invoicing/pos/reports
     * passou a dar 500 («Undefined variable $formatoImpressao») a quem abrisse o
     * talão de uma venda. A fonte única era um trait, e o ensaio guardava que
     * nenhum ecrã voltasse a incluir o modal sem ele.
     *
     * Em React o problema não se põe: o papel vem no `meta.formato` da própria
     * consulta e a linha traz as duas moradas. Não há variável para faltar. O
     * que este ensaio prende agora é que o partial não volta.
     *
     * @test
     */
    public function o_modal_em_blade_nao_volta(): void
    {
        $this->assertFileDoesNotExist(
            resource_path('views/livewire/pos/partials/print-modal.blade.php'),
            'o modal do talão vive em React (ModalDoTalao.tsx); um segundo em Blade divergiria à primeira alteração',
        );

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($it as $ficheiro) {
            if ($ficheiro->isDir() || ! str_ends_with($ficheiro->getFilename(), '.blade.php')) {
                continue;
            }

            $this->assertStringNotContainsString(
                'pos.partials.print-modal',
                file_get_contents($ficheiro->getPathname()),
                $ficheiro->getFilename().' voltou a incluir o modal do talão em Blade',
            );
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

    /**
     * OS DOIS PAPÉIS SÃO DO SERVIDOR.
     *
     * Nem o talão nem o A4 se desenham no ecrã: são as duas moradas que o
     * servidor gera, as mesmas dos Documentos. Duas versões do mesmo papel
     * divergem à primeira alteração — e no talão, que é uma factura-recibo, a
     * divergência é um documento que a AGT não reconhece.
     *
     * E é por serem do servidor que o talão NÃO SE TRADUZ: é um documento fiscal
     * angolano, em português por lei. Se fosse desenhado em React, cada frase
     * dele passaria por `t()` e sairia em inglês a quem tem a interface em
     * inglês.
     *
     * @test
     */
    public function os_dois_papeis_sao_a_pre_visualizacao_do_servidor(): void
    {
        $modal = file_get_contents(resource_path('js/ecras/facturacao/pos/ModalDoTalao.tsx'));

        // O papel à frente é a morada que o servidor mandou, dentro de um iframe.
        $this->assertStringContainsString('venda.papeis[papel]', $modal,
            'o papel é o do servidor, não um segundo desenho');
        $this->assertStringContainsString('<iframe', $modal);

        // O botão imprime o que está à frente, não sempre o talão.
        $this->assertStringContainsString('function imprimir()', $modal);

        /*
         * E NADA DO DOCUMENTO PASSA POR `t()`.
         *
         * O que se traduz no modal são os BOTÕES e os avisos — «Imprimir»,
         * «Nova venda». O corpo do documento vem do servidor e não tem uma
         * única cadeia traduzível.
         */
        $this->assertStringNotContainsString('id="ticket-print"', $modal,
            'o talão voltou a ser desenhado no ecrã: em React isso passaria as menções legais por t()');
    }
}
