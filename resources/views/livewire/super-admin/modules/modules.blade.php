<div>
    <!-- Header with Gradient -->
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-puzzle-piece text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Módulos</h2>
                    <p class="text-purple-100 text-sm">Gerir categorias e funcionalidades do sistema</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Módulo
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between mb-2">
                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/30">
                    <i class="fas fa-layer-group text-white text-xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold">Total Módulos</p>
            <p class="text-3xl font-bold text-gray-900">{{ $modules->count() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between mb-2">
                <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/30">
                    <i class="fas fa-check-circle text-white text-xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold">Módulos Ativos</p>
            <p class="text-3xl font-bold text-gray-900">{{ $modules->where('is_active', true)->count() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-yellow-100 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between mb-2">
                <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-yellow-500/30">
                    <i class="fas fa-shield-alt text-white text-xl"></i>
                </div>
            </div>
            <p class="text-sm text-orange-600 font-semibold">Módulos Core</p>
            <p class="text-3xl font-bold text-gray-900">{{ $modules->where('is_core', true)->count() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-red-100 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between mb-2">
                <div class="w-12 h-12 bg-gradient-to-br from-red-400 to-red-500 rounded-xl flex items-center justify-center shadow-lg shadow-red-500/30">
                    <i class="fas fa-times-circle text-white text-xl"></i>
                </div>
            </div>
            <p class="text-sm text-red-600 font-semibold">Módulos Inativos</p>
            <p class="text-3xl font-bold text-gray-900">{{ $modules->where('is_active', false)->count() }}</p>
        </div>
    </div>

    <!-- Search -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center space-x-4">
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Pesquisar módulos por nome, slug ou descrição..."
                       class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
            </div>
        </div>
    </div>

    <!-- Modules Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($modules as $module)
            <div class="group bg-white rounded-2xl shadow-lg overflow-hidden border {{ $module->is_active ? 'border-green-200' : 'border-gray-200' }} hover:shadow-xl transition-all">
                <!-- Header with colored background -->
                <div class="p-6 bg-gradient-to-br {{ $module->is_active ? 'from-green-50 to-emerald-50' : 'from-gray-50 to-gray-100' }} border-b border-gray-100">
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center shadow-lg shadow-purple-500/30">
                            <i class="fas fa-{{ $module->icon }} text-white text-2xl"></i>
                        </div>
                        
                        <div class="flex flex-col items-end space-y-2">
                            @if($module->is_core)
                                <span class="inline-flex items-center px-2.5 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">
                                    <i class="fas fa-star mr-1"></i>Core
                                </span>
                            @endif
                            <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full {{ $module->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $module->is_active ? 'bg-green-500' : 'bg-gray-500' }} mr-1.5"></span>
                                {{ $module->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </div>
                    </div>
                    
                    <h3 class="text-xl font-bold text-gray-900 mb-1">{{ $module->name }}</h3>
                    <p class="text-sm text-gray-600 line-clamp-2">{{ $module->description }}</p>
                </div>
                
                <!-- Body -->
                <div class="p-6">
                    <!-- Info Grid -->
                    <div class="space-y-3 mb-4">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center text-xs text-gray-600">
                                <span class="w-6 h-6 rounded-lg bg-blue-100 flex items-center justify-center mr-2">
                                    <i class="fas fa-code-branch text-blue-600 text-[10px]"></i>
                                </span>
                                Versão
                            </span>
                            <span class="text-sm font-semibold text-gray-900">{{ $module->version }}</span>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center text-xs text-gray-600">
                                <span class="w-6 h-6 rounded-lg bg-orange-100 flex items-center justify-center mr-2">
                                    <i class="fas fa-sort-numeric-up text-orange-600 text-[10px]"></i>
                                </span>
                                Ordem
                            </span>
                            <span class="text-sm font-semibold text-gray-900">{{ $module->order }}</span>
                        </div>
                        
                        @if($module->dependencies && count($module->dependencies) > 0)
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center text-xs text-gray-600">
                                    <span class="w-6 h-6 rounded-lg bg-purple-100 flex items-center justify-center mr-2">
                                        <i class="fas fa-link text-purple-600 text-[10px]"></i>
                                    </span>
                                    Dependências
                                </span>
                                <span class="text-sm font-semibold text-gray-900">{{ count($module->dependencies) }}</span>
                            </div>
                        @endif
                        
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center text-xs text-gray-600">
                                <span class="w-6 h-6 rounded-lg bg-green-100 flex items-center justify-center mr-2">
                                    <i class="fas fa-building text-green-600 text-[10px]"></i>
                                </span>
                                Tenants usando
                            </span>
                            <span class="text-sm font-semibold {{ $module->tenants_count > 0 ? 'text-green-700' : 'text-gray-400' }}">{{ $module->tenants_count }}</span>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex space-x-2 pt-4 border-t border-gray-100 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button wire:click="edit({{ $module->id }})" class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium hover:bg-blue-100 transition-colors">
                            <i class="fas fa-edit mr-1.5"></i>Editar
                        </button>
                        <button wire:click="toggleStatus({{ $module->id }})" class="flex-1 inline-flex items-center justify-center px-3 py-2 {{ $module->is_active ? 'bg-orange-50 text-orange-700 hover:bg-orange-100' : 'bg-green-50 text-green-700 hover:bg-green-100' }} rounded-lg text-xs font-medium transition-colors">
                            <i class="fas fa-power-off mr-1.5"></i>{{ $module->is_active ? 'Desativar' : 'Ativar' }}
                        </button>
                        @if(!$module->is_core)
                        <button wire:click="delete({{ $module->id }})"
                                wire:confirm="Tem certeza que deseja excluir o módulo '{{ $module->name }}'? Esta ação não pode ser desfeita."
                                class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-red-50 text-red-700 rounded-lg text-xs font-medium hover:bg-red-100 transition-colors">
                            <i class="fas fa-trash mr-1.5"></i>Excluir
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-puzzle-piece text-gray-400 text-3xl"></i>
                    </div>
                    <p class="text-gray-500 font-medium text-lg">Nenhum módulo encontrado</p>
                    @if($search)
                        <p class="text-gray-400 text-sm mt-1">Tente uma pesquisa diferente</p>
                    @else
                        <p class="text-gray-400 text-sm mt-1">Comece criando um novo módulo</p>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    <!-- Modals -->
    @include('livewire.super-admin.modules.partials.form-modal')
</div>
