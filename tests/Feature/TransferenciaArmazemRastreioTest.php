<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Rastreio das transferências entre armazéns.
 *
 * O ecrã movia stock e deixava um rasto que não servia para nada: a referência
 * do lote era um `crc32(uniqid())` — um inteiro opaco que ninguém diz ao
 * telefone nem escreve num papel —, não havia documento nenhum para imprimir, e
 * os saldos ficavam por gravar de um dos lados.
 *
 * O pior era esse último: `from_warehouse_id` e `to_warehouse_id` nunca eram
 * preenchidos, e sem eles o carimbo de saldos não distingue as duas pernas da
 * transferência. Derivava o saldo anterior do DESTINO somando em vez de
 * subtrair — e escrevia no histórico um "antes" que nunca existiu.
 *
 * O ecrã é hoje o React `TransferenciasEntreArmazens.tsx`; a regra vive no
 * serviço `TransferenciaDeStock` e entra pela API
 * `/api/v1/invoicing/react/transferencias`. É a essa porta que estes ensaios
 * batem — o rasto tem de continuar igual, venha de onde vier.
 */
class TransferenciaArmazemRastreioTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/transferencias';

    protected Warehouse $destino;
    protected Product $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->destino = Warehouse::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Armazém Secundário',
            'code'       => 'SEC',
            'is_active'  => true,
            'is_default' => false,
        ]);

        $this->produto = Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'AMIDOL Paracetamol 500mg',
            'sku'            => 'AMI-500',
            'code'           => 'AMI-500',
            'price'          => 300,
            'cost'           => 150,
            'type'           => 'produto',
            'manage_stock'   => true,
            'stock_quantity' => 0,
        ]);

        // O documento do lote e o do ajuste passaram a pedir `stock.view`: são
        // páginas com o id no URL, e antes abriam só com `auth`.
        $this->comPermissoes('invoicing.warehouse-transfer.create', 'invoicing.stock.edit', 'invoicing.stock.view')
             ->comModulo('invoicing');
    }

    private function comStock(Warehouse $armazem, float $q): Stock
    {
        return Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $armazem->id,
            'product_id'   => $this->produto->id,
            'quantity'     => $q,
        ]);
    }

    /** Transfere $q unidades do armazém principal para o secundário. */
    private function transferir(float $q, string $nota = 'Reposição de loja')
    {
        return $this->postJson(self::RAIZ . '/entre-armazens', [
            'de'    => $this->armazem->id,
            'para'  => $this->destino->id,
            'notas' => $nota,
            'itens' => [[
                'product_id'   => $this->produto->id,
                'product_name' => $this->produto->name,
                'quantity'     => $q,
            ]],
        ]);
    }

    /** O ajuste em lote, num armazém só e com um sentido. */
    private function ajustar(string $tipo, float $q, string $motivo)
    {
        return $this->postJson(self::RAIZ . '/ajuste', [
            'armazem' => $this->armazem->id,
            'tipo'    => $tipo,
            'motivo'  => $motivo,
            'itens'   => [[
                'product_id'   => $this->produto->id,
                'product_name' => $this->produto->name,
                'quantity'     => $q,
            ]],
        ]);
    }

    public function test_a_transferencia_recebe_uma_referencia_legivel(): void
    {
        $this->comStock($this->armazem, 20);

        $resposta = $this->transferir(5)->assertCreated();

        $referencia = StockMovement::where('product_id', $this->produto->id)->value('batch_reference');

        $this->assertSame($resposta->json('referencia'), $referencia, 'a API devolve a referência que gravou');

        $this->assertMatchesRegularExpression(
            '#^MOV/\d{4}/\d{6}$#',
            (string) $referencia,
            'a referência do lote tem de ser MOV/AAAA/NNNNNN, como no ecrã de movimentação de stock'
        );
    }

    public function test_as_duas_pernas_partilham_a_mesma_referencia(): void
    {
        $this->comStock($this->armazem, 20);

        $this->transferir(5)->assertCreated();

        $referencias = StockMovement::where('product_id', $this->produto->id)
            ->pluck('batch_reference')
            ->unique();

        $this->assertCount(2, StockMovement::where('product_id', $this->produto->id)->get(), 'saída e entrada');
        $this->assertCount(1, $referencias, 'as duas pernas são o mesmo documento');
    }

    public function test_os_quatro_saldos_ficam_gravados(): void
    {
        // 20 na origem, 3 no destino, transferem-se 5.
        $this->comStock($this->armazem, 20);
        $this->comStock($this->destino, 3);

        $this->transferir(5)->assertCreated();

        $saida = StockMovement::where('warehouse_id', $this->armazem->id)
            ->where('product_id', $this->produto->id)->first();

        $entrada = StockMovement::where('warehouse_id', $this->destino->id)
            ->where('product_id', $this->produto->id)->first();

        $this->assertSame(20.0, (float) $saida->balance_before, 'origem antes');
        $this->assertSame(15.0, (float) $saida->balance_after, 'origem depois');

        $this->assertSame(3.0, (float) $entrada->balance_before, 'destino antes');
        $this->assertSame(8.0, (float) $entrada->balance_after, 'destino depois');
    }

    public function test_o_saldo_anterior_do_destino_nao_e_inflacionado(): void
    {
        // A regressão exacta: sem from/to_warehouse_id, carimbarSaldos() não
        // sabia qual das pernas estava a olhar e SOMAVA a quantidade ao saldo
        // do destino em vez de a subtrair. Com 3 no destino e 5 transferidos
        // gravava "antes = 13" — um saldo que nunca existiu — em vez de 3.
        $this->comStock($this->armazem, 20);
        $this->comStock($this->destino, 3);

        $this->transferir(5)->assertCreated();

        $entrada = StockMovement::where('warehouse_id', $this->destino->id)
            ->where('product_id', $this->produto->id)->first();

        $this->assertNotSame(13.0, (float) $entrada->balance_before, 'o saldo somado em vez de subtraído');
        $this->assertSame(
            (float) $entrada->balance_after - 5.0,
            (float) $entrada->balance_before,
            'antes + transferido = depois'
        );
    }

    public function test_as_pernas_dizem_de_onde_para_onde(): void
    {
        $this->comStock($this->armazem, 20);

        $this->transferir(5)->assertCreated();

        foreach (StockMovement::where('product_id', $this->produto->id)->get() as $m) {
            $this->assertSame($this->armazem->id, (int) $m->from_warehouse_id);
            $this->assertSame($this->destino->id, (int) $m->to_warehouse_id);
        }
    }

    public function test_fica_registado_quem_transferiu(): void
    {
        $this->comStock($this->armazem, 20);

        $this->transferir(5)->assertCreated();

        foreach (StockMovement::where('product_id', $this->produto->id)->get() as $m) {
            $this->assertSame($this->user->id, (int) $m->user_id);
        }
    }

    public function test_o_stock_dos_dois_armazens_bate_com_os_saldos_gravados(): void
    {
        $this->comStock($this->armazem, 20);
        $this->comStock($this->destino, 3);

        $this->transferir(5)->assertCreated();

        $origem  = Stock::where('warehouse_id', $this->armazem->id)->where('product_id', $this->produto->id)->first();
        $destino = Stock::where('warehouse_id', $this->destino->id)->where('product_id', $this->produto->id)->first();

        $this->assertSame(15.0, (float) $origem->quantity);
        $this->assertSame(8.0, (float) $destino->quantity);
    }

    public function test_o_documento_do_lote_abre(): void
    {
        $this->comStock($this->armazem, 20);
        $this->comStock($this->destino, 3);

        $resposta = $this->transferir(5)->assertCreated();
        $referencia = $resposta->json('referencia');

        // A API entrega o caminho do PDF já feito — é o que o ecrã põe no
        // botão do comprovativo.
        $this->assertSame('/invoicing/stock/movimentacao/' . $referencia . '/pdf', $resposta->json('pdf'));

        $papel = $this->get(route('invoicing.stock.batch-preview', ['reference' => $referencia]));

        $papel->assertOk();
        $papel->assertSee('Transferência de Stock');
        $papel->assertSee($referencia);
        $papel->assertSee('Armazém Secundário');
        // Os saldos dos dois lados no papel: 20 → 15 na origem, 3 → 8 no destino.
        $papel->assertSee('Armazém de origem');
        $papel->assertSee('Armazém de destino');
    }

    /**
     * QUEM VÊ A LISTA ABRE O PAPEL.
     *
     * A rota do documento pedia `stock.view` e o controlador aceitava quem só
     * transfere: a lista mostrava os ícones de PDF e o clique levava 403.
     */
    public function test_quem_so_ve_as_transferencias_abre_o_papel_do_lote(): void
    {
        $this->comStock($this->armazem, 20);
        $referencia = $this->transferir(5)->assertCreated()->json('referencia');

        $this->user->syncPermissions([]);
        $this->comPermissoes('invoicing.warehouse-transfer.view');

        $this->get(route('invoicing.stock.batch-preview', ['reference' => $referencia]))->assertOk()->assertSee($referencia);
        $this->get(route('invoicing.stock.batch-pdf', ['reference' => $referencia]))->assertOk();

        // E a lista dá as duas moradas.
        $linha = collect($this->getJson('/api/v1/invoicing/react/transferencias/historico')->assertOk()->json('data'))->firstWhere('referencia', $referencia);
        $this->assertStringEndsWith($referencia . '/pdf', $linha['pdf']);
        $this->assertStringEndsWith($referencia . '/preview', $linha['preview']);

        // Sem nenhuma das permissões, não abre.
        $this->user->syncPermissions([]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->get(route('invoicing.stock.batch-preview', ['reference' => $referencia]))->assertForbidden();
    }

    public function test_uma_transferencia_nao_usa_o_documento_de_entradas_e_saidas(): void
    {
        // O documento de entradas/saídas tem UM armazém e uma linha por
        // movimento. Numa transferência isso daria o mesmo artigo duas vezes,
        // com sinais opostos e sem dizer de onde para onde foi.
        $this->comStock($this->armazem, 20);

        $referencia = $this->transferir(5)->assertCreated()->json('referencia');

        $this->get(route('invoicing.stock.batch-preview', ['reference' => $referencia]))
            ->assertDontSee('Movimentação de Stock');
    }

    public function test_o_ajuste_grava_o_saldo_anterior(): void
    {
        // Num ajuste a quantidade não diz de quanto para quanto: sem o saldo
        // anterior, "+6" no histórico é ilegível.
        $this->comStock($this->armazem, 12);

        $this->ajustar('in', 6, 'Contagem física')->assertCreated();

        $m = StockMovement::where('product_id', $this->produto->id)->first();

        $this->assertSame(12.0, (float) $m->balance_before);
        $this->assertSame(18.0, (float) $m->balance_after);
        $this->assertMatchesRegularExpression('#^MOV/\d{4}/\d{6}$#', (string) $m->batch_reference);
    }

    public function test_o_documento_do_ajuste_soma_as_linhas(): void
    {
        // As linhas de ajuste gravam a quantidade COM SINAL e ficam todas em
        // `type = adjustment`. O documento classificava por `type`, e por isso
        // elas apareciam na tabela sem entrar em nenhum dos totais.
        $this->comStock($this->armazem, 12);

        $referencia = $this->ajustar('out', 4, 'Quebra')->assertCreated()->json('referencia');

        $this->get(route('invoicing.stock.batch-preview', ['reference' => $referencia]))
            ->assertOk()
            ->assertSee('Total saídas (1)');
    }

    public function test_o_detalhe_traz_as_duas_pernas_com_os_saldos(): void
    {
        $this->comStock($this->armazem, 20);
        $this->comStock($this->destino, 3);

        $referencia = $this->transferir(5)->assertCreated()->json('referencia');

        $detalhe = $this->getJson(self::RAIZ . '/detalhes?referencia=' . urlencode($referencia))
            ->assertOk()->json('data');

        $this->assertCount(2, $detalhe, 'saída e entrada');

        $saldos = array_map(fn ($d) => [(float) $d['antes'], (float) $d['depois']], $detalhe);

        $this->assertContains([20.0, 15.0], $saldos, 'a perna da origem');
        $this->assertContains([3.0, 8.0], $saldos, 'a perna do destino');
    }

    public function test_o_detalhe_de_um_lote_nao_apanha_o_de_outro(): void
    {
        // O `reference_id` é um crc32: dois lotes diferentes da mesma empresa
        // podem cair no mesmo inteiro e o detalhe misturaria as duas
        // movimentações. Procurar pela referência MOV/ fecha essa porta.
        $this->comStock($this->armazem, 40);

        $primeira = $this->transferir(5)->assertCreated()->json('referencia');
        $this->transferir(7)->assertCreated();

        $detalhe = $this->getJson(self::RAIZ . '/detalhes?referencia=' . urlencode($primeira))
            ->assertOk()->json('data');

        $this->assertCount(2, $detalhe);

        foreach ($detalhe as $linha) {
            $this->assertSame(5.0, abs((float) $linha['quantidade']), 'só as linhas do primeiro lote');
        }
    }

    /** Cria $n artigos com stock no armazém principal. */
    private function catalogo(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $p = Product::create([
                'tenant_id'      => $this->tenant->id,
                'name'           => sprintf('ARTIGO %03d', $i),
                'sku'            => "ART-{$i}",
                'code'           => "ART-{$i}",
                'barcode'        => '789' . str_pad((string) $i, 10, '0', STR_PAD_LEFT),
                'price'          => 100,
                'cost'           => 50,
                'type'           => 'produto',
                'manage_stock'   => true,
                'stock_quantity' => 0,
            ]);

            Stock::create([
                'tenant_id'    => $this->tenant->id,
                'warehouse_id' => $this->armazem->id,
                'product_id'   => $p->id,
                'quantity'     => 10,
            ]);
        }
    }

    /** A grelha de artigos do carrinho, com o que há no armazém de origem. */
    private function artigos(array $filtros = []): array
    {
        $filtros += ['armazem' => $this->armazem->id, 'so_com_stock' => 1];

        return $this->getJson(self::RAIZ . '/artigos?' . http_build_query($filtros))->assertOk()->json('data');
    }

    public function test_a_grelha_de_artigos_nao_custa_uma_consulta_por_artigo(): void
    {
        // A razão de o ecrã ser inutilizável numa farmácia: carregava o
        // catálogo INTEIRO em cada render e a vista fazia mais uma consulta de
        // stock POR ARTIGO. Com 5.729 artigos eram perto de 5.730 consultas a
        // cada tecla premida no campo de pesquisa.
        $this->catalogo(60);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->artigos(['procura' => 'ARTIGO 01']);

        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(
            40,
            $consultas,
            "60 artigos deram {$consultas} consultas — o stock voltou a ser lido artigo a artigo"
        );
    }

    public function test_a_grelha_vem_com_o_stock_do_armazem_na_mesma_consulta(): void
    {
        $this->catalogo(3);

        $artigos = $this->artigos();

        $this->assertNotEmpty($artigos);

        foreach ($artigos as $a) {
            $this->assertSame(10.0, (float) $a['disponivel'], 'o stock tem de vir na consulta dos artigos');
        }
    }

    public function test_a_grelha_e_limitada(): void
    {
        // Sem limite eram 5.729 cartões no DOM.
        $this->catalogo(60);

        $this->assertLessThanOrEqual(50, count($this->artigos()));
    }

    public function test_a_pesquisa_encontra_pelo_codigo_de_barras(): void
    {
        // Numa farmácia lê-se o artigo pelo leitor; a pesquisa antiga só olhava
        // para o nome e o código.
        $this->catalogo(3);

        $artigos = $this->artigos(['procura' => '7890000000001']);

        $this->assertCount(1, $artigos);
        $this->assertSame('ARTIGO 001', $artigos[0]['name']);
    }

    public function test_sem_pesquisa_a_transferencia_so_mostra_o_que_ha_na_origem(): void
    {
        // Não se transfere o que não existe na origem. Sem isto, os cinquenta
        // primeiros por ordem alfabética eram quase todos artigos a zero.
        $this->catalogo(2);

        // Um artigo do catálogo sem stock nenhum neste armazém.
        Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'AAA SEM STOCK',
            'sku'            => 'SEM-1',
            'code'           => 'SEM-1',
            'price'          => 100,
            'type'           => 'produto',
            'manage_stock'   => true,
            'stock_quantity' => 0,
        ]);

        $nomes = array_column($this->artigos(), 'name');

        $this->assertNotContains('AAA SEM STOCK', $nomes, 'não se transfere o que não existe na origem');
        $this->assertContains('ARTIGO 000', $nomes);
    }

    public function test_com_pesquisa_encontra_mesmo_o_que_esta_a_zero(): void
    {
        // Quem procura um artigo pelo nome tem de o encontrar, nem que seja
        // para ver que está a zero. Escondê-lo faria parecer que não existe.
        $this->catalogo(1);

        Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'AAA SEM STOCK',
            'sku'            => 'SEM-1',
            'code'           => 'SEM-1',
            'price'          => 100,
            'type'           => 'produto',
            'manage_stock'   => true,
            'stock_quantity' => 0,
        ]);

        $artigos = $this->artigos(['procura' => 'SEM STOCK']);

        $this->assertCount(1, $artigos);
        $this->assertSame('AAA SEM STOCK', $artigos[0]['name']);
        // Sem linha de stock neste armazém a API responde zero — e o ecrã
        // pinta-o a vermelho em vez de o esconder.
        $this->assertSame(0.0, (float) $artigos[0]['disponivel']);
    }

    public function test_uma_quantidade_nao_positiva_e_recusada(): void
    {
        // Isto é o que mais preocupava na correcção da quantidade no carrinho:
        // um negativo INVERTE a transferência — aumenta a origem e diminui o
        // destino. O carrinho já o recusava; a API tem de o recusar também,
        // porque é ela que grava.
        $this->comStock($this->armazem, 20);

        foreach ([0, -3] as $quantidade) {
            $this->transferir($quantidade)
                ->assertStatus(422)
                ->assertJsonValidationErrors('itens.0.quantity');
        }

        $this->assertSame(0, StockMovement::where('product_id', $this->produto->id)->count());
        $this->assertSame(20.0, (float) Stock::where('warehouse_id', $this->armazem->id)->value('quantity'));
    }

    public function test_acima_do_disponivel_a_api_recusa_e_o_carrinho_limita(): void
    {
        // Quem escreve 50 quando há 9 quer transferir o que houver: dizer-lhe
        // quanto é mais útil do que recusar, e o carrinho em React limita ao
        // disponível ainda antes de gravar. Mas o tecto do ecrã é conforto, não
        // é a defesa: a defesa é a API recusar.
        $this->comStock($this->armazem, 9);

        $this->transferir(50)->assertStatus(422)->assertJsonValidationErrors('itens');

        $carrinho = file_get_contents(base_path('resources/js/ecras/facturacao/transferencias/Carrinho.tsx'));

        $this->assertStringContainsString('n > i.disponivel', $carrinho, 'o carrinho deixou de limitar ao disponível');
        $this->assertStringContainsString('n <= 0', $carrinho, 'o carrinho deixou de recusar zero e negativos');
    }

    public function test_stock_insuficiente_nao_deixa_nada_gravado(): void
    {
        $this->comStock($this->armazem, 2);

        $this->transferir(5)->assertStatus(422);

        $this->assertSame(
            0,
            StockMovement::where('product_id', $this->produto->id)->count(),
            'nem movimento, nem referência queimada'
        );

        $this->assertSame(
            2.0,
            (float) Stock::where('warehouse_id', $this->armazem->id)->where('product_id', $this->produto->id)->value('quantity'),
            'o stock da origem não mexe'
        );
    }
}
