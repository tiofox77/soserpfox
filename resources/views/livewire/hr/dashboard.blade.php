<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-chart-line text-3xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold">Dashboard RH</h1>
                    <p class="text-blue-100 text-sm mt-1">
                        <i class="fas fa-calendar-day mr-1"></i>
                        {{ \Carbon\Carbon::now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
                    </p>
                </div>
            </div>
            <div class="hidden md:flex items-center space-x-3">
                <a href="{{ route('hr.employees.index') }}" 
                   class="bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white px-4 py-2 rounded-xl font-semibold transition-all">
                    <i class="fas fa-users mr-2"></i>Funcionários
                </a>
                <a href="{{ route('hr.attendance.index') }}" 
                   class="bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white px-4 py-2 rounded-xl font-semibold transition-all">
                    <i class="fas fa-user-clock mr-2"></i>Presenças
                </a>
            </div>
        </div>
    </div>

    {{-- Alertas --}}
    @if(count($alerts) > 0)
        <div class="grid grid-cols-1 gap-4">
            @foreach($alerts as $alert)
                <div class="bg-{{ $alert['type'] === 'warning' ? 'yellow' : 'red' }}-50 border-l-4 border-{{ $alert['type'] === 'warning' ? 'yellow' : 'red' }}-500 p-4 rounded-lg shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas {{ $alert['icon'] }} text-{{ $alert['type'] === 'warning' ? 'yellow' : 'red' }}-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-bold text-{{ $alert['type'] === 'warning' ? 'yellow' : 'red' }}-900">
                                    {{ $alert['title'] }}
                                </h3>
                                <p class="text-sm text-{{ $alert['type'] === 'warning' ? 'yellow' : 'red' }}-700 mt-1">
                                    {{ $alert['message'] }}
                                </p>
                            </div>
                        </div>
                        <a href="{{ $alert['action'] }}" 
                           class="px-4 py-2 bg-{{ $alert['type'] === 'warning' ? 'yellow' : 'red' }}-600 hover:bg-{{ $alert['type'] === 'warning' ? 'yellow' : 'red' }}-700 text-white rounded-lg font-semibold text-sm transition-all">
                            {{ $alert['action_text'] }}
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 stagger-animation">
        {{-- Total Funcionários --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 card-hover card-3d overflow-hidden relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-blue-500/10 to-indigo-500/10 rounded-full -mr-16 -mt-16"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                        <i class="fas fa-users text-white text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-blue-600 font-semibold mb-2">Total de Funcionários</p>
                <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['total_employees'] }}</p>
                <p class="text-xs text-gray-500">
                    <span class="text-green-600 font-semibold">{{ $stats['active_employees'] }}</span> ativos
                </p>
            </div>
        </div>

        {{-- Presenças Hoje --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 card-hover card-zoom overflow-hidden relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-green-500/10 to-emerald-500/10 rounded-full -mr-16 -mt-16"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                        <i class="fas fa-user-check text-white text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-green-600 font-semibold mb-2">Presenças Hoje</p>
                <p class="text-4xl font-bold text-gray-900 mb-1">{{ $attendanceToday['present'] }}</p>
                <p class="text-xs text-gray-500">
                    <span class="text-yellow-600 font-semibold">{{ $attendanceToday['late'] }}</span> atrasados
                </p>
            </div>
        </div>

        {{-- Férias Ativas --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 card-hover card-glow overflow-hidden relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-purple-500/10 to-pink-500/10 rounded-full -mr-16 -mt-16"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float">
                        <i class="fas fa-umbrella-beach text-white text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-purple-600 font-semibold mb-2">De Férias Hoje</p>
                <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['on_vacation'] }}</p>
                <p class="text-xs text-gray-500">
                    <span class="text-orange-600 font-semibold">{{ $pendingVacations }}</span> pendentes
                </p>
            </div>
        </div>

        {{-- Ausências Hoje --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-red-100 card-hover card-3d overflow-hidden relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-red-500/10 to-pink-500/10 rounded-full -mr-16 -mt-16"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-red-500/50 icon-float">
                        <i class="fas fa-user-times text-white text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-red-600 font-semibold mb-2">Ausências Hoje</p>
                <p class="text-4xl font-bold text-gray-900 mb-1">{{ $attendanceToday['absent'] }}</p>
                <p class="text-xs text-gray-500">
                    Total de {{ $attendanceToday['total'] }} registros
                </p>
            </div>
        </div>
    </div>

    {{-- Conteúdo Principal --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Coluna Esquerda (2/3) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Gráfico de Presenças da Semana --}}
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-chart-bar mr-2 text-blue-600"></i>
                        Presenças da Última Semana
                    </h3>
                </div>
                <div class="p-6">
                    <div class="flex items-end justify-between space-x-2 h-48">
                        @foreach($weekAttendance as $day)
                            <div class="flex-1 flex flex-col items-center">
                                <div class="w-full bg-gradient-to-t from-blue-500 to-indigo-600 rounded-t-lg transition-all hover:from-blue-600 hover:to-indigo-700 relative group"
                                     style="height: {{ $day['present'] > 0 ? ($day['present'] / max(array_column($weekAttendance, 'present'))) * 100 : 5 }}%">
                                    <div class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                        {{ $day['present'] }} presentes
                                    </div>
                                </div>
                                <div class="mt-2 text-center">
                                    <p class="text-xs font-semibold text-gray-900">{{ $day['date'] }}</p>
                                    <p class="text-xs text-gray-500">{{ substr($day['day'], 0, 3) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Funcionários por Departamento --}}
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-sitemap mr-2 text-green-600"></i>
                        Funcionários por Departamento
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        @forelse($employeesByDepartment as $dept)
                            @php
                                $percentage = $stats['active_employees'] > 0 ? ($dept->total / $stats['active_employees']) * 100 : 0;
                                $colors = ['blue', 'green', 'purple', 'orange', 'pink', 'indigo', 'cyan'];
                                $color = $colors[$loop->index % count($colors)];
                            @endphp
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-gray-900">
                                        {{ $dept->department->name ?? 'Sem Departamento' }}
                                    </span>
                                    <span class="text-sm font-bold text-{{ $color }}-600">
                                        {{ $dept->total }} ({{ number_format($percentage, 1) }}%)
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                    <div class="bg-gradient-to-r from-{{ $color }}-500 to-{{ $color }}-600 h-3 rounded-full transition-all duration-500"
                                         style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-center text-gray-500 py-8">
                                <i class="fas fa-inbox text-4xl mb-2"></i><br>
                                Nenhum departamento encontrado
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Últimas Admissões --}}
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-user-plus mr-2 text-purple-600"></i>
                        Últimas Admissões
                    </h3>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($recentHires as $employee)
                        <div class="px-6 py-4 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white font-bold shadow-lg mr-3">
                                        {{ strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $employee->full_name }}</p>
                                        <p class="text-xs text-gray-500">{{ $employee->position->name ?? 'Sem cargo' }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-purple-600">
                                        {{ \Carbon\Carbon::parse($employee->hire_date)->format('d/m/Y') }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($employee->hire_date)->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-gray-500 py-8">
                            <i class="fas fa-inbox text-4xl mb-2"></i><br>
                            Nenhuma admissão recente
                        </p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Coluna Direita (1/3) --}}
        <div class="space-y-6">
            {{-- Aniversariantes do Mês --}}
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-pink-50 to-rose-50 border-b border-pink-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-birthday-cake mr-2 text-pink-600"></i>
                        Aniversariantes
                    </h3>
                    <p class="text-xs text-gray-600 mt-1">{{ \Carbon\Carbon::now()->locale('pt_BR')->monthName }}</p>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($birthdays as $employee)
                        <div class="px-6 py-4 hover:bg-pink-50 transition-colors">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-pink-500 to-rose-600 flex items-center justify-center text-white font-bold shadow-lg mr-3">
                                    {{ strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1)) }}
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900 text-sm">{{ $employee->full_name }}</p>
                                    <div class="flex items-center text-xs text-gray-500 mt-1">
                                        <i class="fas fa-calendar-day mr-1 text-pink-500"></i>
                                        {{ \Carbon\Carbon::parse($employee->birth_date)->format('d/m') }}
                                        @if(\Carbon\Carbon::parse($employee->birth_date)->isToday())
                                            <span class="ml-2 px-2 py-0.5 bg-pink-500 text-white rounded-full text-xs font-semibold">HOJE! 🎉</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-gray-500 py-8 px-4">
                            <i class="fas fa-cake-candles text-4xl mb-2 text-gray-300"></i><br>
                            <span class="text-sm">Nenhum aniversário este mês</span>
                        </p>
                    @endforelse
                </div>
            </div>

            {{-- Próximas Férias --}}
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-cyan-50 border-b border-blue-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-plane-departure mr-2 text-blue-600"></i>
                        Próximas Férias
                    </h3>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($upcomingVacations as $vacation)
                        <div class="px-6 py-4 hover:bg-blue-50 transition-colors">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center text-white font-bold shadow-lg mr-3">
                                    {{ strtoupper(substr($vacation->employee->first_name, 0, 1) . substr($vacation->employee->last_name, 0, 1)) }}
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900 text-sm">{{ $vacation->employee->full_name }}</p>
                                    <div class="flex items-center text-xs text-gray-500 mt-1">
                                        <i class="fas fa-calendar mr-1 text-blue-500"></i>
                                        {{ \Carbon\Carbon::parse($vacation->start_date)->format('d/m') }} até {{ \Carbon\Carbon::parse($vacation->end_date)->format('d/m') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-gray-500 py-8 px-4">
                            <i class="fas fa-inbox text-4xl mb-2 text-gray-300"></i><br>
                            <span class="text-sm">Nenhuma féria programada</span>
                        </p>
                    @endforelse
                </div>
            </div>

            {{-- Ações Rápidas --}}
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-bolt mr-2 text-yellow-600"></i>
                        Ações Rápidas
                    </h3>
                </div>
                <div class="p-4 space-y-2">
                    <a href="{{ route('hr.employees.index') }}" 
                       class="block px-4 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg text-center">
                        <i class="fas fa-user-plus mr-2"></i>Novo Funcionário
                    </a>
                    <a href="{{ route('hr.attendance.index') }}" 
                       class="block px-4 py-3 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg text-center">
                        <i class="fas fa-clock mr-2"></i>Registrar Presença
                    </a>
                    <a href="{{ route('hr.vacations.index') }}" 
                       class="block px-4 py-3 bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg text-center">
                        <i class="fas fa-umbrella-beach mr-2"></i>Aprovar Férias
                    </a>
                    <a href="{{ route('hr.departments.index') }}" 
                       class="block px-4 py-3 bg-gradient-to-r from-orange-500 to-red-600 hover:from-orange-600 hover:to-red-700 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg text-center">
                        <i class="fas fa-sitemap mr-2"></i>Departamentos
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .card-hover {
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .card-hover:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }
    
    .card-3d:hover {
        transform: translateY(-10px) rotateX(5deg) scale(1.03);
    }
    
    .card-zoom:hover {
        transform: scale(1.05);
    }
    
    .card-glow:hover {
        box-shadow: 0 0 30px rgba(168, 85, 247, 0.4);
    }
    
    .card-hover:hover .icon-float {
        transform: translateY(-5px) scale(1.1);
    }
    
    .stagger-animation > * {
        animation: fadeInUp 0.6s ease forwards;
        opacity: 0;
    }
    .stagger-animation > *:nth-child(1) { animation-delay: 0.1s; }
    .stagger-animation > *:nth-child(2) { animation-delay: 0.2s; }
    .stagger-animation > *:nth-child(3) { animation-delay: 0.3s; }
    .stagger-animation > *:nth-child(4) { animation-delay: 0.4s; }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
@endpush
