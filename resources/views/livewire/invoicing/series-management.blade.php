<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-hashtag mr-3 text-purple-600"></i>
                    {{ __('Gestão de Séries de Documentos') }}
                </h1>
                <p class="text-gray-600 mt-2">{{ __('Configure as séries e numeração dos documentos fiscais') }}</p>
            </div>
            <button wire:click="openCreateModal" 
                    class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-xl font-bold transition shadow-lg">
                <i class="fas fa-plus mr-2"></i>
                {{ __('Nova Série') }}
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('Pesquisar') }}</label>
                <input type="text" wire:model.live.debounce.300ms="search" 
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                       placeholder="{{ __('🔍 Buscar por nome ou código...') }}">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('Filtrar por Tipo') }}</label>
                <select wire:model.live="filterType" 
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                    <option value="">{{ __('Todos os tipos') }}</option>
                    <option value="invoice">{{ __('Faturas (FT)') }}</option>
                    <option value="proforma">{{ __('Proformas (PR)') }}</option>
                    <option value="pos">{{ __('Fatura-Recibo POS (FR)') }}</option>
                    <option value="receipt">{{ __('Recibos (RC)') }}</option>
                    <option value="credit_note">{{ __('Notas de Crédito (NC)') }}</option>
                    <option value="debit_note">{{ __('Notas de Débito (ND)') }}</option>
                    <option value="purchase">{{ __('Faturas de Compra (FC)') }}</option>
                    <option value="advance">{{ __('Adiantamentos (AD)') }}</option>
                    <option value="transport">{{ __('Guias de Transporte (GT)') }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Series List --}}
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-file mr-1"></i>{{ __('Tipo') }}
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-tag mr-1"></i>{{ __('Código') }}
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-signature mr-1"></i>{{ __('Nome') }}
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-eye mr-1"></i>{{ __('Pré-visualização') }}
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-sort-numeric-down mr-1"></i>{{ __('Próximo Nº') }}
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-toggle-on mr-1"></i>{{ __('Status') }}
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider" title="{{ __('DS.120 §4.6: A=Aberta, U=Em utilização, F=Fechada') }}">
                            <i class="fas fa-shield-alt mr-1"></i>{{ __('Estado AGT') }}
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-cog mr-1"></i>{{ __('Ações') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($series as $item)
                    <tr class="hover:bg-purple-50 transition-all duration-200">
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $typeBadges = [
                                    'invoice' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'fa-file-invoice-dollar', 'label' => 'Fatura (FT)'],
                                    'proforma' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'icon' => 'fa-file-alt', 'label' => 'Proforma (PR)'],
                                    'pos' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-800', 'icon' => 'fa-cash-register', 'label' => 'POS (FR)'],
                                    'receipt' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'fa-receipt', 'label' => 'Recibo (RC)'],
                                    'credit_note' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'icon' => 'fa-file-invoice', 'label' => 'N. Crédito (NC)'],
                                    'debit_note' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'fa-file-invoice', 'label' => 'N. Débito (ND)'],
                                    'purchase' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-800', 'icon' => 'fa-shopping-cart', 'label' => 'Compra (FC)'],
                                    'advance' => ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-800', 'icon' => 'fa-hand-holding-usd', 'label' => 'Adiantamento (AD)'],
                                    'transport' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'icon' => 'fa-truck', 'label' => 'Guia Transporte (GT)'],
                                ];
                                $badge = $typeBadges[$item->document_type] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon' => 'fa-file', 'label' => $item->document_type];
                            @endphp
                            <span class="px-3 py-1 {{ $badge['bg'] }} {{ $badge['text'] }} text-xs font-bold rounded-full">
                                <i class="fas {{ $badge['icon'] }} mr-1"></i>{{ $badge['label'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-lg font-bold text-purple-600">{{ $item->series_code }}</span>
                            @if($item->is_default)
                                <span class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-800 text-[10px] font-bold rounded">{{ __('PADRÃO') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-semibold text-gray-900">{{ $item->name }}</div>
                            @if($item->description)
                                <div class="text-xs text-gray-500">{{ Str::limit($item->description, 40) }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 bg-gray-100 text-gray-800 text-sm font-mono font-bold rounded">
                                {{ $item->previewNextNumber() }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-lg font-bold text-gray-900">{{ $item->next_number }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($item->is_active)
                                <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">
                                    <i class="fas fa-check-circle mr-1"></i>{{ __('Ativa') }}
                                </span>
                            @else
                                <span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-bold rounded-full">
                                    <i class="fas fa-times-circle mr-1"></i>{{ __('Inativa') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            @php
                                $agtBadges = [
                                    'A' => ['bg' => 'bg-blue-100',   'text' => 'text-blue-800',   'icon' => 'fa-folder-open',   'label' => 'Aberta'],
                                    'U' => ['bg' => 'bg-amber-100',  'text' => 'text-amber-800',  'icon' => 'fa-spinner',       'label' => 'Em uso'],
                                    'F' => ['bg' => 'bg-rose-100',   'text' => 'text-rose-800',   'icon' => 'fa-lock',          'label' => 'Fechada'],
                                ];
                                $agtBadge = $agtBadges[$item->agt_series_status] ?? null;
                            @endphp
                            @if($agtBadge)
                                <span class="px-3 py-1 {{ $agtBadge['bg'] }} {{ $agtBadge['text'] }} text-xs font-bold rounded-full"
                                      title="DS.120 §4.6 — {{ $item->agt_series_status }}">
                                    <i class="fas {{ $agtBadge['icon'] }} mr-1"></i>{{ $agtBadge['label'] }}
                                </span>
                            @elseif($item->isAGTRegistered())
                                <span class="px-3 py-1 bg-gray-100 text-gray-600 text-xs font-bold rounded-full" title="{{ __('Registada na AGT mas sem estado A/U/F sincronizado') }}">
                                    <i class="fas fa-question-circle mr-1"></i>—
                                </span>
                            @else
                                <span class="px-2 py-1 bg-slate-50 text-slate-400 text-[10px] rounded-full">
                                    {{ __('Não AGT') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center space-x-2">
                                <button wire:click="editSeries({{ $item->id }})" 
                                        class="p-2 bg-blue-100 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="confirmDelete({{ $item->id }})" 
                                        class="p-2 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <i class="fas fa-hashtag text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 text-lg font-semibold">{{ __('Nenhuma série encontrada') }}</p>
                                <p class="text-gray-400 text-sm mt-2">{{ __('Crie a primeira série de documentos') }}</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($series->hasPages())
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $series->links() }}
        </div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    @if($showModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas {{ $isEdit ? 'fa-edit' : 'fa-plus' }} mr-2"></i>
                    {{ $isEdit ? 'Editar Série' : 'Nova Série' }}
                </h3>
                <button wire:click="$set('showModal', false)" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form wire:submit.prevent="save" class="p-6 space-y-4">
                {{-- Tipo de Documento --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-file mr-1 text-blue-500"></i>
                        {{ __('Tipo de Documento *') }}
                    </label>
                    @php
                        // Só os NOMES é que são daqui. O prefixo entre parênteses
                        // estava escrito à mão em cada opção — mais uma cópia do
                        // mapa, e logo a que o utilizador lê para saber o que
                        // esperar do número. Uma opção a dizer "(PR)" enquanto a
                        // série sai com outro prefixo é exactamente o engano que
                        // deixou passar 17 séries erradas.
                        $tiposDeDocumento = [
                            'invoice'     => __('Fatura'),
                            'proforma'    => __('Proforma'),
                            'pos'         => __('Fatura-Recibo POS'),
                            'receipt'     => __('Recibo'),
                            'credit_note' => __('Nota de Crédito'),
                            'debit_note'  => __('Nota de Débito'),
                            'purchase'    => __('Fatura de Compra'),
                            'advance'     => __('Adiantamento'),
                            'transport'   => __('Guia de Transporte'),
                        ];
                    @endphp
                    {{-- .live: o prefixo é derivado do tipo e tem de acompanhar a escolha --}}
                    <select wire:model.live="document_type"
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                        @foreach ($tiposDeDocumento as $tipoDeDocumento => $rotuloDoTipo)
                            <option value="{{ $tipoDeDocumento }}">
                                {{ $rotuloDoTipo }} ({{ \App\Models\Invoicing\InvoicingSeries::prefixoDe($tipoDeDocumento) }})
                            </option>
                        @endforeach
                    </select>
                    @error('document_type') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    {{-- Prefixo --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-tag mr-1 text-blue-500"></i>
                            {{-- Este rótulo dizia "(FT, PRF, RC)" e ensinava a escrever
                                 'PRF' onde a AGT exige 'PR'. O prefixo é fixado pela
                                 AGT e derivado do tipo — não se escreve à mão. --}}
                            {{ __('Prefixo AGT (fixado pelo tipo de documento)') }}
                        </label>
                        <input type="text" wire:model="prefix" readonly
                               class="w-full px-4 py-3 border-2 border-gray-200 bg-gray-100 text-gray-600 rounded-xl cursor-not-allowed"
                               placeholder="FT">
                        @error('prefix') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    {{-- Código da Série --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-barcode mr-1 text-blue-500"></i>
                            {{ __('Código da Série *') }}
                        </label>
                        <input type="text" wire:model="series_code" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                               placeholder="{{ __('A, B, 01, etc') }}">
                        @error('series_code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Nome --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-signature mr-1 text-blue-500"></i>
                        {{ __('Nome da Série *') }}
                    </label>
                    <input type="text" wire:model="name" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                           placeholder="{{ __('Ex: Vendas Loja, Vendas Online') }}">
                    @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    {{-- Próximo Número --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-sort-numeric-down mr-1 text-blue-500"></i>
                            {{ __('Próximo Número *') }}
                        </label>
                        <input type="number" wire:model="next_number" min="1" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                        @error('next_number') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    {{-- Zeros à esquerda --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            {{ __('Zeros à Esquerda *') }}
                        </label>
                        <input type="number" wire:model="number_padding" min="1" max="10" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                               placeholder="6">
                        @error('number_padding') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-500 mt-1">6 = 000001, 4 = 0001</p>
                    </div>
                </div>

                {{-- Opções --}}
                <div class="space-y-3 p-4 bg-gray-50 rounded-xl">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="include_year" 
                               class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <span class="ml-3 text-sm font-semibold text-gray-700">
                            {{ __('Incluir ano no formato (FT A/2025/000001)') }}
                        </span>
                    </label>

                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="reset_yearly" 
                               class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <span class="ml-3 text-sm font-semibold text-gray-700">
                            {{ __('Resetar numeração anualmente') }}
                        </span>
                    </label>

                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="is_default" 
                               class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <span class="ml-3 text-sm font-semibold text-gray-700">
                            {{ __('Definir como série padrão') }}
                        </span>
                    </label>

                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="is_active" 
                               class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                        <span class="ml-3 text-sm font-semibold text-gray-700">
                            {{ __('Série ativa') }}
                        </span>
                    </label>
                </div>

                {{-- Configuração AGT (DS.120 §§4.5/4.6) --}}
                <div class="p-4 bg-gradient-to-br from-emerald-50 to-green-50 border border-emerald-200 rounded-xl space-y-3">
                    <h4 class="text-sm font-bold text-emerald-900 flex items-center gap-2">
                        <i class="fas fa-shield-alt text-emerald-600"></i>
                        {{ __('Configuração AGT (Facturação Electrónica)') }}
                    </h4>
                    <p class="text-xs text-emerald-800/80">
                        {{ __('Estes campos só são usados ao registar a série na AGT (`SolicitarSerie`).') }}
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                {{ __('Ano da Série') }}
                                <span class="text-[10px] text-gray-500">{{ __('(DS.120 §4.5)') }}</span>
                            </label>
                            <input type="number" wire:model="series_year" min="2024" max="2099"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 outline-none text-sm">
                            @error('series_year')
                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-[10px] text-gray-500 mt-1">{{ __('Jan–15Dez: ano corrente. Após 15Dez: corrente ou seguinte.') }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                {{ __('Estabelecimento') }}
                            </label>
                            <input type="text" wire:model="establishment_number" placeholder="SEDE"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 outline-none text-sm">
                            @error('establishment_number')
                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">
                                {{ __('Método de Facturação') }}
                                <span class="text-[10px] text-gray-500">{{ __('(DS.120 §4.6)') }}</span>
                            </label>
                            <select wire:model="invoicing_method"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 outline-none text-sm">
                                <option value="">{{ __('— Seleccionar —') }}</option>
                                <option value="FEPC">{{ __('FEPC — Pré-Comunicada') }}</option>
                                <option value="FESF">{{ __('FESF — Sem Facturação') }}</option>
                                <option value="SF">{{ __('SF — Sem Factura') }}</option>
                            </select>
                            @error('invoicing_method')
                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Descrição --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        {{ __('Descrição') }}
                    </label>
                    <textarea wire:model="description" rows="2"
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                              placeholder="{{ __('Descrição adicional (opcional)') }}"></textarea>
                </div>

                {{-- Preview --}}
                <div class="p-4 bg-blue-50 rounded-xl">
                    <p class="text-sm font-semibold text-blue-900 mb-2">
                        <i class="fas fa-eye mr-2"></i>{{ __('Pré-visualização:') }}
                    </p>
                    <p class="text-lg font-mono font-bold text-blue-700">
                        {{ $prefix }} {{ $series_code }} {{ $include_year ? date('Y') . '/' : '' }}{{ str_pad($next_number, $number_padding, '0', STR_PAD_LEFT) }}
                    </p>
                </div>

                {{-- Buttons --}}
                <div class="flex space-x-3 pt-4">
                    <button type="button" wire:click="$set('showModal', false)"
                            class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                        <i class="fas fa-times mr-2"></i>{{ __('Cancelar') }}
                    </button>
                    <button type="submit"
                            class="flex-1 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-xl font-semibold transition shadow-lg">
                        <i class="fas fa-save mr-2"></i>{{ __('Salvar') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Delete Modal --}}
    @if($showDeleteModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
            <div class="flex items-center justify-center w-16 h-16 mx-auto bg-red-100 rounded-full mb-4">
                <i class="fas fa-trash text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-center text-gray-900 mb-2">{{ __('Eliminar Série?') }}</h3>
            <p class="text-center text-gray-600 mb-6">{{ __('Esta ação não pode ser revertida.') }}</p>
            <div class="flex gap-3">
                <button wire:click="$set('showDeleteModal', false)" 
                        class="flex-1 px-4 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                    {{ __('Cancelar') }}
                </button>
                <button wire:click="deleteSeries" 
                        class="flex-1 px-4 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition">
                    {{ __('Eliminar') }}
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Toastr Notifications --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('notify', (event) => {
                const data = event[0] || event;
                const type = data.type || 'info';
                const message = data.message || 'Ação realizada';
                
                if (typeof toastr !== 'undefined') {
                    toastr.options = {
                        "closeButton": true,
                        "progressBar": true,
                        "positionClass": "toast-top-right",
                        "timeOut": "3000",
                    };
                    
                    toastr[type](message);
                }
            });
        });
    </script>
</div>
