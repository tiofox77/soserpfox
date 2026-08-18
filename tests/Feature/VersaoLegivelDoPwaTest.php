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
        $c = new PwaController;

        $antes = [$c->buildLabel(), $c->buildVersion()];

        touch(resource_path('pwa/sw.js'));
        Cache::forget('pwa.version');
        Cache::forget('pwa.version.label');

        $depois = [(new PwaController)->buildLabel(), (new PwaController)->buildVersion()];

        $this->assertNotSame($antes[1], $depois[1], 'a assinatura tinha de mudar');
        $this->assertNotEmpty($depois[0]);
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
        $html = view('layouts.pwa', ['title' => 'x'])->render();

        $this->assertStringContainsString('v' . config('changelog.current', '1.0'), $html);
        $this->assertStringContainsString((new PwaController)->buildLabel(), $html);
    }
}
