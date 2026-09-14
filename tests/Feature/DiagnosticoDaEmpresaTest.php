<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

class DiagnosticoDaEmpresaTest extends TenantTestCase
{
    /** Corre de ponta a ponta numa empresa e não escreve nada. @test */
    public function corre_e_so_le(): void
    {
        $antes = [\App\Models\Invoicing\Tax::withoutGlobalScopes()->count(), \Spatie\Permission\Models\Role::count(), \App\Models\Invoicing\Warehouse::withoutGlobalScopes()->count()];

        $this->artisan('empresa:diagnostico', ['--tenant' => $this->tenant->id])
            ->expectsOutputToContain('EMPRESA #' . $this->tenant->id)
            ->expectsOutputToContain('Facturação')
            ->assertSuccessful();

        $this->assertSame($antes, [\App\Models\Invoicing\Tax::withoutGlobalScopes()->count(), \Spatie\Permission\Models\Role::count(), \App\Models\Invoicing\Warehouse::withoutGlobalScopes()->count()]);
    }
}
