<?php

namespace Tests\Feature\Pwa;

use Tests\TenantTestCase;

/**
 * A entrada do PWA tem de instalar o service worker.
 *
 * Era a única página que não o fazia: o registo vivia só no `layouts.pwa`, ou
 * seja, DENTRO da aplicação. Quem chegasse primeiro à entrada — instalação
 * nova, dados do site limpos, ou a sessão a expirar e o `auth` a mandar para
 * cá — ficava sem service worker nenhum.
 *
 * O efeito só aparece no pior momento: enquanto há rede corre tudo bem, e no
 * dia em que o servidor cai o browser mostra a SUA página de erro, como se o
 * modo offline nunca tivesse existido. Pior ainda: sem service worker, a
 * própria entrada não fica guardada — logo, nem para pôr o PIN se volta.
 *
 * E tudo o que ela carrega tem de ser LOCAL. Sem o Dexie o motor não arranca
 * (`if (typeof Dexie === 'undefined') return`), e um Dexie que vem de um CDN
 * não chega quando não há rede — que é exactamente quando esta página serve
 * para alguma coisa.
 */
class EntradaRegistaServiceWorkerTest extends TenantTestCase
{
    private function entrada(): string
    {
        return $this->get('/invoicing/offline/login')->assertOk()->getContent();
    }

    public function test_a_entrada_regista_o_service_worker(): void
    {
        $pacote = \App\Support\PacoteReact::doPwa();

        if (! $pacote) {
            $this->markTestSkipped('O pacote do PWA não está construído (npm run pwa:build).');
        }

        // O registo vive no pacote (casca/servicoOffline.ts, chamado à cabeça
        // do pwa.tsx) — e o pacote é carregado pela entrada.
        $this->assertStringContainsString('src="'.$pacote.'"', $this->entrada(),
            'sem o pacote, quem chega primeiro à entrada nunca instala o modo offline');
        $this->assertStringContainsString("register('/sw.js'", file_get_contents(resource_path('js/casca/servicoOffline.ts')));
        $this->assertStringContainsString('ligarServicoOffline(', file_get_contents(resource_path('js/pwa.tsx')));
    }

    /**
     * Nada de CDN. A entrada carregava Tailwind, Alpine e Dexie de três
     * servidores estranhos — e sem rede nenhum deles chega.
     */
    public function test_a_entrada_nao_depende_de_nenhum_cdn(): void
    {
        $html = $this->entrada();

        foreach (['cdn.tailwindcss.com', 'unpkg.com', 'cdnjs.cloudflare.com'] as $cdn) {
            $this->assertStringNotContainsString(
                $cdn,
                $html,
                "a entrada tem de funcionar sem rede — {$cdn} não chega lá"
            );
        }
    }

    /** E carrega mesmo o motor, do próprio servidor: o pacote do PWA leva o Dexie dentro. */
    public function test_a_entrada_carrega_o_motor_local(): void
    {
        $html = $this->entrada();

        $this->assertMatchesRegularExpression('#<script type="module" src="/pwa-app/pwa-[^"]+\.js"#', $html);
        $this->assertStringContainsString('/js/vendor/bcrypt.min.js', $html, 'o PIN confere-se com o bcrypt local');
    }

    /**
     * O service worker tem de PRÉ-GUARDAR a entrada.
     *
     * Ela estava na lista de recurso mas não na de pré-guardar: num aparelho
     * acabado de instalar não havia cópia nenhuma para servir sem rede.
     */
    public function test_o_service_worker_pre_guarda_a_entrada(): void
    {
        $sw = $this->get('/sw.js')->assertOk()->getContent();

        $inicio = strpos($sw, 'PRECACHE_PAGINAS');
        $fim = strpos($sw, '];', $inicio);
        $lista = substr($sw, $inicio, $fim - $inicio);

        $this->assertStringContainsString('/invoicing/offline/login', $lista);
    }

    /**
     * O ping nunca pode vir do cache: ele existe para responder sobre AGORA.
     * Servido do cache, dizia "há sessão" muito depois de ela ter morrido — e
     * era assim que o ecrã anunciava "sem ligação" com o telemóvel cheio de
     * sinal.
     */
    public function test_o_ping_nao_e_servido_do_cache(): void
    {
        $sw = $this->get('/sw.js')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/pathname === .\/api\/v1\/invoicing\/ping./',
            $sw,
            'o ping tem de ter regra própria, antes das rotas de API'
        );
    }
}
