<?php

namespace Tests\Feature;

use App\Rules\NifDeEmpresa;
use Illuminate\Support\Facades\Validator;
use Tests\TenantTestCase;

/**
 * O NIF com que uma empresa se regista.
 *
 * Em Angola o NIF de pessoa colectiva tem dez digitos e comeca por 5. O de
 * pessoa singular e o proprio numero do BI. Registar a empresa com o NIF do BI
 * passa despercebido durante semanas e so aparece quando a AGT comeca a
 * recusar as facturas — altura em que ja ha documentos emitidos com o numero
 * errado.
 *
 * Ate aqui o registo validava 'min:5' — cinco CARACTERES — e o ecra do super
 * admin nao validava nada.
 */
class NifDeEmpresaTest extends TenantTestCase
{
    private function erro(?string $nif): ?string
    {
        $v = Validator::make(['nif' => $nif], ['nif' => [new NifDeEmpresa()]]);

        return $v->fails() ? $v->errors()->first('nif') : null;
    }

    public function test_um_nif_de_empresa_passa(): void
    {
        $this->assertNull($this->erro('5417289442'));
        $this->assertNull($this->erro('5000123456'));
    }

    /** Escrito com espacos ou tracos, como as pessoas escrevem. */
    public function test_aceita_espacos_pontos_e_tracos(): void
    {
        $this->assertNull($this->erro('5417 289 442'));
        $this->assertNull($this->erro('5417.289.442'));
        $this->assertNull($this->erro('5417-289-442'));
    }

    /** O caso que motivou tudo isto: o numero do BI. */
    public function test_o_numero_do_bi_e_recusado_e_dito_pelo_nome(): void
    {
        $erro = $this->erro('004512345LA041');

        $this->assertNotNull($erro, 'o numero do BI nao pode passar como NIF de empresa');
        $this->assertStringContainsString('bilhete de identidade', $erro,
            'dizer so "invalido" nao demove quem acha que aquele e o numero');
    }

    /** Um NIF de pessoa singular so por digitos tambem nao serve. */
    public function test_um_nif_que_nao_comeca_por_cinco_e_recusado(): void
    {
        foreach (['2417289442', '0417289442', '3417289442'] as $nif) {
            $erro = $this->erro($nif);
            $this->assertNotNull($erro, "{$nif} devia ser recusado");
            $this->assertStringContainsString('começa por 5', $erro);
        }
    }

    public function test_nove_digitos_passam_e_os_outros_comprimentos_nao(): void
    {
        $this->assertNull($this->erro('541728944'), 'nove digitos comecados por 5 existem mesmo');
        $this->assertNotNull($this->erro('54172894421'), 'onze digitos nao');
        $this->assertNotNull($this->erro('54172894'), 'oito digitos nao');
    }

    /**
     * O vazio e trabalho do `required`, e nao desta regra: o Laravel salta as
     * regras de um campo vazio a menos que ele seja obrigatorio. Testa-se como
     * os ecras a usam mesmo — required + a regra — e nao a regra sozinha.
     */
    public function test_vazio_e_recusado_quando_obrigatorio(): void
    {
        foreach (['', null] as $nada) {
            $v = Validator::make(['nif' => $nada], ['nif' => ['required', new NifDeEmpresa()]]);
            $this->assertTrue($v->fails(), 'um NIF vazio nao pode passar no registo');
        }
    }

    // ---- os ecras que criam empresa --------------------------------------

    /**
     * Acrescentar outra empresa a uma conta que ja existe.
     *
     * Pelo ecra e nao pela regra sozinha: o que interessa provar e que o ecra
     * a usa, e nao que a regra funciona isolada — ja ha testes para isso.
     */
    public function test_o_ecra_de_nova_empresa_recusa_o_numero_do_bi(): void
    {
        \Livewire\Livewire::test(\App\Livewire\MyAccount::class)
            // O limite de empresas e verificado ANTES da validacao, com um
            // return a meio: sem o levantar, o metodo nunca chegava as regras
            // e os dois testes passavam sem exercitar nada.
            ->set('maxAllowed', 99)
            ->set('newCompanyName', 'Farmácia Teste')
            ->set('newCompanyNif', '004512345LA041')
            ->set('newCompanyRegime', array_key_first(\App\Models\Tenant::REGIMES))
            ->call('createCompany')
            ->assertHasErrors('newCompanyNif');
    }

    /** E com um NIF de empresa a validacao do NIF ja nao se queixa. */
    public function test_o_ecra_de_nova_empresa_aceita_um_nif_de_empresa(): void
    {
        \Livewire\Livewire::test(\App\Livewire\MyAccount::class)
            // O limite de empresas e verificado ANTES da validacao, com um
            // return a meio: sem o levantar, o metodo nunca chegava as regras
            // e os dois testes passavam sem exercitar nada.
            ->set('maxAllowed', 99)
            ->set('newCompanyName', 'Farmácia Teste')
            ->set('newCompanyNif', '5000123456')
            ->set('newCompanyRegime', array_key_first(\App\Models\Tenant::REGIMES))
            ->call('createCompany')
            ->assertHasNoErrors('newCompanyNif');
    }

    /** A mesma regra pelo nome, que e como o ecra do super admin a usa. */
    public function test_a_regra_com_nome_faz_o_mesmo(): void
    {
        $bom = Validator::make(['nif' => '5417289442'], ['nif' => 'nif_empresa']);
        $mau = Validator::make(['nif' => '004512345LA041'], ['nif' => 'nif_empresa']);

        $this->assertFalse($bom->fails());
        $this->assertTrue($mau->fails(), 'a regra com nome tem de recusar o mesmo que o objecto');
    }
}
