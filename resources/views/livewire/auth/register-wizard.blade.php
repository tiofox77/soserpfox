<div class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-4xl">
        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="{{ route('landing.home') }}" class="inline-flex items-center mb-4">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-chart-line text-white text-2xl"></i>
                </div>
                <span class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">SOSERP</span>
            </a>
        </div>

        <!-- Messages -->
        @if (session()->has('error'))
            <div class="mb-6 bg-red-50 border-2 border-red-500 rounded-2xl p-4 text-red-800">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-2xl mr-3"></i>
                    <div>
                        <strong>Erro:</strong> {{ session('error') }}
                    </div>
                </div>
            </div>
        @endif

        @if (session()->has('warning'))
            <div class="mb-6 bg-yellow-50 border-2 border-yellow-500 rounded-2xl p-4 text-yellow-800">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-2xl mr-3"></i>
                    <div>
                        <strong>Atenção:</strong> {{ session('warning') }}
                    </div>
                </div>
            </div>
        @endif

        @if (session()->has('info'))
            <div class="mb-6 bg-blue-50 border-2 border-blue-500 rounded-2xl p-4 text-blue-800">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-2xl mr-3"></i>
                    <div>
                        <strong>Informação:</strong> {{ session('info') }}
                    </div>
                </div>
            </div>
        @endif

        <!-- Wizard Card -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Progress Steps -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-8 py-6">
                <div class="flex items-center justify-between mb-4">
                    @if($isLoggedIn)
                        <!-- Mensagem para usuário logado -->
                        <div class="inline-flex items-center px-4 py-2 bg-white/20 backdrop-blur-sm rounded-full text-white">
                            <i class="fas fa-user-check mr-2"></i>
                            <span class="text-sm font-semibold">Logado como: {{ $name }}</span>
                        </div>
                    @else
                        <div></div>
                    @endif
                    
                    <div class="flex items-center gap-3">
                        <!-- Indicador de Progresso Salvo -->
                        @php
                            $savedProgress = session('wizard_progress');
                            $savedAt = $savedProgress['saved_at'] ?? null;
                        @endphp
                        @if($savedAt)
                            <div class="inline-flex items-center px-3 py-1 bg-green-500/20 backdrop-blur-sm rounded-full text-white text-xs">
                                <i class="fas fa-check-circle mr-1"></i>
                                Progresso salvo
                            </div>
                        @endif
                        
                        <!-- Botão Recomeçar -->
                        <button wire:click="restartWizard" 
                                onclick="return confirm('Deseja realmente recomeçar? Todo o progresso será perdido.')"
                                class="inline-flex items-center px-3 py-1 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full text-white text-xs transition"
                                title="Recomeçar wizard do zero">
                            <i class="fas fa-redo mr-1"></i>
                            Recomeçar
                        </button>
                    </div>
                </div>
                
                <div class="flex items-center justify-between max-w-2xl mx-auto">
                    @if(!$isLoggedIn)
                        <!-- Step 1 (apenas para usuários não logados) -->
                        <div class="flex items-center flex-1">
                            <div class="relative">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-lg {{ $currentStep >= 1 ? 'bg-white text-purple-600' : 'bg-white/30 text-white' }} transition">
                                    @if($currentStep > 1)
                                        <i class="fas fa-check"></i>
                                    @else
                                        1
                                    @endif
                                </div>
                            </div>
                            <div class="ml-3 text-white">
                                <div class="text-sm font-semibold">Passo 1</div>
                                <div class="text-xs opacity-90">Seus Dados</div>
                            </div>
                        </div>

                        <!-- Line -->
                        <div class="flex-1 h-1 {{ $currentStep >= 2 ? 'bg-white' : 'bg-white/30' }} mx-4 transition"></div>
                    @endif

                    <!-- Step 2 -->
                    <div class="flex items-center flex-1">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-lg {{ $currentStep >= 2 ? 'bg-white text-purple-600' : 'bg-white/30 text-white' }} transition">
                                @if($currentStep > 2)
                                    <i class="fas fa-check"></i>
                                @else
                                    {{ $isLoggedIn ? '1' : '2' }}
                                @endif
                            </div>
                        </div>
                        <div class="ml-3 text-white">
                            <div class="text-sm font-semibold">Passo {{ $isLoggedIn ? '1' : '2' }}</div>
                            <div class="text-xs opacity-90">Sua Empresa</div>
                        </div>
                    </div>

                    <!-- Line -->
                    <div class="flex-1 h-1 {{ $currentStep >= 3 ? 'bg-white' : 'bg-white/30' }} mx-4 transition"></div>

                    <!-- Step 3 -->
                    <div class="flex items-center flex-1">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-lg {{ $currentStep >= 3 ? 'bg-white text-purple-600' : 'bg-white/30 text-white' }} transition">
                                @if($currentStep > 3)
                                    <i class="fas fa-check"></i>
                                @else
                                    {{ $isLoggedIn ? '2' : '3' }}
                                @endif
                            </div>
                        </div>
                        <div class="ml-3 text-white">
                            <div class="text-sm font-semibold">Passo {{ $isLoggedIn ? '2' : '3' }}</div>
                            <div class="text-xs opacity-90">Escolha o Plano</div>
                        </div>
                    </div>

                    <!-- Line -->
                    <div class="flex-1 h-1 {{ $currentStep >= 4 ? 'bg-white' : 'bg-white/30' }} mx-4 transition"></div>

                    <!-- Step 4 -->
                    <div class="flex items-center flex-1">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-lg {{ $currentStep >= 4 ? 'bg-white text-purple-600' : 'bg-white/30 text-white' }} transition">
                                {{ $isLoggedIn ? '3' : '4' }}
                            </div>
                        </div>
                        <div class="ml-3 text-white">
                            <div class="text-sm font-semibold">Passo {{ $isLoggedIn ? '3' : '4' }}</div>
                            <div class="text-xs opacity-90">Pagamento</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <div class="p-8">
                <form wire:submit.prevent="register">
                    
                    <!-- STEP 1: User Data -->
                    @if($currentStep == 1)
                        <div class="max-w-2xl mx-auto">
                            <div class="text-center mb-8">
                                <h2 class="text-3xl font-bold text-gray-900">Crie sua conta</h2>
                                <p class="text-gray-600 mt-2">Comece informando seus dados pessoais</p>
                            </div>

                            <div class="space-y-6">
                                <!-- Name -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-user text-blue-500 mr-2"></i>Nome Completo *
                                    </label>
                                    <input wire:model="name" type="text" required
                                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('name') border-red-500 @enderror"
                                           placeholder="João Silva">
                                    @error('name')
                                        <p class="mt-2 text-sm text-red-600 flex items-center">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <!-- Email -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-envelope text-blue-500 mr-2"></i>Email *
                                    </label>
                                    <input wire:model="email" type="email" required
                                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('email') border-red-500 @enderror"
                                           placeholder="joao@empresa.ao">
                                    @error('email')
                                        <p class="mt-2 text-sm text-red-600 flex items-center">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <!-- Password -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-lock text-blue-500 mr-2"></i>Senha *
                                    </label>
                                    <input wire:model="password" type="password" required
                                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('password') border-red-500 @enderror"
                                           placeholder="Mínimo 6 caracteres">
                                    @error('password')
                                        <p class="mt-2 text-sm text-red-600 flex items-center">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <!-- Confirm Password -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-lock text-blue-500 mr-2"></i>Confirmar Senha *
                                    </label>
                                    <input wire:model="password_confirmation" type="password" required
                                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                           placeholder="Digite a senha novamente">
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- STEP 2: Company Data -->
                    @if($currentStep == 2)
                        <div class="max-w-2xl mx-auto">
                            <div class="text-center mb-8">
                                <h2 class="text-3xl font-bold text-gray-900">Dados da Empresa</h2>
                                <p class="text-gray-600 mt-2">Informe os dados da sua empresa</p>
                            </div>

                            <div class="space-y-6">
                                <!-- Company Name -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-building text-purple-500 mr-2"></i>Nome da Empresa *
                                    </label>
                                    <input wire:model="company_name" type="text" required
                                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition @error('company_name') border-red-500 @enderror"
                                           placeholder="Minha Empresa Lda">
                                    @error('company_name')
                                        <p class="mt-2 text-sm text-red-600 flex items-center">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <!-- NIF -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-id-card text-purple-500 mr-2"></i>NIF *
                                    </label>
                                    <input wire:model="company_nif" type="text" required
                                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition @error('company_nif') border-red-500 @enderror"
                                           placeholder="123456789">
                                    @error('company_nif')
                                        <p class="mt-2 text-sm text-red-600 flex items-center">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <!-- Address -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-map-marker-alt text-purple-500 mr-2"></i>Endereço
                                    </label>
                                    <input wire:model="company_address" type="text"
                                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition"
                                           placeholder="Rua exemplo, Luanda">
                                </div>

                                <!-- Phone & Email -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-phone text-purple-500 mr-2"></i>Telefone
                                        </label>
                                        <input wire:model="company_phone" type="text"
                                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition"
                                               placeholder="+244 923 456 789">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-envelope text-purple-500 mr-2"></i>Email da Empresa
                                        </label>
                                        <input wire:model="company_email" type="email"
                                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition"
                                               placeholder="contato@empresa.ao">
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- STEP 3: Plan Selection -->
                    @if($currentStep == 3)
                        <div class="max-w-4xl mx-auto">
                            <div class="text-center mb-8">
                                <h2 class="text-3xl font-bold text-gray-900">Escolha seu Plano</h2>
                                <p class="text-gray-600 mt-2">Selecione o plano ideal para seu negócio</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                @foreach($plans as $plan)
                                    <div wire:click="$set('selected_plan_id', {{ $plan->id }})" 
                                         class="cursor-pointer border-2 rounded-2xl p-6 transition {{ $selected_plan_id == $plan->id ? 'border-purple-500 bg-purple-50 shadow-lg scale-105' : 'border-gray-200 hover:border-purple-300' }}">
                                        
                                        @if($plan->is_featured)
                                            <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white text-xs font-bold px-3 py-1 rounded-full inline-block mb-3">
                                                <i class="fas fa-star mr-1"></i>POPULAR
                                            </div>
                                        @endif

                                        <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $plan->name }}</h3>
                                        <p class="text-gray-600 text-sm mb-4">{{ $plan->description }}</p>
                                        
                                        <div class="mb-4">
                                            <span class="text-3xl font-bold text-gray-900">{{ number_format($plan->price_monthly, 0) }}</span>
                                            <span class="text-gray-600 text-sm"> Kz/mês</span>
                                        </div>

                                        <ul class="space-y-2 text-sm mb-4">
                                            <li class="flex items-center text-gray-700">
                                                <i class="fas fa-check text-green-500 mr-2 text-xs"></i>
                                                {{ $plan->max_users }} Utilizadores
                                            </li>
                                            <li class="flex items-center text-gray-700">
                                                <i class="fas fa-check text-green-500 mr-2 text-xs"></i>
                                                {{ $plan->max_companies >= 999 ? 'Ilimitadas' : $plan->max_companies }} Empresas
                                            </li>
                                            <li class="flex items-center text-gray-700">
                                                <i class="fas fa-check text-green-500 mr-2 text-xs"></i>
                                                {{ $plan->trial_days }} dias grátis
                                            </li>
                                        </ul>

                                        @if($selected_plan_id == $plan->id)
                                            <div class="bg-purple-600 text-white text-center py-2 rounded-lg font-semibold">
                                                <i class="fas fa-check-circle mr-2"></i>Selecionado
                                            </div>
                                        @else
                                            <div class="bg-gray-100 text-gray-600 text-center py-2 rounded-lg font-semibold">
                                                Selecionar
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                        </div>
                    @endif

                    <!-- STEP 4: Payment Method -->
                    @if($currentStep == 4)
                        <div class="max-w-2xl mx-auto">
                            <div class="text-center mb-8">
                                <h2 class="text-3xl font-bold text-gray-900">Método de Pagamento</h2>
                                <p class="text-gray-600 mt-2">Como deseja efetuar o pagamento?</p>
                            </div>

                            <!-- Plano Selecionado Resumo -->
                            @php
                                $selectedPlan = $plans->firstWhere('id', $selected_plan_id);
                            @endphp
                            @if($selectedPlan)
                                <div class="mb-8 bg-gradient-to-r from-purple-500 to-pink-500 rounded-2xl p-6 text-white">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm opacity-90 mb-1">Plano Selecionado</div>
                                            <h3 class="text-2xl font-bold">{{ $selectedPlan->name }}</h3>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm opacity-90 mb-1">Valor Mensal</div>
                                            <div class="text-3xl font-bold">{{ number_format($selectedPlan->price_monthly, 0) }} Kz</div>
                                        </div>
                                    </div>
                                    <div class="mt-4 pt-4 border-t border-white/20">
                                        <div class="flex items-center text-sm">
                                            <i class="fas fa-gift mr-2"></i>
                                            {{ $selectedPlan->trial_days }} dias de teste grátis inclusos
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Método de Pagamento -->
                            <div class="mb-6">
                                <label class="block text-sm font-semibold text-gray-700 mb-4">
                                    <i class="fas fa-credit-card text-green-500 mr-2"></i>Selecione o Método de Pagamento
                                </label>
                                
                                <div class="space-y-3">
                                    <!-- Transferência Bancária -->
                                    <div wire:click="$set('payment_method', 'transfer')" 
                                         class="cursor-pointer border-2 rounded-xl p-4 transition {{ $payment_method === 'transfer' ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-green-300' }}">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center mr-4">
                                                    <i class="fas fa-university text-green-600 text-xl"></i>
                                                </div>
                                                <div>
                                                    <div class="font-bold text-gray-900">Transferência Bancária</div>
                                                    <div class="text-sm text-gray-600">Pagamento por transferência</div>
                                                </div>
                                            </div>
                                            @if($payment_method === 'transfer')
                                                <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dados Bancários -->
                            @if($payment_method === 'transfer')
                                <div class="mb-6 bg-blue-50 border-2 border-blue-200 rounded-xl p-6">
                                    <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                        Dados para Transferência
                                    </h4>
                                    
                                    <div class="space-y-3 text-sm">
                                        <div class="flex justify-between py-2 border-b border-blue-200">
                                            <span class="text-gray-600">Banco:</span>
                                            <span class="font-semibold text-gray-900">BAI - Banco Angolano de Investimentos</span>
                                        </div>
                                        <div class="flex justify-between py-2 border-b border-blue-200">
                                            <span class="text-gray-600">Titular:</span>
                                            <span class="font-semibold text-gray-900">SOSERP Sistemas Lda</span>
                                        </div>
                                        <div class="flex justify-between py-2 border-b border-blue-200">
                                            <span class="text-gray-600">IBAN:</span>
                                            <span class="font-semibold text-gray-900">AO06 0000 0000 1234 5678 9012 3</span>
                                        </div>
                                        <div class="flex justify-between py-2">
                                            <span class="text-gray-600">Valor:</span>
                                            <span class="font-bold text-green-600 text-lg">{{ number_format($selectedPlan->price_monthly ?? 0, 2) }} Kz</span>
                                        </div>
                                    </div>

                                    <div class="mt-4 p-3 bg-yellow-100 border border-yellow-300 rounded-lg">
                                        <p class="text-xs text-yellow-800">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            <strong>Importante:</strong> Após efetuar a transferência, insira a referência e anexe o comprovativo abaixo.
                                        </p>
                                    </div>
                                </div>

                                <!-- Referência da Transferência -->
                                <div class="mb-6">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-hashtag text-blue-500 mr-2"></i>Referência da Transferência
                                    </label>
                                    <input wire:model="payment_reference" type="text"
                                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                           placeholder="Ex: TRF123456789">
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>Número de referência da sua transferência bancária
                                    </p>
                                </div>

                                <!-- Upload Comprovativo -->
                                <div class="mb-6">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-file-upload text-blue-500 mr-2"></i>
                                        Comprovativo de Pagamento 
                                        @php
                                            $selectedPlan = $plans->firstWhere('id', $selected_plan_id);
                                            $isTrialPlan = $selectedPlan && $selectedPlan->trial_days > 0;
                                        @endphp
                                        @if(!$isTrialPlan)
                                            <span class="text-red-600">*</span>
                                        @else
                                            <span class="text-gray-500 text-xs">(Opcional - Período Trial)</span>
                                        @endif
                                    </label>
                                    
                                    @if(!$payment_proof)
                                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-400 transition">
                                            <i class="fas fa-cloud-upload-alt text-gray-400 text-4xl mb-3"></i>
                                            <p class="text-sm text-gray-600 mb-2">Arraste o ficheiro ou clique para selecionar</p>
                                            <p class="text-xs text-gray-500 mb-3">PDF, JPG ou PNG até 5MB</p>
                                            <input wire:model="payment_proof" type="file" id="payment_proof" class="hidden" accept=".pdf,.jpg,.jpeg,.png">
                                            <label for="payment_proof" class="cursor-pointer inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
                                                <i class="fas fa-upload mr-2"></i>Selecionar Ficheiro
                                            </label>
                                        </div>
                                    @else
                                        <div class="border-2 border-green-300 bg-green-50 rounded-xl p-4">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center">
                                                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                                        <i class="fas fa-file-alt text-green-600 text-xl"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-semibold text-gray-900">{{ $payment_proof->getClientOriginalName() }}</p>
                                                        <p class="text-xs text-gray-600">{{ number_format($payment_proof->getSize() / 1024, 2) }} KB</p>
                                                    </div>
                                                </div>
                                                <button wire:click="$set('payment_proof', null)" type="button" class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-times-circle text-xl"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                    
                                    @error('payment_proof')
                                        <p class="mt-2 text-sm text-red-600 flex items-center">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                    
                                    <p class="text-xs text-gray-500 mt-2">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        @if($isTrialPlan)
                                            Comprovativo opcional durante período trial
                                        @else
                                            Comprovativo obrigatório para planos pagos
                                        @endif
                                    </p>
                                    
                                    <div wire:loading wire:target="payment_proof" class="mt-2 text-sm text-blue-600">
                                        <i class="fas fa-spinner fa-spin mr-2"></i>Carregando ficheiro...
                                    </div>
                                </div>
                            @endif

                            <!-- Terms -->
                            <div class="p-4 bg-blue-50 rounded-xl border border-blue-200">
                                <label class="flex items-start cursor-pointer">
                                    <input type="checkbox" required
                                           class="w-4 h-4 mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-3 text-sm text-gray-700">
                                        Concordo com os <a href="#" class="text-blue-600 hover:text-blue-800 font-semibold">Termos de Serviço</a> 
                                        e <a href="#" class="text-blue-600 hover:text-blue-800 font-semibold">Política de Privacidade</a>
                                    </span>
                                </label>
                            </div>
                        </div>
                    @endif

                    <!-- Navigation Buttons -->
                    <div class="flex justify-between items-center mt-8 pt-6 border-t border-gray-200">
                        <div>
                            @if($currentStep > 1)
                                <button type="button" wire:click="previousStep"
                                        class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                                </button>
                            @else
                                <a href="{{ route('login') }}" class="px-6 py-3 text-gray-600 hover:text-gray-900 font-semibold">
                                    Já tem conta? <span class="underline">Entre aqui</span>
                                </a>
                            @endif
                        </div>

                        <div>
                            @if($currentStep < 4)
                                <button type="button" wire:click="nextStep"
                                        class="px-8 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl font-semibold hover:shadow-lg transition">
                                    Próximo <i class="fas fa-arrow-right ml-2"></i>
                                </button>
                            @else
                                <button type="submit"
                                        wire:loading.attr="disabled"
                                        class="px-8 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl font-semibold hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="register">
                                        <i class="fas fa-rocket mr-2"></i>Finalizar e Criar Conta
                                    </span>
                                    <span wire:loading wire:target="register">
                                        <i class="fas fa-spinner fa-spin mr-2"></i>Processando...
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Back to Landing & Restart -->
        <div class="flex items-center justify-between mt-6">
            <a href="{{ route('landing.home') }}" class="text-gray-600 hover:text-gray-900 text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Voltar para o site
            </a>
            
            @if(session('wizard_progress'))
                <button wire:click="restartWizard" 
                        onclick="return confirm('Tem certeza que deseja recomeçar? Todo o progresso será perdido.')"
                        class="text-red-600 hover:text-red-900 text-sm font-medium">
                    <i class="fas fa-redo mr-2"></i>Recomeçar do Início
                </button>
            @endif
        </div>
    </div>
</div>
