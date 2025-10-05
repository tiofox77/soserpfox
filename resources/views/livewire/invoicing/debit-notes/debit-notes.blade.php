<div class="p-6">
    {{-- Header Vermelho --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-file-circle-plus mr-3 text-red-600"></i>
                    Notas de Débito
                </h2>
                <p class="text-gray-600 mt-1">Juros, multas e cobranças adicionais</p>
            </div>
            <a href="{{ route('invoicing.debit-notes.create') }}" 
               class="px-6 py-3 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white rounded-xl font-bold transition shadow-lg transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Nova Nota de Débito
            </a>
        </div>
    </div>

    {{-- Stats Cards Vermelhos --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-200 text-xs font-medium">Total</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-file-circle-plus text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-gray-500 to-gray-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-200 text-xs font-medium">Rascunho</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['draft'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-edit text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-rose-500 to-rose-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-rose-200 text-xs font-medium">Emitidas</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['issued'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-200 text-xs font-medium">Valor Total</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($stats['total_amount'], 2) }}</p>
                    <p class="text-orange-200 text-xs">AOA</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-coins text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-xl shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <input type="text" wire:model.live="search" placeholder="Pesquisar..." class="rounded-lg border-gray-300">
            <select wire:model.live="filterStatus" class="rounded-lg border-gray-300">
                <option value="">Todos os Status</option>
                <option value="draft">Rascunho</option>
                <option value="issued">Emitida</option>
                <option value="paid">Paga</option>
                <option value="cancelled">Cancelada</option>
            </select>
            <select wire:model.live="filterReason" class="rounded-lg border-gray-300">
                <option value="">Todos os Motivos</option>
                <option value="interest">Juros</option>
                <option value="penalty">Multa</option>
                <option value="additional_charge">Cobrança Adicional</option>
                <option value="correction">Correção</option>
                <option value="other">Outro</option>
            </select>
            <input type="date" wire:model.live="filterDateFrom" class="rounded-lg border-gray-300">
            <input type="date" wire:model.live="filterDateTo" class="rounded-lg border-gray-300">
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-red-50 to-rose-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-hashtag mr-1 text-red-600"></i>Número
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-user mr-1 text-red-600"></i>Cliente
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-file-invoice mr-1 text-red-600"></i>Fatura Ref.
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-calendar mr-1 text-red-600"></i>Data
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-tag mr-1 text-red-600"></i>Motivo
                    </th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-coins mr-1 text-red-600"></i>Total
                    </th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-info-circle mr-1 text-red-600"></i>Status
                    </th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-cog mr-1 text-red-600"></i>Ações
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($debitNotes as $debitNote)
                <tr class="hover:bg-red-50 transition-colors duration-150">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-bold text-red-600">{{ $debitNote->debit_note_number }}</div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900">{{ $debitNote->client->name }}</div>
                        <div class="text-xs text-gray-500">NIF: {{ $debitNote->client->nif ?? 'N/D' }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($debitNote->invoice)
                        <div class="text-sm text-gray-900">{{ $debitNote->invoice->invoice_number }}</div>
                        <div class="text-xs text-gray-500">{{ $debitNote->invoice->invoice_date->format('d/m/Y') }}</div>
                        @else
                        <span class="text-xs text-gray-400">N/A</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $debitNote->issue_date->format('d/m/Y') }}
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">
                            {{ $debitNote->reason_label ?? 'N/A' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <span class="text-sm font-bold text-red-600">{{ number_format($debitNote->total, 2) }}</span>
                        <span class="text-xs text-gray-500">AOA</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="inline-flex items-center px-3 py-1 bg-gradient-to-r from-{{ $debitNote->status_color }}-100 to-{{ $debitNote->status_color }}-200 text-{{ $debitNote->status_color }}-800 text-xs font-bold rounded-full">
                            <i class="fas fa-circle mr-1 text-xs"></i>
                            {{ $debitNote->status_label }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('invoicing.debit-notes.preview', $debitNote->id) }}" 
                               target="_blank"
                               class="p-2 bg-purple-100 text-purple-600 rounded-lg hover:bg-purple-200 transition" 
                               title="Preview HTML">
                                <i class="fas fa-file-alt"></i>
                            </a>
                            <a href="{{ route('invoicing.debit-notes.pdf', $debitNote->id) }}" 
                               target="_blank"
                               class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition" 
                               title="Gerar PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <a href="{{ route('invoicing.debit-notes.edit', $debitNote->id) }}" 
                               class="p-2 bg-indigo-100 text-indigo-600 rounded-lg hover:bg-indigo-200 transition" 
                               title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button wire:click="confirmDelete({{ $debitNote->id }})" 
                                    class="p-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition" 
                                    title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center">
                        <div class="flex flex-col items-center justify-center text-gray-400">
                            <i class="fas fa-file-circle-plus text-6xl mb-4"></i>
                            <p class="text-lg font-medium">Nenhuma nota de débito encontrada</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="px-6 py-4">
            {{ $debitNotes->links() }}
        </div>
    </div>

    <style>
        @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scale-in { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .animate-fade-in { animation: fade-in 0.2s ease-out; }
        .animate-scale-in { animation: scale-in 0.2s ease-out; }
    </style>
</div>
