<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Services\Tenant\TenantModuleSyncService;
use Tests\TenantTestCase;

/**
 * Um teste de módulo que expirou NÃO pode bloquear quem depois paga.
 *
 * O Tenant::hasModule filtra por tenant_module.trial_ends_at. Como as
 * activações por direito de plano não limpavam essa data, um módulo que
 * tivesse estado em teste ficava barrado para sempre — o cliente comprava
 * o plano que o inclui, o pivô ficava activo, e continuava sem acesso.
 */
class TesteExpiradoNaoBloqueiaPlanoPagoTest extends TenantTestCase
{
    private function modulo(string $slug): Module
    {
        return Module::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'is_core' => false]);
    }

    public function test_o_modulo_com_teste_expirado_fica_barrado(): void
    {
        $mod = $this->modulo('restaurant');

        $this->tenant->modules()->syncWithoutDetaching([
            $mod->id => ['is_active' => true, 'trial_ends_at' => now()->subDay()],
        ]);

        $this->assertFalse($this->tenant->fresh()->hasModule('restaurant'),
            'o teste expirou — o módulo tem mesmo de ficar barrado');
    }

    public function test_activar_por_direito_de_plano_encerra_o_teste_e_devolve_o_acesso(): void
    {
        $mod = $this->modulo('restaurant');

        // Estado envenenado: teste expirado.
        $this->tenant->modules()->syncWithoutDetaching([
            $mod->id => ['is_active' => true, 'trial_ends_at' => now()->subDay()],
        ]);
        $this->assertFalse($this->tenant->fresh()->hasModule('restaurant'));

        // O cliente compra um plano que inclui o módulo.
        (new TenantModuleSyncService())->activateModule($this->tenant, 'restaurant');

        $tenant = $this->tenant->fresh();

        $this->assertNull(
            $tenant->modules()->where('modules.slug', 'restaurant')->first()->pivot->trial_ends_at,
            'activar por direito de plano tem de encerrar o teste'
        );
        $this->assertTrue($tenant->hasModule('restaurant'),
            'quem paga o plano tem de ter o módulo');
    }
}
