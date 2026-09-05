<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Nenhum `route('nome')` aponta para um nome que não existe.
 *
 * É uma classe de defeito que NÃO se vê a desenvolver e cai em produção como
 * um 500 completo, quando alguém chega àquela linha. Aconteceu no ecrã inicial:
 * um aviso de facturas a vencer apontava para `invoicing.invoices` — nome que
 * nunca existiu (a rota é `invoicing.sales.invoices`) — e o /home inteiro
 * deixou de abrir para quem tinha facturas quase vencidas.
 *
 * O ensaio varre o código à procura de nomes LITERAIS e confirma que existem.
 *
 * A DÍVIDA CONHECIDA está listada abaixo, com o motivo. Não está lá para se
 * ignorar o problema: está para que um nome morto NOVO falhe já, em vez de se
 * perder no meio dos antigos. Quem corrigir um, tira-o da lista.
 */
class RotasQueNaoExistemTest extends TestCase
{
    /**
     * Nomes que hoje não existem e ainda não foram tratados.
     *
     * Cada um destes é um 500 à espera de alguém lhe tocar.
     */
    private const DIVIDA = [
        // Só existem na build on-premise (`config('licensing.enforce')`), e na
        // cloud as rotas nem se registam. Não são defeito — são condicionais.
        'licenca.index', 'licenca.verificar', 'licenca.solicitar',
        'licenca.sincronizar', 'licenca.guardar',

        // Falsos positivos: `Notification::route('mail', …)` e `->route('tab')`
        // não são rotas de URL, é o mesmo nome de método noutro contexto.
        'mail', 'tab',

        // Dentro de um comentário `// TODO:` — não é uma chamada, é uma nota.
        'invoicing.imports.pdf',

        // A verificação de email está desligada (`Auth::routes()` não a inclui),
        // por isso a rota não se regista e a vista nunca é servida. A chamada
        // está protegida por `Route::has()` — este varrimento lê o ficheiro e
        // não sabe ver a guarda, mas em execução não rebenta.
        'verification.resend',
    ];

    /** @test */
    public function nenhuma_chamada_a_route_aponta_para_um_nome_inexistente(): void
    {
        $mortas = [];

        foreach (['app', 'resources/views', 'routes'] as $pasta) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($pasta))
            );

            foreach ($it as $ficheiro) {
                if (! $ficheiro->isFile() || ! str_ends_with($ficheiro->getFilename(), '.php')) {
                    continue;
                }

                $conteudo = file_get_contents($ficheiro->getPathname());

                // Só nomes literais — os montados em variáveis não se verificam.
                if (! preg_match_all("/\broute\(\s*'([a-zA-Z0-9_.\-]+)'/", $conteudo, $m)) {
                    continue;
                }

                foreach ($m[1] as $nome) {
                    if (Route::has($nome) || in_array($nome, self::DIVIDA, true)) {
                        continue;
                    }

                    $relativo = str_replace(base_path().DIRECTORY_SEPARATOR, '', $ficheiro->getPathname());
                    $mortas[] = "route('{$nome}')  em  {$relativo}";
                }
            }
        }

        $this->assertSame([], array_values(array_unique($mortas)),
            "Estes nomes de rota não existem — cada um é um 500 quando lá se chegar:\n  "
            .implode("\n  ", array_unique($mortas)));
    }

    /**
     * A dívida não cresce à socapa.
     *
     * Se alguém acrescentar um nome à lista sem o corrigir, isto obriga a que
     * seja uma decisão consciente e não um atalho.
     */
    public function test_a_lista_de_divida_nao_engorda(): void
    {
        $this->assertLessThanOrEqual(9, count(self::DIVIDA),
            'a dívida de rotas mortas devia diminuir, não aumentar');
    }

    /** O nome que derrubou o /home continua a existir. */
    public function test_a_rota_das_facturas_de_venda_existe(): void
    {
        $this->assertTrue(Route::has('invoicing.sales.invoices'));
        $this->assertFalse(Route::has('invoicing.invoices'),
            'se este nome passar a existir, tirar a substituição do Notifications');
    }
}
