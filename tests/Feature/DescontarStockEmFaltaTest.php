<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * Descontar o stock das vendas que nunca o descontaram.
 *
 * Enquanto um artigo tinha o "Gerenciar Stock" desligado, vender não gerava
 * movimento nenhum. Depois de a bandeira ser ligada, o stock volta a contar —
 * mas a partir de um número que já vinha errado.
 *
 * O que estes testes protegem, por ordem de gravidade:
 *   1. NÃO descontar duas vezes. É a única maneira de este comando fazer um
 *      estrago pior do que o problema que resolve.
 *   2. A contagem física manda: quem contou depois da venda já a tem
 *      reflectida. Numa base real, ignorar isto dava 1210 linhas descontadas
 *      a dobrar.
 *   3. O agregado do artigo acompanha as linhas do armazém.
 */
class DescontarStockEmFaltaTest extends TenantTestCase
{
    private function artigo(float $stock = 10): Product
    {
        $p = Product::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Paracetamol',
            'code'      => 'P-' . uniqid(),
            'type'      => 'produto',
            'price'     => 500,
            'manage_stock' => true,
            'is_active' => true,
        ]);

        Stock::withoutGlobalScopes()->create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $p->id,
            'quantity'     => $stock,
            'reserved_quantity' => 0,
        ]);

        return $p->refresh();
    }

    /** Uma venda que NÃO gerou movimento — o caso que se quer corrigir. */
    private function venda(Product $artigo, float $qtd, ?string $data = null): SalesInvoice
    {
        $factura = SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'warehouse_id'   => $this->armazem->id,
            'invoice_number' => 'FT-' . uniqid(),
            'invoice_date'   => $data ?? now()->subDay(),
            'due_date'       => now(),
            'subtotal'       => 500 * $qtd,
            'total'          => 500 * $qtd,
            'paid_amount'    => 0,
            'status'         => 'paid',
            'created_by'     => $this->user->id,
        ]);

        SalesInvoiceItem::create([
            'tenant_id'        => $this->tenant->id,
            'sales_invoice_id' => $factura->id,
            'product_id'       => $artigo->id,
            'product_name'     => $artigo->name,
            'description'      => $artigo->name,
            'quantity'         => $qtd,
            'unit_price'       => 500,
            'total'            => 500 * $qtd,
        ]);

        return $factura->refresh();
    }

    private function correr(array $opcoes = [])
    {
        return $this->artisan('vendas:descontar-stock-em-falta', array_merge([
            '--tenant' => $this->tenant->id,
            '--desde'  => now()->subDays(30)->toDateString(),
        ], $opcoes));
    }

    private function stockDe(Product $artigo): float
    {
        return (float) Stock::withoutGlobalScopes()
            ->where('warehouse_id', $this->armazem->id)
            ->where('product_id', $artigo->id)
            ->value('quantity');
    }

    // ── o caso principal ─────────────────────────────────────────────────────

    public function test_desconta_a_venda_que_nunca_desceu_o_stock(): void
    {
        $artigo = $this->artigo(10);
        $this->venda($artigo, 3);

        $this->correr(['--aplicar' => true])->assertSuccessful();

        $this->assertSame(7.0, $this->stockDe($artigo));
    }

    public function test_o_movimento_fica_ligado_a_factura_que_o_originou(): void
    {
        $artigo = $this->artigo(10);
        $factura = $this->venda($artigo, 3);

        $this->correr(['--aplicar' => true])->assertSuccessful();

        $mov = StockMovement::where('reference_type', SalesInvoice::class)
            ->where('reference_id', $factura->id)->first();

        $this->assertNotNull($mov, 'sem referência não há rastreio do artigo');
        $this->assertSame('out', $mov->type);
        $this->assertEquals(3, (float) $mov->quantity);
    }

    /**
     * O agregado do artigo tem de acompanhar as linhas do armazém.
     *
     * É o StockObserver que o mantém, e é por isso que aqui se grava pelo
     * MODELO e nunca por increment() nem DB::table — esses passavam ao lado
     * dos eventos e deixavam o POS a discordar da Gestão de Stock.
     */
    public function test_o_agregado_do_artigo_acompanha(): void
    {
        $artigo = $this->artigo(10);
        $this->venda($artigo, 4);

        $this->correr(['--aplicar' => true])->assertSuccessful();

        $this->assertEquals(6, (float) $artigo->refresh()->stock_quantity);
    }

    // ── nunca duas vezes ─────────────────────────────────────────────────────

    public function test_correr_duas_vezes_nao_desconta_a_dobrar(): void
    {
        $artigo = $this->artigo(10);
        $this->venda($artigo, 3);

        $this->correr(['--aplicar' => true])->assertSuccessful();
        $this->correr(['--aplicar' => true])->assertSuccessful();

        $this->assertSame(7.0, $this->stockDe($artigo), 'a segunda passagem não pode voltar a descontar');
    }

    public function test_uma_venda_que_ja_descontou_fica_em_paz(): void
    {
        $artigo = $this->artigo(10);
        $factura = $this->venda($artigo, 3);

        // Como se a venda tivesse corrido normalmente.
        StockMovement::semAplicarStock(fn () => StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id' => $artigo->id,
            'type' => 'out',
            'reference_type' => SalesInvoice::class,
            'reference_id' => $factura->id,
            'quantity' => 3,
            'user_id' => $this->user->id,
        ]));

        $this->correr(['--aplicar' => true])->assertSuccessful();

        $this->assertSame(10.0, $this->stockDe($artigo));
    }

    /** Meia saída registada: desconta-se só o que falta, não tudo outra vez. */
    public function test_desconta_apenas_a_diferenca(): void
    {
        $artigo = $this->artigo(10);
        $factura = $this->venda($artigo, 5);

        StockMovement::semAplicarStock(fn () => StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id' => $artigo->id,
            'type' => 'out',
            'reference_type' => SalesInvoice::class,
            'reference_id' => $factura->id,
            'quantity' => 2,
            'user_id' => $this->user->id,
        ]));

        $this->correr(['--aplicar' => true])->assertSuccessful();

        $this->assertSame(7.0, $this->stockDe($artigo), 'faltavam 3, não 5');
    }

    // ── a contagem física manda ──────────────────────────────────────────────

    /**
     * Quem contou depois da venda já a tem reflectida no número.
     *
     * Sem esta regra, uma farmácia que se mantém em ordem à custa de contagens
     * periódicas veria as vendas antigas descontadas uma segunda vez.
     */
    public function test_uma_contagem_posterior_a_venda_protege_o_stock(): void
    {
        $artigo = $this->artigo(10);
        $this->venda($artigo, 3, now()->subDays(10)->toDateString());

        // Alguém foi à prateleira e contou ONTEM.
        StockMovement::semAplicarStock(fn () => StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id' => $artigo->id,
            'type' => 'adjustment',
            'quantity' => 10,
            'notes' => 'contagem',
            'user_id' => $this->user->id,
        ]));

        $this->correr(['--aplicar' => true])->assertSuccessful();

        $this->assertSame(10.0, $this->stockDe($artigo), 'a contagem já reflectia a venda');
    }

    public function test_uma_contagem_anterior_a_venda_nao_protege(): void
    {
        $artigo = $this->artigo(10);

        $contagem = StockMovement::semAplicarStock(fn () => StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id' => $artigo->id,
            'type' => 'adjustment',
            'quantity' => 10,
            'user_id' => $this->user->id,
        ]));

        // O Eloquent reescreve o created_at ao criar; para datar a contagem
        // no passado é preciso forçá-lo depois.
        $contagem->forceFill(['created_at' => now()->subDays(20)])->saveQuietly();

        // A venda é POSTERIOR à contagem: não está reflectida.
        $this->venda($artigo, 3, now()->subDay()->toDateString());

        $this->correr(['--aplicar' => true])->assertSuccessful();

        $this->assertSame(7.0, $this->stockDe($artigo));
    }

    // ── o que fica de fora ───────────────────────────────────────────────────

    public function test_nao_toca_em_artigos_que_nao_contam_stock(): void
    {
        $artigo = $this->artigo(10);
        $artigo->forceFill(['manage_stock' => false])->save();
        $this->venda($artigo, 3);

        $this->correr(['--aplicar' => true])->assertSuccessful();

        $this->assertSame(10.0, $this->stockDe($artigo), 'se a bandeira está desligada, é porque assim se quer');
    }

    public function test_nao_toca_numa_factura_anulada(): void
    {
        $artigo = $this->artigo(10);
        $factura = $this->venda($artigo, 3);

        // Anulada em silêncio: passar de 'paid' a 'cancelled' pelo caminho
        // normal faz o SalesInvoiceObserver DEVOLVER stock que nunca saiu, e
        // isso é outro assunto — aqui só interessa que uma factura anulada
        // não seja descontada.
        $factura->forceFill(['status' => 'cancelled'])->saveQuietly();

        $this->correr(['--aplicar' => true])->assertSuccessful();

        $this->assertSame(10.0, $this->stockDe($artigo));
    }

    public function test_nao_toca_fora_do_periodo(): void
    {
        $artigo = $this->artigo(10);
        $this->venda($artigo, 3, now()->subDays(90)->toDateString());

        $this->correr(['--aplicar' => true])->assertSuccessful();

        $this->assertSame(10.0, $this->stockDe($artigo));
    }

    // ── anular só devolve o que saiu ─────────────────────────────────────────

    /**
     * Anular uma venda que nunca desceu o stock não pode SUBIR o stock.
     *
     * O returnStock devolvia a quantidade da linha sem olhar a nada. Com o
     * "Gerenciar Stock" desligado num catálogo inteiro, cada anulação criava
     * produto do nada — e ninguém percebia porque é que o inventário subia
     * sozinho.
     */
    public function test_anular_uma_venda_que_nao_descontou_nao_cria_stock(): void
    {
        $artigo = $this->artigo(10);
        $factura = $this->venda($artigo, 3);

        $factura->forceFill(['status' => 'cancelled'])->save();

        $this->assertSame(10.0, $this->stockDe($artigo), 'não saiu nada, não há nada a devolver');
    }

    public function test_anular_uma_venda_que_descontou_devolve_o_que_saiu(): void
    {
        $artigo = $this->artigo(10);
        $factura = $this->venda($artigo, 3);

        // Descontar como uma venda normal faria.
        $this->correr(['--aplicar' => true])->assertSuccessful();
        $this->assertSame(7.0, $this->stockDe($artigo));

        $factura->refresh()->forceFill(['status' => 'cancelled'])->save();

        $this->assertSame(10.0, $this->stockDe($artigo));
    }

    public function test_anular_duas_vezes_nao_devolve_a_dobrar(): void
    {
        $artigo = $this->artigo(10);
        $factura = $this->venda($artigo, 3);
        $this->correr(['--aplicar' => true])->assertSuccessful();

        $factura->refresh()->forceFill(['status' => 'cancelled'])->save();
        $factura->refresh()->forceFill(['status' => 'paid'])->saveQuietly();
        $factura->refresh()->forceFill(['status' => 'cancelled'])->save();

        $this->assertSame(10.0, $this->stockDe($artigo), 'devolveu-se o que saiu, e só uma vez');
    }

    // ── simulação e segurança ────────────────────────────────────────────────

    public function test_sem_aplicar_nao_grava_nada(): void
    {
        $artigo = $this->artigo(10);
        $this->venda($artigo, 3);

        $this->correr()->expectsOutputToContain('SIMULAÇÃO')->assertSuccessful();

        $this->assertSame(10.0, $this->stockDe($artigo));
        $this->assertSame(0, StockMovement::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_sem_empresa_recusa(): void
    {
        $this->artisan('vendas:descontar-stock-em-falta')->assertFailed();
    }

    /**
     * O stock pode ficar negativo, e deve.
     *
     * Vendeu-se mais do que o sistema julgava ter. O negativo é a medida do
     * que falta contar; escondê-lo em zero apagava a única pista.
     */
    public function test_deixa_o_stock_ir_a_negativo(): void
    {
        $artigo = $this->artigo(1);
        $this->venda($artigo, 4);

        $this->correr(['--aplicar' => true])
            ->expectsOutputToContain('NEGATIVO')
            ->assertSuccessful();

        $this->assertSame(-3.0, $this->stockDe($artigo));
    }
}
