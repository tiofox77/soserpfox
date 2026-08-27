@php
    $t = $mapa['totais'];
    $nomeDoMes = $meses[$mes] ?? $mes;
@endphp

<div>
    {{-- Cabeçalho --}}
    <div class="mb-6 bg-gradient-to-r from-rose-600 to-orange-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-landmark text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Mapa de IRT</h2>
                    <p class="text-rose-100 text-sm">Imposto retido aos trabalhadores — {{ $nomeDoMes }} de {{ $ano }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('hr.irt-map.pdf', ['ano' => $ano, 'mes' => $mes, 'departamento' => $departamento]) }}"
                   target="_blank"
                   class="px-4 py-2.5 bg-white/20 hover:bg-white/30 rounded-xl text-sm font-semibold transition">
                    <i class="fas fa-print mr-1"></i>Imprimir
                </a>
                <a href="{{ route('hr.irt-map.csv', ['ano' => $ano, 'mes' => $mes, 'departamento' => $departamento]) }}"
                   class="px-4 py-2.5 bg-white text-rose-700 hover:bg-rose-50 rounded-xl text-sm font-bold transition shadow">
                    <i class="fas fa-file-csv mr-1"></i>Exportar CSV
                </a>
            </div>
        </div>
    </div>

    {{-- Período e filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow p-4">
        <div class="flex flex-wrap items-end gap-3">
            <button wire:click="mesAnterior" class="w-10 h-10 rounded-xl border border-gray-300 hover:bg-gray-50 text-gray-600" title="Mês anterior">
                <i class="fas fa-chevron-left"></i>
            </button>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Mês</label>
                <select wire:model.live="mes" class="px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-rose-500">
                    @foreach($meses as $n => $nome)
                        <option value="{{ $n }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Ano</label>
                <select wire:model.live="ano" class="px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-rose-500">
                    @foreach($anos as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                    @endforeach
                </select>
            </div>

            <button wire:click="mesSeguinte" class="w-10 h-10 rounded-xl border border-gray-300 hover:bg-gray-50 text-gray-600" title="Mês seguinte">
                <i class="fas fa-chevron-right"></i>
            </button>

            <div class="ml-auto">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Departamento</label>
                <select wire:model.live="departamento" class="px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-rose-500">
                    <option value="">Todos</option>
                    @foreach($departamentos as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Folhas que ficaram de fora. É a razão nº1 de o mapa não bater certo,
         por isso avisa-se em vez de somar em silêncio. --}}
    @if($mapa['ignoradas']->isNotEmpty())
        <div class="mb-6 bg-amber-50 border border-amber-200 rounded-2xl p-4 text-sm">
            <p class="font-bold text-amber-900 mb-1">
                <i class="fas fa-triangle-exclamation mr-1"></i>
                {{ $mapa['ignoradas']->count() }} folha(s) deste mês ficaram de fora
            </p>
            <p class="text-amber-800 mb-2">
                Só entram folhas <strong>aprovadas</strong> ou <strong>pagas</strong>: um rascunho ainda vai
                mudar e uma folha anulada não reteve imposto nenhum. Declarar qualquer uma seria declarar
                imposto que não foi retido.
            </p>
            <div class="flex flex-wrap gap-2">
                @foreach($mapa['ignoradas'] as $f)
                    <span class="bg-white border border-amber-300 text-amber-800 text-xs px-2 py-1 rounded-lg">
                        {{ $f->payroll_number }} · {{ $f->status }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Números de topo --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @php
            $cartoes = [
                ['IRT a entregar', number_format($t['irt'], 2, ',', '.') . ' Kz', 'fa-landmark', 'rose'],
                ['Matéria colectável', number_format($t['base'], 2, ',', '.') . ' Kz', 'fa-calculator', 'orange'],
                ['Trabalhadores', $t['trabalhadores'], 'fa-users', 'indigo'],
                ['Isentos de IRT', $t['isentos'], 'fa-shield-halved', 'emerald'],
            ];
        @endphp
        @foreach($cartoes as [$rotulo, $valor, $icone, $cor])
            <div class="bg-white rounded-2xl shadow p-4 border-l-4 border-{{ $cor }}-500">
                <div class="flex items-center justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-gray-500 uppercase">{{ $rotulo }}</p>
                        <p class="text-xl font-bold text-gray-900 mt-1 truncate">{{ $valor }}</p>
                    </div>
                    <i class="fas {{ $icone }} text-{{ $cor }}-400 text-xl ml-2"></i>
                </div>
            </div>
        @endforeach
    </div>

    {{-- O mapa --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-bold text-gray-900">
                <i class="fas fa-table-list text-rose-600 mr-2"></i>Retenções do período {{ $mapa['periodo'] }}
            </h3>
            @if($mapa['folhas']->isNotEmpty())
                <span class="text-xs text-gray-500">
                    {{ $mapa['folhas']->count() }} folha(s): {{ $mapa['folhas']->pluck('payroll_number')->implode(', ') }}
                </span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-xs font-bold text-gray-500 uppercase">
                        <th class="px-4 py-3 text-left">Nº</th>
                        <th class="px-4 py-3 text-left">Trabalhador</th>
                        <th class="px-4 py-3 text-left">NIF</th>
                        <th class="px-4 py-3 text-right">Remuneração bruta</th>
                        <th class="px-4 py-3 text-right">INSS 3%</th>
                        <th class="px-4 py-3 text-right">Matéria colectável</th>
                        <th class="px-4 py-3 text-right">Taxa</th>
                        <th class="px-4 py-3 text-right">IRT retido</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($mapa['linhas'] as $l)
                        <tr class="hover:bg-rose-50/50">
                            <td class="px-4 py-3 text-gray-500">{{ $l['numero'] ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">{{ $l['nome'] }}</div>
                                @if($l['departamento'])
                                    <div class="text-xs text-gray-400">{{ $l['departamento'] }}</div>
                                @endif
                                @if($l['folhas'] > 1)
                                    {{-- Duas folhas no mesmo mês (a normal e a do 13.º, por exemplo):
                                         somam-se, porque o que se declara é o total retido à pessoa. --}}
                                    <div class="text-xs text-indigo-500">{{ $l['folhas'] }} folhas somadas</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs {{ $l['nif'] ? 'text-gray-600' : 'text-red-500' }}">
                                {{ $l['nif'] ?: 'SEM NIF' }}
                            </td>
                            <td class="px-4 py-3 text-right">{{ number_format($l['bruto'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">{{ number_format($l['inss'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($l['base'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">
                                @if($l['isento'])
                                    <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-0.5 rounded-full">isento</span>
                                @else
                                    {{ number_format($l['taxa'], 2, ',', '.') }}%
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-bold {{ $l['isento'] ? 'text-gray-400' : 'text-rose-700' }}">
                                {{ number_format($l['irt'], 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-inbox text-gray-400 text-2xl"></i>
                                </div>
                                <h4 class="font-bold text-gray-900 mb-1">Sem retenções neste período</h4>
                                <p class="text-gray-500 text-sm">
                                    @if($mapa['ignoradas']->isNotEmpty())
                                        Há folhas deste mês, mas nenhuma está aprovada ou paga.
                                    @else
                                        Não há folha de salários processada para {{ $nomeDoMes }} de {{ $ano }}.
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if($mapa['linhas']->isNotEmpty())
                    <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                        <tr class="font-bold">
                            <td colspan="3" class="px-4 py-3 text-gray-700">
                                TOTAL — {{ $t['trabalhadores'] }} trabalhador(es), {{ $t['tributados'] }} tributado(s)
                            </td>
                            <td class="px-4 py-3 text-right">{{ number_format($t['bruto'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($t['inss'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($t['base'], 2, ',', '.') }}</td>
                            <td></td>
                            <td class="px-4 py-3 text-right text-rose-700 text-base">{{ number_format($t['irt'], 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Trabalhadores sem NIF impedem a entrega: é melhor dizê-lo aqui do que
         a AGT dizê-lo depois. --}}
    @php $semNif = $mapa['linhas']->where('nif', '')->count(); @endphp
    @if($semNif > 0)
        <div class="mt-6 bg-red-50 border border-red-200 rounded-2xl p-4 text-sm text-red-800">
            <i class="fas fa-circle-exclamation mr-1"></i>
            <strong>{{ $semNif }} trabalhador(es) sem NIF.</strong>
            A declaração é feita por NIF — preencha-o na ficha de cada um antes de entregar este mapa.
        </div>
    @endif
</div>
