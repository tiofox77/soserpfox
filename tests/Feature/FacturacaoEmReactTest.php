<?php

namespace Tests\Feature;

use App\Support\EcraReact;
use Illuminate\Support\Facades\Route;
use ReflectionFunction;
use Spatie\Permission\Models\Permission;
use Tests\TenantTestCase;

/**
 * A FACTURAÇÃO É TODA REACT, E O SERVIDOR É SÓ API.
 *
 * Esta é a prova do que a migração diz ter feito, e fica aqui para o dia em
 * que alguém, com a melhor das intenções, acrescentar «só mais um ecrãzinho em
 * Livewire porque é rápido». Duas implementações da mesma regra fiscal foi
 * exactamente o que esta migração existiu para acabar.
 *
 * O que se guarda, por partes:
 *
 *   1. NÃO HÁ LIVEWIRE NA FACTURAÇÃO — nem classes, nem vistas, nem ninguém a
 *      invocá-las.
 *   2. TODAS as moradas de `/invoicing/…` que são ECRÃ servem React, e
 *      prova-se de duas maneiras: pela origem da acção da rota (todas) e
 *      abrindo a página a sério (as que não precisam de um documento).
 *   3. OS ECRÃS SÓ FALAM COM A API — nenhum `.tsx` conhece uma morada do
 *      Livewire.
 *   4. O QUE ESCREVE ESTÁ NA API, com permissão verificada em cada acção.
 */
class FacturacaoEmReactTest extends TenantTestCase
{
    /** Moradas que são descarga ou papel, não ecrã: saem do varrimento. */
    private const NAO_SAO_ECRA = '#(pdf|preview|talao|csv|descarregar|download|imprimir|export)#';

