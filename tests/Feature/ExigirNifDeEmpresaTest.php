<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * Uma empresa com NIF de pessoa singular e levada ao ecra onde o corrige.
 *
 * A validacao no registo so trava os NOVOS. Quem se registou antes ficou com o
 * que escreveu, e nada o obrigava a reparar — ate ao dia em que a AGT recusa as
 * facturas, com documentos ja emitidos com o numero errado.
 */
class ExigirNifDeEmpresaTest extends TenantTestCase
{
    private function comNif(?string $nif): void
    {
        $this->tenant->update(['nif' => $nif]);

        // O User memoriza a empresa activa na própria instância. Sem
        // reautenticar com uma instância fresca, o middleware lia o NIF que
        // estava em memória antes desta actualização — e o teste passava a
        // medir o estado antigo.
        $this->actingAs(\App\Models\User::find($this->user->id));
    }

    public function test_com_nif_de_bi_e_levada_ao_ecra_da_empresa(): void
    {
        $this->comNif('004512345LA041');

        $this->get('/home')
            ->assertRedirect(route('company.profile'));
    }

    public function test_com_nif_de_pessoa_singular_tambem(): void
    {
        $this->comNif('2417289442');

        $this->get('/home')->assertRedirect(route('company.profile'));
    }

    public function test_com_nif_de_empresa_passa(): void
    {
        $this->comNif('5417289442');

        $this->get('/home')->assertOk();
    }

    /** O ecra onde se corrige tem de estar sempre acessivel, senao e um beco. */
    public function test_o_ecra_da_empresa_nunca_e_bloqueado(): void
    {
        $this->comNif('004512345LA041');

        $this->get('/empresa')->assertOk();
    }

    /**
     * O PONTO DE VENDA NAO PARA.
     *
     * Uma farmacia com o NIF errado continua a ter clientes ao balcao. Trancar
     * a caixa seria um estrago maior do que o que se esta a corrigir.
     */
    public function test_o_pos_offline_continua_a_funcionar(): void
    {
        $this->comNif('004512345LA041');

        $this->get('/invoicing/offline')->assertOk();
    }

    /** Sem NIF nao se redirecciona: e outro problema, e nao e este que o resolve. */
    public function test_sem_nif_nao_redirecciona(): void
    {
        $this->comNif(null);

        $this->get('/home')->assertOk();
    }

    /** A mensagem diz porque importa, e nao so que esta errado. */
    public function test_a_mensagem_explica_a_consequencia(): void
    {
        $this->comNif('004512345LA041');

        $this->get('/home');

        $this->assertStringContainsString('AGT', session('warning'));
        $this->assertStringContainsString('recusados', session('warning'));
    }
}
