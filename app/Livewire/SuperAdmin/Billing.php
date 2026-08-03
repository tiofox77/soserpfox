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
    public $markAsPaid = true;
    public $paymentReference = '';
    public $paymentMethod = 'bank_transfer';
    
    // Loading states
    public $approvingOrderId = null;
    public $rejectingOrderId = null;

    // SAFT-AO (Software AGT) — config global, só o dono do sistema
    public $saft_software_cert = '';
    public $saft_product_id = '';
    public $saft_version = '1.0.0';

    public function mount()
    {
        $this->saft_software_cert = (string) softwareSetting('invoicing', 'saft_software_cert', '');
        $this->saft_product_id    = (string) softwareSetting('invoicing', 'saft_product_id', '');
        $this->saft_version       = (string) softwareSetting('invoicing', 'saft_version', '1.0.0');
    }

    public function saveSaftConfig()
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $this->validate([
            'saft_software_cert' => 'nullable|string|max:100',
            'saft_product_id'    => 'nullable|string|max:100',
            'saft_version'       => 'required|string|max:20',
        ]);

        \App\Models\SoftwareSetting::set('invoicing', 'saft_software_cert', $this->saft_software_cert, 'string', 'Certificado Software AGT (SAFT-AO)');
        \App\Models\SoftwareSetting::set('invoicing', 'saft_product_id', $this->saft_product_id, 'string', 'Product ID do software (SAFT-AO)');
        \App\Models\SoftwareSetting::set('invoicing', 'saft_version', $this->saft_version, 'string', 'Versão do formato SAFT-AO');

        $this->dispatch('success', message: 'Configuração SAFT-AO guardada com sucesso!');
    }

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
        $this->invoice_number = Invoice::generateInvoiceNumber();
        $this->invoice_date = now()->format('Y-m-d');
        $this->due_date = now()->addDays(30)->format('Y-m-d');
        $this->showModal = true;
    }

    public function createSubscription()
    {
        $this->reset(['tenant_id', 'plan_id', 'billing_cycle', 'selectedPlan', 'paymentReference']);
        $this->markAsPaid = true;
        $this->paymentMethod = 'bank_transfer';
        $this->showSubscriptionModal = true;
    }

    public function closeSubscriptionModal()
    {
        $this->showSubscriptionModal = false;
        $this->reset(['tenant_id', 'plan_id', 'billing_cycle', 'selectedPlan', 'paymentReference']);
    }

    /**
     * Carregar dados de uma subscription existente para edição.
     * Permite ao admin alterar plano, ciclo e marcar como pago.
     */
    public function editSubscription($subscriptionId)
    {
        $subscription = Subscription::with('tenant', 'plan')->findOrFail($subscriptionId);
        $this->tenant_id = $subscription->tenant_id;
        $this->plan_id = $subscription->plan_id;
        $this->billing_cycle = $subscription->billing_cycle ?? 'monthly';
        $this->selectedPlan = $subscription->plan;
        $this->markAsPaid = $subscription->status === 'active';
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
            'tenant_id'     => 'required|exists:tenants,id',
            'plan_id'       => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,quarterly,semiannual,yearly',
            'paymentMethod' => 'nullable|string|in:bank_transfer,cash,multicaixa,credit_card,other',
            'paymentReference' => 'nullable|string|max:255',
        ]);

        \DB::beginTransaction();
        try {
            $tenant = Tenant::findOrFail($this->tenant_id);
            $plan   = Plan::findOrFail($this->plan_id);

            $amount    = $plan->getPrice($this->billing_cycle);
            $periodEnd = match($this->billing_cycle) {
                'yearly'     => now()->addMonths(14),
                'semiannual' => now()->addMonths(6),
                'quarterly'  => now()->addMonths(3),
                default      => now()->addMonth(),
            };

            // Status da subscription: 'active' se pago, 'pending' se não
            $subStatus = $this->markAsPaid ? 'active' : 'pending';

            // Procurar subscription existente (qualquer status) deste tenant para o mesmo plano
            $existingSubscription = Subscription::where('tenant_id', $this->tenant_id)
                ->whereIn('status', ['active', 'pending', 'trial'])
                ->first();

            if ($existingSubscription) {
                $existingSubscription->update([
                    'plan_id'              => $this->plan_id,
                    'billing_cycle'        => $this->billing_cycle,
                    'amount'               => $amount,
                    'status'               => $subStatus,
                    'current_period_start' => now(),
                    'current_period_end'   => $periodEnd,
                    'ends_at'              => $periodEnd,
                ]);
                $subscription = $existingSubscription;
                $message = 'Subscrição atualizada com sucesso!';
            } else {
                $subscription = Subscription::create([
                    'tenant_id'            => $this->tenant_id,
                    'plan_id'              => $this->plan_id,
                    'status'               => $subStatus,
                    'billing_cycle'        => $this->billing_cycle,
                    'amount'               => $amount,
                    'current_period_start' => now(),
                    'current_period_end'   => $periodEnd,
                    'ends_at'              => $periodEnd,
                    'trial_ends_at'        => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                ]);
                $message = 'Subscrição criada com sucesso!';
            }

            // Sincronizar módulos do plano (apenas se subscription activa)
            if ($subStatus === 'active') {
                // Via serviço + dependências do plano: o sync() duro anterior
                // APAGAVA a Tesouraria dos clientes em Business/Enterprise
                // (planos que não a listam), deixando a Faturação sem formas de
                // pagamento. Também semeia pré-requisitos e concede permissões.
                $sync = new \App\Services\Tenant\TenantModuleSyncService();
                foreach ($plan->moduleSlugsWithDependencies() as $slug) {
                    $sync->activateModule($tenant, $slug);
                }
                $tenant->update([
                    'max_users'      => $plan->max_users,
                    'max_storage_mb' => $plan->max_storage_mb,
                ]);
            }

            // Criar Order de auditoria (status reflecte se foi pago ou não)
            $order = Order::create([
                'tenant_id'         => $tenant->id,
                'user_id'           => auth()->id(),
                'plan_id'           => $plan->id,
                'amount'            => $amount,
                'billing_cycle'     => $this->billing_cycle,
                'status'            => $this->markAsPaid ? 'approved' : 'pending',
                'payment_method'    => $this->paymentMethod ?: 'bank_transfer',
                'payment_reference' => $this->paymentReference ?: null,
                'approved_at'       => $this->markAsPaid ? now() : null,
                'approved_by'       => $this->markAsPaid ? auth()->id() : null,
                'notes'             => 'Subscrição criada/alterada manualmente pelo Super Admin via Billing.',
            ]);

            // Criar Invoice se marcado como pago
            if ($this->markAsPaid) {
                Invoice::create([
                    'tenant_id'      => $tenant->id,
                    'invoice_number' => Invoice::generateInvoiceNumber(),
                    'description'    => "Subscrição {$plan->name} - " . match($this->billing_cycle) {
                        'yearly'     => 'Anual (14 meses)',
                        'semiannual' => 'Semestral',
                        'quarterly'  => 'Trimestral',
                        default      => 'Mensal',
                    },
                    'invoice_date' => now(),
                    'due_date'     => now(),
                    'paid_at'      => now(),
                    'subtotal'     => $amount,
                    'tax'          => 0,
                    'total'        => $amount,
                    'status'       => 'paid',
                ]);
                $message .= ' Fatura paga gerada automaticamente.';
            } else {
                $message .= ' Aguarda confirmação de pagamento.';
            }

            \DB::commit();
            $this->dispatch('success', message: $message);
            $this->closeSubscriptionModal();
        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::error('Billing::saveSubscription error', [
                'error' => $e->getMessage(),
                'tenant_id' => $this->tenant_id,
                'plan_id' => $this->plan_id,
            ]);
            $this->dispatch('error', message: 'Erro ao salvar subscrição: ' . $e->getMessage());
        }
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
            $subscription = Subscription::with(['tenant', 'plan.modules'])->findOrFail($id);
            
            if ($subscription->status === 'active') {
                $this->dispatch('error', message: "Não é possível excluir uma subscrição ativa. Cancele-a primeiro.");
                return;
            }
            
            $subscription->delete();
            $this->dispatch('success', message: 'Subscrição excluída com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Erro ao excluir subscrição: ' . $e->getMessage());
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
        $invoice->markAsPaid();
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
                $query->where(function ($q) {
                    $q->where('invoice_number', 'like', '%' . $this->search . '%')
                      ->orWhereHas('tenant', function ($sub) {
                          $sub->where('name', 'like', '%' . $this->search . '%');
                      });
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
                $query->where(function ($q) {
                    $q->whereHas('tenant', function ($sub) {
                        $sub->where('name', 'like', '%' . $this->subscriptionSearch . '%');
                    })->orWhereHas('plan', function ($sub) {
                        $sub->where('name', 'like', '%' . $this->subscriptionSearch . '%');
                    });
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
