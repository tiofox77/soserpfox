<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Plan;
use App\Models\Order;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RegisterWizard extends Component
{
    use WithFileUploads;
    
    // Wizard step
    public $currentStep = 1;
    public $isLoggedIn = false;
    
    // Step 1: User data
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    
    // Step 2: Company data
    public $company_name = '';
    public $company_nif = '';
    public $company_address = '';
    public $company_phone = '';
    public $company_email = '';
    
    // Step 3: Plan selection
    public $selected_plan_id = null;
    public $plans = [];
    
    // Step 4: Payment
    public $payment_method = 'transfer';
    public $payment_proof = null;
    public $payment_reference = '';
    
    public function mount()
    {
        $this->plans = Plan::where('is_active', true)->orderBy('order')->get();
        
        // Verificar se usuário está logado
        if (auth()->check()) {
            $this->isLoggedIn = true;
            $user = auth()->user();
            
            // Preencher dados do usuário
            $this->name = $user->name;
            $this->email = $user->email;
            
            // Pular passo 1 e ir direto para criar empresa
            $this->currentStep = 2;
        }
        
        // Restaurar progresso salvo na sessão
        $this->loadWizardProgress();
        
        // Verificar se há dados incompletos após refresh
        $this->checkAndResetIfIncomplete();
        
        // Selecionar plano Starter por padrão se não tiver selecionado
        if (!$this->selected_plan_id) {
            $starterPlan = $this->plans->where('slug', 'starter')->first();
            if ($starterPlan) {
                $this->selected_plan_id = $starterPlan->id;
            }
        }
    }
    
    /**
     * Carregar progresso salvo da sessão
     */
    protected function loadWizardProgress()
    {
        $progress = session('wizard_progress', []);
        
        if (!empty($progress)) {
            // Restaurar passo atual
            $this->currentStep = $progress['currentStep'] ?? $this->currentStep;
            
            // Restaurar dados do usuário (step 1)
            if (!$this->isLoggedIn) {
                $this->name = $progress['name'] ?? $this->name;
                $this->email = $progress['email'] ?? $this->email;
            }
            
            // Restaurar dados da empresa (step 2)
            $this->company_name = $progress['company_name'] ?? $this->company_name;
            $this->company_nif = $progress['company_nif'] ?? $this->company_nif;
            $this->company_address = $progress['company_address'] ?? $this->company_address;
            $this->company_phone = $progress['company_phone'] ?? $this->company_phone;
            $this->company_email = $progress['company_email'] ?? $this->company_email;
            
            // Restaurar plano selecionado (step 3)
            $this->selected_plan_id = $progress['selected_plan_id'] ?? $this->selected_plan_id;
            
            // Restaurar dados de pagamento (step 4)
            $this->payment_method = $progress['payment_method'] ?? $this->payment_method;
            $this->payment_reference = $progress['payment_reference'] ?? $this->payment_reference;
        }
    }
    
    /**
     * Verificar se os dados estão incompletos e resetar se necessário
     * (útil quando usuário atualiza a página e perde dados do formulário)
     */
    protected function checkAndResetIfIncomplete()
    {
        // Se usuário não logado e está em passo > 1, verificar se step 1 está completo
        if (!$this->isLoggedIn && $this->currentStep > 1) {
            if (empty($this->name) || empty($this->email) || empty($this->password)) {
                \Log::info('Dados incompletos no Step 1 detectados. Reiniciando wizard.');
                $this->resetToStart('Dados do usuário incompletos. Por favor, preencha novamente.');
                return;
            }
        }
        
        // Se está no passo 2 ou superior, verificar se step 2 está completo
        if ($this->currentStep > 2) {
            if (empty($this->company_name) || empty($this->company_nif)) {
                \Log::info('Dados incompletos no Step 2 detectados. Reiniciando wizard.');
                $this->resetToStart('Dados da empresa incompletos. Por favor, preencha novamente.');
                return;
            }
        }
        
        // Se está no passo 3 ou superior, verificar se step 3 está completo
        if ($this->currentStep > 3) {
            if (empty($this->selected_plan_id)) {
                \Log::info('Plano não selecionado detectado. Reiniciando wizard.');
                $this->resetToStart('Nenhum plano foi selecionado. Por favor, selecione um plano.');
                return;
            }
        }
    }
    
    /**
     * Resetar wizard para o início com mensagem
     */
    protected function resetToStart($message = null)
    {
        $this->clearWizardProgress();
        $this->reset([
            'name', 'email', 'password', 'password_confirmation',
            'company_name', 'company_nif', 'company_address', 'company_phone', 'company_email',
            'selected_plan_id', 'payment_method', 'payment_reference'
        ]);
        $this->currentStep = $this->isLoggedIn ? 2 : 1;
        
        if ($message) {
            session()->flash('warning', $message);
        }
        
        // Limpar erros de validação
        $this->resetErrorBag();
    }
    
    /**
     * Salvar progresso na sessão
     */
    protected function saveWizardProgress()
    {
        session([
            'wizard_progress' => [
                'currentStep' => $this->currentStep,
                'name' => $this->name,
                'email' => $this->email,
                'company_name' => $this->company_name,
                'company_nif' => $this->company_nif,
                'company_address' => $this->company_address,
                'company_phone' => $this->company_phone,
                'company_email' => $this->company_email,
                'selected_plan_id' => $this->selected_plan_id,
                'payment_method' => $this->payment_method,
                'payment_reference' => $this->payment_reference,
                'saved_at' => now()->toDateTimeString(),
            ]
        ]);
    }
    
    /**
     * Limpar progresso da sessão
     */
    protected function clearWizardProgress()
    {
        session()->forget('wizard_progress');
    }
    
    // Step 1 validation
    protected function validateStep1()
    {
        return $this->validate([
            'name' => 'required|min:3',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ]);
    }
    
    // Step 2 validation
    protected function validateStep2()
    {
        return $this->validate([
            'company_name' => 'required|min:3',
            'company_nif' => 'required|min:5|unique:tenants,nif',
            'company_address' => 'nullable',
            'company_phone' => 'nullable',
            'company_email' => 'nullable|email',
        ]);
    }
    
    // Step 3 validation
    protected function validateStep3()
    {
        return $this->validate([
            'selected_plan_id' => 'required|exists:plans,id',
        ]);
    }
    
    // Step 4 validation
    protected function validateStep4()
    {
        $plan = Plan::find($this->selected_plan_id);
        $isTrial = $plan && $plan->trial_days > 0;
        
        $rules = [
            'payment_method' => 'required|in:transfer',
            'payment_reference' => $isTrial ? 'nullable|string|max:255' : 'required|string|max:255',
        ];
        
        // Se não é trial, comprovativo é obrigatório
        if (!$isTrial) {
            $rules['payment_proof'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:5120'; // 5MB
        } else {
            $rules['payment_proof'] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
        }
        
        return $this->validate($rules);
    }
    
    public function nextStep()
    {
        try {
            if ($this->currentStep == 1) {
                $this->validateStep1();
                $this->currentStep = 2;
            } elseif ($this->currentStep == 2) {
                $this->validateStep2();
                $this->currentStep = 3;
            } elseif ($this->currentStep == 3) {
                $this->validateStep3();
                $this->currentStep = 4;
            }
            
            // Salvar progresso após avançar
            $this->saveWizardProgress();
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Se erro de validação, verificar se dados foram perdidos
            $this->checkIfDataLostAndReset($e);
            throw $e;
        }
    }
    
    /**
     * Verificar se dados foram perdidos (refresh) e resetar wizard
     */
    protected function checkIfDataLostAndReset($validationException)
    {
        $errors = $validationException->errors();
        
        // Se há muitos erros (>= 3), provavelmente refresh perdeu dados
        if (count($errors) >= 3) {
            \Log::warning('Múltiplos erros de validação detectados, possível perda de dados após refresh', [
                'errors_count' => count($errors),
                'current_step' => $this->currentStep
            ]);
            
            $this->resetToStart('Dados do formulário foram perdidos. Por favor, preencha novamente desde o início.');
        }
    }
    
    public function previousStep()
    {
        // Se usuário logado, não deixar voltar para o passo 1
        $minStep = $this->isLoggedIn ? 2 : 1;
        
        if ($this->currentStep > $minStep) {
            $this->currentStep--;
            // Salvar progresso ao voltar
            $this->saveWizardProgress();
        }
    }
    
    // Salvar automaticamente quando campos mudarem
    public function updated()
    {
        $this->saveWizardProgress();
    }
    
    /**
     * Reiniciar wizard do zero
     */
    public function restartWizard()
    {
        $this->clearWizardProgress();
        $this->reset([
            'name', 'email', 'password', 'password_confirmation',
            'company_name', 'company_nif', 'company_address', 'company_phone', 'company_email',
            'selected_plan_id', 'payment_method', 'payment_reference'
        ]);
        $this->currentStep = $this->isLoggedIn ? 2 : 1;
        
        session()->flash('info', 'Progresso reiniciado. Comece novamente.');
    }
    
    public function register()
    {
        \Log::info('=== INÍCIO DO REGISTRO ===');
        \Log::info('Usuário logado?', ['isLoggedIn' => $this->isLoggedIn]);
        \Log::info('Dados do formulário', [
            'company_name' => $this->company_name,
            'company_nif' => $this->company_nif,
            'selected_plan_id' => $this->selected_plan_id,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'has_payment_proof' => $this->payment_proof ? 'SIM' : 'NÃO'
        ]);
        
        // Validar apenas steps necessários
        try {
            \Log::info('Iniciando validações...');
            
            if (!$this->isLoggedIn) {
                \Log::info('Validando Step 1 (usuário)');
                $this->validateStep1();
            }
            
            \Log::info('Validando Step 2 (empresa)');
            $this->validateStep2();
            
            \Log::info('Validando Step 3 (plano)');
            $this->validateStep3();
            
            \Log::info('Validando Step 4 (pagamento)');
            $this->validateStep4();
            
            \Log::info('Todas validações passaram!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Erro de validação', [
                'errors' => $e->errors()
            ]);
            throw $e;
        }
        
        DB::beginTransaction();
        try {
            \Log::info('Iniciando transação do banco de dados');
            // 1. Obter ou criar usuário
            if ($this->isLoggedIn) {
                $user = auth()->user();
                \Log::info('Usuário já logado', ['user_id' => $user->id, 'email' => $user->email]);
            } else {
                \Log::info('Criando novo usuário...');
                $user = User::create([
                    'name' => $this->name,
                    'email' => $this->email,
                    'password' => Hash::make($this->password),
                    'is_active' => true,
                    'is_super_admin' => true, // Primeiro usuário é super admin
                ]);
                \Log::info('Usuário criado', ['user_id' => $user->id, 'is_super_admin' => true]);
            }
            
            // 2. Criar tenant/empresa
            \Log::info('Criando tenant/empresa...', ['company_name' => $this->company_name]);
            $tenant = Tenant::create([
                'name' => $this->company_name,
                'company_name' => $this->company_name,
                'nif' => $this->company_nif,
                'address' => $this->company_address,
                'phone' => $this->company_phone,
                'email' => $this->company_email ?: $this->email,
                'is_active' => true,
            ]);
            \Log::info('Tenant criado', ['tenant_id' => $tenant->id]);
            
            // 3. Vincular usuário ao tenant como owner/super-admin
            \Log::info('Vinculando usuário ao tenant como Super Admin...');
            $user->tenants()->attach($tenant->id, [
                'is_active' => true,
                'joined_at' => now(),
            ]);
            
            // 3.1 Definir tenant como ativo para o usuário
            $user->tenant_id = $tenant->id;
            $user->save();
            
            // 3.2 Atribuir role Super Admin usando Spatie Permission
            setPermissionsTeamId($tenant->id);
            $superAdminRole = \Spatie\Permission\Models\Role::firstOrCreate(
                ['name' => 'super-admin', 'guard_name' => 'web'],
                ['tenant_id' => $tenant->id]
            );
            $user->assignRole($superAdminRole);
            
            \Log::info('Usuário vinculado ao tenant como Super Admin e tenant definido como ativo', [
                'user_tenant_id' => $user->tenant_id,
                'role' => 'super-admin',
                'role_id' => $superAdminRole->id
            ]);
            
            // 4. Salvar comprovativo de pagamento se houver
            $paymentProofPath = null;
            if ($this->payment_proof) {
                \Log::info('Salvando comprovativo de pagamento...');
                $paymentProofPath = $this->payment_proof->store('payment-proofs', 'public');
                \Log::info('Comprovativo salvo', ['path' => $paymentProofPath]);
            }
            
            // 5. Criar subscription
            \Log::info('Criando subscription...');
            $plan = Plan::find($this->selected_plan_id);
            
            // Determinar status baseado em pagamento
            $hasPaidProof = $this->payment_reference || $paymentProofPath;
            $trialDays = (int) $plan->trial_days; // Converter para inteiro
            $hasTrialPeriod = $trialDays > 0;
            
            // Se pagou, aguarda aprovação. Se não pagou mas tem trial, inicia trial
            $now = now();
            if ($hasPaidProof) {
                $status = 'pending'; // Aguarda aprovação do Super Admin
                $trialEndsAt = null;
                $periodStart = null; // Só inicia quando aprovar
                $periodEnd = null;
            } elseif ($hasTrialPeriod) {
                $status = 'trial';
                $trialEndsAt = $now->copy()->addDays($trialDays);
                $periodStart = $now;
                $periodEnd = $trialEndsAt;
            } else {
                $status = 'pending';
                $trialEndsAt = null;
                $periodStart = null;
                $periodEnd = null;
            }
            
            $subscription = $tenant->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => $status,
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'ends_at' => $periodEnd,
                'amount' => $plan->price_monthly,
                'billing_cycle' => 'monthly',
            ]);
            
            \Log::info('Subscription criada', [
                'subscription_id' => $subscription->id,
                'status' => $status,
                'has_payment' => $hasPaidProof,
                'has_trial' => $hasTrialPeriod
            ]);
            
            // 6. Criar pedido (Order) para aprovação do Super Admin
            $order = Order::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'amount' => $plan->price_monthly,
                'payment_method' => $this->payment_method,
                'payment_reference' => $this->payment_reference,
                'payment_proof' => $paymentProofPath,
                'status' => 'pending',
                'notes' => "Pedido criado via wizard de registro. Empresa: {$tenant->name}",
            ]);
            
            // 7. Ativar módulos do plano
            \Log::info('Ativando módulos do plano...', ['included_modules' => $plan->included_modules]);
            if ($plan->included_modules && is_array($plan->included_modules)) {
                foreach ($plan->included_modules as $moduleSlug) {
                    \Log::info('Procurando módulo', ['slug' => $moduleSlug]);
                    $module = \App\Models\Module::where('slug', $moduleSlug)->first();
                    if ($module) {
                        $tenant->modules()->attach($module->id, [
                            'is_active' => true,
                            'activated_at' => now(),
                        ]);
                        \Log::info('Módulo ativado', ['module_id' => $module->id, 'name' => $module->name]);
                    } else {
                        \Log::warning('Módulo não encontrado', ['slug' => $moduleSlug]);
                    }
                }
            } else {
                \Log::warning('Plano não tem módulos incluídos ou não é array');
            }
            
            \Log::info('Commit da transação...');
            DB::commit();
            \Log::info('Transação commitada com sucesso!');
            
            // Limpar progresso do wizard após sucesso
            $this->clearWizardProgress();
            \Log::info('Progresso do wizard limpo');
            
            // Login automático apenas se não estava logado
            if (!$this->isLoggedIn) {
                \Log::info('Efetuando login automático...');
                Auth::login($user);
                \Log::info('Login efetuado');
            }
            
            // Redirecionar para home
            \Log::info('Redirecionando para home...');
            
            if ($status === 'pending') {
                session()->flash('success', 'Empresa criada com sucesso! Seu pagamento está aguardando aprovação. Você receberá acesso total assim que for aprovado.');
            } elseif ($status === 'trial') {
                session()->flash('success', "Empresa criada com sucesso! Você tem {$trialDays} dias de teste grátis. Bem-vindo ao SOSERP!");
            } else {
                session()->flash('success', 'Empresa criada com sucesso! Bem-vindo ao SOSERP.');
            }
            
            return redirect()->route('home');
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('ERRO AO CRIAR CONTA', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Erro ao criar conta: ' . $e->getMessage());
            $this->dispatch('error', message: 'Erro ao criar conta: ' . $e->getMessage());
        }
    }
    
    public function render()
    {
        return view('livewire.auth.register-wizard')
            ->layout('components.layouts.guest');
    }
}
