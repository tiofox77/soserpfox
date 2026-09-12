<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Services\Analytics\Regiao;
use App\Services\Analytics\RegistoDeVisita;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

/**
 * O REGISTO DE UMA VISITA — o que se grava, e o que nunca sai para a rede.
 *
 * Era `AnalyticsEcraTest` e media duas coisas de uma vez: o que o registo grava
 * e o que o ecrã desenha. O ecrã passou a React e as perguntas dele vivem em
 * `Tests\Feature\Plataforma\ApiDaPlataformaTest`; aqui ficou o registo, que é
 * outra coisa e não depende de ecrã nenhum.
 *
 * O painel de países existia e mostrou zero em 322 visitas — o país só era lido
 * de um cabeçalho da Cloudflare que este alojamento não tem.
 */
class RegistoDeVisitasTest extends TenantTestCase
{
    private int $contador = 0;

    /** Um evento, com o mínimo para ser válido. */
    private function evento(array $dados = []): AnalyticsEvent
    {
        $this->contador++;

        return AnalyticsEvent::create(array_merge([
            'visitor_id' => sprintf('%08d-0000-4000-a000-000000000001', $this->contador),
            'session_id' => sprintf('%08d-0000-4000-a000-000000000002', $this->contador),
            'type' => 'pageview',
            'event_name' => 'pageview',
            'path' => '/',
            'created_at' => now(),
        ], $dados));
    }

    public function test_a_duracao_e_gravada_na_linha_da_propria_pagina(): void
    {
        // A saída não é um acto novo: é o desfecho do pageview. Numa linha
        // própria, a média de tempo por página — que se lê da linha do
        // pageview — ficava sempre vazia.
        $sessao = '70000000-0000-4000-a000-000000000002';

        $this->postJson('/api/analytics/track', [
            'visitor_id' => '70000000-0000-4000-a000-000000000001',
            'session_id' => $sessao,
            'type' => 'pageview',
            'path' => '/precos',
        ])->assertOk();

        $this->postJson('/api/analytics/track', [
            'visitor_id' => '70000000-0000-4000-a000-000000000001',
            'session_id' => $sessao,
            'type' => 'page_exit',
            'path' => '/precos',
            'duration_seconds' => 42,
        ])->assertOk();

        $this->assertSame(
            1,
            AnalyticsEvent::where('session_id', $sessao)->count(),
            'a saída não cria linha nova'
        );

        $this->assertSame(
            42,
            (int) AnalyticsEvent::where('session_id', $sessao)->value('duration_seconds')
        );
    }

    public function test_o_termo_pesquisado_e_guardado_em_minusculas(): void
    {
        // Senão "Amidol", "amidol" e "AMIDOL" contam como três termos e o
        // painel divide-se a si próprio.
        $this->postJson('/api/analytics/track', [
            'visitor_id' => '80000000-0000-4000-a000-000000000001',
            'session_id' => '80000000-0000-4000-a000-000000000002',
            'type' => 'search',
            'search_term' => '  AMIDOL  ',
        ])->assertOk();

        $this->assertSame('amidol', AnalyticsEvent::latest('id')->value('search_term'));
    }

    public function test_o_registo_do_servidor_nao_guarda_termos_curtos(): void
    {
        // Quem procura escreve letra a letra. Guardar "a" e "am" faz o painel
        // dos mais pesquisados mostrar o alfabeto.
        RegistoDeVisita::pesquisa('am', 'pos');
        RegistoDeVisita::pesquisa('amidol', 'pos');

        $this->assertSame(1, AnalyticsEvent::where('type', 'search')->count());
        $this->assertSame('amidol', AnalyticsEvent::where('type', 'search')->value('search_term'));
    }

    public function test_o_registo_do_servidor_nunca_rebenta(): void
    {
        // Uma pesquisa num ecrã de vendas não pode falhar porque o registo de
        // estatísticas teve um problema. Um termo com 300 caracteres não cabe
        // na coluna — e mesmo assim isto tem de devolver sem lançar.
        RegistoDeVisita::pesquisa(str_repeat('x', 300), 'pos');

        $this->assertTrue(true, 'não lançou');
    }

    public function test_a_regiao_de_um_ip_conhecido_e_copiada_sem_ir_a_rede(): void
    {
        // Cada IP é perguntado UMA vez. O que já foi resolvido copia-se das
        // linhas anteriores do mesmo IP — numa base com meia dúzia de pessoas,
        // isso são meia dúzia de perguntas ao todo.
        Http::fake();

        $this->evento(['ip' => '41.223.10.5', 'country' => 'AO', 'city' => 'Luanda']);
        $this->evento(['ip' => '41.223.10.5', 'country' => null]);

        $conta = Regiao::resolverPendentes();

        Http::assertNothingSent();

        $this->assertSame(1, $conta['copiados']);
        $this->assertSame(
            2,
            AnalyticsEvent::where('ip', '41.223.10.5')->where('country', 'AO')->count()
        );
    }

    public function test_um_ip_privado_nunca_sai_para_a_rede(): void
    {
        // Sem isto, a máquina de desenvolvimento e a rede interna do cliente
        // eram mandadas para o serviço externo a cada arranque — e voltavam
        // sempre sem resposta, portanto ficavam eternamente por resolver.
        Http::fake();

        $this->evento(['ip' => '192.168.1.10']);
        $this->evento(['ip' => '127.0.0.1']);

        Regiao::resolverPendentes();

        Http::assertNothingSent();

        $this->assertSame(0, Regiao::porResolver());
    }

    public function test_a_regiao_resolve_se_pelo_servico_externo(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response(['status' => 'success', 'countryCode' => 'AO', 'city' => 'Benguela']),
        ]);

        $this->evento(['ip' => '196.216.2.1']);

        $conta = Regiao::resolverPendentes();

        $this->assertSame(1, $conta['perguntados']);
        $this->assertSame('AO', AnalyticsEvent::where('ip', '196.216.2.1')->value('country'));
        $this->assertSame('Benguela', AnalyticsEvent::where('ip', '196.216.2.1')->value('city'));
    }

    public function test_o_servico_externo_em_baixo_nao_estraga_nada(): void
    {
        Http::fake(['ip-api.com/*' => Http::response('', 500)]);

        $this->evento(['ip' => '196.216.2.2']);

        $conta = Regiao::resolverPendentes();

        $this->assertSame(1, $conta['falhados']);
        $this->assertNull(AnalyticsEvent::where('ip', '196.216.2.2')->value('country'));
    }

    public function test_desligar_a_regiao_impede_qualquer_chamada(): void
    {
        // Isto manda endereços IP de visitantes para um serviço de terceiros.
        // Tem de haver um interruptor.
        config(['analytics.geo' => false]);
        Http::fake();

        $this->evento(['ip' => '196.216.2.3']);

        Regiao::resolverPendentes();

        Http::assertNothingSent();
        $this->assertNull(AnalyticsEvent::where('ip', '196.216.2.3')->value('country'));
    }
}
