<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Services\Audit\LeituraDaTrilha;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * A trilha de auditoria tem de se ler.
 *
 * O ecrã mostrava o que está gravado, tal e qual: `warehouse_id: 12`,
 * `product_id: 8779`, `type: "out"`. Fiel, e ilegível — para saber o que tinha
 * acontecido era preciso ir à base traduzir os números à mão.
 *
 * Tudo isto é do lado da LEITURA, e é a única forma que serve: a tabela é
 * append-only e encadeada por hash, portanto enriquecer o que lá está obrigaria
 * a reescrever linhas e a partir a cadeia. Resolvido na leitura, os registos
 * antigos também passam a ler-se.
 */
class LeituraDaTrilhaTest extends TenantTestCase
{
    protected Product $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->produto = Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'SEGURO-72HORAS 1.5MG PILULA DIA SEGUINTE',
            'sku'            => 'SEG-72',
            'code'           => 'SEG-72',
            'price'          => 1000,
            'cost'           => 600,
            'type'           => 'produto',
            'manage_stock'   => true,
            'stock_quantity' => 0,
        ]);
    }

    private function leitura(): LeituraDaTrilha
    {
        return new LeituraDaTrilha;
    }

    /** O registo de auditoria do último movimento criado. */
    private function trilhaDoMovimento(): AuditTrail
    {
        $registo = AuditTrail::withoutGlobalScopes()
            ->where('auditable_type', StockMovement::class)
            ->where('event', 'created')
            ->latest('id')
            ->first();

        $this->assertNotNull($registo, 'a auditoria não registou o movimento');

        return $registo;
    }

    public function test_a_frase_diz_o_que_aconteceu_sem_ids(): void
    {
        Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->produto->id,
            'quantity'     => 7,
        ]);

        StockMovement::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->produto->id,
            'type'         => 'out',
            'quantity'     => 1,
            'unit_cost'    => 199.60,
            'user_id'      => $this->user->id,
            'notes'        => 'Venda POS - FR A/001813',
        ]);

        $frase = $this->leitura()->frase($this->trilhaDoMovimento());

        $this->assertStringContainsString('Saída de 1', $frase);
        $this->assertStringContainsString('SEGURO-72HORAS', $frase);
        $this->assertStringContainsString($this->armazem->name, $frase);
        $this->assertStringContainsString('saldo 7 → 6', $frase, 'o saldo é o que transforma o registo em prova');
    }

    public function test_a_referencia_traz_o_nome_e_o_numero(): void
    {
        // O nome é o de HOJE e o registo é do passado: se o armazém for
        // renomeado, é o número que continua a ancorar o facto.
        $rotulo = $this->leitura()->rotuloDaReferencia('warehouse_id', $this->armazem->id);

        $this->assertStringContainsString($this->armazem->name, $rotulo);
        $this->assertStringContainsString('#' . $this->armazem->id, $rotulo);
    }

    public function test_uma_referencia_apagada_fica_pelo_numero(): void
    {
        // Um artigo removido do catálogo não pode fazer o ecrã rebentar nem
        // desaparecer do registo: mostra-se o identificador.
        $this->assertSame('#999999', $this->leitura()->rotuloDaReferencia('product_id', 999999));
    }

    public function test_os_campos_saem_com_nome_em_portugues(): void
    {
        Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->produto->id,
            'quantity'     => 7,
        ]);

        StockMovement::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->produto->id,
            'type'         => 'out',
            'quantity'     => 1,
            'user_id'      => $this->user->id,
        ]);

        $campos = collect($this->leitura()->campos($this->trilhaDoMovimento()))
            ->keyBy('campo');

        $this->assertSame('Armazém', $campos['warehouse_id']['rotulo']);
        $this->assertSame('Artigo', $campos['product_id']['rotulo']);
        $this->assertSame('Saldo anterior', $campos['balance_before']['rotulo']);
        $this->assertSame('Saldo depois', $campos['balance_after']['rotulo']);

        $this->assertStringContainsString($this->armazem->name, $campos['warehouse_id']['depois']);
        $this->assertSame('saída', $campos['type']['depois'], 'o tipo traduzido');
    }

    public function test_o_id_nao_aparece_como_alteracao_numa_criacao(): void
    {
        // "id: null → 12572" não diz nada a ninguém, e o identificador já está
        // no cabeçalho do detalhe.
        Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->produto->id,
            'quantity'     => 7,
        ]);

        StockMovement::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->produto->id,
            'type'         => 'out',
            'quantity'     => 1,
            'user_id'      => $this->user->id,
        ]);

        $campos = array_column($this->leitura()->campos($this->trilhaDoMovimento()), 'campo');

        $this->assertNotContains('id', $campos);
    }

    public function test_as_referencias_aparecem_primeiro(): void
    {
        Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->produto->id,
            'quantity'     => 7,
        ]);

        StockMovement::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->produto->id,
            'type'         => 'out',
            'quantity'     => 1,
            'user_id'      => $this->user->id,
        ]);

        $campos = $this->leitura()->campos($this->trilhaDoMovimento());

        $this->assertTrue(
            $campos[0]['referencia'],
            'de que artigo e de que armazém se fala é o que se procura ao abrir o detalhe'
        );
    }

    public function test_preparar_resolve_a_pagina_toda_em_poucas_consultas(): void
    {
        // Sem o lote era uma consulta por campo e por linha: 25 registos com
        // seis referências davam mais de cem consultas por render.
        Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->produto->id,
            'quantity'     => 100,
        ]);

        for ($i = 0; $i < 12; $i++) {
            StockMovement::create([
                'tenant_id'    => $this->tenant->id,
                'warehouse_id' => $this->armazem->id,
                'product_id'   => $this->produto->id,
                'type'         => 'out',
                'quantity'     => 1,
                'user_id'      => $this->user->id,
            ]);
        }

        $registos = AuditTrail::withoutGlobalScopes()
            ->where('auditable_type', StockMovement::class)
            ->latest('id')->limit(12)->get();

        $this->assertCount(12, $registos);

        $leitura = $this->leitura();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $leitura->preparar($registos);

        foreach ($registos as $r) {
            $leitura->frase($r);
        }

        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Hoje são 9: três tabelas de referência (armazéns, artigos, empresas),
        // a inspecção de colunas de cada uma, e pouco mais. O que este limite
        // guarda é que o custo NÃO acompanha o número de registos — linha a
        // linha, com três referências cada, seriam 36.
        $this->assertLessThan(
            12,
            $consultas,
            "12 registos deram {$consultas} consultas — a resolução voltou a ser linha a linha"
        );
    }

    public function test_o_nome_do_modelo_e_traduzido(): void
    {
        $leitura = $this->leitura();

        $this->assertSame('movimento de stock', $leitura->nomeDoModelo('StockMovement'));
        $this->assertSame('linha de factura', $leitura->nomeDoModelo('SalesInvoiceItem'));
        $this->assertSame('nota de crédito', $leitura->nomeDoModelo('CreditNote'));
    }

    public function test_um_nome_de_classe_no_valor_e_traduzido(): void
    {
        // `reference_type` guarda "App\Models\Invoicing\SalesInvoice"; no ecrã
        // isso aparecia com as barras a dobrar, por causa do json_encode.
        $this->assertSame(
            'factura',
            $this->leitura()->valor('reference_type', 'App\\Models\\Invoicing\\SalesInvoice')
        );
    }

    public function test_o_ecra_da_auditoria_mostra_a_frase(): void
    {
        $this->comPermissoes('invoicing.settings.view')->comModulo('invoicing');

        Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->produto->id,
            'quantity'     => 7,
        ]);

        StockMovement::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->produto->id,
            'type'         => 'out',
            'quantity'     => 1,
            'user_id'      => $this->user->id,
        ]);

        // O ecrã é React e lê a trilha pela API. O que se guarda é o mesmo: a
        // linha chega em português legível, com o nome do artigo, e sem os
        // nomes crus das colunas — que era o que estava lá antes de haver
        // frase nenhuma.
        $linhas = $this->getJson('/api/v1/invoicing/react/auditoria')->assertOk()->json('data');
        $frases = implode("\n", array_column($linhas, 'frase'));

        $this->assertStringContainsString('SEGURO-72HORAS', $frases);
        $this->assertStringContainsString('Saída de 1', $frases);
        $this->assertStringNotContainsString('warehouse_id:', $frases);
    }
}
