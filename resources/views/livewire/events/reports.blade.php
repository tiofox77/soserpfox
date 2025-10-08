<div>
    <!-- Header com Gradient -->
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-chart-bar text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Relatórios de Eventos</h2>
                    <p class="text-purple-100 text-sm">Indicadores e estatísticas</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <!-- Dropdown Exportar -->
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl flex items-center">
                        <i class="fas fa-download mr-2"></i>Exportar
                        <i class="fas fa-chevron-down ml-2 text-xs"></i>
                    </button>
                    
                    <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-200 py-2 z-50">
                        <button wire:click="exportToCsv" class="w-full text-left px-4 py-2 hover:bg-gray-50 transition flex items-center">
                            <i class="fas fa-file-csv text-green-600 mr-3 w-5"></i>
                            <span class="text-gray-700">CSV</span>
                        </button>
                        <button wire:click="exportToPdf" class="w-full text-left px-4 py-2 hover:bg-gray-50 transition flex items-center">
                            <i class="fas fa-file-pdf text-red-600 mr-3 w-5"></i>
                            <span class="text-gray-700">PDF</span>
                        </button>
                        <button wire:click="exportToExcel" class="w-full text-left px-4 py-2 hover:bg-gray-50 transition flex items-center">
                            <i class="fas fa-file-excel text-emerald-600 mr-3 w-5"></i>
                            <span class="text-gray-700">Excel</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter text-purple-600 mr-2"></i>Filtros
            </h3>
            <button wire:click="resetFilters" class="text-sm text-gray-600 hover:text-purple-600 transition">
                <i class="fas fa-redo mr-1"></i>Limpar Filtros
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Data Início -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-calendar text-blue-500 mr-1"></i>Data Início
                </label>
                <input type="date" wire:model.live="startDate" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
            </div>
            
            <!-- Data Fim -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-calendar text-blue-500 mr-1"></i>Data Fim
                </label>
                <input type="date" wire:model.live="endDate" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
            </div>
            
            <!-- Cliente -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-user text-green-500 mr-1"></i>Cliente
                </label>
                <select wire:model.live="selectedClient" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                    <option value="">Todos os Clientes</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <!-- Tipo -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-tag text-orange-500 mr-1"></i>Tipo
                </label>
                <select wire:model.live="selectedType" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                    <option value="">Todos os Tipos</option>
                    @foreach($types as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            
            <!-- Status -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-circle text-purple-500 mr-1"></i>Status
                </label>
                <select wire:model.live="selectedStatus" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                    <option value="">Todos os Status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <!-- Total de Eventos -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                    <i class="fas fa-calendar-check text-2xl"></i>
                </div>
                <span class="text-xs opacity-75">Total</span>
            </div>
            <h3 class="text-3xl font-bold mb-1">{{ number_format($this->totalEvents) }}</h3>
            <p class="text-sm opacity-75">Eventos</p>
        </div>
        
        <!-- Valor Total -->
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                    <i class="fas fa-dollar-sign text-2xl"></i>
                </div>
                <span class="text-xs opacity-75">Valor</span>
            </div>
            <h3 class="text-3xl font-bold mb-1">{{ number_format($this->totalValue, 2) }}</h3>
            <p class="text-sm opacity-75">Kz Total</p>
        </div>
        
        <!-- Valor Médio -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                    <i class="fas fa-chart-line text-2xl"></i>
                </div>
                <span class="text-xs opacity-75">Média</span>
            </div>
            <h3 class="text-3xl font-bold mb-1">{{ number_format($this->averageValue, 2) }}</h3>
            <p class="text-sm opacity-75">Kz por Evento</p>
        </div>
        
        <!-- Total Participantes -->
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <span class="text-xs opacity-75">Participantes</span>
            </div>
            <h3 class="text-3xl font-bold mb-1">{{ number_format($this->totalAttendees) }}</h3>
            <p class="text-sm opacity-75">Total Esperado</p>
        </div>
    </div>

    <!-- Gráfico Interativo - Eventos por Mês -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-chart-area text-purple-600 mr-2"></i>Evolução Mensal de Eventos
        </h3>
        <canvas id="monthlyChart" height="80"></canvas>
    </div>

    <!-- Gráficos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Eventos por Mês -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-chart-line text-blue-600 mr-2"></i>Eventos por Mês
            </h3>
            <div class="space-y-2">
                @forelse($this->eventsByMonth as $item)
                    @php
                        $percentage = $this->totalEvents > 0 ? ($item->total / $this->totalEvents) * 100 : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium text-gray-700">{{ \Carbon\Carbon::parse($item->month)->format('M/Y') }}</span>
                            <span class="text-gray-600">{{ $item->total }} eventos</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2.5 rounded-full transition-all" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm text-center py-4">Nenhum evento encontrado</p>
                @endforelse
            </div>
        </div>
        
        <!-- Eventos por Cliente (Top 10) -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-user-tie text-green-600 mr-2"></i>Top 10 Clientes
            </h3>
            <div class="space-y-2">
                @forelse($this->eventsByClient as $item)
                    @php
                        $percentage = $this->totalEvents > 0 ? ($item->total / $this->totalEvents) * 100 : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium text-gray-700 truncate">{{ $item->client->name ?? 'Sem Cliente' }}</span>
                            <span class="text-gray-600">{{ $item->total }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-gradient-to-r from-green-500 to-green-600 h-2.5 rounded-full transition-all" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm text-center py-4">Nenhum cliente encontrado</p>
                @endforelse
            </div>
        </div>
        
        <!-- Eventos por Tipo -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-tags text-orange-600 mr-2"></i>Eventos por Tipo
            </h3>
            <div class="space-y-3">
                @forelse($this->eventsByType as $item)
                    @php
                        $percentage = $this->totalEvents > 0 ? ($item->total / $this->totalEvents) * 100 : 0;
                        $colors = [
                            'bg-orange-500' => 'bg-orange-500',
                            'bg-purple-500' => 'bg-purple-500',
                            'bg-pink-500' => 'bg-pink-500',
                            'bg-indigo-500' => 'bg-indigo-500',
                            'bg-teal-500' => 'bg-teal-500',
                        ];
                        $color = array_values($colors)[$loop->index % count($colors)];
                    @endphp
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="flex items-center flex-1">
                            <div class="w-8 h-8 {{ $color }} rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-tag text-white text-xs"></i>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">{{ ucfirst($item->type) }}</div>
                                <div class="text-xs text-gray-500">{{ number_format($percentage, 1) }}%</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-gray-900">{{ $item->total }}</div>
                            <div class="text-xs text-gray-500">eventos</div>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm text-center py-4">Nenhum tipo encontrado</p>
                @endforelse
            </div>
        </div>
        
        <!-- Eventos por Status -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-circle-notch text-purple-600 mr-2"></i>Eventos por Status
            </h3>
            <div class="space-y-3">
                @forelse($this->eventsByStatus as $item)
                    @php
                        $percentage = $this->totalEvents > 0 ? ($item->total / $this->totalEvents) * 100 : 0;
                        $statusColors = [
                            'pending' => 'bg-yellow-500',
                            'confirmed' => 'bg-blue-500',
                            'in_progress' => 'bg-orange-500',
                            'completed' => 'bg-green-500',
                            'cancelled' => 'bg-red-500',
                        ];
                        $color = $statusColors[$item->status] ?? 'bg-gray-500';
                    @endphp
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="flex items-center flex-1">
                            <div class="w-8 h-8 {{ $color }} rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-circle text-white text-xs"></i>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">{{ ucfirst($item->status) }}</div>
                                <div class="text-xs text-gray-500">{{ number_format($percentage, 1) }}%</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-gray-900">{{ $item->total }}</div>
                            <div class="text-xs text-gray-500">eventos</div>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm text-center py-4">Nenhum status encontrado</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dados do gráfico
    const monthlyData = @json($this->eventsByMonth);
    
    const labels = monthlyData.map(item => {
        const [year, month] = item.month.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleDateString('pt-BR', { month: 'short', year: '2-digit' });
    });
    
    const data = monthlyData.map(item => item.total);
    
    // Criar gráfico
    const ctx = document.getElementById('monthlyChart');
    const monthlyChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Eventos',
                data: data,
                borderColor: 'rgb(147, 51, 234)',
                backgroundColor: 'rgba(147, 51, 234, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: 'rgb(147, 51, 234)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgb(147, 51, 234)',
                    borderWidth: 1,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' eventos';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        color: '#6b7280'
                    },
                    grid: {
                        color: '#f3f4f6'
                    }
                },
                x: {
                    ticks: {
                        color: '#6b7280'
                    },
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
    
    // Atualizar gráfico quando filtros mudarem
    Livewire.on('chart-updated', () => {
        setTimeout(() => {
            location.reload();
        }, 100);
    });
});
</script>
@endpush
