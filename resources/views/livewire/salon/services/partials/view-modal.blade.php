@if($showViewModal && $viewingService)
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" wire:click="closeViewModal"></div>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <!-- Header -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center text-indigo-600 text-2xl shadow-lg">
                            <i class="fas fa-spa"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-white">{{ $viewingService->name }}</h3>
                            <p class="text-indigo-100">
                                {{ $viewingService->category->name ?? 'Sem categoria' }}
                                @if(!$viewingService->is_active)
                                    <span class="px-2 py-0.5 bg-red-400 text-white text-xs rounded-full ml-2">Inativo</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <button wire:click="closeViewModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <!-- Stats -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="text-center p-4 bg-blue-50 rounded-xl">
                        <p class="text-2xl font-bold text-blue-600">{{ $viewingService->duration_formatted }}</p>
                        <p class="text-xs text-gray-500 font-semibold">Duração</p>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-xl">
                        <p class="text-2xl font-bold text-green-600">{{ number_format($viewingService->price, 0, ',', '.') }} Kz</p>
                        <p class="text-xs text-gray-500 font-semibold">Preço</p>
                    </div>
                    <div class="text-center p-4 bg-pink-50 rounded-xl">
                        <p class="text-2xl font-bold text-pink-600">{{ $viewingService->commission_percent }}%</p>
                        <p class="text-xs text-gray-500 font-semibold">Comissão</p>
                    </div>
                </div>

                <!-- Detalhes -->
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-info-circle text-indigo-500 mr-2"></i>Informações
                        </h4>
                        <div class="space-y-2 text-sm">
                            <p class="flex justify-between">
                                <span class="text-gray-500">ID:</span>
                                <span class="font-semibold">{{ $viewingService->id }}</span>
                            </p>
                            <p class="flex justify-between">
                                <span class="text-gray-500">Código:</span>
                                <span class="font-semibold">{{ $viewingService->code ?? '-' }}</span>
                            </p>
                            <p class="flex justify-between">
                                <span class="text-gray-500">Custo:</span>
                                <span class="font-semibold">{{ number_format($viewingService->cost, 0, ',', '.') }} Kz</span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-cog text-purple-500 mr-2"></i>Configurações
                        </h4>
                        <div class="space-y-2 text-sm">
                            <p class="flex items-center justify-between">
                                <span class="text-gray-500">Status:</span>
                                @if($viewingService->is_active)
                                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-semibold">Ativo</span>
                                @else
                                    <span class="px-2 py-1 bg-red-100 text-red-700 rounded-lg text-xs font-semibold">Inativo</span>
                                @endif
                            </p>
                            <p class="flex items-center justify-between">
                                <span class="text-gray-500">Agend. Online:</span>
                                @if($viewingService->online_booking)
                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold">Sim</span>
                                @else
                                    <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-lg text-xs font-semibold">Não</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                @if($viewingService->text_description)
                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                    <h4 class="text-sm font-bold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-align-left text-gray-500 mr-2"></i>Descrição
                    </h4>
                    <p class="text-sm text-gray-600">{{ $viewingService->text_description }}</p>
                </div>
                @endif

                <!-- Profissionais -->
                @if($viewingService->professionals && $viewingService->professionals->count() > 0)
                <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-user-tie text-indigo-500 mr-2"></i>Profissionais que realizam este serviço
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach($viewingService->professionals as $professional)
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm font-semibold">
                                {{ $professional->name }}
                            </span>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-between">
                    <button wire:click="openModal({{ $viewingService->id }})" class="px-6 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-semibold transition">
                        <i class="fas fa-edit mr-2"></i>Editar
                    </button>
                    <button wire:click="closeViewModal" class="px-6 py-2.5 bg-gray-200 hover:bg-gray-300 rounded-xl font-semibold transition">
                        <i class="fas fa-times mr-2"></i>Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
