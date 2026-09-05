<?php

namespace Tests\Feature\Pwa;

use Tests\TenantTestCase;

/**
 * O manifesto do PWA — a configuração que se estraga em silêncio.
 *
 * Nada aqui rebenta um ecrã. O que acontece quando isto está errado é pior:
 * o ícone sai cortado no telemóvel de quem instalou, a barra de estado muda
 * de cor entre a aplicação e o navegador, ou uma actualização do `start_url`
 * cria uma segunda aplicação e deixa a primeira órfã. Ninguém abre um bilhete
 * por isso — simplesmente fica com ar de coisa mal feita.
 *
 * Os defeitos que estes ensaios travam, todos encontrados de uma vez:
 *
 *   · TODOS os ícones declarados `any maskable` sem terem margem nenhuma;
 *   · os atalhos a apontar para `icon-96.png`, um ficheiro que não existe;
 *   · três cores de tema diferentes em circulação;
 *   · sem `id`, e portanto sem identidade estável.
 */
class ManifestoDoPwaTest extends TenantTestCase
{
    private function manifesto(): array
    {
        $resposta = $this->get('/manifest.webmanifest');
        $resposta->assertOk();

        return json_decode($resposta->getContent(), true);
    }

    /** @test */
    public function o_manifesto_tem_o_que_o_chrome_exige_para_instalar(): void
    {
        $m = $this->manifesto();

        foreach (['name', 'short_name', 'start_url', 'display', 'icons'] as $campo) {
            $this->assertArrayHasKey($campo, $m, "O manifesto não tem `{$campo}`.");
        }

        $this->assertSame('standalone', $m['display']);

        // O Chrome exige um 192 e um 512 para oferecer a instalação. Sem eles
        // não aparece o botão, e ninguém percebe porquê.
        $tamanhos = array_column($m['icons'], 'sizes');
        $this->assertContains('192x192', $tamanhos);
        $this->assertContains('512x512', $tamanhos);
    }

    /**
     * O ENSAIO DO ÍCONE CORTADO.
     *
     * `purpose: "any maskable"` é uma promessa ao sistema operativo: "este
     * desenho tem margem, podes recortá-lo". O crachá do SOS toca as quatro
     * bordas — o Android acreditava, aplicava a máscara do lançador, e o anel
     * azul saía cortado dos quatro lados.
     *
     * Um ícone é `any` OU `maskable`, nunca os dois, a menos que tenha mesmo
     * a zona segura respeitada — e nesse caso é outro ficheiro.
     *
     * @test
     */
    public function nenhum_icone_promete_ser_mascaravel_e_inteiro_ao_mesmo_tempo(): void
    {
        $m = $this->manifesto();

        foreach ($m['icons'] as $icone) {
            $this->assertNotSame(
                'any maskable',
                $icone['purpose'] ?? '',
                "O ícone {$icone['src']} promete margem de segurança que o desenho não tem — "
                . 'o Android vai recortá-lo.'
            );
        }

        $mascaraveis = array_filter($m['icons'], fn ($i) => ($i['purpose'] ?? '') === 'maskable');
        $inteiros = array_filter($m['icons'], fn ($i) => ($i['purpose'] ?? '') === 'any');

        $this->assertNotEmpty($mascaraveis, 'Sem ícones mascaráveis, o Android inventa um recorte.');
        $this->assertNotEmpty($inteiros, 'Sem ícones `any`, o separador do navegador fica sem ícone.');
    }

    /** @test */
    public function todos_os_icones_do_manifesto_existem_mesmo(): void
    {
        $m = $this->manifesto();

        $fontes = array_column($m['icons'], 'src');

        foreach ($m['shortcuts'] ?? [] as $atalho) {
            foreach ($atalho['icons'] ?? [] as $icone) {
                $fontes[] = $icone['src'];
            }
        }

        foreach (array_unique($fontes) as $fonte) {
            $caminho = parse_url($fonte, PHP_URL_PATH);

            // Um ícone em falta faz o Chrome recusar a instalação inteira. Os
            // atalhos apontavam para `icon-96.png` — um ficheiro que nunca
            // existiu, e um 404 que ninguém via.
            $this->get($caminho)->assertOk();
        }
    }

