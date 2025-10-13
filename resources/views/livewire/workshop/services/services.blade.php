<div>
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
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-tools text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Catálogo de Serviços</h2>
                    <p class="text-purple-100 text-sm">Gerencie os serviços oferecidos pela oficina</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Serviço
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50">
                    <i class="fas fa-tools text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">Total Serviços</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $services->total() }}
            </p>
            <p class="text-xs text-gray-500">Cadastrados</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Ativos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $services->where('is_active', true)->count() }}
            </p>
            <p class="text-xs text-gray-500">Disponíveis</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50">
                    <i class="fas fa-layer-group text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Categorias</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $services->pluck('category')->unique()->count() }}
            </p>
            <p class="text-xs text-gray-500">Diferentes</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-gray-500 to-gray-600 rounded-2xl flex items-center justify-center shadow-lg shadow-gray-500/50">
                    <i class="fas fa-times-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 font-semibold mb-2">Inativos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $services->where('is_active', false)->count() }}
            </p>
            <p class="text-xs text-gray-500">Desativados</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-purple-600"></i>
                Filtros Avançados
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-search mr-1 text-purple-600"></i>Pesquisar
                </label>
                <input type="text" wire:model.live="search" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                       placeholder="Nome ou descrição do serviço...">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-tag mr-1 text-indigo-600"></i>Categoria
                </label>
                <select wire:model.live="categoryFilter" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                    <option value="">Todas Categorias</option>
                    <option value="Manutenção">🔧 Manutenção</option>
                    <option value="Reparação">🛠️ Reparação</option>
                    <option value="Inspeção">🔍 Inspeção</option>
                    <option value="Pintura">🎨 Pintura</option>
                    <option value="Mecânica">⚙️ Mecânica</option>
                    <option value="Elétrica">⚡ Elétrica</option>
                    <option value="Chapa">🔨 Chapa</option>
                    <option value="Pneus">🚗 Pneus</option>
                    <option value="Outro">📦 Outro</option>
                </select>
            </div>
        </div>
    </div>

        <!-- Services Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($services as $service)
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all transform hover:scale-105 overflow-hidden">
                    <!-- Category Badge -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-3">
                        <div class="flex items-center justify-between">
                            <span class="text-white font-bold text-sm">
                                <i class="fas fa-tag mr-2"></i>{{ $service->category }}
                            </span>
                            @if($service->is_active)
                                <span class="bg-green-500 text-white text-xs px-2 py-1 rounded-full">
                                    <i class="fas fa-check mr-1"></i>Ativo
                                </span>
                            @else
                                <span class="bg-gray-500 text-white text-xs px-2 py-1 rounded-full">
                                    <i class="fas fa-times mr-1"></i>Inativo
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-6">
                        <div class="mb-4">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $service->name }}</h3>
                            <p class="text-sm text-gray-600 line-clamp-2">
                                {{ $service->description ?: 'Sem descrição' }}
                            </p>
                        </div>

                        <div class="space-y-2 mb-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">
                                    <i class="fas fa-code mr-2 text-blue-600"></i>Código:
                                </span>
                                <span class="font-semibold text-gray-900">{{ $service->service_code }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">
                                    <i class="fas fa-money-bill-wave mr-2 text-green-600"></i>Mão de Obra:
                                </span>
                                <span class="font-bold text-green-600">{{ $service->formatted_labor_cost }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">
                                    <i class="fas fa-clock mr-2 text-orange-600"></i>Tempo:
                                </span>
                                <span class="font-semibold text-gray-900">{{ $service->estimated_hours }}h</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end space-x-2 pt-4 border-t border-gray-200">
                            <button wire:click="edit({{ $service->id }})" 
                                    class="flex-1 text-blue-600 hover:text-blue-900 hover:bg-blue-50 py-2 rounded-lg transition-all font-medium">
                                <i class="fas fa-edit mr-2"></i>Editar
                            </button>
                            <button wire:click="delete({{ $service->id }})" 
                                    onclick="return confirm('Tem certeza que deseja remover este serviço?')"
                                    class="flex-1 text-red-600 hover:text-red-900 hover:bg-red-50 py-2 rounded-lg transition-all font-medium">
                                <i class="fas fa-trash mr-2"></i>Remover
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full">
                    <div class="bg-white rounded-xl shadow-md p-12 text-center">
                        <i class="fas fa-tools text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg font-medium">Nenhum serviço encontrado</p>
                        <p class="text-gray-400 text-sm mt-2">Clique em "Novo Serviço" para começar</p>
                    </div>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $services->links() }}
        </div>
    </div>

    {{-- Modal --}}
    @if($showModal)
        @include('livewire.workshop.services.partials.form-modal')
    @endif
</div>
