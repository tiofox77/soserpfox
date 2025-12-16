<?php

namespace App\Livewire\SuperAdmin;

use App\Models\{Invoice, Tenant, Plan, Subscription, Order};
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.superadmin')]
#[Title('Billing')]
class Billing extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $subscriptionSearch = '';
    public $subscriptionStatusFilter = '';
    public $showModal = false;
    public $editingInvoiceId = null;

    // Form fields
    public $tenant_id, $plan_id, $invoice_number, $description;
    public $invoice_date, $due_date;
    public $subtotal = 0, $tax = 0, $total = 0;
    public $status = 'pending';
    public $billing_cycle = 'monthly';
    public $selectedPlan = null;
    public $showSubscriptionModal = false;
    
    // Loading states
    public $approvingOrderId = null;
    public $rejectingOrderId = null;

    protected $rules = [
        'tenant_id' => 'required|exists:tenants,id',
        'invoice_number' => 'required|unique:invoices,invoice_number',
        'description' => 'required',
        'invoice_date' => 'required|date',
        'due_date' => 'required|date',
        'subtotal' => 'required|numeric|min:0',
        'tax' => 'required|numeric|min:0',
        'total' => 'required|numeric|min:0',
        'status' => 'required|in:pending,paid,overdue,cancelled',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function createSubscription()
    {
        $this->reset(['tenant_id', 'plan_id', 'billing_cycle', 'selectedPlan']);
        $this->showSubscriptionModal = true;
    }

    public function updatedTenantId($value)
    {
        // Verificar se tenant já tem subscrição ativa
        if ($value) {
            $existingSubscription = Subscription::where('tenant_id', $value)
                ->where('status', 'active')
                ->with('plan')
                ->first();
                
            if ($existingSubscription) {
                $this->dispatch('warning', message: "Este tenant já tem uma subscrição ativa ({$existingSubscription->plan->name}). Ao continuar, você irá renovar/atualizar a subscrição existente.");
            }
        }
    }

    public function updatedPlanId($value)
    {
        if ($value) {
            $this->selectedPlan = Plan::with('modules')->find($value);
        } else {
            $this->selectedPlan = null;
        }
    }

    public function saveSubscription()
    {
        $this->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $tenant = Tenant::findOrFail($this->tenant_id);
        $plan = Plan::findOrFail($this->plan_id);

        // Verificar se já existe subscrição ativa para este tenant
        $existingSubscription = Subscription::where('tenant_id', $this->tenant_id)
            ->where('status', 'active')
            ->first();

        if ($existingSubscription) {
            // Se já existe subscrição ativa, renovar/atualizar
            $amount = $this->billing_cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
            $periodStart = $existingSubscription->current_period_end ?? now();
            $periodEnd = $this->billing_cycle === 'yearly' ? $periodStart->copy()->addYear() : $periodStart->copy()->addMonth();
            
            $existingSubscription->update([
                'plan_id' => $this->plan_id,
                'billing_cycle' => $this->billing_cycle,
                'amount' => $amount,
                'current_period_end' => $periodEnd,
            ]);
            
            $subscription = $existingSubscription;
            $message = 'Subscrição renovada/atualizada com sucesso!';
        } else {
            // Criar nova subscription
            $amount = $this->billing_cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
            $periodEnd = $this->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth();
            
            $subscription = Subscription::create([
                'tenant_id' => $this->tenant_id,
                'plan_id' => $this->plan_id,
                'status' => 'active',
                'billing_cycle' => $this->billing_cycle,
                'amount' => $amount,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'trial_ends_at' => now()->addDays($plan->trial_days),
            ]);
            
            $message = 'Subscrição criada com sucesso!';
        }

        // Sincronizar módulos do plano para o tenant
        $moduleIds = $plan->modules->pluck('id')->toArray();
        
        // Remover módulos que não estão mais no plano
        $tenant->modules()->sync([]);
        
        // Adicionar módulos do novo plano
        foreach ($plan->modules as $module) {
            $tenant->modules()->attach($module->id, [
                'is_active' => true,
                'activated_at' => now(),
            ]);
        }

        $this->dispatch('success', message: $message . ' Módulos sincronizados.');
        $this->showSubscriptionModal = false;
        $this->reset(['tenant_id', 'plan_id', 'billing_cycle', 'selectedPlan']);
    }

    public function viewSubscription($id)
    {
        $subscription = Subscription::with(['tenant', 'plan.modules'])->findOrFail($id);
        $this->dispatch('info', message: "Subscrição #{$id} - {$subscription->tenant->name}");
    }

    public function cancelSubscription($id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->cancel();
        $this->dispatch('success', message: 'Subscrição cancelada com sucesso!');
    }

    public function deleteSubscription($id)
    {
        try {
            $subscription = Subscription::findOrFail($id);
            $tenant = $subscription->tenant;
            
            // Remover módulos do tenant
            foreach ($subscription->plan->modules as $module) {
                $tenant->modules()->detach($module->id);
            }
            
            $subscription->delete();
            $this->dispatch('success', message: 'Subscrição excluída com sucesso! Módulos desativados.');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir subscrição!');
        }
    }

    public function edit($id)
    {
        $invoice = Invoice::findOrFail($id);
        $this->editingInvoiceId = $id;
        $this->tenant_id = $invoice->tenant_id;
        $this->invoice_number = $invoice->invoice_number;
        $this->description = $invoice->description;
        $this->invoice_date = $invoice->invoice_date->format('Y-m-d');
        $this->due_date = $invoice->due_date->format('Y-m-d');
        $this->subtotal = $invoice->subtotal;
        $this->tax = $invoice->tax;
        $this->total = $invoice->total;
        $this->status = $invoice->status;
        $this->showModal = true;
    }

    public function save()
    {
        if ($this->editingInvoiceId) {
            $this->rules['invoice_number'] = 'required|unique:invoices,invoice_number,' . $this->editingInvoiceId;
        }

        $this->validate();

        $data = [
            'tenant_id' => $this->tenant_id,
            'invoice_number' => $this->invoice_number,
            'description' => $this->description,
            'invoice_date' => $this->invoice_date,
            'due_date' => $this->due_date,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'status' => $this->status,
        ];

        if ($this->editingInvoiceId) {
            Invoice::find($this->editingInvoiceId)->update($data);
            $this->dispatch('success', message: 'Fatura atualizada com sucesso!');
        } else {
            Invoice::create($data);
            $this->dispatch('success', message: 'Fatura criada com sucesso!');
        }

        $this->closeModal();
    }

    public function markAsPaid($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->update(['status' => 'paid']);
        $this->dispatch('success', message: 'Fatura marcada como paga!');
    }

    public function delete($id)
    {
        try {
            Invoice::findOrFail($id)->delete();
            $this->dispatch('success', message: 'Fatura excluída com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir fatura!');
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset(['tenant_id', 'invoice_number', 'description', 'invoice_date', 'due_date', 'editingInvoiceId']);
        $this->subtotal = 0;
        $this->tax = 0;
        $this->total = 0;
        $this->status = 'pending';
    }

    public function updatedSubtotal()
    {
        $this->calculateTotal();
    }

    public function updatedTax()
    {
        $this->calculateTotal();
    }

    private function calculateTotal()
    {
        $this->total = $this->subtotal + $this->tax;
    }

    public function render()
    {
        $invoices = Invoice::with('tenant')
            ->when($this->search, function ($query) {
                $query->where('invoice_number', 'like', '%' . $this->search . '%')
                    ->orWhereHas('tenant', function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%');
                    });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->latest()
            ->paginate(15);

        $totalRevenue = Invoice::where('status', 'paid')->sum('total');
        $pendingRevenue = Invoice::where('status', 'pending')->sum('total');
        $tenants = Tenant::where('is_active', true)->get();
        $plans = Plan::with('modules')->where('is_active', true)->orderBy('order')->get();
        
        // Subscriptions com filtros
        $subscriptions = Subscription::with(['tenant', 'plan.modules'])
            ->when($this->subscriptionSearch, function ($query) {
                $query->whereHas('tenant', function ($q) {
                    $q->where('name', 'like', '%' . $this->subscriptionSearch . '%');
                })->orWhereHas('plan', function ($q) {
                    $q->where('name', 'like', '%' . $this->subscriptionSearch . '%');
                });
            })
            ->when($this->subscriptionStatusFilter, function ($query) {
                $query->where('status', $this->subscriptionStatusFilter);
            })
            ->latest()
            ->get();
        
        // Pedidos pendentes
        $pendingOrders = Order::with(['tenant', 'user', 'plan'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('livewire.super-admin.billing.billing', compact('invoices', 'totalRevenue', 'pendingRevenue', 'tenants', 'plans', 'subscriptions', 'pendingOrders'));
    }
    
    public function approveOrder($orderId)
    {
        $this->approvingOrderId = $orderId; // Ativar loading
        
        \Log::info('🎯 SuperAdmin: Iniciando aprovação de pedido', ['order_id' => $orderId]);
        
        try {
            $order = Order::with(['tenant', 'plan'])->findOrFail($orderId);
            
            \Log::info('📦 Pedido encontrado', [
                'order_id' => $order->id,
                'tenant' => $order->tenant->name,
                'plan' => $order->plan->name,
                'amount' => $order->amount
            ]);
            
            // Simplesmente mudar o status para 'approved'
            // O OrderObserver fará TODO o trabalho:
            // - Cancelar subscription antiga
            // - Criar nova subscription
            // - Sincronizar módulos (ativar/desativar)
            // - ENVIAR EMAIL (pode demorar)
            $order->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);
            
            \Log::info('✅ Status atualizado para approved. Observer processará automaticamente.');
            
            $this->dispatch('success', message: '✅ Pedido aprovado! Email de confirmação enviado ao cliente.');
            
        } catch (\Exception $e) {
            \Log::error('❌ SuperAdmin: Erro ao aprovar pedido', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->dispatch('error', message: 'Erro ao aprovar pedido: ' . $e->getMessage());
        } finally {
            $this->approvingOrderId = null; // Desativar loading
        }
    }
    
    public function rejectOrder($orderId)
    {
        $this->rejectingOrderId = $orderId; // Ativar loading
        
        \Log::info('🚫 SuperAdmin: Rejeitando pedido', ['order_id' => $orderId]);
        
        try {
            $order = Order::with(['tenant', 'user', 'plan'])->findOrFail($orderId);
            
            // Observer vai enviar email automaticamente
            $order->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => auth()->id(),
            ]);
            
            \Log::info('✅ Pedido rejeitado com sucesso. Email enviado ao cliente.');
            $this->dispatch('success', message: '✅ Pedido rejeitado! Email de notificação enviado ao cliente.');
            
        } catch (\Exception $e) {
            \Log::error('❌ Erro ao rejeitar pedido', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            $this->dispatch('error', message: 'Erro ao rejeitar pedido: ' . $e->getMessage());
        } finally {
            $this->rejectingOrderId = null; // Desativar loading
        }
    }
    
    protected function sendRejectionNotification($order)
    {
        try {
            $user = $order->user;
            $tenant = $order->tenant;
            $plan = $order->plan;
            
            if (!$user || !$user->email) {
                \Log::warning('Usuário não encontrado para notificação de rejeição');
                return;
            }
            
            $emailData = [
                'user_name' => $user->name,
                'tenant_name' => $tenant->name,
                'plan_name' => $plan->name,
                'reason' => 'Comprovante de pagamento não aprovado. Por favor, entre em contato com o suporte.',
                'app_name' => config('app.name', 'SOSERP'),
                'support_email' => 'suporte@soserp.vip',
            ];
            
            \Illuminate\Support\Facades\Mail::to($user->email)
                ->send(new \App\Mail\TemplateMail('plan_rejected', $emailData, $tenant->id));
            
            \Log::info('📧 Email de rejeição enviado', ['to' => $user->email]);
            
        } catch (\Exception $e) {
            \Log::error('❌ Erro ao enviar email de rejeição', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
