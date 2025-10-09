<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'plan_id',
        'amount',
        'billing_cycle',
        'payment_method',
        'payment_reference',
        'payment_proof',
        'status',
        'notes',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Aprovar pedido e ativar/sincronizar plano e módulos
     */
    public function approve($approvedBy = null)
    {
        try {
            \DB::beginTransaction();

            // Atualizar status do pedido
            $this->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $approvedBy ?? auth()->id(),
            ]);

            $tenant = $this->tenant;
            $newPlan = $this->plan;

            if (!$tenant || !$newPlan) {
                throw new \Exception('Tenant ou Plano não encontrado');
            }

            // Buscar plano atual do tenant (subscription ativa)
            $currentSubscription = $tenant->subscriptions()
                ->where('status', 'active')
                ->with('plan.modules')
                ->first();

            $oldPlan = $currentSubscription ? $currentSubscription->plan : null;

            // 1. DESATIVAR SUBSCRIPTION ANTIGA (se existir)
            if ($currentSubscription) {
                $currentSubscription->update([
                    'status' => 'cancelled',
                    'ends_at' => now(),
                ]);

                \Log::info("Subscription antiga cancelada", [
                    'tenant_id' => $tenant->id,
                    'old_plan' => $oldPlan->name ?? 'N/A',
                ]);
            }

            // 2. ATIVAR NOVA SUBSCRIPTION
            $startDate = now();
            $endDate = match($this->billing_cycle) {
                'yearly' => $startDate->copy()->addMonths(14), // 12 + 2 grátis
                'semiannual' => $startDate->copy()->addMonths(6),
                'quarterly' => $startDate->copy()->addMonths(3),
                default => $startDate->copy()->addMonth(),
            };

            $newSubscription = $tenant->subscriptions()->create([
                'plan_id' => $newPlan->id,
                'status' => 'active',
                'billing_cycle' => $this->billing_cycle ?? 'monthly',
                'amount' => $this->amount,
                'current_period_start' => $startDate,
                'current_period_end' => $endDate,
                'ends_at' => $endDate,
            ]);

            \Log::info("Nova subscription criada", [
                'tenant_id' => $tenant->id,
                'new_plan' => $newPlan->name,
                'period' => "{$startDate->format('Y-m-d')} até {$endDate->format('Y-m-d')}",
            ]);

            // 3. SINCRONIZAR MÓDULOS
            $this->syncModules($tenant, $oldPlan, $newPlan);

            \DB::commit();

            \Log::info("Pedido aprovado com sucesso", [
                'order_id' => $this->id,
                'tenant_id' => $tenant->id,
                'new_plan' => $newPlan->name,
            ]);

            return true;

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Erro ao aprovar pedido", [
                'order_id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Rejeitar pedido e enviar notificação
     */
    public function reject($reason = null, $rejectedBy = null)
    {
        try {
            \Log::info("Rejeitando pedido", [
                'order_id' => $this->id,
                'reason' => $reason,
                'rejected_by' => $rejectedBy ?? auth()->id(),
            ]);
            
            // Atualizar status do pedido
            $this->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'rejected_at' => now(),
                'rejected_by' => $rejectedBy ?? auth()->id(),
            ]);
            
            \Log::info("✅ Pedido rejeitado com sucesso", [
                'order_id' => $this->id,
                'tenant_id' => $this->tenant_id,
            ]);
            
            // Observer vai disparar o envio do email automaticamente
            
            return true;
            
        } catch (\Exception $e) {
            \Log::error("❌ Erro ao rejeitar pedido", [
                'order_id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Sincronizar módulos do tenant baseado no plano novo
     */
    protected function syncModules($tenant, $oldPlan, $newPlan)
    {
        // Buscar módulos do novo plano
        $newPlanModuleIds = $newPlan->modules()->pluck('modules.id')->toArray();
        
        // Buscar módulos do plano antigo (se existir)
        $oldPlanModuleIds = $oldPlan ? $oldPlan->modules()->pluck('modules.id')->toArray() : [];

        \Log::info("Sincronizando módulos", [
            'tenant_id' => $tenant->id,
            'old_modules' => $oldPlanModuleIds,
            'new_modules' => $newPlanModuleIds,
        ]);

        // UPGRADE: Módulos que estão no novo mas não estavam no antigo
        $modulesToActivate = array_diff($newPlanModuleIds, $oldPlanModuleIds);

        // DOWNGRADE: Módulos que estavam no antigo mas não estão no novo
        $modulesToDeactivate = array_diff($oldPlanModuleIds, $newPlanModuleIds);

        // MANTER: Módulos que estão em ambos
        $modulesToKeep = array_intersect($oldPlanModuleIds, $newPlanModuleIds);

        // 1. DESATIVAR módulos que não estão no novo plano
        if (!empty($modulesToDeactivate)) {
            foreach ($modulesToDeactivate as $moduleId) {
                $tenant->modules()->updateExistingPivot($moduleId, [
                    'is_active' => false,
                    'deactivated_at' => now(),
                ]);
            }
            \Log::info("Módulos desativados (downgrade)", [
                'tenant_id' => $tenant->id,
                'modules' => $modulesToDeactivate,
            ]);
        }

        // 2. ATIVAR novos módulos
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
            
            \Log::info("Módulos ativados (upgrade)", [
                'tenant_id' => $tenant->id,
                'modules' => $modulesToActivate,
            ]);
        }

        // 3. MANTER módulos existentes ativos
        if (!empty($modulesToKeep)) {
            foreach ($modulesToKeep as $moduleId) {
                $tenant->modules()->updateExistingPivot($moduleId, [
                    'is_active' => true,
                ]);
            }
            \Log::info("Módulos mantidos ativos", [
                'tenant_id' => $tenant->id,
                'modules' => $modulesToKeep,
            ]);
        }

        // 4. REMOVER COMPLETAMENTE módulos que não estão no novo plano e não devem ser mantidos
        // (opcional - se quiser remover da pivot table completamente)
        // $tenant->modules()->detach($modulesToDeactivate);
    }
}
