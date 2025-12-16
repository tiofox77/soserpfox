<div x-show="showBookingModal" x-cloak
     class="fixed inset-0 z-[100] overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="showBookingModal = false; document.body.style.overflow = 'auto'"></div>
    
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden" @click.stop>
            
            {{-- Header --}}
            <div class="sticky top-0 z-10 px-6 py-4 border-b bg-white flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">
                        @if($step == 1) Selecionar Quarto
                        @elseif($step == 2) Escolher Datas
                        @elseif($step == 3) Identificacao
                        @else Confirmacao
                        @endif
                    </h2>
                    <p class="text-sm text-gray-500">Passo {{ $step }} de 4</p>
                </div>
                <button @click="showBookingModal = false; document.body.style.overflow = 'auto'"
                        class="w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            {{-- Progress --}}
            <div class="px-6 py-3 bg-gray-50 border-b">
                <div class="flex items-center justify-between">
                    @for($i = 1; $i <= 4; $i++)
                    <div class="flex items-center {{ $i < 4 ? 'flex-1' : '' }}">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                                    {{ $step >= $i ? 'text-white' : 'bg-gray-200 text-gray-500' }}"
                             @if($step >= $i) style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#6366f1' }}, {{ $settings->secondary_color ?? '#8b5cf6' }})" @endif>
                            @if($step > $i) <i class="fas fa-check"></i> @else {{ $i }} @endif
                        </div>
                        @if($i < 4)<div class="flex-1 h-1 mx-2 rounded {{ $step > $i ? 'bg-green-500' : 'bg-gray-200' }}"></div>@endif
                    </div>
                    @endfor
                </div>
            </div>

            {{-- Content --}}
            <div class="p-6 overflow-y-auto" style="max-height: calc(90vh - 200px);">
                
                {{-- STEP 1: Quartos --}}
                @if($step == 1)
                <div class="space-y-4">
                    @foreach($roomTypes as $room)
                    <div wire:click="selectRoomType({{ $room->id }})"
                         class="p-4 border-2 rounded-xl cursor-pointer transition-all hover:shadow-md
                                {{ $selectedRoomType && $selectedRoomType->id == $room->id ? 'border-green-500 bg-green-50' : 'border-gray-200' }}">
                        <div class="flex gap-4">
                            <div class="w-24 h-24 rounded-lg overflow-hidden flex-shrink-0">
                                @if($room->featured_image)
                                <img src="{{ Storage::url($room->featured_image) }}" class="w-full h-full object-cover">
                                @else
                                <div class="w-full h-full bg-gray-100 flex items-center justify-center">
                                    <i class="fas fa-bed text-gray-300 text-2xl"></i>
                                </div>
                                @endif
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between">
                                    <div>
                                        <h3 class="font-bold">{{ $room->name }}</h3>
                                        <p class="text-sm text-gray-500"><i class="fas fa-user"></i> {{ $room->capacity }} pessoas</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xl font-bold" style="color: {{ $settings->primary_color ?? '#6366f1' }}">{{ number_format($room->base_price, 0, ',', '.') }}</p>
                                        <p class="text-xs text-gray-500">Kz/noite</p>
                                    </div>
                                </div>
                            </div>
                            @if($selectedRoomType && $selectedRoomType->id == $room->id)
                            <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white self-center">
                                <i class="fas fa-check"></i>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- STEP 2: Datas --}}
                @if($step == 2)
                <div class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Check-in</label>
                            <input type="date" wire:model.live="checkInDate" min="{{ now()->toDateString() }}"
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Check-out</label>
                            <input type="date" wire:model.live="checkOutDate" min="{{ now()->addDay()->toDateString() }}"
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Adultos</label>
                            <select wire:model.live="adults" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl">
                                @for($i = 1; $i <= 6; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Criancas</label>
                            <select wire:model.live="children" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl">
                                @for($i = 0; $i <= 4; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                            </select>
                        </div>
                    </div>
                    @if($selectedRoomType)
                    <div class="bg-indigo-50 border-2 border-indigo-200 rounded-xl p-4 flex justify-between items-center">
                        <div>
                            <p class="font-bold">{{ $selectedRoomType->name }}</p>
                            <p class="text-sm text-gray-600">{{ $nights }} noite(s)</p>
                        </div>
                        <p class="text-2xl font-bold" style="color: {{ $settings->primary_color ?? '#6366f1' }}">{{ number_format($this->getTotalPrice(), 0, ',', '.') }} Kz</p>
                    </div>
                    @endif
                </div>
                @endif

                {{-- STEP 3: Auth/Dados --}}
                @if($step == 3)
                <div class="space-y-6">
                    
                    {{-- Selecao de modo --}}
                    @if($authMode == 'select')
                    <div class="text-center mb-8">
                        <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center" style="background: {{ $settings->primary_color ?? '#6366f1' }}20">
                            <i class="fas fa-user text-2xl" style="color: {{ $settings->primary_color ?? '#6366f1' }}"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-2">Como deseja continuar?</h3>
                        <p class="text-gray-500">Escolha uma opcao para finalizar a reserva</p>
                    </div>
                    
                    <div class="space-y-3">
                        <button wire:click="setAuthMode('login')" class="w-full p-4 border-2 border-gray-200 rounded-xl hover:border-indigo-500 hover:bg-indigo-50 transition flex items-center gap-4">
                            <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-sign-in-alt text-indigo-600"></i>
                            </div>
                            <div class="text-left">
                                <p class="font-bold">Ja tenho conta</p>
                                <p class="text-sm text-gray-500">Entrar com telefone</p>
                            </div>
                        </button>
                        
                        <button wire:click="setAuthMode('register')" class="w-full p-4 border-2 border-gray-200 rounded-xl hover:border-green-500 hover:bg-green-50 transition flex items-center gap-4">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-user-plus text-green-600"></i>
                            </div>
                            <div class="text-left">
                                <p class="font-bold">Criar conta</p>
                                <p class="text-sm text-gray-500">Registar-se para futuras reservas</p>
                            </div>
                        </button>
                        
                        <button wire:click="continueAsGuest" class="w-full p-4 border-2 border-gray-200 rounded-xl hover:border-amber-500 hover:bg-amber-50 transition flex items-center gap-4">
                            <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-bolt text-amber-600"></i>
                            </div>
                            <div class="text-left">
                                <p class="font-bold">Reserva rapida</p>
                                <p class="text-sm text-gray-500">Continuar sem criar conta</p>
                            </div>
                        </button>
                    </div>
                    @endif

                    {{-- Login --}}
                    @if($authMode == 'login')
                    <div>
                        <button wire:click="setAuthMode('select')" class="text-gray-500 hover:text-gray-700 mb-4">
                            <i class="fas fa-arrow-left mr-2"></i>Voltar
                        </button>
                        <h3 class="text-xl font-bold mb-4">Entrar na sua conta</h3>
                        
                        @if($loginError)
                        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-4">{{ $loginError }}</div>
                        @endif
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2">Telefone</label>
                                <input type="tel" wire:model="loginPhone" placeholder="+244 923 456 789"
                                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Password (se tiver)</label>
                                <input type="password" wire:model="loginPassword" placeholder="Deixe em branco se nao tiver"
                                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                            </div>
                            <button wire:click="loginClient" class="w-full py-3 text-white rounded-xl font-bold" style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#6366f1' }}, {{ $settings->secondary_color ?? '#8b5cf6' }})">
                                Entrar
                            </button>
                        </div>
                    </div>
                    @endif

                    {{-- Registo --}}
                    @if($authMode == 'register')
                    <div>
                        <button wire:click="setAuthMode('select')" class="text-gray-500 hover:text-gray-700 mb-4">
                            <i class="fas fa-arrow-left mr-2"></i>Voltar
                        </button>
                        <h3 class="text-xl font-bold mb-4">Criar conta</h3>
                        
                        @if($registerError)
                        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-4">{{ $registerError }}</div>
                        @endif
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2">Nome completo *</label>
                                <input type="text" wire:model="registerName" placeholder="Seu nome"
                                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Telefone *</label>
                                <input type="tel" wire:model="registerPhone" placeholder="+244 923 456 789"
                                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Email</label>
                                <input type="email" wire:model="registerEmail" placeholder="email@exemplo.com"
                                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-2">Password</label>
                                    <input type="password" wire:model="registerPassword" placeholder="Opcional"
                                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-2">Confirmar</label>
                                    <input type="password" wire:model="registerPasswordConfirm" placeholder="Confirmar"
                                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                                </div>
                            </div>
                            <button wire:click="registerClient" class="w-full py-3 text-white rounded-xl font-bold" style="background: linear-gradient(135deg, #10b981, #059669)">
                                Criar conta e continuar
                            </button>
                        </div>
                    </div>
                    @endif

                    {{-- Guest / Reserva rapida --}}
                    @if($authMode == 'guest')
                    <div>
                        <button wire:click="setAuthMode('select')" class="text-gray-500 hover:text-gray-700 mb-4">
                            <i class="fas fa-arrow-left mr-2"></i>Voltar
                        </button>
                        <h3 class="text-xl font-bold mb-4">Dados para reserva</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2">Nome completo *</label>
                                <input type="text" wire:model.live="clientName" placeholder="Seu nome"
                                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                                @error('clientName')<span class="text-red-500 text-xs">{{ $message }}</span>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Telefone *</label>
                                <input type="tel" wire:model.live="clientPhone" placeholder="+244 923 456 789"
                                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                                @error('clientPhone')<span class="text-red-500 text-xs">{{ $message }}</span>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Email</label>
                                <input type="email" wire:model.live="clientEmail" placeholder="email@exemplo.com"
                                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Pedidos especiais</label>
                                <textarea wire:model.live="clientNotes" rows="2" placeholder="Preferencias..."
                                          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none resize-none"></textarea>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Autenticado --}}
                    @if($authMode == 'authenticated')
                    <div>
                        <div class="bg-green-50 border-2 border-green-200 rounded-xl p-4 mb-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center text-white text-xl font-bold">
                                        {{ strtoupper(substr($clientName, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-green-800">{{ $clientName }}</p>
                                        <p class="text-sm text-green-600">{{ $clientPhone }}</p>
                                    </div>
                                </div>
                                <button wire:click="logoutClient" class="text-green-600 hover:text-green-800">
                                    <i class="fas fa-sign-out-alt"></i> Sair
                                </button>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2">Email</label>
                                <input type="email" wire:model.live="clientEmail" placeholder="email@exemplo.com"
                                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Pedidos especiais</label>
                                <textarea wire:model.live="clientNotes" rows="2" placeholder="Preferencias..."
                                          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none resize-none"></textarea>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Resumo (mostra em guest e authenticated) --}}
                    @if(in_array($authMode, ['guest', 'authenticated']) && $selectedRoomType)
                    <div class="bg-gray-50 rounded-xl p-4 mt-6">
                        <h4 class="font-bold mb-3">Resumo</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-gray-600">Quarto:</span><span class="font-medium">{{ $selectedRoomType->name }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-600">Check-in:</span><span class="font-medium">{{ \Carbon\Carbon::parse($checkInDate)->format('d/m/Y') }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-600">Check-out:</span><span class="font-medium">{{ \Carbon\Carbon::parse($checkOutDate)->format('d/m/Y') }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-600">Noites:</span><span class="font-medium">{{ $nights }}</span></div>
                            <hr class="my-2">
                            <div class="flex justify-between text-lg"><span class="font-bold">Total:</span><span class="font-bold" style="color: {{ $settings->primary_color ?? '#6366f1' }}">{{ number_format($this->getTotalPrice(), 0, ',', '.') }} Kz</span></div>
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- STEP 4: Confirmacao --}}
                @if($step == 4)
                <div class="text-center py-8">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-check text-4xl text-green-500"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-2">Reserva Confirmada!</h3>
                    <p class="text-gray-600 mb-6">A sua reserva foi registada com sucesso.</p>
                    
                    <div class="bg-gray-50 rounded-xl p-6 max-w-sm mx-auto text-left">
                        <div class="space-y-3">
                            <div class="flex justify-between py-2 border-b"><span class="text-gray-500">Reserva</span><strong>{{ $reservationNumber }}</strong></div>
                            <div class="flex justify-between py-2 border-b"><span class="text-gray-500">Quarto</span><strong>{{ $confirmationData['roomType']->name ?? '' }}</strong></div>
                            <div class="flex justify-between py-2 border-b"><span class="text-gray-500">Check-in</span><strong>{{ \Carbon\Carbon::parse($confirmationData['checkIn'] ?? '')->format('d/m/Y') }}</strong></div>
                            <div class="flex justify-between py-2 border-b"><span class="text-gray-500">Check-out</span><strong>{{ \Carbon\Carbon::parse($confirmationData['checkOut'] ?? '')->format('d/m/Y') }}</strong></div>
                            <div class="flex justify-between py-2"><span class="text-gray-500">Total</span><strong style="color: {{ $settings->primary_color ?? '#6366f1' }}">{{ number_format($confirmationData['total'] ?? 0, 0, ',', '.') }} Kz</strong></div>
                        </div>
                    </div>
                    
                    <p class="text-sm text-gray-500 mt-6">Entraremos em contacto para confirmar os detalhes.</p>
                </div>
                @endif
            </div>

            {{-- Footer --}}
            @if($step < 4)
            <div class="sticky bottom-0 px-6 py-4 border-t bg-white flex items-center justify-between">
                @if($step > 1)
                <button wire:click="previousStep" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </button>
                @else
                <div></div>
                @endif

                @if($step < 3)
                <button wire:click="nextStep" 
                        class="px-6 py-3 text-white rounded-xl font-semibold transition disabled:opacity-50"
                        style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#6366f1' }}, {{ $settings->secondary_color ?? '#8b5cf6' }})"
                        {{ ($step == 1 && !$selectedRoomType) ? 'disabled' : '' }}>
                    Continuar<i class="fas fa-arrow-right ml-2"></i>
                </button>
                @elseif($step == 3 && in_array($authMode, ['guest', 'authenticated']))
                <button type="button" wire:click="submit" wire:loading.attr="disabled"
                        class="px-6 py-3 text-white rounded-xl font-semibold transition disabled:opacity-50"
                        style="background: linear-gradient(135deg, #10b981, #059669)">
                    <span wire:loading.remove wire:target="submit"><i class="fas fa-check-circle mr-2"></i>Confirmar Reserva</span>
                    <span wire:loading wire:target="submit"><i class="fas fa-spinner fa-spin mr-2"></i>A processar...</span>
                </button>
                @endif
            </div>
            @else
            <div class="sticky bottom-0 px-6 py-4 border-t bg-white">
                <button @click="showBookingModal = false; document.body.style.overflow = 'auto'; location.reload()"
                        class="w-full px-6 py-3 text-white rounded-xl font-semibold"
                        style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#6366f1' }}, {{ $settings->secondary_color ?? '#8b5cf6' }})">
                    <i class="fas fa-home mr-2"></i>Voltar ao Inicio
                </button>
            </div>
            @endif
        </div>
    </div>
</div>
