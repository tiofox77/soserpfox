<?php

namespace Tests\Feature\Invoicing;

use App\Livewire\Invoicing\Settings;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Warehouse;
use App\Models\Tenant;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O armazém principal tinha DUAS verdades a competir.
 *
 * `invoicing_settings.default_warehouse_id` era escrito pelo ecrã de
 * definições; `invoicing_warehouses.is_default` é o que TODOS os formulários
 * lêem (facturas, orçamentos, compras, POS, SAFT). Escolher o armazém nas
 * definições não fazia efeito nenhum — só marcá-lo em Armazéns é que pegava.
 *
 * Estes testes fixam a ligação nos dois sentidos.
 */
class ArmazemPrincipalTest extends TenantTestCase
{
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

    // ── Definições → Armazéns ────────────────────────────────────────────

    /** O DEFEITO REPORTADO: escolher nas definições não marcava o armazém. */
    public function test_escolher_nas_definicoes_marca_mesmo_o_armazem(): void
    {
        $this->definicoes();
        $velho = $this->armazem('Armazém Central', true);
        $novo  = $this->armazem('Armazém do Porto');

        Livewire::test(Settings::class)
            ->set('default_warehouse_id', $novo->id)
            ->call('save');

        $this->assertTrue($novo->refresh()->is_default, 'O armazém escolhido tem de ficar padrão.');
        $this->assertFalse($velho->refresh()->is_default, 'O anterior tem de deixar de ser.');
    }

    /** E os formulários passam a apanhá-lo — é isto que o utilizador vê. */
    public function test_os_formularios_passam_a_apanhar_o_armazem_escolhido(): void
    {
        $this->definicoes();
        $this->armazem('Armazém Central', true);
        $novo = $this->armazem('Armazém do Porto');

        Livewire::test(Settings::class)
            ->set('default_warehouse_id', $novo->id)
            ->call('save');

        // O mesmo caminho que InvoiceCreate, QuoteCreate, PurchaseCreate e o
        // POS usam para escolher o armazém de um documento novo.
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
     * Um armazém de outra empresa não pode ser marcado a partir daqui — e a
     * definição fica limpa em vez de apontar para o nada.
     */
    public function test_armazem_de_outra_empresa_e_recusado(): void
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

        Livewire::test(Settings::class)
            ->set('default_warehouse_id', $alheio->id)
            ->call('save');

        $this->assertTrue($alheio->refresh()->is_default, 'O armazém da outra empresa não pode mudar.');
        $this->assertTrue($meu->refresh()->is_default, 'O meu padrão tem de continuar de pé.');
        $this->assertNull($this->definicoes()->refresh()->default_warehouse_id);
    }

    /** Guardar sem escolher armazém não desmarca o que já lá estava. */
    public function test_guardar_sem_escolher_nao_desmarca_nada(): void
    {
        $this->definicoes();
        $actual = $this->armazem('Armazém Central', true);

        Livewire::test(Settings::class)
            ->set('default_warehouse_id', null)
            ->call('save');

        $this->assertTrue($actual->refresh()->is_default);
    }

    /** Escolher o que já é padrão não parte nada (é o caso mais comum). */
    public function test_escolher_o_que_ja_e_padrao_e_inofensivo(): void
    {
        $this->definicoes();
        $actual = $this->armazem('Armazém Central', true);

        Livewire::test(Settings::class)
            ->set('default_warehouse_id', $actual->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($actual->refresh()->is_default);
        $this->assertSame($actual->id, $this->definicoes()->refresh()->default_warehouse_id);
    }
}
