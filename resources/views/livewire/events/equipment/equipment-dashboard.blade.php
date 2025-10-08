<div class="p-4 sm:p-6">
    {{-- Submenu de Navegação --}}
    <div class="mb-6 bg-white rounded-xl shadow-md p-2 flex flex-wrap gap-2">
        <a href="{{ route('events.equipment.dashboard') }}" 
           class="flex items-center px-4 py-2 rounded-lg font-semibold transition {{ request()->routeIs('events.equipment.dashboard') ? 'bg-purple-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
            <i class="fas fa-chart-line mr-2"></i>
            <span class="hidden sm:inline">Dashboard</span>
        </a>
        <a href="{{ route('events.equipment.index') }}" 
           class="flex items-center px-4 py-2 rounded-lg font-semibold transition {{ request()->routeIs('events.equipment.index') ? 'bg-purple-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
            <i class="fas fa-boxes mr-2"></i>
            <span class="hidden sm:inline">Equipamentos</span>
        </a>
        <a href="{{ route('events.equipment.sets') }}" 
           class="flex items-center px-4 py-2 rounded-lg font-semibold transition {{ request()->routeIs('events.equipment.sets') ? 'bg-purple-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
            <i class="fas fa-layer-group mr-2"></i>
            <span class="hidden sm:inline">SETS</span>
        </a>
        <a href="{{ route('events.equipment.categories') }}" 
           class="flex items-center px-4 py-2 rounded-lg font-semibold transition {{ request()->routeIs('events.equipment.categories') ? 'bg-purple-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
            <i class="fas fa-tags mr-2"></i>
            <span class="hidden sm:inline">Categorias</span>
        </a>
        <a href="{{ route('events.calendar') }}" 
           class="flex items-center px-4 py-2 rounded-lg font-semibold transition text-gray-700 hover:bg-gray-100">
            <i class="fas fa-calendar-alt mr-2"></i>
            <span class="hidden sm:inline">Calendário</span>
        </a>
    </div>

    {{-- Header Responsivo --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-chart-line text-purple-600 mr-3"></i>
                Dashboard de Equipamentos
            </h2>
            <p class="text-sm sm:text-base text-gray-600 mt-1">Analytics e estatísticas em tempo real</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach(['7days' => '7 dias', '30days' => '30 dias', '90days' => '90 dias', '1year' => '1 ano'] as $key => $label)
            <button wire:click="$set('period', '{{ $key }}')" 
                    class="px-3 py-2 rounded-lg font-semibold transition {{ $period === $key ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                {{ $label }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- Alertas Críticos Mobile-First --}}
    @if($overdueEquipment->count() > 0)
    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle text-red-600 text-2xl mr-3 mt-1"></i>
            <div class="flex-1">
                <h3 class="font-bold text-red-800 mb-2">⏰ {{ $overdueEquipment->count() }} Equipamento(s) Atrasado(s)</h3>
                <div class="space-y-1">
                    @foreach($overdueEquipment->take(3) as $eq)
                    <p class="text-sm text-red-700">
                        <strong>{{ $eq->name }}</strong> - {{ $eq->days_overdue }} dias ({{ $eq->borrowedToClient->name ?? 'N/A' }})
                    </p>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Stats Cards - Grid Responsivo --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3 sm:gap-6 mb-6">
        <div class="bg-gradient-to-br from-gray-500 to-gray-600 rounded-lg shadow-lg p-4 text-white">
            <p class="text-xs sm:text-sm opacity-90">Total</p>
            <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-4 text-white">
            <p class="text-xs sm:text-sm opacity-90">Disponível</p>
            <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $stats['disponivel'] }}</p>
        </div>
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-4 text-white">
            <p class="text-xs sm:text-sm opacity-90">Em Uso</p>
            <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $stats['em_uso'] }}</p>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-4 text-white">
            <p class="text-xs sm:text-sm opacity-90">Emprestado</p>
            <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $stats['emprestado'] }}</p>
        </div>
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-lg p-4 text-white">
            <p class="text-xs sm:text-sm opacity-90">Manutenção</p>
            <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $stats['manutencao'] }}</p>
        </div>
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-lg shadow-lg p-4 text-white">
            <p class="text-xs sm:text-sm opacity-90">Taxa Uso</p>
            <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $stats['utilization_rate'] }}%</p>
        </div>
    </div>

    {{-- Charts - Grid Responsivo --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-xl p-4 sm:p-6">
            <h3 class="font-bold text-lg mb-4 flex items-center">
                <i class="fas fa-chart-pie text-purple-600 mr-2"></i>
                Uso por Categoria
            </h3>
            <canvas id="categoryChart" class="w-full" style="max-height: 300px;"></canvas>
        </div>

        <div class="bg-white rounded-xl shadow-xl p-4 sm:p-6">
            <h3 class="font-bold text-lg mb-4 flex items-center">
                <i class="fas fa-trophy text-yellow-600 mr-2"></i>
                Top 10 Mais Usados
            </h3>
            <div class="space-y-2 max-h-80 overflow-y-auto">
                @foreach($topEquipment as $index => $eq)
                <div class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <div class="w-8 h-8 rounded-full bg-purple-600 text-white flex items-center justify-center font-bold mr-3">
                        {{ $index + 1 }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-900 truncate">{{ $eq->name }}</p>
                        <p class="text-xs text-gray-500">{{ $eq->total_uses }} usos</p>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-1 rounded-full text-xs font-bold text-white" style="background-color: {{ $eq->status_color }}">
                            {{ $eq->status_label }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Seções Inferiores - Stack em Mobile --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        {{-- Agenda de Manutenção --}}
        <div class="bg-white rounded-xl shadow-xl p-4 sm:p-6">
            <h3 class="font-bold text-lg mb-4 flex items-center">
                <i class="fas fa-wrench text-orange-600 mr-2"></i>
                Agenda de Manutenção
            </h3>
            <div class="space-y-3">
                @forelse($maintenanceSchedule as $eq)
                <div class="flex items-center p-3 border-l-4 {{ $eq->next_maintenance_date->isPast() ? 'border-red-500 bg-red-50' : 'border-yellow-500 bg-yellow-50' }} rounded">
                    <i class="fas fa-tools text-2xl {{ $eq->next_maintenance_date->isPast() ? 'text-red-600' : 'text-yellow-600' }} mr-3"></i>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold truncate">{{ $eq->name }}</p>
                        <p class="text-sm text-gray-600">{{ $eq->next_maintenance_date->format('d/m/Y') }}</p>
                    </div>
                    @if($eq->next_maintenance_date->isPast())
                    <span class="px-2 py-1 bg-red-600 text-white text-xs font-bold rounded-full animate-pulse">ATRASADO</span>
                    @else
                    <span class="text-sm text-gray-500">{{ $eq->next_maintenance_date->diffForHumans() }}</span>
                    @endif
                </div>
                @empty
                <p class="text-center text-gray-500 py-4">Nenhuma manutenção agendada</p>
                @endforelse
            </div>
        </div>

        {{-- Atividade Recente --}}
        <div class="bg-white rounded-xl shadow-xl p-4 sm:p-6">
            <h3 class="font-bold text-lg mb-4 flex items-center">
                <i class="fas fa-history text-blue-600 mr-2"></i>
                Atividade Recente
            </h3>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($recentActivity as $activity)
                <div class="flex items-start p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <div class="mr-3">
                        @php
                        $icons = [
                            'uso' => '▶️',
                            'reserva' => '📅',
                            'emprestimo' => '🤝',
                            'devolucao' => '↩️',
                            'manutencao' => '🔧',
                            'avaria' => '⚠️',
                            'reparacao' => '✅',
                            'transferencia' => '📦'
                        ];
                        @endphp
                        <span class="text-2xl">{{ $icons[$activity->action_type] ?? '📌' }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate">{{ $activity->equipment->name ?? 'N/A' }}</p>
                        <p class="text-xs text-gray-600">{{ $activity->action_type_label }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $activity->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gráfico de Uso por Categoria
const categoryData = @json($chartData['usage_by_category']);
const categoryLabels = Object.keys(categoryData);
const categoryValues = Object.values(categoryData);

const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: categoryLabels,
        datasets: [{
            data: categoryValues,
            backgroundColor: [
                '#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', 
                '#ef4444', '#ec4899', '#14b8a6', '#f97316', '#6366f1'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 10,
                    font: {
                        size: window.innerWidth < 640 ? 10 : 12
                    }
                }
            }
        }
    }
});
</script>
@endpush