    /**
     * @test
     */
    public function a_aplicacao_tem_identidade_estavel(): void
    {
        $m = $this->manifesto();

        // Sem `id`, a aplicação instalada é identificada pelo `start_url`.
        // Mudar esse endereço faz nascer uma aplicação NOVA e a que estava no
        // ecrã do operador fica órfã, a apontar para uma versão que já não
        // recebe actualizações.
        $this->assertArrayHasKey('id', $m, 'O manifesto não tem `id` — a identidade fica presa ao start_url.');
        $this->assertNotSame($m['start_url'], $m['id'], 'O `id` não pode ser o `start_url`: era o mesmo problema com outro nome.');
    }

    /** @test */
    public function a_cor_de_tema_e_uma_so_em_todo_o_produto(): void
    {
        $m = $this->manifesto();
        $cor = $m['theme_color'];

        // Havia três cores em circulação. Não parte nada — mas a barra de
        // estado mudava de azul entre abrir a aplicação instalada e abrir a
        // mesma página no navegador, e pareciam dois produtos.
        $entrada = $this->get('/invoicing/offline/login');
        $entrada->assertOk();
        $entrada->assertSee('<meta name="theme-color" content="' . $cor . '">', false);
    }

    /** @test */
    public function a_entrada_do_pwa_traz_o_que_o_ios_precisa(): void
    {
        $resposta = $this->get('/invoicing/offline/login');

        $resposta->assertOk();

        // O iOS NÃO lê o manifesto. Sem estas, quem adiciona ao ecrã principal
        // num iPhone fica com uma miniatura da página por ícone e a aplicação
        // abre dentro do Safari, com a barra de endereço a comer o topo.
        $resposta->assertSee('apple-touch-icon', false);
        $resposta->assertSee('apple-mobile-web-app-capable', false);
    }

    /** @test */
    public function o_icone_mascaravel_deixa_margem_para_a_mascara(): void
    {
        $resposta = $this->get('/pwa/icon-maskable-512.png');
        $resposta->assertOk();

        // A rota devolve um BinaryFileResponse: o corpo está no ficheiro, não
        // no `getContent()` — que vem vazio e fazia isto falhar por uma razão
        // que não tem nada a ver com o ícone.
        $bruto = $resposta->baseResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            ? file_get_contents($resposta->baseResponse->getFile()->getPathname())
            : $resposta->getContent();

        $imagem = imagecreatefromstring($bruto);
        $this->assertNotFalse($imagem, 'O ícone mascarável não é uma imagem legível.');

        $lado = imagesx($imagem);
        $minX = $lado;
        $maxX = 0;

        for ($y = 0; $y < $lado; $y += 4) {
            for ($x = 0; $x < $lado; $x += 4) {
                $c = imagecolorat($imagem, $x, $y);
                [$r, $g, $b] = [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];

                if (!($r > 245 && $g > 245 && $b > 245)) {
                    $minX = min($minX, $x);
                    $maxX = max($maxX, $x);
                }
            }
        }

        imagedestroy($imagem);

        // A zona segura é um círculo com 80% do lado — raio de 40%. O desenho
        // tem de caber lá dentro, senão a máscara come-lhe as pontas.
        $raioDoDesenho = ($maxX - $minX) / 2;
        $raioSeguro = $lado * 0.40;

        $this->assertLessThanOrEqual(
            $raioSeguro,
            $raioDoDesenho,
            sprintf(
                'O desenho tem raio %.0fpx e a zona segura da máscara é %.0fpx — o Android vai cortá-lo.',
                $raioDoDesenho,
                $raioSeguro
            )
        );
    }
}
