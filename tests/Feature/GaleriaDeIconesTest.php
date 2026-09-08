<?php

namespace Tests\Feature;

use App\Support\GaleriaDeIcones;
use Tests\TestCase;

/**
 * A GALERIA DE ÍCONES.
 *
 * Os formulários pediam o código à mão: escrevia-se `fa-money-bill` numa
 * caixa de texto e esperava-se pelo melhor. Um código mal escrito NÃO DÁ ERRO
 * NENHUM — dá um quadrado vazio na lista, e só se descobre depois de gravado.
 *
 * O que este ensaio guarda:
 *
 * 1. **TODO O ÍCONE DA GALERIA TEM DESENHO.** É a razão de ela existir. Um
 *    ícone do plano pago, de outra família ou com o nome trocado passa por
 *    aqui sem se notar e chega ao utilizador como um quadrado vazio — o
 *    mesmo defeito que a galeria veio resolver, agora escolhido de uma lista.
 *
 * 2. **UMA LISTA SÓ.** Havia quatro no projecto: a do selector Blade que
 *    ninguém usava, e três em caixas de texto com exemplos diferentes.
 *    Quatro listas do mesmo assunto divergem à primeira adição.
 */
class GaleriaDeIconesTest extends TestCase
{
    /**
     * TODO O ÍCONE DA GALERIA EXISTE NO FONT AWESOME QUE ESTÁ INSTALADO.
     *
     * Lê-se o CSS que o browser recebe — `public/vendor/css/...` — e não uma
     * lista de referência: o que interessa é o que o utilizador tem, não o
     * que o Font Awesome publica.
     *
     * @test
     */
    public function todos_os_icones_da_galeria_tem_desenho(): void
    {
        $folha = public_path('vendor/css/fontawesome.min.css');

        $this->assertFileExists($folha, 'o Font Awesome local é que manda aqui');

        $css = file_get_contents($folha);

        $semDesenho = array_values(array_filter(
            GaleriaDeIcones::todos(),
            fn (string $icone) => ! str_contains($css, ".{$icone}:before"),
        ));

        $this->assertSame([], $semDesenho,
            'estes ícones não existem no Font Awesome instalado e sairiam como um quadrado vazio: ' . implode(', ', $semDesenho));
    }

    /** @test */
    public function a_galeria_nao_repete_nem_vem_vazia(): void
    {
        $todos = GaleriaDeIcones::todos();

        $this->assertGreaterThan(80, count($todos), 'uma galeria pequena obriga a voltar ao código à mão');
        $this->assertSame(count($todos), count(array_unique($todos)), 'o mesmo ícone em dois grupos confunde quem procura');
    }

    /**
     * CADA ÍCONE TEM NOME, e é por ele que se procura.
     *
     * Quem quer um carrinho escreve «carrinho», não `cart-shopping`. Um
     * ícone sem nome é um ícone que só se encontra por acaso.
     *
     * @test
     */
    public function cada_icone_tem_nome_e_cada_grupo_tem_icones(): void
    {
        $grupos = GaleriaDeIcones::grupos();

        $this->assertNotEmpty($grupos);

        foreach ($grupos as $g) {
            $this->assertNotEmpty($g['nome'], 'um grupo sem título não se lê');
            $this->assertNotEmpty($g['icones'], "o grupo «{$g['nome']}» está vazio");

            foreach ($g['icones'] as $i) {
                $this->assertStringStartsWith('fa-', $i['codigo'], 'o código vai completo, para o ecrã não o ter de montar');
                $this->assertNotEmpty($i['nome'], "«{$i['codigo']}» não tem nome por que se procure");
            }
        }
    }

    /**
     * OS FORMULÁRIOS DEIXARAM DE PEDIR O CÓDIGO À MÃO.
     *
     * Guarda contra a reincidência: os três em Blade passam pelo selector, e
     * os dos catálogos declaram o campo como `icone` — não como `texto`.
     *
     * @test
     */
    public function nenhum_formulario_pede_o_codigo_do_icone_escrito(): void
    {
        foreach ([
            'resources/views/livewire/salon/service-categories/partials/form-modal.blade.php',
            'resources/views/livewire/super-admin/modules/partials/form-modal.blade.php',
        ] as $caminho) {
            $blade = file_get_contents(base_path($caminho));

            $this->assertStringContainsString('<x-icon-picker', $blade,
                basename($caminho) . ': o ícone escolhe-se da galeria');
            $this->assertStringNotContainsString('wire:model.live="icon" type="text"', $blade,
                basename($caminho) . ': voltou a pedir o código escrito');
        }

        // E nos catálogos em React, o campo é do tipo que abre a galeria.
        $esquemas = file_get_contents(app_path('Services/Invoicing/Catalogos.php'));

        $this->assertStringNotContainsString("campo('icon', 'Ícone', 'texto'", $esquemas,
            'um campo de ícone em texto é uma caixa onde se escreve um código');
    }
}
