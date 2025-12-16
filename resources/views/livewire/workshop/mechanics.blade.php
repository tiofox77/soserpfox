<div class="p-4 sm:p-6">
    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" 
             class="mb-6 bg-gradient-to-r from-green-500 to-emerald-600 rounded-2xl shadow-lg p-4 text-white flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-2xl mr-3"></i>
                <span class="font-semibold">{{ session('success') }}</span>
            </div>
            <button @click="show = false" class="text-white hover:text-green-100 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-user-cog text-orange-600 mr-3"></i>
                Gestão de Mecânicos
            </h2>
            <p class="text-sm sm:text-base text-gray-600">Equipe de mecânicos da oficina</p>
        </div>
        <div class="flex items-center space-x-2 sm:space-x-3">
            <button wire:click="openImportModal" 
                    class="group bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-4 py-2.5 rounded-lg font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center">
                <i class="fas fa-file-import mr-2 group-hover:translate-x-1 transition-transform duration-300"></i>
                Importar de RH
            </button>
            <button wire:click="create" 
                    class="group bg-gradient-to-r from-orange-600 to-red-600 text-white px-5 py-2.5 rounded-lg font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center">
                <i class="fas fa-plus-circle mr-2 group-hover:rotate-90 transition-transform duration-300"></i>
                Novo Mecânico
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-lg p-4 sm:p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 text-xs sm:text-sm font-medium">Total</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $mechanics->total() }}</p>
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
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $mechanics->where('is_active', true)->count() }}</p>
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
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $mechanics->where('is_available', true)->count() }}</p>
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
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $mechanics->whereIn('level', ['senior', 'master'])->count() }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full">
                    <i class="fas fa-star text-xl sm:text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-lg shadow-md p-4 mb-6 border-l-4 border-orange-600">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <input type="text" wire:model.live.debounce.300ms="search" 
                       placeholder="🔍 Pesquisar mecânico por nome..." 
                       class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500">
            </div>
        </div>
    </div>

    {{-- Grid de Mecânicos --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($mechanics as $mechanic)
        <div class="group bg-white rounded-xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden border-2 border-gray-100 hover:border-orange-400">
            
            {{-- Foto/Ícone --}}
            <div class="relative h-48 bg-gradient-to-br from-orange-100 to-red-100 flex items-center justify-center overflow-hidden">
                @if($mechanic->photo)
                    <img src="{{ asset('storage/' . $mechanic->photo) }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" alt="{{ $mechanic->name }}">
                @else
                    <div class="w-24 h-24 rounded-full bg-white/30 backdrop-blur-sm flex items-center justify-center">
                        <i class="fas fa-user-cog text-6xl text-orange-600"></i>
                    </div>
                @endif
                
                {{-- Badge de Status --}}
                <div class="absolute top-2 right-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold text-white shadow-lg {{ $mechanic->is_active ? 'bg-green-500' : 'bg-gray-500' }}">
                        {{ $mechanic->is_active ? '✓ Ativo' : 'Inativo' }}
                    </span>
                </div>
                
                {{-- Badge de Disponibilidade --}}
                @if($mechanic->is_available && $mechanic->is_active)
                <div class="absolute top-2 left-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-500 text-white shadow-lg">
                        🟢 Disponível
                    </span>
                </div>
                @endif
                
                {{-- Badge de Nível --}}
                <div class="absolute bottom-2 left-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold text-white shadow-lg
                        @if($mechanic->level == 'junior') bg-green-500
                        @elseif($mechanic->level == 'pleno') bg-blue-500
                        @elseif($mechanic->level == 'senior') bg-purple-500
                        @else bg-orange-500
                        @endif">
                        @if($mechanic->level == 'junior') 🟢 @endif
                        @if($mechanic->level == 'pleno') 🔵 @endif
                        @if($mechanic->level == 'senior') 🟣 @endif
                        @if($mechanic->level == 'master') 🟠 @endif
                        {{ ucfirst($mechanic->level) }}
                    </span>
                </div>
            </div>

            {{-- Conteúdo --}}
            <div class="p-4">
                <h3 class="font-bold text-lg text-gray-900 mb-1 group-hover:text-orange-700 transition">{{ $mechanic->name }}</h3>
                
                {{-- Especialidades --}}
                @if(!empty($mechanic->specialties) && is_array($mechanic->specialties))
                <div class="flex flex-wrap gap-1 mb-3">
                    @foreach($mechanic->specialties as $spec)
                    <span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded">
                        @if($spec == 'Mecânica Geral') 🔧 @endif
                        @if($spec == 'Motor') ⚙️ @endif
                        @if($spec == 'Suspensão') 🔩 @endif
                        @if($spec == 'Elétrica') ⚡ @endif
                        @if($spec == 'Pintura') 🎨 @endif
                        @if($spec == 'Chapa') 🔨 @endif
                        {{ ucfirst($spec) }}
                    </span>
                    @endforeach
                </div>
                @endif
                
                {{-- Informações --}}
                <div class="space-y-2 text-sm mb-4">
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-phone w-5 text-orange-600"></i>
                        <span class="truncate">{{ $mechanic->phone }}</span>
                    </div>
                    
                    @if($mechanic->email)
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-envelope w-5 text-gray-400"></i>
                        <span class="truncate">{{ $mechanic->email }}</span>
                    </div>
                    @endif
                    
                    @if($mechanic->document)
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-id-card w-5 text-gray-400"></i>
                        <span>{{ $mechanic->document }}</span>
                    </div>
                    @endif
                    
                    @if($mechanic->daily_rate > 0)
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-money-bill-wave w-5 text-green-600"></i>
                        <span class="font-bold text-green-600">{{ number_format($mechanic->daily_rate, 2) }} Kz/dia</span>
                    </div>
                    @endif
                </div>

                {{-- Ações --}}
                <div class="space-y-2">
                    <button wire:click="view({{ $mechanic->id }})"
                            class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg font-semibold transition-all duration-300 hover:scale-105 flex items-center justify-center">
                        <i class="fas fa-eye mr-2"></i>
                        Visualizar
                    </button>
                    <div class="flex gap-2">
                        <button wire:click="edit({{ $mechanic->id }})"
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-semibold transition-all duration-300 hover:scale-105 flex items-center justify-center">
                            <i class="fas fa-edit mr-2"></i>
                            Editar
                        </button>
                        <button wire:click="delete({{ $mechanic->id }})"
                                onclick="return confirm('Tem certeza que deseja excluir este mecânico?')"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg font-semibold transition-all duration-300 hover:scale-105 flex items-center justify-center">
                            <i class="fas fa-trash mr-2"></i>
                            Excluir
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-4 text-center py-16 bg-gray-50 rounded-2xl">
            <i class="fas fa-user-cog text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-500 text-lg font-semibold">Nenhum mecânico cadastrado</p>
            <p class="text-gray-400 text-sm mt-2">Clique em "Novo Mecânico" para começar</p>
        </div>
        @endforelse
    </div>

    {{-- Paginação --}}
    <div class="mt-6">
        {{ $mechanics->links() }}
    </div>

    {{-- Modal de Formulário --}}
    @if($showModal)
        @include('livewire.workshop.mechanics-partials.form-modal')
    @endif

    {{-- Modal de Importação --}}
    @if($showImportModal)
        @include('livewire.workshop.mechanics-partials.import-modal')
    @endif
    
    {{-- Modal de Visualização --}}
    @if($showViewModal)
        @include('livewire.workshop.mechanics-partials.view-modal')
    @endif
</div>
