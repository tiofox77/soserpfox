<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mr-4">
                    <i class="fas fa-file-code text-3xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-bold">Gerador SAFT-AO</h2>
                    <p class="text-purple-100 text-sm mt-1">Standard Audit File for Tax - Angola (AGT)</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm text-purple-200">Versão SAFT</p>
                <p class="text-2xl font-bold">1.01_01</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Configurações --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-cog mr-2 text-purple-600"></i>
                    Configurações da Exportação
                </h3>

                {{-- Período --}}
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt mr-1"></i>Data Início
                        </label>
                        <input type="date" wire:model.live="startDate" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt mr-1"></i>Data Fim
                        </label>
                        <input type="date" wire:model.live="endDate" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                {{-- Tipo de Documento --}}
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-3">
                        <i class="fas fa-filter mr-2 text-purple-600"></i>Filtrar por Tipo de Documento
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="relative cursor-pointer">
                            <input type="radio" wire:model.live="documentType" value="all" class="peer sr-only">
                            <div class="px-4 py-4 border-2 rounded-xl transition-all peer-checked:border-purple-600 peer-checked:bg-purple-50 peer-checked:shadow-lg hover:border-purple-400 hover:shadow-md">
                                <div class="flex flex-col items-center text-center">
                                    <i class="fas fa-file-invoice text-2xl mb-2 text-purple-600"></i>
                                    <span class="font-bold text-sm">Todos</span>
                                    <span class="text-xs text-gray-500 mt-1">Vendas + Compras</span>
                                </div>
                            </div>
                        </label>
                        
                        <label class="relative cursor-pointer">
                            <input type="radio" wire:model.live="documentType" value="sales" class="peer sr-only">
                            <div class="px-4 py-4 border-2 rounded-xl transition-all peer-checked:border-green-600 peer-checked:bg-green-50 peer-checked:shadow-lg hover:border-green-400 hover:shadow-md">
                                <div class="flex flex-col items-center text-center">
                                    <i class="fas fa-shopping-cart text-2xl mb-2 text-green-600"></i>
                                    <span class="font-bold text-sm">Vendas</span>
                                    <span class="text-xs text-gray-500 mt-1">Faturas emitidas</span>
                                </div>
                            </div>
                        </label>
                        
                        <label class="relative cursor-pointer">
                            <input type="radio" wire:model.live="documentType" value="purchases" class="peer sr-only">
                            <div class="px-4 py-4 border-2 rounded-xl transition-all peer-checked:border-orange-600 peer-checked:bg-orange-50 peer-checked:shadow-lg hover:border-orange-400 hover:shadow-md">
                                <div class="flex flex-col items-center text-center">
                                    <i class="fas fa-truck text-2xl mb-2 text-orange-600"></i>
                                    <span class="font-bold text-sm">Compras</span>
                                    <span class="text-xs text-gray-500 mt-1">Faturas recebidas</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Opções de Inclusão --}}
                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                    <p class="text-sm font-bold text-gray-700 mb-3">
                        <i class="fas fa-list-check mr-1"></i>Dados a Incluir
                    </p>
                    <div class="space-y-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="includeCustomers" 
                                   class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                            <span class="ml-3 text-sm text-gray-700">
                                <i class="fas fa-users text-blue-600 mr-1"></i>Clientes
                            </span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="includeSuppliers" 
                                   class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                            <span class="ml-3 text-sm text-gray-700">
                                <i class="fas fa-truck text-orange-600 mr-1"></i>Fornecedores
                            </span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="includeProducts" 
                                   class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                            <span class="ml-3 text-sm text-gray-700">
                                <i class="fas fa-box text-green-600 mr-1"></i>Produtos e Serviços
                            </span>
                        </label>
                    </div>
                </div>

                {{-- Botão Gerar --}}
                <div class="flex gap-3">
                    <button wire:click="generateSAFT" 
                            wire:loading.attr="disabled"
                            class="flex-1 px-6 py-4 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-xl font-bold text-lg shadow-lg transition">
                        <span wire:loading.remove wire:target="generateSAFT">
                            <i class="fas fa-download mr-2"></i>Gerar e Baixar SAFT-AO
                        </span>
                        <span wire:loading wire:target="generateSAFT">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Gerando XML...
                        </span>
                    </button>
                </div>
            </div>

            {{-- Informações SAFT --}}
            <div class="mt-6 bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                <h4 class="text-sm font-bold text-blue-800 mb-2">
                    <i class="fas fa-info-circle mr-1"></i>Sobre o SAFT-AO
                </h4>
                <ul class="text-xs text-blue-700 space-y-1">
                    <li>• SAFT (Standard Audit File for Tax) é obrigatório em Angola</li>
                    <li>• Formato XML conforme AGT (Administração Geral Tributária)</li>
                    <li>• Contém todos os documentos fiscais do período selecionado</li>
                    <li>• Inclui clientes, fornecedores, produtos e transações</li>
                    <li>• Deve ser entregue mensalmente à AGT</li>
                </ul>
            </div>
        </div>

        {{-- Estatísticas --}}
        <div class="space-y-6">
            {{-- Card Total Documentos --}}
            <div class="bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="text-purple-100 text-sm font-semibold">DOCUMENTOS</p>
                        @if($documentType === 'sales')
                            <span class="inline-flex items-center px-2 py-1 bg-green-500/80 rounded-lg text-xs font-bold mt-1">
                                <i class="fas fa-shopping-cart mr-1"></i> Vendas
                            </span>
                        @elseif($documentType === 'purchases')
                            <span class="inline-flex items-center px-2 py-1 bg-orange-500/80 rounded-lg text-xs font-bold mt-1">
                                <i class="fas fa-truck mr-1"></i> Compras
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 bg-white/20 rounded-lg text-xs font-bold mt-1">
                                <i class="fas fa-file-invoice mr-1"></i> Todos
                            </span>
                        @endif
                    </div>
                    <i class="fas fa-file-invoice text-3xl text-white/30"></i>
                </div>
                <p class="text-4xl font-bold">{{ number_format($totalInvoices) }}</p>
                <p class="text-xs text-purple-200 mt-1">no período selecionado</p>
            </div>

            {{-- Card Valor Total --}}
            <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-green-100 text-sm font-semibold">VALOR TOTAL</p>
                    <i class="fas fa-coins text-3xl text-white/30"></i>
                </div>
                <p class="text-4xl font-bold">{{ number_format($totalValue, 2) }}</p>
                <p class="text-xs text-green-200 mt-1">AOA</p>
            </div>

            {{-- Grid de Stats --}}
            <div class="grid grid-cols-3 gap-3">
                {{-- Clientes --}}
                <div class="bg-white rounded-xl shadow p-4 text-center">
                    <i class="fas fa-users text-2xl text-blue-600 mb-2"></i>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($totalCustomers) }}</p>
                    <p class="text-xs text-gray-600">Clientes</p>
                </div>

                {{-- Fornecedores --}}
                <div class="bg-white rounded-xl shadow p-4 text-center">
                    <i class="fas fa-truck text-2xl text-orange-600 mb-2"></i>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($totalSuppliers) }}</p>
                    <p class="text-xs text-gray-600">Fornecedores</p>
                </div>

                {{-- Produtos --}}
                <div class="bg-white rounded-xl shadow p-4 text-center">
                    <i class="fas fa-box text-2xl text-green-600 mb-2"></i>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($totalProducts) }}</p>
                    <p class="text-xs text-gray-600">Produtos</p>
                </div>
            </div>

            {{-- Estrutura XML --}}
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h4 class="text-sm font-bold text-gray-800 mb-3">
                    <i class="fas fa-code mr-1 text-purple-600"></i>Estrutura do XML
                </h4>
                <div class="space-y-2 text-xs">
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-purple-600 mr-2 text-xs"></i>
                        <span class="text-gray-700">Header (Cabeçalho)</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-purple-600 mr-2 text-xs"></i>
                        <span class="text-gray-700">MasterFiles (Mestres)</span>
                    </div>
                    <div class="pl-6 space-y-1">
                        @if($includeCustomers)
                        <div class="flex items-center text-blue-600">
                            <i class="fas fa-dot-circle mr-2 text-xs"></i>
                            <span>Customers</span>
                        </div>
                        @endif
                        @if($includeSuppliers)
                        <div class="flex items-center text-orange-600">
                            <i class="fas fa-dot-circle mr-2 text-xs"></i>
                            <span>Suppliers</span>
                        </div>
                        @endif
                        @if($includeProducts)
                        <div class="flex items-center text-green-600">
                            <i class="fas fa-dot-circle mr-2 text-xs"></i>
                            <span>Products</span>
                        </div>
                        @endif
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-purple-600 mr-2 text-xs"></i>
                        <span class="text-gray-700">SourceDocuments (Documentos)</span>
                    </div>
                    <div class="pl-6 space-y-1">
                        @if($documentType === 'all' || $documentType === 'sales')
                        <div class="flex items-center text-indigo-600">
                            <i class="fas fa-dot-circle mr-2 text-xs"></i>
                            <span>SalesInvoices</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Avisos --}}
            <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-4">
                <h4 class="text-sm font-bold text-yellow-800 mb-2">
                    <i class="fas fa-exclamation-triangle mr-1"></i>Importante
                </h4>
                <ul class="text-xs text-yellow-700 space-y-1">
                    <li>✓ Verifique os dados antes de enviar</li>
                    <li>✓ Guarde uma cópia do arquivo</li>
                    <li>✓ Envie dentro do prazo legal</li>
                    <li>✓ Certifique o software (em breve)</li>
                </ul>
            </div>
        </div>
    </div>
</div>
