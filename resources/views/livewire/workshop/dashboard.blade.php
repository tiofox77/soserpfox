<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-cyan-600 to-blue-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-tachometer-alt text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Dashboard da Oficina</h2>
                    <p class="text-cyan-100 text-sm">Visão geral das operações em tempo real</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros de Data --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-calendar mr-2 text-cyan-600"></i>
                Período de Análise
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-calendar-day mr-1 text-blue-600"></i>Data Início
                </label>
                <input type="date" wire:model.live="dateFrom" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-calendar-check mr-1 text-green-600"></i>Data Fim
                </label>
                <input type="date" wire:model.live="dateTo" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all">
            </div>
        </div>
    </div>

        <!-- KPIs Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total OS -->
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total OS</p>
                        <p class="text-3xl font-bold mt-2">{{ $totalOrders }}</p>
                    </div>
                    <div class="bg-blue-400 bg-opacity-30 rounded-full p-4">
                        <i class="fas fa-clipboard-list text-3xl"></i>
                    </div>
                </div>
            </div>

            <!-- OS Pendentes -->
            <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-yellow-100 text-sm font-medium">Pendentes</p>
                        <p class="text-3xl font-bold mt-2">{{ $pendingOrders }}</p>
                    </div>
                    <div class="bg-yellow-400 bg-opacity-30 rounded-full p-4">
                        <i class="fas fa-clock text-3xl"></i>
                    </div>
                </div>
            </div>

            <!-- Em Andamento -->
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">Em Andamento</p>
                        <p class="text-3xl font-bold mt-2">{{ $inProgressOrders }}</p>
                    </div>
                    <div class="bg-orange-400 bg-opacity-30 rounded-full p-4">
                        <i class="fas fa-tools text-3xl"></i>
                    </div>
                </div>
            </div>

            <!-- Concluídas -->
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Concluídas</p>
                        <p class="text-3xl font-bold mt-2">{{ $completedOrders }}</p>
                    </div>
                    <div class="bg-green-400 bg-opacity-30 rounded-full p-4">
                        <i class="fas fa-check-circle text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financeiro e Veículos -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Receita -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Receita</h3>
                    <i class="fas fa-money-bill-wave text-2xl text-green-600"></i>
                </div>
                <p class="text-3xl font-bold text-green-600">{{ number_format($totalRevenue, 2, ',', '.') }} Kz</p>
                <p class="text-sm text-gray-500 mt-2">Período selecionado</p>
            </div>

            <!-- Pagamentos Pendentes -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">A Receber</h3>
                    <i class="fas fa-hourglass-half text-2xl text-orange-600"></i>
                </div>
                <p class="text-3xl font-bold text-orange-600">{{ number_format($pendingPayments, 2, ',', '.') }} Kz</p>
                <p class="text-sm text-gray-500 mt-2">Pagamentos pendentes</p>
            </div>

            <!-- Veículos -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Veículos</h3>
                    <i class="fas fa-car text-2xl text-blue-600"></i>
                </div>
                <p class="text-3xl font-bold text-blue-600">{{ $totalVehicles }}</p>
                <p class="text-sm text-gray-500 mt-2">{{ $activeVehicles }} ativos</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Serviços Mais Utilizados -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-star mr-2 text-yellow-500"></i>
                    Top 5 Serviços
                </h3>
                <div class="space-y-3">
                    @forelse($topServices as $service)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex-1">
                                <p class="font-medium text-gray-900">{{ $service->name }}</p>
                                <p class="text-sm text-gray-500">{{ $service->count }}x utilizado</p>
                            </div>
                            <p class="text-green-600 font-bold">{{ number_format($service->revenue, 0, ',', '.') }} Kz</p>
                        </div>
                    @empty
                        <p class="text-gray-500 text-center py-4">Nenhum serviço registrado</p>
                    @endforelse
                </div>
            </div>

            <!-- OS por Status -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-chart-pie mr-2 text-blue-600"></i>
                    Distribuição por Status
                </h3>
                <div class="space-y-3">
                    @foreach($ordersByStatus as $statusData)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                @if($statusData->status === 'pending')
                                    <span class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></span>
                                    <span class="font-medium">Pendente</span>
                                @elseif($statusData->status === 'scheduled')
                                    <span class="w-3 h-3 bg-blue-500 rounded-full mr-3"></span>
                                    <span class="font-medium">Agendada</span>
                                @elseif($statusData->status === 'in_progress')
                                    <span class="w-3 h-3 bg-orange-500 rounded-full mr-3"></span>
                                    <span class="font-medium">Em Andamento</span>
                                @elseif($statusData->status === 'completed')
                                    <span class="w-3 h-3 bg-green-500 rounded-full mr-3"></span>
                                    <span class="font-medium">Concluída</span>
                                @elseif($statusData->status === 'delivered')
                                    <span class="w-3 h-3 bg-gray-500 rounded-full mr-3"></span>
                                    <span class="font-medium">Entregue</span>
                                @else
                                    <span class="w-3 h-3 bg-red-500 rounded-full mr-3"></span>
                                    <span class="font-medium">Cancelada</span>
                                @endif
                            </div>
                            <span class="font-bold text-gray-900">{{ $statusData->count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Alertas -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- OS Urgentes -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2 text-red-600"></i>
                    Ordens Urgentes
                </h3>
                <div class="space-y-3">
                    @forelse($urgentOrders as $order)
                        <div class="flex items-center justify-between p-3 bg-red-50 border-l-4 border-red-500 rounded">
                            <div>
                                <p class="font-bold text-gray-900">{{ $order->order_number }}</p>
                                <p class="text-sm text-gray-600">{{ $order->vehicle->plate }} - {{ $order->vehicle->owner_name }}</p>
                            </div>
                            <span class="text-xs px-2 py-1 bg-red-100 text-red-800 rounded-full font-bold">URGENTE</span>
                        </div>
                    @empty
                        <p class="text-gray-500 text-center py-4">Nenhuma OS urgente</p>
                    @endforelse
                </div>
            </div>

            <!-- Documentos Vencendo -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-file-alt mr-2 text-yellow-600"></i>
                    Documentos Vencendo
                </h3>
                <div class="space-y-3">
                    @forelse($expiringDocuments as $vehicle)
                        <div class="flex items-center justify-between p-3 bg-yellow-50 border-l-4 border-yellow-500 rounded">
                            <div>
                                <p class="font-bold text-gray-900">{{ $vehicle->plate }}</p>
                                <p class="text-sm text-gray-600">{{ $vehicle->brand }} {{ $vehicle->model }}</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    @if($vehicle->registration_expiry && $vehicle->registration_expiry <= now()->addDays(30))
                                        Livrete: {{ $vehicle->registration_expiry->format('d/m/Y') }}
                                    @endif
                                    @if($vehicle->insurance_expiry && $vehicle->insurance_expiry <= now()->addDays(30))
                                        Seguro: {{ $vehicle->insurance_expiry->format('d/m/Y') }}
                                    @endif
                                    @if($vehicle->inspection_expiry && $vehicle->inspection_expiry <= now()->addDays(30))
                                        Inspeção: {{ $vehicle->inspection_expiry->format('d/m/Y') }}
                                    @endif
                                </p>
                            </div>
                            <i class="fas fa-exclamation-circle text-yellow-600 text-xl"></i>
                        </div>
                    @empty
                        <p class="text-gray-500 text-center py-4">Todos os documentos em dia</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
