<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Product;
use App\Services\POS\PosSaleService;
use Tests\TenantTestCase;

/**
 * Dois dispositivos offline, e a rede a voltar ao mesmo tempo.
 *
 * É o cenário real de uma loja a fechar: as caixas estiveram sem rede, cada
 * uma com dezenas de vendas na fila, e quando a ligação regressa começam
 * todas a despejar ao mesmo tempo. O que corre mal aqui não corre mal em
 * desenvolvimento — só com duas pessoas a trabalhar a sério.
 */
class SincronizacaoOfflineTest extends TenantTestCase
{
    private Product $artigo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        $this->artigo = Product::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Água 1,5L',
            'code'       => 'AGUA-' . uniqid(),
            'price'      => 500,
            'cost_price' => 300,
            'type'       => 'produto',
            'is_active'  => true,
            'tax_id'     => $this->imposto->id,
        ]);
    }

    private function venda(string $uuid, float $quantidade = 1): array
    {
        return [
            'local_uuid'     => $uuid,
            'payment_method' => 'cash',
            'items'          => [[
                'product_id'   => $this->artigo->id,
                'product_name' => $this->artigo->name,
                'quantity'     => $quantidade,
                'unit_price'   => 500,
            ]],
        ];
    }

    // ==================== numeração ====================

    /**
     * Dois pedidos que leiam a série ao mesmo tempo não podem tirar o mesmo
     * número.
     *
     * O getNextNumber() lia o next_number do objecto que já tinha em mãos,
     * procurava na base de dados se aquele número já estava usado, e só depois
     * incrementava. Sem bloqueio, dois pedidos em voo lêem ambos 100, nenhum
     * dos dois vê a factura do outro (ainda não foi gravada) e ambos devolvem
     * o mesmo número.
     *
     * As duas instâncias abaixo são exactamente isso: a série lida por dois
     * pedidos antes de qualquer um escrever.
     */
    public function test_duas_leituras_da_serie_nao_devolvem_o_mesmo_numero(): void
    {
        $serie = InvoicingSeries::getIssuanceSeries($this->tenant->id, 'pos');

        $primeira = InvoicingSeries::find($serie->id);
        $segunda  = InvoicingSeries::find($serie->id);

        $numeroA = $primeira->getNextNumber();
        $numeroB = $segunda->getNextNumber();

        $this->assertNotSame(
            $numeroA,
            $numeroB,
            'Duas vendas ficariam com o mesmo número de factura.'
        );
    }

    /** E a série fica coerente: o próximo número não recua nem salta. */
    public function test_a_serie_avanca_uma_vez_por_numero_dado(): void
    {
        $serie = InvoicingSeries::getIssuanceSeries($this->tenant->id, 'pos');
        $antes = $serie->next_number;

        InvoicingSeries::find($serie->id)->getNextNumber();
        InvoicingSeries::find($serie->id)->getNextNumber();
        InvoicingSeries::find($serie->id)->getNextNumber();

        $this->assertSame(
            $antes + 3,
            InvoicingSeries::find($serie->id)->next_number,
            'Três números dados, três incrementos.'
        );
    }

    // ==================== idempotência ====================

    /**
     * A mesma venda enviada duas vezes grava uma factura só.
     *
     * Com a rede a oscilar, o PWA reenvia o que não teve resposta. Sem
     * idempotência isso dava factura, desconto de stock e tesouraria a dobrar.
     */
    public function test_a_mesma_venda_reenviada_nao_duplica(): void
    {
        $servico = app(PosSaleService::class);
        $carga = $this->venda('uuid-repetido-1');

        $primeira = $servico->createFromPayload($carga, $this->tenant->id, $this->user->id);
        $segunda  = $servico->createFromPayload($carga, $this->tenant->id, $this->user->id);

        $this->assertSame($primeira->id, $segunda->id);
        $this->assertSame($primeira->invoice_number, $segunda->invoice_number);

        $this->assertSame(
            1,
            SalesInvoice::where('tenant_id', $this->tenant->id)
                ->where('local_uuid', 'uuid-repetido-1')
                ->count()
        );
    }

    /**
     * Vendas de dispositivos diferentes ficam com números diferentes.
     *
     * É o caso que a numeração tem de garantir: dois caixas, duas vendas
     * distintas, dois números distintos — e nenhum erro pelo caminho.
     */
    public function test_duas_vendas_de_caixas_diferentes_recebem_numeros_diferentes(): void
    {
        $servico = app(PosSaleService::class);

        $caixaA = $servico->createFromPayload($this->venda('caixa-a-1'), $this->tenant->id, $this->user->id);
        $caixaB = $servico->createFromPayload($this->venda('caixa-b-1'), $this->tenant->id, $this->user->id);

        $this->assertNotSame($caixaA->id, $caixaB->id);
        $this->assertNotSame(
            $caixaA->invoice_number,
            $caixaB->invoice_number,
            'Duas vendas distintas com o mesmo número violam a numeração fiscal.'
        );
    }

    // ==================== clientes criados offline ====================

    /**
     * Um cliente de balcão reenviado não se duplica.
     *
     * A desduplicação só olhava para o NIF, e quem compra ao balcão não o dá.
     * O local_uuid já vinha do PWA e já era devolvido na resposta — mas não
     * havia coluna onde o guardar, portanto nunca serviu para nada.
     *
     * O caminho que falha na vida real: o pedido chega, o cliente é criado, a
     * ligação cai antes da resposta, o PWA conta como falha e reenvia. Ficavam
     * dois registos da mesma pessoa e as vendas seguintes divididas entre eles.
     */
    public function test_um_cliente_reenviado_nao_duplica(): void
    {
        $carga = [
            'local_uuid' => 'cliente-balcao-1',
            'name'       => 'Maria Fernandes',
            'nif'        => '5417654321',
            'type'       => 'pessoa_fisica',
        ];

        $primeira = $this->postJson('/api/v1/invoicing/clients', $carga)->assertSuccessful();
        $segunda  = $this->postJson('/api/v1/invoicing/clients', $carga)->assertSuccessful();

        $this->assertSame(
            $primeira->json('id'),
            $segunda->json('id'),
            'O reenvio criou um segundo cliente.'
        );

        $this->assertTrue($segunda->json('duplicated'));

        $this->assertSame(
            1,
            \App\Models\Client::where('tenant_id', $this->tenant->id)
                ->where('name', 'Maria Fernandes')
                ->count()
        );
    }

    /** Dois clientes distintos continuam a ser dois. */
    public function test_dois_clientes_diferentes_nao_se_fundem(): void
    {
        $a = $this->postJson('/api/v1/invoicing/clients', [
            'local_uuid' => 'balcao-a', 'name' => 'Ana Lopes',
            'nif' => '5000000001', 'type' => 'pessoa_fisica',
        ])->assertSuccessful();

        $b = $this->postJson('/api/v1/invoicing/clients', [
            'local_uuid' => 'balcao-b', 'name' => 'Bruno Dias',
            'nif' => '5000000002', 'type' => 'pessoa_fisica',
        ])->assertSuccessful();

        $this->assertNotSame($a->json('id'), $b->json('id'));
    }

    /**
     * UM CLIENTE SEM NIF ENTRA — este ensaio dizia o contrário, e era o defeito
     * escrito como regra.
     *
     * A versão anterior exigia 422 «porque a coluna é NOT NULL». Deixou de
     * ser: a coluna é nula, e o próprio controlador grava NULL nos NIF
     * genéricos. O 'required' ficou para trás e fazia isto ao balcão: o
     * cliente rápido do POS (só com o nome, como a maioria) era recusado, o
     * aparelho marcava-o como recusado de vez, e a venda que o referenciava
     * ficava «a reagendar» cinco vezes e morria na fila. A queixa foi
     * literal: «cliente offline não sincroniza e a factura sai como
     * consumidor final».
     */
    public function test_um_cliente_sem_nif_entra(): void
    {
        $r = $this->postJson('/api/v1/invoicing/clients', [
            'local_uuid' => 'sem-nif-1',
            'name'       => 'Cliente de Balcão',
            'type'       => 'pessoa_fisica',
        ])->assertStatus(201);

        $cliente = \App\Models\Client::where('tenant_id', $this->tenant->id)
            ->where('name', 'Cliente de Balcão')
            ->first();

        $this->assertNotNull($cliente, 'o cliente sem contribuinte tem de ficar criado');
        $this->assertSame($cliente->id, $r->json('id'));
        $this->assertNull($cliente->nif, 'sem contribuinte é NULL — não um marcador nem um 422');
    }

    // ==================== a fila do PWA ====================

    /**
     * Um pedido que o servidor recusa não se repete cinco vezes.
     *
     * A fila tratava todos os erros por igual: cinco tentativas e depois
     * "erro permanente". Mas um 4xx é uma recusa pelo que o pedido É — falta
     * um campo, o valor não serve — e repeti-lo não o compõe. Só gasta as
     * tentativas e põe-se à frente das vendas boas que estão atrás na fila,
     * que é o que dói quando a loja fecha e há cem coisas para despejar.
     *
     * O 408 e o 429 ficam de fora: dizem "agora não", não "nunca".
     *
     * Verifica-se na fonte porque é JavaScript — o mesmo caminho que o
     * TraducoesJavaScriptTest usa para as regras de plural.
     */
    public function test_a_fila_do_pwa_nao_repete_o_que_o_servidor_recusou(): void
    {
        $js = file_get_contents(public_path('js/pwa-invoicing.js'));

        $this->assertStringContainsString(
            'erro.definitivo = definitivo',
            $js,
            'A resposta 4xx tem de ser marcada como definitiva.'
        );

        $this->assertStringContainsString(
            'if (err.definitivo)',
            $js,
            'A fila tem de olhar para a marca antes de contar mais uma tentativa.'
        );

        $this->assertStringContainsString(
            "response.status !== 408",
            $js,
            'O 408 diz "agora não" e tem de continuar a ser repetido.'
        );

        $this->assertStringContainsString(
            "response.status !== 429",
            $js,
            'O 429 diz "abrande" e tem de continuar a ser repetido.'
        );
    }

    /**
     * O índice único é a última linha de defesa.
     *
     * Mesmo que a geração do número falhasse, a base de dados tem de recusar
     * duas facturas com o mesmo número na mesma empresa. Sem isso, um erro de
     * concorrência passava a documento fiscal duplicado — e esses não se
     * apagam.
     */
    public function test_a_base_de_dados_recusa_dois_numeros_iguais(): void
    {
        $servico = app(PosSaleService::class);
        $factura = $servico->createFromPayload($this->venda('unico-1'), $this->tenant->id, $this->user->id);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $factura->client_id,
            'invoice_number' => $factura->invoice_number,
            'invoice_date'   => now(),
            'due_date'       => now(),
            'subtotal'       => 1,
            'total'          => 1,
            'status'         => 'paid',
            'created_by'     => $this->user->id,
        ]);
    }
}
