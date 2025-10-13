<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    {{-- Header --}}
    <div class="px-6 py-4 bg-gradient-to-r from-yellow-600 to-orange-600">
        <h2 class="text-xl font-bold text-white flex items-center">
            <i class="fas fa-list mr-2"></i>
            Períodos de {{ $year }}
        </h2>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b-2 border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Código</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Data Início</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Data Fim</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Estado</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fechado em</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($periods as $period)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4">
                        <span class="font-mono font-semibold text-gray-900">{{ $period->code }}</span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="font-medium text-gray-900">{{ $period->name }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{ \Carbon\Carbon::parse($period->date_start)->format('d/m/Y') }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{ \Carbon\Carbon::parse($period->date_end)->format('d/m/Y') }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($period->state === 'open')
                            <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">
                                <i class="fas fa-lock-open mr-1"></i>ABERTO
                            </span>
                        @else
                            <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                                <i class="fas fa-lock mr-1"></i>FECHADO
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        @if($period->closed_at)
                            <div>
                                <div>{{ \Carbon\Carbon::parse($period->closed_at)->format('d/m/Y H:i') }}</div>
                                @if($period->closedBy)
                                <div class="text-xs text-gray-500">por {{ $period->closedBy->name }}</div>
                                @endif
                            </div>
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        @if($period->state === 'open')
                            <button 
                                wire:click="confirmClose({{ $period->id }}, '{{ $period->name }}')"
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm font-semibold">
                                <i class="fas fa-lock mr-1"></i>Fechar
                            </button>
                        @else
                            <button 
                                wire:click="confirmReopen({{ $period->id }}, '{{ $period->name }}')"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm font-semibold">
                                <i class="fas fa-lock-open mr-1"></i>Reabrir
                            </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-calendar-times text-4xl mb-3 block"></i>
                        Nenhum período encontrado para {{ $year }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Info Footer --}}
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
        <div class="flex items-center text-sm text-gray-600">
            <i class="fas fa-info-circle mr-2 text-blue-600"></i>
            <span><strong>Importante:</strong> Ao fechar um período, não será possível criar ou editar lançamentos nesse mês. Apenas reabra se necessário corrigir algo.</span>
        </div>
    </div>
</div>
