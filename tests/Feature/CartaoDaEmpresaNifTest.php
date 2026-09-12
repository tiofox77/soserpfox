<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * O NIF no cartão de cada empresa, na lista do dono da plataforma.
 *
 * É por ele que a AGT identifica a empresa, e é o primeiro número que se pede
 * ao telefone quando um cliente liga — estava só dentro da ficha de edição.
 *
 * O ecrã passou a React: o que se prova aqui é o que a API lhe entrega. O
 * desenho (o aviso a amarelo, «por preencher») vive no cartão.
 */
class CartaoDaEmpresaNifTest extends TenantTestCase
{
    private function comoDonoDaPlataforma(): void
    {
        $this->user->update(['is_super_admin' => true]);
        $this->actingAs($this->user->fresh());
    }

    private function empresa(string $nome, ?string $nif): Tenant
    {
        return Tenant::create([
            'name' => $nome, 'company_name' => $nome, 'nif' => $nif, 'is_active' => true,
        ]);
    }

    private function linha(string $procura): ?array
    {
        return collect(
            $this->getJson('/api/v1/plataforma/react/empresas?'.http_build_query(['procura' => $procura]))
                ->assertOk()->json('empresas')
        )->first();
    }

    public function test_o_cartao_mostra_o_nif(): void
    {
        $this->comoDonoDaPlataforma();
        $this->empresa('Farmacia Com NIF', '5417289442');

        $this->assertSame('5417289442', $this->linha('Farmacia Com NIF')['nif']);
    }

    /** Um NIF que não começa por 5 fica assinalado onde se vê. */
    public function test_um_nif_que_nao_e_de_empresa_e_assinalado(): void
    {
        $this->comoDonoDaPlataforma();
        $this->empresa('Empresa Com NIF Pessoal', '004512345');

        $this->assertFalse($this->linha('Empresa Com NIF Pessoal')['nif_de_empresa']);
    }

    /** E um NIF de empresa não leva aviso nenhum. */
    public function test_um_nif_de_empresa_nao_leva_aviso(): void
    {
        $this->comoDonoDaPlataforma();
        $this->empresa('Empresa Correcta', '5417289442');

        $this->assertTrue($this->linha('Empresa Correcta')['nif_de_empresa']);
    }

    /** Sem NIF diz que falta: nem «é», nem «não é» de empresa. */
    public function test_sem_nif_diz_que_falta(): void
    {
        $this->comoDonoDaPlataforma();
        $this->empresa('Empresa Sem NIF', null);

        $linha = $this->linha('Empresa Sem NIF');

        $this->assertNull($linha['nif']);
        $this->assertNull($linha['nif_de_empresa']);
    }

    /** A pesquisa encontra pelo NIF, como o próprio campo promete. */
    public function test_a_pesquisa_encontra_pelo_nif(): void
    {
        $this->comoDonoDaPlataforma();
        $this->empresa('Procurada Pelo Numero', '5999888777');

        $this->assertSame('Procurada Pelo Numero', $this->linha('5999888777')['nome']);
    }
}
