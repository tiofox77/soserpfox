<div>
    <!-- Header com Gradient -->
    <div class="mb-6 bg-gradient-to-r from-red-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-map-marker-alt text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Locais de Eventos</h2>
                    <p class="text-red-100 text-sm">Gestão de locais e salas para eventos</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-red-600 hover:bg-red-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Local
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 stagger-animation">
        <div class="bg-white rounded-2xl shadow-lg p-6 card-hover card-zoom">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mr-4 shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-map-marked-alt text-white text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total de Locais</p>
                    <p class="text-2xl font-bold text-gray-900 counter-animation">{{ $venues->total() }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-lg p-6 card-hover card-zoom">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center mr-4 shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-check-circle text-white text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Ativos</p>
                    <p class="text-2xl font-bold text-gray-900 counter-animation">{{ $venues->where('is_active', true)->count() }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-lg p-6 card-hover card-zoom">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mr-4 shadow-lg shadow-purple-500/50 icon-float">
                    <i class="fas fa-users text-white text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Capacidade Total</p>
                    <p class="text-2xl font-bold text-gray-900 counter-animation">{{ number_format($venues->sum('capacity')) }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-lg p-6 card-hover card-zoom">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl flex items-center justify-center mr-4 shadow-lg shadow-orange-500/50 icon-float">
                    <i class="fas fa-calendar-alt text-white text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total de Eventos</p>
                    <p class="text-2xl font-bold text-gray-900 counter-animation">{{ $venues->sum('events_count') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="bg-white rounded-2xl shadow-lg p-4 mb-6">
        <div class="flex items-center gap-4">
            <div class="flex-1 relative">
                <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input wire:model.live="search" type="text" 
                       placeholder="Buscar por nome, cidade ou morada..." 
                       class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
            </div>
            @if($search)
            <button wire:click="$set('search', '')" class="px-4 py-3 bg-gray-100 text-gray-600 rounded-xl hover:bg-gray-200 transition">
                <i class="fas fa-times mr-2"></i>Limpar
            </button>
            @endif
        </div>
    </div>

    <!-- Venues Grid -->
    @if($venues->isEmpty())
    <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-map-marker-alt text-4xl text-gray-400"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Nenhum local encontrado</h3>
        <p class="text-gray-600 mb-6">Comece criando o primeiro local para seus eventos</p>
        <button wire:click="create" class="bg-gradient-to-r from-red-600 to-pink-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-red-700 hover:to-pink-700 shadow-lg transition">
            <i class="fas fa-plus mr-2"></i>Criar Primeiro Local
        </button>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 stagger-animation">
        @foreach($venues as $venue)
        <div class="group bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden border border-gray-100 card-hover card-zoom card-bounce">
            <!-- Header do Card -->
            <div class="bg-gradient-to-r from-red-50 to-pink-50 p-4 border-b border-gray-100">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-gray-900 group-hover:text-red-600 transition-colors">
                            {{ $venue->name }}
                        </h3>
                        @if($venue->city)
                        <p class="text-sm text-gray-600 mt-1 flex items-center">
                            <i class="fas fa-map-marker-alt text-red-500 mr-1"></i>
                            {{ $venue->city }}
                        </p>
                        @endif
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $venue->is_active ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-gray-100 text-gray-700 border border-gray-200' }}">
                        {{ $venue->is_active ? '✓ Ativo' : '✕ Inativo' }}
                    </span>
                </div>
            </div>
            
            <!-- Body do Card -->
            <div class="p-4">

            <div class="space-y-3 mb-4">
                @if($venue->address)
                <div class="flex items-start p-2 rounded-lg hover:bg-red-50 transition-colors">
                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0 mr-3">
                        <i class="fas fa-map-marker-alt text-red-600 text-sm"></i>
                    </div>
                    <span class="text-sm text-gray-700 leading-relaxed">{{ $venue->address }}</span>
                </div>
                @endif
                @if($venue->phone)
                <div class="flex items-center p-2 rounded-lg hover:bg-blue-50 transition-colors">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0 mr-3">
                        <i class="fas fa-phone text-blue-600 text-sm"></i>
                    </div>
                    <span class="text-sm text-gray-700">{{ $venue->phone }}</span>
                </div>
                @endif
                @if($venue->contact_person)
                <div class="flex items-center p-2 rounded-lg hover:bg-orange-50 transition-colors">
                    <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0 mr-3">
                        <i class="fas fa-user text-orange-600 text-sm"></i>
                    </div>
                    <span class="text-sm text-gray-700">{{ $venue->contact_person }}</span>
                </div>
                @endif
            </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 gap-3 mt-4">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-3 text-center border border-blue-200">
                        <div class="text-2xl font-bold text-blue-700">{{ $venue->events_count }}</div>
                        <div class="text-xs text-blue-600 mt-1">
                            <i class="fas fa-calendar-alt mr-1"></i>Eventos
                        </div>
                    </div>
                    @if($venue->capacity)
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-3 text-center border border-purple-200">
                        <div class="text-2xl font-bold text-purple-700">{{ $venue->capacity }}</div>
                        <div class="text-xs text-purple-600 mt-1">
                            <i class="fas fa-users mr-1"></i>Capacidade
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Footer do Card -->
            <div class="bg-gray-50 p-4 border-t border-gray-100">
                <div class="flex gap-2">
                    <button wire:click="edit({{ $venue->id }})" 
                            wire:loading.attr="disabled"
                            wire:target="edit({{ $venue->id }})"
                            class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2.5 rounded-xl hover:from-blue-600 hover:to-blue-700 transition font-semibold text-sm shadow-md disabled:opacity-50 disabled:cursor-not-allowed relative">
                        <span wire:loading.remove wire:target="edit({{ $venue->id }})">
                            <i class="fas fa-edit mr-2"></i>Editar
                        </span>
                        <span wire:loading wire:target="edit({{ $venue->id }})" class="flex items-center justify-center">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </button>
                    <button wire:click="delete({{ $venue->id }})" 
                            onclick="return confirm('Tem certeza que deseja excluir este local?')"
                            wire:loading.attr="disabled"
                            wire:target="delete({{ $venue->id }})"
                            class="bg-gradient-to-r from-red-500 to-red-600 text-white px-4 py-2.5 rounded-xl hover:from-red-600 hover:to-red-700 transition font-semibold text-sm shadow-md disabled:opacity-50 disabled:cursor-not-allowed relative">
                        <span wire:loading.remove wire:target="delete({{ $venue->id }})">
                            <i class="fas fa-trash"></i>
                        </span>
                        <span wire:loading wire:target="delete({{ $venue->id }})" class="flex items-center justify-center">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <div class="mt-6">
        {{ $venues->links() }}
    </div>

    <!-- Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
            
            <div class="relative bg-white rounded-2xl max-w-2xl w-full shadow-2xl transform transition-all" @click.stop>
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-red-600 to-pink-600 rounded-t-2xl px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center text-white">
                        <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-map-marker-alt text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold">
                            {{ $editMode ? 'Editar Local' : 'Novo Local' }}
                        </h3>
                    </div>
                    <button wire:click="$set('showModal', false)" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Modal Body -->
                <form wire:submit.prevent="save" class="p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Nome -->
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-building text-red-500 mr-2"></i>Nome do Local *
                            </label>
                            <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition" required>
                            @error('name') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Morada -->
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>Morada
                            </label>
                            <input wire:model="address" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                            @error('address') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Cidade -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-city text-purple-500 mr-2"></i>Cidade
                            </label>
                            <input wire:model="city" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                            @error('city') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Telefone -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-phone text-green-500 mr-2"></i>Telefone
                            </label>
                            <input wire:model="phone" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                            @error('phone') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Pessoa de Contacto -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user text-orange-500 mr-2"></i>Pessoa de Contacto
                            </label>
                            <input wire:model="contact_person" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                            @error('contact_person') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Capacidade -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-users text-blue-500 mr-2"></i>Capacidade (pessoas)
                            </label>
                            <input wire:model="capacity" type="number" min="0" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                            @error('capacity') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Notas -->
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-sticky-note text-yellow-500 mr-2"></i>Notas
                            </label>
                            <textarea wire:model="notes" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition"></textarea>
                            @error('notes') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Ativo -->
                        <div class="col-span-2">
                            <label class="flex items-center p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition">
                                <input type="checkbox" wire:model="is_active" class="w-5 h-5 text-red-600 rounded focus:ring-2 focus:ring-red-500 cursor-pointer">
                                <div class="ml-3">
                                    <div class="text-sm font-semibold text-gray-900">
                                        <i class="fas fa-check-circle text-green-600 mr-2"></i>Local Ativo
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1">Local disponível para novos eventos</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showModal', false)" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="submit" class="relative px-6 py-2.5 bg-gradient-to-r from-red-600 to-pink-600 text-white rounded-xl font-semibold hover:from-red-700 hover:to-pink-700 shadow-lg hover:shadow-xl transition disabled:opacity-50 disabled:cursor-not-allowed" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">
                                <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar' : 'Salvar' }}
                            </span>
                            <span wire:loading wire:target="save" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ $editMode ? 'Atualizando...' : 'Salvando...' }}
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    @if(session()->has('message'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" 
         class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-4 rounded-xl shadow-lg z-50">
        <i class="fas fa-check-circle mr-2"></i>{{ session('message') }}
    </div>
    @endif

    @if(session()->has('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" 
         class="fixed bottom-4 right-4 bg-red-500 text-white px-6 py-4 rounded-xl shadow-lg z-50">
        <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
    </div>
    @endif
</div>

@push('styles')
<style>
/* Animações */
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(50px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Classes de Animação */
.icon-float {
    animation: float 3s ease-in-out infinite;
}

.card-hover {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.card-hover:hover {
    transform: translateY(-8px);
}

.card-zoom:hover {
    transform: scale(1.02) translateY(-4px);
}

.card-bounce:hover {
    animation: pulse 0.6s ease-in-out;
}

.stagger-animation > * {
    animation: fadeInUp 0.6s ease-out;
    animation-fill-mode: both;
}

.stagger-animation > *:nth-child(1) { animation-delay: 0.1s; }
.stagger-animation > *:nth-child(2) { animation-delay: 0.2s; }
.stagger-animation > *:nth-child(3) { animation-delay: 0.3s; }
.stagger-animation > *:nth-child(4) { animation-delay: 0.4s; }
.stagger-animation > *:nth-child(5) { animation-delay: 0.5s; }
.stagger-animation > *:nth-child(6) { animation-delay: 0.6s; }
.stagger-animation > *:nth-child(7) { animation-delay: 0.7s; }
.stagger-animation > *:nth-child(8) { animation-delay: 0.8s; }
.stagger-animation > *:nth-child(9) { animation-delay: 0.9s; }

.counter-animation {
    display: inline-block;
    animation: slideInRight 0.6s ease-out;
}

.gradient-shift {
    background-size: 200% 200%;
    animation: gradientShift 3s ease infinite;
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* Transições suaves */
* {
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

/* Hover effects para ícones */
.icon-float:hover {
    animation: float 1s ease-in-out infinite;
    transform: scale(1.1);
}
</style>
@endpush
