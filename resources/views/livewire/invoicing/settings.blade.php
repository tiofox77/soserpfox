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
                        Séries e Numeração
                    </h2>
                    
                    <div class="grid grid-cols-2 gap-4">
                        {{-- Proforma --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Série Proforma</label>
                            <input type="text" wire:model="proforma_series" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="PRF">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Próximo Número</label>
                            <input type="number" wire:model="proforma_next_number" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                        </div>

                        {{-- Fatura --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Série Fatura</label>
                            <input type="text" wire:model="invoice_series" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="FT">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Próximo Número</label>
                            <input type="number" wire:model="invoice_next_number" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                        </div>

                        {{-- Recibo --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Série Recibo</label>
                            <input type="text" wire:model="receipt_series" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="RC">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Próximo Número</label>
                            <input type="number" wire:model="receipt_next_number" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-blue-50 rounded-lg text-sm text-blue-700">
                        <i class="fas fa-info-circle mr-2"></i>
                        Exemplo: PRF 000001, FT 000001, RC 000001
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
