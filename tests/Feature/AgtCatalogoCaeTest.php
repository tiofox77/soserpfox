<?php

namespace Tests\Feature;

use App\Services\AGT\GestaoAgt;
use Database\Seeders\AGTCaeCodeSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AgtDeEnsaio;
use Tests\TenantTestCase;

/**
 * A lista inteira da CAE-Rev.2 de Angola (INE) no catálogo da AGT.
 *
 * Até 23/09/2026 o catálogo tinha 8 códigos de 5 dígitos, e 4 deles eram da
 * CAE portuguesa. O ecrã só oferecia esses 8.
 */
class AgtCatalogoCaeTest extends TenantTestCase
{
    use AgtDeEnsaio;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->comModulo('invoicing')->comPermissoes('invoicing.agt.view', 'invoicing.agt.edit');
    }

    private function semear(): void
    {
        $this->seed(AGTCaeCodeSeeder::class);
    }

    public function test_carrega_a_lista_inteira_com_a_hierarquia(): void
    {
        $this->semear();

        $porNivel = DB::table('agt_cae_codes')->where('is_active', true)
            ->selectRaw('level, count(*) n')->groupBy('level')->pluck('n', 'level')->all();

        $this->assertSame(21, (int) $porNivel['section']);
        $this->assertSame(88, (int) $porNivel['division']);
        $this->assertSame(238, (int) $porNivel['group']);
        $this->assertSame(413, (int) $porNivel['class']);
        $this->assertSame(575, (int) $porNivel['subclass']);

        // Cada código tem o pai no catálogo, do nível de cima.
        $orfaos = DB::table('agt_cae_codes as f')
            ->leftJoin('agt_cae_codes as p', 'p.code', '=', 'f.parent_code')
            ->where('f.level', '<>', 'section')
            ->whereNull('p.id')
            ->count();
        $this->assertSame(0, $orfaos);

        $restaurante = DB::table('agt_cae_codes')->where('code', '56101')->first();
        $this->assertSame('subclass', $restaurante->level);
        $this->assertSame('5610', $restaurante->parent_code);
        $this->assertSame('Restaurantes tipo tradicional', $restaurante->description);

        // A gralha do PDF («692 692 69200») não pode deixar a subclasse sem classe.
        $this->assertSame('6920', DB::table('agt_cae_codes')->where('code', '69200')->value('parent_code'));
    }

    public function test_os_codigos_que_nao_sao_de_angola_ficam_inactivos_e_os_antigos_passam_a_subclasse(): void
    {
        // Como estava em produção: 5 dígitos gravados como «class», e 96021 que não existe em Angola.
        DB::table('agt_cae_codes')->whereIn('code', ['47190', '96021'])->delete();
        DB::table('agt_cae_codes')->insert([
            ['code' => '47190', 'level' => 'class', 'parent_code' => '47', 'description' => 'antigo', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '96021', 'level' => 'class', 'parent_code' => '96', 'description' => 'Salões de cabeleireiro', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->semear();

        $antigo = DB::table('agt_cae_codes')->where('code', '47190')->first();
        $this->assertSame('subclass', $antigo->level);
        $this->assertSame('4719', $antigo->parent_code);
        $this->assertTrue((bool) $antigo->is_active);

        // Não se apaga (uma empresa pode tê-lo gravado), mas sai da lista.
        $this->assertFalse((bool) DB::table('agt_cae_codes')->where('code', '96021')->value('is_active'));
        $this->assertFalse(GestaoAgt::classesCae()->contains('code', '96021'));
    }

    public function test_o_ecra_recebe_as_575_subclasses_agrupadas_pela_divisao(): void
    {
        $this->semear();

        $cae = $this->getJson('/api/v1/invoicing/react/agt/opcoes')->assertOk()->json('cae');

        $this->assertCount(575, $cae);
        $restaurante = collect($cae)->firstWhere('codigo', '56101');
        $this->assertSame('Restaurantes tipo tradicional', $restaurante['descricao']);
        $this->assertSame('56 · Restaurantes e similares', $restaurante['divisao']);
        // Só subclasses: nem a classe 5610 nem a divisão 56.
        $this->assertNull(collect($cae)->firstWhere('codigo', '5610'));
    }

    public function test_guardar_aceita_uma_subclasse_oficial_e_recusa_um_codigo_que_saiu(): void
    {
        DB::table('agt_cae_codes')->updateOrInsert(['code' => '96021'], ['level' => 'class', 'parent_code' => '96', 'description' => 'x', 'is_active' => true]);
        $this->semear();

        $base = ['agt_auto_submit' => true, 'agt_require_validation' => true];

        $this->postJson('/api/v1/invoicing/react/agt/definicoes', $base + ['agt_eac_code' => '96020'])->assertOk();
        $this->postJson('/api/v1/invoicing/react/agt/definicoes', $base + ['agt_eac_code' => '96021'])
            ->assertStatus(422)->assertJsonValidationErrors('agt_eac_code');
    }

    public function test_diz_que_empresas_tem_um_cae_fora_da_lista_sem_o_mudar(): void
    {
        $this->definicoesAgt()->update(['agt_eac_code' => '62020']);

        Artisan::call('db:seed', ['--class' => AGTCaeCodeSeeder::class, '--force' => true]);

        $this->assertMatchesRegularExpression("/62020: empresas [\\d, ]*\\b{$this->tenant->id}\\b/", Artisan::output());
        $this->assertSame('62020', $this->definicoesAgt()->fresh()->agt_eac_code);
    }
}
