{{-- Modal de Visualização do Veículo --}}
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ activeTab: 'info' }">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="closeViewModal"></div>

        <div class="inline-block w-full max-w-6xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-car text-white text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">{{ $viewingVehicle->plate }}</h3>
                        <p class="text-blue-100 text-sm">{{ $viewingVehicle->brand }} {{ $viewingVehicle->model }} ({{ $viewingVehicle->year }})</p>
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
                            :class="activeTab === 'info' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>Informações
                    </button>
                    <button @click="activeTab = 'history'" 
                            :class="activeTab === 'history' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-history mr-2"></i>Histórico de Serviços
                        <span class="ml-2 bg-blue-100 text-blue-800 text-xs font-bold px-2 py-0.5 rounded-full">
                            {{ $viewingVehicle->workOrders->count() }}
                        </span>
                    </button>
                    <button @click="activeTab = 'stats'" 
                            :class="activeTab === 'stats' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-1 border-b-2 font-semibold text-sm transition-all flex items-center">
                        <i class="fas fa-chart-line mr-2"></i>Estatísticas
                    </button>
                </nav>
            </div>

            {{-- Content --}}
            <div class="p-6 max-h-[600px] overflow-y-auto">
                
                {{-- Tab: Informações --}}
                <div x-show="activeTab === 'info'" class="space-y-6">
                    {{-- Status --}}
                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-100">
                        <p class="text-sm text-gray-600 mb-2">Status do Veículo</p>
                        @if($viewingVehicle->status === 'active')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-green-500 text-white">
                                <i class="fas fa-check-circle mr-2"></i>Ativo
                            </span>
                        @elseif($viewingVehicle->status === 'in_service')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-orange-500 text-white">
                                <i class="fas fa-wrench mr-2"></i>Em Serviço
                            </span>
                        @elseif($viewingVehicle->status === 'completed')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-blue-500 text-white">
                                <i class="fas fa-flag-checkered mr-2"></i>Concluído
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-gray-500 text-white">
                                <i class="fas fa-times-circle mr-2"></i>Inativo
                            </span>
                        @endif
                    </div>

                    {{-- Dados do Proprietário --}}
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-user text-purple-600 mr-2"></i>Proprietário
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Nome</p>
                                <p class="font-bold text-gray-900">{{ $viewingVehicle->owner_name }}</p>
                            </div>
                            @if($viewingVehicle->owner_phone)
                            <div>
                                <p class="text-sm text-gray-600">Telefone</p>
                                <p class="font-bold text-gray-900">{{ $viewingVehicle->owner_phone }}</p>
                            </div>
                            @endif
                            @if($viewingVehicle->owner_email)
                            <div>
                                <p class="text-sm text-gray-600">Email</p>
                                <p class="font-bold text-gray-900">{{ $viewingVehicle->owner_email }}</p>
                            </div>
                            @endif
                            @if($viewingVehicle->owner_nif)
                            <div>
                                <p class="text-sm text-gray-600">NIF</p>
                                <p class="font-bold text-gray-900">{{ $viewingVehicle->owner_nif }}</p>
                            </div>
                            @endif
                        </div>
                        @if($viewingVehicle->owner_address)
                        <div class="mt-4">
                            <p class="text-sm text-gray-600">Endereço</p>
                            <p class="font-bold text-gray-900">{{ $viewingVehicle->owner_address }}</p>
                        </div>
                        @endif
                    </div>

                    {{-- Dados do Veículo --}}
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-car text-blue-600 mr-2"></i>Dados Técnicos
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">VIN/Chassi</p>
                                <p class="font-bold text-gray-900 font-mono">{{ $viewingVehicle->vin ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Número do Motor</p>
                                <p class="font-bold text-gray-900 font-mono">{{ $viewingVehicle->engine_number ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Combustível</p>
                                <p class="font-bold text-gray-900">{{ ucfirst($viewingVehicle->fuel_type ?? 'N/A') }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Quilometragem Atual</p>
                                <p class="font-bold text-gray-900">{{ number_format($viewingVehicle->current_mileage ?? 0, 0, ',', '.') }} km</p>
                            </div>
                        </div>
                    </div>

                    {{-- Documentos e Vencimentos --}}
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-file-alt text-yellow-600 mr-2"></i>Documentos
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            @if($viewingVehicle->insurance_expiry)
                            <div class="p-3 rounded-lg {{ $viewingVehicle->insurance_expiry->isPast() ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200' }}">
                                <p class="text-sm font-bold {{ $viewingVehicle->insurance_expiry->isPast() ? 'text-red-900' : 'text-green-900' }}">Seguro</p>
                                <p class="text-sm {{ $viewingVehicle->insurance_expiry->isPast() ? 'text-red-700' : 'text-green-700' }}">
                                    {{ $viewingVehicle->insurance_expiry->format('d/m/Y') }}
                                    @if($viewingVehicle->insurance_expiry->isPast())
                                        <span class="font-bold">(VENCIDO)</span>
                                    @endif
                                </p>
                            </div>
                            @endif
                            
                            @if($viewingVehicle->inspection_expiry)
                            <div class="p-3 rounded-lg {{ $viewingVehicle->inspection_expiry->isPast() ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200' }}">
                                <p class="text-sm font-bold {{ $viewingVehicle->inspection_expiry->isPast() ? 'text-red-900' : 'text-green-900' }}">Inspeção</p>
                                <p class="text-sm {{ $viewingVehicle->inspection_expiry->isPast() ? 'text-red-700' : 'text-green-700' }}">
                                    {{ $viewingVehicle->inspection_expiry->format('d/m/Y') }}
                                    @if($viewingVehicle->inspection_expiry->isPast())
                                        <span class="font-bold">(VENCIDO)</span>
                                    @endif
                                </p>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Notas --}}
                    @if($viewingVehicle->notes)
                    <div class="bg-yellow-50 rounded-xl p-4 border border-yellow-200">
                        <p class="text-sm font-bold text-yellow-900 mb-2"><i class="fas fa-sticky-note mr-2"></i>Observações</p>
                        <p class="text-gray-700">{{ $viewingVehicle->notes }}</p>
                    </div>
                    @endif
                </div>

                {{-- Tab: Histórico de Serviços --}}
                <div x-show="activeTab === 'history'" class="space-y-4">
                    @forelse($viewingVehicle->workOrders()->latest()->get() as $order)
                        <div class="bg-white rounded-xl border-2 border-gray-200 p-4 hover:shadow-lg transition-all">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h5 class="font-bold text-gray-900">{{ $order->order_number }}</h5>
                                    <p class="text-sm text-gray-600">{{ $order->received_at->format('d/m/Y H:i') }}</p>
                                </div>
                                <div class="text-right">
                                    @if($order->status === 'pending')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-yellow-400 text-yellow-900">
                                            Pendente
                                        </span>
                                    @elseif($order->status === 'in_progress')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-500 text-white">
                                            Em Andamento
                                        </span>
                                    @elseif($order->status === 'completed')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-500 text-white">
                                            Concluída
                                        </span>
                                    @elseif($order->status === 'delivered')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-500 text-white">
                                            Entregue
                                        </span>
                                    @endif
                                    <p class="text-lg font-bold text-purple-600 mt-1">{{ number_format($order->total, 2, ',', '.') }} Kz</p>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 rounded-lg p-3 mb-3">
                                <p class="text-sm text-gray-600 mb-1">Problema:</p>
                                <p class="text-sm text-gray-900">{{ Str::limit($order->problem_description, 150) }}</p>
                            </div>
                            
                            @if($order->items->count() > 0)
                            <div class="border-t pt-3">
                                <p class="text-xs text-gray-600 mb-2">Serviços/Peças:</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($order->items->take(3) as $item)
                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-semibold {{ $item->type === 'service' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                            {{ $item->name }}
                                        </span>
                                    @endforeach
                                    @if($order->items->count() > 3)
                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-semibold bg-gray-100 text-gray-800">
                                            +{{ $order->items->count() - 3 }} mais
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <i class="fas fa-clipboard-list text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 font-medium">Nenhum serviço realizado ainda</p>
                        </div>
                    @endforelse
                </div>

                {{-- Tab: Estatísticas --}}
                <div x-show="activeTab === 'stats'" class="space-y-6">
                    <div class="grid grid-cols-3 gap-6">
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border-2 border-blue-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-bold text-blue-900">Total de Serviços</p>
                                <i class="fas fa-wrench text-blue-600 text-2xl"></i>
                            </div>
                            <p class="text-4xl font-bold text-blue-900">{{ $viewingVehicle->workOrders->count() }}</p>
                        </div>
                        
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-6 border-2 border-green-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-bold text-green-900">Total Gasto</p>
                                <i class="fas fa-dollar-sign text-green-600 text-2xl"></i>
                            </div>
                            <p class="text-3xl font-bold text-green-900">{{ number_format($viewingVehicle->workOrders->sum('total'), 2, ',', '.') }} Kz</p>
                        </div>
                        
                        <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-6 border-2 border-purple-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-bold text-purple-900">Ticket Médio</p>
                                <i class="fas fa-chart-line text-purple-600 text-2xl"></i>
                            </div>
                            <p class="text-3xl font-bold text-purple-900">
                                {{ $viewingVehicle->workOrders->count() > 0 ? number_format($viewingVehicle->workOrders->avg('total'), 2, ',', '.') : '0,00' }} Kz
                            </p>
                        </div>
                    </div>

                    {{-- Serviços Mais Frequentes --}}
                    @if($viewingVehicle->workOrders->count() > 0)
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-list-ol text-orange-600 mr-2"></i>Serviços Mais Frequentes
                        </h4>
                        @php
                            $services = $viewingVehicle->workOrders->pluck('items')->flatten()->where('type', 'service');
                            $topServices = $services->groupBy('name')->map(function($group) {
                                return [
                                    'name' => $group->first()->name,
                                    'count' => $group->count(),
                                    'total' => $group->sum('subtotal')
                                ];
                            })->sortByDesc('count')->take(5);
                        @endphp
                        
                        @if($topServices->count() > 0)
                            <div class="space-y-3">
                                @foreach($topServices as $service)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex-1">
                                        <p class="font-bold text-gray-900">{{ $service['name'] }}</p>
                                        <p class="text-xs text-gray-600">{{ $service['count'] }}x realizado</p>
                                    </div>
                                    <p class="text-sm font-bold text-purple-600">{{ number_format($service['total'], 2, ',', '.') }} Kz</p>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 text-center py-4">Nenhum serviço registrado</p>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t">
                <button wire:click="closeViewModal" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl font-semibold transition-all">
                    Fechar
                </button>
                
                <button wire:click="edit({{ $viewingVehicle->id }})" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition-all flex items-center">
                    <i class="fas fa-edit mr-2"></i>Editar Veículo
                </button>
            </div>
        </div>
    </div>
</div>
