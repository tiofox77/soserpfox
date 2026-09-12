<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Plan;
use App\Services\Plataforma\PlanoAMedida;
use Tests\TenantTestCase;

/**
 * Montar o plano à medida no painel: módulos com preço próprio, soma,
 * teste por módulo e contexto do que a empresa já tem.
 *
 * Era um ensaio do componente em Livewire; o ecrã passou a React e fala com
 * `/api/v1/plataforma/react/empresas/{id}/plano-a-medida`. A soma que o ecrã
 * mostra é a mesma conta do servidor (`PlanoAMedida::somar`), que é a que manda.
 */
class PlanoAMedidaNoPainelTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Module::firstOrCreate(['slug' => 'invoicing'], ['name' => 'Faturação', 'is_core' => false, 'default_price' => 4000]);
        Module::firstOrCreate(['slug' => 'treasury'], ['name' => 'Tesouraria', 'is_core' => false, 'default_price' => 0]);
        Module::firstOrCreate(['slug' => 'rh'], ['name' => 'Recursos Humanos', 'is_core' => false, 'default_price' => 6000]);
        Module::firstOrCreate(['slug' => 'salon'], ['name' => 'Salão', 'is_core' => false, 'default_price' => 3000]);

        $this->user->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($this->user->fresh());
    }

    private function morada(): string
    {
        return "/api/v1/plataforma/react/empresas/{$this->tenant->id}/plano-a-medida";
    }

    private function abrir(): array
    {
        return $this->getJson($this->morada())->assertOk()->json();
    }

    private function guardar(array $troca = [])
    {
        return $this->postJson($this->morada(), array_merge([
            'nome' => 'Plano Soft',
            'modulos' => ['invoicing', 'rh'],
            'precos' => ['invoicing' => 4000, 'rh' => 6000],
            'testes' => [],
            'utilizadores' => 5,
            'empresas' => 1,
            'armazenamento' => 2000,
            'ciclo' => 'monthly',
        ], $troca));
    }

    // ── Contexto: o que a empresa já tem ──────────────────────────

    public function test_mostra_os_modulos_que_a_empresa_ja_tem(): void
    {
        $mod = Module::where('slug', 'invoicing')->first();
        $this->tenant->modules()->syncWithoutDetaching([$mod->id => ['is_active' => true]]);

        $linha = collect($this->abrir()['modulos'])->firstWhere('slug', 'invoicing');

        // O ecrã arranca com os que `ja_tem` escolhidos: o caso comum é acrescentar.
        $this->assertTrue($linha['ja_tem']);
    }

    public function test_sugere_o_preco_base_de_cada_modulo(): void
    {
        $precos = collect($this->abrir()['modulos'])->pluck('preco', 'slug');

        $this->assertSame(4000.0, (float) $precos['invoicing']);
        $this->assertSame(6000.0, (float) $precos['rh']);
    }

    /** O preço combinado no pivô ganha ao de catálogo. */
    public function test_o_preco_combinado_ganha_ao_de_catalogo(): void
    {
        $rh = Module::where('slug', 'rh')->first();
        $this->tenant->modules()->syncWithoutDetaching([$rh->id => ['is_active' => true, 'price' => 4500]]);

        $precos = collect($this->abrir()['modulos'])->pluck('preco', 'slug');

        $this->assertSame(4500.0, (float) $precos['rh']);
    }

    // ── Soma ───────────────────────────────────────────────────────

    public function test_a_mensalidade_e_a_soma_dos_modulos_escolhidos(): void
    {
        $this->assertSame(10000.0, PlanoAMedida::somar(['invoicing', 'rh'], ['invoicing' => 4000, 'rh' => 6000]));
    }

    public function test_tirar_um_modulo_baixa_a_soma(): void
    {
        // O preço de um módulo que já não está escolhido não conta.
        $this->assertSame(4000.0, PlanoAMedida::somar(['invoicing'], ['invoicing' => 4000, 'rh' => 6000]));
    }

    public function test_o_anual_em_branco_e_doze_vezes_a_soma(): void
    {
        $this->guardar(['modulos' => ['invoicing'], 'precos' => ['invoicing' => 5000]])->assertCreated();

        $this->assertSame(60000.0, (float) Plan::where('name', 'Plano Soft')->first()->price_yearly);
    }

    // ── Gravação ───────────────────────────────────────────────────

    public function test_cria_o_plano_com_o_preco_somado(): void
    {
        $this->guardar()->assertCreated();

        $plano = Plan::where('name', 'Plano Soft')->first();

        $this->assertNotNull($plano);
        $this->assertSame(10000.0, (float) $plano->price_monthly);
        $this->assertFalse((bool) $plano->is_public);
    }

    /** A mensalidade é a soma do servidor, e não um número que o browser mande. */
    public function test_a_mensalidade_nao_se_escreve_do_browser(): void
    {
        $this->guardar(['preco_mensal' => 1])->assertCreated();

        $this->assertSame(10000.0, (float) Plan::where('name', 'Plano Soft')->first()->price_monthly);
    }

    public function test_grava_o_preco_de_cada_modulo_na_empresa(): void
    {
        $this->guardar()->assertCreated();

        $rh = $this->tenant->modules()->where('modules.slug', 'rh')->first();

        $this->assertSame(6000.0, (float) $rh->pivot->price);
    }

    // ── Teste por módulo ───────────────────────────────────────────

    private function comSalaoEmTeste()
    {
        return $this->guardar([
            'modulos' => ['invoicing', 'salon'],
            'precos' => ['invoicing' => 4000, 'salon' => 3000],
            'testes' => ['salon' => 15],
        ])->assertCreated();
    }

    public function test_um_modulo_com_dias_de_teste_fica_com_prazo(): void
    {
        $this->comSalaoEmTeste();

        $salon = $this->tenant->modules()->where('modules.slug', 'salon')->first();
        $invo  = $this->tenant->modules()->where('modules.slug', 'invoicing')->first();

        $this->assertNotNull($salon->pivot->trial_ends_at, 'o módulo em teste tem de ter prazo');
        $this->assertNull($invo->pivot->trial_ends_at, 'um módulo sem teste não leva prazo');
    }

    public function test_o_modulo_em_teste_esta_disponivel_ate_ao_prazo(): void
    {
        $this->comSalaoEmTeste();

        $this->assertTrue($this->tenant->fresh()->hasModule('salon'));
    }

    public function test_passado_o_prazo_so_esse_modulo_cai(): void
    {
        $this->comSalaoEmTeste();

        // O prazo passou.
        $salon = Module::where('slug', 'salon')->first();
        $this->tenant->modules()->updateExistingPivot($salon->id, [
            'trial_ends_at' => now()->subDay(),
        ]);

        $tenant = $this->tenant->fresh();

        $this->assertFalse($tenant->hasModule('salon'), 'o módulo em teste caducou');
        $this->assertTrue($tenant->hasModule('invoicing'), 'o resto do plano continua');
    }

    // ── Guardas ────────────────────────────────────────────────────

    public function test_recusa_sem_modulos(): void
    {
        $this->guardar(['modulos' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('modulos');
    }

    public function test_recusa_soma_zero(): void
    {
        $this->guardar(['nome' => 'Plano Zero', 'modulos' => ['invoicing'], 'precos' => ['invoicing' => 0]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('precos');

        $this->assertNull(Plan::where('name', 'Plano Zero')->first());
    }

    /** Um slug de módulo inventado não entra no plano. */
    public function test_recusa_um_modulo_que_nao_existe(): void
    {
        $this->guardar(['modulos' => ['invoicing', 'nao-existe'], 'precos' => ['invoicing' => 4000]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('modulos.1');
    }
}
