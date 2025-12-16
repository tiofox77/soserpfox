{{-- Modal de Visualização do Serviço --}}
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ activeTab: 'info' }">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="closeViewModal"></div>

        <div class="inline-block w-full max-w-4xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-tools text-white text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">{{ $viewingService->name }}</h3>
                        <p class="text-purple-100 text-sm">{{ $viewingService->service_code }} • {{ $viewingService->category }}</p>
                    </div>
                </div>
                <button wire:click="closeViewModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition-all">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            {{-- Tabs --}}
            <div class="border-b border-gray-200 bg-gray-50 px-6">
                <nav class="flex space-x-4">
                    <button @click="activeTab = 'info'" 
                            :class="activeTab === 'info' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>Detalhes
                    </button>
                    <button @click="activeTab = 'stats'" 
                            :class="activeTab === 'stats' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-chart-bar mr-2"></i>Estatísticas
                    </button>
                    <button @click="activeTab = 'history'" 
                            :class="activeTab === 'history' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-history mr-2"></i>Histórico de Uso
                        <span class="ml-2 bg-purple-100 text-purple-800 text-xs font-bold px-2 py-0.5 rounded-full">
                            {{ $viewingService->workOrderItems->count() }}
                        </span>
                    </button>
                </nav>
            </div>

            {{-- Content --}}
            <div class="p-6 max-h-[600px] overflow-y-auto">
                
                {{-- Tab: Detalhes --}}
                <div x-show="activeTab === 'info'" class="space-y-6">
                    {{-- Status --}}
                    <div class="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-xl p-4 border border-purple-100">
                        <p class="text-sm text-gray-600 mb-2">Status</p>
                        @if($viewingService->is_active)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-green-500 text-white">
                                <i class="fas fa-check-circle mr-2"></i>Ativo
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-gray-500 text-white">
                                <i class="fas fa-times-circle mr-2"></i>Inativo
                            </span>
                        @endif
                    </div>

                    {{-- Informações Básicas --}}
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>Informações Básicas
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Código</p>
                                <p class="font-bold text-gray-900">{{ $viewingService->service_code }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Categoria</p>
                                <p class="font-bold text-gray-900">{{ $viewingService->category }}</p>
                            </div>
                        </div>
                        
                        @if($viewingService->description)
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <p class="text-sm text-gray-600 mb-2">Descrição</p>
                            <p class="text-gray-900">{{ $viewingService->description }}</p>
                        </div>
                        @endif
                    </div>

                    {{-- Valores e Tempo --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-6 border-2 border-green-200">
                            <p class="text-sm text-gray-600 mb-2">Custo Mão de Obra</p>
                            <p class="text-3xl font-bold text-green-900">{{ $viewingService->formatted_labor_cost }}</p>
                        </div>
                        
                        <div class="bg-gradient-to-br from-orange-50 to-amber-50 rounded-xl p-6 border-2 border-orange-200">
                            <p class="text-sm text-gray-600 mb-2">Tempo Estimado</p>
                            <p class="text-3xl font-bold text-orange-900">{{ $viewingService->estimated_hours }}h</p>
                        </div>
                    </div>
                </div>

                {{-- Tab: Estatísticas --}}
                <div x-show="activeTab === 'stats'" class="space-y-6">
                    <div class="grid grid-cols-3 gap-6">
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border-2 border-blue-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-bold text-blue-900">Vezes Utilizado</p>
                                <i class="fas fa-redo text-blue-600 text-2xl"></i>
                            </div>
                            <p class="text-4xl font-bold text-blue-900">{{ $viewingService->workOrderItems->count() }}</p>
                        </div>
                        
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-6 border-2 border-green-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-bold text-green-900">Receita Total</p>
                                <i class="fas fa-money-bill-wave text-green-600 text-2xl"></i>
                            </div>
                            <p class="text-4xl font-bold text-green-900">{{ number_format($viewingService->workOrderItems->sum('subtotal'), 2, ',', '.') }} Kz</p>
                        </div>
                        
                        <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-6 border-2 border-purple-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-bold text-purple-900">Preço Médio</p>
                                <i class="fas fa-calculator text-purple-600 text-2xl"></i>
                            </div>
                            <p class="text-4xl font-bold text-purple-900">
                                @if($viewingService->workOrderItems->count() > 0)
                                    {{ number_format($viewingService->workOrderItems->avg('unit_price'), 2, ',', '.') }} Kz
                                @else
                                    0,00 Kz
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Horas Totais --}}
                    <div class="bg-gradient-to-r from-orange-100 to-amber-100 rounded-xl p-6 border-2 border-orange-200">
                        <h4 class="font-bold text-orange-900 mb-2">Total de Horas Trabalhadas</h4>
                        <p class="text-4xl font-bold text-orange-900">{{ $viewingService->workOrderItems->sum('hours') ?? 0 }}h</p>
                        <p class="text-sm text-orange-700 mt-2">Em {{ $viewingService->workOrderItems->count() }} ordens de serviço</p>
                    </div>
                </div>

                {{-- Tab: Histórico de Uso --}}
                <div x-show="activeTab === 'history'" class="space-y-4">
                    @forelse($viewingService->workOrderItems()->with('workOrder.vehicle')->latest()->get() as $item)
                        <div class="bg-white rounded-xl border-2 border-gray-200 p-4 hover:shadow-lg transition-all">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h5 class="font-bold text-gray-900">{{ $item->workOrder->order_number }}</h5>
                                    <p class="text-sm text-gray-600">{{ $item->workOrder->vehicle->plate }} - {{ $item->workOrder->vehicle->brand }} {{ $item->workOrder->vehicle->model }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ $item->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-600">Valor</p>
                                    <p class="text-lg font-bold text-purple-600">{{ number_format($item->subtotal, 2, ',', '.') }} Kz</p>
                                    @if($item->hours)
                                    <p class="text-xs text-gray-500 mt-1">{{ $item->hours }}h trabalhadas</p>
                                    @endif
                                </div>
                            </div>
                            
                            @if($item->description)
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-sm text-gray-900">{{ $item->description }}</p>
                            </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <i class="fas fa-clipboard-list text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 font-medium">Serviço ainda não foi utilizado</p>
                            <p class="text-sm text-gray-400 mt-2">Adicione este serviço a uma ordem de trabalho</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t">
                <button wire:click="closeViewModal" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl font-semibold transition-all">
                    Fechar
                </button>
                
                <button wire:click="edit({{ $viewingService->id }})" class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-semibold transition-all flex items-center">
                    <i class="fas fa-edit mr-2"></i>Editar Serviço
                </button>
            </div>
        </div>
    </div>
</div>
