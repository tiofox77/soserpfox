<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Products;
use App\Livewire\Invoicing\Settings;
use App\Livewire\POS\POSSystem;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O interruptor de perfil do negócio — e, sobretudo, os dois limites que ele
 * não pode atravessar.
 *
 * O perfil manda no que APARECE POR OMISSÃO. Não manda nos avisos do POS, que
 * seguem os dados do artigo, nem esconde campos que já têm valor gravado. Estes
 * testes existem porque as duas coisas são fáceis de partir sem dar por isso:
 * bastaria alguém acrescentar um `if ($perfilFarmacia)` a um sítio plausível.
 */
class PerfilDeNegocioTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes(
            'invoicing.products.view',
            'invoicing.products.create',
            'invoicing.products.edit',
            'invoicing.pos.access',
            'invoicing.pos.view',
        );
    }

    private function definicoes(): InvoicingSettings
    {
        return InvoicingSettings::forTenant($this->tenant->id);
    }

    /** Grava pelo modelo (e não por DB::table) para a memória do pedido ser limpa. */
    private function ligarPerfis(bool $farmacia, bool $vestuario): void
    {
        $this->definicoes()->update([
            'profile_pharmacy' => $farmacia,
            'profile_clothing' => $vestuario,
        ]);
    }

    private function artigo(array $campos = []): Product
    {
        $categoria = \App\Models\Category::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Geral'],
            ['is_active' => true]
        );

        return Product::create(array_merge([
            'tenant_id'   => $this->tenant->id,
            'name'        => 'Artigo ' . uniqid(),
            'code'        => 'A-' . uniqid(),
            'price'       => 1000,
            'type'        => 'produto',
            'is_active'   => true,
            'tax_id'      => $this->imposto->id,
            'category_id' => $categoria->id,
            'tax_rate_id' => $this->imposto->id,
        ], $campos));
    }

    // ==================== o interruptor ====================

    /** "Normal" não é um terceiro valor: é nenhum dos dois ligado. */
    public function test_sem_nenhum_perfil_ligado_a_lista_vem_vazia(): void
    {
        $this->assertSame([], $this->definicoes()->perfisActivos());
    }

    /**
     * Os dois ao mesmo tempo.
     *
     * É o supermercado com balcão de farmácia. Uma escolha única obrigava-o a
     * mentir — daí serem dois booleanos e não um campo só.
     */
    public function test_os_dois_perfis_podem_estar_ligados_ao_mesmo_tempo(): void
    {
        $this->ligarPerfis(true, true);

        $this->assertSame(
            [InvoicingSettings::PERFIL_FARMACIA, InvoicingSettings::PERFIL_VESTUARIO],
            $this->definicoes()->perfisActivos()
        );
    }

    /** O ecrã das Definições grava mesmo as duas colunas. */
    public function test_o_interruptor_das_definicoes_grava(): void
    {
        Livewire::test(Settings::class)
            ->assertSet('profile_pharmacy', false)
            ->set('profile_pharmacy', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('invoicing_settings', [
            'tenant_id'        => $this->tenant->id,
            'profile_pharmacy' => 1,
            'profile_clothing' => 0,
        ]);

        $this->assertSame(
            [InvoicingSettings::PERFIL_FARMACIA],
            $this->definicoes()->perfisActivos()
        );
    }

    /** E desligar tem de pegar: um interruptor que só sabe ligar não é interruptor. */
    public function test_desligar_o_interruptor_pega(): void
    {
        $this->ligarPerfis(true, true);

        Livewire::test(Settings::class)
            ->assertSet('profile_pharmacy', true)
            ->set('profile_pharmacy', false)
            ->set('profile_clothing', false)
            ->call('save');

        $this->assertSame([], $this->definicoes()->perfisActivos());
    }

    // ========= a linha que não se cruza (1): os avisos do POS =========

    /**
     * O psicotrópico pergunta com o perfil de farmácia DESLIGADO.
     *
     * Este é o teste que interessa. Se alguém ligar o aviso ao perfil, quem
     * desligasse a definição de visualização passava a vender psicotrópicos sem
     * uma única pergunta — e o artigo continuava marcado como controlado na
     * base de dados. Uma protecção que se desliga assim não é protecção.
     */
    public function test_o_aviso_de_psicotropico_dispara_com_o_perfil_desligado(): void
    {
        $this->ligarPerfis(false, false);
        $this->abrirTurno();

        $p = $this->produtoComStock(10);
        $p->update(['is_controlled' => true]);

        Livewire::test(POSSystem::class)
            ->call('addToCart', $p->id)
            ->assertDispatched('pos-confirmar-controlado');
    }

    /** O mesmo para a receita médica: avisa na mesma, perfil ou não. */
    public function test_o_aviso_de_receita_dispara_com_o_perfil_desligado(): void
    {
        $this->ligarPerfis(false, false);
        $this->abrirTurno();

        $p = $this->produtoComStock(10);
        $p->update(['requires_prescription' => true]);

        Livewire::test(POSSystem::class)
            ->call('addToCart', $p->id)
            ->assertDispatched('notify', function (string $evento, array $dados) {
                $carga = $dados[0] ?? $dados;

                return str_contains($carga['message'] ?? '', 'RECEITA MÉDICA');
            });
    }

    // ========= a linha que não se cruza (2): dados já gravados =========

    /**
     * Um medicamento já preenchido mostra os campos com o perfil desligado.
     *
     * O formulário arranca com a secção revelada e aberta quando o artigo já
     * tem dados. Sem isto, desligar o perfil escondia valores que continuavam
     * gravados — e o utilizador ficava sem forma de os ver nem de os corrigir.
     */
    public function test_o_perfil_desligado_nao_esconde_um_medicamento_ja_preenchido(): void
    {
        $this->ligarPerfis(false, false);

        $p = $this->artigo(['dosage' => '500mg', 'active_ingredient' => 'Paracetamol']);

        Livewire::test(Products::class)
            ->call('edit', $p->id)
            ->assertSeeHtml('revelado: true')
            ->assertSeeHtml('aberto: true')
            ->assertSeeHtml('med: true')
            ->assertSeeHtml('500mg');
    }

    /** O mesmo para uma peça de roupa. */
    public function test_o_perfil_desligado_nao_esconde_uma_peca_ja_preenchida(): void
    {
        $this->ligarPerfis(false, false);

        $p = $this->artigo(['size' => '42', 'color' => 'Azul-marinho']);

        Livewire::test(Products::class)
            ->call('edit', $p->id)
            ->assertSeeHtml('revelado: true')
            ->assertSeeHtml('vest: true')
            ->assertSeeHtml('Azul-marinho');
    }

    /** A ficha de consulta segue a mesma regra. */
    public function test_a_ficha_mostra_os_campos_gravados_com_o_perfil_desligado(): void
    {
        $this->ligarPerfis(false, false);

        $p = $this->artigo([
            'requires_prescription' => true,
            'dosage'                => '250mg',
            'pharmaceutical_form'   => 'cápsula',
        ]);

        Livewire::test(Products::class)
            ->call('view', $p->id)
            ->assertSee('250mg')
            ->assertSee('Exige receita médica');
    }

    /**
     * Com perfil e sem dados a secção não se impõe — mas fica a um clique.
     *
     * É o outro lado da regra: se bastasse "tem valor OU perfil" para revelar,
     * 99% do catálogo levava com campos de medicamento à frente sem razão.
     */
    public function test_um_artigo_comum_com_os_perfis_desligados_nao_impoe_a_seccao(): void
    {
        $this->ligarPerfis(false, false);

        $p = $this->artigo();

        Livewire::test(Products::class)
            ->call('edit', $p->id)
            ->assertSeeHtml('revelado: false')
            ->assertSee('Este artigo é medicamento ou vestuário?');
    }

    /** Com o perfil ligado e o artigo vazio, a secção aparece na mesma. */
    public function test_com_o_perfil_ligado_a_seccao_aparece_mesmo_sem_dados(): void
    {
        $this->ligarPerfis(true, false);

        $p = $this->artigo();

        Livewire::test(Products::class)
            ->call('edit', $p->id)
            ->assertSeeHtml('revelado: true')
            ->assertSeeHtml('med: true')
            ->assertSeeHtml('vest: false');
    }

    /**
     * Os filtros da lista seguem a mesma regra.
     *
     * Sem isto, desligar o perfil deixava tamanhos e cores gravados sem forma
     * de os filtrar.
     */
    public function test_o_filtro_de_tamanho_aparece_com_o_perfil_desligado_se_houver_tamanhos(): void
    {
        $this->ligarPerfis(false, false);

        $this->artigo(['size' => '42']);

        Livewire::test(Products::class)
            ->assertSeeHtml('wire:model.live="filterTamanho"');
    }

    /** Numa oficina — sem perfil e sem dados — os filtros continuam escondidos. */
    public function test_sem_perfil_e_sem_dados_os_filtros_nao_aparecem(): void
    {
        $this->ligarPerfis(false, false);

        $this->artigo();

        Livewire::test(Products::class)
            ->assertDontSeeHtml('wire:model.live="filterTamanho"');
    }

    /** Sem turno aberto o POS redirecciona no mount e o componente nem existe. */
    private function abrirTurno(): void
    {
        \App\Models\Invoicing\PosShift::create([
            'tenant_id'    => $this->tenant->id,
            'user_id'      => $this->user->id,
            'shift_number' => 'T' . strtoupper(substr(uniqid(), -8)),
            'opened_at'    => now(),
            'status'       => 'open',
        ]);

        \Illuminate\Support\Facades\Cache::flush();
        \Darryldecode\Cart\Facades\CartFacade::session($this->user->id . '_t' . $this->tenant->id)->clear();
    }
}
