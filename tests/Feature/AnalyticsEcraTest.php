<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\Analytics;
use App\Models\AnalyticsEvent;
use App\Services\Analytics\Regiao;
use App\Services\Analytics\RegistoDeVisita;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O ecrã de analytics.
 *
 * O que lá estava media visitas e cliques, mas não respondia a nada do que se
 * pergunta a um painel destes: quem está aqui agora, de onde veio, o que
 * procurou, de que região. O painel de países existia e mostrou zero em 322
 * visitas — o país só era lido de um cabeçalho da Cloudflare que este
 * alojamento não tem.
 */
class AnalyticsEcraTest extends TenantTestCase
{
    private int $contador = 0;

    /** Um evento, com o mínimo para ser válido. */
    private function evento(array $dados = []): AnalyticsEvent
    {
        $this->contador++;

        return AnalyticsEvent::create(array_merge([
            'visitor_id' => sprintf('%08d-0000-4000-a000-000000000001', $this->contador),
            'session_id' => sprintf('%08d-0000-4000-a000-000000000002', $this->contador),
            'type'       => 'pageview',
            'event_name' => 'pageview',
            'path'       => '/',
            'created_at' => now(),
        ], $dados));
    }

    private function ecra()
    {
        return Livewire::test(Analytics::class);
    }

    public function test_mostra_quem_esta_no_site_agora(): void
    {
        // Não existia. Um painel de analytics sem "agora" obriga a escolher um
        // período para responder à pergunta mais imediata que se lhe faz.
        $this->evento(['visitor_id' => '11111111-0000-4000-a000-000000000001', 'created_at' => now()->subMinutes(1)]);
        $this->evento(['visitor_id' => '22222222-0000-4000-a000-000000000001', 'created_at' => now()->subMinutes(3)]);

        // Este já saiu: fora dos cinco minutos.
        $this->evento(['visitor_id' => '33333333-0000-4000-a000-000000000001', 'created_at' => now()->subMinutes(30)]);

        $this->ecra()->assertViewHas('online', 2);
    }

    public function test_o_agora_nao_obedece_ao_periodo_escolhido(): void
    {
        // "Agora" é agora. Se obedecesse ao filtro, escolher "Hoje" às 00:05
        // escondia quem estava no site.
        $this->evento(['created_at' => now()->subMinute()]);

        $this->ecra()
            ->call('setRange', '90d')
            ->assertViewHas('online', 1);
    }

    public function test_classifica_de_onde_vieram_em_canais(): void
    {
        $this->evento(['visitor_id' => 'aaaaaaaa-0000-4000-a000-000000000001', 'referrer' => 'https://www.google.com/search?q=erp']);
        $this->evento(['visitor_id' => 'bbbbbbbb-0000-4000-a000-000000000001', 'referrer' => 'http://m.facebook.com']);
        $this->evento(['visitor_id' => 'cccccccc-0000-4000-a000-000000000001', 'referrer' => null]);

        $canais = $this->ecra()->viewData('canais');

        $this->assertSame(1, $canais['orgânico'] ?? 0);
        $this->assertSame(1, $canais['social'] ?? 0);
        $this->assertSame(1, $canais['directo'] ?? 0);
    }

    public function test_o_canal_de_um_visitante_e_por_onde_ele_ENTROU(): void
    {
        // Alguém que chega pelo Facebook e depois navega no site não passa a
        // "interno" à segunda página — senão o Facebook deixava de aparecer
        // como origem de ninguém.
        $visitante = 'dddddddd-0000-4000-a000-000000000001';

        $this->evento(['visitor_id' => $visitante, 'referrer' => 'http://m.facebook.com', 'created_at' => now()->subMinutes(10)]);
        $this->evento(['visitor_id' => $visitante, 'referrer' => 'https://soserp.vip/', 'created_at' => now()->subMinutes(9)]);

        $canais = $this->ecra()->viewData('canais');

        $this->assertSame(1, $canais['social'] ?? 0);
        $this->assertArrayNotHasKey('interno', $canais->toArray());
    }

    public function test_filtrar_por_canal(): void
    {
        $this->evento(['visitor_id' => 'eeeeeeee-0000-4000-a000-000000000001', 'referrer' => 'http://m.facebook.com']);
        $this->evento(['visitor_id' => 'ffffffff-0000-4000-a000-000000000001', 'referrer' => null]);

        $this->ecra()
            ->set('sourceFilter', 'social')
            ->assertViewHas('totalVisitors', 1);
    }

