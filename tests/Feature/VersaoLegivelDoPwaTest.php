<?php

namespace Tests\Feature;

use App\Http\Controllers\PwaController;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A versão que aparece no cabeçalho do PWA.
 *
 * Estava lá "b0243ac3a1": correcto e ilegível. Quem olha para o cabeçalho faz
 * uma pergunta só — já tenho a correcção de hoje? — e um md5 não responde.
 */
class VersaoLegivelDoPwaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('pwa.version');
        Cache::forget('pwa.version.label');
    }

    public function test_a_versao_legivel_e_uma_data_e_hora(): void
    {
        $label = (new PwaController)->buildLabel();

        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', $label);
    }

    public function test_as_duas_versoes_saem_da_mesma_lista(): void
    {
        // Se discordarem, o service worker actualiza-se e o cabeçalho continua
        // a dizer a data antiga — ou o contrário, que é pior.
        //
        // Isto simulava um deploy com `touch()` no sw.js: mudava a DATA. A
        // assinatura passou a sair dos BYTES — precisamente porque a data
        // falhou em produção — e o ensaio ficou a provar o mecanismo errado.
        // A propriedade que interessa nunca foi «mexer faz mudar»: é as duas
        // versões saírem da MESMA lista, e é isso que se confere agora.
        $c = new PwaController;

        $lista = $c->ficheirosVigiados();

        $this->assertNotEmpty($lista, 'sem lista, as duas versões ficam paradas para sempre');

        $this->assertContains(resource_path('pwa/sw.js'), $lista);
        $this->assertContains(resource_path('views/layouts/pwa.blade.php'), $lista);

        // E mudar a matéria da lista muda a assinatura.
        $ficheiro = tempnam(sys_get_temp_dir(), 'pwa');

        try {
            file_put_contents($ficheiro, 'antes');
            $antes = $c->assinaturaDe([$ficheiro]);

            file_put_contents($ficheiro, 'depois');

            $this->assertNotSame($antes, $c->assinaturaDe([$ficheiro]), 'a assinatura tinha de mudar');
        } finally {
            @unlink($ficheiro);
        }

        $this->assertNotEmpty($c->buildLabel());
    }

    public function test_o_cabecalho_mostra_a_data_e_guarda_o_resto_no_title(): void
    {
        $c = new PwaController;
        $html = view('layouts.pwa', ['title' => 'x'])->render();

        $this->assertStringContainsString($c->buildLabel(), $html);
        $this->assertStringContainsString($c->buildVersion(), $html, 'a assinatura fica no title, para comparar aparelhos');
    }

    public function test_a_versao_aparece_ao_lado_da_data(): void
    {
        // As duas dizem coisas diferentes: a versão é o que foi lançado, a
        // data é o que este aparelho tem. Quem reporta um problema precisa
        // de dar as duas.
        //
        // A versão passou a ser a DO PWA (2.0.4), que sobe sozinha a cada
        // alteração do próprio PWA, e já não a do changelog da aplicação
        // inteira — que subia com coisas que nada têm a ver com o aparelho e
        // por isso não respondia à pergunta "já tenho a correcção de hoje?".
        $html = view('layouts.pwa', ['title' => 'x'])->render();

        $this->assertStringContainsString('v'.(new PwaController)->numeroDeVersao(), $html);
        $this->assertStringContainsString((new PwaController)->buildLabel(), $html);
    }
}
