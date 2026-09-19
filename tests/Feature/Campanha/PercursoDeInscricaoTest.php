<?php

namespace Tests\Feature\Campanha;

use App\Http\Controllers\ModulePagesController;
use App\Models\AnalyticsEvent;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Campanha\ResultadosDaCampanha;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * O PERCURSO DA CAMPANHA SOSERP (18/09/2026): da página do módulo ao cadastro.
 *
 * As páginas /modulos/* levavam ao /register a perder o módulo (Starter por
 * omissão) e sem o Pixel. Agora a aterragem lembra o módulo e a origem na
 * SESSÃO (nada nos URLs), o registo pré-selecciona o plano do módulo, o Pixel
 * está nas páginas dos módulos (só com consentimento), e o CompleteRegistration
 * é um por empresa (event_id determinístico).
 */
class PercursoDeInscricaoTest extends TestCase
{
    use DatabaseTransactions;

    /** slug do módulo => slug do plano, tal como o config promete. */
    private const MODULOS = [
        'vendas' => 'pacote-vendas',
        'rh' => 'pacote-rh',
        'hotel' => 'pacote-hotel',
        'salao' => 'pacote-salao',
        'oficina' => 'pacote-oficina',
        'restaurant' => 'pacote-restaurante',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function plano(string $slug, array $troca = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Plano ' . $slug, 'slug' => $slug, 'description' => 'x',
            'price_monthly' => 24900, 'price_yearly' => 249000, 'trial_days' => 14,
            'max_users' => 5, 'max_companies' => 1, 'is_active' => true, 'is_public' => true,
            'auto_activate' => false, 'order' => 5,
        ], $troca));
    }

    private function props(\Illuminate\Testing\TestResponse $r): array
    {
        $r->assertOk()->assertSee('data-ecra="registo/assistente"', false);
        preg_match('/data-props="([^"]*)"/', $r->getContent(), $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
    }

    public function test_o_config_dos_modulos_espelha_o_controlador(): void
    {
        $ref = new \ReflectionClass(ModulePagesController::class);
        $modulos = $ref->getProperty('modules');
        $modulos->setAccessible(true);
        $doControlador = collect($modulos->getValue(new ModulePagesController()))
            ->mapWithKeys(fn ($m, $slug) => [$slug => $m['plan_slug']])
            ->all();

        // Nenhum módulo do controlador pode ficar de fora do mapa da campanha.
        foreach ($doControlador as $slug => $planSlug) {
            $this->assertSame($planSlug, config('campanha.modulos.' . $slug), "o módulo {$slug} diverge");
        }
    }

    public function test_cada_modulo_pre_selecciona_o_seu_plano_no_registo(): void
    {
        $starter = $this->plano('starter', ['price_monthly' => 0, 'order' => 1]);

        foreach (self::MODULOS as $modulo => $planoSlug) {
            $plano = $this->plano($planoSlug);

            // Sessão limpa por módulo — cada pessoa tem a sua (o progresso de um
            // não pode contaminar o outro).
            $this->flushSession();

            // A aterragem no módulo lembrou o plano (o que o middleware faz).
            $estado = $this->props($this->withSession(['registration_plan' => $planoSlug])->get('/register'))['estado'];

            $this->assertSame($plano->id, $estado['campos']['selected_plan_id'], "o módulo {$modulo} não pré-seleccionou {$planoSlug}");
            $this->assertNotSame($starter->id, $estado['campos']['selected_plan_id'], "o módulo {$modulo} caiu no Starter");

            $plano->forceDelete();
        }
    }

    public function test_a_aterragem_no_modulo_guarda_o_plano_e_a_origem_e_o_registo_herda(): void
    {
        $this->plano('starter', ['price_monthly' => 0, 'order' => 1]);
        $vendas = $this->plano('pacote-vendas');

        // Anúncio da Meta: aterra em /modulos/vendas com utm e fbclid.
        $this->get('/modulos/vendas?utm_source=meta&utm_medium=cpc&utm_campaign=vendas-set&fbclid=abc123')
            ->assertOk();

        // O módulo e a origem ficaram na sessão — sem nada disto ter ido no URL do registo.
        $this->assertSame('pacote-vendas', session('registration_plan'));
        $this->assertSame('meta', session('registration_acquisition.utm_source'));
        $this->assertSame('abc123', session('registration_acquisition.fbclid'));

        // O registo, aberto a seguir, já traz o plano do módulo.
        $estado = $this->props($this->get('/register'))['estado'];
        $this->assertSame($vendas->id, $estado['campos']['selected_plan_id']);
    }

    public function test_um_plano_invalido_no_modulo_nao_forca_nada(): void
    {
        $starter = $this->plano('starter', ['price_monthly' => 0, 'order' => 1]);
        // O módulo aponta um plano que não é público: cai no Starter, não rebenta.
        $this->plano('pacote-vendas', ['is_public' => false]);

        $estado = $this->props($this->withSession(['registration_plan' => 'pacote-vendas'])->get('/register'))['estado'];
        $this->assertSame($starter->id, $estado['campos']['selected_plan_id']);
    }

    public function test_o_pixel_esta_nas_paginas_dos_modulos_so_com_consentimento(): void
    {
        $this->plano('pacote-vendas'); // para a página do módulo não rebentar
        SystemSetting::set('facebook_pixel_id', '1020506307494204');

        $r = $this->get('/modulos/vendas')->assertOk();

        // O Pixel está lá, mas TRAVADO por consentimento (type text/plain) — não
        // executa sem o marketing aceite, e não há CompleteRegistration numa visita.
        $r->assertSee('1020506307494204', false);
        $r->assertSee('data-consentimento="marketing"', false);
        $r->assertSee("fbq('init'", false);
        $r->assertDontSee('CompleteRegistration', false);
    }

    public function test_sem_pixel_configurado_a_pagina_do_modulo_nao_o_mete(): void
    {
        $this->plano('pacote-vendas');
        SystemSetting::set('facebook_pixel_id', '');

        $this->get('/modulos/vendas')->assertOk()->assertDontSee("fbq('init'", false);
    }
}
