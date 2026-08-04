@php
    $ehCliente = $entidade === 'cliente';
    $quem      = $ehCliente ? 'Cliente' : 'Fornecedor';
@endphp

<div class="p-6 max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-sky-600 to-cyan-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-file-invoice-dollar text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">Extracto de Conta Corrente</h1>
                    <p class="text-sky-100 text-sm">
                        Todos os movimentos por ordem, com saldo acumulado — o que deve, e porquê
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                @if($entidadeId)
                    <a href="{{ route('invoicing.reports.account-statement.pdf', [
                            'entidade' => $entidade, 'id' => $entidadeId,
                            'de' => $dateFrom, 'ate' => $dateTo,
                       ]) }}"
                       target="_blank" rel="noopener"
                       class="px-4 py-2 bg-white/20 rounded-lg hover:bg-white/30 text-sm font-semibold">
                        <i class="fas fa-file-pdf mr-1"></i>PDF
                    </a>
                @endif
                <a href="{{ route('invoicing.reports.hub') }}"
                   class="px-4 py-2 bg-white/20 rounded-lg hover:bg-white/30 text-sm font-semibold">
                    <i class="fas fa-arrow-left mr-1"></i>Relatórios
                </a>
            </div>
        </div>
    </div>

    {{-- Escolha da conta --}}
    <div class="mb-6 bg-white rounded-2xl shadow p-5">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Conta</label>
                <select wire:model.live="entidade" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                    <option value="cliente">Cliente</option>
                    <option value="fornecedor">Fornecedor</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">De</label>
                <input type="date" wire:model.live="dateFrom" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Até</label>
                <input type="date" wire:model.live="dateTo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div class="relative">
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ $quem }}</label>
                @if($this->entidadeSelecionada)
                    <div class="flex items-center gap-2 px-3 py-2 border border-sky-200 bg-sky-50 rounded-lg">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900 text-sm truncate">{{ $this->entidadeSelecionada->name }}</p>
                            <p class="text-[11px] text-gray-500">NIF: {{ $this->entidadeSelecionada->nif ?: '—' }}</p>
                        </div>
                        <button wire:click="limpar" type="button" class="text-gray-400 hover:text-red-600" title="Trocar">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @else
                    <input type="text" wire:model.live.debounce.300ms="procura"
                           placeholder="Nome ou NIF…"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">

                    @if(strlen(trim($procura)) > 0)
                        <div class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                            @forelse($this->resultados as $r)
                                <button type="button" wire:click="selecionar({{ $r->id }})"
                                        class="w-full text-left px-3 py-2 hover:bg-sky-50 border-b border-gray-100 last:border-b-0">
                                    <p class="font-semibold text-gray-900 text-sm truncate">{{ $r->name }}</p>
                                    <p class="text-[11px] text-gray-500">NIF: {{ $r->nif ?: '—' }}</p>
                                </button>
                            @empty
                                <div class="px-3 py-3 text-sm text-gray-500 text-center">
                                    <i class="fas fa-search-minus mr-1"></i> Nada encontrado
                                </div>
                            @endforelse
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    @if(!$entidadeId)
        <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
            <i class="fas fa-user-tag text-5xl text-gray-300 mb-4"></i>
            <p class="text-gray-500 font-semibold">Escolha um {{ strtolower($quem) }} para ver o extracto</p>
            <p class="text-gray-400 text-sm mt-1">Procure pelo nome ou pelo NIF no campo acima</p>
        </div>
    @else
        {{-- Resumo --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-2xl shadow p-4 border border-gray-100">
                <p class="text-[11px] text-gray-500 uppercase font-bold">Saldo anterior</p>
                <p class="text-2xl font-bold text-gray-700">{{ number_format($resumo['saldo_anterior'], 2, ',', '.') }}</p>
                <p class="text-[11px] text-gray-400">antes de {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow p-4 border border-red-100">
                <p class="text-[11px] text-gray-500 uppercase font-bold">Débito</p>
                <p class="text-2xl font-bold text-red-600">{{ number_format($resumo['debito'], 2, ',', '.') }}</p>
                <p class="text-[11px] text-gray-400">{{ $ehCliente ? 'facturado' : 'pago ao fornecedor' }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow p-4 border border-emerald-100">
                <p class="text-[11px] text-gray-500 uppercase font-bold">Crédito</p>
                <p class="text-2xl font-bold text-emerald-600">{{ number_format($resumo['credito'], 2, ',', '.') }}</p>
                <p class="text-[11px] text-gray-400">{{ $ehCliente ? 'recebido e creditado' : 'facturado pelo fornecedor' }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow p-4 border border-sky-200">
                <p class="text-[11px] text-gray-500 uppercase font-bold">Saldo em dívida</p>
                <p class="text-2xl font-bold {{ $resumo['saldo_final'] > 0 ? 'text-sky-700' : 'text-gray-400' }}">
                    {{ number_format($resumo['saldo_final'], 2, ',', '.') }}
                </p>
                <p class="text-[11px] text-gray-400">
                    {{ $resumo['saldo_final'] > 0
                        ? ($ehCliente ? 'o cliente deve-nos' : 'devemos ao fornecedor')
                        : 'conta saldada' }}
                </p>
            </div>
        </div>

        {{-- Movimentos --}}
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="font-bold text-gray-900">
                    <i class="fas fa-list mr-2 text-sky-600"></i>Movimentos
                    <span class="ml-2 text-xs font-normal text-gray-500">{{ $resumo['movimentos'] }} no período</span>
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Data</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Documento</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Débito</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Crédito</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        {{-- O saldo anterior é uma LINHA e não só um cartão: sem ele
                             à cabeça, a coluna de saldo parece começar do nada. --}}
                        <tr class="bg-gray-50">
                            <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-gray-400 text-xs">—</td>
                            <td class="px-4 py-2 text-gray-500 italic">Saldo transportado</td>
                            <td class="px-4 py-2"></td>
                            <td class="px-4 py-2"></td>
                            <td class="px-4 py-2 text-right font-bold text-gray-600">
                                {{ number_format($resumo['saldo_anterior'], 2, ',', '.') }}
                            </td>
                        </tr>

                        @forelse($movimentos as $m)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-gray-700 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($m->data)->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold
                                        {{ (float) $m->debito > 0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ $m->tipo }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $m->numero }}</td>
                                <td class="px-4 py-2 text-right text-red-600">
                                    {{ (float) $m->debito != 0 ? number_format($m->debito, 2, ',', '.') : '' }}
                                </td>
                                <td class="px-4 py-2 text-right text-emerald-600">
                                    {{ (float) $m->credito != 0 ? number_format($m->credito, 2, ',', '.') : '' }}
                                </td>
                                <td class="px-4 py-2 text-right font-bold text-gray-900">
                                    {{ number_format($m->saldo, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-400">
                                    <i class="fas fa-inbox text-3xl mb-2"></i>
                                    <p class="text-sm">Sem movimentos no período</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-sky-50">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right font-bold">TOTAIS DO PERÍODO</td>
                            <td class="px-4 py-3 text-right font-bold text-red-700">{{ number_format($resumo['debito'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-700">{{ number_format($resumo['credito'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold text-sky-800">{{ number_format($resumo['saldo_final'], 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</div>
