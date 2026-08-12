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
    public $marcarComoPago = true;
    public $paymentReference = '';
    public $paymentMethod = 'bank_transfer';

    /**
     * Qual subscrição está aberta para edição.
     *
     * Faltava. O ecrã abria uma linha e ao gravar voltava a procurar o alvo
     * por tenant + estado — ou seja, escrevia na subscrição ACTIVA daquela
     * empresa, fosse qual fosse a linha em que se tinha carregado. Abrir uma
     * subscrição cancelada para lhe corrigir o ciclo despromovia a que estava
     * a dar acesso.
     */
    public $editingSubscriptionId = null;

    /** Motivo da recusa, para o cliente saber o que correu mal. */
    public $rejectionReason = '';
    public $rejectingOrder = null;
    public $showRejectModal = false;
    
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

    /**
     * Só o dono da plataforma mexe aqui.
     *
     * O middleware da rota trava a ENTRADA no ecrã, mas os métodos de um
     * componente Livewire são chamados por pedidos próprios. Cada acção
     * verifica por si — defesa em profundidade, e o custo é uma consulta.
     *
     * Usa-se isPlatformSuperAdmin() e não a coluna is_super_admin: a coluna é
     * só um dos caminhos (o outro é o papel global), e o saveSaftConfig()
     * exigia a coluna, o que dava 403 a um dono da plataforma legítimo.
     */
    private function apenasDonoDaPlataforma(): void
    {
        abort_unless(auth()->check() && auth()->user()->isPlatformSuperAdmin(), 403);
    }

    public function saveSaftConfig()
    {
        $this->apenasDonoDaPlataforma();

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

    /**
     * Qualquer filtro volta à primeira página.
     *
     * Só a pesquisa das facturas o fazia. Filtrar por estado estando na página
     * 3 mantinha a página 3 de um resultado que passara a ter uma — e o ecrã
     * dizia "Nenhuma fatura encontrada" com facturas a existir.
     */
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingSubscriptionSearch()
    {
        $this->resetPage();
    }

    public function updatingSubscriptionStatusFilter()
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
        $this->apenasDonoDaPlataforma();

        // O editingSubscriptionId TEM de ser limpo nos dois sítios. Se ficasse
        // só num, "editar a #7, fechar, criar nova" gravava a nova por cima
        // da #7.
        $this->reset(['tenant_id', 'plan_id', 'billing_cycle', 'selectedPlan', 'paymentReference', 'editingSubscriptionId']);
        $this->resetValidation();
        $this->marcarComoPago = true;
        $this->paymentMethod = 'bank_transfer';
        $this->showSubscriptionModal = true;
    }

    public function closeSubscriptionModal()
    {
        $this->showSubscriptionModal = false;
        $this->reset(['tenant_id', 'plan_id', 'billing_cycle', 'selectedPlan', 'paymentReference', 'editingSubscriptionId']);
        $this->resetValidation();
    }

    /**
     * Carregar dados de uma subscription existente para edição.
     * Permite ao admin alterar plano, ciclo e marcar como pago.
     */
    public function editSubscription($subscriptionId)
    {
        $this->apenasDonoDaPlataforma();

        $subscription = Subscription::with('tenant', 'plan')->findOrFail($subscriptionId);

        // Guardar QUAL subscrição se está a editar. Sem isto, o saveSubscription
        // voltava a adivinhar o alvo pelo tenant.
        $this->editingSubscriptionId = $subscription->id;
        $this->tenant_id = $subscription->tenant_id;
        $this->plan_id = $subscription->plan_id;
        $this->billing_cycle = $subscription->billing_cycle ?? 'monthly';
        $this->selectedPlan = $subscription->plan;
        $this->marcarComoPago = $subscription->status === 'active';
        $this->resetValidation();
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
        $this->apenasDonoDaPlataforma();

        $this->validate([
            'tenant_id'     => 'required|exists:tenants,id',
            'plan_id'       => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,quarterly,semiannual,yearly',
            'paymentMethod' => 'nullable|string|in:bank_transfer,cash,multicaixa,credit_card,other',
            'paymentReference' => 'nullable|string|max:255',
        ]);

        \DB::beginTransaction();
        try {
            // A editar: o alvo é a linha que se abriu, e a empresa é a dela.
            // Uma edição nunca muda a subscrição de dono, por isso o tenant do
            // formulário é ignorado neste caminho.
            $emEdicao = $this->editingSubscriptionId
                ? Subscription::findOrFail($this->editingSubscriptionId)
                : null;

            if ($emEdicao) {
                $this->tenant_id = $emEdicao->tenant_id;
            }

            $tenant = Tenant::findOrFail($this->tenant_id);
            $plan   = Plan::findOrFail($this->plan_id);

            $amount    = $plan->getPrice($this->billing_cycle);
            $periodEnd = match($this->billing_cycle) {
                'yearly'     => now()->addMonths(14),
                'semiannual' => now()->addMonths(6),
                'quarterly'  => now()->addMonths(3),
                default      => now()->addMonth(),
            };

            $subStatus = $this->marcarComoPago ? 'active' : 'pending';

            // O alvo: a linha aberta, ou — a criar — a que esta empresa já tem
            // em curso. Antes procurava-se sempre por empresa, mesmo em edição.
            $existingSubscription = $emEdicao ?: Subscription::where('tenant_id', $this->tenant_id)
                ->whereIn('status', ['active', 'pending', 'trial'])
                ->latest('id')
                ->first();

            // Uma subscrição que está a dar acesso não desce para 'pending'.
            //
            // O caso: cliente no Business pago até 2027 pede o Enterprise e diz
            // que transfere amanhã. O admin escolhia Enterprise, desligava
            // "Marcar como PAGO" — que é exactamente o que a caixa promete — e
            // a subscrição em vigor passava a 'pending'. O CheckSubscription
            // procura ('active','trial'): a empresa inteira ficava fora do ERP
            // no pedido seguinte, com o Business já pago apagado do registo.
            //
            // Agora, um plano por pagar não toca no que está em vigor: cria-se
            // uma subscrição pendente ao lado, e o acesso actual mantém-se até
            // o pagamento ser confirmado.
            $emVigor = $existingSubscription
                && in_array($existingSubscription->status, ['active', 'trial'], true);

            if ($emVigor && !$this->marcarComoPago) {
                $subscription = Subscription::create([
                    'tenant_id'            => $this->tenant_id,
                    'plan_id'              => $this->plan_id,
                    'status'               => 'pending',
                    'billing_cycle'        => $this->billing_cycle,
                    'amount'               => $amount,
                    'current_period_start' => null,
                    'current_period_end'   => null,
                    'ends_at'              => null,
                ]);
                $message = 'Subscrição pendente criada. O acesso actual mantém-se até o pagamento ser confirmado.';
            } elseif ($existingSubscription) {
                $existingSubscription->update([
                    'plan_id'              => $this->plan_id,
                    'billing_cycle'        => $this->billing_cycle,
                    'amount'               => $amount,
                    'status'               => $subStatus,
                    'current_period_start' => $this->marcarComoPago ? now() : $existingSubscription->current_period_start,
                    'current_period_end'   => $this->marcarComoPago ? $periodEnd : $existingSubscription->current_period_end,
                    'ends_at'              => $this->marcarComoPago ? $periodEnd : $existingSubscription->ends_at,
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
                    // trial_ends_at SÓ quando a subscrição é mesmo um teste.
                    //
                    // Gravava-se sempre que o plano tivesse trial_days — e todos
                    // os planos pagos têm. Vender um Business e marcá-lo como
                    // pago queimava, sem ninguém dar por isso, a cortesia única
                    // daquele cliente: o DireitoACortesia conta trial_ends_at
                    // preenchido como teste já usado, e a partir daí nem o plano
                    // gratuito nem qualquer período de teste lhe voltavam a
                    // estar disponíveis.
                    'trial_ends_at'        => (!$this->marcarComoPago && $plan->trial_days > 0)
                        ? now()->addDays($plan->trial_days)
                        : null,
                ]);
                $message = 'Subscrição criada com sucesso!';
            }

            // Uma empresa não pode ficar com duas subscrições a dar acesso: meio
            // sistema faz ->where('status','active')->first() e apanharia uma
            // delas ao calhas.
            if ($subscription->status === 'active') {
                Subscription::where('tenant_id', $this->tenant_id)
                    ->where('id', '!=', $subscription->id)
                    ->whereIn('status', ['active', 'trial'])
                    ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'ends_at' => now()]);
            }

            // Módulos: activar os do plano novo E retirar os que ele já não dá.
            //
            // Só se activava. Baixar um cliente de Enterprise para Starter por
            // este ecrã deixava-o com os módulos do plano caro que deixou de
            // pagar — hotel, oficina, restauração — a funcionar na mesma.
            if ($subscription->status === 'active') {
                $sync = new \App\Services\Tenant\TenantModuleSyncService();
                $doPlano = $plan->moduleSlugsWithDependencies();

                foreach ($doPlano as $slug) {
                    $sync->activateModule($tenant, $slug);
                }

                $activos = $tenant->modules()
                    ->wherePivot('is_active', true)
                    ->pluck('modules.slug')
                    ->all();

                foreach ($activos as $slug) {
                    if (!in_array($slug, $doPlano, true)) {
                        // O serviço recusa-se a tirar um módulo do qual outro
                        // activo dependa, portanto não parte nada.
                        $sync->deactivateModule($tenant, $slug);
                    }
                }

                $tenant->update([
                    'max_users'      => $plan->max_users,
                    'max_storage_mb' => $plan->max_storage_mb,
                ]);
            }

            // Order de auditoria.
            //
            // O user_id é o DONO da empresa e não quem carregou no botão: é a
            // ele que o OrderObserver manda o email de confirmação, e é sobre
            // as empresas dele que a subscrição se propaga. Com o id do super
            // admin, o email do "seu plano foi activado" ia para o super admin
            // e a propagação corria sobre as empresas DELE.
            $donoDaEmpresa = $tenant->users()->orderBy('tenant_user.id')->first();

            $order = Order::create([
                'tenant_id'         => $tenant->id,
                'user_id'           => $donoDaEmpresa?->id ?? auth()->id(),
                'plan_id'           => $plan->id,
                'amount'            => $amount,
                'billing_cycle'     => $this->billing_cycle,
                'status'            => $this->marcarComoPago ? 'approved' : 'pending',
                'payment_method'    => $this->paymentMethod ?: 'bank_transfer',
                'payment_reference' => $this->paymentReference ?: null,
                'approved_at'       => $this->marcarComoPago ? now() : null,
                'approved_by'       => $this->marcarComoPago ? auth()->id() : null,
                'notes'             => 'Subscrição criada/alterada manualmente pelo Super Admin via Billing.',
            ]);

            // Factura, se foi pago.
            if ($this->marcarComoPago) {
                Invoice::create([
                    'tenant_id'       => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'invoice_number'  => Invoice::generateInvoiceNumber(),
                    'description'     => "Subscrição {$plan->name} — " . $this->nomeDoCiclo($this->billing_cycle),
                    'invoice_date'    => now(),
                    'due_date'        => now(),
                    'paid_at'         => now(),
                    'payment_method'    => $this->paymentMethod ?: 'bank_transfer',
                    'payment_reference' => $this->paymentReference ?: null,
                    'subtotal'        => $amount,
                    'tax'             => 0,
                    'total'           => $amount,
                    'status'          => 'paid',
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

    /**
     * O nome do ciclo, por extenso.
     *
     * O ecrã escrevia `$ciclo === 'yearly' ? 'Anual' : 'Mensal'` em cinco
     * sítios — trimestral e semestral apareciam como "Mensal", no próprio ecrã
     * que os cria. Um cliente a pagar 242.400 Kz de semestre lia "Mensal".
     */
    public static function nomeDoCiclo(?string $ciclo): string
    {
        return match ($ciclo) {
            'yearly'     => 'Anual',
            'semiannual' => 'Semestral',
            'quarterly'  => 'Trimestral',
            'monthly'    => 'Mensal',
            default      => 'Mensal',
        };
    }

    public function viewSubscription($id)
    {
        $this->apenasDonoDaPlataforma();

        $subscription = Subscription::with(['tenant', 'plan.modules'])->findOrFail($id);
        $this->dispatch('info', message: "Subscrição #{$id} — " . ($subscription->tenant?->name ?? 'empresa apagada'));
    }

    /**
     * Cancelar cumpre o que a confirmação promete: acesso até ao fim do período.
     *
     * O `cancel()` do modelo põe o estado em 'cancelled' de imediato, e o
     * CheckSubscription só aceita 'active' e 'trial' — o cliente perdia o
     * sistema no pedido seguinte, depois de o ecrã garantir ao admin que ele
     * ficava "até ao final do período". Quem tinha pago um ano à frente perdia
     * o ano.
     *
     * Agora marca-se a data do cancelamento e deixa-se correr até ao fim do
     * período pago; o autoExpireSubscriptions do middleware trata do resto.
     * Se o período já passou, cancela mesmo — não há nada a respeitar.
     */
    public function cancelSubscription($id)
    {
        $this->apenasDonoDaPlataforma();

        $subscription = Subscription::findOrFail($id);
        $fimDoPeriodo = $subscription->current_period_end;

        if ($fimDoPeriodo && $fimDoPeriodo->isFuture()) {
            $subscription->update([
                'cancelled_at' => now(),
                'ends_at'      => $fimDoPeriodo,
            ]);

            $this->dispatch('success', message:
                'Subscrição cancelada. O acesso mantém-se até ' . $fimDoPeriodo->format('d/m/Y') . '.');

            return;
        }

        $subscription->cancel();
        $this->dispatch('success', message: 'Subscrição cancelada. O período já tinha terminado, o acesso cessa agora.');
    }

    public function deleteSubscription($id)
    {
        $this->apenasDonoDaPlataforma();

        try {
            $subscription = Subscription::with(['tenant', 'plan.modules'])->findOrFail($id);

            // O 'trial' também está a dar acesso, e apagá-lo levava com ele a
            // prova de que aquela empresa já tinha gasto a sua cortesia.
            if (in_array($subscription->status, ['active', 'trial'], true)) {
                $this->dispatch('error', message: "Não é possível excluir uma subscrição {$subscription->status}. Cancele-a primeiro.");
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
        $this->apenasDonoDaPlataforma();

        $invoice = Invoice::findOrFail($id);
        $this->resetValidation();
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
        $this->apenasDonoDaPlataforma();

        if ($this->editingInvoiceId) {
            $this->rules['invoice_number'] = 'required|unique:invoices,invoice_number,' . $this->editingInvoiceId;
        }

        $this->validate();

        // O total é somado aqui e não aceite do formulário. O campo está
        // `readonly` no HTML, mas readonly é uma sugestão ao browser, não uma
        // validação — e o valor viaja no pedido como qualquer outro.
        $subtotal = round((float) $this->subtotal, 2);
        $imposto  = round((float) $this->tax, 2);
        $total    = round($subtotal + $imposto, 2);

        $data = [
            'tenant_id' => $this->tenant_id,
            'invoice_number' => $this->invoice_number,
            'description' => $this->description,
            'invoice_date' => $this->invoice_date,
            'due_date' => $this->due_date,
            'subtotal' => $subtotal,
            'tax' => $imposto,
            'total' => $total,
            'status' => $this->status,
        ];

        // Uma factura marcada como paga tem de ter data de pagamento: sem ela
        // fica "paga" na lista e por pagar em qualquer relatório que use paid_at.
        if ($this->status === 'paid') {
            $data['paid_at'] = now();
        }

        if ($this->editingInvoiceId) {
            Invoice::find($this->editingInvoiceId)->update($data);
            $this->dispatch('success', message: 'Fatura atualizada com sucesso!');
        } else {
            Invoice::create($data);
            $this->dispatch('success', message: 'Fatura criada com sucesso!');
        }

        $this->closeModal();
    }

    public function marcarFacturaComoPaga($id)
    {
        $this->apenasDonoDaPlataforma();

        $invoice = Invoice::findOrFail($id);

        // Invoice::markAsPaid($metodo = null, $referencia = null) escreve os dois
        // campos. Chamado sem argumentos, apagava o método e a referência de
        // pagamento que já lá estivessem gravados.
        $invoice->markAsPaid($invoice->payment_method, $invoice->payment_reference);

        $this->dispatch('success', message: 'Fatura marcada como paga!');
    }

    public function delete($id)
    {
        $this->apenasDonoDaPlataforma();

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
        // Sem isto, os erros da tentativa anterior reapareciam ao abrir a modal
        // de novo, a apontar para campos entretanto limpos.
        $this->resetValidation();
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
        // Apagar o campo deixava-o a "" e `"" + ""` num contexto numérico
        // levantava TypeError — o modal rebentava com 500 a meio de escrever.
        $this->total = round(((float) $this->subtotal) + ((float) $this->tax), 2);
    }

    public function render()
    {
        $invoices = Invoice::with(['tenant' => fn ($q) => $q->withTrashed()])
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
        $overdueRevenue = Invoice::where('status', 'overdue')->sum('total');

        // As empresas desactivadas continuam a poder ser facturadas — é
        // precisamente a elas que se emite a última factura. Excluí-las do
        // selector impedia-o.
        $tenants = Tenant::orderBy('name')->get();
        $plans = Plan::with('modules')->where('is_active', true)->orderBy('order')->get();

        // withTrashed nas relações: uma empresa apagada (soft delete) deixava
        // ->tenant a null e o ecrã INTEIRO rebentava com 500 no primeiro
        // {{ $subscription->tenant->name }}. O painel de facturação da
        // plataforma ficava inacessível por causa de uma empresa apagada.
        $subscriptions = Subscription::with(['tenant' => fn ($q) => $q->withTrashed(), 'plan.modules'])
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
        $pendingOrders = Order::with(['tenant' => fn ($q) => $q->withTrashed(), 'user', 'plan'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('livewire.super-admin.billing.billing', compact('invoices', 'totalRevenue', 'pendingRevenue', 'overdueRevenue', 'tenants', 'plans', 'subscriptions', 'pendingOrders'));
    }
    
    public function approveOrder($orderId)
    {
        $this->apenasDonoDaPlataforma();

        $this->approvingOrderId = $orderId;

        try {
            $order = Order::with(['tenant', 'plan'])->findOrFail($orderId);

            // Um separador aberto há meia hora ainda mostra o pedido na fila.
            // Sem esta verificação, carregar aprovava um pedido já rejeitado —
            // ou aprovava outra vez um já aprovado.
            if ($order->status !== 'pending') {
                $this->dispatch('error', message:
                    "Este pedido já não está pendente (está \"{$order->status}\"). Recarregue a página.");
                return;
            }

            // A escrita e todo o trabalho do observer numa transacção só.
            //
            // Estavam fora: se o observer rebentasse a meio — a criar a
            // subscrição, a sincronizar módulos, a enviar o email — o pedido
            // ficava 'approved' na base, saía da lista de pendentes (que só
            // mostra 'pending') e o cliente ficava sem plano, sem forma de
            // repetir e sem ninguém dar por isso.
            \DB::transaction(function () use ($order) {
                $order->update([
                    'status'      => 'approved',
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                ]);
            });

            $this->dispatch('success', message: 'Pedido aprovado. O cliente foi notificado por email.');

        } catch (\Throwable $e) {
            \Log::error('Billing::approveOrder falhou', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);

            $this->dispatch('error', message: 'Erro ao aprovar pedido: ' . $e->getMessage());
        } finally {
            $this->approvingOrderId = null;
        }
    }
    
    /**
     * Abrir a caixa que pergunta PORQUÊ.
     *
     * A coluna `rejection_reason` existe desde sempre e nunca era preenchida:
     * o cliente recebia um email a dizer "Não especificado" e ficava sem saber
     * se o comprovativo estava ilegível, se o valor não batia certo, ou o quê.
     */
    public function openRejectModal($orderId)
    {
        $this->apenasDonoDaPlataforma();

        $this->rejectingOrder  = Order::with(['tenant', 'plan'])->findOrFail($orderId);
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function closeRejectModal()
    {
        $this->showRejectModal = false;
        $this->rejectingOrder  = null;
        $this->rejectionReason = '';
        $this->resetValidation();
    }

    public function rejectOrder($orderId = null)
    {
        $this->apenasDonoDaPlataforma();

        $orderId = $orderId ?: $this->rejectingOrder?->id;

        $this->validate([
            'rejectionReason' => 'required|string|min:5|max:500',
        ], [
            'rejectionReason.required' => 'Escreva o motivo — é o que o cliente vai ler.',
            'rejectionReason.min'      => 'O motivo tem de dizer alguma coisa.',
        ]);

        $this->rejectingOrderId = $orderId;

        try {
            $order = Order::with(['tenant', 'user', 'plan'])->findOrFail($orderId);

            if ($order->status !== 'pending') {
                $this->dispatch('error', message:
                    "Este pedido já não está pendente (está \"{$order->status}\"). Recarregue a página.");
                return;
            }

            \DB::transaction(function () use ($order) {
                $order->update([
                    'status'           => 'rejected',
                    'rejection_reason' => $this->rejectionReason,
                    'rejected_at'      => now(),
                    'rejected_by'      => auth()->id(),
                ]);
            });

            $this->closeRejectModal();
            $this->dispatch('success', message: 'Pedido rejeitado. O cliente foi notificado com o motivo.');

        } catch (\Throwable $e) {
            \Log::error('Billing::rejectOrder falhou', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            $this->dispatch('error', message: 'Erro ao rejeitar pedido: ' . $e->getMessage());
        } finally {
            $this->rejectingOrderId = null;
        }
    }
    
}
