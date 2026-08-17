<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Plan;
use App\Models\Order;
use App\Models\EmailTemplate;
use App\Services\Subscriptions\DireitoACortesia;
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
    
    // Email template properties (igual à modal)
    public $testTemplateId;
    public $testEmail = '';
    
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

    /**
     * Regime fiscal AGT.
     *
     * O assistente não perguntava e toda a gente nascia no default da coluna
     * — Regime Geral, a liquidar IVA a 14%. Uma empresa do simplificado ou da
     * não sujeição facturava com impostos errados até alguém descobrir o ecrã
     * /empresa e corrigir. A área de conta já perguntava; o registo não.
     */
    public $company_regime = Tenant::REGIME_GERAL;
    
    // Step 3: Plan selection
    public $selected_plan_id = null;
    public $plans = [];
    
    // Step 4: Payment
    public $payment_method = 'transfer';

    /**
     * O plano veio escolhido do link (página inicial ou campanha)?
     *
     * Serve para não perguntar duas vezes a mesma coisa: quem carregou em
     * "Começar Agora" no cartão de um plano já disse qual queria.
     */
    public bool $planoVeioDoLink = false;

    /**
     * Em que passo a pessoa estava quando o refresh levou a palavra-passe.
     * Assim que a reescrever, volta para lá em vez de repetir o assistente.
     */
    public ?int $passoAntesDaSenha = null;
    public $payment_proof = null;
    public $payment_reference = '';
    
    public function mount()
    {
        $this->captureAcquisition();
        $this->plans = Plan::where('is_active', true)->orderBy('order')->get();
        $requestedPlan = request()->query('plan');
        
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

        // Links de campanha podem pré-selecionar um plano pelo slug, por exemplo:
        // /subscrever/fox-friendly -> /register?plan=fox-friendly
        // O parâmetro explícito prevalece sobre o progresso antigo do wizard.
        if (is_string($requestedPlan) && $requestedPlan !== '') {
            $campaignPlan = $this->plans->firstWhere('slug', $requestedPlan);
            if ($campaignPlan) {
                $this->selected_plan_id = $campaignPlan->id;

                // Quem já escolheu o plano na página inicial não tem de o
                // escolher outra vez aqui. O passo continua a existir para
                // quem chegue sem plano, e este pode sempre voltar atrás para
                // o mudar — o que não pode é ser obrigado a repetir a escolha.
                //
                // A não ser que o plano do link já não esteja disponível para
                // ele: aí é preciso escolher outro, e o passo tem de voltar.
                $this->planoVeioDoLink = $this->direitoACortesia->podeEscolher($campaignPlan);
            }
        }
        
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

    /** Guarda a origem publicitária durante todo o assistente. */
    protected function captureAcquisition(): void
    {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid'];
        $incoming = array_filter(request()->only($keys), fn ($value) => is_string($value) && $value !== '');

        if ($incoming !== []) {
            session(['registration_acquisition' => array_merge(
                session('registration_acquisition', []),
                $incoming
            )]);
        }

        if (!session()->has('registration_visitor_id')) {
            session(['registration_visitor_id' => (string) \Illuminate\Support\Str::uuid()]);
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
            $this->company_regime = $progress['company_regime'] ?? $this->company_regime;
            $this->company_address = $progress['company_address'] ?? $this->company_address;
            $this->company_phone = $progress['company_phone'] ?? $this->company_phone;
            $this->company_email = $progress['company_email'] ?? $this->company_email;
            
            // Restaurar plano selecionado (step 3)
            $this->selected_plan_id = $progress['selected_plan_id'] ?? $this->selected_plan_id;
            // ...e se ele veio do link, para não voltar a perguntar depois de
            // um F5 a meio do registo.
            $this->planoVeioDoLink = (bool) ($progress['planoVeioDoLink'] ?? $this->planoVeioDoLink);
            $this->passoAntesDaSenha = $progress['passoAntesDaSenha'] ?? $this->passoAntesDaSenha;

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
        // A palavra-passe é a única coisa que não fica guardada — é uma
        // credencial, não tem que ficar na sessão do servidor à espera.
        //
        // Só que a seguir a um F5 ela vem sempre vazia, e daqui saía um
        // resetToStart(): a empresa, o NIF, a morada, o plano, tudo apagado
        // por causa do único campo que de propósito não se guarda. Bastava
        // uma recarga, um toque no telemóvel, um erro de rede.
        //
        // Agora não se perde nada: volta-se ao passo 1 só para reescrever a
        // palavra-passe e segue-se dali direto para onde a pessoa estava.
        if (!$this->isLoggedIn && $this->currentStep > 1 && empty($this->password)) {
            if (empty($this->name) || empty($this->email)) {
                \Log::info('Dados do utilizador em falta. Reiniciando wizard.');
                $this->resetToStart('Dados do utilizador incompletos. Por favor, preencha novamente.');
                return;
            }

            $this->passoAntesDaSenha = $this->currentStep;
            $this->currentStep       = 1;

            session()->flash('info', 'Os seus dados foram guardados. Confirme a palavra-passe para continuar de onde parou.');
            return;
        }

        // Estes campos são guardados na sessão; se faltam, faltam mesmo.
        if ($this->currentStep > 2 && (empty($this->company_name) || empty($this->company_nif))) {
            \Log::info('Dados incompletos no Step 2 detectados. Reiniciando wizard.');
            $this->resetToStart('Dados da empresa incompletos. Por favor, preencha novamente.');
            return;
        }

        if ($this->currentStep > 3 && empty($this->selected_plan_id)) {
            \Log::info('Plano não selecionado detectado. Reiniciando wizard.');
            $this->resetToStart('Nenhum plano foi selecionado. Por favor, selecione um plano.');
            return;
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
                'company_regime' => $this->company_regime,
                'company_address' => $this->company_address,
                'company_phone' => $this->company_phone,
                'company_email' => $this->company_email,
                'selected_plan_id' => $this->selected_plan_id,
                'planoVeioDoLink' => $this->planoVeioDoLink,
                'passoAntesDaSenha' => $this->passoAntesDaSenha,
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
            'company_nif' => ['required', new \App\Rules\NifDeEmpresa(), 'unique:tenants,nif'],
            'company_regime' => 'required|in:' . implode(',', array_keys(Tenant::REGIMES)),
            'company_address' => 'nullable',
            'company_phone' => 'nullable',
            'company_email' => 'nullable|email',
        ], [
            'company_regime.required' => 'Escolha o regime fiscal da empresa.',
            'company_regime.in'       => 'Regime fiscal inválido.',
        ]);
    }
    
    // Step 3 validation
    protected function validateStep3()
    {
        $this->validate([
            'selected_plan_id' => 'required|exists:plans,id',
        ]);

        // O plano gratuito é de uma vez só. O ecrã já o mostra fechado, mas a
        // escolha viaja no pedido e o pedido é do lado de fora.
        $recusa = $this->direitoACortesia->motivoParaRecusar($this->planoEscolhido);

        if ($recusa) {
            $this->addError('selected_plan_id', $recusa);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'selected_plan_id' => $recusa,
            ]);
        }

        return true;
    }

    /**
     * O que esta conta ainda pode receber de graça.
     *
     * No registo a identidade é o NIF da empresa — e o utilizador, quando já
     * está autenticado a criar mais uma empresa.
     */
    public function getDireitoACortesiaProperty(): DireitoACortesia
    {
        return DireitoACortesia::de(auth()->user(), $this->company_nif);
    }

    /** Este plano começa em período de teste, ou fica a aguardar pagamento? */
    public function getTemDireitoATesteProperty(): bool
    {
        return $this->direitoACortesia->temDireitoATeste($this->planoEscolhido);
    }
    
    // Step 4 validation
    protected function validateStep4()
    {
        // Sem nada a pagar não há nada a validar.
        //
        // A regra anterior olhava só para `trial_days` e tornava a referência
        // e o comprovativo opcionais. Mas o passo continuava lá, a mostrar
        // "Valor: 0.00 Kz", o IBAN da empresa e o aviso de que era preciso
        // anexar o comprovativo — para um plano que custa zero.
        if ($this->naoHaNadaAPagar) {
            return true;
        }

        // Em período de teste ainda não há transferência feita, por isso a
        // referência e o comprovativo ficam por preencher — como já era.
        //
        // Mas só para quem tem direito ao teste: a quem já o gastou, o plano
        // começa a pagar, e a prova do pagamento volta a ser obrigatória.
        $regra = $this->temDireitoATeste ? 'nullable' : 'required';

        return $this->validate([
            'payment_method'    => 'required|in:transfer',
            'payment_reference' => $regra . '|string|max:255',
            'payment_proof'     => $regra . '|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'payment_reference.required' => 'Indique a referência da transferência.',
            'payment_proof.required'     => 'Anexe o comprovativo da transferência.',
        ]);
    }
    
    /** O plano escolhido, ou null. */
    public function getPlanoEscolhidoProperty(): ?Plan
    {
        return $this->selected_plan_id ? Plan::find($this->selected_plan_id) : null;
    }

    /**
     * O plano é gratuito?
     *
     * Só o preço conta. O período de teste não entra aqui de propósito:
     * todos os planos têm um, e se contasse ninguém pagava no registo.
     * Num plano a 0 Kz, mostrar o IBAN, pedir a referência da transferência
     * e exigir o comprovativo é pedir a prova de um pagamento que ninguém
     * fez — o ecrã chegava a escrever "Valor: 0.00 Kz" e a exigi-lo à mesma.
     */
    public function getNaoHaNadaAPagarProperty(): bool
    {
        $plano = $this->planoEscolhido;

        if (!$plano) {
            return false;
        }

        // Mensal: é o ciclo com que o registo cria sempre a subscrição.
        return (float) ($plano->getPrice('monthly') ?? 0) <= 0;
    }

    /** Há pagamento a tratar no último passo? */
    public function getTemPassoDePagamentoProperty(): bool
    {
        return !$this->naoHaNadaAPagar;
    }

    /** O passo da escolha do plano só existe se ele não veio já escolhido. */
    public function getTemPassoDePlanoProperty(): bool
    {
        return !$this->planoVeioDoLink;
    }

    public function nextStep()
    {
        try {
            if ($this->currentStep == 1) {
                $this->validateStep1();

                // Voltou aqui só para reescrever a palavra-passe depois de um
                // refresh: segue direto para onde estava.
                $this->currentStep       = $this->passoAntesDaSenha ?: 2;
                $this->passoAntesDaSenha = null;
            } elseif ($this->currentStep == 2) {
                $this->validateStep2();

                // Só agora se sabe o NIF, e é por ele que se descobre se a
                // empresa já gastou o gratuito. Se o plano do link deixou de
                // estar disponível, a pergunta tem de voltar — mais vale aqui
                // do que no fim, depois de tudo preenchido.
                $recusa = $this->direitoACortesia->motivoParaRecusar($this->planoEscolhido);

                if ($recusa) {
                    $this->planoVeioDoLink  = false;
                    $this->selected_plan_id = null;
                    $this->currentStep      = 3;
                    session()->flash('warning', $recusa . ' Escolha outro plano para continuar.');
                    $this->saveWizardProgress();

                    return;
                }

                // Com o plano já escolhido no link, salta-se a pergunta.
                $this->currentStep = $this->temPassoDePlano ? 3 : 4;
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

        // Muitos erros de uma vez é, quase sempre, um formulário ainda por
        // preencher — não um refresh que comeu os dados. Apagar tudo aqui
        // castigava quem se limitou a carregar em "Próximo" cedo demais e
        // levava com a empresa e o plano deitados fora.
        //
        // A mensagem de cada campo já diz o que falta; fica só o registo.
        if (count($errors) >= 3) {
            \Log::warning('Vários erros de validação de uma vez', [
                'errors_count' => count($errors),
                'current_step' => $this->currentStep,
            ]);
        }
    }
    
    /**
     * Criar roles padrão para um novo tenant (Sistema de Níveis)
     * 
     * @deprecated Use createDefaultRolesForTenant() helper function instead
     */
    protected function createDefaultRolesForTenant($tenantId)
    {
        // Usar função helper global
        createDefaultRolesForTenant($tenantId);
    }
    
    public function previousStep()
    {
        // Se usuário logado, não deixar voltar para o passo 1
        $minStep = $this->isLoggedIn ? 2 : 1;

        if ($this->currentStep <= $minStep) {
            return;
        }

        $anterior = $this->currentStep - 1;

        // Saltar para trás os passos que não se aplicam, senão o botão
        // "Anterior" levava a um ecrã vazio que o "Seguinte" volta a saltar —
        // e a pessoa ficava presa a saltitar entre os dois.
        if ($anterior === 3 && !$this->temPassoDePlano) {
            $anterior = 2;
        }

        $this->currentStep = max($minStep, $anterior);

        $this->saveWizardProgress();
    }

    /**
     * Voltar a escolher o plano, mesmo tendo vindo escolhido do link.
     *
     * Não perguntar duas vezes não pode virar não deixar mudar de ideias.
     */
    public function escolherOutroPlano(): void
    {
        $this->planoVeioDoLink = false;
        $this->currentStep = 3;
        $this->saveWizardProgress();
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
                    'is_super_admin' => false, // Usuário do tenant, não é super admin do sistema
                ]);
                \Log::info('Usuário criado', ['user_id' => $user->id, 'is_super_admin' => false]);
            }
            
            // 2. Criar tenant/empresa
            \Log::info('Criando tenant/empresa...', ['company_name' => $this->company_name]);
            $tenant = Tenant::create([
                'name' => $this->company_name,
                'company_name' => $this->company_name,
                'nif' => $this->company_nif,
                // O regime tem de entrar NA criação: é ele que decide os
                // impostos com que a empresa é provisionada. Corrigir depois
                // já obriga o TaxRegimeSyncer a reescrever tudo.
                'regime' => Tenant::canonicalRegime($this->company_regime),
                'address' => $this->company_address,
                'phone' => $this->company_phone,
                'email' => $this->company_email ?: $this->email,
                'is_active' => true,
            ]);
            \Log::info('Tenant criado', ['tenant_id' => $tenant->id]);
            
            // 3. Vincular usuário ao tenant como owner/admin do tenant
            \Log::info('Vinculando usuário ao tenant como Admin do Tenant...');
            $user->tenants()->attach($tenant->id, [
                'is_active' => true,
                'joined_at' => now(),
            ]);
            
            // 3.1 Definir tenant como ativo para o usuário
            $user->tenant_id = $tenant->id;
            $user->save();
            
            // 3.2 Criar roles padrão para o tenant
            setPermissionsTeamId($tenant->id);
            
            // Criar roles padrão do tenant
            $this->createDefaultRolesForTenant($tenant->id);
            
            // Atribuir role 'Super Admin' ao dono do tenant
            $superAdminRole = \Spatie\Permission\Models\Role::where('name', 'Super Admin')
                ->where('tenant_id', $tenant->id)
                ->first();
            
            if ($superAdminRole) {
                $user->assignRole($superAdminRole);
            }
            
            \Log::info('Usuário vinculado ao tenant como Admin do Tenant (role: Super Admin)', [
                'user_tenant_id' => $user->tenant_id,
                'role' => 'Super Admin',
                'role_id' => $superAdminRole->id,
                'is_system_super_admin' => false
            ]);
            
            // 3.3 Criar dados padrão de contabilidade
            \Log::info('Criando dados padrão de contabilidade...');
            initializeAccountingDataForTenant($tenant->id);
            \Log::info('Dados de contabilidade criados com sucesso');
            
            // Plano gratuito não leva dados de pagamento. Podem ter ficado de
            // uma escolha anterior — o assistente guarda o progresso — e uma
            // referência esquecida punha a conta a "aguardar aprovação" de um
            // pagamento de 0 Kz que nunca ninguém iria aprovar.
            if ($this->naoHaNadaAPagar) {
                $this->payment_reference = '';
                $this->payment_proof     = null;
            }

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
            
            // Cortesia é uma só, para sempre: quem já teve o plano gratuito ou
            // já gastou um período de teste subscreve à mesma, mas a pagar. O
            // plano fica pendente em vez de arrancar sozinho.
            //
            // Sem isto o sistema oferecia-se em ciclo: 90 dias de FOX
            // Friendly, 30 de teste do Business, 30 do Enterprise, 14 do
            // Pacote Vendas — meses de ERP completo sem uma factura.
            $direito = DireitoACortesia::de($user, $tenant->nif);

            // Determinar status baseado em pagamento e configuração do plano
            $hasPaidProof = $this->payment_reference || $paymentProofPath;
            $trialDays = (int) $plan->trial_days; // Converter para inteiro
            $hasTrialPeriod = $trialDays > 0 && $direito->temDireitoATeste($plan);
            $autoActivate = (bool) $plan->auto_activate; // Ativação automática

            if ($trialDays > 0 && !$hasTrialPeriod) {
                \Log::info('Período de teste não concedido — cortesia já utilizada', [
                    'tenant_id'    => $tenant->id,
                    'plan'         => $plan->slug,
                    'ja_gratuito'  => $direito->jaTeveGratuito(),
                    'ja_teste'     => $direito->jaTeveTeste(),
                ]);
            }
            
            // Lógica de ativação
            $now = now();
            
            if ($hasPaidProof) {
                // PAGOU: aguarda aprovação
                $status = 'pending';
                $trialEndsAt = null;
                $periodStart = null;
                $periodEnd = null;
                \Log::info('Aguardando aprovação de pagamento');

            } elseif ($hasTrialPeriod && ($autoActivate || (float) ($plan->price_monthly ?? 0) <= 0)) {
                // TRIAL só para planos GRATUITOS ou marcados para auto-ativação.
                // ANTES bastava trial_days > 0 e qualquer plano PAGO era activado
                // sem pagamento e sem aprovação do Super Admin (furo de receita:
                // Enterprise 49.900 Kz/mês entregue de borla por 30 dias).
                $status = 'trial';
                $trialEndsAt = $now->copy()->addDays($trialDays);
                $periodStart = $now;
                $periodEnd = $trialEndsAt;
                \Log::info('Trial iniciado (plano gratuito/auto-ativável)', ['trial_days' => $trialDays]);

            } elseif ($autoActivate && $trialDays === 0) {
                // Plano auto-activável que nunca teve período de teste: activa
                // 30 dias de cortesia.
                //
                // A condição era `!$hasTrialPeriod`, que passou a significar
                // duas coisas diferentes — "o plano não tem teste" e "o teste
                // foi negado". Na segunda, isto entregava 30 dias grátis a
                // quem tinha acabado de os perder.
                $status = 'active';
                $trialEndsAt = null;
                $periodStart = $now;
                $periodEnd = $now->copy()->addDays(30);
                \Log::info('Ativação automática sem trial', ['days' => 30]);

            } else {
                // SEM TRIAL e SEM AUTO-ATIVAÇÃO: aguarda aprovação
                $status = 'pending';
                $trialEndsAt = null;
                $periodStart = null;
                $periodEnd = null;
                \Log::info('Aguardando aprovação manual');
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

            // Avisar o dono da plataforma que entrou uma empresa nova, e com
            // que plano. Sem isto, quem descobria um cliente novo era quem se
            // lembrasse de abrir a lista de empresas.
            try {
                app(\App\Services\Billing\AvisoDePagamentoPendente::class)
                    ->empresaRegistada($tenant, $plan, $status);
            } catch (\Throwable $e) {
                // Ninguém perde a conta por causa de um aviso.
                \Log::warning('Aviso de empresa registada falhou', ['erro' => $e->getMessage()]);
            }
            
            // 6. Criar pedido (Order). Se a subscription foi auto-activada (trial via auto_activate),
            //    o pedido nasce 'approved' para não ficar pendente no painel do Super Admin.
            $isAutoActivatedTrial = $autoActivate && !$hasPaidProof && $hasTrialPeriod;
            $isAutoActivatedDirect = $autoActivate && !$hasPaidProof && !$hasTrialPeriod;
            $orderStatus = ($isAutoActivatedTrial || $isAutoActivatedDirect) ? 'approved' : 'pending';
            $orderNotes = $isAutoActivatedTrial
                ? "Pedido auto-aprovado. Plano '{$plan->name}' com {$trialDays} dias de trial gratuito (auto_activate=true). Empresa: {$tenant->name}"
                : ($isAutoActivatedDirect
                    ? "Pedido auto-aprovado. Plano '{$plan->name}' activado directamente (auto_activate=true). Empresa: {$tenant->name}"
                    : "Pedido criado via wizard de registro. Empresa: {$tenant->name}");

            $order = Order::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'amount' => $plan->price_monthly,
                'payment_method' => $this->payment_method,
                'payment_reference' => $this->payment_reference,
                'payment_proof' => $paymentProofPath,
                'status' => $orderStatus,
                'approved_at' => $orderStatus === 'approved' ? $now : null,
                'approved_by' => null, // Sistema (auto-aprovação)
                'notes' => $orderNotes,
            ]);

            \Log::info('Order criada', [
                'order_id' => $order->id,
                'status' => $orderStatus,
                'auto_activated' => $isAutoActivatedTrial || $isAutoActivatedDirect,
            ]);
            
            // 7. Ativar módulos do plano.
            //    Via TenantModuleSyncService: garante as DEPENDÊNCIAS (Faturação
            //    ⇒ Tesouraria), os PRÉ-REQUISITOS de dados (métodos de pagamento,
            //    impostos, armazém, configurações) e as PERMISSÕES nos papéis.
            //    O attach directo que existia aqui deixava empresas com módulos
            //    "activos" mas inutilizáveis (ex.: sem formas de pagamento).
            \Log::info('Ativando módulos do plano...', ['included_modules' => $plan->included_modules]);

            $moduleSlugs = is_array($plan->included_modules) ? $plan->included_modules : [];
            if (empty($moduleSlugs)) {
                // Recurso: usar a relação plan_module quando o JSON não está preenchido
                $moduleSlugs = $plan->modules()->pluck('modules.slug')->toArray();
            }

            // Só activar módulos quando a subscrição já dá direito a usar o
            // sistema. Se ficou 'pending' (plano pago à espera da aprovação do
            // Super Admin), os módulos NÃO são activados — era assim que um
            // registo por aprovar já nascia com tudo ligado.
            if (!in_array($status, ['trial', 'active'], true)) {
                \Log::info('Subscrição pendente de aprovação — módulos NÃO activados', [
                    'tenant_id' => $tenant->id,
                    'status'    => $status,
                ]);
            } elseif (!empty($moduleSlugs)) {
                $sync = new \App\Services\Tenant\TenantModuleSyncService();
                foreach ($moduleSlugs as $moduleSlug) {
                    $sync->activateModule($tenant, $moduleSlug);
                }
                \Log::info('Módulos ativados (com dependências e pré-requisitos)', [
                    'tenant_id' => $tenant->id,
                    'modules'   => $moduleSlugs,
                ]);
            } else {
                \Log::warning('Plano sem módulos incluídos', ['plan_id' => $plan->id]);
            }
            
            \Log::info('Commit da transação...');
            DB::commit();

            // Registo interno da conversão: continua disponível mesmo quando
            // o browser bloqueia o Pixel da Meta.
            $acquisition = session('registration_acquisition', []);
            try {
                $event = [
                    'visitor_id' => session('registration_visitor_id', (string) \Illuminate\Support\Str::uuid()),
                    'session_id' => (string) \Illuminate\Support\Str::uuid(),
                    'type' => 'conversion',
                    'event_name' => 'complete_registration',
                    'url' => request()->fullUrl(),
                    'path' => '/register',
                    'referrer' => request()->headers->get('referer'),
                    'utm_source' => $acquisition['utm_source'] ?? null,
                    'utm_medium' => $acquisition['utm_medium'] ?? null,
                    'utm_campaign' => $acquisition['utm_campaign'] ?? null,
                    'utm_term' => $acquisition['utm_term'] ?? null,
                    'utm_content' => $acquisition['utm_content'] ?? null,
                    'ip' => request()->ip(),
                    'user_id' => $user->id,
                    'meta' => [
                        'tenant_id' => $tenant->id,
                        'plan_id' => $plan->id,
                        'plan_slug' => $plan->slug,
                        'subscription_status' => $status,
                        'fbclid' => $acquisition['fbclid'] ?? null,
                        'gclid' => $acquisition['gclid'] ?? null,
                    ],
                    'user_agent' => substr((string) request()->userAgent(), 0, 500),
                    'created_at' => now(),
                ];
                if (\Illuminate\Support\Facades\Schema::hasColumn('analytics_events', 'tenant_id')) {
                    $event['tenant_id'] = $tenant->id;
                }
                \App\Models\AnalyticsEvent::create($event);
            } catch (\Throwable $trackingError) {
                \Log::warning('Falha ao guardar conversão de cadastro', ['erro' => $trackingError->getMessage()]);
            }

            session()->flash('meta_registration_completed', [
                'event_id' => 'registration-' . $tenant->id . '-' . \Illuminate\Support\Str::uuid(),
                'plan' => $plan->slug,
                'status' => $status,
            ]);
            session()->forget(['registration_acquisition', 'registration_visitor_id']);
            \Log::info('Transação commitada com sucesso!');
            
            // ========================================
            // ENVIAR EMAIL DE BOAS-VINDAS
            // ========================================
            \Log::info('🎯🎯🎯 CHECKPOINT: Chegou no bloco de envio de email! 🎯🎯🎯');
            \Log::info('DEBUG: Dados do usuário antes do email', [
                'user_existe' => isset($user),
                'user_id' => $user->id ?? 'NULL',
                'user_email' => $user->email ?? 'NULL',
                'user_name' => $user->name ?? 'NULL',
            ]);
            
            \Log::info('DEBUG: Dados do tenant antes do email', [
                'tenant_existe' => isset($tenant),
                'tenant_id' => $tenant->id ?? 'NULL',
                'tenant_name' => $tenant->name ?? 'NULL',
            ]);
            
            // ✅ ENVIAR EMAIL SIMPLES COM CONFIGURAÇÃO SSL DIRETA
            try {
                \Log::info('📧 Enviando email de boas-vindas com SSL direto');
                $this->sendSimpleWelcomeEmail($user, $tenant);
                \Log::info('✅ Email de boas-vindas enviado com sucesso');
            } catch (\Exception $emailError) {
                \Log::error('❌ Erro ao enviar email de boas-vindas', [
                    'error' => $emailError->getMessage(),
                    'trace' => $emailError->getTraceAsString(),
                ]);
            }
            
            \Log::info('🏁 CHECKPOINT: Saiu do bloco de envio de email');
            
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
    
    /**
     * Enviar email de boas-vindas usando TEMPLATE DO BANCO + SMTP DO BANCO
     * Usa template 'welcome' e configuração SMTP do banco de dados
     */
    private function sendSimpleWelcomeEmail($user, $tenant)
    {
        \Log::info('🔧 Buscando configuração SMTP do banco de dados');
        
        // BUSCAR CONFIGURAÇÃO SMTP DO BANCO (em vez de hardcoded)
        $smtpSetting = \App\Models\SmtpSetting::getForTenant(null);
        
        if (!$smtpSetting) {
            \Log::error('❌ Configuração SMTP não encontrada no banco');
            throw new \Exception('Configuração SMTP não encontrada');
        }
        
        \Log::info('📧 Configuração SMTP encontrada', [
            'host' => $smtpSetting->host,
            'port' => $smtpSetting->port,
            'encryption' => $smtpSetting->encryption,
            'from' => $smtpSetting->from_email,
        ]);
        
        // CONFIGURAR SMTP usando método configure() do modelo
        $smtpSetting->configure();
        
        \Log::info('✅ SMTP configurado do banco de dados');
        
        // BUSCAR TEMPLATE WELCOME DO BANCO
        $template = EmailTemplate::where('slug', 'welcome')->first();
        
        if (!$template) {
            \Log::error('❌ Template welcome não encontrado');
            throw new \Exception('Template welcome não encontrado');
        }
        
        \Log::info('📄 Template welcome encontrado', [
            'id' => $template->id,
            'subject' => $template->subject,
        ]);
        
        // Dados para o template
        $data = [
            'user_name' => $user->name,
            'tenant_name' => $tenant->name,
            'app_name' => config('app.name', 'SOS ERP'),
            'app_url' => config('app.url'),
            'support_email' => 'sos@soserp.vip',
            'login_url' => route('login'),
        ];
        
        // Renderizar template do BD
        $rendered = $template->render($data);
        
        \Log::info('📧 Template renderizado do BD', [
            'subject' => $rendered['subject'],
            'body_length' => strlen($rendered['body_html']),
        ]);
        
        // Enviar email usando HTML DO TEMPLATE
        \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($user, $rendered) {
            $message->to($user->email, $user->name)
                    ->subject($rendered['subject'])
                    ->html($rendered['body_html']);
        });
        
        \Log::info('✅ Email de boas-vindas enviado via template do BD', [
            'to' => $user->email,
            'subject' => $rendered['subject'],
            'template_id' => $rendered['subject'],
        ]);
    }
    
    /**
     * Criar dados padrão de contabilidade para novo tenant
     */
    private function createDefaultAccountingData($tenantId)
    {
        try {
            // Criar Plano de Contas (71 contas SNC Angola)
            $accountSeeder = new \Database\Seeders\Accounting\AccountSeeder();
            $accountSeeder->runForTenant($tenantId);
            
            // Criar Diários padrão (6 diários)
            $journalSeeder = new \Database\Seeders\Accounting\JournalSeeder();
            $journalSeeder->runForTenant($tenantId);
            
            // Criar Períodos para o ano atual
            $periodSeeder = new \Database\Seeders\Accounting\PeriodSeeder();
            $periodSeeder->runForTenant($tenantId);
            
            \Log::info('✅ Dados de contabilidade criados', [
                'tenant_id' => $tenantId,
                'contas' => 71,
                'diarios' => 6,
                'periodos' => 12
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao criar dados de contabilidade', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            // Não lançar exceção para não quebrar o registro
        }
    }
    
    public function render()
    {
        return view('livewire.auth.register-wizard')
            ->layout('components.layouts.guest');
    }
}
