<div>
    {{-- Flash Messages --}}
    @if (session()->has('info'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" 
             class="mb-6 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-2xl shadow-lg p-4 text-white flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-info-circle text-2xl mr-3"></i>
                <span class="font-semibold">{{ session('info') }}</span>
            </div>
            <button @click="show = false" class="text-white hover:text-blue-100 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-green-600 to-teal-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-chart-bar text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Relatórios da Oficina</h2>
                    <p class="text-green-100 text-sm">Análise detalhada das operações</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-sliders-h mr-2 text-green-600"></i>
                Configurações do Relatório
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-file-alt mr-1 text-green-600"></i>Tipo de Relatório
                </label>
                <select wire:model.live="reportType" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                        <option value="services">Serviços</option>
                        <option value="revenue">Receita</option>
                        <option value="vehicles">Veículos</option>
                        <option value="mechanics">Mecânicos</option>
                        <option value="workorders">Ordens de Serviço</option>
                    </select>
                </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-calendar-day mr-1 text-blue-600"></i>Data Início
                </label>
                <input type="date" wire:model.live="dateFrom" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-calendar-check mr-1 text-purple-600"></i>Data Fim
                </label>
                <input type="date" wire:model.live="dateTo" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
            </div>

            @if($reportType === 'workorders')
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                        <i class="fas fa-info-circle mr-1 text-indigo-600"></i>Status
                    </label>
                    <select wire:model.live="statusFilter" 
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                            <option value="">Todos</option>
                            <option value="pending">Pendente</option>
                            <option value="scheduled">Agendada</option>
                            <option value="in_progress">Em Andamento</option>
                            <option value="completed">Concluída</option>
                            <option value="delivered">Entregue</option>
                    </select>
                </div>
            @endif

            <div class="flex items-end">
                <div class="flex space-x-2">
                    <button wire:click="exportToPdf" 
                            class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                        <i class="fas fa-file-pdf mr-2"></i>PDF
                    </button>
                    <button wire:click="exportToExcel" 
                            class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                        <i class="fas fa-file-excel mr-2"></i>Excel
                    </button>
                </div>
            </div>
        </div>
    </div>

        <!-- Relatório de Serviços -->
        @if($reportType === 'services')
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700">
                    <h3 class="text-xl font-bold text-white">Relatório de Serviços</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Serviço</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Utilizações</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Quantidade</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Preço Médio</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Receita Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($reportData as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $item->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $item->total_uses }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $item->total_quantity }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ number_format($item->avg_price, 2, ',', '.') }} Kz</td>
                                    <td class="px-6 py-4 text-sm font-bold text-green-600">{{ number_format($item->total_revenue, 2, ',', '.') }} Kz</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">Nenhum dado encontrado</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Relatório de Receita -->
        @if($reportType === 'revenue')
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-green-700">
                    <h3 class="text-xl font-bold text-white">Relatório de Receita</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Data</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Total OS</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Mão de Obra</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Peças</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Pago</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Pendente</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($reportData as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ \Carbon\Carbon::parse($item->date)->format('d/m/Y') }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $item->total_orders }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ number_format($item->labor_revenue, 2, ',', '.') }} Kz</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ number_format($item->parts_revenue, 2, ',', '.') }} Kz</td>
                                    <td class="px-6 py-4 text-sm font-bold text-blue-600">{{ number_format($item->total_revenue, 2, ',', '.') }} Kz</td>
                                    <td class="px-6 py-4 text-sm font-bold text-green-600">{{ number_format($item->paid_amount, 2, ',', '.') }} Kz</td>
                                    <td class="px-6 py-4 text-sm font-bold text-orange-600">{{ number_format($item->pending_amount, 2, ',', '.') }} Kz</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">Nenhum dado encontrado</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Relatório de Veículos -->
        @if($reportType === 'vehicles')
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-purple-600 to-purple-700">
                    <h3 class="text-xl font-bold text-white">Relatório de Veículos</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Matrícula</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Veículo</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Proprietário</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Total OS</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">OS no Período</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Receita no Período</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($reportData as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-bold text-gray-900">{{ $item->plate }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $item->brand }} {{ $item->model }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $item->owner_name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $item->total_orders }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-blue-600">{{ $item->period_orders }}</td>
                                    <td class="px-6 py-4 text-sm font-bold text-green-600">{{ number_format($item->period_revenue, 2, ',', '.') }} Kz</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">Nenhum dado encontrado</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Relatório de Mecânicos -->
        @if($reportType === 'mechanics')
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-orange-600 to-orange-700">
                    <h3 class="text-xl font-bold text-white">Relatório de Mecânicos</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Mecânico</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Total OS</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Concluídas</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Em Andamento</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Receita Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($reportData as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $item->first_name }} {{ $item->last_name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $item->total_orders }}</td>
                                    <td class="px-6 py-4 text-sm font-bold text-green-600">{{ $item->completed_orders }}</td>
                                    <td class="px-6 py-4 text-sm font-bold text-orange-600">{{ $item->in_progress_orders }}</td>
                                    <td class="px-6 py-4 text-sm font-bold text-blue-600">{{ number_format($item->total_revenue, 2, ',', '.') }} Kz</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">Nenhum dado encontrado</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Relatório de Ordens de Serviço -->
        @if($reportType === 'workorders')
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-indigo-600 to-indigo-700">
                    <h3 class="text-xl font-bold text-white">Relatório de Ordens de Serviço</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Nº OS</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Veículo</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Mecânico</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Data</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($reportData as $order)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-bold text-gray-900">{{ $order->order_number }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $order->vehicle->plate }} - {{ $order->vehicle->brand }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $order->mechanic ? $order->mechanic->full_name : 'N/A' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $order->received_at->format('d/m/Y') }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        @if($order->status === 'completed')
                                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-bold">Concluída</span>
                                        @elseif($order->status === 'in_progress')
                                            <span class="px-2 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-bold">Em Andamento</span>
                                        @else
                                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-bold">{{ ucfirst($order->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm font-bold text-green-600">{{ number_format($order->total, 2, ',', '.') }} Kz</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">Nenhum dado encontrado</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
