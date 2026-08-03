<div class="p-6 space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Facturas Recebidas <span class="text-sm font-normal text-gray-500">(Adquirente — AGT)</span>
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Listar, consultar e validar facturas emitidas por fornecedores — DS.120 §§4.3, 4.4, 4.7.
            </p>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session('agt_success'))
        <div class="rounded-2xl bg-green-50 border border-green-200 text-green-800 px-4 py-3">
            {{ session('agt_success') }}
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-2xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 flex items-start gap-2">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ $errorMessage }}</span>
        </div>
    @endif

    {{-- Filtro de período --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Data início</label>
                <input type="date" wire:model="queryStartDate"
                       class="w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500 px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Data fim</label>
                <input type="date" wire:model="queryEndDate"
                       class="w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500 px-3 py-2 text-sm" />
            </div>
            <div class="md:col-span-2">
                <button type="button" wire:click="listInvoices" wire:loading.attr="disabled"
                        class="w-full md:w-auto inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm transition disabled:opacity-50">
                    <svg wire:loading.remove wire:target="listInvoices" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <svg wire:loading wire:target="listInvoices" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke-width="2" opacity="0.25"></circle>
                        <path d="M4 12a8 8 0 018-8" stroke-width="2"></path>
                    </svg>
                    Listar Facturas
                </button>
            </div>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-3">Documento</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Data</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Total Líquido</th>
                        <th class="px-4 py-3 text-right">Acções</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($invoices as $inv)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30 transition">
                            <td class="px-4 py-3 text-sm font-mono">{{ $inv['documentNo'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $inv['documentType'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $inv['documentDate'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    @if (($inv['documentStatus'] ?? '') === 'V') bg-green-100 text-green-700
                                    @elseif (($inv['documentStatus'] ?? '') === 'I') bg-red-100 text-red-700
                                    @else bg-gray-100 text-gray-700 @endif">
                                    {{ $inv['documentStatusDescription'] ?? $inv['documentStatus'] ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium">
                                {{ number_format((float)($inv['netTotal'] ?? 0), 2, ',', '.') }} AOA
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button"
                                        wire:click="viewDocument('{{ $inv['documentNo'] ?? '' }}')"
                                        class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium transition">
                                    Detalhe
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-500">
                                @if ($loading)
                                    A carregar...
                                @else
                                    Nenhuma factura no período seleccionado. Clique em <strong>Listar Facturas</strong>.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Detalhe do documento --}}
    @if ($selectedDocument)
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Documento {{ $selectedDocumentNo }}
                    </h2>
                    <p class="text-xs text-gray-500">Detalhe completo (DS.120 §4.4)</p>
                </div>
                <div class="flex gap-2">
                    <button wire:click="openValidationModal('C')"
                            class="px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition">
                        ✓ Confirmar
                    </button>
                    <button wire:click="openValidationModal('R')"
                            class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">
                        ✗ Rejeitar
                    </button>
                </div>
            </div>

            <pre class="text-xs bg-gray-50 dark:bg-gray-900 p-4 rounded-xl overflow-auto max-h-96">{{ json_encode($selectedDocument, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @endif

    {{-- Modal de validação --}}
    @if ($showValidationModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
             wire:click.self="$set('showValidationModal', false)">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $validationAction === 'C' ? 'Confirmar Documento' : 'Rejeitar Documento' }}
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Documento: <span class="font-mono font-medium">{{ $selectedDocumentNo }}</span>
                </p>

                @if ($validationAction === 'C')
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                % IVA dedutível <span class="text-gray-400">(exclusivo com valor não dedutível)</span>
                            </label>
                            <input type="number" step="0.01" min="0" max="100"
                                   wire:model="deductibleVATPercentage"
                                   class="w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Valor IVA não dedutível (AOA)
                            </label>
                            <input type="number" step="0.01" min="0"
                                   wire:model="nonDeductibleAmount"
                                   class="w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-blue-500 px-3 py-2 text-sm" />
                        </div>
                    </div>
                @endif

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showValidationModal', false)"
                            class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium transition">
                        Cancelar
                    </button>
                    <button type="button" wire:click="submitValidation" wire:loading.attr="disabled"
                            class="px-4 py-2 rounded-xl {{ $validationAction === 'C' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }} text-white text-sm font-medium transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="submitValidation">
                            {{ $validationAction === 'C' ? 'Confirmar' : 'Rejeitar' }}
                        </span>
                        <span wire:loading wire:target="submitValidation">A submeter...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