    public function test_filtrar_por_pais(): void
    {
        $this->evento(['visitor_id' => '10000000-0000-4000-a000-000000000001', 'country' => 'AO']);
        $this->evento(['visitor_id' => '20000000-0000-4000-a000-000000000001', 'country' => 'PT']);

        $this->ecra()
            ->set('countryFilter', 'AO')
            ->assertViewHas('totalVisitors', 1);
    }

    public function test_mostra_os_termos_mais_pesquisados(): void
    {
        // Não existia coluna nem painel. "Nomes mais pesquisados" era a única
        // coisa da lista que não tinha sequer onde ser guardada.
        foreach (['amidol', 'amidol', 'paracetamol'] as $i => $termo) {
            $this->evento([
                'visitor_id'  => sprintf('3000000%d-0000-4000-a000-000000000001', $i),
                'type'        => 'search',
                'event_name'  => 'search_pos',
                'search_term' => $termo,
            ]);
        }

        $termos = $this->ecra()->viewData('topSearches');

        $this->assertSame('amidol', $termos->first()->search_term);
        $this->assertSame(2, (int) $termos->first()->vezes);
    }

    public function test_o_percurso_de_um_visitante_mostra_as_paginas_por_ordem(): void
    {
        // "Páginas acessadas" por pessoa. Sabia-se que alguém viu cinco
        // páginas e não QUAIS.
        $visitante = '40000000-0000-4000-a000-000000000001';

        $this->evento(['visitor_id' => $visitante, 'path' => '/',              'created_at' => now()->subMinutes(5)]);
        $this->evento(['visitor_id' => $visitante, 'path' => '/modulos/rh',    'created_at' => now()->subMinutes(4)]);
        $this->evento(['visitor_id' => $visitante, 'path' => '/modulos/hotel', 'created_at' => now()->subMinutes(3)]);

        $percurso = $this->ecra()
            ->call('verVisitante', $visitante)
            ->viewData('percurso');

        $this->assertCount(3, $percurso);
        $this->assertSame(['/', '/modulos/rh', '/modulos/hotel'], $percurso->pluck('path')->all());
    }

    /** Quantas consultas o ecrã faz para desenhar um período. */
    private function consultasPara(string $periodo): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(Analytics::class)->set('range', $periodo);

        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    public function test_o_custo_do_ecra_nao_cresce_com_o_tamanho_do_periodo(): void
    {
        // Aqui estava um ciclo com uma consulta POR DIA do período: sete dias
        // custavam oito consultas e noventa custavam trinta e uma (o ciclo
        // estava limitado a 30), só para desenhar o gráfico.
        //
        // Compara-se 7 dias com 90 em vez de fixar um número: o que não pode
        // acontecer é o custo acompanhar os dias. Um número mágico ficaria
        // desactualizado ao primeiro painel novo.
        for ($i = 0; $i < 60; $i++) {
            $this->evento(['created_at' => now()->subDays($i)]);
        }

        $curto = $this->consultasPara('7d');
        $longo = $this->consultasPara('90d');

        $this->assertSame(
            $curto,
            $longo,
            "7 dias custaram {$curto} consultas e 90 dias custaram {$longo} — a série voltou a ser dia a dia"
        );
    }

    public function test_a_taxa_de_rejeicao_conta_sessoes_de_uma_pagina(): void
    {
        // Sessão que viu duas páginas.
        $this->evento(['session_id' => '50000000-0000-4000-a000-000000000002', 'path' => '/']);
        $this->evento(['session_id' => '50000000-0000-4000-a000-000000000002', 'path' => '/modulos/rh']);

        // Sessão que viu uma e saiu.
        $this->evento(['session_id' => '60000000-0000-4000-a000-000000000002', 'path' => '/']);

        $this->ecra()->assertViewHas('taxaRejeicao', 50);
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
            'type'       => 'pageview',
            'path'       => '/precos',
        ])->assertOk();

        $this->postJson('/api/analytics/track', [
            'visitor_id' => '70000000-0000-4000-a000-000000000001',
            'session_id' => $sessao,
            'type'             => 'page_exit',
            'path'             => '/precos',
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
            'visitor_id'  => '80000000-0000-4000-a000-000000000001',
            'session_id'  => '80000000-0000-4000-a000-000000000002',
            'type'        => 'search',
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
