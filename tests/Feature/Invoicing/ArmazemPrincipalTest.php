<?php

namespace Tests\Feature\Invoicing;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Warehouse;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * O armazém principal tinha DUAS verdades a competir.
 *
 * `invoicing_settings.default_warehouse_id` era escrito pelo ecrã de
 * definições; `invoicing_warehouses.is_default` é o que TODOS os formulários
 * lêem (facturas, orçamentos, compras, POS, SAFT). Escolher o armazém nas
 * definições não fazia efeito nenhum — só marcá-lo em Armazéns é que pegava.
 *
 * O ecrã de definições é hoje React e guarda por
 * `PUT /api/v1/invoicing/react/definicoes`; a ligação nos dois sentidos vive no
 * `DefinicoesDaFacturacao::fixarArmazemPrincipal()` e no `Warehouse::setAsDefault()`.
 * É isso que estes ensaios fixam.
 */
class ArmazemPrincipalTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/definicoes';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit')
             ->comModulo('invoicing');
    }

    private function armazem(string $nome, bool $padrao = false): Warehouse
    {
        return Warehouse::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => $nome,
            'code'       => 'ARM-' . uniqid(),
            'is_active'  => true,
            'is_default' => $padrao,
        ]);
    }

    private function definicoes(): InvoicingSettings
    {
        return InvoicingSettings::firstOrCreate(['tenant_id' => $this->tenant->id]);
    }

    /** O corpo que o ecrã manda, com o que se quiser trocar por cima. */
    private function corpo(array $por = []): array
    {
        return array_merge($this->getJson(self::RAIZ)->assertOk()->json('definicoes'), $por);
    }

    // ── Definições → Armazéns ────────────────────────────────────────────

    /**
     * O DEFEITO REPORTADO: escolher nas definições não marcava o armazém — e
     * os formulários, que lêem o `is_default`, continuavam no antigo.
     */
    public function test_escolher_nas_definicoes_marca_mesmo_o_armazem(): void
    {
        $this->definicoes();
        $velho = $this->armazem('Armazém Central', true);
        $novo  = $this->armazem('Armazém do Porto');

        $this->putJson(self::RAIZ, $this->corpo(['default_warehouse_id' => $novo->id]))
            ->assertOk()->assertJsonPath('aviso', null);

        $this->assertTrue($novo->refresh()->is_default, 'O armazém escolhido tem de ficar padrão.');
        $this->assertFalse($velho->refresh()->is_default, 'O anterior tem de deixar de ser.');

        // O mesmo caminho que os editores de documentos e o POS usam para
        // escolher o armazém de um documento novo — é isto que o utilizador vê.
        $this->assertSame($novo->id, Warehouse::getDefault($this->tenant->id)?->id);
        $this->assertSame($novo->id, defaultWarehouseId());
    }

    // ── Armazéns → Definições ────────────────────────────────────────────

    /** O sentido inverso: marcar em Armazéns actualiza as definições. */
    public function test_marcar_em_armazens_actualiza_as_definicoes(): void
    {
        $definicoes = $this->definicoes();
        $definicoes->update(['default_warehouse_id' => $this->armazem('Armazém Central', true)->id]);

        $novo = $this->armazem('Armazém do Porto');
        $novo->setAsDefault();

        $this->assertSame($novo->id, $definicoes->refresh()->default_warehouse_id);
    }

    /** Só pode haver um padrão: dois deixavam o documento a escolher à sorte. */
    public function test_so_ha_um_armazem_padrao(): void
    {
        $this->definicoes();
        $a = $this->armazem('A', true);
        $b = $this->armazem('B');
        $c = $this->armazem('C');

        $b->setAsDefault();
        $c->setAsDefault();

        $this->assertFalse($a->refresh()->is_default);
        $this->assertFalse($b->refresh()->is_default);
        $this->assertTrue($c->refresh()->is_default);
        $this->assertSame(1, Warehouse::where('tenant_id', $this->tenant->id)->where('is_default', true)->count());
    }

    // ── Guardas ──────────────────────────────────────────────────────────

    /**
     * Um armazém de outra empresa não pode ser marcado a partir daqui.
     *
     * Que a definição fica limpa e volta com aviso está provado em
     * `ApiDasDefinicoesParaReactTest::o_armazem_de_outra_empresa_e_recusado_com_aviso`.
     * O que se guarda AQUI é a outra metade, que ninguém mais vê: o padrão da
     * própria empresa não pode cair no meio da recusa — sem padrão, todo o
     * documento novo nasce sem armazém.
     */
    public function test_armazem_de_outra_empresa_nao_desmarca_o_meu(): void
    {
        $this->definicoes();
        $meu = $this->armazem('O meu', true);

        $outra = Tenant::create([
            'name' => 'Vizinha', 'slug' => 'viz-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $alheio = Warehouse::withoutEvents(fn () => Warehouse::create([
            'tenant_id' => $outra->id, 'name' => 'Alheio',
            'code' => 'ARM-VIZ-' . uniqid(), 'is_active' => true, 'is_default' => true,
        ]));

        $this->putJson(self::RAIZ, $this->corpo(['default_warehouse_id' => $alheio->id]))->assertOk();

        $this->assertTrue($alheio->refresh()->is_default, 'O armazém da outra empresa não pode mudar.');
        $this->assertTrue($meu->refresh()->is_default, 'O meu padrão tem de continuar de pé.');
    }

    /** Guardar sem escolher armazém não desmarca o que já lá estava. */
    public function test_guardar_sem_escolher_nao_desmarca_nada(): void
    {
        $this->definicoes();
        $actual = $this->armazem('Armazém Central', true);

        $this->putJson(self::RAIZ, $this->corpo(['default_warehouse_id' => null]))->assertOk();

        $this->assertTrue($actual->refresh()->is_default);
    }

    /** Escolher o que já é padrão não parte nada (é o caso mais comum). */
    public function test_escolher_o_que_ja_e_padrao_e_inofensivo(): void
    {
        $this->definicoes();
        $actual = $this->armazem('Armazém Central', true);

        $this->putJson(self::RAIZ, $this->corpo(['default_warehouse_id' => $actual->id]))
            ->assertOk()->assertJsonPath('aviso', null);

        $this->assertTrue($actual->refresh()->is_default);
        $this->assertSame($actual->id, $this->definicoes()->refresh()->default_warehouse_id);
    }
}
