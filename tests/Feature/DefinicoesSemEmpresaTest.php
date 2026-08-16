<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Settings;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Um utilizador sem empresa nenhuma a abrir o ecra de definicoes.
 *
 * O activeTenantId() devolve null quando quem esta autenticado nao esta ligado
 * a empresa nenhuma — e o dono da plataforma costuma estar nesse estado. O
 * forTenant(null) descia ate um firstOrCreate(['tenant_id' => null]) e tentava
 * INSERIR uma linha de definicoes sem empresa: a coluna e NOT NULL e o ecra
 * dava 500 em producao.
 *
 * Duas promessas a guardar: nunca se escreve uma linha sem empresa, e o ecra
 * diz o que se passa em vez de estoirar.
 */
class DefinicoesSemEmpresaTest extends TenantTestCase
{
    private function utilizadorSemEmpresa(): User
    {
        $u = User::create([
            'name'      => 'Sem Empresa',
            'email'     => 'sem-empresa-' . uniqid() . '@exemplo.ao',
            'password'  => bcrypt('irrelevante'),
            'tenant_id' => null,
        ]);

        // Sem sessao de empresa activa: e este o estado que provoca o 500.
        session()->forget('active_tenant_id');

        return $u;
    }

    public function test_sem_empresa_nao_estoira(): void
    {
        $this->actingAs($this->utilizadorSemEmpresa());

        $definicoes = InvoicingSettings::forTenant(activeTenantId());

        $this->assertNotNull($definicoes, 'devia devolver algo com que se possa ler um valor por omissao');
    }

    /** E, sobretudo, nao pode deixar lixo gravado. */
    public function test_sem_empresa_nao_grava_linha_nenhuma(): void
    {
        $this->actingAs($this->utilizadorSemEmpresa());

        $antes = DB::table('invoicing_settings')->count();

        InvoicingSettings::forTenant(activeTenantId());

        $this->assertSame($antes, DB::table('invoicing_settings')->count(),
            'gravou uma linha de definicoes sem empresa');
        $this->assertSame(0, DB::table('invoicing_settings')->whereNull('tenant_id')->count());
    }

    /** Os valores por omissao continuam a poder ser lidos por quem so quer ler. */
    public function test_os_valores_por_omissao_continuam_legiveis(): void
    {
        $this->actingAs($this->utilizadorSemEmpresa());

        $definicoes = InvoicingSettings::forTenant(activeTenantId());

        $this->assertSame('AOA', $definicoes->default_currency);
        $this->assertFalse($definicoes->exists, 'nao pode estar gravado na base');
    }

    /** O ecra abre e explica-se, em vez de dar 500. */
    public function test_o_ecra_diz_o_que_se_passa(): void
    {
        $this->actingAs($this->utilizadorSemEmpresa());

        Livewire::test(Settings::class)
            ->assertOk()
            ->assertSee('Nenhuma empresa activa')
            ->assertSee('Escolha primeiro a empresa');
    }

    /**
     * E NAO mostra a ficha. Deixar o formulario a desenhar-se contra os valores
     * por omissao de uma linha que nao existe era pior do que o erro: quem o
     * preenchesse estaria a escrever para o vazio.
     */
    public function test_o_ecra_nao_mostra_a_ficha_para_preencher(): void
    {
        $this->actingAs($this->utilizadorSemEmpresa());

        $html = Livewire::test(Settings::class)->html();

        // Pelo campo desenhado e nao pelo nome da propriedade: o Livewire poe
        // as propriedades publicas todas no snapshot, e procurar la o nome
        // dava sempre positivo, com ficha ou sem ela.
        $this->assertStringNotContainsString('wire:model="default_currency"', $html,
            'a ficha esta la para ser preenchida');
        $this->assertStringNotContainsString('wire:submit', $html);
        $this->assertLessThan(20000, strlen($html), 'isto ainda parece a ficha toda');
    }

    /** Com empresa, a ficha aparece na mesma. */
    public function test_com_empresa_a_ficha_continua_a_aparecer(): void
    {
        Livewire::test(Settings::class)
            ->assertOk()
            ->assertDontSee('Nenhuma empresa activa');
    }

    /** Com empresa continua tudo como estava: le, cria uma vez, e reaproveita. */
    public function test_com_empresa_continua_a_criar_e_a_reaproveitar(): void
    {
        InvoicingSettings::esquecerMemoria();
        DB::table('invoicing_settings')->where('tenant_id', $this->tenant->id)->delete();

        $primeira = InvoicingSettings::forTenant($this->tenant->id);

        $this->assertTrue($primeira->exists);
        $this->assertSame($this->tenant->id, $primeira->tenant_id);

        InvoicingSettings::esquecerMemoria();
        $segunda = InvoicingSettings::forTenant($this->tenant->id);

        $this->assertSame($primeira->id, $segunda->id, 'criou uma segunda linha para a mesma empresa');
    }
}
