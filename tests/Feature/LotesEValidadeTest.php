<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\ProductBatches\ProductBatches;
use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BatchAllocationService;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Lotes e validades.
 *
 * A área não tinha testes de negócio nenhuns — só o de tradução. Cada asserção
 * aqui corresponde a um defeito concreto encontrado a ler o código, e a razão
 * de existir de cada uma está escrita no teste, porque daqui a seis meses o
 * "porquê" é o que se perde primeiro.
 */
class LotesEValidadeTest extends TenantTestCase
{
    private Product $artigo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes(
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

    // ==================== isolamento entre empresas ====================

    /**
     * Um lote de outra empresa não se edita.
     *
     * O ProductBatch é o único modelo deste módulo sem o BelongsToTenant — o
     * Warehouse tem-no, a PurchaseInvoice tem-no. Sem o trait não há global
     * scope, e o edit($id) do componente vai buscar o lote por id em bruto:
     * o id vem do cliente, portanto qualquer id serve.
     *
     * Pior do que ler: o save() escreve 'tenant_id' => activeTenantId(), ou
     * seja, editar o lote de outra empresa transferia-o para a nossa.
     */
    public function test_nao_se_edita_um_lote_de_outra_empresa(): void
    {
        $outra = Tenant::create([
            'name' => 'Empresa Vizinha', 'slug' => 'vizinha-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);

        $loteAlheio = ProductBatch::create([
            'tenant_id'          => $outra->id,
            'product_id'         => $this->artigo->id,
            'warehouse_id'       => $this->armazem->id,
            'batch_number'       => 'ALHEIO',
            'quantity'           => 50,
            'quantity_available' => 50,
            'status'             => 'active',
            'alert_days'         => 30,
        ]);

        // 404 e não 403 de propósito: um 403 confirmaria a quem sonda que
        // aquele id existe nalguma empresa. O lote simplesmente não existe
        // para quem está a olhar.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        try {
            Livewire::test(ProductBatches::class)->call('edit', $loteAlheio->id);
        } finally {
            $this->assertSame(
                $outra->id,
                ProductBatch::withoutGlobalScopes()->find($loteAlheio->id)?->tenant_id,
                'O lote mudou de empresa.'
            );
        }
    }

    /** O mesmo para apagar. */
    public function test_nao_se_apaga_um_lote_de_outra_empresa(): void
    {
        $outra = Tenant::create([
            'name' => 'Empresa Vizinha', 'slug' => 'vizinha-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);

        $loteAlheio = ProductBatch::create([
            'tenant_id'          => $outra->id,
            'product_id'         => $this->artigo->id,
            'warehouse_id'       => $this->armazem->id,
            'batch_number'       => 'ALHEIO',
            'quantity'           => 50,
            'quantity_available' => 50,
            'status'             => 'active',
            'alert_days'         => 30,
        ]);

        // O delete() apanha a excepção no seu try/catch e devolve uma
        // mensagem de erro — o que interessa é que o lote sobrevive.
        Livewire::test(ProductBatches::class)->call('delete', $loteAlheio->id);

        $this->assertNotNull(
            ProductBatch::withoutGlobalScopes()->find($loteAlheio->id),
            'O lote da outra empresa foi apagado.'
        );
    }

    // ==================== o ecrã de lotes ====================

    /**
     * Editar um lote com quantidade zero não rebenta a página.
     *
     * O save() calculava `$this->quantity / $batch->quantity` para ajustar a
     * disponibilidade em proporção. Com quantidade zero — que a validação
     * permite, porque é `min:0` — isso é uma divisão por zero. Em PHP 8 o
     * DivisionByZeroError é um Error e não uma Exception, portanto o
     * catch (\Exception) à volta não o apanha: a página dá 500.
     */
    public function test_editar_um_lote_de_quantidade_zero_nao_rebenta(): void
    {
        $lote = $this->lote(['quantity' => 0, 'quantity_available' => 0]);

        Livewire::test(ProductBatches::class)
            ->call('edit', $lote->id)
            ->set('quantity', 25)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(25, $lote->fresh()->quantity);
    }

    /**
     * Corrigir o total de um lote não pode inventar stock.
     *
     * A regra era proporcional: 100 no total, 40 disponíveis (60 vendidos),
     * corrigir o total para 90 dava 90 × 0,4 = 36 disponíveis. Mas venderam-se
     * 60 — o disponível certo é 30. A proporção inventava 6 unidades que não
     * existem, e o stock passava a mentir sem que nada acusasse.
     */
    public function test_corrigir_o_total_preserva_o_que_ja_saiu(): void
    {
        $lote = $this->lote(['quantity' => 100, 'quantity_available' => 40]);

        Livewire::test(ProductBatches::class)
            ->call('edit', $lote->id)
            ->set('quantity', 90)
            ->call('save');

        $this->assertEquals(
            30,
            $lote->fresh()->quantity_available,
            'Saíram 60 unidades; de 90 sobram 30.'
        );
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
    }

    /** E no dia seguinte já não. */
    public function test_um_lote_de_ontem_expirou(): void
    {
        $lote = $this->lote(['expiry_date' => now()->subDay()->toDateString()]);

        $this->assertTrue($lote->is_expired);
        $this->assertSame(1, ProductBatch::where('tenant_id', $this->tenant->id)->expired()->count());
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

        $html = $this->get('/invoicing/expiry-report')->assertOk()->getContent();

        // 10 × 250 = 2.500,00
        $this->assertStringContainsString('2.500,00', $html);
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

        $csv = $this->conteudoDe($this->relatorio()->exportReport());

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

        $relatorio = $this->relatorio();
        $relatorio->reportType = 'expiring_soon';
        $relatorio->daysFilter = 30;

        $csv = $this->conteudoDe($relatorio->exportReport());

        $this->assertStringContainsString('PERTO', $csv);
        $this->assertStringNotContainsString('LONGE', $csv);
    }

    /**
     * O componente em instância directa.
     *
     * O wrapper de teste do Livewire não devolve a resposta de um download —
     * e o que interessa provar aqui é o conteúdo do ficheiro, não o
     * transporte. É o mesmo caminho que o StockAdjustmentsReportTest usa.
     */
    private function relatorio(): \App\Livewire\Invoicing\Reports\ExpiryReport
    {
        $componente = new \App\Livewire\Invoicing\Reports\ExpiryReport();
        $componente->mount();

        return $componente;
    }

    /** O streamDownload só escreve quando alguém o consome. */
    private function conteudoDe($resposta): string
    {
        ob_start();
        $resposta->sendContent();

        return ob_get_clean();
    }
}
