<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-clock text-pink-500 mr-2"></i>Relatório de Tempos
            </h1>
            <p class="text-gray-500">Análise de tempo de atendimento e eficiência</p>
        </div>
        <a href="{{ route('salon.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-gray-700 font-semibold transition">
            <i class="fas fa-arrow-left mr-2"></i>Voltar
        </a>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-2xl shadow-lg p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Data Início</label>
                <input type="date" wire:model.live="dateFrom" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Data Fim</label>
                <input type="date" wire:model.live="dateTo" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Profissional</label>
                <select wire:model.live="professionalId" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500">
                    <option value="">Todos</option>
                    @foreach($professionals as $professional)
                        <option value="{{ $professional->id }}">{{ $professional->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button wire:click="$refresh" class="w-full px-4 py-2 bg-pink-500 hover:bg-pink-600 text-white rounded-lg font-semibold transition">
                    <i class="fas fa-filter mr-2"></i>Filtrar
                </button>
            </div>
        </div>
    </div>

    {{-- Cards Estatísticas --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
        <div class="bg-white rounded-xl shadow-lg p-4 text-center">
            <div class="text-3xl font-bold text-pink-600">{{ $stats['total_appointments'] }}</div>
            <div class="text-xs text-gray-500 font-semibold">Atendimentos</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 text-center">
            <div class="text-3xl font-bold text-blue-600">{{ $this->formatMinutes($stats['total_time']) }}</div>
            <div class="text-xs text-gray-500 font-semibold">Tempo Total</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 text-center">
            <div class="text-3xl font-bold text-purple-600">{{ $stats['avg_time'] }}min</div>
            <div class="text-xs text-gray-500 font-semibold">Média/Atend.</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 text-center">
            <div class="text-3xl font-bold text-orange-600">{{ $stats['avg_wait'] }}min</div>
            <div class="text-xs text-gray-500 font-semibold">Espera Média</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 text-center">
            <div class="text-3xl font-bold text-green-600">{{ $stats['on_time'] }}</div>
            <div class="text-xs text-gray-500 font-semibold">No Tempo</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 text-center">
            <div class="text-3xl font-bold text-red-600">{{ $stats['delayed'] }}</div>
            <div class="text-xs text-gray-500 font-semibold">Atrasados</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 text-center">
            <div class="text-3xl font-bold text-teal-600">{{ $stats['faster'] }}</div>
            <div class="text-xs text-gray-500 font-semibold">Mais Rápidos</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 text-center">
            <div class="text-3xl font-bold {{ $stats['efficiency'] >= 100 ? 'text-green-600' : 'text-yellow-600' }}">{{ $stats['efficiency'] }}%</div>
            <div class="text-xs text-gray-500 font-semibold">Eficiência</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Por Profissional --}}
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-pink-500 to-purple-500 px-4 py-3">
                <h3 class="text-white font-bold"><i class="fas fa-user-tie mr-2"></i>Por Profissional</h3>
            </div>
            <div class="p-4">
                @if($byProfessional->isEmpty())
                    <p class="text-center text-gray-500 py-4">Sem dados no período</p>
                @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">Profissional</th>
                            <th class="text-center">Atend.</th>
                            <th class="text-center">Tempo Médio</th>
                            <th class="text-center">Eficiência</th>
                            <th class="text-right">Receita</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($byProfessional as $item)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 font-semibold">{{ $item->professional->name ?? 'N/A' }}</td>
                            <td class="text-center">{{ $item->total }}</td>
                            <td class="text-center">{{ round($item->avg_duration) }}min</td>
                            <td class="text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-bold {{ $item->efficiency >= 100 ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                    {{ $item->efficiency }}%
                                </span>
                            </td>
                            <td class="text-right font-bold text-green-600">{{ number_format($item->revenue, 0, ',', '.') }} Kz</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

        {{-- Por Serviço --}}
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-500 to-teal-500 px-4 py-3">
                <h3 class="text-white font-bold"><i class="fas fa-spa mr-2"></i>Por Serviço</h3>
            </div>
            <div class="p-4">
                @if($byService->isEmpty())
                    <p class="text-center text-gray-500 py-4">Sem dados no período</p>
                @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">Serviço</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-right">Receita</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($byService as $item)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 font-semibold">{{ $item->service_name }}</td>
                            <td class="text-center">{{ $item->total }}</td>
                            <td class="text-right font-bold text-green-600">{{ number_format($item->revenue, 0, ',', '.') }} Kz</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>

    {{-- Lista Detalhada --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="bg-gradient-to-r from-gray-700 to-gray-800 px-4 py-3 flex items-center justify-between">
            <h3 class="text-white font-bold"><i class="fas fa-list mr-2"></i>Atendimentos Detalhados</h3>
            <span class="text-white/70 text-sm">{{ $appointments->count() }} registros</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-4 py-3">Data</th>
                        <th class="text-left">Cliente</th>
                        <th class="text-left">Profissional</th>
                        <th class="text-center">Previsto</th>
                        <th class="text-center">Real</th>
                        <th class="text-center">Diferença</th>
                        <th class="text-center">Espera</th>
                        <th class="text-right px-4">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($appointments as $appointment)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2">
                            <div class="font-semibold">{{ formatDate($appointment->date) }}</div>
                            <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($appointment->start_time)->format('H:i') }}</div>
                        </td>
                        <td>{{ $appointment->client->name ?? 'N/A' }}</td>
                        <td>{{ $appointment->professional->name ?? 'N/A' }}</td>
                        <td class="text-center text-gray-600">{{ $appointment->total_duration ?? '-' }}min</td>
                        <td class="text-center font-bold">{{ $appointment->actual_duration_formatted }}</td>
                        <td class="text-center">
                            @php $diff = $appointment->time_difference; @endphp
                            @if($diff !== null)
                                @php $diffRounded = round($diff); @endphp
                                @if($diffRounded > 5)
                                    <span class="text-red-600 font-bold">+{{ $diffRounded }}min</span>
                                @elseif($diffRounded < -5)
                                    <span class="text-green-600 font-bold">{{ $diffRounded }}min</span>
                                @else
                                    <span class="text-gray-600">{{ $diffRounded }}min</span>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-center">
                            @if($appointment->wait_time !== null)
                                <span class="{{ $appointment->wait_time > 10 ? 'text-orange-600' : 'text-gray-600' }}">
                                    {{ $appointment->wait_time }}min
                                </span>
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 text-right font-bold text-green-600">{{ number_format($appointment->total, 0, ',', '.') }} Kz</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-8 text-gray-500">
                            <i class="fas fa-clock text-4xl mb-2 text-gray-300"></i>
                            <p>Nenhum atendimento encontrado no período</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
