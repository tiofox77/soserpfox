<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * O ícone do separador.
 *
 * Duas coisas estavam mal. A primeira: o que aparecia no separador era o
 * logótipo inteiro — 822x412, emblema à esquerda e "SOS ERP" à direita —
 * espremido num quadrado de 16px. Nas Definições do Sistema os campos do
 * logótipo e do favicon são dois, mas foi carregado o mesmo ficheiro nos
 * dois, e ninguém reparou porque a 16px tudo parece uma mancha.
 *
 * A segunda: havia seis maneiras diferentes de declarar os ícones espalhadas
 * pelos layouts, e mais de dez páginas com <head> próprio não declaravam
 * nenhum — os convites, o check-in do hotel, a empresa desactivada, a
 * subscrição expirada, o 503, a página offline.
 *
 * E a página de erro: ao dar-lhe os ícones parti-a, e o 404 passou a 500 em
 * produção. O comentário que escrevi por cima dela era um comentário de HTML,
 * dentro do qual o Blade continua a compilar — a palavra "@include" escrita a
 * meio de uma frase virou uma chamada a make() sem argumentos. Daí o teste
 * que garante que ela renderiza.
 */
class IconesDoSiteTest extends TestCase
{
    use DatabaseTransactions;

    /** Um logótipo largo não é um ícone, esteja no campo que estiver. */
    public function test_um_logotipo_largo_nao_e_aceite_como_favicon(): void
    {
        $largo = 'settings/teste-logo-largo.png';
        $caminho = storage_path('app/public/' . $largo);
        @mkdir(dirname($caminho), 0755, true);

        $im = imagecreatetruecolor(822, 412);
        imagepng($im, $caminho);
        imagedestroy($im);

        SystemSetting::set('app_favicon', $largo);

        try {
            $this->assertStringContainsString('favicon.ico', app_favicon());
        } finally {
            @unlink($caminho);
        }
    }

    /** Um ícone quadrado carregado à mão continua a valer. */
    public function test_um_icone_quadrado_e_respeitado(): void
    {
        $quadrado = 'settings/teste-icone-quadrado.png';
        $caminho = storage_path('app/public/' . $quadrado);
        @mkdir(dirname($caminho), 0755, true);

        $im = imagecreatetruecolor(192, 192);
        imagepng($im, $caminho);
        imagedestroy($im);

        SystemSetting::set('app_favicon', $quadrado);

        try {
            $this->assertStringContainsString($quadrado, app_favicon());
        } finally {
            @unlink($caminho);
        }
    }

    public function test_sem_favicon_configurado_usa_o_do_site(): void
    {
        SystemSetting::set('app_favicon', null);

        $this->assertStringContainsString('favicon.ico', app_favicon());
    }

    /** Os ficheiros têm de existir — o bloco partilhado aponta para todos. */
    public function test_os_ficheiros_dos_icones_existem(): void
    {
        foreach ([
            'favicon.ico',
            'apple-touch-icon.png',
            'brand/favicon-16x16.png',
            'brand/favicon-32x32.png',
            'brand/favicon-48x48.png',
            'brand/soserp-icone-192.png',
            'brand/soserp-icone-512.png',
        ] as $ficheiro) {
            $this->assertFileExists(public_path($ficheiro), "Falta {$ficheiro}");
        }

        foreach ([72, 96, 128, 144, 152, 192, 384, 512] as $lado) {
            $this->assertFileExists(
                public_path("pwa/default/icon-{$lado}x{$lado}.png"),
                "Falta o ícone PWA de {$lado}px — é daqui que o PwaController serve."
            );
        }
    }

    /** Os pequenos têm de ser quadrados e do tamanho que dizem ser. */
    public function test_os_favicons_tem_o_tamanho_que_anunciam(): void
    {
        foreach ([16, 32, 48] as $lado) {
            $medidas = getimagesize(public_path("brand/favicon-{$lado}x{$lado}.png"));

            $this->assertSame($lado, $medidas[0]);
            $this->assertSame($lado, $medidas[1]);
        }
    }

    public function test_as_paginas_publicas_declaram_os_icones(): void
    {
        foreach (['/', '/login', '/register'] as $rota) {
            $html = $this->get($rota)->assertOk()->getContent();

            $this->assertStringContainsString('brand/favicon-32x32.png', $html, "Sem ícones em {$rota}");
            $this->assertStringContainsString('apple-touch-icon.png', $html, "Sem apple-touch-icon em {$rota}");
        }
    }

    /**
     * A página de erro renderiza — e com ícones.
     *
     * Sem este teste, o que se descobre é em produção, com o 404 a devolver
     * 500 a toda a gente que apanhe um link partido.
     */
    public function test_a_pagina_de_erro_renderiza_com_icones(): void
    {
        $html = view('errors.minimal', [
            'exception' => new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('teste'),
        ])->render();

        $this->assertStringContainsString('brand/favicon-32x32.png', $html);
        $this->assertStringContainsString('apple-touch-icon.png', $html);
    }

    public function test_uma_rota_que_nao_existe_devolve_404(): void
    {
        $this->get('/isto-nao-existe-de-todo-' . uniqid())->assertNotFound();
    }
}
