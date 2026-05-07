<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header com Gradiente --}}
        <div class="mb-6 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mr-4">
                        <i class="fas fa-file-code text-3xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold">Gerador SAFT-AO</h2>
                        <p class="text-purple-200 text-sm mt-1">Standard Audit File for Tax — Angola (AGT)</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-xs text-purple-300">Versão SAFT</p>
                    <p class="text-2xl font-black">1.01_01</p>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
            <div class="bg-white rounded-2xl shadow-lg p-4 border border-purple-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                        <i class="fas fa-file-invoice text-purple-600 text-xs"></i>
                    </div>
                    <span class="text-lg font-black text-purple-600">{{ number_format($totalInvoices) }}</span>
                </div>
                <p class="text-[10px] text-gray-500 font-semibold">Faturas</p>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-4 border border-red-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                        <i class="fas fa-file-invoice-dollar text-red-500 text-xs"></i>
                    </div>
                    <span class="text-lg font-black text-red-500">{{ number_format($totalCreditNotes) }}</span>
                </div>
                <p class="text-[10px] text-gray-500 font-semibold">Notas Crédito</p>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-4 border border-orange-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                        <i class="fas fa-file-alt text-orange-500 text-xs"></i>
                    </div>
                    <span class="text-lg font-black text-orange-500">{{ number_format($totalDebitNotes) }}</span>
                </div>
                <p class="text-[10px] text-gray-500 font-semibold">Notas Débito</p>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-4 border border-green-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                        <i class="fas fa-receipt text-green-600 text-xs"></i>
                    </div>
                    <span class="text-lg font-black text-green-600">{{ number_format($totalReceipts) }}</span>
                </div>
                <p class="text-[10px] text-gray-500 font-semibold">Recibos</p>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-4 border border-cyan-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 rounded-lg bg-cyan-100 flex items-center justify-center">
                        <i class="fas fa-exchange-alt text-cyan-600 text-xs"></i>
                    </div>
                    <span class="text-lg font-black text-cyan-600">{{ number_format($totalMovements) }}</span>
                </div>
                <p class="text-[10px] text-gray-500 font-semibold">Mov. Stock</p>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-4 border border-blue-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-xs"></i>
                    </div>
                    <span class="text-lg font-black text-blue-600">{{ number_format($totalCustomers) }}</span>
                </div>
                <p class="text-[10px] text-gray-500 font-semibold">Clientes</p>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-4 border border-indigo-100">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                        <i class="fas fa-box text-indigo-600 text-xs"></i>
                    </div>
                    <span class="text-lg font-black text-indigo-600">{{ number_format($totalProducts) }}</span>
                </div>
                <p class="text-[10px] text-gray-500 font-semibold">Produtos</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Configurações --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-700 to-gray-900 px-6 py-4">
                        <h3 class="text-white font-bold text-sm flex items-center">
                            <i class="fas fa-cog mr-2"></i> Configurações da Exportação
                        </h3>
                    </div>

                    <div class="p-6">
                        {{-- Período --}}
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <i class="fas fa-calendar-alt mr-1 text-gray-400"></i> Data Início
                                </label>
                                <input type="date" wire:model.live="startDate"
                                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-400 focus:border-transparent bg-gray-50 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <i class="fas fa-calendar-alt mr-1 text-gray-400"></i> Data Fim
                                </label>
                                <input type="date" wire:model.live="endDate"
                                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-400 focus:border-transparent bg-gray-50 transition">
                            </div>
                        </div>

                        {{-- Tipo de Documento --}}
                        <div class="mb-6">
                            <label class="block text-xs font-semibold text-gray-600 mb-3">
                                <i class="fas fa-filter mr-1 text-gray-400"></i> Filtrar por Tipo
                            </label>
                            <div class="grid grid-cols-4 gap-3">
                                <label class="relative cursor-pointer">
                                    <input type="radio" wire:model.live="documentType" value="all" class="peer sr-only">
                                    <div class="px-3 py-3 border-2 rounded-xl transition-all peer-checked:border-purple-500 peer-checked:bg-purple-50 peer-checked:shadow-lg hover:border-purple-300 hover:shadow-md text-center">
                                        <i class="fas fa-file-invoice text-xl mb-1 text-purple-600"></i>
                                        <p class="font-bold text-xs">Todos</p>
                                        <p class="text-[10px] text-gray-400">Completo</p>
                                    </div>
                                </label>
                                <label class="relative cursor-pointer">
                                    <input type="radio" wire:model.live="documentType" value="sales" class="peer sr-only">
                                    <div class="px-3 py-3 border-2 rounded-xl transition-all peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:shadow-lg hover:border-green-300 hover:shadow-md text-center">
                                        <i class="fas fa-shopping-cart text-xl mb-1 text-green-600"></i>
                                        <p class="font-bold text-xs">Vendas</p>
                                        <p class="text-[10px] text-gray-400">Faturas</p>
                                    </div>
                                </label>
                                <label class="relative cursor-pointer">
                                    <input type="radio" wire:model.live="documentType" value="purchases" class="peer sr-only">
                                    <div class="px-3 py-3 border-2 rounded-xl transition-all peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:shadow-lg hover:border-orange-300 hover:shadow-md text-center">
                                        <i class="fas fa-truck text-xl mb-1 text-orange-600"></i>
                                        <p class="font-bold text-xs">Compras</p>
                                        <p class="text-[10px] text-gray-400">Fornecedores</p>
                                    </div>
                                </label>
                                <label class="relative cursor-pointer">
                                    <input type="radio" wire:model.live="documentType" value="inventory" class="peer sr-only">
                                    <div class="px-3 py-3 border-2 rounded-xl transition-all peer-checked:border-cyan-500 peer-checked:bg-cyan-50 peer-checked:shadow-lg hover:border-cyan-300 hover:shadow-md text-center">
                                        <i class="fas fa-boxes text-xl mb-1 text-cyan-600"></i>
                                        <p class="font-bold text-xs">Inventário</p>
                                        <p class="text-[10px] text-gray-400">Mov. Stock</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {{-- Dados a Incluir --}}
                        <div class="mb-6">
                            <label class="block text-xs font-semibold text-gray-600 mb-3">
                                <i class="fas fa-list-check mr-1 text-gray-400"></i> Dados a Incluir
                            </label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                @php
                                    $options = [
                                        ['prop' => 'includeTaxTable', 'label' => 'Tabela Impostos', 'icon' => 'percent', 'color' => 'purple'],
                                        ['prop' => 'includeCustomers', 'label' => 'Clientes', 'icon' => 'users', 'color' => 'blue'],
                                        ['prop' => 'includeSuppliers', 'label' => 'Fornecedores', 'icon' => 'truck', 'color' => 'orange'],
                                        ['prop' => 'includeProducts', 'label' => 'Produtos', 'icon' => 'box', 'color' => 'green'],
                                        ['prop' => 'includeCreditNotes', 'label' => 'Notas Crédito', 'icon' => 'file-invoice-dollar', 'color' => 'red'],
                                        ['prop' => 'includeDebitNotes', 'label' => 'Notas Débito', 'icon' => 'file-alt', 'color' => 'yellow'],
                                        ['prop' => 'includePayments', 'label' => 'Recibos', 'icon' => 'receipt', 'color' => 'emerald'],
                                        ['prop' => 'includeStockMovements', 'label' => 'Mov. Stock', 'icon' => 'exchange-alt', 'color' => 'cyan'],
                                    ];
                                @endphp

                                @foreach($options as $opt)
                                <div class="border rounded-xl p-3 transition hover:shadow-md cursor-pointer
                                    {{ $this->{$opt['prop']} ? 'border-'.$opt['color'].'-200 bg-'.$opt['color'].'-50/50' : 'border-gray-200 bg-white' }}">
                                    <label class="flex items-center justify-between cursor-pointer">
                                        <div class="flex items-center">
                                            <i class="fas fa-{{ $opt['icon'] }} text-{{ $opt['color'] }}-500 text-xs mr-2"></i>
                                            <span class="text-xs font-semibold text-gray-700">{{ $opt['label'] }}</span>
                                        </div>
                                        <input type="checkbox" wire:model="{{ $opt['prop'] }}"
                                               class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500 border-gray-300">
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Botão Gerar --}}
                        <button wire:click="generateSAFT"
                                wire:loading.attr="disabled"
                                wire:target="generateSAFT"
                                class="w-full px-6 py-4 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-xl font-bold text-base shadow-lg shadow-purple-200 transition disabled:opacity-50">
                            <span wire:loading.remove wire:target="generateSAFT">
                                <i class="fas fa-download mr-2"></i> Gerar e Baixar SAFT-AO
                            </span>
                            <span wire:loading wire:target="generateSAFT">
                                <i class="fas fa-spinner fa-spin mr-2"></i> A gerar XML...
                            </span>
                        </button>
                    </div>
                </div>

                {{-- Info SAFT --}}
                <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5">
                    <h4 class="text-xs font-bold text-blue-800 mb-2 flex items-center">
                        <i class="fas fa-info-circle mr-2"></i> Sobre o SAFT-AO
                    </h4>
                    <div class="text-xs text-blue-700 space-y-1 leading-relaxed">
                        <p>• SAFT (Standard Audit File for Tax) é obrigatório em Angola conforme Decreto 71/25</p>
                        <p>• Formato XML conforme AGT (Administração Geral Tributária)</p>
                        <p>• Inclui documentos fiscais, recibos, movimentos de stock e dados mestre</p>
                        <p>• Deve ser entregue à AGT conforme prazo legal</p>
                    </div>
                </div>
            </div>

            {{-- Sidebar Direita --}}
            <div class="space-y-6">
                {{-- Card Valor Total --}}
                <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-green-100 text-xs font-semibold">VALOR TOTAL</p>
                        <i class="fas fa-coins text-2xl text-white/30"></i>
                    </div>
                    <p class="text-3xl font-black">{{ number_format($totalValue, 2, ',', '.') }}</p>
                    <p class="text-xs text-green-200 mt-1">AOA no período</p>
                </div>

                {{-- Estrutura XML --}}
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-gray-100">
                        <h4 class="text-xs font-bold text-gray-700 flex items-center">
                            <i class="fas fa-code mr-2 text-purple-500"></i> Estrutura do XML
                        </h4>
                    </div>
                    <div class="p-4 space-y-2 text-xs">
                        {{-- Header --}}
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-purple-500 mr-2 text-[10px]"></i>
                            <span class="font-bold text-gray-700">Header</span>
                        </div>

                        {{-- MasterFiles --}}
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-purple-500 mr-2 text-[10px]"></i>
                            <span class="font-bold text-gray-700">MasterFiles</span>
                        </div>
                        <div class="pl-5 space-y-1">
                            @if($includeTaxTable)
                            <div class="flex items-center text-purple-600">
                                <i class="fas fa-circle text-[5px] mr-2"></i>
                                <span>TaxTable</span>
                                <span class="ml-auto text-[10px] text-green-500 font-bold">NOVO</span>
                            </div>
                            @endif
                            @if($includeCustomers)
                            <div class="flex items-center text-blue-600">
                                <i class="fas fa-circle text-[5px] mr-2"></i><span>Customers</span>
                            </div>
                            @endif
                            @if($includeSuppliers)
                            <div class="flex items-center text-orange-600">
                                <i class="fas fa-circle text-[5px] mr-2"></i><span>Suppliers</span>
                            </div>
                            @endif
                            @if($includeProducts)
                            <div class="flex items-center text-green-600">
                                <i class="fas fa-circle text-[5px] mr-2"></i><span>Products</span>
                            </div>
                            @endif
                        </div>

                        {{-- SourceDocuments --}}
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-purple-500 mr-2 text-[10px]"></i>
                            <span class="font-bold text-gray-700">SourceDocuments</span>
                        </div>
                        <div class="pl-5 space-y-1">
                            @if($documentType === 'all' || $documentType === 'sales')
                            <div class="flex items-center text-indigo-600">
                                <i class="fas fa-circle text-[5px] mr-2"></i>
                                <span>SalesInvoices</span>
                                <span class="text-[10px] text-gray-400 ml-1">(FT</span>
                                @if($includeCreditNotes)<span class="text-[10px] text-gray-400">+NC</span>@endif
                                @if($includeDebitNotes)<span class="text-[10px] text-gray-400">+ND</span>@endif
                                <span class="text-[10px] text-gray-400">)</span>
                            </div>
                            @endif
                            @if($includePayments)
                            <div class="flex items-center text-emerald-600">
                                <i class="fas fa-circle text-[5px] mr-2"></i>
                                <span>Payments</span>
                                <span class="ml-auto text-[10px] text-green-500 font-bold">NOVO</span>
                            </div>
                            @endif
                            @if($includeStockMovements)
                            <div class="flex items-center text-cyan-600">
                                <i class="fas fa-circle text-[5px] mr-2"></i>
                                <span>MovementOfGoods</span>
                                <span class="ml-auto text-[10px] text-green-500 font-bold">NOVO</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Correções Aplicadas --}}
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-gray-100">
                        <h4 class="text-xs font-bold text-gray-700 flex items-center">
                            <i class="fas fa-bug mr-2 text-red-500"></i> Correções SAFT-AO
                        </h4>
                    </div>
                    <div class="p-4 space-y-2">
                        @php
                            $fixes = [
                                'InvoiceStatus dinâmico',
                                'InvoiceType do documento',
                                'SourceBilling do campo',
                                'SourceID = nome utilizador',
                                'Hash do Decreto 71/25',
                                'HashControl obrigatório',
                                'ATCUD incluído',
                                'SystemEntryDate correto',
                                'TaxCode dinâmico',
                                'SpecialRegimes',
                                'TaxTable nos MasterFiles',
                                'NC + ND nos SalesInvoices',
                                'Payments (Recibos)',
                                'MovementOfGoods (Stock)',
                                'Currency no DocumentTotals',
                                'TaxExemptionReason',
                            ];
                        @endphp
                        @foreach($fixes as $fix)
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 text-xs mr-2"></i>
                            <span class="text-[10px] text-gray-600">{{ $fix }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Avisos --}}
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4">
                    <h4 class="text-xs font-bold text-amber-800 mb-2 flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Importante
                    </h4>
                    <div class="text-[10px] text-amber-700 space-y-1">
                        <p>• Verifique os dados antes de enviar à AGT</p>
                        <p>• Guarde uma cópia do arquivo XML</p>
                        <p>• Envie dentro do prazo legal</p>
                        <p>• Certifique-se de que as chaves RSA estão configuradas</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
