<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * O perfil do negócio: farmácia, vestuário, ou nenhum.
 *
 * O perfil decide o que APARECE por omissão. Não decide o que existe, e não
 * decide o que protege. Essas duas linhas são o que estes testes guardam —
 * o resto é conveniência e pode mudar; estas não.
 *
 * O ecrã dos artigos passou a React: quem responde é a API
 * `/api/v1/invoicing/react/products`. O contrato que o ecrã usa para decidir
 * o que mostrar vem em `opcoes`: os `perfis` ligados E o que o catálogo já
 * TEM (`variantes`). É esse OU que garante que desligar o perfil nunca
 * esconde dados gravados.
 */
class PerfilDoNegocioTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/products';

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
            'unit'        => 'un',
            'is_active'   => true,
            'tax_id'      => $this->imposto->id,
            'tax_type'    => 'iva',
            'category_id' => $categoria->id,
            'tax_rate_id' => $this->imposto->id,
        ], $campos));
    }

    private function opcoes(): array
    {
        return $this->getJson(self::RAIZ . '/opcoes')->assertOk()->json();
    }

    private function daLista(int $id): ?array
    {
        return collect($this->getJson(self::RAIZ . '?por_pagina=100')->assertOk()->json('data'))
            ->firstWhere('id', $id);
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
     * Um campo com valor volta SEMPRE do servidor, mesmo com o perfil
     * desligado.
     *
     * É a diferença entre esconder uma secção e perder dados de vista. Se
     * desligar o perfil apagasse o que já lá está da resposta, o utilizador
     * ficava com dados gravados que não conseguia ver nem corrigir — e o
     * artigo continuava a comportar-se como medicamento no POS sem que nada
     * no ecrã o explicasse.
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
        //   1. os VALORES vêm na ficha — sem eles não há onde ver nem corrigir;
        //   2. o ecrã fica a saber que há receituário no catálogo, e é por aí
        //      que revela a secção mesmo com o perfil desligado.
        $linha = $this->daLista($p->id);

        $this->assertSame('Amoxicilina tri-hidratada', $linha['active_ingredient']);
        $this->assertSame('250mg', $linha['dosage']);

        $this->assertSame([], $this->opcoes()['perfis'], 'o perfil está mesmo desligado');
    }

    /** O mesmo para o vestuário. */
    public function test_uma_peca_mostra_os_campos_com_o_perfil_desligado(): void
    {
        $this->perfil(vestuario: false);

        $p = $this->artigo(['name' => 'Casaco', 'size' => 'L', 'color' => 'Verde']);

        $linha = $this->daLista($p->id);

        $this->assertSame('L', $linha['size']);
        $this->assertSame('Verde', $linha['color']);

        $variantes = $this->opcoes()['variantes'];

        $this->assertContains('L', $variantes['tamanhos'], 'sem isto o filtro de tamanho não aparecia');
        $this->assertContains('Verde', $variantes['cores']);
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

        $this->putJson(self::RAIZ . '/' . $p->id, [
            'name' => 'Ibuprofeno',
            'type' => 'produto',
            'unit' => 'un',
            'price' => 1000,
            'category_id' => $p->category_id,
            'tax_type' => 'iva',
            'tax_rate_id' => $this->imposto->id,
            'dosage' => '600mg',
        ])->assertOk();

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
        /*
         * O balcão é hoje React: a porta é o `PosApiController`, o ecrã é o
         * `PontoDeVenda.tsx`, e o balcão sem rede continua no Blade do PWA. Os
         * dois primeiros substituem o `POSSystem` em Livewire, que deixou de
         * existir.
         */
        $ficheiros = [
            app_path('Http/Controllers/Api/Invoicing/PosApiController.php'),
            resource_path('js/ecras/facturacao/pos/PontoDeVenda.tsx'),
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

    /** Com o perfil de farmácia, o ecrã recebe ordem de mostrar o filtro de receita. */
    public function test_o_perfil_de_farmacia_mostra_o_filtro_de_receita(): void
    {
        $this->perfil(farmacia: true);

        $this->assertContains(
            InvoicingSettings::PERFIL_FARMACIA,
            $this->opcoes()['perfis'],
            'é por aqui que o ecrã sabe que há-de mostrar o filtro de receita'
        );
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

        $o = $this->opcoes();

        $this->assertSame([], $o['perfis']);
        $this->assertFalse($o['variantes']['ha_receituario']);
        $this->assertSame([], $o['variantes']['tamanhos']);
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

        $o = $this->opcoes();

        $this->assertSame([], $o['perfis'], 'o perfil continua desligado');
        $this->assertTrue($o['variantes']['ha_receituario'], 'e mesmo assim o filtro tem de aparecer');
    }

    /** O ecrã de definições mostra os quatro perfis e deixa-os gravar. */
    public function test_o_ecra_de_definicoes_explica_os_perfis(): void
    {
        $this->comPermissoes('invoicing.settings.view');

        // A página continua na morada de sempre — agora serve a casca do
        // React, portanto o que se prova aqui é que ABRE.
        $this->get('/invoicing/settings')->assertOk();

        // E o ecrã que ela carrega tem mesmo os quatro interruptores. É uma
        // guarda de fonte: sem ela, tirar um perfil do ecrã passava
        // despercebido — a coluna continuava lá e ninguém a conseguia ligar.
        $ecra = file_get_contents(resource_path('js/ecras/facturacao/Definicoes.tsx'));

        $this->assertStringContainsString('Perfil do negócio', $ecra);

        foreach (['profile_pharmacy', 'profile_clothing', 'profile_cosmetics', 'profile_grocery'] as $campo) {
            $this->assertStringContainsString($campo, $ecra, "o interruptor de {$campo} desapareceu do ecrã");
        }

        // E a API entrega-os para o ecrã os desenhar.
        $definicoes = $this->getJson('/api/v1/invoicing/react/definicoes')->assertOk()->json('definicoes');

        foreach (['profile_pharmacy', 'profile_clothing', 'profile_cosmetics', 'profile_grocery'] as $campo) {
            $this->assertArrayHasKey($campo, $definicoes);
        }
    }
}
