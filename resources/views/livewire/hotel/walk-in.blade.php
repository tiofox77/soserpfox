<div class="max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center justify-center gap-3">
            <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center">
                <i class="fas fa-walking text-white text-2xl"></i>
            </div>
            Walk-in Rápido
        </h1>
        <p class="text-gray-500 dark:text-gray-400 mt-2">Check-in imediato para hóspede sem reserva prévia</p>
    </div>

    {{-- Flash Messages --}}
    @if(session('error'))
    <div class="mb-4 p-4 bg-red-100 border border-red-200 text-red-700 rounded-xl flex items-center gap-2">
        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
    </div>
    @endif

    {{-- Progress Steps --}}
    @if($step < 4)
    <div class="flex items-center justify-center mb-8">
        @foreach([1 => 'Quarto', 2 => 'Hóspede', 3 => 'Confirmar'] as $num => $label)
        <div class="flex items-center">
            <div class="flex flex-col items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold transition-all
                    {{ $step >= $num ? 'bg-green-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500' }}">
                    @if($step > $num) <i class="fas fa-check"></i> @else {{ $num }} @endif
                </div>
                <span class="text-xs mt-1 {{ $step >= $num ? 'text-green-600 font-semibold' : 'text-gray-400' }}">{{ $label }}</span>
            </div>
            @if($num < 3)
            <div class="w-16 h-1 mx-2 rounded {{ $step > $num ? 'bg-green-500' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    {{-- Content Card --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        
        {{-- Step 1: Quarto --}}
        @if($step === 1)
        <div class="p-6">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2">
                <i class="fas fa-bed text-green-500"></i> Selecionar Quarto
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-semibold mb-2">Check-in</label>
                    <input type="date" wire:model.live="checkInDate" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:border-green-500 focus:outline-none dark:bg-gray-700">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Check-out</label>
                    <input type="date" wire:model.live="checkOutDate" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:border-green-500 focus:outline-none dark:bg-gray-700">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-semibold mb-2">Adultos</label>
                    <select wire:model.live="adults" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl dark:bg-gray-700">
                        @for($i = 1; $i <= 6; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Crianças</label>
                    <select wire:model.live="children" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl dark:bg-gray-700">
                        @for($i = 0; $i <= 4; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                    </select>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold mb-2">Tipo de Quarto</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($roomTypes as $rt)
                    <div wire:click="selectRoomType({{ $rt->id }})"
                         class="p-4 border-2 rounded-xl cursor-pointer transition-all hover:shadow-md
                                {{ $selectedRoomTypeId == $rt->id ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-600' }}">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-bold text-gray-800 dark:text-white">{{ $rt->name }}</p>
                                <p class="text-sm text-gray-500">{{ $rt->rooms_count }} disponíveis</p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold text-green-600">{{ number_format($rt->base_price, 0, ',', '.') }}</p>
                                <p class="text-xs text-gray-500">Kz/noite</p>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            @if($selectedRoomTypeId && $availableRooms && count($availableRooms) > 0)
            <div class="mb-6">
                <label class="block text-sm font-semibold mb-2">Quarto Disponível</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($availableRooms as $room)
                    <button type="button" wire:click="selectRoom({{ $room->id }})"
                            class="px-4 py-2 rounded-xl font-bold transition-all
                                   {{ $selectedRoomId == $room->id ? 'bg-green-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}">
                        {{ $room->number }}
                        @if($room->floor) <span class="text-xs opacity-75">({{ $room->floor }}º)</span> @endif
                    </button>
                    @endforeach
                </div>
            </div>
            @elseif($selectedRoomTypeId)
            <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl text-amber-700 dark:text-amber-300">
                <i class="fas fa-exclamation-triangle mr-2"></i> Nenhum quarto disponível para este tipo
            </div>
            @endif
        </div>
        @endif

        {{-- Step 2: Hóspede --}}
        @if($step === 2)
        <div class="p-6">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2">
                <i class="fas fa-user text-green-500"></i> Dados do Hóspede
            </h2>

            @if(!$selectedGuestId && !$isNewGuest)
            <div class="mb-6">
                <label class="block text-sm font-semibold mb-2">Pesquisar Hóspede</label>
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="guestSearch" placeholder="Nome, telefone, email ou documento..."
                           class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:border-green-500 focus:outline-none dark:bg-gray-700">
                    <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                </div>
                
                @if(count($foundGuests) > 0)
                <div class="mt-2 bg-white dark:bg-gray-700 border rounded-xl shadow-lg overflow-hidden">
                    @foreach($foundGuests as $g)
                    <div wire:click="selectGuest({{ $g->id }})" class="p-3 hover:bg-gray-50 dark:hover:bg-gray-600 cursor-pointer border-b last:border-0">
                        <p class="font-medium text-gray-800 dark:text-white">{{ $g->name }}</p>
                        <p class="text-sm text-gray-500">{{ $g->phone }} • {{ $g->email ?? 'Sem email' }}</p>
                    </div>
                    @endforeach
                </div>
                @endif

                <button wire:click="createNewGuest" class="mt-4 w-full py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl text-gray-600 dark:text-gray-400 hover:border-green-500 hover:text-green-600 transition">
                    <i class="fas fa-user-plus mr-2"></i> Criar Novo Hóspede
                </button>
            </div>
            @else
            <div class="space-y-4">
                @if($selectedGuestId && !$isNewGuest)
                <div class="bg-green-50 dark:bg-green-900/20 border-2 border-green-200 dark:border-green-800 rounded-xl p-4 mb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center text-white text-xl font-bold">
                                {{ strtoupper(substr($guestName, 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-bold text-green-800 dark:text-green-300">{{ $guestName }}</p>
                                <p class="text-sm text-green-600 dark:text-green-400">Hóspede existente</p>
                            </div>
                        </div>
                        <button wire:click="$set('selectedGuestId', '')" class="text-green-600 hover:text-green-800">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold mb-2">Nome Completo *</label>
                        <input type="text" wire:model="guestName" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:border-green-500 focus:outline-none dark:bg-gray-700" {{ !$isNewGuest && $selectedGuestId ? 'readonly' : '' }}>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Telefone *</label>
                        <input type="tel" wire:model="guestPhone" placeholder="+244 9XX XXX XXX" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:border-green-500 focus:outline-none dark:bg-gray-700">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Email</label>
                        <input type="email" wire:model="guestEmail" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:border-green-500 focus:outline-none dark:bg-gray-700">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Nº Documento</label>
                        <input type="text" wire:model="guestIdNumber" placeholder="BI / Passaporte" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:border-green-500 focus:outline-none dark:bg-gray-700">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Nacionalidade</label>
                        <input type="text" wire:model="guestNationality" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:border-green-500 focus:outline-none dark:bg-gray-700">
                    </div>
                </div>

                @if($isNewGuest)
                <button wire:click="$set('isNewGuest', false)" class="text-gray-500 hover:text-gray-700 text-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Voltar à pesquisa
                </button>
                @endif
            </div>
            @endif
        </div>
        @endif

        {{-- Step 3: Confirmação --}}
        @if($step === 3)
        <div class="p-6">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-2">
                <i class="fas fa-check-circle text-green-500"></i> Confirmar Check-in
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                {{-- Resumo --}}
                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4">
                    <h3 class="font-bold text-gray-700 dark:text-gray-300 mb-3">Resumo da Estadia</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Quarto:</span><span class="font-bold">{{ $availableRooms->firstWhere('id', $selectedRoomId)?->number ?? 'N/A' }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Check-in:</span><span class="font-bold">{{ \Carbon\Carbon::parse($checkInDate)->format('d/m/Y') }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Check-out:</span><span class="font-bold">{{ \Carbon\Carbon::parse($checkOutDate)->format('d/m/Y') }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Noites:</span><span class="font-bold">{{ $nights }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Hóspedes:</span><span class="font-bold">{{ $adults }} adulto(s){{ $children > 0 ? ", {$children} criança(s)" : '' }}</span></div>
                    </div>
                </div>

                {{-- Hóspede --}}
                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4">
                    <h3 class="font-bold text-gray-700 dark:text-gray-300 mb-3">Hóspede</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Nome:</span><span class="font-bold">{{ $guestName }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Telefone:</span><span class="font-bold">{{ $guestPhone }}</span></div>
                        @if($guestEmail)<div class="flex justify-between"><span class="text-gray-500">Email:</span><span class="font-bold">{{ $guestEmail }}</span></div>@endif
                        @if($guestIdNumber)<div class="flex justify-between"><span class="text-gray-500">Documento:</span><span class="font-bold">{{ $guestIdNumber }}</span></div>@endif
                    </div>
                </div>
            </div>

            {{-- Preço e Pagamento --}}
            <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 mb-6">
                <h3 class="font-bold text-green-700 dark:text-green-300 mb-3">Valor</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-green-600 mb-1">Diária (Kz)</label>
                        <input type="number" wire:model.live="roomRate" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    </div>
                    <div>
                        <label class="block text-xs text-green-600 mb-1">Desconto (Kz)</label>
                        <input type="number" wire:model.live="discount" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    </div>
                    <div>
                        <label class="block text-xs text-green-600 mb-1">Pago Agora (Kz)</label>
                        <input type="number" wire:model="paidAmount" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between text-lg">
                    <span class="text-green-700 dark:text-green-300 font-bold">Total:</span>
                    <span class="text-2xl font-black text-green-600">{{ number_format($totalAmount, 0, ',', '.') }} Kz</span>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-2">Observações / Pedidos Especiais</label>
                <textarea wire:model="specialRequests" rows="2" class="w-full px-4 py-3 border-2 border-gray-200 dark:border-gray-600 rounded-xl focus:border-green-500 focus:outline-none dark:bg-gray-700 resize-none" placeholder="Preferências do hóspede..."></textarea>
            </div>
        </div>
        @endif

        {{-- Step 4: Sucesso --}}
        @if($step === 4)
        <div class="p-8 text-center">
            <div class="w-20 h-20 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-check text-4xl text-green-500"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">Check-in Realizado!</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6">O hóspede foi registado com sucesso.</p>
            
            <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6 max-w-sm mx-auto text-left mb-6">
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b dark:border-gray-700"><span class="text-gray-500">Reserva</span><strong>{{ $reservation?->reservation_number }}</strong></div>
                    <div class="flex justify-between py-2 border-b dark:border-gray-700"><span class="text-gray-500">Quarto</span><strong>{{ $reservation?->room?->number }}</strong></div>
                    <div class="flex justify-between py-2 border-b dark:border-gray-700"><span class="text-gray-500">Hóspede</span><strong>{{ $guestName }}</strong></div>
                    <div class="flex justify-between py-2"><span class="text-gray-500">Total</span><strong class="text-green-600">{{ number_format($totalAmount, 0, ',', '.') }} Kz</strong></div>
                </div>
            </div>

            <div class="flex gap-4 justify-center">
                <button wire:click="newWalkIn" class="px-6 py-3 bg-green-600 text-white rounded-xl font-bold hover:bg-green-700 transition">
                    <i class="fas fa-plus mr-2"></i> Novo Walk-in
                </button>
                <a href="{{ route('hotel.reservations') }}" class="px-6 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-bold hover:bg-gray-200 transition">
                    <i class="fas fa-list mr-2"></i> Ver Reservas
                </a>
            </div>
        </div>
        @endif

        {{-- Footer / Navigation --}}
        @if($step < 4)
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t dark:border-gray-700 flex items-center justify-between">
            @if($step > 1)
            <button wire:click="previousStep" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold hover:bg-gray-300 transition">
                <i class="fas fa-arrow-left mr-2"></i> Voltar
            </button>
            @else
            <div></div>
            @endif

            @if($step < 3)
            <button wire:click="nextStep" class="px-6 py-3 bg-green-600 text-white rounded-xl font-semibold hover:bg-green-700 transition">
                Continuar <i class="fas fa-arrow-right ml-2"></i>
            </button>
            @else
            <button wire:click="submit" wire:loading.attr="disabled" class="px-6 py-3 bg-green-600 text-white rounded-xl font-semibold hover:bg-green-700 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="submit"><i class="fas fa-check-circle mr-2"></i> Confirmar Check-in</span>
                <span wire:loading wire:target="submit"><i class="fas fa-spinner fa-spin mr-2"></i> Processando...</span>
            </button>
            @endif
        </div>
        @endif
    </div>
</div>
