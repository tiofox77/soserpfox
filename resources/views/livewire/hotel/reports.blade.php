<div>
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-chart-bar text-indigo-500"></i>
                Relatórios
            </h1>
            <p class="text-gray-500 dark:text-gray-400">Análise de ocupação, receita e hóspedes</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            {{-- Período rápido --}}
            <select wire:model.live="period" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                <option value="day">Hoje</option>
                <option value="week">Esta Semana</option>
                <option value="month">Este Mês</option>
                <option value="year">Este Ano</option>
            </select>

            {{-- Datas --}}
            <input type="date" wire:model.live="dateFrom" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200">
            <span class="text-gray-500">até</span>
            <input type="date" wire:model.live="dateTo" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200">

            {{-- Tipo de quarto --}}
            <select wire:model.live="roomTypeId" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                <option value="">Todos os Quartos</option>
                @foreach($roomTypes as $rt)
                    <option value="{{ $rt->id }}">{{ $rt->name }}</option>
                @endforeach
            </select>

            {{-- Exportar --}}
            <div class="flex gap-2">
                <button wire:click="exportPdf" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">
                    <i class="fas fa-file-pdf mr-1"></i> PDF
                </button>
                <button wire:click="exportExcel" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition">
                    <i class="fas fa-file-excel mr-1"></i> Excel
                </button>
            </div>
        </div>
    </div>

    {{-- KPIs Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Ocupação</p>
                    <p class="text-2xl font-bold text-indigo-600">{{ $kpis['occupancy_rate'] ?? 0 }}%</p>
                </div>
                <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-percentage text-indigo-600 dark:text-indigo-400"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Receita Total</p>
                    <p class="text-2xl font-bold text-green-600">{{ number_format($kpis['total_revenue'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-dollar-sign text-green-600 dark:text-green-400"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-1">Kz</p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">ADR</p>
                    <p class="text-2xl font-bold text-blue-600">{{ number_format($kpis['adr'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-bed text-blue-600 dark:text-blue-400"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-1">Kz/noite</p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">RevPAR</p>
                    <p class="text-2xl font-bold text-purple-600">{{ number_format($kpis['revpar'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-chart-line text-purple-600 dark:text-purple-400"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-1">Kz/quarto disponível</p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Reservas</p>
                    <p class="text-2xl font-bold text-amber-600">{{ $kpis['total_reservations'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-calendar-check text-amber-600 dark:text-amber-400"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Noites Vendidas</p>
                    <p class="text-2xl font-bold text-cyan-600">{{ $kpis['room_nights'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-cyan-100 dark:bg-cyan-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-moon text-cyan-600 dark:text-cyan-400"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex -mb-px">
                <button wire:click="setTab('occupancy')" 
                        class="px-6 py-4 text-sm font-medium border-b-2 transition
                               {{ $activeTab === 'occupancy' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <i class="fas fa-chart-area mr-2"></i>Ocupação
                </button>
                <button wire:click="setTab('revenue')" 
                        class="px-6 py-4 text-sm font-medium border-b-2 transition
                               {{ $activeTab === 'revenue' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <i class="fas fa-coins mr-2"></i>Receita
                </button>
                <button wire:click="setTab('guests')" 
                        class="px-6 py-4 text-sm font-medium border-b-2 transition
                               {{ $activeTab === 'guests' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <i class="fas fa-users mr-2"></i>Hóspedes
                </button>
            </nav>
        </div>

        <div class="p-6">
            {{-- Tab: Ocupação --}}
            @if($activeTab === 'occupancy')
            <div>
                <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white">Taxa de Ocupação por Dia</h3>
                
                {{-- Gráfico simples de barras --}}
                <div class="overflow-x-auto">
                    <div class="flex items-end gap-1 min-w-max h-64 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                        @foreach($occupancyData as $day)
                        <div class="flex flex-col items-center group" style="width: {{ 100 / max(count($occupancyData), 1) }}%">
                            <div class="relative w-full flex flex-col items-center">
                                <span class="text-xs text-gray-500 mb-1 opacity-0 group-hover:opacity-100 transition">{{ $day['rate'] }}%</span>
                                <div class="w-full max-w-8 rounded-t transition-all group-hover:opacity-80"
                                     style="height: {{ max($day['rate'] * 2, 4) }}px; background: linear-gradient(to top, #6366f1, #8b5cf6);">
                                </div>
                            </div>
                            <span class="text-xs text-gray-400 mt-2 transform -rotate-45">{{ $day['date'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Tabela --}}
                <div class="mt-6 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-gray-600 dark:text-gray-300">Data</th>
                                <th class="px-4 py-3 text-center text-gray-600 dark:text-gray-300">Ocupados</th>
                                <th class="px-4 py-3 text-center text-gray-600 dark:text-gray-300">Disponíveis</th>
                                <th class="px-4 py-3 text-center text-gray-600 dark:text-gray-300">Taxa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($occupancyData as $day)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $day['date'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs">{{ $day['occupied'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">{{ $day['available'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-indigo-500 rounded-full" style="width: {{ $day['rate'] }}%"></div>
                                        </div>
                                        <span class="text-gray-600 dark:text-gray-400">{{ $day['rate'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Tab: Receita --}}
            @if($activeTab === 'revenue')
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Por Tipo de Quarto --}}
                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-4">
                        <i class="fas fa-bed text-indigo-500 mr-2"></i>Por Tipo de Quarto
                    </h4>
                    <div class="space-y-3">
                        @forelse($revenueData['by_room_type'] ?? [] as $item)
                        <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-800 dark:text-white">{{ $item['name'] }}</p>
                                <p class="text-sm text-gray-500">{{ $item['count'] }} reservas</p>
                            </div>
                            <p class="text-lg font-bold text-green-600">{{ number_format($item['total'], 0, ',', '.') }} Kz</p>
                        </div>
                        @empty
                        <p class="text-gray-500 text-center py-4">Sem dados</p>
                        @endforelse
                    </div>
                </div>

                {{-- Por Fonte --}}
                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-4">
                        <i class="fas fa-globe text-blue-500 mr-2"></i>Por Fonte de Reserva
                    </h4>
                    <div class="space-y-3">
                        @forelse($revenueData['by_source'] ?? [] as $item)
                        <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-800 dark:text-white">{{ $item['name'] }}</p>
                                <p class="text-sm text-gray-500">{{ $item['count'] }} reservas</p>
                            </div>
                            <p class="text-lg font-bold text-green-600">{{ number_format($item['total'], 0, ',', '.') }} Kz</p>
                        </div>
                        @empty
                        <p class="text-gray-500 text-center py-4">Sem dados</p>
                        @endforelse
                    </div>
                </div>

                {{-- Resumo de Pagamentos --}}
                <div class="lg:col-span-2 bg-gray-50 dark:bg-gray-900 rounded-xl p-4">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-4">
                        <i class="fas fa-credit-card text-green-500 mr-2"></i>Status de Pagamentos
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-green-100 dark:bg-green-900 rounded-lg p-4 text-center">
                            <p class="text-sm text-green-700 dark:text-green-300">Total Recebido</p>
                            <p class="text-2xl font-bold text-green-600">{{ number_format($revenueData['total_paid'] ?? 0, 0, ',', '.') }} Kz</p>
                        </div>
                        <div class="bg-amber-100 dark:bg-amber-900 rounded-lg p-4 text-center">
                            <p class="text-sm text-amber-700 dark:text-amber-300">Pendente</p>
                            <p class="text-2xl font-bold text-amber-600">{{ number_format($revenueData['total_pending'] ?? 0, 0, ',', '.') }} Kz</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Tab: Hóspedes --}}
            @if($activeTab === 'guests')
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Top Hóspedes --}}
                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-4">
                        <i class="fas fa-trophy text-amber-500 mr-2"></i>Top 10 Hóspedes
                    </h4>
                    <div class="space-y-2">
                        @forelse($guestData['top_guests'] ?? [] as $index => $guest)
                        <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg">
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold
                                             {{ $index < 3 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $index + 1 }}
                                </span>
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-white">{{ $guest->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $guest->reservations_count }} estadias</p>
                                </div>
                            </div>
                            <p class="text-lg font-bold text-green-600">{{ number_format($guest->reservations_sum_total ?? 0, 0, ',', '.') }} Kz</p>
                        </div>
                        @empty
                        <p class="text-gray-500 text-center py-4">Sem dados</p>
                        @endforelse
                    </div>
                </div>

                {{-- Por Nacionalidade --}}
                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4">
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-4">
                        <i class="fas fa-flag text-blue-500 mr-2"></i>Por Nacionalidade
                    </h4>
                    <div class="space-y-2">
                        @forelse($guestData['by_nationality'] ?? [] as $item)
                        <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg">
                            <p class="font-medium text-gray-800 dark:text-white">{{ $item['nationality'] }}</p>
                            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">{{ $item['count'] }}</span>
                        </div>
                        @empty
                        <p class="text-gray-500 text-center py-4">Sem dados</p>
                        @endforelse
                    </div>
                </div>

                {{-- Estatísticas --}}
                <div class="lg:col-span-2 grid grid-cols-3 gap-4">
                    <div class="bg-indigo-100 dark:bg-indigo-900 rounded-lg p-4 text-center">
                        <p class="text-sm text-indigo-700 dark:text-indigo-300">Estadia Média</p>
                        <p class="text-2xl font-bold text-indigo-600">{{ $guestData['avg_stay'] ?? 0 }} noites</p>
                    </div>
                    <div class="bg-green-100 dark:bg-green-900 rounded-lg p-4 text-center">
                        <p class="text-sm text-green-700 dark:text-green-300">Hóspedes Recorrentes</p>
                        <p class="text-2xl font-bold text-green-600">{{ $guestData['repeat_guests'] ?? 0 }}</p>
                    </div>
                    <div class="bg-blue-100 dark:bg-blue-900 rounded-lg p-4 text-center">
                        <p class="text-sm text-blue-700 dark:text-blue-300">Novos Hóspedes</p>
                        <p class="text-2xl font-bold text-blue-600">{{ $guestData['new_guests'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
