<?php

namespace Tests\Feature\Pwa;

use App\Http\Controllers\PwaController;
use Illuminate\Support\Facades\Cache;
use Tests\TenantTestCase;

/**
 * O número de versão do PWA tem de responder a uma pergunta só: «isto mudou
 * desde ontem?».
 *
 * O DEFEITO QUE ISTO FECHA: o cabeçalho mostrava `v2026.08.19.1`, que vinha do
 * changelog e se actualiza à mão — num aparelho a correr código do dia 27.
 * Um número que alguém se pode esquecer de subir responde MAL, e é pior do que
 * não ter número nenhum: dá confiança onde não devia.
 */
class VersaoDoPwaTest extends TenantTestCase
{
    private string $ficheiro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ficheiro = storage_path('app/pwa-versao.json');
        $this->esquecer();
    }

    private function esquecer(): void
    {
        Cache::forget('pwa.versao.numero');
        Cache::forget('pwa.version');
        Cache::forget('pwa.version.label');
    }

    private function pwa(): PwaController
    {
        return app(PwaController::class);
    }

    /** Formato SÉRIE.BUILD — três números, legível de relance. */
    public function test_a_versao_tem_serie_e_build(): void
    {
        config(['pwa.serie' => '2.0']);

        $versao = $this->pwa()->numeroDeVersao();

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $versao, "Saiu '{$versao}'.");
        $this->assertStringStartsWith('2.0.', $versao);
    }

    /** Ler duas vezes sem mexer em nada não pode fazer o número subir. */
    public function test_ler_duas_vezes_nao_sobe_o_numero(): void
    {
        $primeira = $this->pwa()->numeroDeVersao();
        $this->esquecer();
        $segunda = $this->pwa()->numeroDeVersao();

        $this->assertSame($primeira, $segunda, 'O número só sobe quando o PWA muda.');
    }

    /**
     * A PROVA: mexer num ficheiro do PWA faz o número subir exactamente uma vez.
     */
    public function test_mexer_no_motor_sobe_o_numero_uma_vez(): void
    {
        $motor = public_path('js/pwa-invoicing.js');
        $this->assertFileExists($motor);

        $antes = $this->pwa()->numeroDeVersao();

        // Uma alteração ao motor, como num deploy.
        touch($motor, time() + 5);
        $this->esquecer();

        $depois = $this->pwa()->numeroDeVersao();

        $this->assertNotSame($antes, $depois, 'Mexer no motor tem de subir o número.');
        $this->assertSame(
            $this->numeroDoBuild($antes) + 1,
            $this->numeroDoBuild($depois),
            'Tem de subir de um, não de mais.'
        );

        // E não volta a subir sem mais nenhuma alteração.
        $this->esquecer();
        $this->assertSame($depois, $this->pwa()->numeroDeVersao());
    }

    /** A série vem da configuração; o build continua de onde estava. */
    public function test_subir_a_serie_nao_reinicia_o_build(): void
    {
        config(['pwa.serie' => '2.0']);
        $antes = $this->pwa()->numeroDeVersao();

        config(['pwa.serie' => '3.0']);
        $this->esquecer();
        $depois = $this->pwa()->numeroDeVersao();

        $this->assertStringStartsWith('3.0.', $depois);
        $this->assertSame(
            $this->numeroDoBuild($antes),
            $this->numeroDoBuild($depois),
            'Mudar de série não reinicia a contagem.'
        );
    }

    /** Sem o ficheiro de contagem, recomeça — mas nunca em zero. */
    public function test_sem_ficheiro_recomeca_em_um(): void
    {
        @unlink($this->ficheiro);
        $this->esquecer();

        $this->assertSame(1, $this->numeroDoBuild($this->pwa()->numeroDeVersao()));
    }

    /** Um ficheiro de contagem corrompido não pode partir o cabeçalho. */
    public function test_ficheiro_corrompido_nao_parte_nada(): void
    {
        file_put_contents($this->ficheiro, 'isto não é json {{{');
        $this->esquecer();

        $versao = $this->pwa()->numeroDeVersao();

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $versao);
    }

    /** A data ao lado continua a ser a da última alteração real. */
    public function test_a_data_acompanha_a_ultima_alteracao(): void
    {
        $etiqueta = $this->pwa()->buildLabel();

        $this->assertMatchesRegularExpression(
            '#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$#',
            $etiqueta,
            "A etiqueta saiu '{$etiqueta}'."
        );
    }

    private function numeroDoBuild(string $versao): int
    {
        return (int) (explode('.', $versao)[2] ?? 0);
    }
}
