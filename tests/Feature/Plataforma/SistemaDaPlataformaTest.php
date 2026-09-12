<?php

namespace Tests\Feature\Plataforma;

use App\Models\User;
use App\Services\Plataforma\ExecutarArtisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

/**
 * AS FERRAMENTAS DE SISTEMA DA PLATAFORMA — optimização, comandos, scripts e
 * actualizações. Os ecrãs passaram a React; o que se prova aqui é a API.
 *
 * Nenhum ensaio corre um comando a sério: o `ExecutarArtisan` é trocado por um
 * que só anota o que lhe pediram. Um `config:cache` corrido aqui gravava a
 * configuração de ensaio em `bootstrap/cache` e partia a aplicação local. E o
 * `storage` aponta para uma pasta temporária, para o histórico e o log não
 * mexerem nos verdadeiros.
 */
class SistemaDaPlataformaTest extends TenantTestCase
{
    private const API = '/api/v1/plataforma/react';

    private User $dono;

    private string $pastaTemporaria;

    private string $storageOriginal;

    /** @var array<int, array{0: string, 1: array}> */
    private array $pedidos = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dono = User::create(['name' => 'Dono', 'email' => 'dono_'.uniqid().'@exemplo.ao', 'password' => bcrypt('x')]);
        $this->dono->forceFill(['is_super_admin' => true])->save();

        $pedidos = &$this->pedidos;
        $this->app->instance(ExecutarArtisan::class, new class($pedidos) extends ExecutarArtisan {
            public function __construct(private array &$pedidos) {}

            public function correr(string $comando, array $parametros = []): array
            {
                $this->pedidos[] = [$comando, $parametros];

                return ['codigo' => 0, 'saida' => "feito: {$comando}"];
            }
        });

