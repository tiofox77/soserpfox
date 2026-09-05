<?php

namespace Tests\Feature;

use App\Support\AutoCuraOpcache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A auto-cura do OPcache — a rede de segurança contra «method not found» por
 * cache de código velha depois de um deploy.
 *
 * O que estes ensaios prendem é o CONTRATO que a torna segura: nunca lança
 * (corre no meio de um pedido que já está a falhar), devolve sempre um booleano,
 * e a janela (tranca atómica na cache) impede que um método genuinamente
 * inexistente desate a repor a cada pedido — o que evita o ciclo de recargas.
 */
class AutoCuraOpcacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('opcache-auto-reset');
    }

    protected function tearDown(): void
    {
        Cache::forget('opcache-auto-reset');
        parent::tearDown();
    }

    /** @test */
    public function nunca_lanca_e_devolve_booleano(): void
    {
        // Não pode rebentar seja qual for o estado do OPcache no ambiente.
        $this->assertIsBool(AutoCuraOpcache::talvezRepor());
    }

    /**
     * A JANELA: com a tranca já posta, não repõe outra vez.
     *
     * É isto que impede o ciclo de recargas quando o método falta MESMO (bug
     * genuíno): a primeira vez cura (ou tenta), as seguintes na janela ficam
     * quietas e o erro volta a ser visível.
     *
     * @test
     */
    public function com_a_tranca_posta_nao_repoe(): void
    {
        // Simula "já se repôs agora": a chave existe.
        Cache::put('opcache-auto-reset', 1, 60);

        $this->assertFalse(
            AutoCuraOpcache::talvezRepor(),
            'com a tranca posta tem de recusar — senão é tempestade de resets e ciclo de recargas'
        );
    }
}
