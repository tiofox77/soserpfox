<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Subscriptions\DireitoACortesia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Uma cortesia por cliente. Uma só, para sempre.
 *
 * Sem esta regra o sistema oferecia-se em ciclo: 180 dias de FOX Friendly,
 * depois os 30 de teste do Business, depois os 30 do Enterprise, depois os 14
 * do Pacote Vendas, do Pacote RH, do Pacote Hotel... ano e meio de ERP
 * completo sem uma factura pelo meio, e ainda sobravam planos para o ano
 * seguinte. Bastava mudar de plano. Ou criar outra empresa.
 *
 * O que existia antes só olhava para a bandeira `is_promotional`, só para o
 * MESMO plano, e só na MESMA empresa.
 */
class DireitoACortesiaTest extends TestCase
{
    use DatabaseTransactions;

    private function plano(string $slug, float $preco, int $teste = 0): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug), 'slug' => $slug . '-' . uniqid(),
            'description' => 'Teste', 'price_monthly' => $preco, 'price_yearly' => $preco * 10,
            'trial_days' => $teste, 'max_users' => 5, 'max_companies' => 3,
            'is_active' => true, 'order' => 1,
        ]);
    }

    private function empresa(string $nif = null): Tenant
    {
        return Tenant::create([
            'name'  => 'Empresa ' . uniqid(),
            'slug'  => 'empresa-' . uniqid(),
            'nif'   => $nif ?: (string) random_int(500000000, 599999999),
            'email' => 'e' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);
    }

    private function utilizadorDe(Tenant ...$empresas): User
    {
        $user = User::create([
            'name' => 'Dono', 'email' => 'dono' . uniqid() . '@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $empresas[0]->id ?? null,
        ]);

        foreach ($empresas as $empresa) {
            $user->tenants()->syncWithoutDetaching([$empresa->id]);
        }

        return $user;
    }

    private function subscrever(Tenant $empresa, Plan $plano, string $estado, bool $comTeste = false): void
    {
        $empresa->subscriptions()->create([
            'plan_id'       => $plano->id,
            'status'        => $estado,
            'billing_cycle' => 'monthly',
            'amount'        => $plano->price_monthly,
            'trial_ends_at' => $comTeste ? now()->addDays($plano->trial_days) : null,
        ]);
    }

    public function test_quem_chega_de_novo_tem_direito_a_tudo(): void
    {
        $gratis  = $this->plano('gratis', 0, 180);
        $empresa = $this->empresa();
        $direito = DireitoACortesia::daEmpresa($empresa, $this->utilizadorDe($empresa));

        $this->assertNull($direito->motivoParaRecusar($gratis));
        $this->assertTrue($direito->temDireitoATeste($gratis));
    }

    /** Regra 1: já teve o plano gratuito, não o renova. */
    public function test_quem_ja_teve_o_gratuito_nao_o_repete(): void
    {
        $gratis  = $this->plano('gratis', 0, 180);
        $empresa = $this->empresa();
        $this->subscrever($empresa, $gratis, 'expired');

        $direito = DireitoACortesia::daEmpresa($empresa, $this->utilizadorDe($empresa));

        $this->assertNotNull($direito->motivoParaRecusar($gratis));
        $this->assertTrue($direito->jaTeveGratuito());
    }

    /** Nem sequer um plano gratuito diferente. */
    public function test_o_gratuito_gasto_fecha_qualquer_outro_gratuito(): void
    {
        $primeiro = $this->plano('gratis-um', 0, 180);
        $segundo  = $this->plano('gratis-dois', 0, 90);
        $empresa  = $this->empresa();
        $this->subscrever($empresa, $primeiro, 'expired');

        $direito = DireitoACortesia::daEmpresa($empresa, $this->utilizadorDe($empresa));

        $this->assertNotNull($direito->motivoParaRecusar($segundo));
    }

    /** Regra 2: o teste é um só, mesmo mudando de plano. */
    public function test_o_teste_de_um_plano_gasta_o_teste_de_todos(): void
    {
        $business   = $this->plano('business', 24900, 30);
        $enterprise = $this->plano('enterprise', 49900, 30);
        $empresa    = $this->empresa();
        $this->subscrever($empresa, $business, 'trial', comTeste: true);

        $direito = DireitoACortesia::daEmpresa($empresa, $this->utilizadorDe($empresa));

        $this->assertFalse($direito->temDireitoATeste($enterprise));
        $this->assertNotNull($direito->motivoSemTeste());
        // Subscrever continua a poder — o que acaba é o começar de graça.
        $this->assertNull($direito->motivoParaRecusar($enterprise));
    }

    /** Regra 3: quem já foi cliente não passa depois para o gratuito. */
    public function test_quem_ja_pagou_nao_desce_para_o_gratuito(): void
    {
        $business = $this->plano('business', 24900, 30);
        $gratis   = $this->plano('gratis', 0, 180);
        $empresa  = $this->empresa();
        $this->subscrever($empresa, $business, 'active');

        $direito = DireitoACortesia::daEmpresa($empresa, $this->utilizadorDe($empresa));

        $this->assertNotNull($direito->motivoParaRecusar($gratis));
        $this->assertTrue($direito->jaFoiCliente());
    }

    public function test_quem_ativou_o_teste_de_um_plano_pago_nao_passa_ao_gratuito(): void
    {
        $business = $this->plano('business', 24900, 30);
        $gratis   = $this->plano('gratis', 0, 180);
        $empresa  = $this->empresa();
        $this->subscrever($empresa, $business, 'trial', comTeste: true);

        $direito = DireitoACortesia::daEmpresa($empresa, $this->utilizadorDe($empresa));

        $this->assertNotNull($direito->motivoParaRecusar($gratis));
    }

    /** O gratuito também gasta o teste — é a mesma cortesia por outro nome. */
    public function test_o_gratuito_gasta_o_direito_ao_teste(): void
    {
        $gratis   = $this->plano('gratis', 0, 180);
        $business = $this->plano('business', 24900, 30);
        $empresa  = $this->empresa();
        $this->subscrever($empresa, $gratis, 'trial', comTeste: true);

        $direito = DireitoACortesia::daEmpresa($empresa, $this->utilizadorDe($empresa));

        $this->assertFalse($direito->temDireitoATeste($business));
    }

    /** Criar outra empresa não devolve a cortesia. */
    public function test_a_segunda_empresa_do_mesmo_dono_nao_recomeca_o_contador(): void
    {
        $gratis  = $this->plano('gratis', 0, 180);
        $primeira = $this->empresa();
        $segunda  = $this->empresa();
        $dono     = $this->utilizadorDe($primeira, $segunda);
        $this->subscrever($primeira, $gratis, 'expired');

        $direito = DireitoACortesia::daEmpresa($segunda, $dono);

        $this->assertNotNull($direito->motivoParaRecusar($gratis));
        $this->assertFalse($direito->temDireitoATeste($gratis));
    }

    /** Nem voltar com outro email e a mesma empresa. */
    public function test_o_mesmo_nif_conta_mesmo_com_outra_conta(): void
    {
        $gratis = $this->plano('gratis', 0, 180);
        $nif    = (string) random_int(500000000, 599999999);

        $antiga = $this->empresa($nif);
        $this->subscrever($antiga, $gratis, 'expired');

        // Outro utilizador, outra empresa — mesmo contribuinte.
        $nova   = $this->empresa($nif);
        $outro  = $this->utilizadorDe($nova);

        $direito = DireitoACortesia::daEmpresa($nova, $outro);

        $this->assertNotNull($direito->motivoParaRecusar($gratis));
    }

    /** Apagar a empresa não é forma de recomeçar. */
    public function test_apagar_a_empresa_nao_devolve_a_cortesia(): void
    {
        $gratis = $this->plano('gratis', 0, 180);
        $nif    = (string) random_int(500000000, 599999999);

        $antiga = $this->empresa($nif);
        $dono   = $this->utilizadorDe($antiga);
        $this->subscrever($antiga, $gratis, 'expired');
        $antiga->delete();

        $nova = $this->empresa($nif);

        $this->assertNotNull(
            DireitoACortesia::daEmpresa($nova, $dono)->motivoParaRecusar($gratis)
        );
    }

    /**
     * Um pedido que ficou por pagar não deu acesso a nada.
     *
     * Contá-lo fechava a porta a quem começou a assinar o Business, desistiu
     * a meio e agora nunca mais poderia sequer experimentar o gratuito.
     */
    public function test_um_pedido_por_pagar_nao_gasta_a_cortesia(): void
    {
        $business = $this->plano('business', 24900, 30);
        $gratis   = $this->plano('gratis', 0, 180);
        $empresa  = $this->empresa();
        $this->subscrever($empresa, $business, 'pending');

        $direito = DireitoACortesia::daEmpresa($empresa, $this->utilizadorDe($empresa));

        $this->assertNull($direito->motivoParaRecusar($gratis));
        $this->assertTrue($direito->temDireitoATeste($gratis));
    }

    /** Uma subscrição cancelada esteve activa — conta. */
    public function test_cancelar_nao_apaga_o_que_ja_se_usou(): void
    {
        $gratis  = $this->plano('gratis', 0, 180);
        $empresa = $this->empresa();
        $this->subscrever($empresa, $gratis, 'cancelled');

        $direito = DireitoACortesia::daEmpresa($empresa, $this->utilizadorDe($empresa));

        $this->assertNotNull($direito->motivoParaRecusar($gratis));
    }

    /** Um plano pago nunca é recusado — só perde o arranque gratuito. */
    public function test_um_plano_pago_esta_sempre_disponivel(): void
    {
        $business = $this->plano('business', 24900, 30);
        $gratis   = $this->plano('gratis', 0, 180);
        $empresa  = $this->empresa();
        $this->subscrever($empresa, $gratis, 'expired');

        $direito = DireitoACortesia::daEmpresa($empresa, $this->utilizadorDe($empresa));

        $this->assertNull($direito->motivoParaRecusar($business));
        $this->assertFalse($direito->temDireitoATeste($business));
    }

    /** Um plano sem dias de teste não tem teste para dar. */
    public function test_plano_sem_dias_de_teste_nao_da_teste(): void
    {
        $semTeste = $this->plano('sem-teste', 9900, 0);
        $empresa  = $this->empresa();

        $direito = DireitoACortesia::daEmpresa($empresa, $this->utilizadorDe($empresa));

        $this->assertFalse($direito->temDireitoATeste($semTeste));
    }
}
