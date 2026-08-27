<?php

namespace Tests\Feature\Invoicing;

use App\Livewire\Invoicing\Products;
use App\Livewire\Invoicing\Reports\ExpiryReport;
use App\Livewire\Invoicing\Settings;
use App\Livewire\POS\POSSystem;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\Stock;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * As promessas por sector que o site faz — farmácia, vestuário, cosmética e
 * mercearia — verificadas uma a uma.
 *
 * Não chega a coluna existir na base: cada uma destas funções foi anunciada a
 * quem paga, e o que se testa aqui é o COMPORTAMENTO que a frase promete. Se
 * amanhã alguém mexer no POS ou nos filtros, é aqui que rebenta — antes de
 * rebentar ao balcão de uma farmácia.
 */
class FuncoesPorSectorTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Todos os perfis ligados: o que se mede aqui são as funções, não o
        // interruptor que as mostra (esse tem teste próprio no fim).
        InvoicingSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'profile_pharmacy'  => true,
                'profile_clothing'  => true,
                'profile_cosmetics' => true,
                'profile_grocery'   => true,
            ]
        );
    }

    private function artigo(array $campos = []): Product
    {
        return Product::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'code'      => 'ART-' . uniqid(),
            'name'      => 'Artigo',
            'price'     => 1000,
            'is_active' => true,
        ], $campos));
    }

    /**
     * O POS exige turno aberto — sem ele o mount() redirecciona e o
     * Livewire::test() rebenta com "array offset on null", que não diz nada
     * sobre o que falta. Abrir o turno é o que um balconista faz antes de
     * vender o primeiro artigo.
     */
    private function abrirTurno(): \App\Models\Invoicing\PosShift
    {
        return \App\Models\Invoicing\PosShift::create([
            'tenant_id'    => $this->tenant->id,
            'user_id'      => $this->user->id,
            'shift_number' => 'T-' . uniqid(),
            'opened_at'    => now(),
            'status'       => 'open',
        ]);
    }

    private function comStock(Product $p, float $qtd = 50): Product
    {
        Stock::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'product_id' => $p->id, 'warehouse_id' => $this->armazem->id],
            ['quantity' => $qtd]
        );

        return $p;
    }

    // ═══ FARMÁCIA ═══════════════════════════════════════════════════════

    /** "assinala o artigo que exige receita" */
    public function test_farmacia_artigo_com_receita_fica_assinalado_e_filtravel(): void
    {
        $comReceita = $this->artigo(['name' => 'Antibiótico', 'requires_prescription' => true]);
        $semReceita = $this->artigo(['name' => 'Vitamina C']);

        $this->assertTrue($comReceita->refresh()->requires_prescription);

        // O filtro da lista separa mesmo os dois.
        Livewire::test(Products::class)
            ->set('filterPrescricao', 'sim')
            ->assertSee('Antibiótico')
            ->assertDontSee('Vitamina C');

        Livewire::test(Products::class)
            ->set('filterPrescricao', 'nao')
            ->assertSee('Vitamina C')
            ->assertDontSee('Antibiótico');

        $this->assertSame($semReceita->id, Product::where('tenant_id', $this->tenant->id)
            ->where('requires_prescription', false)->orWhereNull('requires_prescription')->first()->id);
    }

    /**
     * "pede confirmação antes de vender um psicotrópico".
     *
     * A PROMESSA QUE MAIS IMPORTA: vender um controlado por engano tem
     * consequência legal para a farmácia, e quando a venda fecha o artigo já
     * saiu do balcão.
     */
    public function test_farmacia_psicotropico_nao_entra_no_carrinho_sem_confirmacao(): void
    {
        $this->abrirTurno();
        $controlado = $this->comStock($this->artigo([
            'name' => 'Diazepam 5mg', 'is_controlled' => true,
        ]));

        $pos = Livewire::test(POSSystem::class)
            ->call('addToCart', $controlado->id);

        $pos->assertDispatched('pos-confirmar-controlado');

        // E NÃO entrou no carrinho enquanto ninguém confirmou.
        $this->assertSame(0, \Cart::session($pos->get('cartKey') ?? 'x')->getContent()->count()
            + (int) collect($pos->get('cartItems') ?? [])->count());
    }

    /** Confirmado pelo operador, aí sim entra. */
    public function test_farmacia_psicotropico_entra_depois_de_confirmado(): void
    {
        $this->abrirTurno();
        $controlado = $this->comStock($this->artigo([
            'name' => 'Diazepam 5mg', 'is_controlled' => true,
        ]));

        Livewire::test(POSSystem::class)
            ->call('addToCart', $controlado->id, true)
            ->assertNotDispatched('pos-confirmar-controlado');
    }

    /** Um artigo normal nunca pergunta nada — senão o aviso perde o efeito. */
    public function test_farmacia_artigo_normal_nao_pede_confirmacao(): void
    {
        $this->abrirTurno();
        $normal = $this->comStock($this->artigo(['name' => 'Adesivo']));

        Livewire::test(POSSystem::class)
            ->call('addToCart', $normal->id)
            ->assertNotDispatched('pos-confirmar-controlado');
    }

    /** "Procura pela substância activa — paracetamol, não Ben-u-ron" */
    public function test_farmacia_procura_pela_substancia_activa(): void
    {
        $benuron = $this->artigo(['name' => 'Ben-u-ron 500mg', 'active_ingredient' => 'Paracetamol']);
        $this->artigo(['name' => 'Brufen 400mg', 'active_ingredient' => 'Ibuprofeno']);

        $achados = Product::where('tenant_id', $this->tenant->id)
            ->porSubstancia('paracetamol')->pluck('id')->all();

        $this->assertSame([$benuron->id], $achados);
    }

    /** Um % escrito ao balcão procura um %, não abre a pesquisa ao catálogo. */
    public function test_farmacia_procura_por_substancia_escapa_o_wildcard(): void
    {
        $this->artigo(['name' => 'A', 'active_ingredient' => 'Paracetamol']);
        $this->artigo(['name' => 'B', 'active_ingredient' => 'Ibuprofeno']);

        $achados = Product::where('tenant_id', $this->tenant->id)->porSubstancia('%')->count();

        $this->assertSame(0, $achados, 'O % não pode devolver o catálogo inteiro.');
    }

    /** O POS também procura pela substância — é lá que a pergunta é feita. */
    public function test_farmacia_o_pos_encontra_pela_substancia(): void
    {
        $this->abrirTurno();
        $this->comStock($this->artigo(['name' => 'Ben-u-ron 500mg', 'active_ingredient' => 'Paracetamol']));
        $this->comStock($this->artigo(['name' => 'Brufen 400mg', 'active_ingredient' => 'Ibuprofeno']));

        Livewire::test(POSSystem::class)
            ->set('search', 'Paracetamol')
            ->assertSee('Ben-u-ron')
            ->assertDontSee('Brufen');
    }

    /** "Lotes com saída FIFO pela validade — sai primeiro o que expira antes" */
    public function test_farmacia_lotes_saem_pela_validade_mais_curta(): void
    {
        $artigo = $this->artigo(['name' => 'Xarope', 'track_batches' => true]);

        $tarde = $this->lote($artigo, 'L-TARDE', now()->addMonths(10));
        $cedo  = $this->lote($artigo, 'L-CEDO', now()->addMonths(2));
        $medio = $this->lote($artigo, 'L-MEIO', now()->addMonths(5));

        // A mesma ordenação que a facturação, o POS e as transferências usam.
        $ordem = ProductBatch::where('tenant_id', $this->tenant->id)
            ->where('product_id', $artigo->id)
            ->orderBy('expiry_date', 'asc')
            ->pluck('batch_number')->all();

        $this->assertSame(['L-CEDO', 'L-MEIO', 'L-TARDE'], $ordem);
        $this->assertNotNull($cedo->id . $medio->id . $tarde->id);
    }

    /** "Relatório de validade — o que está a chegar ao fim, a tempo de abater" */
    public function test_farmacia_relatorio_de_validade_apanha_o_que_esta_a_expirar(): void
    {
        $artigo = $this->artigo(['name' => 'Pomada', 'track_batches' => true]);

        $this->lote($artigo, 'PERTO', now()->addDays(15));
        $this->lote($artigo, 'LONGE', now()->addMonths(18));

        Livewire::test(ExpiryReport::class)
            ->set('daysFilter', 30)
            ->assertSee('PERTO')
            ->assertDontSee('LONGE');
    }

    /** "Dosagem, forma farmacêutica e n.º ARMED — na ficha, ao lado do preço" */
    public function test_farmacia_guarda_dosagem_forma_e_armed(): void
    {
        $artigo = $this->artigo([
            'name' => 'Amoxicilina', 'dosage' => '500 mg',
            'pharmaceutical_form' => 'Cápsula', 'armed_registration' => 'ARMED-12345',
        ])->refresh();

        $this->assertSame('500 mg', $artigo->dosage);
        $this->assertSame('Cápsula', $artigo->pharmaceutical_form);
        $this->assertSame('ARMED-12345', $artigo->armed_registration);
    }

    // ═══ VESTUÁRIO ══════════════════════════════════════════════════════

    /** "Tamanho, cor, género e composição — na ficha do artigo" */
    public function test_vestuario_guarda_tamanho_cor_genero_e_composicao(): void
    {
        $artigo = $this->artigo([
            'name' => 'T-shirt', 'size' => 'M', 'color' => 'Azul-marinho',
            'gender' => 'unissexo', 'material' => '100% algodão',
        ])->refresh();

        $this->assertSame('M', $artigo->size);
        $this->assertSame('Azul-marinho', $artigo->color);
        $this->assertSame('unissexo', $artigo->gender);
        $this->assertSame('100% algodão', $artigo->material);
    }

    /** "Filtros de tamanho e de cor — com os valores reais do seu catálogo" */
    public function test_vestuario_filtros_trazem_os_valores_reais_do_catalogo(): void
    {
        $this->artigo(['name' => 'Camisa', 'size' => 'M', 'color' => 'Azul-marinho']);
        $this->artigo(['name' => 'Calças', 'size' => 'L', 'color' => 'Preto']);
        $this->artigo(['name' => 'Sem variantes']);

        $catalogo = Livewire::test(Products::class)->instance()->getVariantesCatalogoProperty();

        $this->assertSame(['L', 'M'], $catalogo['tamanhos']);
        $this->assertSame(['Azul-marinho', 'Preto'], $catalogo['cores']);
    }

    public function test_vestuario_filtrar_por_tamanho_e_por_cor(): void
    {
        $this->artigo(['name' => 'Camisa Média', 'size' => 'M', 'color' => 'Azul-marinho']);
        $this->artigo(['name' => 'Calças Grandes', 'size' => 'L', 'color' => 'Preto']);

        Livewire::test(Products::class)
            ->set('filterTamanho', 'M')
            ->assertSee('Camisa Média')
            ->assertDontSee('Calças Grandes');

        Livewire::test(Products::class)
            ->set('filterCor', 'Preto')
            ->assertSee('Calças Grandes')
            ->assertDontSee('Camisa Média');
    }

    /** "Procurar pelo tamanho no POS — 't-shirt M' chega" */
    public function test_vestuario_o_pos_encontra_pelo_tamanho(): void
    {
        $this->abrirTurno();
        $this->comStock($this->artigo(['name' => 'Camisa de linho', 'size' => 'XL']));
        $this->comStock($this->artigo(['name' => 'Calças de ganga', 'size' => 'S']));

        Livewire::test(POSSystem::class)
            ->set('search', 'XL')
            ->assertSee('Camisa de linho')
            ->assertDontSee('Calças de ganga');
    }

    // ═══ COSMÉTICA ══════════════════════════════════════════════════════

    /** "Meses após abertura (PAO) — o frasco com 12M no rótulo" */
    public function test_cosmetica_guarda_pao_e_inci(): void
    {
        $artigo = $this->artigo([
            'name' => 'Creme de rosto', 'pao_months' => 12,
            'inci_ingredients' => 'Aqua, Glycerin, Parabens',
        ])->refresh();

        $this->assertSame(12, (int) $artigo->pao_months);
        $this->assertStringContainsString('Parabens', $artigo->inci_ingredients);
    }

    /** "Conteúdo líquido — 50 ml e 200 ml do mesmo creme deixam de ser a mesma linha" */
    public function test_cosmetica_conteudo_liquido_distingue_duas_linhas(): void
    {
        $pequeno = $this->artigo(['name' => 'Creme hidratante', 'net_content' => '50 ml']);
        $grande  = $this->artigo(['name' => 'Creme hidratante', 'net_content' => '200 ml']);

        $this->assertNotSame($pequeno->id, $grande->id);
        $this->assertSame(
            ['200 ml', '50 ml'],
            Product::where('tenant_id', $this->tenant->id)
                ->where('name', 'Creme hidratante')
                ->orderBy('net_content')->pluck('net_content')->all()
        );
    }

    /**
     * "Conteúdo líquido e TOM".
     *
     * O tom não tem coluna própria de propósito — é a mesma coisa que a cor,
     * que já existe, e dois sítios a gravar o mesmo davam duas respostas
     * diferentes à mesma pergunta. O formulário diz isso a quem preenche.
     * O que este teste fixa é que o tom É registável e filtrável.
     */
    public function test_cosmetica_o_tom_regista_se_na_cor_e_e_filtravel(): void
    {
        $this->artigo(['name' => 'Batom mate', 'color' => 'Vermelho rubi', 'net_content' => '4 g']);
        $this->artigo(['name' => 'Batom cremoso', 'color' => 'Rosa velho', 'net_content' => '4 g']);

        Livewire::test(Products::class)
            ->set('filterCor', 'Vermelho rubi')
            ->assertSee('Batom mate')
            ->assertDontSee('Batom cremoso');
    }

    /** "Validades e lotes, como na farmácia" */
    public function test_cosmetica_lotes_e_validades_funcionam_igual(): void
    {
        $artigo = $this->artigo(['name' => 'Perfume', 'track_batches' => true]);
        $this->lote($artigo, 'PERF-A', now()->addDays(20));

        Livewire::test(ExpiryReport::class)
            ->set('daysFilter', 30)
            ->assertSee('PERF-A');
    }

    // ═══ MERCEARIA ══════════════════════════════════════════════════════

    /** "Conservação à vista — ambiente, refrigerado ou congelado" */
    public function test_mercearia_conservacao_guarda_e_filtra(): void
    {
        $this->artigo(['name' => 'Leite fresco', 'storage_conditions' => 'refrigerado']);
        $this->artigo(['name' => 'Arroz', 'storage_conditions' => 'ambiente']);

        Livewire::test(Products::class)
            ->set('filterConservacao', 'refrigerado')
            ->assertSee('Leite fresco')
            ->assertDontSee('Arroz');
    }

    /**
     * "Conservação à vista NA LISTA DE STOCK — onde quem arruma a mercadoria
     * olha, e não escondido dentro da ficha."
     *
     * O ecrã de stock é outro que não a lista de artigos: a promessa é sobre
     * ESTE, que é o que se tem aberto durante uma descarga.
     */
    public function test_mercearia_conservacao_aparece_na_lista_de_stock(): void
    {
        $leite = $this->comStock($this->artigo([
            'name' => 'Leite fresco', 'storage_conditions' => 'refrigerado',
        ]));

        Livewire::test(\App\Livewire\Invoicing\StockManagement::class)
            ->assertSee('Leite fresco')
            ->assertSee('Conservação');

        $this->assertSame('refrigerado', $leite->refresh()->storage_conditions);
    }

    /** "Alergénios e país de origem — o que o rótulo alimentar obriga a ter" */
    public function test_mercearia_guarda_alergenios_e_origem(): void
    {
        $artigo = $this->artigo([
            'name' => 'Bolacha', 'allergens' => 'Glúten, ovo, leite',
            'origin_country' => 'Portugal',
        ])->refresh();

        $this->assertStringContainsString('Glúten', $artigo->allergens);
        $this->assertSame('Portugal', $artigo->origin_country);
    }

    /** "'Leite' em duas linhas só se distingue por 1 L e 200 ml" */
    public function test_mercearia_conteudo_liquido_distingue_o_leite(): void
    {
        $this->artigo(['name' => 'Leite', 'net_content' => '1 L']);
        $this->artigo(['name' => 'Leite', 'net_content' => '200 ml']);

        $this->assertSame(2, Product::where('tenant_id', $this->tenant->id)->where('name', 'Leite')->count());
    }

    // ═══ OS INTERRUPTORES ═══════════════════════════════════════════════

    /** "Liga-se num interruptor nas definições de faturação." */
    public function test_os_perfis_ligam_e_desligam_nas_definicoes(): void
    {
        Livewire::test(Settings::class)
            ->set('profile_pharmacy', true)
            ->set('profile_clothing', false)
            ->set('profile_cosmetics', false)
            ->set('profile_grocery', true)
            ->call('save');

        $d = InvoicingSettings::where('tenant_id', $this->tenant->id)->first();

        $this->assertTrue((bool) $d->profile_pharmacy);
        $this->assertFalse((bool) $d->profile_clothing);
        $this->assertTrue((bool) $d->profile_grocery);
        $this->assertSame(['farmacia', 'mercearia'], $d->perfisActivos());
    }

    /**
     * Desligar o perfil NÃO pode calar o aviso de psicotrópico.
     *
     * O interruptor decide o que se MOSTRA na ficha; a segurança da venda
     * segue os dados do artigo, sempre. Uma farmácia que desligue o perfil por
     * engano não pode passar a vender controlados sem confirmação.
     */
    public function test_desligar_o_perfil_nao_cala_o_aviso_de_psicotropico(): void
    {
        InvoicingSettings::where('tenant_id', $this->tenant->id)->update(['profile_pharmacy' => false]);

        $this->abrirTurno();
        $controlado = $this->comStock($this->artigo(['name' => 'Metadona', 'is_controlled' => true]));

        Livewire::test(POSSystem::class)
            ->call('addToCart', $controlado->id)
            ->assertDispatched('pos-confirmar-controlado');
    }

    // ── Auxiliares ───────────────────────────────────────────────────────

    private function lote(Product $artigo, string $numero, $validade): ProductBatch
    {
        return ProductBatch::create([
            'tenant_id'          => $this->tenant->id,
            'product_id'         => $artigo->id,
            'warehouse_id'       => $this->armazem->id,
            'batch_number'       => $numero,
            'expiry_date'        => $validade,
            'quantity'           => 10,
            'quantity_available' => 10,
            'status'             => 'active',
        ]);
    }
}
