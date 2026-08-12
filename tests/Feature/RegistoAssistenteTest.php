<?php

namespace Tests\Feature;

use App\Livewire\Auth\RegisterWizard;
use App\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O assistente de registo não pode pedir o que já sabe nem cobrar o que não custa.
 *
 * Duas coisas davam mau aspecto logo à porta:
 *
 *   · quem escolhia o FOX Friendly — 0 Kz — chegava ao fim e via o IBAN da
 *     SOSERP, o campo "Referência da Transferência", "Valor: 0.00 Kz" e o
 *     aviso de que tinha de anexar o comprovativo. Comprovativo de quê?
 *
 *   · quem escolhia o plano na página inicial e carregava em "Começar Agora"
 *     era recebido com a pergunta "selecione o plano ideal para o seu
 *     negócio" — a escolha que tinha acabado de fazer. O botão ligava a
 *     /register sem levar nada consigo.
 *
 * E ainda uma terceira, mais cara: a palavra-passe não é guardada na sessão
 * (e não deve ser), mas o assistente lia essa ausência como "os dados
 * perderam-se" e apagava tudo. Bastava um F5 para deitar fora a empresa, o
 * NIF, a morada e o plano.
 */
class RegistoAssistenteTest extends TestCase
{
    // Como no resto da suite: o esquema é montado uma vez por
    // scripts/prepare_test_db.php e cada teste corre dentro de uma transacção.
    use DatabaseTransactions;

    private function planoGratuito(): Plan
    {
        return Plan::create([
            'name' => 'Amigo', 'slug' => 'amigo-teste', 'description' => 'Grátis',
            'price_monthly' => 0, 'price_yearly' => 0, 'trial_days' => 180,
            'max_users' => 3, 'max_companies' => 1, 'is_active' => true,
            'auto_activate' => true, 'order' => 1,
        ]);
    }

    private function planoPago(): Plan
    {
        return Plan::create([
            'name' => 'Empresarial', 'slug' => 'empresarial-teste', 'description' => 'Pago',
            'price_monthly' => 24900, 'price_yearly' => 249000, 'trial_days' => 0,
            'max_users' => 20, 'max_companies' => 3, 'is_active' => true,
            'auto_activate' => false, 'order' => 2,
        ]);
    }

    public function test_plano_gratuito_nao_tem_pagamento_a_tratar(): void
    {
        $gratis = $this->planoGratuito();

        Livewire::test(RegisterWizard::class)
            ->set('selected_plan_id', $gratis->id)
            ->assertSet('naoHaNadaAPagar', true)
            ->assertSet('temPassoDePagamento', false);
    }

    public function test_plano_pago_continua_a_pedir_o_pagamento(): void
    {
        $pago = $this->planoPago();

        Livewire::test(RegisterWizard::class)
            ->set('selected_plan_id', $pago->id)
            ->assertSet('naoHaNadaAPagar', false)
            ->assertSet('temPassoDePagamento', true);
    }

    /**
     * Todos os planos têm dias de teste. Se o teste contasse como "nada a
     * pagar", o passo do pagamento desaparecia para toda a gente.
     */
    public function test_ter_dias_de_teste_nao_torna_um_plano_pago_em_gratuito(): void
    {
        $pago = $this->planoPago();
        $pago->update(['trial_days' => 30]);

        Livewire::test(RegisterWizard::class)
            ->set('selected_plan_id', $pago->id)
            ->assertSet('naoHaNadaAPagar', false);
    }

    public function test_o_ecra_do_plano_gratuito_nao_mostra_iban_nem_pede_comprovativo(): void
    {
        $gratis = $this->planoGratuito();

        Livewire::test(RegisterWizard::class)
            ->set('selected_plan_id', $gratis->id)
            ->set('currentStep', 4)
            ->assertDontSee('IBAN')
            ->assertDontSee('Referência da Transferência')
            ->assertDontSee('Comprovativo')
            ->assertSee('Sem pagamento a efetuar');
    }

    public function test_o_ecra_do_plano_pago_mostra_os_dados_da_transferencia(): void
    {
        $pago = $this->planoPago();

        Livewire::test(RegisterWizard::class)
            ->set('selected_plan_id', $pago->id)
            ->set('currentStep', 4)
            ->assertSee('IBAN')
            ->assertSee('Dados para Transferência');
    }

    public function test_plano_vindo_do_link_nao_e_perguntado_outra_vez(): void
    {
        $gratis = $this->planoGratuito();
        $this->planoPago();

        Livewire::withQueryParams(['plan' => 'amigo-teste'])
            ->test(RegisterWizard::class)
            ->assertSet('selected_plan_id', $gratis->id)
            ->assertSet('planoVeioDoLink', true)
            ->assertSet('temPassoDePlano', false)
            ->assertDontSee('Escolha o Plano');
    }

