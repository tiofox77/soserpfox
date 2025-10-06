<?php

namespace App\Observers;

use App\Models\Plan;
use Illuminate\Support\Facades\Log;

class PlanObserver
{
    /**
     * Quando módulos são atualizados no plano, sincronizar com todos os tenants
     */
    public function updated(Plan $plan)
    {
        // Verificar se os módulos foram alterados
        if ($plan->wasChanged('updated_at')) {
            $this->syncModulesToTenants($plan);
        }
    }

    /**
     * Sincronizar módulos do plano com todos os tenants que têm subscription ativa
     */
    protected function syncModulesToTenants(Plan $plan)
    {
        try {
            // Pegar IDs dos módulos vinculados ao plano
            $moduleIds = $plan->modules()->pluck('modules.id')->toArray();

            if (empty($moduleIds)) {
                Log::info("Plano {$plan->name} não tem módulos vinculados.");
                return;
            }

            // Buscar todos os tenants com subscription ativa deste plano
            $tenants = $plan->subscriptions()
                ->where('status', 'active')
                ->with('tenant')
                ->get()
                ->pluck('tenant')
                ->filter(); // Remove nulls

            if ($tenants->isEmpty()) {
                Log::info("Plano {$plan->name} não tem tenants com subscription ativa.");
                return;
            }

            $syncedCount = 0;

            // Para cada tenant, vincular os módulos do plano
            foreach ($tenants as $tenant) {
                if (!$tenant) continue;

                // Preparar dados para sincronização
                $modulesToSync = [];
                foreach ($moduleIds as $moduleId) {
                    $modulesToSync[$moduleId] = [
                        'is_active' => true,
                        'activated_at' => now(),
                    ];
                }

                // Sincronizar sem remover módulos existentes
                $tenant->modules()->syncWithoutDetaching($modulesToSync);
                $syncedCount++;

                Log::info("Módulos do plano '{$plan->name}' sincronizados com tenant '{$tenant->name}' (ID: {$tenant->id})");
            }

            Log::info("Total de {$syncedCount} tenant(s) sincronizados com os módulos do plano '{$plan->name}'");

        } catch (\Exception $e) {
            Log::error("Erro ao sincronizar módulos do plano {$plan->name}: " . $e->getMessage());
        }
    }
}
