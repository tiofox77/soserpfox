<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-red-600 to-rose-600 rounded-2xl shadow-lg p-4 sm:p-6 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3 sm:mr-4">
                    <i class="fas fa-file-circle-minus text-xl sm:text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-lg sm:text-2xl font-bold">{{ __('Notas de Crédito') }}</h2>
                    <p class="text-red-100 text-xs sm:text-sm">{{ __('Devoluções, descontos e correções') }}</p>
                </div>
            </div>
            <a href="{{ route('invoicing.credit-notes.create') }}"
               class="bg-white text-red-600 hover:bg-red-50 px-4 sm:px-6 py-2 sm:py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105 text-sm sm:text-base">
                <i class="fas fa-plus mr-2"></i>{{ __('Nova Nota de Crédito') }}
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-red-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl flex items-center justify-center shadow-lg shadow-red-500/40 mb-3 sm:mb-4">
                <i class="fas fa-file-circle-minus text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-red-600 font-semibold mb-1">{{ __('Total') }}</p>
            <p class="text-2xl sm:text-4xl font-bold text-gray-900">{{ $stats['total'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-gray-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-gray-500 to-gray-600 rounded-2xl flex items-center justify-center shadow-lg shadow-gray-500/40 mb-3 sm:mb-4">
                <i class="fas fa-edit text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-gray-600 font-semibold mb-1">{{ __('Rascunho') }}</p>
            <p class="text-2xl sm:text-4xl font-bold text-gray-900">{{ $stats['draft'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-rose-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-rose-500 to-red-600 rounded-2xl flex items-center justify-center shadow-lg shadow-rose-500/40 mb-3 sm:mb-4">
                <i class="fas fa-check-circle text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-rose-600 font-semibold mb-1">{{ __('Emitidas') }}</p>
            <p class="text-2xl sm:text-4xl font-bold text-gray-900">{{ $stats['issued'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-teal-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg shadow-teal-500/40 mb-3 sm:mb-4">
                <i class="fas fa-coins text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-teal-600 font-semibold mb-1">{{ __('Valor Total') }}</p>
            <p class="text-xl sm:text-3xl font-bold text-gray-900">{{ number_format($stats['total_amount'], 2) }} <span class="text-xs text-gray-500">AOA</span></p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-4 sm:p-6">
        <h3 class="text-lg font-bold text-gray-900 flex items-center mb-4">
            <i class="fas fa-filter mr-2 text-red-600"></i>{{ __('Filtros') }}
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 sm:gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-search mr-1"></i>{{ __('Pesquisar') }}</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Pesquisar...') }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-info-circle mr-1"></i>{{ __('Status') }}</label>
                <select wire:model.live="filterStatus" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition text-sm bg-white">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="draft">{{ __('Rascunho') }}</option>
                    <option value="issued">{{ __('Emitida') }}</option>
                    <option value="cancelled">{{ __('Cancelada') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-tag mr-1"></i>{{ __('Motivo') }}</label>
                <select wire:model.live="filterReason" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition text-sm bg-white">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="return">{{ __('Devolução') }}</option>
                    <option value="discount">{{ __('Desconto') }}</option>
                    <option value="correction">{{ __('Correção') }}</option>
                    <option value="other">{{ __('Outro') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-calendar-alt mr-1"></i>{{ __('De') }}</label>
                <input type="date" wire:model.live="filterDateFrom" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-calendar-alt mr-1"></i>{{ __('Até') }}</label>
                <input type="date" wire:model.live="filterDateTo" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition text-sm">
            </div>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-red-50 to-rose-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-red-700 uppercase">{{ __('Número') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-red-700 uppercase">{{ __('Cliente') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-red-700 uppercase">{{ __('Fatura Origem') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-red-700 uppercase">{{ __('Data') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-red-700 uppercase">{{ __('Motivo') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-red-700 uppercase">{{ __('Valor') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-red-700 uppercase">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-red-700 uppercase">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($creditNotes as $creditNote)
                        <tr class="hover:bg-red-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-mono bg-red-100 text-red-800 rounded-full font-bold">
                                    {{ $creditNote->credit_note_number }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 bg-red-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-red-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-semibold text-gray-900">{{ $creditNote->client->name ?? 'N/A' }}</p>
                                        <p class="text-xs text-gray-500">{{ $creditNote->client->nif ?? __('Sem NIF') }}</p>
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
                                <p class="text-lg font-bold text-red-600">
                                    {{ number_format($creditNote->total, 2) }}
                                </p>
                                <p class="text-xs text-gray-500">AOA</p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full
                                    {{ $creditNote->status === 'issued' ? 'bg-red-100 text-red-700' : '' }}
                                    {{ $creditNote->status === 'draft' ? 'bg-gray-100 text-gray-700' : '' }}
                                    {{ $creditNote->status === 'cancelled' ? 'bg-green-100 text-green-700' : '' }}">
                                    {{ $creditNote->status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="viewCreditNote({{ $creditNote->id }})"
                                            class="text-red-600 hover:text-red-900"
                                            title="{{ __('Ver detalhes') }}">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="{{ route('invoicing.credit-notes.preview', $creditNote->id) }}"
                                       target="_blank"
                                       class="text-purple-600 hover:text-purple-900"
                                       title="{{ __('Ver documento') }}">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                    <a href="{{ route('invoicing.credit-notes.pdf', $creditNote->id) }}"
                                       target="_blank"
                                       class="text-green-600 hover:text-green-900"
                                       title="{{ __('Descarregar PDF') }}">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                    @if($creditNote->status === 'draft')
                                    <a href="{{ route('invoicing.credit-notes.edit', $creditNote->id) }}"
                                       class="text-indigo-600 hover:text-indigo-900"
                                       title="{{ __('Editar') }}">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @endif
                                    <button wire:click="confirmDelete({{ $creditNote->id }})"
                                            class="text-green-600 hover:text-green-900"
                                            title="{{ __('Eliminar') }}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <i class="fas fa-file-circle-minus text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 font-medium">{{ __('Nenhuma nota de crédito encontrada') }}</p>
                                <p class="text-gray-400 text-sm mt-2">{{ __('Crie sua primeira nota de crédito para começar') }}</p>
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
            <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 rounded-t-2xl">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas fa-exclamation-triangle mr-2"></i>{{ __('Confirmar Exclusão') }}
                </h3>
            </div>

            <div class="p-6">
                <p class="text-gray-700 mb-4">
                    {{ __('Tem certeza que deseja eliminar esta nota de crédito?') }}
                </p>
                <p class="text-sm text-gray-500">
                    {{ __('Esta ação não pode ser desfeita.') }}
                </p>
            </div>

            <div class="px-6 pb-6 flex gap-3">
                <button wire:click="$set('showDeleteModal', false)"
                        class="flex-1 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold transition">
                    {{ __('Cancelar') }}
                </button>
                <button wire:click="deleteCreditNote"
                        class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition">
                    {{ __('Eliminar') }}
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal de visualização --}}
    @include('livewire.invoicing.credit-notes.view-modal')

    <style>
        @keyframes scale-in { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .animate-scale-in { animation: scale-in 0.2s ease-out; }
    </style>
</div>
