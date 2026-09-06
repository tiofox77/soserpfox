<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
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
        $pos = file_get_contents(app_path('Livewire/POS/POSSystem.php'));

        $controlado = strpos($pos, "\$product->is_controlled && !\$controladoConfirmado");
        $pergunta = strpos($pos, "\$product->preco_no_pos && \$precoEscrito === null");

        $this->assertNotFalse($controlado);
        $this->assertNotFalse($pergunta, 'falta a pergunta do preço no POS');
        $this->assertLessThan($pergunta, $controlado, 'a confirmação do psicotrópico vem primeiro');

        // O preço escrito é o que entra na linha, não o da ficha.
        $this->assertStringContainsString("'price' => \$precoDaLinha", $pos);
        $this->assertStringContainsString('max(0, (float) str_replace', $pos,
            'o valor vem do browser: negativo não passa');
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
        $modal = resource_path('views/livewire/pos/partials/preco-modal.blade.php');
        $this->assertFileExists($modal);

        $html = file_get_contents($modal);
        $this->assertStringContainsString('$showPrecoModal', $html);
        $this->assertStringContainsString('x-moeda-input', $html, 'o preço usa a máscara de dinheiro da casa');
        $this->assertStringContainsString('confirmarPrecoNoPos', $html);
        $this->assertStringContainsString('fecharPrecoModal', $html, 'cancelar é a forma de desistir');
        $this->assertStringContainsString('$precoModalNome', $html, 'o artigo aparece por inteiro: os nomes são longos');

        $ecra = file_get_contents(resource_path('views/livewire/pos/possystem.blade.php'));
        $this->assertStringContainsString("livewire.pos.partials.preco-modal", $ecra, 'o modal tem de estar no ecrã');

        // A caixa do navegador saiu de vez.
        $js = file_get_contents(resource_path('views/livewire/pos/partials/scripts.blade.php'));
        $this->assertStringNotContainsString('pos-perguntar-preco', $js);
    }

    /** @test */
    public function o_modal_devolve_o_preco_sem_perder_a_confirmacao_do_psicotropico(): void
    {
        $pos = file_get_contents(app_path('Livewire/POS/POSSystem.php'));

        $this->assertStringContainsString('public function confirmarPrecoNoPos', $pos);
        $this->assertStringContainsString('MoneyHelper::parse(', $pos,
            'o valor escrito passa pelo mesmo leitor de dinheiro do resto do sistema');
        $this->assertStringContainsString('public function confirmarPrecoNoPos($escrito = null)', $pos,
            'o valor viaja COM o submeter: em viagem própria chegava depois de o artigo já ter entrado');
        $this->assertStringContainsString('$this->addToCart($id, $controlado, $valor)', $pos,
            'a confirmação do psicotrópico volta com o preço');
        $this->assertStringContainsString("addError('precoModalValor'", $pos, 'negativo não passa');
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
        $pos = file_get_contents(app_path('Livewire/POS/POSSystem.php'));

        $this->assertStringContainsString('$precoVemDaFicha = ! $product->preco_no_pos;', $pos,
            'a sincronização tem de saber quais preços não lhe pertencem');

        $sincronizacao = substr($pos, strpos($pos, '$precoVemDaFicha'), 600);

        $this->assertStringContainsString('round((float) $item->price, 2)', $sincronizacao,
            'num artigo de preço perguntado, o preço canónico é o que está na linha');

        // O imposto não fica de fora da sincronização.
        $this->assertStringContainsString('tax_rate', $sincronizacao);
        $this->assertStringContainsString('tax_type', $sincronizacao);
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
