<?php

namespace Tests\Feature;

use App\Models\Invoicing\ProductBatch;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\BatchAllocationService;
use Tests\TenantTestCase;

/**
 * Lotes e validades.
 *
 * A área não tinha testes de negócio nenhuns — só o de tradução. Cada asserção
 * aqui corresponde a um defeito concreto encontrado a ler o código, e a razão
 * de existir de cada uma está escrita no teste, porque daqui a seis meses o
 * "porquê" é o que se perde primeiro.
 *
 * O ecrã dos lotes é hoje o React `Lotes.tsx`, servido pela API
 * `/api/v1/invoicing/react/lotes` (regras no `GestorDeLotes`), e o relatório
 * de validades é o mapa `expiry-report` do catálogo dos relatórios. A alocação
 * e o modelo nunca passaram pelo Livewire — continuam a ser provados de frente.
 */
class LotesEValidadeTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/lotes';

    private Product $artigo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes(
            'invoicing.stock.view',
            'invoicing.product-batches.view',
            'invoicing.product-batches.create',
            'invoicing.product-batches.edit',
            'invoicing.product-batches.delete',
        );

        $this->artigo = Product::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Leite UHT',
            'code'       => 'LEITE-' . uniqid(),
            'price'      => 1000,
            'cost_price' => 600,
            'type'       => 'produto',
            'is_active'  => true,
            'tax_id'     => $this->imposto->id,
        ]);
    }

    private function lote(array $campos = []): ProductBatch
    {
        return ProductBatch::create(array_merge([
            'tenant_id'          => $this->tenant->id,
            'product_id'         => $this->artigo->id,
            'warehouse_id'       => $this->armazem->id,
            'batch_number'       => 'L' . uniqid(),
            'expiry_date'        => now()->addDays(90)->toDateString(),
            'quantity'           => 100,
            'quantity_available' => 100,
            'cost_price'         => 600,
            'status'             => 'active',
            'alert_days'         => 30,
        ], $campos));
    }

    /** O corpo que a API espera para criar ou corrigir um lote. */
    private function corpo(array $por = []): array
    {
        return array_merge([
            'product_id'   => $this->artigo->id,
            'warehouse_id' => $this->armazem->id,
            'batch_number' => 'L' . uniqid(),
            'expiry_date'  => now()->addDays(90)->toDateString(),
            'quantity'     => 100,
            'cost_price'   => 600,
            'alert_days'   => 30,
        ], $por);
    }

    /** Um lote de uma empresa vizinha, a que este utilizador não tem acesso. */
    private function loteAlheio(): ProductBatch
    {
        $outra = Tenant::create([
            'name' => 'Empresa Vizinha', 'slug' => 'vizinha-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);

        return ProductBatch::create([
            'tenant_id'          => $outra->id,
            'product_id'         => $this->artigo->id,
            'warehouse_id'       => $this->armazem->id,
            'batch_number'       => 'ALHEIO',
            'quantity'           => 50,
            'quantity_available' => 50,
            'status'             => 'active',
            'alert_days'         => 30,
        ]);
    }

    // ==================== isolamento entre empresas ====================

    /**
     * Um lote de outra empresa não se edita.
     *
     * O ProductBatch é o único modelo deste módulo sem o BelongsToTenant — o
     * Warehouse tem-no, a PurchaseInvoice tem-no. Sem o trait não há global
     * scope, e o id vem do cliente: qualquer id serve.
     *
     * Pior do que ler: gravar escrevia 'tenant_id' => activeTenantId(), ou
     * seja, editar o lote de outra empresa transferia-o para a nossa. Hoje a
     * API procura o lote JÁ filtrado pela empresa activa.
     */
    public function test_nao_se_edita_um_lote_de_outra_empresa(): void
    {
        $alheio = $this->loteAlheio();

        // 404 e não 403 de propósito: um 403 confirmaria a quem sonda que
        // aquele id existe nalguma empresa. O lote simplesmente não existe
        // para quem está a olhar.
        $this->putJson(self::RAIZ . '/' . $alheio->id, $this->corpo(['quantity' => 5]))->assertNotFound();

        $depois = ProductBatch::withoutGlobalScopes()->find($alheio->id);

        $this->assertSame($alheio->tenant_id, $depois?->tenant_id, 'O lote mudou de empresa.');
        $this->assertEquals(50, $depois->quantity, 'O lote da outra empresa foi alterado.');
    }

    /** O mesmo para apagar. */
    public function test_nao_se_apaga_um_lote_de_outra_empresa(): void
    {
        $alheio = $this->loteAlheio();

        $this->deleteJson(self::RAIZ . '/' . $alheio->id)->assertNotFound();

        $this->assertNotNull(
            ProductBatch::withoutGlobalScopes()->find($alheio->id),
            'O lote da outra empresa foi apagado.'
        );
    }

    // ==================== o ecrã de lotes ====================

    /**
     * Editar um lote com quantidade zero não rebenta a página.
     *
     * A regra antiga calculava `$this->quantity / $batch->quantity` para
     * ajustar a disponibilidade em proporção. Com quantidade zero — que a
     * validação permite, porque é `min:0` — isso é uma divisão por zero. Em
     * PHP 8 o DivisionByZeroError é um Error e não uma Exception, portanto o
     * catch (\Exception) à volta não o apanhava: a página dava 500.
     */
    public function test_editar_um_lote_de_quantidade_zero_nao_rebenta(): void
    {
        $lote = $this->lote(['quantity' => 0, 'quantity_available' => 0]);

        $this->putJson(self::RAIZ . '/' . $lote->id, $this->corpo(['quantity' => 25]))
            ->assertOk()
            ->assertJsonPath('data.quantity', 25);

        $this->assertEquals(25, $lote->fresh()->quantity);
        $this->assertEquals(25, $lote->fresh()->quantity_available, 'nada tinha saído de um lote a zero');
    }

    // ==================== a alocação FIFO ====================

    /**
     * Um lote expirado não bloqueia a venda dos lotes bons.
     *
     * O allocateFIFO abortava a alocação INTEIRA assim que encontrasse um lote
     * expirado do mesmo artigo. Um lote esquecido no armazém — e há sempre um —
     * impedia de vender o produto todo, por tempo indeterminado, com uma
     * mensagem que não dizia o que fazer. O correcto é saltar o expirado e
     * servir dos que prestam.
     */
    public function test_um_lote_expirado_nao_bloqueia_a_venda_dos_outros(): void
    {
        $this->lote([
            'batch_number' => 'VELHO',
            'expiry_date'  => now()->subDays(10)->toDateString(),
            'quantity'     => 20, 'quantity_available' => 20,
        ]);

        $bom = $this->lote([
            'batch_number' => 'BOM',
            'expiry_date'  => now()->addDays(60)->toDateString(),
            'quantity'     => 50, 'quantity_available' => 50,
        ]);

        $r = app(BatchAllocationService::class)
            ->allocateFIFO($this->artigo->id, $this->armazem->id, 30);

        $this->assertTrue($r['success'], 'A venda foi bloqueada: ' . ($r['message'] ?? ''));

        $ids = array_column($r['allocations'], 'batch_id');

        $this->assertSame([$bom->id], $ids, 'Só o lote bom podia ser alocado.');
        $this->assertEquals(30, $r['allocations'][0]['quantity']);
    }

    /**
     * A disponibilidade e a alocação têm de dizer a mesma coisa.
     *
     * O checkAvailability somava também os lotes expirados: respondia "há 50
     * disponíveis" e a allocateFIFO logo a seguir dizia "quantidade
     * insuficiente". Hoje não tem quem o chame, mas fica alinhado — uma
     * inconsistência adormecida acorda no dia em que alguém o ligar.
     */
    public function test_a_disponibilidade_nao_conta_os_lotes_expirados(): void
    {
        $this->lote([
            'expiry_date' => now()->subDays(3)->toDateString(),
            'quantity'    => 40, 'quantity_available' => 40,
        ]);

        $this->lote([
            'expiry_date' => now()->addDays(60)->toDateString(),
            'quantity'    => 10, 'quantity_available' => 10,
        ]);

        $r = app(BatchAllocationService::class)
            ->checkAvailability($this->artigo->id, $this->armazem->id, 30);

        $this->assertEquals(10, $r['total_available'], 'Os 40 expirados não se vendem.');
        $this->assertFalse($r['available']);
        $this->assertSame(1, $r['expired_count']);
    }

    /** Sem lotes bons que cheguem, a alocação falha — e diz porquê. */
    public function test_so_com_lotes_expirados_a_alocacao_falha(): void
    {
        $this->lote([
            'expiry_date' => now()->subDay()->toDateString(),
            'quantity'    => 20, 'quantity_available' => 20,
        ]);

        $r = app(BatchAllocationService::class)
            ->allocateFIFO($this->artigo->id, $this->armazem->id, 5);

        $this->assertFalse($r['success']);
        $this->assertNotEmpty($r['message']);
    }

    /** O FIFO serve primeiro o que expira mais cedo. */
    public function test_o_fifo_serve_primeiro_o_que_expira_mais_cedo(): void
    {
        $tarde = $this->lote([
            'expiry_date' => now()->addDays(90)->toDateString(),
            'quantity'    => 10, 'quantity_available' => 10,
        ]);

        $cedo = $this->lote([
            'expiry_date' => now()->addDays(10)->toDateString(),
            'quantity'    => 10, 'quantity_available' => 10,
        ]);

        $r = app(BatchAllocationService::class)
            ->allocateFIFO($this->artigo->id, $this->armazem->id, 15);

        $this->assertTrue($r['success']);
        $this->assertSame([$cedo->id, $tarde->id], array_column($r['allocations'], 'batch_id'));
        $this->assertEquals(10, $r['allocations'][0]['quantity']);
        $this->assertEquals(5, $r['allocations'][1]['quantity']);
    }

    /**
     * Dois pedidos ao mesmo tempo não podem tirar do mesmo lote duas vezes.
     *
     * O decreaseQuantity lia o disponível para memória, subtraía e gravava a
     * linha inteira. Dois processos que leiam 100 e tirem 10 cada gravam ambos
     * 90 — a segunda escrita apaga a primeira e as unidades desaparecem da
     * contabilidade sem deixar rasto. É a perda de actualização clássica, e
     * num sistema de POS com vários caixas acontece.
     */
    public function test_duas_saidas_do_mesmo_lote_nao_se_perdem(): void
    {
        $lote = $this->lote(['quantity' => 100, 'quantity_available' => 100]);

        // Duas instâncias do MESMO lote, ambas com o valor lido antes de
        // qualquer escrita — que é exactamente o que dois pedidos em paralelo
        // têm em mãos.
        $primeira = ProductBatch::find($lote->id);
        $segunda  = ProductBatch::find($lote->id);

        $primeira->decreaseQuantity(10);
        $segunda->decreaseQuantity(10);

        $this->assertEquals(
            80,
            $lote->fresh()->quantity_available,
            'Saíram 20 unidades no total.'
        );
    }

    // ==================== a validade em si ====================

    /**
     * Um lote que expira hoje ainda serve.
     *
     * O acessor is_expired dizia que sim a partir da meia-noite; o scope
     * expired() da lista dizia que não. O mesmo lote aparecia como "Expirado"
     * no crachá e ficava de fora da lista de expirados — e, pior, o
     * allocateFIFO recusava-o. Um produto com validade hoje é vendável hoje.
     */
    public function test_um_lote_que_expira_hoje_ainda_nao_expirou(): void
    {
        $lote = $this->lote(['expiry_date' => now()->toDateString()]);

        $this->assertFalse($lote->is_expired, 'Um lote com validade hoje ainda serve hoje.');

        $this->assertSame(
            0,
            ProductBatch::where('tenant_id', $this->tenant->id)->expired()->count(),
            'O crachá e a lista têm de concordar.'
        );

        // E a lista do ecrã, que filtra pelo mesmo scope, também não o mostra.
        $this->assertSame(0, $this->getJson(self::RAIZ . '?estado=expired')->assertOk()->json('meta.total'));
        $this->assertSame(0, $this->getJson(self::RAIZ)->assertOk()->json('resumo.expirados'));
    }

    /** E no dia seguinte já não. */
    public function test_um_lote_de_ontem_expirou(): void
    {
        $lote = $this->lote(['expiry_date' => now()->subDay()->toDateString()]);

        $this->assertTrue($lote->is_expired);
        $this->assertSame(1, ProductBatch::where('tenant_id', $this->tenant->id)->expired()->count());
        $this->assertSame(1, $this->getJson(self::RAIZ . '?estado=expired')->assertOk()->json('meta.total'));
    }

    /**
     * Os dias até à validade contam-se em dias inteiros.
     *
     * O diffInDays do Carbon 3 devolve um float com as horas dentro: um lote
     * que expira daqui a 30 dias dava 29,39 às duas da tarde. O ecrã mostrava
     * "29,395833333333 dias" e a comparação com alert_days trocava a fronteira
     * conforme a hora a que se abrisse a página.
     */
    public function test_os_dias_ate_a_validade_sao_inteiros(): void
    {
        $lote = $this->lote(['expiry_date' => now()->addDays(30)->toDateString()]);

        $this->assertIsInt($lote->days_until_expiry);
        $this->assertSame(30, $lote->days_until_expiry);

        // A API entrega o mesmo inteiro ao ecrã, e não um número com horas
        // dentro.
        $linha = collect($this->getJson(self::RAIZ)->assertOk()->json('data'))->firstWhere('id', $lote->id);
        $this->assertSame(30, $linha['dias']);
    }

    /** Já expirado conta em negativo, para o ecrã poder dizer há quantos dias. */
    public function test_um_lote_expirado_conta_dias_em_negativo(): void
    {
        $lote = $this->lote(['expiry_date' => now()->subDays(5)->toDateString()]);

        $this->assertSame(-5, $lote->days_until_expiry);
    }

    /** Sem data de validade não há alarme nenhum a dar. */
    public function test_um_lote_sem_validade_nao_expira_nem_alerta(): void
    {
        $lote = $this->lote(['expiry_date' => null]);

        $this->assertNull($lote->days_until_expiry);
        $this->assertFalse($lote->is_expired);
        $this->assertFalse($lote->is_expiring_soon);
    }

    // ==================== o relatório ====================

    /** O valor em risco soma quantidade × custo, e não uma coluna só. */
    public function test_o_relatorio_soma_o_valor_em_risco(): void
    {
        $this->lote([
            'expiry_date' => now()->addDays(10)->toDateString(),
            'quantity'    => 10, 'quantity_available' => 10, 'cost_price' => 250,
        ]);

        $r = $this->getJson('/api/v1/invoicing/react/relatorios/expiry-report')->assertOk();

        // 10 × 250 = 2.500,00
        $this->assertEqualsWithDelta(2500, $r->json('dados.stats.value_at_risk'), 0.01);
        $this->assertEqualsWithDelta(2500, collect($r->json('dados.batches'))->sum('valor'), 0.01);
    }

    /**
     * O botão de exportar exporta.
     *
     * Dizia "Exportação em desenvolvimento..." e não fazia nada. É a lista que
     * se leva para o armazém a decidir o que abater — sem ela, o relatório só
     * serve para olhar.
     */
    public function test_o_relatorio_exporta_o_que_esta_no_ecra(): void
    {
        $this->lote([
            'batch_number' => 'L-EXPORTA',
            'expiry_date'  => now()->addDays(10)->toDateString(),
            'quantity'     => 10, 'quantity_available' => 10, 'cost_price' => 250,
        ]);

        $csv = $this->csvDoRelatorio();

        $this->assertStringContainsString('L-EXPORTA', $csv);
        $this->assertStringContainsString('Leite UHT', $csv);

        // 10 × 250, sem separador de milhares: no ficheiro o número é para a
        // folha de cálculo somar, não para uma pessoa ler. O ecrã é que o
        // apresenta com o ponto.
        $this->assertStringContainsString('2500,00', $csv);
    }

    /**
     * A exportação respeita os filtros do ecrã.
     *
     * Se levasse a tabela toda, o ficheiro que a pessoa abre não bate certo
     * com o que ela viu — e é nessa diferença que se toma a decisão errada.
     */
    public function test_a_exportacao_respeita_os_filtros(): void
    {
        $this->lote([
            'batch_number' => 'PERTO',
            'expiry_date'  => now()->addDays(5)->toDateString(),
        ]);

        $this->lote([
            'batch_number' => 'LONGE',
            'expiry_date'  => now()->addDays(300)->toDateString(),
        ]);

        $csv = $this->csvDoRelatorio(['reportType' => 'expiring_soon', 'daysFilter' => 30]);

        $this->assertStringContainsString('PERTO', $csv);
        $this->assertStringNotContainsString('LONGE', $csv);
    }

    /**
     * O CSV do mapa de validades, com os filtros do ecrã na query.
     *
     * Descarrega-se pela rota de página (com a sessão) e não pela API: um
     * ficheiro não viaja em JSON.
     */
    private function csvDoRelatorio(array $filtros = []): string
    {
        $resposta = $this->get('/invoicing/expiry-report/csv' . ($filtros ? '?' . http_build_query($filtros) : ''));

        $resposta->assertOk();
        $this->assertStringContainsString('text/csv', (string) $resposta->headers->get('content-type'));

        return $resposta->streamedContent();
    }
}
