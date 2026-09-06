<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * Criar produto com os campos numéricos opcionais em branco.
 *
 * O formulário devolve '' num campo de número que se limpa. A validação
 * deixava passar (são nullable) mas o MySQL recusa '' numa coluna inteira:
 * o INSERT rebentava, o pedido devolvia 500 e o ecrã não dizia nada —
 * o utilizador carregava em "Criar" e não acontecia nada.
 *
 * O ecrã passou a React e a gravação passou a ser a API
 * `/api/v1/invoicing/react/products`. O caminho é outro, o campo em branco é
 * o mesmo — e é o que se continua a provar aqui. VAZIO NÃO É ZERO: o mínimo
 * em branco é 0, mas o máximo em branco é "sem máximo".
 */
class CriarProdutoComCamposVaziosTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/products';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')
            ->comPermissoes('invoicing.products.view', 'invoicing.products.create', 'invoicing.products.edit');
    }

    private function categoria(): Category
    {
        return Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bebidas ' . uniqid(), 'is_active' => true,
        ]);
    }

    /** O corpo do formulário, com o mínimo obrigatório preenchido. */
    private function corpo(array $por = []): array
    {
        return array_merge([
            'name' => 'pao',
            'type' => 'produto',
            'price' => 0,
            'unit' => 'Unidade',
            'category_id' => $this->categoria()->id,
            'tax_type' => 'isento',
            'exemption_reason' => 'I13',
        ], $por);
    }

    private function gravado(): ?Product
    {
        return Product::where('tenant_id', $this->tenant->id)->where('name', 'pao')->first();
    }

    public function test_grava_com_stock_minimo_e_maximo_em_branco(): void
    {
        $this->postJson(self::RAIZ, $this->corpo([
            'stock_min' => '',
            'stock_max' => '',
            'stock_quantity' => '',
        ]))->assertCreated();

        $p = $this->gravado();

        $this->assertNotNull($p, 'o produto tinha de ser gravado');
        $this->assertSame(0, (int) $p->stock_min, 'mínimo em branco é 0');
        $this->assertNull($p->stock_max, 'máximo em branco é "sem máximo"');
    }

    public function test_grava_com_meses_apos_abertura_em_branco(): void
    {
        $this->postJson(self::RAIZ, $this->corpo(['pao_months' => '']))->assertCreated();

        $this->assertNotNull($this->gravado());
    }

    public function test_os_valores_preenchidos_continuam_a_ser_gravados(): void
    {
        $this->postJson(self::RAIZ, $this->corpo([
            'stock_min' => '5',
            'stock_max' => '50',
        ]))->assertCreated();

        $p = $this->gravado();

        $this->assertSame(5, (int) $p->stock_min);
        $this->assertSame(50, (int) $p->stock_max);
    }

    /**
     * E o custo em branco também não rebenta.
     *
     * `cost` é NOT NULL *com omissão na base* — e uma omissão só se aplica
     * quando a coluna não vem no INSERT. Mandar o vazio atropelava-a e o
     * MySQL respondia «Column 'cost' cannot be null».
     */
    public function test_grava_com_o_custo_em_branco(): void
    {
        $this->postJson(self::RAIZ, $this->corpo(['cost' => '']))->assertCreated();

        $this->assertSame(0.0, (float) $this->gravado()->cost);
    }
}
