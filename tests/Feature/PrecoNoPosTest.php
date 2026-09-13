<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * Artigos cujo preço se decide no balcão.
 *
 * PORQUE EXISTE. Há negócios em que o preço não está na ficha — um trabalho em
 * inox feito à medida, um serviço combinado com o cliente. O catálogo entra sem
 * preço e quem vende escreve-o na hora.
 *
 * SÓ MUDA O POS. Na factura de venda o preço da linha sempre se escreveu à mão.
 * É no POS, onde tocar no artigo o mete logo no carrinho, que a pergunta tinha
 * de existir — senão o artigo entrava a zero e a venda saía a zero.
 */
class PrecoNoPosTest extends TenantTestCase
{
    private function artigo(array $extra = []): Product
    {
        $p = new Product(array_merge([
            'name'         => 'Bancada em inox à medida',
            'type'         => 'servico',
            'price'        => 0,
            'manage_stock' => false,
            'is_active'    => true,
            'unit'         => 'UN',
        ], $extra));

        $p->tenant_id = $this->tenant->id;
        $p->save();

        return $p;
    }

    /** @test */
    public function a_coluna_existe_e_nasce_desligada(): void
    {
        $this->assertTrue(Schema::hasColumn('invoicing_products', 'preco_no_pos'));

        // Por omissão nada muda: quem não pediu isto não nota diferença.
        $this->assertFalse((bool) $this->artigo()->preco_no_pos);
    }

