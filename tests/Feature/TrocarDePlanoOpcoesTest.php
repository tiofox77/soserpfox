<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Plataforma\TrocarDePlano;
use App\Support\CicloDeFacturacao;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * O acordo, além do plano: oferta opcional, dias à medida, preço por utilizador.
 *
 * O que estes ensaios prendem, por ordem de importância:
 *   1. a OMISSÃO não muda — o anual continua a dar 14 meses por todas as
 *      outras portas (registo, aprovação, renovação);
 *   2. tirar a oferta dá 12 meses, e a RENOVAÇÃO repete-o em vez de voltar aos 14;
 *   3. dias à medida ganham ao ciclo, e a renovação repete os dias;
 *   4. cobrar por utilizador grava a base (N × preço), não só o total.
 */
class TrocarDePlanoOpcoesTest extends TestCase
{
    use DatabaseTransactions;

    private function plano(float $mensal = 10000, float $anual = 100000): Plan
    {
        return Plan::create([
            'name' => 'Plano '.uniqid(), 'slug' => 'plano-'.uniqid(),
            'price_monthly' => $mensal, 'price_yearly' => $anual, 'trial_days' => 0,
            'max_users' => 10, 'max_storage_mb' => 5000, 'is_active' => true,
        ]);
    }

    private function empresa(): Tenant
    {
        return Tenant::create([
            'name' => 'Empresa '.uniqid(), 'email' => uniqid().'@teste.local',
            'nif' => '5'.random_int(10000000, 99999999),
        ]);
    }

    /** @test */
    public function por_omissao_o_anual_continua_a_dar_catorze_meses(): void
    {
        $sub = app(TrocarDePlano::class)->aplicar($this->empresa(), $this->plano(), 'yearly');

        $this->assertTrue((bool) $sub->com_oferta);
        $this->assertSame(14, (int) $sub->current_period_start->diffInMonths($sub->current_period_end));
        $this->assertSame(14, CicloDeFacturacao::meses('yearly'), 'a política por omissão não pode ter mudado');
    }

    /** @test */
    public function sem_oferta_o_anual_da_doze_meses_e_fica_escrito(): void
    {
        $sub = app(TrocarDePlano::class)->aplicar($this->empresa(), $this->plano(), 'yearly', [
            'com_oferta' => false,
        ]);

        $this->assertFalse((bool) $sub->com_oferta);
        $this->assertSame(12, (int) $sub->current_period_start->diffInMonths($sub->current_period_end));
    }

    /**
     * A RENOVAÇÃO REPETE O ACORDO.
     *
     * Sem isto, uma empresa fechada sem oferta ganhava os dois meses na
     * primeira renovação, à socapa.
     *
     * @test
     */
    public function a_renovacao_de_um_anual_sem_oferta_continua_sem_oferta(): void
    {
        $sub = app(TrocarDePlano::class)->aplicar($this->empresa(), $this->plano(), 'yearly', [
            'com_oferta' => false,
        ]);

        $fimAntes = $sub->current_period_end->copy();
        $sub->renew();

        $this->assertSame(12, (int) $fimAntes->diffInMonths($sub->fresh()->current_period_end),
            'a renovação não pode devolver os dois meses que o acordo tirou');
    }

    /** @test */
    public function dias_a_medida_ganham_ao_ciclo(): void
    {
        $sub = app(TrocarDePlano::class)->aplicar($this->empresa(), $this->plano(), 'yearly', [
            'dias' => 364,
        ]);

        $this->assertSame(364, (int) $sub->dias_personalizados);
        $this->assertSame(364, (int) $sub->current_period_start->diffInDays($sub->current_period_end));

        // E a renovação repete os 364, não volta ao anual.
        $fimAntes = $sub->current_period_end->copy();
        $sub->renew();
        $this->assertSame(364, (int) $fimAntes->diffInDays($sub->fresh()->current_period_end));
    }

    /** @test */
    public function cobrar_por_utilizador_grava_a_base_e_o_total(): void
    {
        $sub = app(TrocarDePlano::class)->aplicar($this->empresa(), $this->plano(), 'monthly', [
            'preco_por_utilizador' => 1500,
            'utilizadores' => 7,
        ]);

        $this->assertSame(10500.0, (float) $sub->amount);
        $this->assertSame(1500.0, (float) $sub->preco_por_utilizador);
        $this->assertSame(7, (int) $sub->utilizadores_cobrados);
    }

    /** Sem indicar quantos, cobra pelos utilizadores do plano. */
    public function test_por_utilizador_sem_numero_usa_os_do_plano(): void
    {
        $sub = app(TrocarDePlano::class)->aplicar($this->empresa(), $this->plano(), 'monthly', [
            'preco_por_utilizador' => 2000,
        ]);

        $this->assertSame(10, (int) $sub->utilizadores_cobrados);
        $this->assertSame(20000.0, (float) $sub->amount);
    }

    /** Sem opções, o valor é o da tabela — nada muda para quem não pede nada. */
    public function test_sem_opcoes_o_valor_e_o_da_tabela(): void
    {
        $sub = app(TrocarDePlano::class)->aplicar($this->empresa(), $this->plano(10000, 100000), 'yearly');

        $this->assertSame(100000.0, (float) $sub->amount);
        $this->assertNull($sub->preco_por_utilizador);
        $this->assertNull($sub->dias_personalizados);
    }

    /** Um engano de tecla não vira uma subscrição de cem anos. */
    public function test_dias_absurdos_sao_recusados(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(TrocarDePlano::class)->aplicar($this->empresa(), $this->plano(), 'monthly', ['dias' => 99999]);
    }
}
