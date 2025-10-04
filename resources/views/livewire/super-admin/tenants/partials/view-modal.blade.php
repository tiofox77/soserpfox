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