    /**
     * O QUE SE MARCA NO ECRÃ É O QUE FICA GRAVADO.
     *
     * A marca perdeu-se ao migrar o ecrã de artigos para React: o formulário
     * em Livewire tinha-a, a API que o substituiu nunca a aceitou, e quem
     * vende trabalhos à medida ficou sem maneira de a ligar.
     *
     * @test
     */
    public function o_formulario_do_produto_tem_a_opcao(): void
    {
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.products.create', 'invoicing.products.edit', 'invoicing.products.view');

        $categoria = \App\Models\Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Trabalhos ' . uniqid(),
            'is_active' => true,
        ]);

        $base = [
            'name' => 'Bancada em inox à medida',
            'type' => 'servico',
            'price' => 0,
            'unit' => 'UN',
            'category_id' => $categoria->id,
            'tax_type' => 'iva',
            'tax_rate_id' => $this->imposto->id,
        ];

        $id = $this->postJson('/api/v1/invoicing/react/products', $base + ['preco_no_pos' => true])
            ->assertCreated()
            ->assertJsonPath('data.preco_no_pos', true)
            ->json('data.id');

        $this->assertTrue((bool) Product::find($id)->preco_no_pos);

        // A editar, um pedido que não traga o campo não desmarca a decisão.
        $this->putJson("/api/v1/invoicing/react/products/{$id}", $base + ['name' => 'Bancada em inox'])
            ->assertOk()
            ->assertJsonPath('data.preco_no_pos', true);

        // E desmarcar de propósito desmarca.
        $this->putJson("/api/v1/invoicing/react/products/{$id}", $base + ['preco_no_pos' => false])
            ->assertOk()
            ->assertJsonPath('data.preco_no_pos', false);

        // O ecrã tem a caixa: sem ela não há como marcar seja o que for.
        $this->assertStringContainsString(
            'preco_no_pos',
            file_get_contents(resource_path('js/ecras/facturacao/Produtos.tsx')),
            'o formulário do produto em React tem a opção'
        );
    }

    /**
     * A PERGUNTA VEM DEPOIS DAS VALIDAÇÕES.
     *
     * Escrever um preço numa venda que a seguir é recusada por falta de stock
     * ou por ser psicotrópico não confirmado é fazer o operador trabalhar duas
     * vezes.
     *
     * @test
     */
    public function o_pos_pergunta_o_preco_e_so_depois_das_outras_validacoes(): void
    {
        $ecra = file_get_contents(resource_path('js/ecras/facturacao/pos/PontoDeVenda.tsx'));

        $controlado = strpos($ecra, 'porAConfirmarControlado(a)');
        $pergunta = strpos($ecra, 'porAPerguntarPreco(a)');

        $this->assertNotFalse($controlado, 'falta a confirmação do psicotrópico');
        $this->assertNotFalse($pergunta, 'falta a pergunta do preço no POS');
        $this->assertLessThan($pergunta, $controlado, 'a confirmação do psicotrópico vem primeiro');

        // E confirmado o psicotrópico, a pergunta do preço ainda acontece: um
        // artigo pode ser as duas coisas.
        $this->assertStringContainsString('if (a.pergunta_preco) porAPerguntarPreco(a);', $ecra,
            'a confirmação do psicotrópico não pode atropelar a pergunta do preço');

        // O preço escrito é o que entra na linha, não o da ficha.
        $modal = file_get_contents(resource_path('js/ecras/facturacao/pos/ModalDePreco.tsx'));

        $this->assertStringContainsString('const valido = Number.isFinite(numero) && numero > 0;', $modal,
            'o valor vem do browser: zero e negativo não passam');
    }

    /**
     * A PERGUNTA É UM MODAL DO POS, NÃO A CAIXA DO NAVEGADOR.
     *
     * O `window.prompt` funcionava, mas era o único sítio do POS que não se
     * parecia com o POS: sem o nome do artigo em destaque, sem máscara de
     * dinheiro, e com os botões cinzentos do sistema operativo.
     *
     * @test
     */
    public function a_pergunta_e_um_modal_do_pos(): void
    {
        $modal = resource_path('js/ecras/facturacao/pos/ModalDePreco.tsx');
        $this->assertFileExists($modal);

        $html = file_get_contents($modal);

        $this->assertStringContainsString("titulo={t('Preço desta venda')}", $html);
        $this->assertStringContainsString('subtitulo={artigo?.nome}', $html,
            'o artigo aparece por inteiro: os nomes são longos');
        $this->assertStringContainsString("t('Juntar ao carrinho')", $html);
        $this->assertStringContainsString("t('Cancelar')", $html, 'cancelar é a forma de desistir');
        // O preço de catálogo entra escrito como proposta, e o campo abre
        // seleccionado: quem só quer confirmar carrega em Enter.
        $this->assertStringContainsString('porValor(String(artigo.preco))', $html);
        $this->assertStringContainsString('onFocus={(e) => e.target.select()}', $html);

        // A CAIXA DO NAVEGADOR SAIU DE VEZ.
        $ecra = file_get_contents(resource_path('js/ecras/facturacao/pos/PontoDeVenda.tsx'));

        $this->assertStringContainsString('<ModalDePreco', $ecra, 'o modal tem de estar no ecrã');
        $this->assertStringNotContainsString('window.prompt', $ecra);
    }

    /** @test */
    public function o_modal_devolve_o_preco_sem_perder_a_confirmacao_do_psicotropico(): void
    {
        $ecra = file_get_contents(resource_path('js/ecras/facturacao/pos/PontoDeVenda.tsx'));

        // O preço escrito volta e entra na linha — não o da ficha.
        $this->assertStringContainsString('if (aPerguntarPreco) juntar(aPerguntarPreco, preco);', $ecra);
        $this->assertStringContainsString('const preco = precoEscrito ?? a.preco;', $ecra,
            'o preço escrito manda; o de catálogo é o plano B');

        /*
         * E A CONFIRMAÇÃO DO PSICOTRÓPICO SOBREVIVE À PERGUNTA DO PREÇO.
         *
         * Um artigo pode ser as duas coisas: confirma-se a venda controlada e o
         * preço é perguntado a seguir — não ao contrário, e nenhum dos dois se
         * come ao outro.
         */
        $confirmacao = strpos($ecra, "t('Confirmo a venda')");
        $this->assertNotFalse($confirmacao);

        $bloco = substr($ecra, max(0, $confirmacao - 900), 900);
        $this->assertStringContainsString('if (a.pergunta_preco) porAPerguntarPreco(a);', $bloco);
        $this->assertStringContainsString('else juntar(a);', $bloco);
    }

    /**
     * A SINCRONIZAÇÃO DO CARRINHO NÃO APAGA O PREÇO ESCRITO.
     *
     * Este foi o defeito a sério, e não se via no caminho do modal: antes de
     * calcular qualquer total, o POS percorre o carrinho e repõe o preço de
     * cada linha pelo preço da FICHA — existe para uma linha antiga não
     * sobreviver com o preço de ontem. Num artigo cujo preço é perguntado na
     * venda, a ficha diz zero por definição, e o valor escrito no balcão era
     * apagado no instante a seguir. Via-se: escrevia-se 17.500 e a linha
     * aparecia a 0,00.
     *
     * O imposto CONTINUA a ser sincronizado. Esse é fiscal e segue a ficha e o
     * regime, aconteça o que acontecer ao preço.
     *
     * @test
     */
    public function a_sincronizacao_do_carrinho_respeita_o_preco_escrito(): void
    {
        /*
         * EM REACT O PROBLEMA DEIXOU DE EXISTIR — e é isso que se prende aqui.
         *
         * O carrinho em Livewire vivia na sessão e era SINCRONIZADO com a ficha
         * a cada desenho, para uma sessão de ontem não sobreviver com o preço de
         * ontem. Num artigo de preço perguntado, a ficha diz zero por definição,
         * e o valor escrito ao balcão era apagado no instante a seguir: escrevia-se
         * 17.500 e a linha aparecia a 0,00.
         *
         * O carrinho é hoje estado do ecrã, e o preço da linha só muda quando
         * alguém o muda. Se voltar a haver uma sincronização com a ficha, este
         * ensaio avisa.
         */
        $ecra = file_get_contents(resource_path('js/ecras/facturacao/pos/PontoDeVenda.tsx'));

        // O preço vive na linha do carrinho, e é ele que viaja para a venda.
        $this->assertStringContainsString('preco: number;', $ecra,
            'o preço é da linha do carrinho, não da ficha do artigo');
        $this->assertStringContainsString('unit_price: l.preco', $ecra,
            'o que se factura é o preço da linha');

        // E nada volta a ir buscar o preço à ficha depois de a linha existir.
        $this->assertStringNotContainsString('sincronizarCarrinho', $ecra);
        $this->assertStringNotContainsString('preco: a.preco }', $ecra,
            'uma sincronização com a ficha apagava o preço escrito ao balcão');
    }

    /** @test */
    public function a_factura_de_venda_nao_e_afectada(): void
    {
        // A regra é só do balcão. Se um dia alguém a levar para a factura, isto
        // avisa — lá o preço da linha sempre foi escrito à mão.
        foreach (['Services/Invoicing/EmissorDeFacturas.php', 'Http/Controllers/Api/Invoicing/FacturaApiController.php', 'Http/Controllers/Api/Invoicing/EmissorApiController.php'] as $ecra) {
            $this->assertStringNotContainsString('preco_no_pos', file_get_contents(app_path($ecra)),
                "{$ecra}: a factura não muda por causa disto");
        }
    }
}
