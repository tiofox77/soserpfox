<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * O carrinho do POS é por utilizador E por empresa.
 *
 * Era só por utilizador. Quem tem mais de uma empresa — e há quem tenha —
 * trocava de empresa e levava o carrinho atrás: artigos escolhidos numa ficavam
 * lá para serem facturados na outra.
 *
 * O BALCÃO É HOJE REACT, e o defeito voltou pela porta do lado: o espelho do
 * carrinho em `localStorage` lia a empresa de uma `<meta name="tenant-id">` que
 * não existe em lado nenhum do layout. A chave saía sempre `pos_carrinho_0` —
 * igual para todas as empresas e para todos os operadores. O ensaio que guardava
 * isto apontava para um componente Livewire que já nenhuma rota serve, e por
 * isso nunca deu por nada.
 *
 * Agora quem diz de quem é o carrinho é o SERVIDOR, nas opções do balcão.
 */
class PosCarrinhoPorEmpresaTest extends TenantTestCase
{
    private const OPCOES = '/api/v1/invoicing/react/pos/opcoes';

    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.pos.access', 'invoicing.sales.invoices.create');
    }

    public function test_o_dono_do_carrinho_diz_a_empresa_e_o_operador(): void
    {
        $r = $this->actingAs($this->user)->getJson(self::OPCOES)->assertOk();

        $this->assertSame($this->tenant->id, $r->json('dono_do_carrinho.empresa'));
        $this->assertSame($this->user->id, $r->json('dono_do_carrinho.operador'));
    }

    public function test_o_mesmo_utilizador_em_empresas_diferentes_tem_carrinhos_diferentes(): void
    {
        // O caso real: há contas com duas empresas.
        $primeira = $this->actingAs($this->user)->getJson(self::OPCOES)->assertOk()->json('dono_do_carrinho');

        $outra = \App\Models\Tenant::create([
            'name' => 'Segunda Empresa',
            'slug' => 'segunda-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'segunda'.uniqid().'@exemplo.ao',
            'is_active' => true,
        ]);

        /*
         * O utilizador tem mesmo acesso às duas — é o que torna a troca
         * possível, e é a situação real. A ligação nasce ACTIVA de propósito:
         * `User::tenants()` filtra por `is_active` no pivot, e uma ligação sem
         * ela não conta como empresa da pessoa.
         */
        $this->user->tenants()->syncWithoutDetaching([$outra->id => ['is_active' => true]]);

        // A empresa nova nasce com o mesmo plano da activa — é o que a criação
        // de empresa faz, e sem subscrição o balcão nem abre.
        $plano = $this->tenant->activeSubscription?->plan_id;
        $outra->subscriptions()->create([
            'plan_id' => $plano, 'status' => 'active', 'billing_cycle' => 'monthly',
            'amount' => 0, 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        $this->user->switchTenant($outra->id);
        $this->comPermissoes('invoicing.pos.access', 'invoicing.sales.invoices.create');

        $segunda = $this->actingAs($this->user->fresh())->getJson(self::OPCOES)
            ->assertOk()->json('dono_do_carrinho');

        $this->assertNotSame(
            $primeira['empresa'],
            $segunda['empresa'],
            'trocar de empresa não pode continuar no mesmo carrinho',
        );
    }

    /**
     * E O ECRÃ USA-O MESMO.
     *
     * A meta tag que ele lia não existia; se voltar a ler de uma, a chave volta
     * a ser a mesma para todos.
     */
    public function test_o_ecra_monta_a_chave_com_a_empresa_e_o_operador(): void
    {
        $fonte = file_get_contents(resource_path('js/ecras/facturacao/pos/PontoDeVenda.tsx'));

        $this->assertStringContainsString('pos_carrinho_t${dono.empresa}_u${dono.operador}', $fonte);
        $this->assertStringNotContainsString('meta[name="tenant-id"]', $fonte,
            'a chave do carrinho voltou a sair de uma meta tag que não existe');
    }
}
