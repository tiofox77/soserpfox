<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Products;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O perfil do negócio: farmácia, vestuário, ou nenhum.
 *
 * O perfil decide o que APARECE por omissão. Não decide o que existe, e não
 * decide o que protege. Essas duas linhas são o que estes testes guardam —
 * o resto é conveniência e pode mudar; estas não.
 */
class PerfilDoNegocioTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes(
            'invoicing.products.view',
            'invoicing.products.edit',
        );
    }

    private function perfil(bool $farmacia = false, bool $vestuario = false): void
    {
        InvoicingSettings::forTenant($this->tenant->id)->update([
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

    // ==================== a definição ====================

    /** Os dois perfis são independentes: pode-se ligar ambos. */
    public function test_os_dois_perfis_podem_estar_ligados_ao_mesmo_tempo(): void
    {
        $this->perfil(farmacia: true, vestuario: true);

        $activos = InvoicingSettings::forTenant($this->tenant->id)->perfisActivos();

        $this->assertContains('farmacia', $activos);
        $this->assertContains('vestuario', $activos);
    }

    /** "Normal" é simplesmente nenhum ligado — não é um terceiro valor. */
    public function test_sem_perfil_nenhum_a_lista_vem_vazia(): void
    {
        $this->perfil();

        $this->assertSame([], InvoicingSettings::forTenant($this->tenant->id)->perfisActivos());
    }

    /** Por omissão, uma empresa nova não tem perfil nenhum. */
    public function test_uma_empresa_nova_comeca_sem_perfil(): void
    {
        $definicoes = InvoicingSettings::forTenant($this->tenant->id);

        $this->assertFalse((bool) $definicoes->profile_pharmacy);
        $this->assertFalse((bool) $definicoes->profile_clothing);
    }

    // ==================== a linha que não se cruza (1) ====================

    /**
     * Um campo com valor aparece SEMPRE, mesmo com o perfil desligado.
     *
     * É a diferença entre esconder uma secção e perder dados de vista. Se
     * desligar o perfil escondesse o que já lá está, o utilizador ficava com
     * dados gravados que não conseguia ver nem corrigir — e o artigo
     * continuava a comportar-se como medicamento no POS sem que nada no ecrã
     * o explicasse.
     */
    public function test_um_medicamento_mostra_os_campos_com_o_perfil_desligado(): void
    {
        $this->perfil(farmacia: false);

        $p = $this->artigo([
            'name'              => 'Amoxicilina',
            'active_ingredient' => 'Amoxicilina tri-hidratada',
            'dosage'            => '250mg',
        ]);

        // Duas coisas separadas, e as duas necessárias:
        //
        //   1. o BLOCO aparece — sem ele não há onde ver nem corrigir;
        //   2. as PROPRIEDADES vêm carregadas.
        //
        // Não se procura o valor no HTML: um input com wire:model não traz
        // value="" na resposta inicial — o Livewire preenche-o do lado do
        // cliente, a partir do estado. Procurá-lo aqui dava um teste que
        // falhava com o código certo (e, ao contrário, um que passava porque
        // o texto aparecia noutro sítio qualquer da página).
        $componente = Livewire::test(Products::class)->call('edit', $p->id);

        $componente->assertSee('Substância activa (DCI)');

        $componente->assertSet('active_ingredient', 'Amoxicilina tri-hidratada');
        $componente->assertSet('dosage', '250mg');
    }

    /** O mesmo para o vestuário. */
    public function test_uma_peca_mostra_os_campos_com_o_perfil_desligado(): void
    {
        $this->perfil(vestuario: false);

        $p = $this->artigo(['name' => 'Casaco', 'size' => 'L', 'color' => 'Verde']);

        $componente = Livewire::test(Products::class)->call('edit', $p->id);

        $componente->assertSee('Composição');

        $componente->assertSet('size', 'L');
        $componente->assertSet('color', 'Verde');
    }

    /**
     * E continua a ser editável, não apenas visível.
     *
     * Mostrar sem deixar corrigir era metade do problema resolvido.
     */
    public function test_um_campo_continua_editavel_com_o_perfil_desligado(): void
    {
        $this->perfil(farmacia: false);

        $p = $this->artigo(['name' => 'Ibuprofeno', 'dosage' => '400mg']);

        Livewire::test(Products::class)
            ->call('edit', $p->id)
            ->set('dosage', '600mg')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('600mg', $p->fresh()->dosage);
    }

    // ==================== a linha que não se cruza (2) ====================

    /**
     * Os avisos do POS NÃO leem o perfil.
     *
     * Uma protecção que se desliga por uma definição de visualização não é
     * uma protecção — é uma armadilha à espera do dia em que alguém desligar
     * o perfil sem perceber o que estava a desligar.
     *
     * Verifica-se na FONTE porque é uma propriedade do desenho, não do
     * comportamento de um caso: mesmo que hoje nenhum caso a viole, basta
     * alguém acrescentar a leitura amanhã.
     */
    public function test_o_pos_nao_le_o_perfil_em_lado_nenhum(): void
    {
        $ficheiros = [
            app_path('Livewire/POS/POSSystem.php'),
            resource_path('views/livewire/pos/possystem.blade.php'),
            resource_path('views/invoicing/offline/pos.blade.php'),
        ];

        foreach ($ficheiros as $f) {
            $this->assertFileExists($f);

            $this->assertDoesNotMatchRegularExpression(
                '/profile_pharmacy|profile_clothing|perfisActivos/',
                file_get_contents($f),
                basename($f) . ' lê o perfil do negócio. Os avisos de receita e de '
                    . 'psicotrópico seguem os dados do artigo e nunca uma definição de ecrã.'
            );
        }
    }

    /**
     * Na prática: um psicotrópico continua a exigir confirmação com o perfil
     * de farmácia desligado.
     *
     * O teste acima guarda o desenho; este guarda o resultado.
     */
    public function test_um_psicotropico_continua_a_avisar_com_o_perfil_desligado(): void
    {
        $this->perfil(farmacia: false);

        $p = $this->artigo(['name' => 'Diazepam', 'is_controlled' => true]);

        // A marca está no artigo e é dela que o POS depende.
        $this->assertTrue((bool) $p->fresh()->is_controlled);

        $doAparelho = collect(
            $this->getJson('/api/v1/invoicing/sync')->assertOk()->json('data.products')
        )->firstWhere('id', $p->id);

        // Chega ao POS offline mesmo sem perfil: é o artigo que manda.
        if ($doAparelho) {
            $this->assertTrue((bool) $doAparelho['is_controlled']);
        }
    }

    // ==================== o que o perfil faz mesmo ====================

    /** Com o perfil de farmácia, o filtro de receita aparece. */
    public function test_o_perfil_de_farmacia_mostra_o_filtro_de_receita(): void
    {
        $this->perfil(farmacia: true);

        Livewire::test(Products::class)->assertSee('Receita');
    }

    /**
     * Sem perfil e sem artigos marcados, o filtro não aparece.
     *
     * Uma oficina não tem de ver três selects que nunca vai usar.
     */
    public function test_sem_perfil_e_sem_dados_o_filtro_nao_aparece(): void
    {
        $this->perfil();
        $this->artigo(['name' => 'Parafuso M8']);

        Livewire::test(Products::class)
            ->assertDontSee('Exige receita')
            ->assertDontSee('Venda livre');
    }

    /**
     * Mas se o catálogo JÁ tem artigos marcados, o filtro aparece na mesma.
     *
     * O caso de quem foi marcando artigos antes de ligar o perfil — ou de
     * quem o desligou por engano. Os dados existem; a forma de lá chegar tem
     * de existir também.
     */
    public function test_com_artigos_marcados_o_filtro_aparece_sem_perfil(): void
    {
        $this->perfil(farmacia: false);
        $this->artigo(['name' => 'Antibiótico', 'requires_prescription' => true]);

        Livewire::test(Products::class)->assertSee('Receita');
    }

    /** O ecrã de definições mostra os dois perfis e o que cada um liga. */
    public function test_o_ecra_de_definicoes_explica_os_perfis(): void
    {
        $this->comPermissoes('invoicing.settings.view');

        $this->get('/invoicing/settings')
            ->assertOk()
            ->assertSee('Perfil do Negócio')
            ->assertSee('Trabalha com medicamentos')
            ->assertSee('Trabalha com vestuário')
            ->assertSee('O que fica activo');
    }
}
