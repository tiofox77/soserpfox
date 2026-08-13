<div>
    <div class="mb-6 bg-gradient-to-r from-amber-600 to-yellow-600 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-medal text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">{{ __('Melhor Fornecedor') }}</h2><p class="text-amber-100 text-sm">{{ __('Score multi-critério (volume, frequência, fiabilidade, prazo)') }}</p></div>
        </div>
        <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>{{ __('Voltar') }}</a>
    </div>

    <x-report-filters color="amber" />

    @if($stats->count() >= 1)
        {{-- Podium dos 3 melhores --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            @foreach($stats->take(3) as $idx => $s)
                @php
                    $medals = ['from-yellow-400 to-amber-500', 'from-gray-300 to-gray-400', 'from-orange-400 to-orange-500'];
                    $titles = ['🥇 Campeão', '🥈 Vice', '🥉 Terceiro'];
                @endphp
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-{{ ['yellow', 'gray', 'orange'][$idx] }}-300">
                    <div class="bg-gradient-to-br {{ $medals[$idx] }} p-4 text-white text-center">
                        <p class="text-xs font-bold uppercase opacity-90">{{ $titles[$idx] }}</p>
                        <p class="text-4xl font-bold mt-2">{{ $s->total_score }}</p>
                        <p class="text-xs opacity-90">{{ __('Score (máx. 100)') }}</p>
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold text-gray-900 truncate">{{ $s->name }}</h3>
                        <p class="text-xs text-gray-500 mb-3">NIF: {{ $s->nif ?? '—' }}</p>
                        <div class="space-y-1 text-xs">
                            <div class="flex justify-between"><span class="text-gray-500">{{ __('Volume comprado:') }}</span><strong>{{ number_format($s->total_value, 0, ',', '.') }} Kz</strong></div>
                            <div class="flex justify-between"><span class="text-gray-500">{{ __('Faturas:') }}</span><strong>{{ $s->invoices_count }}</strong></div>
                            <div class="flex justify-between"><span class="text-gray-500">{{ __('Fiabilidade:') }}</span><strong class="text-emerald-700">{{ $s->reliability_pct }}%</strong></div>
                            <div class="flex justify-between"><span class="text-gray-500">{{ __('Prazo médio:') }}</span><strong>{{ number_format($s->avg_payment_term ?? 0, 0) }} dias</strong></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Critérios --}}
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg mb-4 text-xs text-blue-900">
        <p class="font-bold mb-1"><i class="fas fa-info-circle mr-1"></i>{{ __('Critérios do Score (máximo 100 pontos):') }}</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
            <div><strong>{{ __('Volume (30 pts):') }}</strong> {{ __('valor total comprado') }}</div>
            <div><strong>{{ __('Frequência (20 pts):') }}</strong> {{ __('nº de faturas no período') }}</div>
            <div><strong>{{ __('Fiabilidade (25 pts):') }}</strong> {{ __('ausência de faturas vencidas') }}</div>
            <div><strong>{{ __('Prazo (25 pts):') }}</strong> {{ __('prazo médio de pagamento concedido') }}</div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">{{ __('Fornecedor') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Faturas') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Volume') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Ticket Médio') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Fiabilidade') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Prazo Médio') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Score') }}</th>
                        <th class="px-3 py-2">{{ __('Distribuição') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($stats as $idx => $s)
                        <tr class="hover:bg-amber-50">
                            <td class="px-3 py-2 font-semibold text-gray-500">{{ $idx + 1 }}</td>
                            <td class="px-3 py-2">
                                <p class="font-semibold">{{ $s->name }}</p>
                                <p class="text-xs text-gray-500">NIF: {{ $s->nif ?? '—' }}</p>
                            </td>
                            <td class="px-3 py-2 text-right">{{ $s->invoices_count }}</td>
                            <td class="px-3 py-2 text-right font-bold">{{ number_format($s->total_value, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($s->avg_ticket, 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">
                                <span class="font-bold {{ $s->reliability_pct >= 80 ? 'text-emerald-700' : ($s->reliability_pct >= 50 ? 'text-amber-600' : 'text-red-700') }}">{{ $s->reliability_pct }}%</span>
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($s->avg_payment_term ?? 0, 0) }} dias</td>
                            <td class="px-3 py-2 text-right">
                                <span class="inline-flex px-2 py-1 rounded-lg font-bold {{ $s->total_score >= 70 ? 'bg-emerald-100 text-emerald-800' : ($s->total_score >= 40 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">{{ $s->total_score }}</span>
                            </td>
                            <td class="px-3 py-2 w-1/5">
                                <div class="flex h-3 rounded-full overflow-hidden bg-gray-100" title="{{ __('Volume / Frequência / Fiabilidade / Prazo') }}">
                                    <div class="bg-blue-500" style="width: {{ $s->volume_score }}%"></div>
                                    <div class="bg-purple-500" style="width: {{ $s->frequency_score }}%"></div>
                                    <div class="bg-emerald-500" style="width: {{ $s->reliability_score }}%"></div>
                                    <div class="bg-amber-500" style="width: {{ $s->payment_score }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-8 text-center text-gray-400 italic">{{ __('Sem dados no período') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
