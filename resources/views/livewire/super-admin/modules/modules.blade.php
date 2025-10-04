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
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 stagger-animation">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 card-hover card-3d">
            <div class="flex items-center justify-between mb-2">
                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float gradient-shift">
                    <i class="fas fa-layer-group text-white text-xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold">Total Módulos</p>
            <p class="text-3xl font-bold text-gray-900 group-hover:scale-110 transition-transform inline-block">{{ $modules->count() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 card-hover card-zoom">
            <div class="flex items-center justify-between mb-2">
                <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float gradient-shift">
                    <i class="fas fa-check-circle text-white text-xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold">Módulos Ativos</p>
            <p class="text-3xl font-bold text-gray-900 group-hover:scale-110 transition-transform inline-block">{{ $modules->where('is_active', true)->count() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-yellow-100 card-hover card-glow">
            <div class="flex items-center justify-between mb-2">
                <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-yellow-500/50 icon-float gradient-shift">
                    <i class="fas fa-shield-alt text-white text-xl"></i>
                </div>
            </div>
            <p class="text-sm text-orange-600 font-semibold">Módulos Core</p>
            <p class="text-3xl font-bold text-gray-900 group-hover:scale-110 transition-transform inline-block">{{ $modules->where('is_core', true)->count() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 card-hover card-rotate">
            <div class="flex items-center justify-between mb-2">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float gradient-shift">
                    <i class="fas fa-stopwatch text-white text-xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold">Atualizações Recentes</p>
            <p class="text-3xl font-bold text-gray-900 group-hover:scale-110 transition-transform inline-block">0</p>
        </div>
    </div>

    <!-- Modules Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 stagger-animation">
        @foreach($modules as $module)
            <div class="group bg-white rounded-2xl shadow-lg overflow-hidden border {{ $module->is_active ? 'border-green-200' : 'border-gray-200' }} card-hover card-3d">
                <!-- Header with colored background -->
                <div class="p-6 bg-gradient-to-br {{ $module->is_active ? 'from-green-50 to-emerald-50' : 'from-gray-50 to-gray-100' }} border-b border-gray-100 gradient-shift">
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float gradient-shift card-bounce">
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
                    <p class="text-sm text-gray-600">{{ $module->description }}</p>
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
                        
                        @if($module->dependencies)
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
                            <span class="text-sm font-semibold text-gray-900">{{ $module->tenants()->wherePivot('is_active', true)->count() }}</span>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex space-x-2 pt-4 border-t border-gray-100 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button wire:click="edit({{ $module->id }})" class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium hover:bg-blue-100 transition-colors">
                            <i class="fas fa-edit mr-1.5"></i>Editar
                        </button>
                        <button wire:click="toggleStatus({{ $module->id }})" class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-purple-50 text-purple-700 rounded-lg text-xs font-medium hover:bg-purple-100 transition-colors">
                            <i class="fas fa-power-off mr-1.5"></i>Toggle
                        </button>
                        @if(!$module->is_core)
                        <button wire:click="delete({{ $module->id }})" onclick="return confirm('Excluir este módulo?')" class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-red-50 text-red-700 rounded-lg text-xs font-medium hover:bg-red-100 transition-colors">
                            <i class="fas fa-trash mr-1.5"></i>Excluir
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Modals -->
    @include('livewire.super-admin.modules.partials.form-modal')
</div>
