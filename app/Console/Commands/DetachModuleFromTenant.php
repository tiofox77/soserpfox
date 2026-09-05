<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Desliga um módulo de um tenant (is_active=false no pivot tenant_module).
 *
 * O acesso a um módulo passa pelo `Tenant::hasModule()`, que exige a linha do
 * pivot com is_active=true. Desligar aqui tira o acesso — mas NÃO apaga a linha
 * (guarda o preço acordado e é reversível com `module:attach`), e não toca nos
 * DADOS do módulo. É o mesmo que o painel de superadmin faz por empresa.
 *
 * A SECO por omissão: sem --aplicar, só mostra os módulos activos e o que faria.
 */
class DetachModuleFromTenant extends Command
{
    protected $signature = 'module:detach {module_slug} {tenant_id} {--aplicar : desliga de facto}';

    protected $description = 'Desliga um módulo de um tenant (is_active=false no pivot; reversível)';

    public function handle(): int
    {
        $slug = $this->argument('module_slug');
        $tenantId = (int) $this->argument('tenant_id');

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant #{$tenantId} não encontrado.");

            return self::FAILURE;
        }

        $module = Module::where('slug', $slug)->first();
        if (! $module) {
            $this->error("Módulo '{$slug}' não existe.");

            return self::FAILURE;
        }

        $this->line("Tenant: #{$tenant->id}  {$tenant->name}");
        $this->line("Módulo alvo: {$module->name} ({$slug})");
        $this->mostrarModulos($tenant);

        $pivot = DB::table('tenant_module')
            ->where('tenant_id', $tenantId)->where('module_id', $module->id)->first();

        if (! $pivot) {
            $this->comment('O módulo já não está ligado a esta empresa — nada a fazer.');

            return self::SUCCESS;
        }
        if (! $pivot->is_active) {
            $this->comment('O módulo já está DESLIGADO — nada a fazer.');

            return self::SUCCESS;
        }

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->comment("A SECO. Corra com --aplicar para desligar '{$slug}' de #{$tenantId}.");

            return self::SUCCESS;
        }

        DB::table('tenant_module')
            ->where('tenant_id', $tenantId)->where('module_id', $module->id)
            ->update(['is_active' => false, 'updated_at' => now()]);

        $this->newLine();
        $this->info("Desligado '{$slug}' de #{$tenantId}  {$tenant->name}.");
        $tenant->load('modules');
        $this->mostrarModulos($tenant->fresh());

        return self::SUCCESS;
    }

    private function mostrarModulos(Tenant $tenant): void
    {
        $activos = DB::table('tenant_module as tm')
            ->join('modules as m', 'm.id', '=', 'tm.module_id')
            ->where('tm.tenant_id', $tenant->id)->where('tm.is_active', true)
            ->orderBy('m.slug')->pluck('m.slug')->all();

        $this->line('Módulos ACTIVOS agora: '.(implode(', ', $activos) ?: '—'));
    }
}
