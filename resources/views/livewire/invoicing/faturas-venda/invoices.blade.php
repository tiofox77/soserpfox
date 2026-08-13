{{-- Sem padding proprio: o <main> do layout ja da p-3 sm:p-4 lg:p-6.
     Repeti-lo tirava 96px de largura em ecra grande e deixava esta lista
     visivelmente mais estreita do que as de Clientes e Produtos, que usam
     um <div> simples. --}}
<div>
    {{-- Header --}}
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                {{-- Bloco de PHP em duas linhas, de propósito: a forma de uma
                     linha parte a compilação quando a expressão tem ternários.

                     E NÃO escrever aqui o nome dessas directivas por extenso.
                     Este comentário tinha-as, e o compilador foi buscar a
                     abertura DENTRO do comentário: engolia tudo até ao bloco
                     seguinte — o título, o subtítulo e o botão de criar
                     desapareciam da página, sem erro nenhum. Um comentário a
                     avisar da armadilha caiu nela. --}}
                @php
                    $__ehFR = $typeFilter === 'FR';
                @endphp
                <h2 class="text-xl sm:text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas {{ $__ehFR ? 'fa-receipt text-emerald-600' : 'fa-file-invoice text-purple-600' }} mr-2 sm:mr-3"></i>
                    {{ $__ehFR ? __('Faturas-Recibo') : __('Faturas de Venda') }}
                </h2>
                <p class="text-gray-600 mt-1 text-xs sm:text-base">
                    {{ $__ehFR
                        ? __('Documentos pagos no acto (FR) — mesma sequência do POS')
                        : __('Faturas de vendas para clientes') }}
                </p>
            </div>
            <a href="{{ route('invoicing.sales.invoices.create', $__ehFR ? ['type' => 'FR'] : []) }}"
               x-data="{ loading: false }" @click="loading = true"
               :class="loading && 'opacity-70 pointer-events-none scale-95'"
               class="px-4 sm:px-6 py-2 sm:py-3 bg-gradient-to-r {{ $__ehFR ? 'from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700' : 'from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700' }} text-white rounded-xl font-bold transition-all duration-300 shadow-lg hover:scale-105 active:scale-95 text-sm sm:text-base text-center">
                <span x-show="!loading"><i class="fas fa-plus mr-2"></i>{{ $__ehFR ? __('Nova Fatura-Recibo') : __('Nova Fatura') }}</span>
                <span x-show="loading" x-cloak><i class="fas fa-spinner fa-spin mr-2"></i>{{ __('Carregando...') }}</span>
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded-lg animate-fade-in">
            <i class="fas fa-check-circle mr-2"></i>{{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg animate-fade-in">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-4 sm:mb-6">
        <div class="bg-gradient-to-br from-orange-500 to-red-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-200 text-xs font-medium">{{ __('Total') }}</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-file-alt text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-gray-500 to-gray-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-200 text-xs font-medium">{{ __('Rascunho') }}</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['draft'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-edit text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-200 text-xs font-medium">{{ __('Pendentes') }}</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['pending'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-clock text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-xs font-medium">{{ __('Pagas') }}</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['paid'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-yellow-500 to-orange-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-200 text-xs font-medium">{{ __('Valor Total') }}</p>
                    <p class="text-xl font-bold mt-1">{{ number_format($stats['total_amount'], 2) }} Kz</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-money-bill-wave text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros.
         Grelha de 12 colunas em duas linhas certas. A anterior tinha 5 colunas
         e 6 unidades (a pesquisa ocupava duas), por isso a última data caía
         sozinha para uma segunda linha e a barra parecia partida.

         As datas ganharam rótulo: eram dois campos iguais lado a lado e não
         havia como saber qual era o "de" e qual era o "até". E o filtro de
         ARMAZÉM passa a existir no ecrã — estava no componente e a lista de
         armazéns já era passada à vista, mas não havia onde o escolher. --}}
    <div class="bg-white rounded-xl shadow-md p-3 sm:p-4 mb-4 sm:mb-6">
        <div class="grid grid-cols-2 md:grid-cols-12 gap-3">
            <div class="col-span-2 md:col-span-4">
                <label class="block text-[11px] font-bold text-gray-500 mb-1 uppercase">{{ __('Pesquisar') }}</label>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="{{ __('Número ou cliente…') }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
            </div>
            <div class="col-span-1 md:col-span-2">
                <label class="block text-[11px] font-bold text-gray-500 mb-1 uppercase">{{ __('Tipo') }}</label>
                <select wire:model.live="typeFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-purple-500">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="FT">{{ __('Fatura (FT)') }}</option>
                    <option value="FR">{{ __('Fatura-Recibo (FR)') }}</option>
                </select>
            </div>
            <div class="col-span-1 md:col-span-2">
                <label class="block text-[11px] font-bold text-gray-500 mb-1 uppercase">{{ __('Estado') }}</label>
                <select wire:model.live="statusFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-purple-500">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="draft">{{ __('Rascunho') }}</option>
                    <option value="pending">{{ __('Pendente') }}</option>
                    <option value="paid">{{ __('Pago') }}</option>
                    <option value="partially_paid">{{ __('Parcialmente pago') }}</option>
                    <option value="overdue">{{ __('Atrasado') }}</option>
                    <option value="credited">{{ __('Creditado') }}</option>
                    <option value="cancelled">{{ __('Cancelado') }}</option>
                </select>
            </div>
            <div class="col-span-2 md:col-span-4">
                <label class="block text-[11px] font-bold text-gray-500 mb-1 uppercase">{{ __('Armazém') }}</label>
                <select wire:model.live="warehouseFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-purple-500">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-span-1 md:col-span-3">
                <label class="block text-[11px] font-bold text-gray-500 mb-1 uppercase">{{ __('De') }}</label>
                <input type="date" wire:model.live="dateFrom" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
            </div>
            <div class="col-span-1 md:col-span-3">
                <label class="block text-[11px] font-bold text-gray-500 mb-1 uppercase">{{ __('Até') }}</label>
                <input type="date" wire:model.live="dateTo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
            </div>
            <div class="col-span-1 md:col-span-3">
                <label class="block text-[11px] font-bold text-gray-500 mb-1 uppercase">{{ __('Por página') }}</label>
                <select wire:model.live="perPage" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-purple-500">
                    <option value="15">15</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="col-span-1 md:col-span-3 flex items-end">
                <button wire:click="limparFiltros" type="button"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">
                    <i class="fas fa-eraser mr-1"></i>{{ __('Limpar') }}
                </button>
            </div>
        </div>

        {{-- O período começa no mês corrente: sem isto dizer nada, uma factura
             de Janeiro "desaparecia" e parecia que a pesquisa estava avariada. --}}
        {{-- Uma frase inteira, e nao pedacos colados: em ingles e frances a
             ordem das palavras nao e a portuguesa. O plural vai por
             trans_choice, que o "documento(s)" nao existe noutras linguas. --}}
        <p class="mt-3 text-[11px] text-gray-400">
            <i class="fas fa-circle-info mr-1"></i>
            {{ trans_choice(
                'A mostrar :n documento de :inicio a :fim. Alargue as datas para ver mais.|A mostrar :n documentos de :inicio a :fim. Alargue as datas para ver mais.',
                $invoices->total(),
                [
                    'n' => $invoices->total(),
                    'inicio' => \Carbon\Carbon::parse($dateFrom)->format('d/m/Y'),
                    'fim' => \Carbon\Carbon::parse($dateTo)->format('d/m/Y'),
                ]
            ) }}
        </p>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
            <h3 class="text-white font-bold text-lg flex items-center">
                <i class="fas fa-list mr-2"></i>
                {{ __('Lista de Faturas de Venda') }}
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-hashtag mr-1 text-purple-600"></i>{{ __('Número') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase w-full">
                            {{-- w-full: e o CLIENTE que fica com a largura que sobra.
                                 Sem isto era a coluna de accoes a absorve-la — 282px
                                 para quatro botoes de 34px, enquanto o nome do cliente
                                 se apertava em 149. --}}
                            <i class="fas fa-user mr-1 text-blue-600"></i>{{ __('Cliente') }}
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-calendar mr-1 text-green-600"></i>{{ __('Data') }}
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-calendar-check mr-1 text-purple-600"></i>{{ __('Vencimento') }}
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-info-circle mr-1 text-gray-600"></i>{{ __('Estado') }}
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-money-bill mr-1 text-green-600"></i>{{ __('Total') }}
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase whitespace-nowrap w-px">
                            <i class="fas fa-cog mr-1 text-gray-600"></i>{{ __('Ações') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($invoices as $invoice)
                    <tr class="hover:bg-purple-50 transition-all duration-200">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="text-sm font-bold text-purple-600">{{ $invoice->invoice_number }}</span>
                            @if(($invoice->invoice_type ?? 'FT') === 'FR')
                                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700" title="{{ __('Fatura-Recibo — paga no acto') }}">FR</span>
                            @else
                                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700" title="{{ __('Fatura') }}">FT</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-semibold text-gray-900">{{ $invoice->client->name }}</div>
                            <div class="text-xs text-gray-500">{{ $invoice->client->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-gray-700">
                            {{ $invoice->invoice_date->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-3 text-center text-sm">
                            @if($invoice->due_date)
                                <span class="{{ $invoice->due_date->isPast() ? 'text-red-600 font-bold' : 'text-gray-700' }}">
                                    {{ $invoice->due_date->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-3 py-1 bg-{{ $invoice->status_color }}-100 text-{{ $invoice->status_color }}-800 text-xs font-bold rounded-full inline-flex items-center gap-1">
                                @if($invoice->status === 'draft')
                                    <i class="fas fa-edit"></i>
                                @elseif($invoice->status === 'pending')
                                    <i class="fas fa-clock"></i>
                                @elseif($invoice->status === 'partially_paid')
                                    <i class="fas fa-circle-half-stroke"></i>
                                @elseif($invoice->status === 'paid')
                                    <i class="fas fa-check"></i>
                                @elseif($invoice->status === 'cancelled')
                                    <i class="fas fa-times"></i>
                                @elseif($invoice->status === 'overdue')
                                    <i class="fas fa-exclamation-triangle"></i>
                                @elseif($invoice->status === 'sent')
                                    <i class="fas fa-paper-plane"></i>
                                @elseif($invoice->status === 'credited')
                                    <i class="fas fa-rotate-left"></i>
                                @else
                                    <i class="fas fa-question"></i>
                                @endif
                                {{ $invoice->status_label }}
                            </span>
                            @if($invoice->status === 'partially_paid')
                                <div class="text-xs text-gray-600 mt-1">
                                    {{ __('Pago:') }} {{ number_format($invoice->paid_amount ?? 0, 2) }} AOA
                                    <br>{{ __('Falta:') }} {{ number_format($invoice->balance, 2) }} AOA
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="text-lg font-bold text-gray-900">{{ number_format($invoice->total, 2) }}</span>
                            <div class="text-xs text-gray-500">Kz</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center space-x-2">
                                {{-- Ver Proforma --}}
                                <button wire:click="viewInvoice({{ $invoice->id }})"
                                        wire:loading.attr="disabled"
                                        class="group relative p-2 bg-purple-100 hover:bg-purple-600 rounded-lg transition-all duration-200 transform hover:scale-110 disabled:opacity-50">
                                    <i class="fas fa-eye text-purple-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        {{ __('Visualizar') }}
                                    </span>
                                </button>
                                
                                {{-- Preview / Imprimir --}}
                                <a href="{{ route('invoicing.sales.invoices.preview', $invoice->id) }}" target="_blank"
                                   class="group relative p-2 bg-red-100 hover:bg-red-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-file-pdf text-red-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        {{ __('PDF/Imprimir') }}
                                    </span>
                                </a>
                                
                                {{-- Editar: SÓ rascunhos. Um documento fiscal emitido
                                     não pode ser alterado (Decreto 71/25) — rectifica-se
                                     por Nota de Crédito/Débito. --}}
                                @if($invoice->status === 'draft' && $invoice->invoice_status !== 'F')
                                <a href="{{ route('invoicing.sales.invoices.edit', $invoice->id) }}"
                                   class="group relative p-2 bg-blue-100 hover:bg-blue-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-edit text-blue-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        {{ __('Editar rascunho') }}
                                    </span>
                                </a>
                                @else
                                {{-- Rectificação conforme AGT --}}
                                <a href="{{ route('invoicing.credit-notes.create', ['invoice' => $invoice->id]) }}"
                                   class="group relative p-2 bg-amber-100 hover:bg-amber-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-file-circle-minus text-amber-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        {{ __('Nota de Crédito (corrigir/anular)') }}
                                    </span>
                                </a>

                                <a href="{{ route('invoicing.debit-notes.create', ['invoice' => $invoice->id]) }}"
                                   class="group relative p-2 bg-indigo-100 hover:bg-indigo-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-file-circle-plus text-indigo-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        {{ __('Nota de Débito (acrescentar)') }}
                                    </span>
                                </a>
                                @endif

                                {{-- Pagamento: a Fatura-Recibo (FR) é paga no acto da
                                     venda, por definição. Mostrar "registar
                                     pagamento" ou "marcar como pago" numa FR já
                                     liquidada é um convite a duplicar recebimentos. --}}
                                @php
                                    $__linhaFR = ($invoice->invoice_type ?? 'FT') === 'FR';
                                    $__podePagar = !$__linhaFR
                                        && !in_array($invoice->status, ['paid', 'cancelled'], true);
                                @endphp

                                @if($__podePagar)
                                <button wire:click="$dispatch('openPaymentModal', { invoiceType: 'sale', invoiceId: {{ $invoice->id }} })"
                                        class="group relative p-2 bg-gradient-to-r from-green-100 to-emerald-100 hover:from-green-600 hover:to-emerald-600 rounded-lg transition-all duration-200 transform hover:scale-110 shadow-sm">
                                    <i class="fas fa-money-bill-wave text-green-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        💰 {{ __('Registrar Pagamento') }}
                                    </span>
                                </button>
                                @endif

                                @if($__podePagar)
                                <button wire:click="markAsPaid({{ $invoice->id }})"
                                        wire:loading.attr="disabled"
                                        wire:confirm="{{ __('Marcar esta fatura como paga?') }}"
                                        class="group relative p-2 bg-green-100 hover:bg-green-600 rounded-lg transition-all duration-200 transform hover:scale-110 disabled:opacity-50">
                                    <i class="fas fa-check-circle text-green-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        {{ __('Marcar como Pago') }}
                                    </span>
                                </button>
                                @endif

                                {{-- Eliminar: SÓ rascunhos. Documento emitido anula-se
                                     por Nota de Crédito, nunca se apaga (Decreto 71/25). --}}
                                @if($invoice->status === 'draft' && $invoice->invoice_status !== 'F')
                                <button wire:click="confirmDelete({{ $invoice->id }})"
                                        wire:loading.attr="disabled"
                                        class="group relative p-2 bg-red-100 hover:bg-red-600 rounded-lg transition-all duration-200 transform hover:scale-110 disabled:opacity-50">
                                    <i class="fas fa-trash text-red-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        {{ __('Eliminar rascunho') }}
                                    </span>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center justify-center animate-pulse">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-file-invoice text-gray-300 text-4xl"></i>
                                </div>
                                {{-- ATENCAO: este texto fala de "proforma de compra"
                                     numa lista de FACTURAS DE VENDA — copia-e-cola
                                     de outro ecra. Embrulhado tal e qual para nao
                                     alterar conteudo neste lote; corrigir o
                                     portugues ANTES de mandar traduzir. --}}
                                <p class="text-gray-500 text-lg font-semibold">{{ __('Nenhuma proforma encontrada') }}</p>
                                <p class="text-gray-400 text-sm mt-2">{{ __('Crie a sua primeira proforma de compra') }}</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $invoices->links() }}
        </div>
    </div>

    {{-- Modals --}}
    @include('livewire.invoicing.faturas-venda.delete-modal')
    @include('livewire.invoicing.faturas-venda.view-modal')
    
    {{-- Payment Modal --}}
    @livewire('invoicing.payment-modal')
</div>
