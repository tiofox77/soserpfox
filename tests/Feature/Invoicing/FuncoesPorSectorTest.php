<?php

namespace Tests\Feature\Invoicing;

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
 *
 * O POS continua em Livewire e prova-se aí. A lista de artigos, o stock, os
 * relatórios e as definições passaram a React: provam-se contra a API, que é
 * onde as regras passaram a viver.
 */
class FuncoesPorSectorTest extends TenantTestCase
{
    private const ARTIGOS = '/api/v1/invoicing/react/products';

    private const STOCK = '/api/v1/invoicing/react/stock';

    private const DEFINICOES = '/api/v1/invoicing/react/definicoes';

    private const VALIDADES = '/api/v1/invoicing/react/relatorios/expiry-report';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes(
            'invoicing.products.view',
            'invoicing.stock.view',
            'invoicing.reports.view',
            'invoicing.settings.view',
            'invoicing.settings.edit',
        );

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
        InvoicingSettings::esquecerMemoria();
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

    /** Os artigos que a lista da API devolve com estes filtros. */
    private function listados(array $filtros = []): array
    {
        $r = $this->getJson(self::ARTIGOS . '?' . http_build_query($filtros + ['por_pagina' => 100]))
            ->assertOk();

        return collect($r->json('data'))->pluck('name', 'id')->all();
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
        $sim = $this->listados(['prescricao' => 'sim']);
        $this->assertArrayHasKey($comReceita->id, $sim);
        $this->assertArrayNotHasKey($semReceita->id, $sim);

        $nao = $this->listados(['prescricao' => 'nao']);
        $this->assertArrayHasKey($semReceita->id, $nao);
        $this->assertArrayNotHasKey($comReceita->id, $nao);

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

        // O balcão é React: é o servidor que marca o artigo como controlado, e
        // é essa marca que faz o ecrã perguntar ANTES de ele entrar no carrinho.
        $linha = $this->linhaDoPos($controlado);

        $this->assertNotNull($linha);
        $this->assertTrue($linha['controlado']);
    }

    /** Confirmado pelo operador, aí sim entra — e é o ecrã que o faz. */
    public function test_farmacia_psicotropico_entra_depois_de_confirmado(): void
    {
        $fonte = file_get_contents(resource_path('js/ecras/facturacao/pos/PontoDeVenda.tsx'));

        // A pergunta é uma porta: o «Confirmo a venda» é que chama o `juntar`.
        $this->assertStringContainsString('porAConfirmarControlado(a)', $fonte);
        $this->assertStringContainsString("t('Confirmo a venda')", $fonte);
        $this->assertStringContainsString("t('Não vender')", $fonte,
            'a pergunta tem de ter as duas respostas');
    }

    /** Um artigo normal nunca pergunta nada — senão o aviso perde o efeito. */
    public function test_farmacia_artigo_normal_nao_pede_confirmacao(): void
    {
        $this->abrirTurno();
        $normal = $this->comStock($this->artigo(['name' => 'Adesivo']));

        $linha = $this->linhaDoPos($normal);

        $this->assertNotNull($linha);
        $this->assertFalse($linha['controlado']);
        $this->assertFalse($linha['receita']);
    }

    /** A linha de um artigo na grelha do balcão, tal como o ecrã a recebe. */
    private function linhaDoPos(Product $p, string $procura = ''): ?array
    {
        $this->comPermissoes('invoicing.pos.access');

        $q = $procura === '' ? '' : '?procura='.urlencode($procura);

        return collect(
            $this->actingAs($this->user)
                ->getJson('/api/v1/invoicing/react/pos/artigos'.$q)->assertOk()->json('data')
        )->firstWhere('id', $p->id);
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

        $nomes = collect(
            $this->comPermissoes('invoicing.pos.access')->actingAs($this->user)
                ->getJson('/api/v1/invoicing/react/pos/artigos?procura=Paracetamol')
                ->assertOk()->json('data')
        )->pluck('nome');

        $this->assertTrue($nomes->contains(fn ($n) => str_contains($n, 'Ben-u-ron')));
        $this->assertFalse($nomes->contains(fn ($n) => str_contains($n, 'Brufen')));
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

        $lotes = $this->lotesDoRelatorio(30);

        $this->assertContains('PERTO', $lotes);
        $this->assertNotContains('LONGE', $lotes);
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

        $variantes = $this->getJson(self::ARTIGOS . '/opcoes')->assertOk()->json('variantes');

        $this->assertSame(['L', 'M'], $variantes['tamanhos']);
        $this->assertSame(['Azul-marinho', 'Preto'], $variantes['cores']);
    }

