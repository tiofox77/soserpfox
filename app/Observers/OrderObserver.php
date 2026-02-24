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
        
        // Verificar se o status mudou para 'rejected'
        if ($order->wasChanged('status') && $order->status === 'rejected') {
            \Log::info("❌ OrderObserver: Pedido rejeitado, enviando notificação", [
                'order_id' => $order->id,
                'old_status' => $order->getOriginal('status'),
                'new_status' => $order->status,
            ]);
            
            try {
                $this->processRejection($order);
            } catch (\Exception $e) {
                \Log::error("❌ OrderObserver: Erro ao processar rejeição", [
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

            // 2. ATIVAR SUBSCRIPTION PENDENTE (ou criar nova se não existir)
            // Buscar subscription pendente do mesmo plano e tenant
            $pendingSubscription = $tenant->subscriptions()
                ->where('plan_id', $newPlan->id)
                ->where('status', 'pending')
                ->latest()
                ->first();
            
            $startDate = now();
            $endDate = match($order->billing_cycle) {
                'yearly' => $startDate->copy()->addMonths(14), // 12 + 2 grátis
                'semiannual' => $startDate->copy()->addMonths(6),
                'quarterly' => $startDate->copy()->addMonths(3),
                default => $startDate->copy()->addMonth(),
            };
            
            if ($pendingSubscription) {
                // ATIVAR a subscription pendente existente
                $pendingSubscription->update([
                    'status' => 'active',
                    'current_period_start' => $startDate,
                    'current_period_end' => $endDate,
                    'ends_at' => $endDate,
                ]);
                
                $newSubscription = $pendingSubscription;
                
                \Log::info("✅ Subscription pendente ATIVADA", [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $newSubscription->id,
                    'new_plan' => $newPlan->name,
                    'period' => "{$startDate->format('Y-m-d')} até {$endDate->format('Y-m-d')}",
                ]);
            } else {
                // CRIAR nova subscription (caso não exista pendente)
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
            }

            // 3. SINCRONIZAR MÓDULOS no tenant do pedido
            $this->syncModules($tenant, $oldPlan, $newPlan);

            // 4. BUG-01 FIX: PROPAGAR subscription para TODOS os outros tenants do user
            $this->propagateToUserTenants($order->user, $tenant, $newSubscription, $newPlan, $oldPlan);

            // 5. Verificar se é upgrade/downgrade e enviar notificação apropriada
            if ($oldPlan && $oldPlan->id !== $newPlan->id) {
                $this->sendPlanUpdateNotification($order, $tenant, $oldPlan, $newPlan);
            }

            // 6. Atualizar campos de aprovação no pedido (se não foram definidos)
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
            
            // 7. ENVIAR NOTIFICAÇÃO DE PAGAMENTO APROVADO (se for primeiro pagamento)
            if (!$oldPlan) {
                $this->sendApprovalNotification($order, $tenant, $newPlan, $newSubscription);
            }

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
    
    /**
     * BUG-01 FIX: Propagar subscription para TODOS os outros tenants do user
     */
    protected function propagateToUserTenants($user, $sourceTenant, $sourceSubscription, $newPlan, $oldPlan): void
    {
        if (!$user) return;
        
        $otherTenants = $user->tenants()->where('tenants.id', '!=', $sourceTenant->id)->get();
        
        if ($otherTenants->isEmpty()) return;
        
        $maxCompanies = $newPlan->max_companies ?? 1;
        $propagatedCount = 0;
        $maxToPropagate = max(0, $maxCompanies - 1); // source tenant já conta como 1
        
        foreach ($otherTenants as $otherTenant) {
            // Respeitar limite: source tenant + propagated <= maxCompanies
            if ($propagatedCount >= $maxToPropagate) {
                \Log::info("Limite de empresas atingido, não propagar mais", [
                    'max_companies' => $maxCompanies,
                    'max_to_propagate' => $maxToPropagate,
                    'propagated' => $propagatedCount,
                ]);
                break;
            }
            
            // Cancelar subscription antiga do outro tenant (se existir)
            $otherTenant->subscriptions()
                ->whereIn('status', ['active', 'trial'])
                ->each(function ($sub) {
                    $sub->update(['status' => 'cancelled', 'ends_at' => now()]);
                });
            
            // Remover subscriptions pendentes
            $otherTenant->subscriptions()
                ->where('status', 'pending')
                ->delete();
            
            // Criar subscription clone com MESMAS datas
            $otherTenant->subscriptions()->create([
                'plan_id'              => $sourceSubscription->plan_id,
                'status'               => $sourceSubscription->status,
                'billing_cycle'        => $sourceSubscription->billing_cycle,
                'amount'               => $sourceSubscription->amount,
                'current_period_start' => $sourceSubscription->current_period_start,
                'current_period_end'   => $sourceSubscription->current_period_end,
                'ends_at'              => $sourceSubscription->ends_at,
                'trial_ends_at'        => $sourceSubscription->trial_ends_at,
            ]);
            
            // Sincronizar módulos no outro tenant
            $this->syncModules($otherTenant, $oldPlan, $newPlan);
            
            $propagatedCount++;
            
            \Log::info("✅ Subscription propagada para tenant", [
                'source_tenant' => $sourceTenant->id,
                'target_tenant' => $otherTenant->id,
                'target_name' => $otherTenant->name,
                'plan' => $newPlan->name,
            ]);
        }
        
        if ($propagatedCount > 0) {
            \Log::info("📦 Subscription propagada para {$propagatedCount} tenant(s) adicionais");
        }
    }
    
    /**
     * Enviar notificação de atualização de plano (upgrade/downgrade)
     */
    protected function sendPlanUpdateNotification($order, $tenant, $oldPlan, $newPlan): void
    {
        try {
            \Log::info("📧 Enviando notificação de atualização de plano", [
                'order_id' => $order->id,
                'tenant_id' => $tenant->id,
                'old_plan' => $oldPlan->name,
                'new_plan' => $newPlan->name,
            ]);
            
            $user = $order->user;
            if (!$user || !$user->email) {
                \Log::warning("❌ Usuário não encontrado ou sem email", ['order_id' => $order->id]);
                return;
            }
            
            // BUSCAR CONFIGURAÇÃO SMTP DO BANCO (igual ao wizard)
            $smtpSetting = \App\Models\SmtpSetting::getForTenant(null);
            
            if (!$smtpSetting) {
                \Log::error('❌ Configuração SMTP não encontrada no banco');
                return;
            }
            
            \Log::info('📧 Configuração SMTP encontrada', [
                'host' => $smtpSetting->host,
                'port' => $smtpSetting->port,
            ]);
            
            // CONFIGURAR SMTP
            $smtpSetting->configure();
            \Log::info('✅ SMTP configurado do banco de dados');
            
            // BUSCAR TEMPLATE DO BANCO
            $template = \App\Models\EmailTemplate::where('slug', 'plan_updated')->first();
            
            if (!$template) {
                \Log::error('❌ Template plan_updated não encontrado');
                return;
            }
            
            \Log::info('📄 Template plan_updated encontrado', [
                'id' => $template->id,
                'subject' => $template->subject,
            ]);
            
            // Determinar se é upgrade ou downgrade
            $isUpgrade = $newPlan->price_monthly > $oldPlan->price_monthly;
            $changeType = $isUpgrade ? 'upgrade' : 'downgrade';
            
            // Dados para o template
            $data = [
                'user_name' => $user->name,
                'tenant_name' => $tenant->name,
                'old_plan_name' => $oldPlan->name,
                'new_plan_name' => $newPlan->name,
                'change_type' => $changeType,
                'app_name' => config('app.name', 'SOS ERP'),
                'app_url' => config('app.url'),
                'support_email' => $smtpSetting->from_email,
                'login_url' => route('login'),
            ];
            
            // Renderizar template do BD
            $rendered = $template->render($data);
            
            \Log::info('📧 Template renderizado', [
                'to' => $user->email,
                'subject' => $rendered['subject'],
            ]);
            
            // Enviar email usando HTML DO TEMPLATE
            \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($user, $rendered) {
                $message->to($user->email, $user->name)
                        ->subject($rendered['subject'])
                        ->html($rendered['body_html']);
            });
            
            \Log::info("✅ Email de atualização de plano enviado com sucesso!", [
                'destinatario' => $user->email,
                'template' => 'plan_updated',
                'tipo' => $changeType,
            ]);
            
        } catch (\Exception $emailError) {
            \Log::error("❌ Erro ao enviar email de atualização de plano", [
                'error_message' => $emailError->getMessage(),
                'error_file' => $emailError->getFile(),
                'error_line' => $emailError->getLine(),
                'order_id' => $order->id,
                'tenant_id' => $tenant->id,
                'trace' => $emailError->getTraceAsString(),
            ]);
        }
    }
    
    /**
     * Enviar notificação de aprovação ao cliente
     */
    protected function sendApprovalNotification($order, $tenant, $plan, $subscription): void
    {
        try {
            \Log::info("📧 Iniciando envio de notificação de aprovação", [
                'order_id' => $order->id,
                'tenant_id' => $tenant->id,
                'user_id' => $order->user_id,
            ]);
            
            $user = $order->user;
            if (!$user || !$user->email) {
                \Log::warning("❌ Usuário não encontrado ou sem email", ['order_id' => $order->id]);
                return;
            }
            
            // BUSCAR CONFIGURAÇÃO SMTP DO BANCO (igual ao wizard)
            $smtpSetting = \App\Models\SmtpSetting::getForTenant(null);
            
            if (!$smtpSetting) {
                \Log::error('❌ Configuração SMTP não encontrada no banco');
                return;
            }
            
            \Log::info('📧 Configuração SMTP encontrada', [
                'host' => $smtpSetting->host,
                'port' => $smtpSetting->port,
            ]);
            
            // CONFIGURAR SMTP
            $smtpSetting->configure();
            \Log::info('✅ SMTP configurado do banco de dados');
            
            // BUSCAR TEMPLATE DO BANCO
            $template = \App\Models\EmailTemplate::where('slug', 'payment_approved')->first();
            
            if (!$template) {
                \Log::error('❌ Template payment_approved não encontrado');
                return;
            }
            
            \Log::info('📄 Template payment_approved encontrado', [
                'id' => $template->id,
                'subject' => $template->subject,
            ]);
            
            // Dados para o template
            $data = [
                'user_name' => $user->name,
                'tenant_name' => $tenant->name,
                'plan_name' => $plan->name,
                'amount' => number_format($order->amount, 2, ',', '.'),
                'billing_cycle' => $order->billing_cycle ?? 'monthly',
                'period_start' => $subscription->current_period_start->format('d/m/Y'),
                'period_end' => $subscription->current_period_end->format('d/m/Y'),
                'app_name' => config('app.name', 'SOS ERP'),
                'app_url' => config('app.url'),
                'support_email' => $smtpSetting->from_email,
                'login_url' => route('login'),
            ];
            
            // Renderizar template do BD
            $rendered = $template->render($data);
            
            \Log::info('📧 Template renderizado', [
                'to' => $user->email,
                'subject' => $rendered['subject'],
            ]);
            
            // Enviar email usando HTML DO TEMPLATE
            \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($user, $rendered) {
                $message->to($user->email, $user->name)
                        ->subject($rendered['subject'])
                        ->html($rendered['body_html']);
            });
            
            \Log::info("✅ Email de aprovação enviado com sucesso!", [
                'destinatario' => $user->email,
                'template' => 'payment_approved',
            ]);
            
        } catch (\Exception $emailError) {
            \Log::error("❌ Erro ao enviar email de aprovação", [
                'error_message' => $emailError->getMessage(),
                'error_file' => $emailError->getFile(),
                'error_line' => $emailError->getLine(),
                'order_id' => $order->id,
                'tenant_id' => $tenant->id,
                'trace' => $emailError->getTraceAsString(),
            ]);
            // Não falha o processo de aprovação se o email falhar
        }
    }
    
    /**
     * Processar rejeição: enviar notificação ao cliente
     */
    protected function processRejection(Order $order): void
    {
        $tenant = $order->tenant;
        $plan = $order->plan;
        
        if (!$tenant || !$plan) {
            \Log::error("❌ Tenant ou Plano não encontrado para rejeição", ['order_id' => $order->id]);
            return;
        }
        
        // REMOVER subscription pendente relacionada ao pedido rejeitado
        $pendingSubscription = $tenant->subscriptions()
            ->where('plan_id', $plan->id)
            ->where('status', 'pending')
            ->latest()
            ->first();
        
        if ($pendingSubscription) {
            $pendingSubscription->delete();
            \Log::info("🗑️ Subscription pendente removida após rejeição", [
                'subscription_id' => $pendingSubscription->id,
                'tenant_id' => $tenant->id,
                'plan' => $plan->name,
            ]);
        }
        
        $user = $order->user;
        if (!$user || !$user->email) {
            \Log::warning("❌ Usuário não encontrado ou sem email", ['order_id' => $order->id]);
            return;
        }
        
        try {
            \Log::info("📧 Iniciando envio de notificação de rejeição", [
                'order_id' => $order->id,
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);
            
            // BUSCAR CONFIGURAÇÃO SMTP DO BANCO (igual ao wizard)
            $smtpSetting = \App\Models\SmtpSetting::getForTenant(null);
            
            if (!$smtpSetting) {
                \Log::error('❌ Configuração SMTP não encontrada no banco');
                return;
            }
            
            \Log::info('📧 Configuração SMTP encontrada', [
                'host' => $smtpSetting->host,
                'port' => $smtpSetting->port,
            ]);
            
            // CONFIGURAR SMTP
            $smtpSetting->configure();
            \Log::info('✅ SMTP configurado do banco de dados');
            
            // BUSCAR TEMPLATE DO BANCO
            $template = \App\Models\EmailTemplate::where('slug', 'plan_rejected')->first();
            
            if (!$template) {
                \Log::error('❌ Template plan_rejected não encontrado');
                return;
            }
            
            \Log::info('📄 Template plan_rejected encontrado', [
                'id' => $template->id,
                'subject' => $template->subject,
            ]);
            
            // Dados para o template
            $data = [
                'user_name' => $user->name,
                'tenant_name' => $tenant->name,
                'plan_name' => $plan->name,
                'amount' => number_format($order->amount, 2, ',', '.'),
                'reason' => $order->rejection_reason ?? 'Não especificado',
                'order_id' => $order->id,
                'app_name' => config('app.name', 'SOS ERP'),
                'app_url' => config('app.url'),
                'support_email' => $smtpSetting->from_email,
                'support_url' => route('support.tickets', [], false) ? route('support.tickets') : url('/support/tickets'),
                'billing_url' => url('/my-account'),
            ];
            
            // Renderizar template do BD
            $rendered = $template->render($data);
            
            \Log::info('📧 Template renderizado', [
                'to' => $user->email,
                'subject' => $rendered['subject'],
            ]);
            
            // Enviar email usando HTML DO TEMPLATE
            \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($user, $rendered) {
                $message->to($user->email, $user->name)
                        ->subject($rendered['subject'])
                        ->html($rendered['body_html']);
            });
            
            \Log::info("✅ Email de rejeição enviado com sucesso!", [
                'destinatario' => $user->email,
                'template' => 'plan_rejected',
                'order_id' => $order->id,
            ]);
            
        } catch (\Exception $emailError) {
            \Log::error("❌ Erro ao enviar email de rejeição", [
                'error_message' => $emailError->getMessage(),
                'error_file' => $emailError->getFile(),
                'error_line' => $emailError->getLine(),
                'order_id' => $order->id,
                'tenant_id' => $tenant->id,
                'user_email' => $user->email,
                'trace' => $emailError->getTraceAsString(),
            ]);
            // Não falha o processo se o email falhar
        }
    }
}
