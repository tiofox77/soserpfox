<!-- Modal View Tenant Details -->
@if($showViewModal ?? false)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showViewModal') }" x-show="show" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity backdrop-blur-sm" wire:click="closeViewModal"></div>
            
            <div class="relative bg-white rounded-2xl max-w-4xl w-full shadow-2xl transform transition-all" @click.stop>
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-t-2xl px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center text-white">
                        <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-eye text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold">Detalhes do Tenant</h3>
                    </div>
                    <button wire:click="closeViewModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Modal Body -->
                @if($viewingTenant ?? false)
                <div class="p-6 max-h-[70vh] overflow-y-auto">
                    <!-- Header Info -->
                    <div class="flex items-start space-x-6 mb-6 pb-6 border-b border-gray-200">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center shadow-lg">
                            <span class="text-white font-bold text-3xl">{{ strtoupper(substr($viewingTenant->name, 0, 2)) }}</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $viewingTenant->name }}</h3>
                            <p class="text-gray-600 mb-3">{{ $viewingTenant->slug }}</p>
                            <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full {{ $viewingTenant->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $viewingTenant->is_active ? 'bg-green-500' : 'bg-gray-500' }} mr-1.5"></span>
                                {{ $viewingTenant->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Contact Information -->
                        <div class="bg-blue-50 rounded-xl p-4">
                            <h4 class="font-bold text-blue-900 mb-3 flex items-center">
                                <i class="fas fa-address-card mr-2"></i>Informações de Contacto
                            </h4>
                            <div class="space-y-3">
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-envelope text-blue-600 mt-1"></i>
                                    <div>
                                        <p class="text-xs text-blue-700 font-semibold">Email</p>
                                        <p class="text-sm text-gray-900">{{ $viewingTenant->email }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-phone text-blue-600 mt-1"></i>
                                    <div>
                                        <p class="text-xs text-blue-700 font-semibold">Telefone</p>
                                        <p class="text-sm text-gray-900">{{ $viewingTenant->phone ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Company Information -->
                        <div class="bg-purple-50 rounded-xl p-4">
                            <h4 class="font-bold text-purple-900 mb-3 flex items-center">
                                <i class="fas fa-briefcase mr-2"></i>Informações da Empresa
                            </h4>
                            <div class="space-y-3">
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-building text-purple-600 mt-1"></i>
                                    <div>
                                        <p class="text-xs text-purple-700 font-semibold">Nome da Empresa</p>
                                        <p class="text-sm text-gray-900">{{ $viewingTenant->company_name ?? 'N/A' }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-id-card text-purple-600 mt-1"></i>
                                    <div>
                                        <p class="text-xs text-purple-700 font-semibold">NIF</p>
                                        <p class="text-sm text-gray-900">{{ $viewingTenant->nif ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Resource Limits -->
                        <div class="bg-green-50 rounded-xl p-4">
                            <h4 class="font-bold text-green-900 mb-3 flex items-center">
                                <i class="fas fa-chart-bar mr-2"></i>Limites de Recursos
                            </h4>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-users text-green-600"></i>
                                        <span class="text-sm text-gray-700">Máx. Utilizadores</span>
                                    </div>
                                    <span class="font-bold text-gray-900">{{ $viewingTenant->max_users }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-database text-green-600"></i>
                                        <span class="text-sm text-gray-700">Storage</span>
                                    </div>
                                    <span class="font-bold text-gray-900">{{ $viewingTenant->max_storage_mb }}MB</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Information -->
                        <div class="bg-orange-50 rounded-xl p-4">
                            <h4 class="font-bold text-orange-900 mb-3 flex items-center">
                                <i class="fas fa-info-circle mr-2"></i>Informações do Sistema
                            </h4>
                            <div class="space-y-3">
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-calendar-plus text-orange-600 mt-1"></i>
                                    <div>
                                        <p class="text-xs text-orange-700 font-semibold">Criado em</p>
                                        <p class="text-sm text-gray-900">{{ $viewingTenant->created_at->format('d/m/Y H:i') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-calendar-check text-orange-600 mt-1"></i>
                                    <div>
                                        <p class="text-xs text-orange-700 font-semibold">Última Atualização</p>
                                        <p class="text-sm text-gray-900">{{ $viewingTenant->updated_at->format('d/m/Y H:i') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Active Modules -->
                    <div class="mt-6">
                        <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-puzzle-piece mr-2 text-purple-600"></i>Modulos Activos
                        </h4>
                        @if($viewingTenant->modules && $viewingTenant->modules->count() > 0)
                            <div class="flex flex-wrap gap-2">
                                @foreach($viewingTenant->modules as $module)
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold
                                        {{ $module->pivot->is_active ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-gray-100 text-gray-500 border border-gray-200' }}">
                                        <span class="w-2 h-2 rounded-full {{ $module->pivot->is_active ? 'bg-green-500' : 'bg-gray-400' }} mr-1.5"></span>
                                        {{ $module->name }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-400 italic">Nenhum modulo associado</p>
                        @endif
                    </div>

                    <!-- Users & Roles -->
                    <div class="mt-6">
                        <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-users mr-2 text-blue-600"></i>Utilizadores ({{ $viewingTenant->users->count() }})
                        </h4>
                        @if($viewingTenant->users && $viewingTenant->users->count() > 0)
                            <div class="space-y-2">
                                @foreach($viewingTenant->users as $user)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center">
                                                <span class="text-white text-xs font-bold">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-gray-800">{{ $user->name }}</p>
                                                <p class="text-xs text-gray-500">{{ $user->email }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if($user->pivot->is_active)
                                                <span class="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-semibold rounded-full">Activo</span>
                                            @else
                                                <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-[10px] font-semibold rounded-full">Inactivo</span>
                                            @endif
                                            @if($user->is_super_admin)
                                                <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-[10px] font-bold rounded-full">Super Admin</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-400 italic">Nenhum utilizador associado</p>
                        @endif
                    </div>

                    <!-- Action Footer -->
                    <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button wire:click="closeViewModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i>Fechar
                        </button>
                        <button wire:click="editFromView" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-purple-700 shadow-lg hover:shadow-xl transition">
                            <i class="fas fa-edit mr-2"></i>Editar
                        </button>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
@endif
