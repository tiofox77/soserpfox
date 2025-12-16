{{-- Booking Steps para o Modal --}}
<div class="p-6">
    {{-- Progress Steps --}}
    <div class="flex items-center justify-center mb-8">
        @foreach([1 => 'Serviços', 2 => 'Data/Hora', 3 => 'Dados', 4 => 'Confirmação'] as $num => $label)
            <div class="flex items-center">
                <button wire:click="goToStep({{ $num }})" 
                        class="flex flex-col items-center {{ $step >= $num ? 'cursor-pointer' : 'cursor-not-allowed' }}"
                        @if($step < $num) disabled @endif>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all
                        {{ $step >= $num ? 'shadow-lg' : 'bg-gray-200 text-gray-400' }}"
                        @if($step >= $num) style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%); color: white;" @endif>
                        @if($step > $num)
                            <i class="fas fa-check"></i>
                        @else
                            {{ $num }}
                        @endif
                    </div>
                    <span class="text-xs mt-1 whitespace-nowrap {{ $step >= $num ? 'text-gray-700 font-medium' : 'text-gray-400' }}">{{ $label }}</span>
                </button>
                @if($num < 4)
                    <div class="w-8 sm:w-12 h-0.5 mx-1 sm:mx-2 {{ $step > $num ? 'bg-pink-500' : 'bg-gray-200' }}"
                         @if($step > $num) style="background: {{ $settings->primary_color ?? '#ec4899' }}" @endif></div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Step 1: Serviços --}}
    @if($step === 1)
    <div>
        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
            <i class="fas fa-spa" style="color: {{ $settings->primary_color ?? '#ec4899' }}"></i>
            Escolha os Serviços
        </h2>

        {{-- Categorias --}}
        @if($categories->count() > 0)
        <div class="flex flex-wrap gap-2 mb-4">
            <button wire:click="selectCategory(null)"
                    class="px-3 py-1.5 rounded-full text-sm font-medium transition
                           {{ !$selectedCategory ? 'text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
                    @if(!$selectedCategory) style="background: {{ $settings->primary_color ?? '#ec4899' }}" @endif>
                Todos
            </button>
            @foreach($categories as $category)
                <button wire:click="selectCategory({{ $category->id }})"
                        class="px-3 py-1.5 rounded-full text-sm font-medium transition
                               {{ $selectedCategory == $category->id ? 'text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
                        @if($selectedCategory == $category->id) style="background: {{ $category->color ?? $settings->primary_color ?? '#ec4899' }}" @endif>
                    {{ $category->name }}
                </button>
            @endforeach
        </div>
        @endif

        {{-- Lista de Serviços --}}
        <div class="space-y-2 max-h-80 overflow-y-auto pr-2">
            @forelse($services as $service)
                <div wire:click="toggleService({{ $service->id }})"
                     class="flex items-center justify-between p-3 rounded-xl border-2 cursor-pointer transition-all
                            {{ in_array($service->id, $selectedServices) ? 'border-pink-500 bg-pink-50' : 'border-gray-200 hover:border-pink-300' }}"
                     style="{{ in_array($service->id, $selectedServices) ? 'border-color: ' . ($settings->primary_color ?? '#ec4899') . '; background-color: ' . ($settings->primary_color ?? '#ec4899') . '10;' : '' }}">
                    <div class="flex items-center gap-3">
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition
                                    {{ in_array($service->id, $selectedServices) ? 'bg-pink-500 border-pink-500' : 'border-gray-300' }}"
                             style="{{ in_array($service->id, $selectedServices) ? 'background-color: ' . ($settings->primary_color ?? '#ec4899') . '; border-color: ' . ($settings->primary_color ?? '#ec4899') . ';' : '' }}">
                            @if(in_array($service->id, $selectedServices))
                                <i class="fas fa-check text-white text-xs"></i>
                            @endif
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 text-sm">{{ $service->name }}</p>
                            <p class="text-xs text-gray-500">
                                <i class="fas fa-clock mr-1"></i>{{ $service->duration_formatted }}
                            </p>
                        </div>
                    </div>
                    <p class="font-bold text-sm" style="color: {{ $settings->primary_color ?? '#ec4899' }}">
                        {{ number_format($service->price, 0, ',', '.') }} Kz
                    </p>
                </div>
            @empty
                <div class="text-center py-8">
                    <i class="fas fa-spa text-4xl text-gray-200 mb-2"></i>
                    <p class="text-gray-500 text-sm">Nenhum serviço disponível</p>
                </div>
            @endforelse
        </div>

        {{-- Resumo e Botão --}}
        @if(count($selectedServices) > 0)
        <div class="mt-4 pt-4 border-t border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">{{ count($selectedServices) }} serviço(s)</p>
                    <p class="text-xl font-bold" style="color: {{ $settings->primary_color ?? '#ec4899' }}">
                        {{ number_format($this->totalPrice, 0, ',', '.') }} Kz
                    </p>
                </div>
                <button wire:click="nextStep" 
                        class="px-6 py-3 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition"
                        style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                    Continuar <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Step 2: Profissional e Data/Hora --}}
    @if($step === 2)
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Profissional --}}
        <div>
            <h3 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                <i class="fas fa-user" style="color: {{ $settings->primary_color ?? '#ec4899' }}"></i>
                Profissional
            </h3>
            <div class="space-y-2 max-h-48 overflow-y-auto pr-2">
                @foreach($professionals as $professional)
                    <div wire:click="selectProfessional({{ $professional->id }})"
                         class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all
                                {{ $selectedProfessional == $professional->id ? 'border-pink-500 bg-pink-50' : 'border-gray-200 hover:border-pink-300' }}"
                         style="{{ $selectedProfessional == $professional->id ? 'border-color: ' . ($settings->primary_color ?? '#ec4899') . '; background-color: ' . ($settings->primary_color ?? '#ec4899') . '10;' : '' }}">
                        @if($professional->photo)
                            <img src="{{ Storage::url($professional->photo) }}" alt="{{ $professional->name }}" 
                                 class="w-10 h-10 rounded-full object-cover">
                        @else
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold"
                                 style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                                {{ strtoupper(substr($professional->name, 0, 1)) }}
                            </div>
                        @endif
                        <div class="flex-1">
                            <p class="font-medium text-gray-900 text-sm">{{ $professional->nickname ?? $professional->name }}</p>
                            @if($professional->specialization)
                                <p class="text-xs text-gray-500">{{ $professional->specialization }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Data e Horário --}}
        <div>
            <h3 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                <i class="fas fa-calendar-alt" style="color: {{ $settings->primary_color ?? '#ec4899' }}"></i>
                Data e Horário
            </h3>

            {{-- Datas --}}
            <div class="mb-4">
                <div class="flex gap-2 overflow-x-auto pb-2">
                    @foreach(array_slice($this->availableDates, 0, 7) as $dateInfo)
                        <button wire:click="selectDate('{{ $dateInfo['date'] }}')"
                                class="flex-shrink-0 px-3 py-2 rounded-lg border-2 text-center transition min-w-[60px]
                                       {{ $selectedDate == $dateInfo['date'] ? 'text-white border-transparent' : 'border-gray-200 hover:border-pink-300' }}"
                                style="{{ $selectedDate == $dateInfo['date'] ? 'background: linear-gradient(135deg, ' . ($settings->primary_color ?? '#ec4899') . ' 0%, ' . ($settings->secondary_color ?? '#8b5cf6') . ' 100%)' : '' }}">
                            <p class="text-xs {{ $selectedDate == $dateInfo['date'] ? 'text-white/80' : 'text-gray-500' }}">
                                {{ Carbon\Carbon::parse($dateInfo['date'])->isoFormat('ddd') }}
                            </p>
                            <p class="text-sm font-bold">{{ Carbon\Carbon::parse($dateInfo['date'])->format('d') }}</p>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Horários --}}
            @if($selectedProfessional && $selectedDate)
                @if(count($this->availableSlots) > 0)
                    <div class="grid grid-cols-4 gap-1.5 max-h-32 overflow-y-auto">
                        @foreach($this->availableSlots as $slot)
                            <button wire:click="selectTime('{{ $slot }}')"
                                    class="py-2 rounded-lg border text-center text-xs font-medium transition
                                           {{ $selectedTime == $slot ? 'text-white border-transparent' : 'border-gray-200 hover:border-pink-300 text-gray-700' }}"
                                    style="{{ $selectedTime == $slot ? 'background: linear-gradient(135deg, ' . ($settings->primary_color ?? '#ec4899') . ' 0%, ' . ($settings->secondary_color ?? '#8b5cf6') . ' 100%)' : '' }}">
                                {{ $slot }}
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4 bg-gray-50 rounded-xl">
                        <p class="text-gray-500 text-sm">Sem horários disponíveis</p>
                    </div>
                @endif
            @else
                <div class="text-center py-4 bg-gray-50 rounded-xl">
                    <p class="text-gray-500 text-sm">Selecione um profissional</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Navegação Step 2 --}}
    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
        <button wire:click="previousStep" 
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition text-sm">
            <i class="fas fa-arrow-left mr-1"></i> Voltar
        </button>
        @if($selectedProfessional && $selectedDate && $selectedTime)
            <button wire:click="nextStep" 
                    class="px-6 py-3 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition"
                    style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                Continuar <i class="fas fa-arrow-right ml-2"></i>
            </button>
        @endif
    </div>
    @endif

    {{-- Step 3: Autenticação e Dados do Cliente --}}
    @if($step === 3)
    <div>
        {{-- Resumo --}}
        <div class="p-4 rounded-xl text-white mb-4"
             style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
            <div class="grid grid-cols-4 gap-2 text-xs">
                <div>
                    <p class="text-white/60">Serviços</p>
                    <p class="font-medium">{{ count($selectedServices) }}</p>
                </div>
                <div>
                    <p class="text-white/60">Profissional</p>
                    <p class="font-medium">{{ Str::limit($professionals->find($selectedProfessional)?->name ?? '-', 10) }}</p>
                </div>
                <div>
                    <p class="text-white/60">Data</p>
                    <p class="font-medium">{{ Carbon\Carbon::parse($selectedDate)->format('d/m') }}</p>
                </div>
                <div>
                    <p class="text-white/60">Hora</p>
                    <p class="font-medium">{{ $selectedTime }}</p>
                </div>
            </div>
            <div class="mt-2 pt-2 border-t border-white/20 flex justify-between items-center">
                <span class="text-sm">Total</span>
                <span class="text-lg font-bold">{{ number_format($this->totalPrice, 0, ',', '.') }} Kz</span>
            </div>
        </div>

        {{-- Se já está autenticado --}}
        @if($authMode === 'authenticated' && $authenticatedClient)
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold"
                             style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                            {{ strtoupper(substr($authenticatedClient->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="font-bold text-gray-900">{{ $authenticatedClient->name }}</p>
                            <p class="text-xs text-gray-500">{{ $authenticatedClient->phone ?? $authenticatedClient->mobile }}</p>
                        </div>
                    </div>
                    <button wire:click="logoutClient" class="text-xs text-gray-500 hover:text-red-500 transition">
                        <i class="fas fa-sign-out-alt mr-1"></i> Trocar
                    </button>
                </div>
                @if($authenticatedClient->is_vip)
                    <div class="mt-2 flex items-center gap-2 text-xs">
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">
                            <i class="fas fa-crown mr-1"></i> Cliente VIP
                        </span>
                        <span class="text-gray-500">{{ $authenticatedClient->loyalty_points }} pontos</span>
                    </div>
                @endif
            </div>

            {{-- Agendamentos anteriores --}}
            @if($this->clientAppointments->count() > 0)
                <div class="mb-4">
                    <p class="text-xs font-medium text-gray-500 mb-2">Seus últimos agendamentos</p>
                    <div class="space-y-1 max-h-24 overflow-y-auto">
                        @foreach($this->clientAppointments->take(3) as $apt)
                            <div class="flex justify-between items-center text-xs p-2 bg-gray-50 rounded-lg">
                                <span>{{ $apt->date->format('d/m/Y') }} {{ $apt->start_time }}</span>
                                <span class="px-2 py-0.5 rounded-full text-xs
                                    {{ $apt->status === 'completed' ? 'bg-green-100 text-green-700' : 
                                       ($apt->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') }}">
                                    {{ $apt->status === 'completed' ? 'Concluído' : ($apt->status === 'cancelled' ? 'Cancelado' : 'Agendado') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Observações --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Observações (opcional)</label>
                <textarea wire:model="clientNotes" rows="2" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent transition text-sm"
                          placeholder="Alguma informação adicional..."></textarea>
            </div>

        {{-- Selecionar modo de autenticação --}}
        @elseif($authMode === 'select')
            <div class="space-y-3">
                <p class="text-sm text-gray-600 text-center mb-4">Como deseja continuar?</p>
                
                {{-- Opção: Já tenho conta --}}
                <button wire:click="setAuthMode('login')"
                        class="w-full p-4 border-2 border-gray-200 rounded-xl hover:border-pink-300 hover:bg-pink-50 transition flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-user text-blue-600 text-lg"></i>
                    </div>
                    <div class="text-left flex-1">
                        <p class="font-bold text-gray-900">Já tenho conta</p>
                        <p class="text-xs text-gray-500">Entrar com meu telefone</p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400"></i>
                </button>

                {{-- Opção: Criar conta --}}
                <button wire:click="setAuthMode('register')"
                        class="w-full p-4 border-2 border-gray-200 rounded-xl hover:border-pink-300 hover:bg-pink-50 transition flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                        <i class="fas fa-user-plus text-green-600 text-lg"></i>
                    </div>
                    <div class="text-left flex-1">
                        <p class="font-bold text-gray-900">Criar conta</p>
                        <p class="text-xs text-gray-500">Registar e acumular pontos</p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400"></i>
                </button>

                {{-- Opção: Reserva rápida --}}
                <button wire:click="continueAsGuest"
                        class="w-full p-4 border-2 border-gray-200 rounded-xl hover:border-pink-300 hover:bg-pink-50 transition flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">
                        <i class="fas fa-bolt text-gray-600 text-lg"></i>
                    </div>
                    <div class="text-left flex-1">
                        <p class="font-bold text-gray-900">Reserva rápida</p>
                        <p class="text-xs text-gray-500">Continuar sem conta</p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400"></i>
                </button>
            </div>

        {{-- Formulário de Login --}}
        @elseif($authMode === 'login')
            <div class="space-y-3">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-gray-900">Entrar na conta</h3>
                    <button wire:click="setAuthMode('select')" class="text-xs text-gray-500 hover:text-gray-700">
                        <i class="fas fa-arrow-left mr-1"></i> Voltar
                    </button>
                </div>

                @if($loginError)
                    <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-xs">
                        <i class="fas fa-exclamation-circle mr-1"></i> {{ $loginError }}
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Telefone *</label>
                    <input wire:model="loginPhone" type="tel" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent transition text-sm"
                           placeholder="+244 923 456 789">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Password (se definida)</label>
                    <input wire:model="loginPassword" type="password" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent transition text-sm"
                           placeholder="Deixe vazio se não tiver">
                </div>

                <button wire:click="loginClient"
                        class="w-full py-3 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition"
                        style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                    <i class="fas fa-sign-in-alt mr-2"></i> Entrar
                </button>

                <p class="text-center text-xs text-gray-500">
                    Não tem conta? 
                    <button wire:click="setAuthMode('register')" class="text-pink-600 hover:underline">Criar agora</button>
                </p>
            </div>

        {{-- Formulário de Registo --}}
        @elseif($authMode === 'register')
            <div class="space-y-3">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-gray-900">Criar conta</h3>
                    <button wire:click="setAuthMode('select')" class="text-xs text-gray-500 hover:text-gray-700">
                        <i class="fas fa-arrow-left mr-1"></i> Voltar
                    </button>
                </div>

                @if($registerError)
                    <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-xs">
                        <i class="fas fa-exclamation-circle mr-1"></i> {{ $registerError }}
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Nome Completo *</label>
                    <input wire:model="registerName" type="text" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent transition text-sm"
                           placeholder="Seu nome">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Telefone *</label>
                    <input wire:model="registerPhone" type="tel" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent transition text-sm"
                           placeholder="+244 923 456 789">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Email (opcional)</label>
                    <input wire:model="registerEmail" type="email" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent transition text-sm"
                           placeholder="seu@email.com">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Password (opcional)</label>
                        <input wire:model="registerPassword" type="password" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent transition text-sm"
                               placeholder="Min 4 caracteres">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Confirmar</label>
                        <input wire:model="registerPasswordConfirm" type="password" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent transition text-sm"
                               placeholder="Repetir">
                    </div>
                </div>

                <button wire:click="registerClient"
                        class="w-full py-3 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition"
                        style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                    <i class="fas fa-user-plus mr-2"></i> Criar Conta e Continuar
                </button>

                <p class="text-center text-xs text-gray-500">
                    Já tem conta? 
                    <button wire:click="setAuthMode('login')" class="text-pink-600 hover:underline">Entrar</button>
                </p>
            </div>

        {{-- Formulário Guest (Reserva Rápida) --}}
        @elseif($authMode === 'guest')
            <div class="space-y-3">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-gray-900">Reserva Rápida</h3>
                    <button wire:click="setAuthMode('select')" class="text-xs text-gray-500 hover:text-gray-700">
                        <i class="fas fa-arrow-left mr-1"></i> Voltar
                    </button>
                </div>

                <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-blue-600 text-xs">
                    <i class="fas fa-info-circle mr-1"></i> 
                    Crie uma conta para acumular pontos e ter acesso ao histórico de agendamentos.
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Nome Completo *</label>
                    <input wire:model="clientName" type="text" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent transition text-sm"
                           placeholder="Seu nome">
                    @error('clientName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Telefone/WhatsApp *</label>
                    <input wire:model="clientPhone" type="tel" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent transition text-sm"
                           placeholder="+244 923 456 789">
                    @error('clientPhone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Email (opcional)</label>
                    <input wire:model="clientEmail" type="email" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent transition text-sm"
                           placeholder="seu@email.com">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Observações (opcional)</label>
                    <textarea wire:model="clientNotes" rows="2" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-transparent transition text-sm"
                              placeholder="Alguma informação adicional..."></textarea>
                </div>
            </div>
        @endif

        {{-- Navegação --}}
        <div class="flex justify-between mt-4 pt-4 border-t border-gray-100">
            <button wire:click="previousStep" 
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Voltar
            </button>
            @if($authMode === 'authenticated' || $authMode === 'guest')
                <button wire:click="submitBooking" 
                        class="px-6 py-3 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition"
                        style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                    <i class="fas fa-check mr-2"></i> Confirmar
                </button>
            @endif
        </div>
    </div>
    @endif

    {{-- Step 4: Confirmação --}}
    @if($step === 4)
    <div class="text-center">
        <div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center text-white"
             style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
            <i class="fas fa-check text-3xl"></i>
        </div>

        <h2 class="text-2xl font-bold text-gray-900 mb-2">Agendamento Confirmado!</h2>
        <p class="text-gray-500 text-sm mb-4">
            @if($settings->require_confirmation ?? true)
                O salão entrará em contato para confirmar.
            @else
                Seu agendamento foi confirmado.
            @endif
        </p>

        @if($appointmentNumber)
            <div class="inline-block px-4 py-2 bg-gray-100 rounded-lg mb-4">
                <p class="text-xs text-gray-500">Número</p>
                <p class="text-lg font-bold" style="color: {{ $settings->primary_color ?? '#ec4899' }}">{{ $appointmentNumber }}</p>
            </div>
        @endif

        {{-- Detalhes --}}
        @if($confirmationData)
        <div class="text-left bg-gray-50 rounded-xl p-4 mb-4 text-sm">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="text-gray-500 text-xs">Data</p>
                    <p class="font-medium">{{ Carbon\Carbon::parse($selectedDate)->isoFormat('D [de] MMM') }}</p>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Horário</p>
                    <p class="font-medium">{{ $selectedTime }}</p>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Profissional</p>
                    <p class="font-medium">{{ $confirmationData['professional']?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Total</p>
                    <p class="font-bold" style="color: {{ $settings->primary_color ?? '#ec4899' }}">{{ number_format($this->totalPrice, 0, ',', '.') }} Kz</p>
                </div>
            </div>
        </div>
        @endif

        {{-- Ações --}}
        <div class="flex flex-col gap-2">
            @if($settings->salon_whatsapp)
                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $settings->salon_whatsapp) }}?text=Olá! Acabei de fazer um agendamento ({{ $appointmentNumber }})"
                   target="_blank"
                   class="w-full px-4 py-3 bg-green-500 hover:bg-green-600 text-white rounded-xl font-bold transition flex items-center justify-center gap-2">
                    <i class="fab fa-whatsapp"></i> Falar no WhatsApp
                </a>
            @endif
            <button @click="showBookingModal = false; document.body.style.overflow = 'auto'; window.location.reload()"
                    class="w-full px-4 py-3 bg-gray-100 text-gray-700 rounded-xl font-bold hover:bg-gray-200 transition">
                Fechar
            </button>
        </div>
    </div>
    @endif
</div>