    public function test_sem_plano_no_link_o_passo_da_escolha_mantem_se(): void
    {
        $this->planoGratuito();

        Livewire::test(RegisterWizard::class)
            ->assertSet('planoVeioDoLink', false)
            ->assertSet('temPassoDePlano', true)
            ->assertSee('Escolha o Plano');
    }

    /** Não perguntar duas vezes não pode virar não deixar mudar de ideias. */
    public function test_pode_sempre_trocar_de_plano_mesmo_vindo_do_link(): void
    {
        $this->planoGratuito();

        Livewire::withQueryParams(['plan' => 'amigo-teste'])
            ->test(RegisterWizard::class)
            ->assertSet('temPassoDePlano', false)
            ->call('escolherOutroPlano')
            ->assertSet('planoVeioDoLink', false)
            ->assertSet('temPassoDePlano', true)
            ->assertSet('currentStep', 3);
    }

    public function test_com_plano_no_link_a_empresa_leva_direto_ao_ultimo_passo(): void
    {
        $this->planoPago();

        Livewire::withQueryParams(['plan' => 'empresarial-teste'])
            ->test(RegisterWizard::class)
            ->set('name', 'Ana')
            ->set('email', 'ana@exemplo.ao')
            ->set('password', 'segredo-forte-123')
            ->set('password_confirmation', 'segredo-forte-123')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->set('company_name', 'Padaria Central')
            ->set('company_nif', '5417123456')
            ->call('nextStep')
            ->assertSet('currentStep', 4);
    }

    /** O botão "Anterior" não pode cair no passo que o "Seguinte" salta. */
    public function test_voltar_atras_salta_o_passo_do_plano_que_veio_do_link(): void
    {
        $this->planoPago();

        Livewire::withQueryParams(['plan' => 'empresarial-teste'])
            ->test(RegisterWizard::class)
            ->set('currentStep', 4)
            ->call('previousStep')
            ->assertSet('currentStep', 2);
    }

    /**
     * O caso que apagava tudo: recarregar a página a meio do registo.
     */
    public function test_um_refresh_nao_deita_fora_os_dados_ja_preenchidos(): void
    {
        $pago = $this->planoPago();

        session(['wizard_progress' => [
            'currentStep'       => 4,
            'name'              => 'Ana',
            'email'             => 'ana@exemplo.ao',
            'company_name'      => 'Padaria Central',
            'company_nif'       => '5417123456',
            'company_address'   => 'Rua 1, Luanda',
            'selected_plan_id'  => $pago->id,
            'payment_method'    => 'transfer',
            'payment_reference' => 'TRF900',
        ]]);

        // Sem a palavra-passe — é o que acontece a seguir a um F5.
        Livewire::test(RegisterWizard::class)
            ->assertSet('company_name', 'Padaria Central')
            ->assertSet('company_nif', '5417123456')
            ->assertSet('company_address', 'Rua 1, Luanda')
            ->assertSet('selected_plan_id', $pago->id)
            // Volta ao passo 1 só para reescrever a palavra-passe...
            ->assertSet('currentStep', 1)
            ->assertSet('passoAntesDaSenha', 4)
            // ...e daí segue direto para onde estava.
            ->set('password', 'segredo-forte-123')
            ->set('password_confirmation', 'segredo-forte-123')
            ->call('nextStep')
            ->assertSet('currentStep', 4)
            ->assertSet('company_name', 'Padaria Central');
    }

    public function test_o_plano_do_link_sobrevive_ao_refresh(): void
    {
        $gratis = $this->planoGratuito();

        session(['wizard_progress' => [
            'currentStep'      => 2,
            'name'             => 'Ana',
            'email'            => 'ana@exemplo.ao',
            'selected_plan_id' => $gratis->id,
            'planoVeioDoLink'  => true,
        ]]);

        Livewire::test(RegisterWizard::class)
            ->assertSet('planoVeioDoLink', true)
            ->assertSet('temPassoDePlano', false);
    }

    /** Erros de validação não são motivo para apagar o que já foi escrito. */
    public function test_erros_de_validacao_nao_apagam_a_empresa_ja_preenchida(): void
    {
        $this->planoPago();

        Livewire::test(RegisterWizard::class)
            ->set('company_name', 'Padaria Central')
            ->set('company_nif', '5417123456')
            ->call('nextStep')          // passo 1 vazio: chove validação
            ->assertHasErrors()
            ->assertSet('company_name', 'Padaria Central')
            ->assertSet('company_nif', '5417123456')
            ->assertSet('currentStep', 1);
    }

    public function test_a_pagina_inicial_leva_o_plano_escolhido_para_o_registo(): void
    {
        $pago = $this->planoPago();

        $this->get('/')
            ->assertOk()
            ->assertSee(route('register', ['plan' => $pago->slug]), false);
    }
}
