<?php

namespace App\Observers;

use App\Models\Order;

class OrderObserver
{
    /**
     * Handle the Order "updated" event.
     * Executa após o update ser salvo
     */
    public function updated(Order $order): void
    {
        // Verificar se o status mudou para 'approved'
        if ($order->wasChanged('status') && $order->status === 'approved') {
            \Log::info("✅ OrderObserver: Pedido aprovado, iniciando processamento", [
                'order_id' => $order->id,
                'old_status' => $order->getOriginal('status'),
                'new_status' => $order->status,
            ]);
            
            try {
                $this->processApproval($order);
            } catch (\Exception $e) {
                \Log::error("❌ OrderObserver: Erro ao processar aprovação", [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Processar a aprovação: ativar subscription e sincronizar módulos
     */
    protected function processApproval(Order $order): void
    {
        $tenant = $order->tenant;
        $newPlan = $order->plan;

        if (!$tenant || !$newPlan) {
            \Log::error("Tenant ou Plano não encontrado", ['order_id' => $order->id]);
            return;
        }

        \DB::beginTransaction();
        try {
            // Buscar subscription ativa atual
            $currentSubscription = $tenant->subscriptions()
                ->where('status', 'active')
                ->with('plan.modules')
                ->first();

            $oldPlan = $currentSubscription ? $currentSubscription->plan : null;

            // 1. CANCELAR SUBSCRIPTION ANTIGA
            if ($currentSubscription) {
                $currentSubscription->update([
                    'status' => 'cancelled',
                    'ends_at' => now(),
                ]);

                \Log::info("📦 Subscription antiga cancelada", [
                    'tenant_id' => $tenant->id,
                    'old_plan' => $oldPlan->name ?? 'N/A',
                ]);
            }

            // 2. CRIAR NOVA SUBSCRIPTION ATIVA
            $startDate = now();
            $endDate = match($order->billing_cycle) {
                'yearly' => $startDate->copy()->addMonths(14), // 12 + 2 grátis
                'semiannual' => $startDate->copy()->addMonths(6),
                'quarterly' => $startDate->copy()->addMonths(3),
                default => $startDate->copy()->addMonth(),
            };

            $newSubscription = $tenant->subscriptions()->create([
                'plan_id' => $newPlan->id,
                'status' => 'active',
                'billing_cycle' => $order->billing_cycle ?? 'monthly',
                'amount' => $order->amount,
                'current_period_start' => $startDate,
                'current_period_end' => $endDate,
                'ends_at' => $endDate,
            ]);

            \Log::info("🎉 Nova subscription criada e ativada", [
                'tenant_id' => $tenant->id,
                'subscription_id' => $newSubscription->id,
                'new_plan' => $newPlan->name,
                'period' => "{$startDate->format('Y-m-d')} até {$endDate->format('Y-m-d')}",
            ]);

            // 3. SINCRONIZAR MÓDULOS
            $this->syncModules($tenant, $oldPlan, $newPlan);

            // 4. Atualizar campos de aprovação no pedido (se não foram definidos)
            if (!$order->approved_at) {
                $order->approved_at = now();
            }
            if (!$order->approved_by) {
                $order->approved_by = auth()->id() ?? 1; // Sistema
            }
            $order->saveQuietly(); // Salvar sem disparar eventos

            \DB::commit();

            \Log::info("✅ Processamento de aprovação concluído com sucesso", [
                'order_id' => $order->id,
                'tenant_id' => $tenant->id,
                'new_plan' => $newPlan->name,
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("❌ Erro ao processar aprovação no Observer", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Sincronizar módulos baseado no upgrade/downgrade
     */
    protected function syncModules($tenant, $oldPlan, $newPlan): void
    {
        // Módulos do novo plano
        $newPlanModuleIds = $newPlan->modules()->pluck('modules.id')->toArray();
        
        // Módulos do plano antigo
        $oldPlanModuleIds = $oldPlan ? $oldPlan->modules()->pluck('modules.id')->toArray() : [];

        \Log::info("🔄 Sincronizando módulos", [
            'tenant_id' => $tenant->id,
            'old_plan' => $oldPlan->name ?? 'Nenhum',
            'new_plan' => $newPlan->name,
            'old_modules' => $oldPlanModuleIds,
            'new_modules' => $newPlanModuleIds,
        ]);

        // UPGRADE: Novos módulos a ativar
        $modulesToActivate = array_diff($newPlanModuleIds, $oldPlanModuleIds);

        // DOWNGRADE: Módulos a desativar
        $modulesToDeactivate = array_diff($oldPlanModuleIds, $newPlanModuleIds);

        // MANTER: Módulos em comum
        $modulesToKeep = array_intersect($oldPlanModuleIds, $newPlanModuleIds);

        // 1. DESATIVAR módulos removidos (downgrade)
        if (!empty($modulesToDeactivate)) {
            foreach ($modulesToDeactivate as $moduleId) {
                $tenant->modules()->updateExistingPivot($moduleId, [
                    'is_active' => false,
                    'deactivated_at' => now(),
                ]);
            }
            \Log::info("❌ Módulos desativados (downgrade)", [
                'tenant_id' => $tenant->id,
                'modules_ids' => $modulesToDeactivate,
            ]);
        }

        // 2. ATIVAR novos módulos (upgrade)
        if (!empty($modulesToActivate)) {
            $syncData = [];
            foreach ($modulesToActivate as $moduleId) {
                $syncData[$moduleId] = [
                    'is_active' => true,
                    'activated_at' => now(),
                    'deactivated_at' => null,
                ];
            }
            $tenant->modules()->syncWithoutDetaching($syncData);
            
            \Log::info("✅ Módulos ativados (upgrade)", [
                'tenant_id' => $tenant->id,
                'modules_ids' => $modulesToActivate,
            ]);
        }

        // 3. MANTER módulos existentes ativos
        if (!empty($modulesToKeep)) {
            foreach ($modulesToKeep as $moduleId) {
                $tenant->modules()->updateExistingPivot($moduleId, [
                    'is_active' => true,
                ]);
            }
            \Log::info("✔️ Módulos mantidos ativos", [
                'tenant_id' => $tenant->id,
                'modules_ids' => $modulesToKeep,
            ]);
        }
    }
}
