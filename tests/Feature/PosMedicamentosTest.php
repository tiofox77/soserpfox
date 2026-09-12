<?php

namespace Tests\Feature;

use App\Models\Product;
use Tests\TenantTestCase;

/**
 * O que o balcão tem de saber ANTES de fechar a venda.
 *
 * Depois de emitida a factura, o artigo já saiu da farmácia: um aviso que só
 * aparece no fim não serve para nada. Por isso a receita avisa e o psicotrópico
 * pergunta — e perguntam no momento em que o artigo entra no carrinho.
 *
 * O BALCÃO É HOJE REACT, e as duas regras tinham-se perdido: a API nem sequer
 * dizia ao ecrã que um artigo exige receita ou é de venda controlada. O ensaio
 * que as guardava apontava para um componente Livewire que já nenhuma rota
 * serve, e por isso nunca deu por nada.
 *
 * O que se verifica aqui é o que o SERVIDOR diz — e que o ecrã tem os dois
 * caminhos, porque a decisão de perguntar é dele.
 */
class PosMedicamentosTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/pos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.pos.access', 'invoicing.pos.view')
            ->comModulo('invoicing');

        \App\Models\Invoicing\PosShift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'shift_number' => 'T'.strtoupper(substr(uniqid(), -8)),
            'opened_at' => now(),
            'status' => 'open',
        ]);

        \Illuminate\Support\Facades\Cache::flush();
    }

    private function medicamento(array $campos): Product
    {
        $produto = $this->produtoComStock(10);
        $produto->update($campos);

        return $produto->fresh();
    }

    /** A linha do artigo, tal como o ecrã a recebe. */
    private function linhaDe(Product $p, string $procura = ''): ?array
    {
        $q = $procura === '' ? '' : '?procura='.urlencode($procura);

        return collect(
            $this->actingAs($this->user)->getJson(self::RAIZ.'/artigos'.$q)->assertOk()->json('data')
        )->firstWhere('id', $p->id);
    }

    public function test_o_servidor_diz_que_o_artigo_exige_receita(): void
    {
        $p = $this->medicamento(['requires_prescription' => true]);

        $linha = $this->linhaDe($p);

        $this->assertNotNull($linha, 'o artigo com receita continua a vender-se — só avisa');
        $this->assertTrue($linha['receita']);
        $this->assertFalse($linha['controlado']);
    }

    public function test_o_servidor_diz_que_o_artigo_e_de_venda_controlada(): void
    {
        $p = $this->medicamento(['is_controlled' => true]);

        $linha = $this->linhaDe($p);

        $this->assertNotNull($linha);
        $this->assertTrue($linha['controlado']);
    }

    /**
     * A RECEITA AVISA, NÃO TRAVA — e o CONTROLADO PERGUNTA.
     *
     * É o ecrã que decide qual dos dois caminhos segue; é lá que se verifica,
     * porque um `receita` que o ecrã ignorasse era um campo a fingir.
     */
    public function test_o_ecra_avisa_da_receita_e_pergunta_pelo_controlado(): void
    {
        $fonte = file_get_contents(resource_path('js/ecras/facturacao/pos/PontoDeVenda.tsx'));

        // A receita entra no carrinho e deixa um aviso.
        $this->assertStringContainsString('RECEITA MÉDICA', $fonte);

        // O controlado abre uma pergunta ANTES de `juntar`.
        $this->assertStringContainsString('porAConfirmarControlado(a)', $fonte);
        $this->assertMatchesRegularExpression(
            '/if \(a\.controlado && !\(a\.stock !== null && a\.stock < 1\)\)/',
            $fonte,
            'um controlado esgotado não chega a perguntar: não há o que vender',
        );
    }

    /** A grelha continua a esconder o que não tem stock, controlado ou não. */
    public function test_um_controlado_esgotado_nem_aparece(): void
    {
        $p = $this->produtoComStock(0);
        $p->update(['is_controlled' => true]);

        $this->assertNull($this->linhaDe($p->fresh()),
            'sem stock não se mostra — e por isso nem chega a perguntar');
    }

    public function test_procurar_pela_substancia_activa_encontra_o_medicamento(): void
    {
        // A pergunta que a farmácia faz todos os dias: quem pede «paracetamol»
        // não sabe o nome comercial da caixa.
        $p = $this->medicamento(['active_ingredient' => 'Paracetamol']);

        $this->assertNotNull($this->linhaDe($p, 'paracet'));
    }

    public function test_procurar_pelo_tamanho_encontra_a_peca_de_roupa(): void
    {
        /*
         * Nome, código e SKU sem algarismos de propósito. O `produtoComStock()`
         * gera-os com `uniqid()`, que é hexadecimal — um «38» ao calhar no meio
         * fazia este ensaio passar pela procura no nome, mesmo com a procura por
         * tamanho desligada. Um ensaio que passa com a funcionalidade removida
         * não guarda nada.
         */
        $p = $this->medicamento([
            'name' => 'Camisa de linho',
            'code' => 'CAMISALINHO',
            'sku' => 'CAMISALINHO',
            'size' => '38',
        ]);

        $this->assertNotNull($this->linhaDe($p, '38'));
    }
}