    /**
     * O QUE MORA EM `/invoicing/` E NÃO É DESTA MIGRAÇÃO.
     *
     * Não é esquecimento nem excepção de conveniência — são coisas que vivem
     * debaixo do mesmo prefixo por razão de endereço, e cada uma tem a sua
     * razão para continuar como está:
     *
     *   · `offline/…` é o PWA. Corre sem rede, com Alpine e Dexie, e é isso
     *     que o faz funcionar quando a loja fica sem internet. Passá-lo para
     *     React era outra migração, com outro risco.
     *   · `pos…` é o POS ao balcão (`App\Livewire\POS\POSSystem`), que ficou
     *     de fora por decisão: é o ecrã que não pode parar num dia de vendas.
     */
    private const FORA_DESTA_MIGRACAO = '#^invoicing/(offline|pos)#';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    /** @test */
    public function nao_ha_livewire_nenhum_na_facturacao(): void
    {
        $this->assertDirectoryDoesNotExist(app_path('Livewire/Invoicing'),
            'os componentes Livewire da facturação voltaram');

        $this->assertDirectoryDoesNotExist(resource_path('views/livewire/invoicing'),
            'as vistas Livewire da facturação voltaram');

        /*
         * E NINGUÉM OS INVOCA a partir do que sobrou.
         *
         * O nome `App\Livewire\Invoicing\…` continua a aparecer em COMENTÁRIOS
         * — «isto vivia dentro do Settings», «faz o que o ecrã de sempre
         * fazia» — e é bom que apareça: é a história que explica porque é que
         * o serviço tem a forma que tem. O que não pode existir é uma
         * invocação a sério, e é só isso que se persegue aqui.
         */
        $invocacoes = [
            "#@livewire\(['\"]invoicing#",
            '#<livewire:invoicing#',
            '#use\s+App\\\\Livewire\\\\Invoicing#',
            '#new\s+\\\\?App\\\\Livewire\\\\Invoicing#',
            '#App\\\\Livewire\\\\Invoicing\\\\[A-Za-z\\\\]+::class#',
            "#view\(['\"]livewire\.invoicing#",
        ];

        foreach ($this->ficheiros([resource_path('views'), app_path()], ['php']) as $ficheiro) {
            $fonte = file_get_contents($ficheiro);
            $nome = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $ficheiro);

            foreach ($invocacoes as $padrao) {
                $this->assertDoesNotMatchRegularExpression($padrao, $fonte,
                    "{$nome}: ainda invoca a facturação em Livewire");
            }
        }
    }

    /**
     * TODA A MORADA DE ECRÃ SERVE REACT.
     *
     * Pela ORIGEM da acção: as páginas em React nascem todas do
     * `EcraReact::pagina()`, e o closure que ele devolve tem morada conhecida.
     * Um ecrã novo em Livewire — ou um controlador a devolver uma vista —
     * cairia aqui sem precisar de o abrir.
     *
     * @test
     */
    public function todas_as_moradas_de_ecra_nascem_do_ecra_react(): void
    {
        $forasteiras = [];

        foreach ($this->moradasDeEcra() as $uri => $rota) {
            $accao = $rota->getAction('uses');

            if (! $accao instanceof \Closure) {
                $forasteiras[$uri] = is_string($accao) ? $accao : get_debug_type($accao);

                continue;
            }

            $onde = (new ReflectionFunction($accao))->getFileName();

            if ($onde !== (new \ReflectionClass(EcraReact::class))->getFileName()) {
                $forasteiras[$uri] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', (string) $onde);
            }
        }

        $this->assertSame([], $forasteiras,
            "estas moradas da facturação não servem um ecrã React:\n" . json_encode($forasteiras, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * E ABREM MESMO, com o ecrã montado e sem Livewire no miolo.
     *
     * A origem da acção prova o desenho; isto prova o funcionamento. Ficam de
     * fora as moradas que pedem um documento (`{id}`), que estão cobertas
     * pelos ensaios de abrir e editar.
     *
     * @test
     */
    public function as_moradas_sem_parametro_abrem_com_o_ecra_montado(): void
    {
        $this->comTodasAsPermissoesDaFacturacao();

        $abertas = 0;

        foreach ($this->moradasDeEcra() as $uri => $rota) {
            if (str_contains($uri, '{')) {
                continue;
            }

            $resposta = $this->actingAs($this->user)->get('/' . $uri);

            // Uma morada pode depender de coisas que a bancada de ensaio não
            // tem (um turno aberto, por exemplo) e redireccionar — o que não
            // se aceita é um erro.
            $this->assertLessThan(400, $resposta->getStatusCode(),
                "/{$uri} respondeu {$resposta->getStatusCode()}");

            if ($resposta->getStatusCode() !== 200) {
                continue;
            }

            $html = $resposta->getContent();

            $this->assertStringContainsString('data-ecra="facturacao/', $html,
                "/{$uri} abriu sem montar ecrã React nenhum");

            $abertas++;
        }

        // Se um dia o varrimento deixar de encontrar moradas, isto acusa em
        // vez de passar por vazio.
        $this->assertGreaterThan(40, $abertas, 'o varrimento das moradas encontrou pouca coisa');
    }

    /**
     * OS ECRÃS SÓ FALAM COM A API.
     *
     * O cliente de API (`resources/js/api/cliente.ts`) põe o prefixo, e cada
     * ficheiro de API só escreve o caminho. O que aqui se recusa é um ecrã a
     * conhecer uma morada do Livewire (`/livewire/update`) ou a inventar uma
     * segunda porta para o servidor.
     *
     * @test
     */
    public function os_ecras_so_falam_com_a_api(): void
    {
        foreach ($this->ficheiros([resource_path('js')], ['ts', 'tsx']) as $ficheiro) {
            $fonte = file_get_contents($ficheiro);
            $nome = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $ficheiro);

            $this->assertStringNotContainsString('/livewire/', $fonte,
                "{$nome}: um ecrã em React não fala com o Livewire");

            /*
             * `fetch()` à mão salta o cliente da API — e com ele o token, os
             * erros tratados e o 419 da sessão morta.
             *
             * A expressão exige que o `fetch` não venha colado a nada: sem
             * isso, o `refetch()` do react-query — que é precisamente a forma
             * certa de voltar a pedir — dava falta.
             */
            $forasteiro = preg_match('/(?<![\w.])fetch\s*\(/', $fonte)
                && ! str_ends_with($nome, 'api' . DIRECTORY_SEPARATOR . 'cliente.ts')
                && ! str_ends_with($nome, 'i18n.ts');

            $this->assertFalse($forasteiro, "{$nome}: usa fetch() directo em vez do cliente da API");
        }
    }

    /**
     * A API COBRE O MÓDULO E VERIFICA PERMISSÃO EM CADA CONTROLADOR.
     *
     * Um controlador sem verificação é uma porta aberta: as rotas da API estão
     * atrás de `api.token` e `subscription`, que dizem QUEM é e se a empresa
     * está em dia — mas não o que essa pessoa pode fazer.
     *
     * @test
     */
    public function a_api_da_facturacao_verifica_permissao(): void
    {
        $controladores = glob(app_path('Http/Controllers/Api/Invoicing/*ApiController.php'));

        $this->assertGreaterThan(20, count($controladores), 'a API da facturação encolheu');

        foreach ($controladores as $ficheiro) {
            $fonte = file_get_contents($ficheiro);

            $this->assertMatchesRegularExpression(
                '/(exigir|authorize|can\(|abort_unless)/',
                $fonte,
                basename($ficheiro) . ': não verifica permissão nenhuma'
            );
        }
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    /** @return array<string,\Illuminate\Routing\Route> as moradas de ECRÃ da facturação */
    private function moradasDeEcra(): array
    {
        $moradas = [];

        foreach (Route::getRoutes() as $rota) {
            $uri = $rota->uri();

            if (! str_starts_with($uri, 'invoicing') || ! in_array('GET', $rota->methods(), true)) {
                continue;
            }

            if (preg_match(self::NAO_SAO_ECRA, $uri) || preg_match(self::FORA_DESTA_MIGRACAO, $uri)) {
                continue;
            }

            $moradas[$uri] = $rota;
        }

        return $moradas;
    }

    /** Dá ao utilizador todas as permissões que as rotas da facturação exigem. */
    private function comTodasAsPermissoesDaFacturacao(): void
    {
        $nomes = [];

        foreach ($this->moradasDeEcra() as $rota) {
            foreach ($rota->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                    foreach (explode('|', substr($middleware, 11)) as $permissao) {
                        $nomes[trim($permissao)] = true;
                    }
                }
            }
        }

        foreach (array_keys($nomes) as $nome) {
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }

        $this->comPermissoes(...array_keys($nomes));
    }

    /** @return list<string> */
    private function ficheiros(array $raizes, array $extensoes): array
    {
        $encontrados = [];

        foreach ($raizes as $raiz) {
            if (! is_dir($raiz)) {
                continue;
            }

            $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz));

            foreach ($iterador as $ficheiro) {
                if ($ficheiro->isFile() && in_array($ficheiro->getExtension(), $extensoes, true)) {
                    $encontrados[] = $ficheiro->getPathname();
                }
            }
        }

        return $encontrados;
    }
}
