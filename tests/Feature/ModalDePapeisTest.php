<?php

namespace Tests\Feature;

use App\Livewire\Users\RolesAndPermissions;
use App\Models\Module;
use App\Support\CatalogoDePermissoes;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * O modal do papel: por módulo, em português, só o que a empresa tem.
 *
 * Mostrava 340 nomes técnicos em maiúsculas a todas as empresas, com
 * «Selecionar Todas» a marcar módulos que a empresa nem tinha. Agora:
 * núcleo + módulos activos, rótulo em português, contagens por módulo, e
 * atalhos («todo o módulo», «só consulta», «copiar de outro papel»).
 */
class ModalDePapeisTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['invoicing.sales.invoices.view', 'invoicing.sales.invoices.create', 'invoicing.sales.invoices.edit',
            'invoicing.sales.invoices.delete', 'hotel.rooms.view', 'hotel.rooms.edit', 'users.view', 'customers.view'] as $n) {
            Permission::findOrCreate($n, 'web');
        }

        $this->comPermissoes('users.roles.manage');
    }

    private function activar(string $slug): void
    {
        $modulo = Module::firstOrCreate(['slug' => $slug], [
            'name' => ucfirst($slug), 'is_active' => true, 'is_core' => false, 'order' => 1,
        ]);

        $this->tenant->modules()->syncWithoutDetaching([$modulo->id => ['is_active' => true]]);
    }

    private function ecra()
    {
        return Livewire::test(RolesAndPermissions::class)->call('openRoleModal');
    }

    /** @test */
    public function so_aparecem_o_nucleo_e_os_modulos_activos(): void
    {
        $this->activar('invoicing');

        $grupos = $this->ecra()->instance()->grupos;

        $this->assertArrayHasKey('invoicing', $grupos);
        $this->assertArrayHasKey('users', $grupos);
        $this->assertArrayNotHasKey('hotel', $grupos, 'o hotel não está activo nesta empresa');
    }

    /** @test */
    public function os_rotulos_vem_em_portugues(): void
    {
        $this->activar('invoicing');

        $this->ecra()
            ->call('escolherModulo', 'invoicing')
            ->assertSee('Ver Faturas de Venda')
            ->assertSee('Faturação');
    }

    /** «Tudo» marca o que a empresa vê — não o sistema inteiro. */
    public function test_marcar_tudo_nao_apanha_modulos_que_a_empresa_nao_tem(): void
    {
        $this->activar('invoicing');

        $ids = $this->ecra()->call('selectAllPermissions')->get('selectedPermissions');

        $hotel = Permission::where('name', 'hotel.rooms.view')->value('id');
        $facturas = Permission::where('name', 'invoicing.sales.invoices.view')->value('id');

        $this->assertContains((int) $facturas, array_map('intval', $ids));
        $this->assertNotContains((int) $hotel, array_map('intval', $ids));
    }

    /** «Só consulta» deixa o ver e tira o resto. */
    public function test_so_consulta_deixa_apenas_as_de_ver(): void
    {
        $this->activar('invoicing');

        $ids = $this->ecra()
            ->call('toggleModulo', 'invoicing')
            ->call('soLeituraDoModulo', 'invoicing')
            ->get('selectedPermissions');

        $nomes = Permission::whereIn('id', $ids)->pluck('name')->all();

        $this->assertContains('invoicing.sales.invoices.view', $nomes);
        $this->assertNotContains('invoicing.sales.invoices.delete', $nomes);
        $this->assertNotContains('invoicing.sales.invoices.create', $nomes);
    }

    /** Copiar de outro papel traz-lhe as permissões. */
    public function test_copiar_de_outro_papel(): void
    {
        $this->activar('invoicing');

        setPermissionsTeamId($this->tenant->id);
        $origem = Role::create(['name' => 'Origem '.uniqid(), 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $origem->givePermissionTo(['invoicing.sales.invoices.view', 'customers.view']);

        $ids = $this->ecra()
            ->set('copiarDe', $origem->id)
            ->call('copiarDePapel')
            ->get('selectedPermissions');

        $this->assertEqualsCanonicalizing(
            $origem->permissions->pluck('id')->map(fn ($i) => (int) $i)->all(),
            array_map('intval', $ids)
        );
    }

    /** Guardar grava exactamente o que ficou marcado. */
    public function test_guardar_grava_o_que_ficou_marcado(): void
    {
        $this->activar('invoicing');
        $ver = Permission::where('name', 'invoicing.sales.invoices.view')->first();

        $this->ecra()
            ->set('roleName', 'Balcão '.uniqid())
            ->set('selectedPermissions', [(string) $ver->id])
            ->call('saveRole')
            ->assertHasNoErrors()
            ->assertSet('showRoleModal', false);

        $papel = Role::where('tenant_id', $this->tenant->id)->where('name', 'like', 'Balcão %')->first();

        $this->assertNotNull($papel);
        $this->assertTrue($papel->hasPermissionTo('invoicing.sales.invoices.view'));
        $this->assertFalse($papel->hasPermissionTo('invoicing.sales.invoices.delete'));
    }

    /** O catálogo nunca devolve um nome cru. */
    public function test_o_catalogo_le_os_nomes_em_portugues(): void
    {
        $this->assertSame('Ver Faturas de Venda', CatalogoDePermissoes::humanizar('invoicing.sales.invoices.view'));
        $this->assertSame('Editar Quartos', CatalogoDePermissoes::humanizar('hotel.rooms.edit'));
        $this->assertSame('Ver Clientes', CatalogoDePermissoes::humanizar('customers.view'));
        $this->assertSame('Gerir Utilizadores', CatalogoDePermissoes::humanizar('users.manage'));
        $this->assertSame('Ver Painel', CatalogoDePermissoes::humanizar('hotel.dashboard.view'));
        $this->assertSame('Faturas de Venda', CatalogoDePermissoes::entidade('invoicing.sales.invoices.view'));
        $this->assertNull(CatalogoDePermissoes::grupoDe('invoices.view'), 'lixo antigo não pertence a módulo nenhum');
        $this->assertSame('invoicing', CatalogoDePermissoes::grupoDe('customers.view'));
    }

    /** O comando de descrição só escreve onde está vazio. */
    public function test_descrever_preenche_so_as_vazias(): void
    {
        $vazia = Permission::findOrCreate('hotel.rooms.edit', 'web');
        $vazia->update(['description' => null]);
        $cheia = Permission::findOrCreate('users.view', 'web');
        $cheia->update(['description' => 'Escrita à mão']);

        $this->artisan('permissoes:descrever')->assertExitCode(0);
        $this->assertNull($vazia->fresh()->description, 'a seco não escreve');

        $this->artisan('permissoes:descrever', ['--aplicar' => true])->assertExitCode(0);

        $this->assertSame('Editar Quartos', $vazia->fresh()->description);
        $this->assertSame('Escrita à mão', $cheia->fresh()->description, 'nunca por cima de uma escrita à mão');
    }

    /** A permissão que ninguém tinha: a cópia offline passa a exigir a de vender no POS. */
    public function test_a_copia_offline_exige_a_permissao_de_vender(): void
    {
        $rotas = file_get_contents(base_path('routes/web.php'));

        $this->assertStringNotContainsString('invoicing.pos.create', $rotas);
        $this->assertMatchesRegularExpression('/permission:invoicing\.pos\.sell.*importar-copia-offline/s', $rotas);
    }
}
