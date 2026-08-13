<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-lg p-4 sm:p-6 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3 sm:mr-4">
                    <i class="fas fa-file-circle-plus text-xl sm:text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-lg sm:text-2xl font-bold">{{ __('Notas de Débito') }}</h2>
                    <p class="text-green-100 text-xs sm:text-sm">{{ __('Juros, multas e cobranças adicionais') }}</p>
                </div>
            </div>
            <a href="{{ route('invoicing.debit-notes.create') }}"
               class="bg-white text-green-600 hover:bg-green-50 px-4 sm:px-6 py-2 sm:py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105 text-sm sm:text-base">
                <i class="fas fa-plus mr-2"></i>{{ __('Nova Nota de Débito') }}
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-green-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/40 mb-3 sm:mb-4">
                <i class="fas fa-file-circle-plus text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-green-600 font-semibold mb-1">{{ __('Total') }}</p>
            <p class="text-2xl sm:text-4xl font-bold text-gray-900">{{ $stats['total'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-gray-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-gray-500 to-gray-600 rounded-2xl flex items-center justify-center shadow-lg shadow-gray-500/40 mb-3 sm:mb-4">
                <i class="fas fa-edit text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-gray-600 font-semibold mb-1">{{ __('Rascunho') }}</p>
            <p class="text-2xl sm:text-4xl font-bold text-gray-900">{{ $stats['draft'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-emerald-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-emerald-500 to-green-600 rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-500/40 mb-3 sm:mb-4">
                <i class="fas fa-check-circle text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-emerald-600 font-semibold mb-1">{{ __('Emitidas') }}</p>
            <p class="text-2xl sm:text-4xl font-bold text-gray-900">{{ $stats['issued'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-orange-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-orange-500 to-amber-600 rounded-2xl flex items-center justify-center shadow-lg shadow-orange-500/40 mb-3 sm:mb-4">
                <i class="fas fa-coins text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-orange-600 font-semibold mb-1">{{ __('Valor Total') }}</p>
            <p class="text-xl sm:text-3xl font-bold text-gray-900">{{ number_format($stats['total_amount'], 2) }} <span class="text-xs text-gray-500">AOA</span></p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-4 sm:p-6">
        <h3 class="text-lg font-bold text-gray-900 flex items-center mb-4">
            <i class="fas fa-filter mr-2 text-green-600"></i>{{ __('Filtros') }}
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 sm:gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-search mr-1"></i>{{ __('Pesquisar') }}</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Pesquisar...') }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-info-circle mr-1"></i>{{ __('Status') }}</label>
                <select wire:model.live="filterStatus" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm bg-white">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="draft">{{ __('Rascunho') }}</option>
                    <option value="issued">{{ __('Emitida') }}</option>
                    <option value="paid">{{ __('Paga') }}</option>
                    <option value="cancelled">{{ __('Cancelada') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-tag mr-1"></i>{{ __('Motivo') }}</label>
                <select wire:model.live="filterReason" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm bg-white">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="interest">{{ __('Juros') }}</option>
                    <option value="penalty">{{ __('Multa') }}</option>
                    <option value="additional_charge">{{ __('Cobrança Adicional') }}</option>
                    <option value="correction">{{ __('Correção') }}</option>
                    <option value="other">{{ __('Outro') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-calendar-alt mr-1"></i>{{ __('De') }}</label>
                <input type="date" wire:model.live="filterDateFrom" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-calendar-alt mr-1"></i>{{ __('Até') }}</label>
                <input type="date" wire:model.live="filterDateTo" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition text-sm">
            </div>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-green-50 to-emerald-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-hashtag mr-1 text-green-600"></i>{{ __('Número') }}
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-user mr-1 text-green-600"></i>{{ __('Cliente') }}
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-file-invoice mr-1 text-green-600"></i>{{ __('Fatura Ref.') }}
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-calendar mr-1 text-green-600"></i>{{ __('Data') }}
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-tag mr-1 text-green-600"></i>{{ __('Motivo') }}
                    </th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-coins mr-1 text-green-600"></i>{{ __('Total') }}
                    </th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-info-circle mr-1 text-green-600"></i>{{ __('Status') }}
                    </th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-cog mr-1 text-green-600"></i>{{ __('Ações') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($debitNotes as $debitNote)
                <tr class="hover:bg-green-50 transition-colors duration-150">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-bold text-green-600">{{ $debitNote->debit_note_number }}</div>
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
                        <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                            {{ $debitNote->reason_label ?? 'N/A' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <span class="text-sm font-bold text-green-600">{{ number_format($debitNote->total, 2) }}</span>
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
                            <button wire:click="viewDebitNote({{ $debitNote->id }})"
                                    class="p-2 bg-emerald-100 text-emerald-600 rounded-lg hover:bg-emerald-200 transition"
                                    title="{{ __('Ver detalhes') }}">
                                <i class="fas fa-eye"></i>
                            </button>
                            <a href="{{ route('invoicing.debit-notes.preview', $debitNote->id) }}"
                               target="_blank"
                               class="p-2 bg-purple-100 text-purple-600 rounded-lg hover:bg-purple-200 transition"
                               title="{{ __('Pré-visualizar HTML') }}">
                                <i class="fas fa-file-alt"></i>
                            </a>
                            <a href="{{ route('invoicing.debit-notes.pdf', $debitNote->id) }}"
                               target="_blank"
                               class="p-2 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 transition"
                               title="{{ __('Descarregar PDF') }}">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <a href="{{ route('invoicing.debit-notes.edit', $debitNote->id) }}"
                               class="p-2 bg-indigo-100 text-indigo-600 rounded-lg hover:bg-indigo-200 transition"
                               title="{{ __('Editar') }}">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button wire:click="confirmDelete({{ $debitNote->id }})"
                                    class="p-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition"
                                    title="{{ __('Eliminar') }}">
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
                            <p class="text-lg font-medium">{{ __('Nenhuma nota de débito encontrada') }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-100">
            {{ $debitNotes->links() }}
        </div>
    </div>

    {{-- Modal de visualização --}}
    @include('livewire.invoicing.debit-notes.view-modal')

    <style>
        @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scale-in { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .animate-fade-in { animation: fade-in 0.2s ease-out; }
        .animate-scale-in { animation: scale-in 0.2s ease-out; }
    </style>
</div>