        $this->storageOriginal = $this->app->storagePath();
        $this->pastaTemporaria = sys_get_temp_dir().DIRECTORY_SEPARATOR.'soserp-sistema-'.uniqid();
        File::ensureDirectoryExists($this->pastaTemporaria.'/logs');
        $this->app->useStoragePath($this->pastaTemporaria);

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->storageOriginal);
        File::deleteDirectory($this->pastaTemporaria);

        parent::tearDown();
    }

    public static function moradas(): array
    {
        return [
            'otimização' => ['/otimizacao', '/superadmin/system-optimization'],
            'comandos' => ['/comandos', '/superadmin/system-commands'],
            'scripts' => ['/scripts', '/superadmin/script-runner'],
            'actualizações' => ['/actualizacoes', '/superadmin/system-updates'],
        ];
    }

    /** @dataProvider moradas */
    public function test_um_utilizador_de_empresa_nao_entra(string $api, string $pagina): void
    {
        $this->getJson(self::API.$api)->assertForbidden();
        $this->assertNotEquals(200, $this->get($pagina)->getStatusCode());
    }

    /** @dataProvider moradas */
    public function test_o_dono_entra_na_api_e_na_pagina(string $api, string $pagina): void
    {
        $this->actingAs($this->dono)->getJson(self::API.$api)->assertOk();
        $this->actingAs($this->dono)->get($pagina)->assertOk()->assertSee('data-ecra="plataforma/', false);
    }

    /* ─── Otimização ─────────────────────────────────────────────────── */

    public function test_limpar_os_caches_corre_os_quatro_comandos(): void
    {
        $this->actingAs($this->dono)->postJson(self::API.'/otimizacao/limpar-caches')->assertOk();

        $this->assertSame(['cache:clear', 'config:clear', 'route:clear', 'view:clear'], array_column($this->pedidos, 0));
    }

    public function test_otimizar_grava_os_tres_caches(): void
    {
        $this->actingAs($this->dono)->postJson(self::API.'/otimizacao/otimizar')->assertOk();

        $this->assertSame(['config:cache', 'route:cache', 'view:cache'], array_column($this->pedidos, 0));
    }

    public function test_o_user_ini_leva_os_valores_escolhidos(): void
    {
        $valores = ['environment' => 'production', 'validate_timestamps' => 0, 'revalidate_freq' => 0, 'max_input_vars' => 3000, 'memory_limit' => '768M', 'max_execution_time' => 360];

        $r = $this->actingAs($this->dono)->get('/superadmin/system-optimization/user-ini?'.http_build_query($valores))->assertOk();

        $conteudo = $r->streamedContent();
        $this->assertStringContainsString('memory_limit = 768M', $conteudo);
        $this->assertStringContainsString('opcache.validate_timestamps = 0', $conteudo);
        $this->assertStringContainsString('max_input_vars = 3000', $conteudo);
    }

    /** O que vai parar a um ficheiro que o PHP do servidor lê é validado antes. */
    public function test_um_valor_disparatado_nao_entra_no_user_ini(): void
    {
        $this->actingAs($this->dono)->postJson(self::API.'/otimizacao/user-ini', [
            'environment' => 'production', 'validate_timestamps' => 0, 'revalidate_freq' => 0, 'max_input_vars' => 3000,
            'memory_limit' => "512M\nauto_prepend_file = /tmp/x.php", 'max_execution_time' => 360,
        ])->assertStatus(422)->assertJsonValidationErrors('memory_limit');
    }

    /* ─── Comandos ───────────────────────────────────────────────────── */

    public function test_correr_um_comando_devolve_as_linhas_e_fica_no_historico(): void
    {
        $r = $this->actingAs($this->dono)->postJson(self::API.'/comandos/migrate_status', ['parametros' => []])->assertOk();

        $this->assertTrue($r->json('ok'));
        $this->assertContains('feito: migrate:status', array_column($r->json('linhas'), 'texto'));
        $this->assertSame('migrate_status', $r->json('historico.0.command_key'));
        $this->assertFileExists($this->pastaTemporaria.'/logs/command_history.json');
    }

    public function test_o_migrate_vai_sempre_com_force(): void
    {
        $this->actingAs($this->dono)->postJson(self::API.'/comandos/migrate', ['parametros' => []])->assertOk();

        $this->assertSame([['migrate', ['--force' => true]]], $this->pedidos);
    }

    public function test_um_comando_que_nao_existe_e_404(): void
    {
        $this->actingAs($this->dono)->postJson(self::API.'/comandos/db_wipe', ['parametros' => []])->assertNotFound();
        $this->assertSame([], $this->pedidos);
    }

    public function test_os_parametros_sao_validados_contra_a_base(): void
    {
        $this->actingAs($this->dono)->postJson(self::API.'/comandos/attach_module_tenant', ['parametros' => ['module_slug' => 'nao-existe']])
            ->assertStatus(422)->assertJsonValidationErrors('parametros.module_slug');

        $this->assertSame([], $this->pedidos);
    }

    /** O seeder vinha do browser e ia direito ao db:seed. Só os da lista. */
    public function test_so_se_semeia_um_seeder_da_lista(): void
    {
        $this->actingAs($this->dono)->postJson(self::API.'/comandos/seeder', ['seeder' => 'App\\Qualquer\\Classe'])
            ->assertStatus(422)->assertJsonValidationErrors('seeder');

        $this->assertSame([], $this->pedidos);

        $seeder = collect($this->actingAs($this->dono)->getJson(self::API.'/comandos')->json('seeders'))->first();

        if (! $seeder) {
            $this->markTestSkipped('não há seeders em database/seeders');
        }

        $this->actingAs($this->dono)->postJson(self::API.'/comandos/seeder', ['seeder' => $seeder['namespace']])->assertOk();
        $this->assertSame([['db:seed', ['--class' => $seeder['namespace'], '--force' => true]]], $this->pedidos);
    }

    /* ─── Scripts ────────────────────────────────────────────────────── */

    /** Com `../` o nome ia para o `require` e corria qualquer PHP do servidor. */
    public function test_um_script_fora_da_pasta_e_recusado(): void
    {
        $this->actingAs($this->dono)->postJson(self::API.'/scripts/correr', ['script' => '../artisan'])
            ->assertStatus(422)->assertJsonValidationErrors('script');

        $this->actingAs($this->dono)->postJson(self::API.'/scripts/correr', ['script' => 'nao_existe.php'])
            ->assertStatus(422)->assertJsonValidationErrors('script');
    }

    public function test_a_lista_de_scripts_so_tem_php(): void
    {
        $nomes = array_column($this->actingAs($this->dono)->getJson(self::API.'/scripts')->assertOk()->json('scripts'), 'nome');

        $this->assertNotEmpty($nomes);
        $this->assertSame([], array_values(array_filter($nomes, fn ($n) => ! str_ends_with($n, '.php'))));
    }

    /** O log lê-se pelo fim: só as últimas cem linhas, das mais recentes para as mais antigas. */
    public function test_o_log_mostra_so_o_fim_do_ficheiro(): void
    {
        $linhas = array_map(fn ($i) => "[2026-09-13] local.INFO: linha {$i}", range(1, 5000));
        File::put($this->pastaTemporaria.'/logs/laravel.log', implode("\n", $linhas)."\n");

        $log = $this->actingAs($this->dono)->getJson(self::API.'/scripts/log')->assertOk()->json('log');

        $this->assertCount(100, $log);
        $this->assertSame('[2026-09-13] local.INFO: linha 5000', $log[0]);
        $this->assertSame('[2026-09-13] local.INFO: linha 4901', $log[99]);
    }

    /* ─── Actualizações ──────────────────────────────────────────────── */

    private function github(): void
    {
        Http::fake(['api.github.com/*' => Http::response([
            ['tag_name' => '99.0.0', 'name' => 'Futura', 'body' => 'Tudo novo', 'published_at' => '2026-09-01T10:00:00Z', 'prerelease' => false],
            ['tag_name' => '0.0.1', 'name' => 'Antiga', 'body' => '', 'published_at' => '2020-01-01T10:00:00Z', 'prerelease' => false],
        ])]);
    }

    public function test_as_releases_dizem_qual_e_mais_recente(): void
    {
        $this->github();

        $r = collect($this->actingAs($this->dono)->getJson(self::API.'/actualizacoes/releases')->assertOk()->json('releases'))->keyBy('tag_name');

        $this->assertTrue($r['99.0.0']['is_newer']);
        $this->assertFalse($r['0.0.1']['is_newer']);
    }

    /**
     * A versão vinha do browser e entrava no endereço e no caminho do ficheiro.
     * Agora só uma etiqueta de release que o GitHub devolve.
     */
    public function test_so_se_instala_uma_release_do_repositorio(): void
    {
        $this->github();

        $this->actingAs($this->dono)->postJson(self::API.'/actualizacoes/instalar', ['versao' => '../../.env'])
            ->assertStatus(422)->assertJsonValidationErrors('versao');

        $this->actingAs($this->dono)->postJson(self::API.'/actualizacoes/instalar', ['versao' => '98.0.0'])
            ->assertStatus(422)->assertJsonValidationErrors('versao');

        $this->assertSame([], $this->pedidos, 'nada correu');
        Http::assertNotSent(fn ($pedido) => str_contains($pedido->url(), 'archive/refs/tags'));
    }
}
