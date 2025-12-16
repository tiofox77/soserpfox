{{-- Modal de Visualização do Mecânico --}}
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ activeTab: 'info' }">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="closeViewModal"></div>

        <div class="inline-block w-full max-w-5xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-user-cog text-white text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">{{ $viewingMechanic->name }}</h3>
                        <p class="text-purple-100 text-sm">{{ ucfirst($viewingMechanic->level) }} • {{ $viewingMechanic->specialties_formatted }}</p>
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
                        <i class="fas fa-info-circle mr-2"></i>Informações
                    </button>
                    <button @click="activeTab = 'stats'" 
                            :class="activeTab === 'stats' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-chart-bar mr-2"></i>Desempenho
                    </button>
                    <button @click="activeTab = 'history'" 
                            :class="activeTab === 'history' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-history mr-2"></i>Histórico
                        <span class="ml-2 bg-purple-100 text-purple-800 text-xs font-bold px-2 py-0.5 rounded-full">
                            {{ $viewingMechanic->workOrders->count() }}
                        </span>
                    </button>
                </nav>
            </div>

            {{-- Content --}}
            <div class="p-6 max-h-[600px] overflow-y-auto">
                
                {{-- Tab: Informações --}}
                <div x-show="activeTab === 'info'" class="space-y-6">
                    {{-- Status --}}
                    <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-4 border border-purple-100">
                        <p class="text-sm text-gray-600 mb-2">Status</p>
                        @if($viewingMechanic->is_active)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-green-500 text-white">
                                <i class="fas fa-check-circle mr-2"></i>Ativo
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-gray-500 text-white">
                                <i class="fas fa-times-circle mr-2"></i>Inativo
                            </span>
                        @endif
                    </div>

                    {{-- Dados de Contato --}}
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-address-card text-blue-600 mr-2"></i>Contato
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Telefone</p>
                                <p class="font-bold text-gray-900">{{ $viewingMechanic->phone }}</p>
                            </div>
                            @if($viewingMechanic->mobile)
                            <div>
                                <p class="text-sm text-gray-600">Celular</p>
                                <p class="font-bold text-gray-900">{{ $viewingMechanic->mobile }}</p>
                            </div>
                            @endif
                            @if($viewingMechanic->email)
                            <div>
                                <p class="text-sm text-gray-600">Email</p>
                                <p class="font-bold text-gray-900">{{ $viewingMechanic->email }}</p>
                            </div>
                            @endif
                            @if($viewingMechanic->document)
                            <div>
                                <p class="text-sm text-gray-600">Documento</p>
                                <p class="font-bold text-gray-900">{{ $viewingMechanic->document }}</p>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Especialidades --}}
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-tools text-orange-600 mr-2"></i>Especialidades
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($viewingMechanic->specialties as $specialty)
                                <span class="px-4 py-2 bg-gradient-to-r from-purple-100 to-pink-100 text-purple-800 rounded-xl font-semibold text-sm">
                                    {{ $specialty }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    {{-- Valores --}}
                    <div class="grid grid-cols-2 gap-4">
                        @if($viewingMechanic->hourly_rate > 0)
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-6 border-2 border-green-200">
                            <p class="text-sm text-gray-600 mb-2">Taxa por Hora</p>
                            <p class="text-3xl font-bold text-green-900">{{ number_format($viewingMechanic->hourly_rate, 2, ',', '.') }} Kz</p>
                        </div>
                        @endif
                        @if($viewingMechanic->daily_rate > 0)
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border-2 border-blue-200">
                            <p class="text-sm text-gray-600 mb-2">Taxa por Dia</p>
                            <p class="text-3xl font-bold text-blue-900">{{ number_format($viewingMechanic->daily_rate, 2, ',', '.') }} Kz</p>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Tab: Desempenho --}}
                <div x-show="activeTab === 'stats'" class="space-y-6">
                    <div class="grid grid-cols-3 gap-6">
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border-2 border-blue-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-bold text-blue-900">Total de OS</p>
                                <i class="fas fa-clipboard-list text-blue-600 text-2xl"></i>
                            </div>
                            <p class="text-4xl font-bold text-blue-900">{{ $viewingMechanic->workOrders->count() }}</p>
                        </div>
                        
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-6 border-2 border-green-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-bold text-green-900">Concluídas</p>
                                <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                            </div>
                            <p class="text-4xl font-bold text-green-900">{{ $viewingMechanic->workOrders->whereIn('status', ['completed', 'delivered'])->count() }}</p>
                        </div>
                        
                        <div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-xl p-6 border-2 border-yellow-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-bold text-yellow-900">Em Andamento</p>
                                <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                            </div>
                            <p class="text-4xl font-bold text-yellow-900">{{ $viewingMechanic->workOrders->whereIn('status', ['pending', 'in_progress', 'waiting_parts'])->count() }}</p>
                        </div>
                    </div>

                    {{-- Receita Gerada --}}
                    <div class="bg-gradient-to-r from-purple-100 to-pink-100 rounded-xl p-6 border-2 border-purple-200">
                        <h4 class="font-bold text-purple-900 mb-2">Receita Total Gerada</h4>
                        <p class="text-4xl font-bold text-purple-900">{{ number_format($viewingMechanic->workOrders->sum('labor_total'), 2, ',', '.') }} Kz</p>
                        <p class="text-sm text-purple-700 mt-2">Mão de obra em {{ $viewingMechanic->workOrders->count() }} ordens de serviço</p>
                    </div>
                </div>

                {{-- Tab: Histórico --}}
                <div x-show="activeTab === 'history'" class="space-y-4">
                    @forelse($viewingMechanic->workOrders()->latest()->get() as $order)
                        <div class="bg-white rounded-xl border-2 border-gray-200 p-4 hover:shadow-lg transition-all">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h5 class="font-bold text-gray-900">{{ $order->order_number }}</h5>
                                    <p class="text-sm text-gray-600">{{ $order->vehicle->plate }} - {{ $order->vehicle->brand }} {{ $order->vehicle->model }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ $order->received_at->format('d/m/Y') }}</p>
                                </div>
                                <div class="text-right">
                                    @if($order->status === 'completed' || $order->status === 'delivered')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-500 text-white">
                                            Concluída
                                        </span>
                                    @elseif($order->status === 'in_progress')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-500 text-white">
                                            Em Andamento
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-yellow-400 text-yellow-900">
                                            Pendente
                                        </span>
                                    @endif
                                    <p class="text-sm font-bold text-purple-600 mt-1">{{ number_format($order->labor_total, 2, ',', '.') }} Kz</p>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-sm text-gray-900">{{ Str::limit($order->problem_description, 120) }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <i class="fas fa-clipboard-list text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 font-medium">Nenhuma ordem de serviço ainda</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t">
                <button wire:click="closeViewModal" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl font-semibold transition-all">
                    Fechar
                </button>
                
                <button wire:click="edit({{ $viewingMechanic->id }})" class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-semibold transition-all flex items-center">
                    <i class="fas fa-edit mr-2"></i>Editar Mecânico
                </button>
            </div>
        </div>
    </div>
</div>
