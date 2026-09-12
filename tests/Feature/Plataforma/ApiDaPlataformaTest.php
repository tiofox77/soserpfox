<?php

namespace Tests\Feature\Plataforma;

use App\Models\AnalyticsEvent;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * A API DOS ECRÃS DA PLATAFORMA — a casa do dono, não a de uma empresa.
 *
 * A PRIMEIRA COISA QUE SE PROVA É A GUARDA. Este grupo de rotas vê TODAS as
 * empresas, muda o preço do que se vende e liga módulos a quem paga: se a porta
 * ficar aberta, um utilizador de uma empresa qualquer mexe na plataforma toda.
 * O papel «Super Admin» NÃO serve — esse é por empresa e todos os donos o têm.
 */
class ApiDaPlataformaTest extends TenantTestCase
{
    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dono = User::create([
            'name' => 'Dono da Plataforma',
            'email' => 'dono_'.uniqid().'@exemplo.ao',
            'password' => bcrypt('x'),
        ]);

        $this->dono->forceFill(['is_super_admin' => true])->save();
    }

    /* ─── A guarda ────────────────────────────────────────────────────── */

    public static function moradasDaPlataforma(): array
    {
        return [
            'painel' => ['/api/v1/plataforma/react/painel'],
            'analítica' => ['/api/v1/plataforma/react/analitica'],
            'planos' => ['/api/v1/plataforma/react/planos'],
            'módulos' => ['/api/v1/plataforma/react/modulos'],
            'empresas' => ['/api/v1/plataforma/react/empresas'],
            'facturação' => ['/api/v1/plataforma/react/facturacao'],
        ];
    }

    /**
     * @dataProvider moradasDaPlataforma
     */
    public function test_um_utilizador_de_empresa_nao_entra_na_api_da_plataforma(string $morada): void
    {
        // O TenantTestCase já autenticou um utilizador NORMAL — que é dono da
        // empresa dele e tem o papel «Super Admin» por empresa.
        $this->getJson($morada)->assertForbidden();
    }

    /**
     * @dataProvider moradasDaPlataforma
     */
    public function test_o_dono_da_plataforma_entra(string $morada): void
    {
        $this->actingAs($this->dono)->getJson($morada)->assertOk();
    }

    public function test_sem_sessao_nenhuma_a_api_nao_responde(): void
    {
        auth()->logout();

        // Sem sessão o grupo manda para o login: o que não pode é responder.
        $resposta = $this->getJson('/api/v1/plataforma/react/planos');

        $this->assertNotSame(200, $resposta->status());
    }

    /** Escrever exige a mesma porta que ler. */
    public function test_um_utilizador_de_empresa_nao_cria_planos(): void
    {
        $this->postJson('/api/v1/plataforma/react/planos', ['name' => 'Pirata'])
            ->assertForbidden();

        $this->assertDatabaseMissing('plans', ['name' => 'Pirata']);
    }

    /* ─── O painel ────────────────────────────────────────────────────── */

    public function test_o_painel_conta_todas_as_empresas_e_nao_so_a_do_escopo(): void
    {
        Tenant::create([
            'name' => 'Outra Casa', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'outra'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $json = $this->actingAs($this->dono)
            ->getJson('/api/v1/plataforma/react/painel')->assertOk()->json();

        // O dono da plataforma vê o universo: pelo menos as duas empresas.
        $this->assertGreaterThanOrEqual(2, $json['numeros']['empresas']);
    }

    /**
     * SEM MÊS ANTERIOR NÃO HÁ PERCENTAGEM.
     *
     * O componente antigo devolvia 0% e o ecrã desenhava «0%» — que se lê como
     * «ficou igual». Não é: é «não há com que comparar».
     */
    public function test_o_crescimento_e_nulo_quando_nao_ha_mes_anterior(): void
    {
        Tenant::whereNotNull('created_at')->update(['created_at' => now()]);

        $json = $this->actingAs($this->dono)
            ->getJson('/api/v1/plataforma/react/painel')->assertOk()->json();

        $this->assertNull($json['numeros']['crescimento']);
    }

    /* ─── Os planos ───────────────────────────────────────────────────── */

    private function moduloNovo(string $slug = null): Module
    {
        return Module::create([
            'name' => 'Módulo '.($slug ?? uniqid()),
            'slug' => $slug ?? 'mod-'.uniqid(),
            'description' => 'Para o ensaio.',
            'icon' => 'puzzle-piece',
            'version' => '1.0.0',
            'is_active' => true,
            'order' => 0,
        ]);
    }

    private function planoValido(array $troca = []): array
    {
        return array_merge([
            'name' => 'Plano de Ensaio',
            'slug' => 'ensaio-'.uniqid(),
            'description' => 'Serve para o ensaio.',
            'price_monthly' => 10000,
            'price_quarterly' => 28500,
            'price_semiannual' => 54000,
            'price_yearly' => 102000,
            'max_users' => 5,
            'max_companies' => 1,
            'max_storage_mb' => 1000,
            'max_documents' => 500,
            'trial_days' => 14,
            'order' => 3,
            'is_active' => true,
            'is_public' => true,
            'is_featured' => false,
            'is_promotional' => false,
            'auto_activate' => false,
            'features' => ['Facturação', 'Suporte'],
            'modulos' => [],
        ], $troca);
    }

    /**
     * OS QUATRO CAMPOS QUE O ECRÃ NUNCA DEIXOU TOCAR.
     *
     * `price_quarterly` e `price_semiannual` são os preços que a página «A minha
     * conta» mostra ao cliente; nasciam a zero (lido como 3× e 6× o mensal, sem
     * desconto) e só um comando de consola os sabia pôr. `max_documents` trava a numeração fiscal. `is_public` é o que
     * separa um plano da montra de um plano à medida.
     */
    public function test_criar_um_plano_grava_os_quatro_ciclos_o_tecto_e_a_montra(): void
    {
        $dados = $this->planoValido(['is_promotional' => true]);

        $this->actingAs($this->dono)
            ->postJson('/api/v1/plataforma/react/planos', $dados)
            ->assertCreated();

        $plano = Plan::where('slug', $dados['slug'])->firstOrFail();

        $this->assertSame('28500.00', $plano->getRawOriginal('price_quarterly'));
        $this->assertSame('54000.00', $plano->getRawOriginal('price_semiannual'));
        $this->assertSame(500, (int) $plano->max_documents);
        $this->assertTrue((bool) $plano->is_public);
        $this->assertTrue((bool) $plano->is_promotional);
        $this->assertSame(['Facturação', 'Suporte'], $plano->features);
    }

    /** Vazio é «sem tecto», e não zero. */
    public function test_o_tecto_de_documentos_vazio_grava_nulo(): void
    {
        $dados = $this->planoValido(['max_documents' => null]);

        $this->actingAs($this->dono)
            ->postJson('/api/v1/plataforma/react/planos', $dados)->assertCreated();

        $this->assertNull(Plan::where('slug', $dados['slug'])->firstOrFail()->max_documents);
    }

    /**
     * UM PLANO NA MONTRA TEM DE ESTAR AO SERVIÇO.
     *
     * Publicar um plano desligado punha-o na página de preços a levar quem
     * clicasse a um beco sem saída.
     */
    public function test_nao_se_publica_um_plano_fora_de_servico(): void
    {
        $this->actingAs($this->dono)
            ->postJson('/api/v1/plataforma/react/planos', $this->planoValido([
                'is_active' => false, 'is_public' => true,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_public');
    }

    /** E desligar um plano tira-o da montra, em vez de o deixar lá. */
    public function test_desligar_um_plano_tira_o_da_montra(): void
    {
        $plano = Plan::create([
            'name' => 'Vai Desligar', 'slug' => 'desligar-'.uniqid(),
            'description' => 'x', 'price_monthly' => 1000, 'price_yearly' => 10000,
            'is_active' => true, 'is_public' => true,
        ]);

        $this->actingAs($this->dono)
            ->postJson("/api/v1/plataforma/react/planos/{$plano->id}/alternar")->assertOk();

        $plano->refresh();

        $this->assertFalse((bool) $plano->is_active);
        $this->assertFalse((bool) $plano->is_public);
    }

    /**
     * O JSON ANTIGO EM PASSO COM A TABELA DE LIGAÇÃO.
     *
     * `included_modules` duplica a ligação e três comandos ainda o lêem. Gravar
     * por aqui deixava-o a apodrecer com o que lá estava de antes.
     */
    public function test_gravar_os_modulos_actualiza_tambem_o_json_antigo(): void
    {
        $modulo = $this->moduloNovo();
        $dados = $this->planoValido(['modulos' => [$modulo->id]]);

        $this->actingAs($this->dono)
            ->postJson('/api/v1/plataforma/react/planos', $dados)->assertCreated();

        $plano = Plan::where('slug', $dados['slug'])->firstOrFail();

        $this->assertSame([$modulo->slug], $plano->included_modules);
        $this->assertTrue($plano->modules->contains('id', $modulo->id));
    }

    public function test_um_plano_com_subscricao_activa_nao_se_apaga(): void
    {
        $plano = Plan::create([
            'name' => 'Ocupado', 'slug' => 'ocupado-'.uniqid(),
            'description' => 'x', 'price_monthly' => 1000, 'price_yearly' => 10000,
            'is_active' => true,
        ]);

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plano->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 1000,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->actingAs($this->dono)
            ->deleteJson("/api/v1/plataforma/react/planos/{$plano->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('plans', ['id' => $plano->id]);
    }

    public function test_a_lista_de_planos_diz_porque_nao_se_apaga(): void
    {
        $plano = Plan::create([
            'name' => 'Com Ensaio', 'slug' => 'ensaiado-'.uniqid(),
            'description' => 'x', 'price_monthly' => 1000, 'price_yearly' => 10000,
            'is_active' => true,
        ]);

        Subscription::create([
            'tenant_id' => $this->tenant->id, 'plan_id' => $plano->id,
            'status' => 'trial', 'billing_cycle' => 'monthly', 'amount' => 0,
            'starts_at' => now(), 'ends_at' => now()->addDays(14),
        ]);

        $json = $this->actingAs($this->dono)
            ->getJson('/api/v1/plataforma/react/planos')->assertOk()->json();

        $linha = collect($json['planos'])->firstWhere('id', $plano->id);

        $this->assertFalse($linha['pode_apagar']);
        $this->assertSame(1, $linha['subscricoes_presas']);
    }

    /* ─── Os módulos ──────────────────────────────────────────────────── */

    private function moduloValido(array $troca = []): array
    {
        return array_merge([
            'name' => 'Módulo de Ensaio',
            'slug' => 'ensaio-'.uniqid(),
            'description' => 'Serve para o ensaio.',
            'icon' => 'fa-cubes',
            'version' => '1.0.0',
            'order' => 5,
            'default_price' => 2500,
            'is_active' => true,
            'is_core' => false,
            'dependencies' => [],
        ], $troca);
    }

    /**
     * AS DEPENDÊNCIAS NÃO TINHAM CAMPO.
     *
     * A lista contava-as e o formulário não as sabia gravar — punham-se por
     * `tinker`. E são elas que levam a Tesouraria atrás da Facturação.
     */
    public function test_gravar_um_modulo_grava_as_dependencias(): void
    {
        $base = $this->moduloNovo('base-'.uniqid());
        $dados = $this->moduloValido(['dependencies' => [$base->slug]]);

        $this->actingAs($this->dono)
            ->postJson('/api/v1/plataforma/react/modulos', $dados)->assertCreated();

        $modulo = Module::where('slug', $dados['slug'])->firstOrFail();

        $this->assertSame([$base->slug], $modulo->dependencies);
    }

    /** E um slug que não existe não entra como dependência. */
    public function test_uma_dependencia_inventada_e_recusada(): void
    {
        $this->actingAs($this->dono)
            ->postJson('/api/v1/plataforma/react/modulos', $this->moduloValido([
                'dependencies' => ['modulo-que-nao-existe'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('dependencies.0');
    }

    /** Nem ele mesmo: `moduleSlugsWithDependencies()` andaria em círculos. */
    public function test_um_modulo_nao_depende_de_si_mesmo(): void
    {
        $dados = $this->moduloValido();
        $dados['dependencies'] = [$dados['slug']];

        // O slug ainda não existe na tabela, por isso a regra `exists` é a
        // primeira a falhar; o que se prova é que NÃO é aceite.
        $this->actingAs($this->dono)
            ->postJson('/api/v1/plataforma/react/modulos', $dados)
            ->assertStatus(422);
    }

    /**
     * O ÍCONE GUARDA-SE PELADO e sai com o `fa-`.
     *
     * O menu em Blade monta `fas fa-{{ $icon }}`: guardar `fa-cubes` dava
     * `fa-fa-cubes` e um quadrado vazio no menu de quem paga.
     */
    public function test_o_icone_guarda_se_sem_o_prefixo_e_devolve_se_com_ele(): void
    {
        $dados = $this->moduloValido(['icon' => 'fa-cubes']);

        $this->actingAs($this->dono)
            ->postJson('/api/v1/plataforma/react/modulos', $dados)->assertCreated();

        $modulo = Module::where('slug', $dados['slug'])->firstOrFail();

        $this->assertSame('cubes', $modulo->icon);

        $json = $this->actingAs($this->dono)
            ->getJson("/api/v1/plataforma/react/modulos/{$modulo->id}")->assertOk()->json();

        $this->assertSame('fa-cubes', $json['ficha']['icon']);
    }

    public function test_um_modulo_de_nucleo_nao_se_apaga(): void
    {
        $modulo = $this->moduloNovo();
        $modulo->update(['is_core' => true]);

        $this->actingAs($this->dono)
            ->deleteJson("/api/v1/plataforma/react/modulos/{$modulo->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('modules', ['id' => $modulo->id]);
    }

    public function test_um_modulo_num_plano_nao_se_apaga(): void
    {
        $modulo = $this->moduloNovo();

        $plano = Plan::create([
            'name' => 'Com Módulo', 'slug' => 'commodulo-'.uniqid(),
            'description' => 'x', 'price_monthly' => 1000, 'price_yearly' => 10000,
        ]);

        $plano->modules()->sync([$modulo->id]);

        $this->actingAs($this->dono)
            ->deleteJson("/api/v1/plataforma/react/modulos/{$modulo->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('modules', ['id' => $modulo->id]);
    }

    /**
     * QUEM DEPENDE DELE também o prende.
     *
     * Esta razão não era verificada em sítio nenhum: apagar um módulo de que
     * outro depende deixava a dependência a apontar para um slug morto, e
     * `moduleSlugsWithDependencies()` a devolver um módulo que não existe.
     */
    public function test_um_modulo_de_que_outro_depende_nao_se_apaga(): void
    {
        $base = $this->moduloNovo('tesouraria-'.uniqid());
        $dependente = $this->moduloNovo();
        $dependente->update(['dependencies' => [$base->slug]]);

        $this->actingAs($this->dono)
            ->deleteJson("/api/v1/plataforma/react/modulos/{$base->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('modules', ['id' => $base->id]);
    }

    public function test_um_modulo_solto_apaga_se(): void
    {
        $modulo = $this->moduloNovo();

        $this->actingAs($this->dono)
            ->deleteJson("/api/v1/plataforma/react/modulos/{$modulo->id}")
            ->assertOk();

        $this->assertDatabaseMissing('modules', ['id' => $modulo->id]);
    }

    /** Juntar um módulo ao plano liga-o em quem já tem esse plano. */
    public function test_juntar_um_modulo_ao_plano_liga_o_nas_empresas_que_o_tem(): void
    {
        $modulo = $this->moduloNovo();

        $plano = Plan::create([
            'name' => 'A Crescer', 'slug' => 'crescer-'.uniqid(),
            'description' => 'x', 'price_monthly' => 1000, 'price_yearly' => 10000,
            'is_active' => true,
        ]);

        Subscription::create([
            'tenant_id' => $this->tenant->id, 'plan_id' => $plano->id,
            'status' => 'active', 'billing_cycle' => 'monthly', 'amount' => 1000,
            'starts_at' => now(), 'ends_at' => now()->addMonth(),
        ]);

        $this->actingAs($this->dono)->putJson("/api/v1/plataforma/react/planos/{$plano->id}", [
            'name' => $plano->name,
            'slug' => $plano->slug,
            'description' => 'x',
            'price_monthly' => 1000,
            'price_quarterly' => 2850,
            'price_semiannual' => 5400,
            'price_yearly' => 10000,
            'max_users' => 5, 'max_companies' => 1, 'max_storage_mb' => 1000,
            'max_documents' => null, 'trial_days' => 0, 'order' => 0,
            'is_active' => true, 'is_public' => false, 'is_featured' => false,
            'is_promotional' => false, 'auto_activate' => false,
            'features' => [], 'modulos' => [$modulo->id],
        ])->assertOk();

        $this->assertDatabaseHas('tenant_module', [
            'tenant_id' => $this->tenant->id,
            'module_id' => $modulo->id,
            'is_active' => 1,
        ]);
    }

    /* ─── A analítica ─────────────────────────────────────────────────── */

    private function evento(array $dados = []): AnalyticsEvent
    {
        static $n = 0;
        $n++;

        return AnalyticsEvent::create(array_merge([
            'visitor_id' => sprintf('api%05d-0000-4000-a000-000000000001', $n),
            'session_id' => sprintf('api%05d-0000-4000-a000-000000000002', $n),
            'type' => 'pageview',
            'event_name' => 'pageview',
            'path' => '/',
            'created_at' => now(),
        ], $dados));
    }

    private function analitica(array $filtros = []): array
    {
        return $this->actingAs($this->dono)
            ->getJson('/api/v1/plataforma/react/analitica?'.http_build_query($filtros))
            ->assertOk()->json();
    }

    public function test_a_analitica_diz_quem_esta_no_site_agora(): void
    {
        AnalyticsEvent::query()->delete();

        $this->evento(['visitor_id' => 'agora-1', 'created_at' => now()->subMinute()]);
        $this->evento(['visitor_id' => 'agora-2', 'created_at' => now()->subMinutes(3)]);
        // Este já saiu: fora dos cinco minutos.
        $this->evento(['visitor_id' => 'saiu-1', 'created_at' => now()->subMinutes(30)]);

        $this->assertSame(2, $this->analitica()['agora']['visitantes']);
    }

    /** «Agora» é agora: não obedece ao período escolhido. */
    public function test_o_agora_nao_obedece_ao_periodo(): void
    {
        AnalyticsEvent::query()->delete();
        $this->evento(['created_at' => now()->subMinute()]);

        $this->assertSame(1, $this->analitica(['periodo' => '90d'])['agora']['visitantes']);
    }

    public function test_classifica_de_onde_vieram_em_canais(): void
    {
        AnalyticsEvent::query()->delete();

        $this->evento(['visitor_id' => 'org-1', 'referrer' => 'https://www.google.com/search?q=erp']);
        $this->evento(['visitor_id' => 'soc-1', 'referrer' => 'http://m.facebook.com']);
        $this->evento(['visitor_id' => 'dir-1', 'referrer' => null]);

        $canais = collect($this->analitica()['origens']['canais'])->pluck('visitantes', 'canal');

        $this->assertSame(1, $canais['orgânico'] ?? 0);
        $this->assertSame(1, $canais['social'] ?? 0);
        $this->assertSame(1, $canais['directo'] ?? 0);
    }

    /** O canal de um visitante é por onde ele ENTROU. */
    public function test_o_canal_e_a_primeira_visita_e_nao_a_ultima(): void
    {
        AnalyticsEvent::query()->delete();

        $this->evento(['visitor_id' => 'ent-1', 'referrer' => 'http://m.facebook.com', 'created_at' => now()->subMinutes(10)]);
        $this->evento(['visitor_id' => 'ent-1', 'referrer' => 'https://soserp.vip/', 'created_at' => now()->subMinutes(9)]);

        $canais = collect($this->analitica()['origens']['canais'])->pluck('visitantes', 'canal');

        $this->assertSame(1, $canais['social'] ?? 0);
        $this->assertArrayNotHasKey('interno', $canais->all());
    }

    public function test_filtrar_por_canal(): void
    {
        AnalyticsEvent::query()->delete();

        $this->evento(['visitor_id' => 'f-soc', 'referrer' => 'http://m.facebook.com']);
        $this->evento(['visitor_id' => 'f-dir', 'referrer' => null]);

        $this->assertSame(1, $this->analitica(['canal' => 'social'])['numeros']['visitantes']);
    }

    public function test_um_canal_inventado_e_recusado(): void
    {
        // Sem isto, um canal escrito à mão no endereço devolvia o ecrã todo sem
        // filtro nenhum — e ninguém percebia que o filtro não pegou.
        $this->actingAs($this->dono)
            ->getJson('/api/v1/plataforma/react/analitica?canal=inventado')
            ->assertStatus(422);
    }

    public function test_filtrar_por_pais(): void
    {
        AnalyticsEvent::query()->delete();

        $this->evento(['visitor_id' => 'p-ao', 'country' => 'AO']);
        $this->evento(['visitor_id' => 'p-pt', 'country' => 'PT']);

        $this->assertSame(1, $this->analitica(['pais' => 'AO'])['numeros']['visitantes']);
    }

    public function test_os_paises_vem_com_bandeira_e_nome(): void
    {
        AnalyticsEvent::query()->delete();
        $this->evento(['visitor_id' => 'b-ao', 'country' => 'AO']);

        $pais = $this->analitica()['regiao']['paises'][0];

        // A bandeira e o nome vêm do ICU, que só existe no servidor: o ecrã em
        // Blade chamava-os a meio do HTML e o React não os podia calcular.
        $this->assertSame('AO', $pais['codigo']);
        $this->assertNotSame('', trim($pais['bandeira']));
        $this->assertStringContainsStringIgnoringCase('angola', $pais['nome']);
    }

    public function test_mostra_os_termos_mais_procurados(): void
    {
        AnalyticsEvent::query()->delete();

        foreach (['amidol', 'amidol', 'paracetamol'] as $i => $termo) {
            $this->evento([
                'visitor_id' => "proc-{$i}",
                'type' => 'search',
                'event_name' => 'search_pos',
                'search_term' => $termo,
            ]);
        }

        $procuras = $this->analitica()['procuras'];

        $this->assertSame('amidol', $procuras[0]['termo']);
        $this->assertSame(2, $procuras[0]['vezes']);
    }

    public function test_a_taxa_de_rejeicao_conta_sessoes_de_uma_pagina(): void
    {
        AnalyticsEvent::query()->delete();

        // Uma sessão que viu duas páginas.
        $this->evento(['session_id' => 'duas', 'path' => '/']);
        $this->evento(['session_id' => 'duas', 'path' => '/modulos/rh']);
        // Uma que viu uma e saiu.
        $this->evento(['session_id' => 'uma', 'path' => '/']);

        $this->assertSame(50, $this->analitica()['numeros']['rejeicao']);
    }

    public function test_quem_esta_autenticado_nao_conta_como_visitante(): void
    {
        AnalyticsEvent::query()->delete();

        $this->evento(['visitor_id' => 'anon-1']);
        $this->evento(['visitor_id' => 'user-1', 'path' => '/pos', 'user_id' => $this->user->id]);

        $json = $this->analitica();

        $this->assertSame(1, $json['numeros']['visitantes']);
        $this->assertSame(1, $json['agora']['visitantes']);
        // E aparece, com nome, no painel próprio.
        $this->assertSame(1, $json['utilizadores']['online']);
        $this->assertSame($this->user->name, $json['utilizadores']['lista'][0]['nome']);
        $this->assertSame('/pos', $json['utilizadores']['lista'][0]['pagina']);
    }

    /**
     * A TENDÊNCIA COMPARAVA MAÇÃS COM LARANJAS.
     *
     * O período de agora conta só quem não tem sessão iniciada; o anterior
     * contava TODOS. Num sistema com clientes a trabalhar, a seta apontava para
     * baixo por construção.
     */
    public function test_a_tendencia_compara_visitantes_com_visitantes(): void
    {
        AnalyticsEvent::query()->delete();

        // Período anterior: um visitante anónimo e dez clientes a trabalhar.
        $this->evento(['visitor_id' => 'ant-anon', 'created_at' => now()->subDays(10)]);

        for ($i = 0; $i < 10; $i++) {
            $this->evento([
                'visitor_id' => "ant-user-{$i}",
                'user_id' => $this->user->id,
                'created_at' => now()->subDays(10),
            ]);
        }

        // Agora: dois visitantes anónimos. Subiu de 1 para 2 = +100%.
        $this->evento(['visitor_id' => 'hoje-1', 'created_at' => now()->subDay()]);
        $this->evento(['visitor_id' => 'hoje-2', 'created_at' => now()->subDay()]);

        $this->assertSame(100.0, (float) $this->analitica(['periodo' => '7d'])['numeros']['tendencia']);
    }

    /** Sem período anterior, a tendência é nula e não «0%». */
    public function test_sem_periodo_anterior_a_tendencia_e_nula(): void
    {
        AnalyticsEvent::query()->delete();
        $this->evento(['created_at' => now()->subHour()]);

        $this->assertNull($this->analitica(['periodo' => 'today'])['numeros']['tendencia']);
    }

    public function test_o_percurso_de_um_visitante_tem_morada_propria(): void
    {
        AnalyticsEvent::query()->delete();

        $this->evento(['visitor_id' => 'perc-1', 'path' => '/', 'referrer' => 'http://m.facebook.com', 'created_at' => now()->subMinutes(5)]);
        $this->evento(['visitor_id' => 'perc-1', 'path' => '/modulos/rh', 'created_at' => now()->subMinutes(4)]);
        $this->evento(['visitor_id' => 'perc-1', 'path' => '/modulos/hotel', 'created_at' => now()->subMinutes(3)]);

        $json = $this->actingAs($this->dono)
            ->getJson('/api/v1/plataforma/react/analitica/percurso/perc-1')->assertOk()->json();

        $this->assertSame(['/', '/modulos/rh', '/modulos/hotel'], array_column($json['passos'], 'pagina'));
        // E a cabeça diz por onde entrou, que é o que faltava saber.
        $this->assertSame('social', $json['cabeca']['canal']);
        $this->assertSame('Facebook', $json['cabeca']['fonte']);
    }

    /**
     * O CUSTO NÃO CRESCE COM O TAMANHO DO PERÍODO.
     *
     * Aqui esteve um ciclo com uma consulta POR DIA: sete dias custavam oito
     * consultas e noventa custavam trinta e uma, só para desenhar o gráfico.
     */
    public function test_o_custo_da_analitica_nao_cresce_com_o_periodo(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->evento(['created_at' => now()->subDays($i)]);
        }

        $contar = function (string $periodo): int {
            \Illuminate\Support\Facades\DB::flushQueryLog();
            \Illuminate\Support\Facades\DB::enableQueryLog();

            $this->actingAs($this->dono)
                ->getJson("/api/v1/plataforma/react/analitica?periodo={$periodo}")->assertOk();

            $n = count(\Illuminate\Support\Facades\DB::getQueryLog());
            \Illuminate\Support\Facades\DB::disableQueryLog();

            return $n;
        };

        // UM PEDIDO A FRIO conta a mais: a sessão, o utilizador e a cache de
        // permissões só se carregam na primeira vez. Mede-se depois de aquecer,
        // senão o primeiro período medido parece sempre o mais caro.
        $contar('7d');

        $curto = $contar('7d');
        $longo = $contar('90d');

        $this->assertSame(
            $curto,
            $longo,
            "7 dias custaram {$curto} consultas e 90 custaram {$longo} — a série voltou a ser dia a dia",
        );
    }

    /* ─── As empresas ─────────────────────────────────────────────────── */

    private function empresaSolta(string $nome = 'Empresa Solta'): Tenant
    {
        return Tenant::create([
            'name' => $nome, 'slug' => 'solta-'.uniqid(),
            'nif' => '5'.random_int(100000000, 999999999),
            'email' => 'solta'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);
    }

    public function test_a_lista_de_empresas_traz_os_sinais_de_vida_e_as_contagens(): void
    {
        $this->empresaSolta();

        $json = $this->actingAs($this->dono)
            ->getJson('/api/v1/plataforma/react/empresas?por_pagina=50')->assertOk()->json();

        $this->assertArrayHasKey('vazia', $json['contagens']);
        $this->assertNotEmpty($json['empresas']);
        $this->assertArrayHasKey('vida', $json['empresas'][0]);
        $this->assertArrayHasKey('subscricao', $json['empresas'][0]);
    }

    /**
     * O FILTRO DE ESTADO NÃO MEXE NAS CONTAGENS: clicar num cartão zerava os
     * outros, e nunca se conseguia trocar de cartão.
     */
    public function test_filtrar_por_estado_nao_zera_as_contagens_dos_outros(): void
    {
        $this->empresaSolta();

        $todas = $this->actingAs($this->dono)->getJson('/api/v1/plataforma/react/empresas')->json('contagens');
        $filtradas = $this->actingAs($this->dono)->getJson('/api/v1/plataforma/react/empresas?estado=activa')->json('contagens');

        $this->assertSame($todas, $filtradas);
    }

    /** A ficha para ver traz a lista de pessoas — sem se perder atrás da contagem. */
    public function test_ver_uma_empresa_traz_as_pessoas_e_a_contagem(): void
    {
        $empresa = $this->empresaSolta();
        $empresa->users()->attach($this->user->id, ['is_active' => true, 'joined_at' => now()]);

        $json = $this->actingAs($this->dono)
            ->getJson("/api/v1/plataforma/react/empresas/{$empresa->id}")->assertOk()->json('empresa');

        $this->assertSame(1, $json['utilizadores']);
        $this->assertSame($this->user->name, $json['pessoas'][0]['nome']);
    }

    /** O país é da lista, ou não entra — o SAF-T leva-o. */
    public function test_um_pais_inventado_e_recusado(): void
    {
        $this->actingAs($this->dono)->postJson('/api/v1/plataforma/react/empresas', [
            'name' => 'Sem País', 'slug' => 'sem-pais-'.uniqid(), 'email' => 'sp'.uniqid().'@exemplo.ao',
            'country' => 'ZZ', 'max_users' => 5, 'max_storage_mb' => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors('country');
    }

    /** Apagar mesmo exige o nome escrito exactamente como está. */
    public function test_apagar_em_definitivo_exige_o_nome_certo(): void
    {
        $empresa = $this->empresaSolta('Casa A Apagar');

        $this->actingAs($this->dono)
            ->deleteJson("/api/v1/plataforma/react/empresas/{$empresa->id}", ['confirmacao' => 'casa a apagar'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirmacao');

        $this->assertNotNull(Tenant::withTrashed()->find($empresa->id));
    }

    public function test_o_que_se_perde_diz_se_antes_de_apagar(): void
    {
        $empresa = $this->empresaSolta();

        $json = $this->actingAs($this->dono)
            ->getJson("/api/v1/plataforma/react/empresas/{$empresa->id}/apagar")->assertOk()->json();

        $this->assertSame($empresa->name, $json['nome']);
        $this->assertNotEmpty($json['perdas']);
        $this->assertNull($json['impedido']);
    }

    /** Procurar alguém para juntar: nada abaixo de dois caracteres, e nunca o dono. */
    public function test_procurar_pessoas_pede_dois_caracteres_e_esconde_os_donos(): void
    {
        $empresa = $this->empresaSolta();

        $this->actingAs($this->dono)
            ->getJson("/api/v1/plataforma/react/empresas/{$empresa->id}/utilizadores/procurar?q=D")
            ->assertOk()->assertExactJson(['pessoas' => []]);

        $pessoas = $this->actingAs($this->dono)
            ->getJson("/api/v1/plataforma/react/empresas/{$empresa->id}/utilizadores/procurar?q=Dono da Plat")
            ->assertOk()->json('pessoas');

        $this->assertSame([], $pessoas, 'o dono da plataforma não se junta a empresas por aqui');
    }

    /**
     * O RESUMO DO PLANO VEM DA REGRA QUE GRAVA. Um anual com a oferta dá
     * catorze meses; sem ela, doze.
     */
    public function test_o_resumo_do_plano_segue_a_regra_do_acordo(): void
    {
        $empresa = $this->empresaSolta();
        $plano = Plan::create([
            'name' => 'Resumo', 'slug' => 'resumo-'.uniqid(), 'description' => 'x',
            'price_monthly' => 1000, 'price_yearly' => 10000, 'is_active' => true,
        ]);

        $com = $this->actingAs($this->dono)->postJson("/api/v1/plataforma/react/empresas/{$empresa->id}/plano/resumo", [
            'plano' => $plano->id, 'ciclo' => 'yearly', 'com_oferta' => true,
        ])->assertOk()->json();

        $sem = $this->actingAs($this->dono)->postJson("/api/v1/plataforma/react/empresas/{$empresa->id}/plano/resumo", [
            'plano' => $plano->id, 'ciclo' => 'yearly', 'com_oferta' => false,
        ])->assertOk()->json();

        $this->assertTrue($com['oferta_aplicavel']);
        $this->assertGreaterThan($sem['dias'], $com['dias']);
    }

    /** Um plano à medida a zero gasta a cortesia única do cliente: recusa-se. */
    public function test_um_plano_a_medida_a_zero_e_recusado(): void
    {
        $empresa = $this->empresaSolta();
        $modulo = $this->moduloNovo();

        $this->actingAs($this->dono)->postJson("/api/v1/plataforma/react/empresas/{$empresa->id}/plano-a-medida", [
            'nome' => 'À Medida', 'modulos' => [$modulo->slug], 'precos' => [$modulo->slug => 0],
            'utilizadores' => 5, 'empresas' => 1, 'armazenamento' => 1000, 'ciclo' => 'monthly',
        ])->assertStatus(422)->assertJsonValidationErrors('precos');

        $this->assertDatabaseMissing('plans', ['name' => 'À Medida']);
    }
}
