<?php

namespace Tests\Feature;

use App\Livewire\POS\POSSystem;
use Tests\TenantTestCase;

/**
 * O carrinho do POS é por utilizador E por empresa.
 *
 * Era só por utilizador. Quem tem mais de uma empresa — e há quem tenha —
 * trocava de empresa e levava o carrinho atrás: artigos escolhidos numa
 * ficavam lá para serem facturados na outra.
 *
 * O addToCart valida a empresa ao ENTRAR, mas quem valida não é quem grava: o
 * fecho da venda percorre o carrinho e cria as linhas sem voltar a verificar.
 * A troca de empresa também não limpava nada.
 */
class PosCarrinhoPorEmpresaTest extends TenantTestCase
{
    private function chaveDoCarrinho(): string
    {
        $metodo = new \ReflectionMethod(POSSystem::class, 'cartKey');
        $metodo->setAccessible(true);

        return $metodo->invoke(new POSSystem());
    }

    public function test_a_chave_inclui_a_empresa(): void
    {
        $this->actingAs($this->user);

        $chave = $this->chaveDoCarrinho();

        $this->assertStringContainsString((string) $this->user->id, $chave);
        $this->assertStringContainsString('_t' . $this->tenant->id, $chave);
    }

    public function test_o_mesmo_utilizador_em_empresas_diferentes_tem_carrinhos_diferentes(): void
    {
        // O caso real: farmacia@luksimoes.com tem duas empresas.
        $this->actingAs($this->user);
        $primeira = $this->chaveDoCarrinho();

        $outra = \App\Models\Tenant::create([
            'name'      => 'Segunda Empresa',
            'slug'      => 'segunda-' . uniqid(),
            'nif'       => (string) random_int(500000000, 599999999),
            'email'     => 'segunda' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        // O utilizador tem mesmo acesso às duas — é o que torna a troca
        // possível, e é a situação real.
        $this->user->tenants()->syncWithoutDetaching([$outra->id]);
        $this->user->switchTenant($outra->id);

        $segunda = $this->chaveDoCarrinho();

        $this->assertNotSame(
            $primeira,
            $segunda,
            'trocar de empresa não pode continuar no mesmo carrinho'
        );
    }
}
