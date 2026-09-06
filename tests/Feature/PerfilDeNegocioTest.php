<?php

namespace Tests\Feature;

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
 *
 * O POS continua em Livewire e é lá que se prova o primeiro limite. O ecrã dos
 * artigos e o das definições passaram a React: o segundo limite prova-se agora
 * contra a API, que é quem decide o que o ecrã tem para mostrar.
 */
class PerfilDeNegocioTest extends TenantTestCase
{
    private const ARTIGOS = '/api/v1/invoicing/react/products';

    private const DEFINICOES = '/api/v1/invoicing/react/definicoes';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes(
            'invoicing.products.view',
            'invoicing.products.create',
            'invoicing.products.edit',
            'invoicing.pos.access',
            'invoicing.pos.view',
            'invoicing.settings.view',
            'invoicing.settings.edit',
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

    /** O corpo inteiro das definições, com as alterações por cima. */
    private function ficha(array $por = []): array
    {
        return array_merge($this->getJson(self::DEFINICOES)->assertOk()->json('definicoes'), $por);
    }

    private function opcoes(): array
    {
        return $this->getJson(self::ARTIGOS . '/opcoes')->assertOk()->json();
    }

    private function daLista(int $id): ?array
    {
        return collect($this->getJson(self::ARTIGOS . '?por_pagina=100')->assertOk()->json('data'))
            ->firstWhere('id', $id);
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
        $this->assertFalse($this->getJson(self::DEFINICOES)->json('definicoes.profile_pharmacy'));

        $this->putJson(self::DEFINICOES, $this->ficha(['profile_pharmacy' => true]))->assertOk();

        $this->assertDatabaseHas('invoicing_settings', [
            'tenant_id'        => $this->tenant->id,
            'profile_pharmacy' => 1,
            'profile_clothing' => 0,
        ]);

        $this->assertSame(
            [InvoicingSettings::PERFIL_FARMACIA],
            $this->definicoes()->fresh()->perfisActivos()
        );
    }

    /** E desligar tem de pegar: um interruptor que só sabe ligar não é interruptor. */
    public function test_desligar_o_interruptor_pega(): void
    {
        $this->ligarPerfis(true, true);

        $this->assertTrue($this->getJson(self::DEFINICOES)->json('definicoes.profile_pharmacy'));

        $this->putJson(self::DEFINICOES, $this->ficha([
            'profile_pharmacy' => false,
            'profile_clothing' => false,
        ]))->assertOk();

        $this->assertSame([], $this->definicoes()->fresh()->perfisActivos());
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
     * Um medicamento já preenchido continua a chegar inteiro ao ecrã com o
     * perfil desligado.
     *
     * O formulário revela a secção quando o artigo já tem dados — e só pode
     * fazê-lo porque os dados vêm sempre na ficha. Se o servidor os calasse
     * com o perfil desligado, o utilizador ficava sem forma de os ver nem de
     * os corrigir, e o artigo continuava a comportar-se como medicamento no
     * POS sem nada no ecrã a explicá-lo.
     */
    public function test_o_perfil_desligado_nao_esconde_um_medicamento_ja_preenchido(): void
    {
        $this->ligarPerfis(false, false);

        $p = $this->artigo(['dosage' => '500mg', 'active_ingredient' => 'Paracetamol']);

        $linha = $this->daLista($p->id);

        $this->assertSame('500mg', $linha['dosage']);
        $this->assertSame('Paracetamol', $linha['active_ingredient']);
        $this->assertSame([], $this->opcoes()['perfis'], 'o perfil está mesmo desligado');
    }

    /** O mesmo para uma peça de roupa — e o filtro de tamanho aparece por causa dela. */
    public function test_o_perfil_desligado_nao_esconde_uma_peca_ja_preenchida(): void
    {
        $this->ligarPerfis(false, false);

        $p = $this->artigo(['size' => '42', 'color' => 'Azul-marinho']);

        $linha = $this->daLista($p->id);

        $this->assertSame('42', $linha['size']);
        $this->assertSame('Azul-marinho', $linha['color']);

        $this->assertContains('Azul-marinho', $this->opcoes()['variantes']['cores']);
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

        $linha = $this->daLista($p->id);

        $this->assertSame('250mg', $linha['dosage']);
        $this->assertSame('cápsula', $linha['pharmaceutical_form']);
        $this->assertTrue($linha['requires_prescription'], 'a marca de receita tem de se ver');
    }

    /**
     * Com perfil e sem dados a secção não se impõe — mas fica a um clique.
     *
     * É o outro lado da regra: se bastasse "tem valor OU perfil" para revelar,
     * 99% do catálogo levava com campos de medicamento à frente sem razão. O
     * servidor diz as duas coisas em separado (o que está ligado, e o que o
     * catálogo tem) precisamente para o ecrã as poder distinguir.
     */
    public function test_um_artigo_comum_com_os_perfis_desligados_nao_impoe_a_seccao(): void
    {
        $this->ligarPerfis(false, false);

        $p = $this->artigo();

        $linha = $this->daLista($p->id);

        foreach (['dosage', 'active_ingredient', 'size', 'color', 'storage_conditions'] as $campo) {
            $this->assertArrayHasKey($campo, $linha);
            $this->assertNull($linha[$campo], "um artigo comum não traz {$campo}");
        }

        $o = $this->opcoes();

        $this->assertSame([], $o['perfis']);
        $this->assertFalse($o['variantes']['ha_receituario']);
        $this->assertFalse($o['variantes']['ha_conservacao']);
    }

    /** Com o perfil ligado e o artigo vazio, a secção aparece na mesma. */
    public function test_com_o_perfil_ligado_a_seccao_aparece_mesmo_sem_dados(): void
    {
        $this->ligarPerfis(true, false);

        $this->artigo();

        $o = $this->opcoes();

        $this->assertSame([InvoicingSettings::PERFIL_FARMACIA], $o['perfis']);
        $this->assertFalse($o['variantes']['ha_receituario'], 'não há dados — quem revela é o perfil');
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

        $this->assertSame(['42'], $this->opcoes()['variantes']['tamanhos']);
    }

    /** Numa oficina — sem perfil e sem dados — os filtros continuam escondidos. */
    public function test_sem_perfil_e_sem_dados_os_filtros_nao_aparecem(): void
    {
        $this->ligarPerfis(false, false);

        $this->artigo();

        $this->assertSame([], $this->opcoes()['variantes']['tamanhos']);
    }

    /**
     * Um artigo com TODOS os campos de sector preenchidos.
     *
     * Não é uma lista escrita à mão para ficar bonita: é o contrato. Vai pela
     * API, volta pela API, e é dela que sai a lista de campos que o ecrã tem
     * de tratar.
     */
    private const UM_DE_TUDO = [
        'requires_prescription' => true,
        'is_controlled' => true,
        'active_ingredient' => 'Paracetamol',
        'dosage' => '500mg',
        'pharmaceutical_form' => 'comprimido',
        'armed_registration' => 'ARMED-0001',
        'size' => 'M',
        'color' => 'Azul-marinho',
        'gender' => 'unissexo',
        'material' => '100% algodão',
        'net_content' => '200g',
        'pao_months' => 12,
        'inci_ingredients' => 'Aqua, Glycerin',
        'storage_conditions' => 'refrigerado',
        'allergens' => 'glúten',
        'origin_country' => 'Angola',
    ];

    /**
     * O ECRÃ EM REACT DESENHA MESMO A SECÇÃO DE SECTOR — E OS FILTROS.
     *
     * Houve uma temporada em que não desenhava: o servidor entregava tudo (os
     * valores gravados, os perfis ligados e o que o catálogo tem) e o
     * `Produtos.tsx` não mostrava nada disso. Uma farmácia ou uma loja de
     * roupa não conseguia preencher nem ver os campos do seu ramo.
     *
     * O que este ensaio guarda são três coisas:
     *
     * 1. cada campo de sector que a API aceita e devolve está cablado no ecrã
     *    — o dia em que alguém acrescentar uma coluna e se esquecer do `.tsx`,
     *    é aqui que aparece;
     * 2. os quatro filtros que a API aceita existem na lista;
     * 3. **a regra que não se cruza**: o que decide mostrar um bloco é «o
     *    perfil está ligado OU o artigo já tem o dado», nunca só o perfil.
     *    Sem a segunda metade, desligar uma definição de visualização escondia
     *    dados gravados sem forma de os ver nem de os corrigir.
     *
     * O comportamento vivo — a secção a aparecer com o perfil ligado e a NÃO
     * desaparecer com ele desligado — prova-se no browser, em
     * `tests/browser/react.produtos.spec.js`.
     */
    public function test_o_ecra_em_react_desenha_a_seccao_de_sector_e_os_filtros(): void
    {
        $this->ligarPerfis(true, true);

        $o = $this->opcoes();

        $this->assertArrayHasKey('perfis', $o);
        $this->assertArrayHasKey('variantes', $o);
        $this->assertNotEmpty($o['generos']);
        $this->assertNotEmpty($o['conservacao']);

        $categoria = \App\Models\Category::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Geral'],
            ['is_active' => true]
        );

        $id = $this->postJson(self::ARTIGOS, self::UM_DE_TUDO + [
            'name' => 'Artigo com Tudo',
            'type' => 'produto',
            'price' => 1000,
            'unit' => 'un',
            'category_id' => $categoria->id,
            'tax_type' => 'isento',
            'exemption_reason' => 'M99',
        ])->assertCreated()->json('data.id');

        $ficha = $this->daLista($id);

        $ecra = file_get_contents(resource_path('js/ecras/facturacao/Produtos.tsx'));

        // Sem espaços dos dois lados: o que se guarda é a REGRA, não a
        // formatação com que o Prettier a partiu em linhas neste momento.
        $semEspacos = preg_replace('/\s+/', '', $ecra);

        foreach (self::UM_DE_TUDO as $campo => $valor) {
            $this->assertArrayHasKey($campo, $ficha, "a API tinha de devolver {$campo}");
            $this->assertNotNull($ficha[$campo], "o valor gravado de {$campo} não pode voltar vazio");
            $this->assertStringContainsString(
                $campo,
                $ecra,
                "o ecrã em React não trata do campo de sector {$campo}"
            );
        }

        foreach (['prescricao', 'tamanho', 'cor', 'conservacao'] as $filtro) {
            $this->assertStringContainsString(
                "filtros.{$filtro}",
                $semEspacos,
                "falta o filtro de sector {$filtro} na lista, e a API já o aceita"
            );
        }

        // A regra dos quatro blocos do formulário: perfil OU dado gravado.
        foreach ([
            "perfis.has('farmacia')||temMedicamento",
            "perfis.has('vestuario')||temVestuario",
            "perfis.has('cosmetica')||temCosmetica",
            "perfis.has('mercearia')||temMercearia",
        ] as $regra) {
            $this->assertStringContainsString(
                $regra,
                $semEspacos,
                'o perfil não pode decidir sozinho: um artigo com o dado gravado mostra-o sempre'
            );
        }

        // E a mesma regra nos filtros da lista.
        $this->assertStringContainsString(
            "perfis.has('farmacia')||opcoes.variantes.ha_receituario",
            $semEspacos,
            'o filtro de receita tem de aparecer também a quem já tem artigos marcados'
        );

        // A saída para quem não tem perfil nenhum ligado: os campos ficam a um
        // clique, sem ter de ir às Definições marcar um artigo isolado.
        $this->assertStringContainsString('campos próprios do ramo', $ecra);
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
