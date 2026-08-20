<?php

namespace Tests\Feature;

use App\Models\ErroDoSistema;
use App\Services\Agent\NotificarOpenClaw;
use App\Services\Agent\RegistoDeErros;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TenantTestCase;

/**
 * O log passa a falar.
 *
 * O ficheiro de log tem 2000 linhas das quais 1972 são INFO de rotina: um erro
 * a sério afoga-se lá dentro e ninguém o vê. Isto agrupa por PROBLEMA — uma
 * linha com um contador — para o agente externo poder avisar uma vez e não mil.
 *
 * O que estes testes protegem, por ordem de importância:
 *   1. segredos nunca saem de casa — isto é servido por uma API a um agente
 *      externo;
 *   2. o mesmo problema é UMA linha, senão o agente manda uma mensagem por
 *      cliente afectado;
 *   3. nada disto rebenta nem recorre — corre dentro do tratamento de erros.
 */
class RegistoDeErrosTest extends TenantTestCase
{
    private RegistoDeErros $registo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registo = app(RegistoDeErros::class);
        config(['agent.erros.capturar' => true]);
    }

    // ── agrupamento ──────────────────────────────────────────────────────────

    public function test_o_mesmo_problema_com_ids_diferentes_e_uma_so_linha(): void
    {
        foreach ([123, 456, 789] as $id) {
            $this->registo->registar('error', "Utilizador {$id} não encontrado", [], 'app/X.php', 10);
        }

        $this->assertSame(1, ErroDoSistema::count(), 'os números são o que varia entre ocorrências do mesmo problema');
        $this->assertSame(3, (int) ErroDoSistema::first()->ocorrencias);
    }

    public function test_problemas_diferentes_ficam_em_linhas_diferentes(): void
    {
        $this->registo->registar('error', 'A base não respondeu', [], 'app/A.php', 1);
        $this->registo->registar('error', 'O ficheiro não existe', [], 'app/B.php', 2);

        $this->assertSame(2, ErroDoSistema::count());
    }

    public function test_a_mesma_mensagem_em_sitios_diferentes_sao_problemas_diferentes(): void
    {
        $this->registo->registar('error', 'Falhou', [], 'app/A.php', 10);
        $this->registo->registar('error', 'Falhou', [], 'app/B.php', 10);

        $this->assertSame(2, ErroDoSistema::count());
    }

    public function test_datas_uuids_e_caminhos_nao_separam_o_mesmo_problema(): void
    {
        $a = $this->registo->normalizar('Falha em 2026-08-20 no ficheiro C:\\laragon2\\www\\x.php');
        $b = $this->registo->normalizar('Falha em 2026-01-02 no ficheiro C:\\outro\\sitio\\y.php');

        $this->assertSame($a, $b);
    }

    // ── segredos ─────────────────────────────────────────────────────────────

    public function test_segredos_nunca_entram(): void
    {
        $this->registo->registar('error', 'Falha ao entrar', [
            'password'      => 'muito-secreta',
            'token'         => 'oclaw_abc123',
            'authorization' => 'Bearer xyz',
            'utilizador'    => 'ana',
        ]);

        $ctx = ErroDoSistema::first()->contexto;

        $this->assertSame('[oculto]', $ctx['password']);
        $this->assertSame('[oculto]', $ctx['token']);
        $this->assertSame('[oculto]', $ctx['authorization']);
        $this->assertSame('ana', $ctx['utilizador'], 'o que não é segredo fica, senão não se percebe nada');
    }

    public function test_segredos_aninhados_tambem_sao_ocultados(): void
    {
        $this->registo->registar('error', 'Falha', [
            'pedido' => ['corpo' => ['password' => 'x', 'nif' => '5417...']],
        ]);

        $ctx = ErroDoSistema::first()->contexto;

        $this->assertSame('[oculto]', $ctx['pedido']['corpo']['password']);
    }

    // ── o que não é problema ─────────────────────────────────────────────────

    public function test_um_aviso_nao_e_um_problema(): void
    {
        $this->registo->registar('warning', 'só um aviso');
        $this->registo->registar('info', 'rotina');

        $this->assertSame(0, ErroDoSistema::count(), 'warning é ruído a mais para acordar alguém');
    }

    public function test_o_ruido_conhecido_fica_de_fora(): void
    {
        // Um 404 e uma password errada são o sistema a funcionar.
        $this->registo->registar('error', 'x', [], null, null, 'Symfony\\...\\NotFoundHttpException');
        $this->registo->registar('error', 'y', [], null, null, 'Illuminate\\Validation\\ValidationException');

        $this->assertSame(0, ErroDoSistema::count());
    }

    // ── ciclo de vida ────────────────────────────────────────────────────────

    public function test_um_erro_resolvido_que_volta_e_reaberto(): void
    {
        $this->registo->registar('error', 'Falhou', [], 'app/A.php', 1);

        ErroDoSistema::first()->forceFill([
            'resolvido_em'  => now(),
            'notificado_em' => now(),
        ])->save();

        $this->registo->registar('error', 'Falhou', [], 'app/A.php', 1);

        $erro = ErroDoSistema::first();
        $this->assertNull($erro->resolvido_em, 'um erro dado por resolvido que regressa não pode ficar calado');
        $this->assertNull($erro->notificado_em, 'e volta a merecer aviso');
    }

    // ── o canal de log ───────────────────────────────────────────────────────

    public function test_um_Log_error_da_aplicacao_e_apanhado(): void
    {
        Log::error('rebentou a sério', ['onde' => 'teste']);

        $this->assertSame(1, ErroDoSistema::count());
        $this->assertStringContainsString('rebentou', ErroDoSistema::first()->mensagem);
    }

    public function test_com_a_captura_desligada_nao_se_grava_nada(): void
    {
        config(['agent.erros.capturar' => false]);

        Log::error('rebentou');

        $this->assertSame(0, ErroDoSistema::count());
    }

    // ── o empurrão para o agente ─────────────────────────────────────────────

    public function test_o_webhook_leva_os_erros_por_notificar_e_vai_assinado(): void
    {
        Http::fake(['https://openclaw.exemplo/hook' => Http::response(['ok' => true], 200)]);

        config([
            'agent.erros.notificar'      => true,
            'agent.erros.webhook_url'    => 'https://openclaw.exemplo/hook',
            'agent.erros.webhook_secret' => 'segredo-partilhado',
        ]);

        $this->registo->registar('critical', 'A base caiu', [], 'app/A.php', 1);

        $r = app(NotificarOpenClaw::class)->empurrar();

        $this->assertSame(1, $r['enviados']);

        Http::assertSent(function ($pedido) {
            $corpo = $pedido->body();
            $momento = $pedido->header('X-Soserp-Timestamp')[0];
            $assinatura = $pedido->header('X-Soserp-Signature')[0];

            // É a assinatura que permite ao agente saber que a mensagem veio
            // daqui e não de quem descobriu o URL dele.
            return hash_equals(
                hash_hmac('sha256', $momento . '.' . $corpo, 'segredo-partilhado'),
                $assinatura
            );
        });

        $this->assertNotNull(ErroDoSistema::first()->notificado_em);
    }

    public function test_o_mesmo_erro_nao_e_empurrado_duas_vezes(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        config(['agent.erros.notificar' => true, 'agent.erros.webhook_url' => 'https://x.test/h']);

        $this->registo->registar('error', 'Falhou', [], 'app/A.php', 1);

        $this->assertSame(1, app(NotificarOpenClaw::class)->empurrar()['enviados']);
        $this->assertSame(0, app(NotificarOpenClaw::class)->empurrar()['enviados']);
    }

    public function test_se_o_agente_nao_responder_o_erro_fica_por_notificar(): void
    {
        Http::fake(['*' => Http::response('em baixo', 500)]);
        config(['agent.erros.notificar' => true, 'agent.erros.webhook_url' => 'https://x.test/h']);

        $this->registo->registar('error', 'Falhou', [], 'app/A.php', 1);

        $r = app(NotificarOpenClaw::class)->empurrar();

        $this->assertSame(0, $r['enviados']);
        // Marcar antes de o agente confirmar deixava o erro por avisar para
        // sempre à primeira falha de rede.
        $this->assertNull(ErroDoSistema::first()->notificado_em);
    }

    public function test_com_o_empurrao_desligado_nao_sai_pedido_nenhum(): void
    {
        Http::fake();
        config(['agent.erros.notificar' => false]);

        $this->registo->registar('error', 'Falhou', [], 'app/A.php', 1);
        app(NotificarOpenClaw::class)->empurrar();

        Http::assertNothingSent();
    }

    /**
     * A recursão é o modo de falhar mais perigoso desta peça.
     *
     * O empurrão faz um pedido HTTP. Se o agente estiver em baixo, o cliente
     * HTTP escreve no log — e essa escrita criaria um erro novo que voltava a
     * querer ser notificado, num ciclo que só parava com o servidor em baixo.
     */
    public function test_um_erro_durante_o_empurrao_nao_gera_outro_erro(): void
    {
        Http::fake(function () {
            // O que o cliente HTTP faz quando o outro lado está em baixo.
            Log::error('cURL error 7: Failed to connect');

            return Http::response('', 500);
        });

        config(['agent.erros.notificar' => true, 'agent.erros.webhook_url' => 'https://x.test/h']);

        $this->registo->registar('error', 'Falhou', [], 'app/A.php', 1);
        $antes = ErroDoSistema::count();

        app(NotificarOpenClaw::class)->empurrar();

        $this->assertSame($antes, ErroDoSistema::count(),
            'a falha do empurrão não pode virar um erro novo — seria um ciclo sem fim');
    }
}
