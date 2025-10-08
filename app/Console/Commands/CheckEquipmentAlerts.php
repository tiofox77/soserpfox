<?php

namespace App\Console\Commands;

use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Mail\EquipmentOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckEquipmentAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'equipment:check-alerts {--tenant= : ID do tenant específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica equipamentos atrasados e manutenções pendentes, enviando alertas por email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando alertas de equipamentos...');

        $tenants = $this->option('tenant') 
            ? Tenant::where('id', $this->option('tenant'))->get()
            : Tenant::where('is_active', true)->get();

        $totalAlerts = 0;

        foreach ($tenants as $tenant) {
            $this->info("Verificando tenant: {$tenant->name}");

            // Equipamentos atrasados
            $overdueEquipment = Equipment::where('tenant_id', $tenant->id)
                ->overdue()
                ->with('borrowedToClient')
                ->get();

            // Manutenções pendentes (próximos 7 dias)
            $maintenanceDue = Equipment::where('tenant_id', $tenant->id)
                ->needsMaintenance()
                ->get();

            if ($overdueEquipment->count() > 0 || $maintenanceDue->count() > 0) {
                $this->warn("  ⚠️  {$overdueEquipment->count()} atrasados | {$maintenanceDue->count()} manutenções");
                
                // Buscar usuários para notificar
                $usersToNotify = User::where('tenant_id', $tenant->id)
                    ->whereHas('roles', function($query) {
                        $query->whereIn('name', ['Super Admin', 'Admin', 'Manager']);
                    })
                    ->get();

                foreach ($usersToNotify as $user) {
                    try {
                        Mail::to($user->email)->send(
                            new EquipmentOverdueNotification($overdueEquipment, $maintenanceDue)
                        );
                        $this->info("  ✅ Email enviado para: {$user->email}");
                    } catch (\Exception $e) {
                        $this->error("  ❌ Erro ao enviar para {$user->email}: {$e->getMessage()}");
                    }
                }

                $totalAlerts += ($overdueEquipment->count() + $maintenanceDue->count());
            } else {
                $this->info("  ✅ Nenhum alerta");
            }
        }

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("✅ Verificação concluída! Total de alertas: {$totalAlerts}");

        return Command::SUCCESS;
    }
}
