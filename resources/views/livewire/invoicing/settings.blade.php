<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-cogs mr-3 text-purple-600"></i>
                    Configurações de Faturação
                </h1>
                <p class="text-gray-600 mt-2">Configure os padrões do sistema de faturação</p>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <form wire:submit.prevent="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Sidebar Tabs --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-xl p-4 sticky top-6">
                    <nav class="space-y-2">
                        <a href="#padroes" class="flex items-center px-4 py-3 text-sm font-semibold text-purple-600 bg-purple-50 rounded-xl">
                            <i class="fas fa-star mr-3"></i>
                            Padrões
                        </a>
                        <a href="#series" class="flex items-center px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 rounded-xl">
                            <i class="fas fa-hashtag mr-3"></i>
                            Séries e Numeração
                        </a>
                        <a href="#impostos" class="flex items-center px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 rounded-xl">
                            <i class="fas fa-percentage mr-3"></i>
                            Impostos
                        </a>
                        <a href="#descontos" class="flex items-center px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 rounded-xl">
                            <i class="fas fa-tags mr-3"></i>
                            Descontos
                        </a>
                        <a href="#validade" class="flex items-center px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 rounded-xl">
                            <i class="fas fa-calendar-alt mr-3"></i>
                            Validade
                        </a>
                        <a href="#impressao" class="flex items-center px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 rounded-xl">
                            <i class="fas fa-print mr-3"></i>
                            Impressão
                        </a>
                        <a href="#saft" class="flex items-center px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 rounded-xl">
                            <i class="fas fa-file-code mr-3"></i>
                            SAFT-AO
                        </a>
                        <a href="#pos" class="flex items-center px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 rounded-xl">
                            <i class="fas fa-cash-register mr-3"></i>
                            POS - Ponto de Venda
                        </a>
                    </nav>
                </div>
            </div>

            {{-- Content --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Padrões --}}
                <div id="padroes" class="bg-white rounded-2xl shadow-xl p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-star mr-2 text-purple-600"></i>
                        Padrões do Sistema
                    </h2>
                    
                    <div class="space-y-4">
                        {{-- Armazém Padrão --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-warehouse mr-1 text-blue-500"></i>
                                Armazém Padrão
                            </label>
                            <select wire:model="default_warehouse_id" 
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                <option value="">Selecione um armazém</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Cliente Padrão --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user mr-1 text-blue-500"></i>
                                Cliente Padrão (Consumidor Final)
                            </label>
                            <select wire:model="default_client_id" 
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                <option value="">Selecione um cliente</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->name }} - {{ $client->nif }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Fornecedor Padrão --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-truck mr-1 text-blue-500"></i>
                                Fornecedor Padrão
                            </label>
                            <select wire:model="default_supplier_id" 
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                <option value="">Selecione um fornecedor</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Imposto Padrão --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-percent mr-1 text-green-500"></i>
                                Imposto Padrão (IVA)
                            </label>
                            <select wire:model="default_tax_id" 
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                <option value="">Selecione um imposto</option>
                                @foreach($taxes as $tax)
                                    <option value="{{ $tax->id }}">
                                        {{ $tax->name }} - {{ $tax->rate }}% 
                                        @if($tax->is_default) ⭐ @endif
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                <a href="{{ route('invoicing.taxes') }}" class="text-purple-600 hover:underline">Gerir impostos</a>
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            {{-- Moeda Padrão --}}
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-money-bill mr-1 text-green-500"></i>
                                    Moeda Padrão
                                </label>
                                <select wire:model="default_currency" 
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                    <option value="AOA">AOA - Kwanza</option>
                                    <option value="USD">USD - Dólar</option>
                                    <option value="EUR">EUR - Euro</option>
                                </select>
                            </div>

                            {{-- Taxa de Câmbio --}}
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Taxa de Câmbio Padrão
                                </label>
                                <input type="number" step="0.0001" wire:model="default_exchange_rate" 
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                            </div>
                        </div>

                        {{-- Método de Pagamento --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-credit-card mr-1 text-purple-500"></i>
                                Método de Pagamento Padrão
                            </label>
                            <select wire:model="default_payment_method" 
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                <option value="dinheiro">Dinheiro</option>
                                <option value="transferencia">Transferência Bancária</option>
                                <option value="multicaixa">Multicaixa</option>
                                <option value="cartao">Cartão de Crédito/Débito</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Séries e Numeração --}}
                <div id="series" class="bg-white rounded-2xl shadow-xl p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-hashtag mr-2 text-purple-600"></i>
                        Séries por Tipo de Documento
                    </h2>

                    {{-- Info AGT --}}
                    <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-300 rounded-xl">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-2xl text-blue-600 mr-3 flex-shrink-0"></i>
                            <div class="text-sm">
                                <div class="font-bold text-blue-900 mb-2">Formato AGT Angola</div>
                                <div class="text-blue-800 space-y-1">
                                    <p><strong>Prefixos AGT (fixos):</strong> FT, FR, PR, RC, NC, ND</p>
                                    <p><strong>Série personalizável:</strong> A, B, C, D...</p>
                                    <p><strong>Formato:</strong> <span class="font-mono">[TIPO] [SÉRIE] [ANO]/[NÚMERO]</span></p>
                                    <p class="text-xs mt-1">Exemplo: <strong>FT A 2025/000001</strong></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Lista de Documentos --}}
                    <div class="space-y-4">
                        @php
                            $documentTypes = [
                                'proforma' => ['name' => 'Proforma de Venda', 'prefix' => 'PR', 'icon' => 'file-invoice', 'color' => 'blue'],
                                'invoice' => ['name' => 'Fatura de Venda', 'prefix' => 'FT', 'icon' => 'file-invoice-dollar', 'color' => 'green'],
                                'pos' => ['name' => 'Fatura-Recibo (POS)', 'prefix' => 'FR', 'icon' => 'cash-register', 'color' => 'emerald'],
                                'receipt' => ['name' => 'Recibo', 'prefix' => 'RC', 'icon' => 'receipt', 'color' => 'purple'],
                                'credit_note' => ['name' => 'Nota de Crédito', 'prefix' => 'NC', 'icon' => 'file-excel', 'color' => 'orange'],
                                'debit_note' => ['name' => 'Nota de Débito', 'prefix' => 'ND', 'icon' => 'file-alt', 'color' => 'red'],
                                'purchase' => ['name' => 'Fatura de Compra', 'prefix' => 'FC', 'icon' => 'shopping-cart', 'color' => 'indigo'],
                            ];
                            
                            $allSeries = \App\Models\Invoicing\InvoicingSeries::where('tenant_id', activeTenantId())
                                ->where('is_active', true)
                                ->orderBy('document_type')
                                ->orderBy('series_code')
                                ->get()
                                ->groupBy('document_type');
                        @endphp

                        @foreach($documentTypes as $type => $info)
                        <div class="border-2 border-{{ $info['color'] }}-200 rounded-xl p-4 bg-{{ $info['color'] }}-50">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-{{ $info['color'] }}-500 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-{{ $info['icon'] }} text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-{{ $info['color'] }}-900">{{ $info['name'] }}</h3>
                                        <p class="text-xs text-{{ $info['color'] }}-700">Prefixo AGT: <span class="font-mono font-bold">{{ $info['prefix'] }}</span></p>
                                    </div>
                                </div>
                                <button wire:click="openNewSeriesModal('{{ $type }}', '{{ $info['prefix'] }}')" class="px-3 py-1.5 bg-{{ $info['color'] }}-600 hover:bg-{{ $info['color'] }}-700 text-white rounded-lg text-xs font-semibold transition">
                                    <i class="fas fa-plus mr-1"></i>Nova Série
                                </button>
                            </div>

                            @php
                                $series = $allSeries[$type] ?? collect();
                            @endphp

                            @if($series->count() > 0)
                            <div class="space-y-2">
                                @foreach($series as $s)
                                <div class="bg-white border border-{{ $info['color'] }}-300 rounded-lg p-3 flex items-center justify-between">
                                    <div class="flex items-center flex-1">
                                        @if($s->is_default)
                                        <div class="px-2 py-1 bg-green-500 text-white text-xs rounded font-bold mr-3">
                                            PADRÃO
                                        </div>
                                        @endif
                                        <div class="flex-1">
                                            <div class="font-bold text-gray-900">
                                                Série: <span class="font-mono text-{{ $info['color'] }}-600">{{ $s->series_code }}</span>
                                            </div>
                                            <div class="text-xs text-gray-600 mt-1">
                                                Próximo: <span class="font-mono">{{ $s->previewNextNumber() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if(!$s->is_default)
                                        <button wire:click="setDefaultSeries({{ $s->id }})" 
                                                onclick="return confirm('Definir {{ $s->series_code }} como série padrão para {{ $info['name'] }}?')" 
                                                class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded text-xs font-semibold transition">
                                            Usar como Padrão
                                        </button>
                                        @endif
                                        <button wire:click="editSeries({{ $s->id }})" class="px-3 py-1.5 bg-{{ $info['color'] }}-100 hover:bg-{{ $info['color'] }}-200 text-{{ $info['color'] }}-700 rounded text-xs font-semibold transition">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @else
                            <div class="bg-white border-2 border-dashed border-{{ $info['color'] }}-300 rounded-lg p-4 text-center">
                                <i class="fas fa-info-circle text-{{ $info['color'] }}-400 text-2xl mb-2"></i>
                                <p class="text-sm text-{{ $info['color'] }}-700">Nenhuma série criada ainda</p>
                                <p class="text-xs text-{{ $info['color'] }}-600 mt-1">Será criada automaticamente no primeiro uso</p>
                                <p class="text-xs text-{{ $info['color'] }}-800 font-mono mt-2">{{ $info['prefix'] }} A 2025/000001</p>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Impostos --}}
                <div id="impostos" class="bg-white rounded-2xl shadow-xl p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-percentage mr-2 text-purple-600"></i>
                        Impostos e Retenções
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            {{-- Taxa IVA --}}
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-receipt mr-1 text-blue-500"></i>
                                    Taxa IVA Padrão (%)
                                </label>
                                <input type="number" step="0.01" wire:model="default_tax_rate" 
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                            </div>

                            {{-- Taxa IRT --}}
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-hand-holding-usd mr-1 text-red-500"></i>
                                    Taxa IRT Padrão (%)
                                </label>
                                <input type="number" step="0.01" wire:model="default_irt_rate" 
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                            </div>
                        </div>

                        {{-- Aplicar IRT automaticamente --}}
                        <div>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="apply_irt_services" 
                                       class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                <span class="ml-3 text-sm font-semibold text-gray-700">
                                    Aplicar IRT (6,5%) automaticamente em serviços
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Descontos --}}
                <div id="descontos" class="bg-white rounded-2xl shadow-xl p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-tags mr-2 text-purple-600"></i>
                        Política de Descontos
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="space-y-3">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="allow_line_discounts" 
                                       class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                <span class="ml-3 text-sm font-semibold text-gray-700">
                                    Permitir desconto por linha de produto
                                </span>
                            </label>

                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="allow_commercial_discount" 
                                       class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                <span class="ml-3 text-sm font-semibold text-gray-700">
                                    Permitir desconto comercial
                                </span>
                            </label>

                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="allow_financial_discount" 
                                       class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                <span class="ml-3 text-sm font-semibold text-gray-700">
                                    Permitir desconto financeiro
                                </span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Desconto Máximo Permitido (%)
                            </label>
                            <input type="number" step="0.01" wire:model="max_discount_percent" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                        </div>
                    </div>
                </div>

                {{-- Validade --}}
                <div id="validade" class="bg-white rounded-2xl shadow-xl p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-calendar-alt mr-2 text-purple-600"></i>
                        Validade e Prazos
                    </h2>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Validade Proforma (dias)
                            </label>
                            <input type="number" wire:model="proforma_validity_days" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Prazo Pagamento Fatura (dias)
                            </label>
                            <input type="number" wire:model="invoice_due_days" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                        </div>
                    </div>
                </div>

                {{-- Impressão --}}
                <div id="impressao" class="bg-white rounded-2xl shadow-xl p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-print mr-2 text-purple-600"></i>
                        Opções de Impressão
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="space-y-3">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="auto_print_after_save" 
                                       class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                <span class="ml-3 text-sm font-semibold text-gray-700">
                                    Imprimir automaticamente após salvar
                                </span>
                            </label>

                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="show_company_logo" 
                                       class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                <span class="ml-3 text-sm font-semibold text-gray-700">
                                    Mostrar logótipo da empresa
                                </span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Texto Rodapé Fatura
                            </label>
                            <textarea wire:model="invoice_footer_text" rows="3"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                      placeholder="Texto a aparecer no rodapé das faturas"></textarea>
                        </div>
                    </div>
                </div>

                {{-- SAFT-AO --}}
                <div id="saft" class="bg-white rounded-2xl shadow-xl p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-file-code mr-2 text-purple-600"></i>
                        SAFT-AO (Exportação Fiscal)
                    </h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Certificado Software AGT
                            </label>
                            <input type="text" wire:model="saft_software_cert" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="Ex: AGT/2024/XXXX">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Product ID
                            </label>
                            <input type="text" wire:model="saft_product_id" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="Ex: SOSERP/v1.0">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Versão SAFT
                            </label>
                            <input type="text" wire:model="saft_version" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="1.0.0">
                        </div>

                        <div class="mt-4 p-3 bg-yellow-50 rounded-lg text-sm text-yellow-700">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Importante:</strong> Estes dados são obrigatórios para exportação SAFT-AO conforme AGT Angola.
                        </div>
                    </div>
                </div>

                {{-- Observações Padrão --}}
                <div class="bg-white rounded-2xl shadow-xl p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-comment-alt mr-2 text-purple-600"></i>
                        Observações Padrão
                    </h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Notas Padrão
                            </label>
                            <textarea wire:model="default_notes" rows="3"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                      placeholder="Texto padrão para campo notas"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Termos e Condições Padrão
                            </label>
                            <textarea wire:model="default_terms" rows="3"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                      placeholder="Termos e condições padrão"></textarea>
                        </div>
                    </div>
                </div>

                {{-- POS - Ponto de Venda --}}
                <div id="pos" class="bg-white rounded-2xl shadow-xl p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-cash-register mr-2 text-purple-600"></i>
                        POS - Ponto de Venda
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border-2 border-emerald-200 rounded-xl p-6">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-3xl text-emerald-600"></i>
                                </div>
                                <div class="ml-4 flex-1">
                                    <h3 class="text-lg font-bold text-emerald-900 mb-2">
                                        Configurações do POS
                                    </h3>
                                    <p class="text-sm text-emerald-800 mb-2">
                                        O Ponto de Venda (POS) possui suas configurações próprias acessíveis através do menu.
                                    </p>
                                    
                                    {{-- Série Atual AGT --}}
                                    <div class="bg-white rounded-lg p-3 mb-4 border border-emerald-300">
                                        <div class="text-sm font-bold text-emerald-900 mb-1">
                                            <i class="fas fa-hashtag mr-1"></i> Série Padrão AGT:
                                        </div>
                                        <div class="text-lg font-bold text-emerald-600">
                                            FR A
                                        </div>
                                        <div class="text-xs text-gray-600 mt-1">
                                            Formato AGT Angola
                                        </div>
                                        <div class="text-xs text-gray-700 mt-2 font-mono bg-gray-50 p-2 rounded">
                                            FR A {{ date('Y') }}/000001
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            FR = Fatura-Recibo (fixo)<br>
                                            A = Série (personalizável)<br>
                                            {{ date('Y') }} = Ano atual<br>
                                            000001 = Numeração sequencial
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-3">
                                        <a href="{{ route('invoicing.pos') }}" 
                                           class="inline-flex items-center px-4 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold transition shadow-lg">
                                            <i class="fas fa-cash-register mr-2"></i>
                                            Abrir POS
                                        </a>
                                        <a href="{{ route('invoicing.pos.reports') }}" 
                                           class="inline-flex items-center px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition shadow-lg ml-3">
                                            <i class="fas fa-chart-line mr-2"></i>
                                            Relatórios POS
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                                <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                                    <i class="fas fa-cog mr-2 text-indigo-600"></i>
                                    Configurações Disponíveis
                                </h4>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle mr-2 text-green-500"></i>
                                        Sons e notificações
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle mr-2 text-green-500"></i>
                                        Aparência do grid
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle mr-2 text-green-500"></i>
                                        Configurações de impressão
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle mr-2 text-green-500"></i>
                                        Comportamento do sistema
                                    </li>
                                </ul>
                            </div>

                            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                                <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                                    <i class="fas fa-chart-bar mr-2 text-purple-600"></i>
                                    Recursos do POS
                                </h4>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle mr-2 text-green-500"></i>
                                        Validação de stock
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle mr-2 text-green-500"></i>
                                        Impressão de tickets SAFT
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle mr-2 text-green-500"></i>
                                        Integração com Treasury
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle mr-2 text-green-500"></i>
                                        Relatórios de vendas
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-4">
                            <div class="flex items-start">
                                <i class="fas fa-lightbulb text-2xl text-yellow-600 mr-3 flex-shrink-0"></i>
                                <div>
                                    <h4 class="font-bold text-yellow-900 mb-2">Dica</h4>
                                    <p class="text-sm text-yellow-800">
                                        As configurações gerais de faturação (séries, impostos, descontos, etc.) aplicam-se também ao POS. 
                                        Configure-as nesta página e ajuste preferências específicas do POS através do seu menu de configurações.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Botão Salvar --}}
                <div class="flex justify-end">
                    <x-loading-button 
                        action="save" 
                        icon="save" 
                        color="purple"
                        class="px-8 py-4 font-bold">
                        Salvar Configurações
                    </x-loading-button>
                </div>
            </div>
        </div>
    </form>

    {{-- Modal de Série --}}
    @if($showSeriesModal)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas fa-hashtag mr-2"></i>
                    {{ $editingSeriesId ? 'Editar Série' : 'Nova Série' }}
                </h3>
                <button wire:click="$set('showSeriesModal', false)" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                {{-- Prefixo AGT (fixo) --}}
                <div class="bg-blue-50 border-2 border-blue-300 rounded-xl p-4">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-blue-600 text-2xl mr-3"></i>
                        <div>
                            <p class="text-sm font-bold text-blue-900">Prefixo AGT (fixo)</p>
                            <p class="text-2xl font-bold text-blue-600 font-mono">{{ $seriesPrefix }}</p>
                            <p class="text-xs text-blue-700 mt-1">Definido pela legislação angolana</p>
                        </div>
                    </div>
                </div>

                {{-- Código da Série --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        Código da Série <span class="text-red-500">*</span>
                    </label>
                    <input type="text" wire:model="seriesCode" 
                           maxlength="10"
                           placeholder="A, B, C, etc."
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200 font-mono text-lg"
                           {{ $editingSeriesId ? 'readonly' : '' }}>
                    <p class="text-xs text-gray-500 mt-1">Exemplo: A, B, C, 01, 02, etc.</p>
                    @error('seriesCode') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- Nome (opcional) --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        Nome da Série (opcional)
                    </label>
                    <input type="text" wire:model="seriesName" 
                           placeholder="Ex: Vendas Loja, Vendas Online..."
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                    @error('seriesName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- Descrição (opcional) --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        Descrição (opcional)
                    </label>
                    <textarea wire:model="seriesDescription" 
                              rows="2"
                              placeholder="Descrição da série..."
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"></textarea>
                    @error('seriesDescription') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- Preview --}}
                <div class="bg-green-50 border-2 border-green-300 rounded-xl p-4">
                    <p class="text-sm font-bold text-green-900 mb-2">Preview do Formato:</p>
                    <p class="text-2xl font-bold text-green-600 font-mono">
                        {{ $seriesPrefix }} {{ $seriesCode }} {{ date('Y') }}/000001
                    </p>
                </div>

                {{-- Botões --}}
                <div class="flex gap-3">
                    <button wire:click="$set('showSeriesModal', false)" 
                            class="flex-1 px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-bold transition">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button wire:click="saveSeries" 
                            class="flex-1 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-xl font-bold transition">
                        <i class="fas fa-save mr-2"></i>{{ $editingSeriesId ? 'Atualizar' : 'Criar Série' }}
                    </button>
                </div>
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