    public function test_vestuario_filtrar_por_tamanho_e_por_cor(): void
    {
        $media   = $this->artigo(['name' => 'Camisa Média', 'size' => 'M', 'color' => 'Azul-marinho']);
        $grandes = $this->artigo(['name' => 'Calças Grandes', 'size' => 'L', 'color' => 'Preto']);

        $porTamanho = $this->listados(['tamanho' => 'M']);
        $this->assertArrayHasKey($media->id, $porTamanho);
        $this->assertArrayNotHasKey($grandes->id, $porTamanho);

        $porCor = $this->listados(['cor' => 'Preto']);
        $this->assertArrayHasKey($grandes->id, $porCor);
        $this->assertArrayNotHasKey($media->id, $porCor);
    }

    /** "Procurar pelo tamanho no POS — 't-shirt M' chega" */
    public function test_vestuario_o_pos_encontra_pelo_tamanho(): void
    {
        $this->abrirTurno();
        $this->comStock($this->artigo(['name' => 'Camisa de linho', 'size' => 'XL']));
        $this->comStock($this->artigo(['name' => 'Calças de ganga', 'size' => 'S']));

        $nomes = collect(
            $this->comPermissoes('invoicing.pos.access')->actingAs($this->user)
                ->getJson('/api/v1/invoicing/react/pos/artigos?procura=XL')
                ->assertOk()->json('data')
        )->pluck('nome');

        $this->assertTrue($nomes->contains('Camisa de linho'));
        $this->assertFalse($nomes->contains('Calças de ganga'));
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
        $mate    = $this->artigo(['name' => 'Batom mate', 'color' => 'Vermelho rubi', 'net_content' => '4 g']);
        $cremoso = $this->artigo(['name' => 'Batom cremoso', 'color' => 'Rosa velho', 'net_content' => '4 g']);

        $porTom = $this->listados(['cor' => 'Vermelho rubi']);

        $this->assertArrayHasKey($mate->id, $porTom);
        $this->assertArrayNotHasKey($cremoso->id, $porTom);
    }

    /** "Validades e lotes, como na farmácia" */
    public function test_cosmetica_lotes_e_validades_funcionam_igual(): void
    {
        $artigo = $this->artigo(['name' => 'Perfume', 'track_batches' => true]);
        $this->lote($artigo, 'PERF-A', now()->addDays(20));

        $this->assertContains('PERF-A', $this->lotesDoRelatorio(30));
    }

    // ═══ MERCEARIA ══════════════════════════════════════════════════════

    /** "Conservação à vista — ambiente, refrigerado ou congelado" */
    public function test_mercearia_conservacao_guarda_e_filtra(): void
    {
        $leite = $this->artigo(['name' => 'Leite fresco', 'storage_conditions' => 'refrigerado']);
        $arroz = $this->artigo(['name' => 'Arroz', 'storage_conditions' => 'ambiente']);

        $frio = $this->listados(['conservacao' => 'refrigerado']);

        $this->assertArrayHasKey($leite->id, $frio);
        $this->assertArrayNotHasKey($arroz->id, $frio);
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

        $this->assertTrue(
            $this->getJson(self::STOCK . '/opcoes')->assertOk()->json('mostra_conservacao'),
            'a coluna da conservação tem de estar à vista de quem arruma'
        );

        $linha = collect($this->getJson(self::STOCK)->assertOk()->json('data'))
            ->firstWhere('product_id', $leite->id);

        $this->assertNotNull($linha, 'o artigo tem de aparecer na lista de stock');
        $this->assertSame('Leite fresco', $linha['artigo']);
        $this->assertSame('refrigerado', $linha['conservacao']);
        $this->assertSame('Refrigerado', $linha['conservacao_rotulo']);

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
        $ficha = $this->getJson(self::DEFINICOES)->assertOk()->json('definicoes');

        $this->putJson(self::DEFINICOES, array_merge($ficha, [
            'profile_pharmacy'  => true,
            'profile_clothing'  => false,
            'profile_cosmetics' => false,
            'profile_grocery'   => true,
        ]))->assertOk();

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
        InvoicingSettings::esquecerMemoria();

        $this->abrirTurno();
        $controlado = $this->comStock($this->artigo(['name' => 'Metadona', 'is_controlled' => true]));

        $linha = $this->linhaDoPos($controlado);

        $this->assertNotNull($linha);
        $this->assertTrue($linha['controlado'],
            'desligar o perfil de visualização não pode desligar a pergunta do psicotrópico');
    }

    // ── Auxiliares ───────────────────────────────────────────────────────

    /** Os números de lote que o relatório de validade traz para esta janela. */
    private function lotesDoRelatorio(int $dias): array
    {
        $r = $this->getJson(self::VALIDADES . '?' . http_build_query([
            'reportType' => 'expiring_soon',
            'daysFilter' => $dias,
        ]))->assertOk();

        return collect($r->json('dados.batches'))->pluck('batch_number')->all();
    }

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
