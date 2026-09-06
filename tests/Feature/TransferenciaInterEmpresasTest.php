<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Transferência de stock entre EMPRESAS.
 *
 * O mesmo tratamento dado às transferências entre armazéns — referência
 * legível, saldos, documento, quantidade corrigível — com uma diferença que
 * muda o desenho: são duas empresas, e a numeração MOV/AAAA/NNNNNN é
 * sequencial POR EMPRESA.
 *
 * Daí as DUAS referências. Escrever a da origem nas linhas do destino corrompia
 * a sequência do destino: se ele já tivesse ido mais longe, passavam a existir
 * lá dois documentos com o mesmo número.
 *
 * O ecrã é hoje o React `TransferenciasEntreEmpresas.tsx`; a regra vive no
 * serviço `TransferenciaDeStock` e entra pela API
 * `/api/v1/invoicing/react/transferencias/entre-empresas`.
 */
class TransferenciaInterEmpresasTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/transferencias';

    protected Tenant $empresaB;
    protected Warehouse $armazemB;
    protected Product $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresaB = Tenant::create([
            'name'      => 'Empresa Destino',
            'slug'      => 'empresa-destino-' . uniqid(),
            'nif'       => '5' . random_int(100000000, 999999999),
            'email'     => 'destino' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        // O utilizador tem de ter acesso às duas: o serviço resolve o destino a
        // partir das empresas DO UTILIZADOR, e bem — um id arbitrário permitia
        // escrever stock em qualquer empresa da plataforma.
        $this->user->tenants()->syncWithoutDetaching([$this->empresaB->id]);

        $this->armazemB = Warehouse::withoutGlobalScopes()
            ->where('tenant_id', $this->empresaB->id)
            ->where('is_active', true)
            ->first()
            ?? Warehouse::create([
                'tenant_id'  => $this->empresaB->id,
                'name'       => 'Armazém B',
                'code'       => 'ARM-B',
                'is_active'  => true,
                'is_default' => true,
            ]);

        $this->produto = Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'AMOXICILINA 500MG',
            'sku'            => 'AMOX-500',
            'code'           => 'AMOX-500',
            'barcode'        => '7890000000123',
            'price'          => 1200,
            'cost'           => 700,
            'type'           => 'produto',
            'manage_stock'   => true,
            'stock_quantity' => 0,
        ]);

        $this->comModulo('invoicing')
             ->comPermissoes('invoicing.inter-company-transfer.create', 'invoicing.stock.view');
    }

    private function comStock(float $q): Stock
    {
        return Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->produto->id,
            'quantity'     => $q,
            'unit_cost'    => 700,
        ]);
    }

    private function transferir(float $q = 4, ?int $paraEmpresa = null)
    {
        return $this->postJson(self::RAIZ . '/entre-empresas', [
            'de_armazem'   => $this->armazem->id,
            'para_empresa' => $paraEmpresa ?? $this->empresaB->id,
            'para_armazem' => $this->armazemB->id,
            'notas'        => 'Reposição entre lojas',
            'itens'        => [[
                'product_id'   => $this->produto->id,
                'product_name' => $this->produto->name,
                'quantity'     => $q,
                'unit_cost'    => 700,
            ]],
        ]);
    }

    /** As duas pernas: a da empresa activa e a da empresa destino. */
    private function pernas(): array
    {
        $saida = StockMovement::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('reference_type', 'inter_company')
            ->latest('id')->first();

        $entrada = StockMovement::withoutGlobalScopes()
            ->where('tenant_id', $this->empresaB->id)
            ->where('reference_type', 'inter_company')
            ->latest('id')->first();

        return [$saida, $entrada];
    }

    public function test_cada_empresa_recebe_a_sua_referencia(): void
    {
        $this->comStock(20);
        $resposta = $this->transferir(4)->assertCreated();

        [$saida, $entrada] = $this->pernas();

        $this->assertMatchesRegularExpression('#^MOV/\d{4}/\d{6}$#', (string) $saida->batch_reference);
        $this->assertMatchesRegularExpression('#^MOV/\d{4}/\d{6}$#', (string) $entrada->batch_reference);

        // E a API entrega as duas, com o nome de quem recebeu — é o que o ecrã
        // mostra no comprovativo.
        $this->assertSame($saida->batch_reference, $resposta->json('referencia_origem'));
        $this->assertSame($entrada->batch_reference, $resposta->json('referencia_destino'));
        $this->assertSame('Empresa Destino', $resposta->json('destino_nome'));
    }

    public function test_a_referencia_do_destino_sai_da_sequencia_do_destino(): void
    {
        // A regressão que isto evita: com uma referência partilhada, a da
        // origem entrava na sequência do destino. Se o destino já lá tivesse
        // chegado, ficavam dois documentos diferentes com o mesmo número — e o
        // ecrã do documento juntava-os num só.
        //
        // Põe-se o destino MUITO à frente e confirma-se que ele continua a sua
        // própria contagem em vez de herdar a da origem.
        StockMovement::semAplicarStock(fn () => StockMovement::withoutGlobalScopes()->create([
            'tenant_id'       => $this->empresaB->id,
            'warehouse_id'    => $this->armazemB->id,
            'product_id'      => $this->produto->id,
            'type'            => 'in',
            'quantity'        => 1,
            'user_id'         => $this->user->id,
            'batch_reference' => 'MOV/' . now()->year . '/000900',
        ]));

        $this->comStock(20);
        $this->transferir(4)->assertCreated();

        [$saida, $entrada] = $this->pernas();

        $this->assertSame('MOV/' . now()->year . '/000901', $entrada->batch_reference, 'segue a sequência do destino');
        $this->assertNotSame($saida->batch_reference, $entrada->batch_reference, 'são dois documentos');
    }

    public function test_os_quatro_saldos_ficam_gravados(): void
    {
        // Na perna do destino os saldos TÊM de ir explícitos: o carimbo
        // automático lê o stock com o global scope da empresa activa — a
        // origem — e para o destino não devolvia nada.
        $this->comStock(20);
        $resposta = $this->transferir(4)->assertCreated();

        [$saida, $entrada] = $this->pernas();

        $this->assertSame(20.0, (float) $saida->balance_before);
        $this->assertSame(16.0, (float) $saida->balance_after);

        $this->assertSame(0.0, (float) $entrada->balance_before, 'o destino não tinha nenhum');
        $this->assertSame(4.0, (float) $entrada->balance_after);

        // Os mesmos quatro no resumo que o ecrã mostra.
        $this->assertEqualsWithDelta(20, $resposta->json('resumo.0.origem_antes'), 0.001);
        $this->assertEqualsWithDelta(16, $resposta->json('resumo.0.origem_depois'), 0.001);
        $this->assertEqualsWithDelta(0, $resposta->json('resumo.0.destino_antes'), 0.001);
        $this->assertEqualsWithDelta(4, $resposta->json('resumo.0.destino_depois'), 0.001);
    }

    public function test_o_documento_do_lote_abre(): void
    {
        $this->comStock(20);
        $resposta = $this->transferir(4)->assertCreated();

        [$saida] = $this->pernas();

        $this->assertSame('/invoicing/stock/movimentacao/' . $saida->batch_reference . '/pdf', $resposta->json('pdf'));

        // Dentro de cada empresa a movimentação tem UM lado só — a outra perna
        // pertence à outra empresa. Por isso vai no documento de entradas e
        // saídas e não no de transferências, que junta as duas pernas.
        $this->get(route('invoicing.stock.batch-preview', ['reference' => $saida->batch_reference]))
            ->assertOk()
            ->assertSee('Movimentação de Stock')
            ->assertSee($saida->batch_reference)
            ->assertSee('Empresa Destino');   // vem na nota do movimento
    }

    public function test_uma_quantidade_nao_positiva_nao_passa(): void
    {
        // Um negativo aqui tira stock ao destino e dá-o à origem — o contrário
        // do que se pediu, em duas empresas ao mesmo tempo. O carrinho em React
        // já o recusa, mas quem grava é a API.
        $this->comStock(20);

        foreach ([0, -2] as $quantidade) {
            $this->transferir($quantidade)->assertStatus(422)->assertJsonValidationErrors('itens.0.quantity');
        }

        $this->assertSame(0, StockMovement::withoutGlobalScopes()->where('reference_type', 'inter_company')->count());
    }

    public function test_acima_do_disponivel_nao_se_transfere(): void
    {
        $this->comStock(6);

        $this->transferir(99)->assertStatus(422)->assertJsonValidationErrors('itens');

        $this->assertSame(
            6.0,
            (float) DB::table('invoicing_stocks')->where('tenant_id', $this->tenant->id)->where('product_id', $this->produto->id)->sum('quantity'),
            'o stock da origem não mexe'
        );
        $this->assertSame(0.0, (float) DB::table('invoicing_stocks')->where('tenant_id', $this->empresaB->id)->sum('quantity'));
    }

    public function test_sem_motivo_a_transferencia_e_recusada(): void
    {
        // Uma transferência entre empresas move património de uma para a
        // outra: sem motivo escrito, não há como justificá-la mais tarde.
        $this->comStock(20);

        $this->postJson(self::RAIZ . '/entre-empresas', [
            'de_armazem'   => $this->armazem->id,
            'para_empresa' => $this->empresaB->id,
            'para_armazem' => $this->armazemB->id,
            'itens'        => [['product_id' => $this->produto->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('notas');
    }

    public function test_nada_se_escreve_numa_empresa_a_que_o_utilizador_nao_tem_acesso(): void
    {
        // A defesa que já existia e que não pode partir-se: um id arbitrário
        // permitia escrever stock E ARTIGOS em qualquer empresa da plataforma.
        $alheia = Tenant::create([
            'name'      => 'Empresa Alheia',
            'slug'      => 'alheia-' . uniqid(),
            'nif'       => (string) random_int(700000000, 799999999),
            'email'     => 'alheia' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        $this->comStock(20);

        $this->transferir(4, $alheia->id)->assertStatus(422);

        $this->assertSame(
            0,
            StockMovement::withoutGlobalScopes()->where('tenant_id', $alheia->id)->count(),
            'nada escrito numa empresa a que o utilizador não tem acesso'
        );

        $this->assertSame(
            0,
            Product::withoutGlobalScopes()->where('tenant_id', $alheia->id)->where('name', $this->produto->name)->count(),
            'nem sequer a cópia do artigo'
        );

        $this->assertSame(
            20.0,
            (float) DB::table('invoicing_stocks')
                ->where('tenant_id', $this->tenant->id)
                ->where('product_id', $this->produto->id)
                ->sum('quantity'),
            'o stock da origem não mexe'
        );
    }

    public function test_o_historico_entre_empresas_mostra_a_perna_desta_empresa(): void
    {
        // Cada empresa vê o seu lado: a origem vê a saída, o destino veria a
        // entrada. Misturá-los mostraria a uma empresa o movimento da outra.
        $this->comStock(20);
        $this->transferir(4)->assertCreated();

        $linhas = $this->getJson(self::RAIZ . '/entre-empresas/historico')->assertOk()->json('data');

        $this->assertCount(1, $linhas);
        $this->assertSame('saida', $linhas[0]['sentido']);
        $this->assertEqualsWithDelta(4, $linhas[0]['quantidade'], 0.001);
        $this->assertSame($this->produto->name, $linhas[0]['artigo']);
        $this->assertSame($this->user->name, $linhas[0]['quem']);
    }
}
