<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-file-invoice mr-3 text-green-600"></i>
                    Lançamentos Contabilísticos
                </h1>
                <p class="text-gray-600 mt-1">Gestão de lançamentos por partidas dobradas</p>
            </div>
            <button wire:click="openModal" 
                    class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:shadow-lg transition flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Novo Lançamento
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-indigo-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Lançamentos</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['total'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Todos os movimentos</p>
                </div>
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-file-invoice text-indigo-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Rascunhos</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['draft'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Aguardando</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-edit text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Lançados</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['posted'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Confirmados</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-600 hover:shadow-xl transition transform hover:-translate-y-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Este Mês</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $stats['this_month'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Mês atual</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Pesquisar Referência</label>
                <input type="text" wire:model.live="search" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500"
                       placeholder="Referência...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Diário</label>
                <select wire:model.live="journalFilter" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
                    <option value="">Todos os diários</option>
                    @foreach($journals as $journal)
                        <option value="{{ $journal->id }}">{{ $journal->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                <select wire:model.live="stateFilter" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
                    <option value="">Todos os estados</option>
                    <option value="draft">Rascunho</option>
                    <option value="posted">Lançado</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Referência</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Diário</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Débito</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Crédito</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Estado</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($moves as $move)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $move->date->format('d/m/Y') }}</td>
                        <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ $move->ref }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $move->journal->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-right font-medium text-green-600">
                            {{ number_format($move->total_debit, 2, ',', '.') }} Kz
                        </td>
                        <td class="px-6 py-4 text-sm text-right font-medium text-red-600">
                            {{ number_format($move->total_credit, 2, ',', '.') }} Kz
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                @if($move->state === 'posted') bg-green-100 text-green-800
                                @elseif($move->state === 'draft') bg-yellow-100 text-yellow-800
                                @else bg-red-100 text-red-800
                                @endif">
                                @if($move->state === 'posted') Lançado
                                @elseif($move->state === 'draft') Rascunho
                                @else Cancelado
                                @endif
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($move->state === 'draft')
                                <button wire:click="post({{ $move->id }})" 
                                        class="text-green-600 hover:text-green-800 mx-1" title="Confirmar">
                                    <i class="fas fa-check-circle"></i>
                                </button>
                            @endif
                            <button wire:click="delete({{ $move->id }})" 
                                    onclick="confirm('Tem certeza?') || event.stopImmediatePropagation()"
                                    class="text-red-600 hover:text-red-800 mx-1">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-3 block"></i>
                            Nenhum lançamento encontrado
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $moves->links() }}
        </div>
    </div>

    {{-- Modal Estilo Primavera/Excel --}}
    @if($showModal)
        @include('livewire.accounting.moves.partials.form-modal-excel')
    @endif
</div>
