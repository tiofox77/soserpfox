<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-file-circle-minus mr-3 text-green-600"></i>
                    Notas de Crédito
                </h2>
                <p class="text-gray-600 mt-1">Devoluções, descontos e correções</p>
            </div>
            <a href="{{ route('invoicing.credit-notes.create') }}" 
               class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-bold transition shadow-lg transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Nova Nota de Crédito
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-xs font-medium">Total</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-file-circle-minus text-xl"></i>
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

        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-emerald-200 text-xs font-medium">Emitidas</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['issued'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-teal-500 to-teal-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-teal-200 text-xs font-medium">Valor Total</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($stats['total_amount'], 2) }}</p>
                    <p class="text-teal-200 text-xs">AOA</p>
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
                <option value="cancelled">Cancelada</option>
            </select>
            <select wire:model.live="filterReason" class="rounded-lg border-gray-300">
                <option value="">Todos os Motivos</option>
                <option value="return">Devolução</option>
                <option value="discount">Desconto</option>
                <option value="correction">Correção</option>
                <option value="other">Outro</option>
            </select>
            <input type="date" wire:model.live="filterDateFrom" class="rounded-lg border-gray-300">
            <input type="date" wire:model.live="filterDateTo" class="rounded-lg border-gray-300">
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-green-50 to-emerald-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase">Número</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase">Cliente</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase">Fatura Origem</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase">Motivo</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-green-700 uppercase">Valor</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-green-700 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-green-700 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($creditNotes as $creditNote)
                        <tr class="hover:bg-green-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-mono bg-green-100 text-green-800 rounded-full font-bold">
                                    {{ $creditNote->credit_note_number }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 bg-green-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-green-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-semibold text-gray-900">{{ $creditNote->client->name ?? 'N/A' }}</p>
                                        <p class="text-xs text-gray-500">{{ $creditNote->client->nif ?? 'Sem NIF' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($creditNote->invoice)
                                    <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded font-mono">
                                        {{ $creditNote->invoice->invoice_number }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $creditNote->issue_date->format('d/m/Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded">
                                    {{ $creditNote->reason_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <p class="text-lg font-bold text-green-600">
                                    {{ number_format($creditNote->total, 2) }}
                                </p>
                                <p class="text-xs text-gray-500">AOA</p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full
                                    {{ $creditNote->status === 'issued' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $creditNote->status === 'draft' ? 'bg-gray-100 text-gray-700' : '' }}
                                    {{ $creditNote->status === 'cancelled' ? 'bg-red-100 text-red-700' : '' }}">
                                    {{ $creditNote->status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('invoicing.credit-notes.preview', $creditNote->id) }}" 
                                       target="_blank"
                                       class="text-purple-600 hover:text-purple-900"
                                       title="Preview">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                    <a href="{{ route('invoicing.credit-notes.pdf', $creditNote->id) }}" 
                                       target="_blank"
                                       class="text-red-600 hover:text-red-900"
                                       title="PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                    @if($creditNote->status === 'draft')
                                    <a href="{{ route('invoicing.credit-notes.edit', $creditNote->id) }}" 
                                       class="text-indigo-600 hover:text-indigo-900"
                                       title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @endif
                                    <button wire:click="confirmDelete({{ $creditNote->id }})" 
                                            class="text-red-600 hover:text-red-900"
                                            title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <i class="fas fa-file-circle-minus text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 font-medium">Nenhuma nota de crédito encontrada</p>
                                <p class="text-gray-400 text-sm mt-2">Crie sua primeira nota de crédito para começar</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($creditNotes->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $creditNotes->links() }}
        </div>
        @endif
    </div>

    {{-- Modal de Confirmação de Exclusão --}}
    @if($showDeleteModal)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full">
            <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 rounded-t-2xl">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Confirmar Exclusão
                </h3>
            </div>
            
            <div class="p-6">
                <p class="text-gray-700 mb-4">
                    Tem certeza que deseja eliminar esta nota de crédito?
                </p>
                <p class="text-sm text-gray-500">
                    Esta ação não pode ser desfeita.
                </p>
            </div>
            
            <div class="px-6 pb-6 flex gap-3">
                <button wire:click="$set('showDeleteModal', false)" 
                        class="flex-1 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    Cancelar
                </button>
                <button wire:click="deleteCreditNote" 
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition">
                    Eliminar
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
