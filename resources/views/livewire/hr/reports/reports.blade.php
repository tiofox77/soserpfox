<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-violet-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-chart-pie text-3xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold">Relatórios RH</h1>
                    <p class="text-purple-100 text-sm mt-1">Análises e mapas do módulo de Recursos Humanos</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Tipo de Relatório</label>
                <select wire:model.live="reportType" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 text-sm">
                    <option value="salary_map">Mapa de Salários</option>
                    <option value="department_costs">Custos por Departamento</option>
                    <option value="attendance_summary">Resumo de Presenças</option>
                    <option value="vacation_balance">Saldo de Férias</option>
                    <option value="headcount">Evolução Quadro de Pessoal</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Ano</label>
                <select wire:model.live="year" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 text-sm">
                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>
            @if($reportType !== 'headcount')
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Mês</label>
                <select wire:model.live="month" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 text-sm">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}">{{ \Carbon\Carbon::create(null, $m)->locale('pt_BR')->monthName }}</option>
                    @endfor
                </select>
            </div>
            @endif
            @if(!in_array($reportType, ['headcount', 'department_costs']))
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Departamento</label>
                <select wire:model.live="departmentId" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 text-sm">
                    <option value="">Todos</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>
    </div>

    {{-- Relatório --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden" wire:loading.class="opacity-50">
        @if($reportType === 'salary_map')
            @include('livewire.hr.reports.partials.salary-map', ['data' => $reportData])
        @elseif($reportType === 'department_costs')
            @include('livewire.hr.reports.partials.department-costs', ['data' => $reportData])
        @elseif($reportType === 'attendance_summary')
            @include('livewire.hr.reports.partials.attendance-summary', ['data' => $reportData])
        @elseif($reportType === 'vacation_balance')
            @include('livewire.hr.reports.partials.vacation-balance', ['data' => $reportData])
        @elseif($reportType === 'headcount')
            @include('livewire.hr.reports.partials.headcount', ['data' => $reportData])
        @endif
    </div>
</div>
