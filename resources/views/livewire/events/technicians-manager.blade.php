<div class="p-4 sm:p-6">
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-user-tie text-cyan-600 mr-3"></i>
                Gestão de Técnicos
            </h2>
            <p class="text-sm sm:text-base text-gray-600">Equipe técnica e especialidades</p>
        </div>
        <div class="flex items-center space-x-2 sm:space-x-3">
            <button wire:click="create" 
                    class="group bg-gradient-to-r from-cyan-600 to-blue-600 text-white px-5 py-2.5 rounded-lg font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center">
                <i class="fas fa-plus-circle mr-2 group-hover:rotate-90 transition-transform duration-300"></i>
                Novo Técnico
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-lg shadow-lg p-4 sm:p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-cyan-100 text-xs sm:text-sm font-medium">Total</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $technicians->total() }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full">
                    <i class="fas fa-users text-xl sm:text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-4 sm:p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-xs sm:text-sm font-medium">Ativos</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $technicians->where('is_active', true)->count() }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full">
                    <i class="fas fa-check-circle text-xl sm:text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-4 sm:p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-xs sm:text-sm font-medium">Disponíveis</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $technicians->where('is_available', true)->count() }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full">
                    <i class="fas fa-user-clock text-xl sm:text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-4 sm:p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-xs sm:text-sm font-medium">Especialistas</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $technicians->whereIn('level', ['senior', 'master'])->count() }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full">
                    <i class="fas fa-star text-xl sm:text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-lg shadow-md p-4 mb-6 border-l-4 border-cyan-600">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <input type="text" wire:model.live.debounce.300ms="search" 
                       placeholder="🔍 Pesquisar técnico por nome..." 
                       class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500">
            </div>
        </div>
    </div>

    {{-- Grid de Técnicos --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($technicians as $tech)
        <div class="group bg-white rounded-xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden border-2 border-gray-100 hover:border-cyan-400">
            
            {{-- Foto/Ícone --}}
            <div class="relative h-48 bg-gradient-to-br from-cyan-100 to-blue-100 flex items-center justify-center overflow-hidden">
                @if($tech->photo)
                    <img src="{{ asset('storage/' . $tech->photo) }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" alt="{{ $tech->name }}">
                @else
                    <div class="w-24 h-24 rounded-full bg-white/30 backdrop-blur-sm flex items-center justify-center">
                        <i class="fas fa-user-tie text-6xl text-cyan-600"></i>
                    </div>
                @endif
                
                {{-- Badge de Status --}}
                <div class="absolute top-2 right-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold text-white shadow-lg {{ $tech->is_active ? 'bg-green-500' : 'bg-gray-500' }}">
                        {{ $tech->is_active ? '✓ Ativo' : 'Inativo' }}
                    </span>
                </div>
                
                {{-- Badge de Disponibilidade --}}
                @if($tech->is_available && $tech->is_active)
                <div class="absolute top-2 left-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-500 text-white shadow-lg">
                        🟢 Disponível
                    </span>
                </div>
                @endif
                
                {{-- Badge de Nível --}}
                <div class="absolute bottom-2 left-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold text-white shadow-lg
                        @if($tech->level == 'junior') bg-green-500
                        @elseif($tech->level == 'pleno') bg-blue-500
                        @elseif($tech->level == 'senior') bg-purple-500
                        @else bg-orange-500
                        @endif">
                        @if($tech->level == 'junior') 🟢 @endif
                        @if($tech->level == 'pleno') 🔵 @endif
                        @if($tech->level == 'senior') 🟣 @endif
                        @if($tech->level == 'master') 🟠 @endif
                        {{ ucfirst($tech->level) }}
                    </span>
                </div>
            </div>

            {{-- Conteúdo --}}
            <div class="p-4">
                <h3 class="font-bold text-lg text-gray-900 mb-1 group-hover:text-cyan-700 transition">{{ $tech->name }}</h3>
                
                {{-- Especialidades --}}
                @if($tech->specialties && count($tech->specialties) > 0)
                <div class="flex flex-wrap gap-1 mb-3">
                    @foreach($tech->specialties as $spec)
                    <span class="px-2 py-1 bg-cyan-100 text-cyan-700 text-xs font-semibold rounded">
                        @if($spec == 'audio') 🎤 @endif
                        @if($spec == 'video') 🎥 @endif
                        @if($spec == 'iluminacao') 💡 @endif
                        @if($spec == 'streaming') 📡 @endif
                        {{ ucfirst($spec) }}
                    </span>
                    @endforeach
                </div>
                @endif
                
                {{-- Informações --}}
                <div class="space-y-2 text-sm mb-4">
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-phone w-5 text-cyan-600"></i>
                        <span class="truncate">{{ $tech->phone }}</span>
                    </div>
                    
                    @if($tech->email)
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-envelope w-5 text-gray-400"></i>
                        <span class="truncate">{{ $tech->email }}</span>
                    </div>
                    @endif
                    
                    @if($tech->document)
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-id-card w-5 text-gray-400"></i>
                        <span>{{ $tech->document }}</span>
                    </div>
                    @endif
                    
                    @if($tech->daily_rate > 0)
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-money-bill-wave w-5 text-green-600"></i>
                        <span class="font-bold text-green-600">{{ number_format($tech->daily_rate, 2) }} €/dia</span>
                    </div>
                    @endif
                </div>

                {{-- Ações --}}
                <div class="space-y-2">
                    <div class="flex gap-2">
                        <button wire:click="edit({{ $tech->id }})"
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-semibold transition-all duration-300 hover:scale-105 flex items-center justify-center">
                            <i class="fas fa-edit mr-2"></i>
                            Editar
                        </button>
                        <button wire:click="delete({{ $tech->id }})"
                                onclick="return confirm('Tem certeza que deseja excluir este técnico?')"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg font-semibold transition-all duration-300 hover:scale-105 flex items-center justify-center">
                            <i class="fas fa-trash mr-2"></i>
                            Excluir
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-3 text-center py-16 bg-gray-50 rounded-2xl">
            <i class="fas fa-user-hard-hat text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-500 text-lg font-semibold">Nenhum técnico cadastrado</p>
            <p class="text-gray-400 text-sm mt-2">Clique em "Novo Técnico" para começar</p>
        </div>
        @endforelse
    </div>

    {{-- Paginação --}}
    <div class="mt-6">
        {{ $technicians->links() }}
    </div>

    {{-- Modal --}}
    @if($showModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" 
         style="backdrop-filter: blur(4px);">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-cyan-600 to-blue-600 px-6 py-4 flex items-center justify-between sticky top-0 z-10 rounded-t-2xl">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-user-hard-hat mr-3"></i>
                    {{ $editingId ? 'Editar' : 'Novo' }} Técnico
                </h3>
                <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                {{-- Nome Completo --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-user mr-1 text-cyan-600"></i>
                        Nome Completo *
                    </label>
                    <input type="text" wire:model="name" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 @error('name') border-red-500 @enderror"
                           placeholder="Ex: João Silva Santos">
                    @error('name') <p class="text-red-600 text-xs mt-1 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>
                
                {{-- Grid de Contatos --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-phone mr-1 text-cyan-600"></i>
                            Telefone *
                        </label>
                        <input type="text" wire:model="phone" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 @error('phone') border-red-500 @enderror"
                               placeholder="+244 939 779 902">
                        @error('phone') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-envelope mr-1 text-gray-600"></i>
                            Email
                        </label>
                        <input type="email" wire:model="email" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 @error('email') border-red-500 @enderror"
                               placeholder="joao@email.com">
                        @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                
                {{-- Documento --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-id-card mr-1 text-gray-600"></i>
                        Documento (BI/NIF)
                    </label>
                    <input type="text" wire:model="document" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500"
                           placeholder="Ex: 123456789LA">
                </div>
                
                {{-- Especialidades --}}
                <div class="bg-cyan-50 rounded-lg p-4 border-2 border-cyan-200">
                    <label class="block text-sm font-bold text-gray-700 mb-3">
                        <i class="fas fa-star mr-1 text-cyan-600"></i>
                        Especialidades * (selecione pelo menos uma)
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 p-3 bg-white rounded-lg cursor-pointer hover:bg-cyan-100 transition border-2 {{ in_array('audio', $specialties) ? 'border-cyan-500 bg-cyan-50' : 'border-gray-200' }}">
                            <input type="checkbox" wire:model="specialties" value="audio" class="w-5 h-5 text-cyan-600 rounded">
                            <span class="font-semibold">🎤 Áudio</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-white rounded-lg cursor-pointer hover:bg-cyan-100 transition border-2 {{ in_array('video', $specialties) ? 'border-cyan-500 bg-cyan-50' : 'border-gray-200' }}">
                            <input type="checkbox" wire:model="specialties" value="video" class="w-5 h-5 text-cyan-600 rounded">
                            <span class="font-semibold">🎥 Vídeo</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-white rounded-lg cursor-pointer hover:bg-cyan-100 transition border-2 {{ in_array('iluminacao', $specialties) ? 'border-cyan-500 bg-cyan-50' : 'border-gray-200' }}">
                            <input type="checkbox" wire:model="specialties" value="iluminacao" class="w-5 h-5 text-cyan-600 rounded">
                            <span class="font-semibold">💡 Iluminação</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-white rounded-lg cursor-pointer hover:bg-cyan-100 transition border-2 {{ in_array('streaming', $specialties) ? 'border-cyan-500 bg-cyan-50' : 'border-gray-200' }}">
                            <input type="checkbox" wire:model="specialties" value="streaming" class="w-5 h-5 text-cyan-600 rounded">
                            <span class="font-semibold">📡 Streaming</span>
                        </label>
                    </div>
                    @error('specialties') <p class="text-red-600 text-xs mt-2 flex items-center"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>
                
                {{-- Nível --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-layer-group mr-1 text-cyan-600"></i>
                        Nível de Experiência *
                    </label>
                    <select wire:model="level" 
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500">
                        <option value="junior">🟢 Junior</option>
                        <option value="pleno">🔵 Pleno</option>
                        <option value="senior">🟣 Senior</option>
                        <option value="master">🟠 Master</option>
                    </select>
                </div>
                
                {{-- Valores --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-money-bill-wave mr-1 text-green-600"></i>
                            Valor por Hora (€)
                        </label>
                        <input type="number" wire:model="hourly_rate" step="0.01" min="0"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500"
                               placeholder="0.00">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar-day mr-1 text-green-600"></i>
                            Cachê por Dia (€)
                        </label>
                        <input type="number" wire:model="daily_rate" step="0.01" min="0"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500"
                               placeholder="0.00">
                    </div>
                </div>
                
                {{-- Status Ativo --}}
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
                    <input type="checkbox" wire:model="is_active" id="is_active" class="w-5 h-5 text-cyan-600 rounded">
                    <label for="is_active" class="font-semibold text-gray-700 cursor-pointer">
                        Técnico Ativo
                    </label>
                </div>
                
                {{-- Botões de Ação --}}
                <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t">
                    <button wire:click="save" 
                            wire:loading.attr="disabled"
                            class="flex-1 bg-gradient-to-r from-cyan-600 to-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="save">
                            <i class="fas fa-save mr-2"></i>
                            {{ $editingId ? 'Atualizar' : 'Salvar' }} Técnico
                        </span>
                        <span wire:loading wire:target="save">
                            <i class="fas fa-spinner fa-spin mr-2"></i>
                            Salvando...
                        </span>
                    </button>
                    <button wire:click="closeModal" 
                            type="button"
                            class="px-6 py-3 border-2 border-gray-300 rounded-lg font-bold text-gray-700 hover:bg-gray-50 transition">
                        <i class="fas fa-times mr-2"></i>
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
