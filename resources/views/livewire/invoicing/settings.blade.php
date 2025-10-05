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
                        @if(auth()->user()->hasRole('Super Admin'))
                        <a href="#saft" class="flex items-center px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 rounded-xl">
                            <i class="fas fa-file-code mr-3"></i>
                            <span class="flex items-center">
                                SAFT-AO
                                <span class="ml-2 px-2 py-0.5 bg-red-500 text-white text-xs rounded-full">ADMIN</span>
                            </span>
                        </a>
                        @endif
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

                        {{-- Card com valores atuais --}}
                        <div class="mb-4 p-4 bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-2xl text-green-600 mr-3"></i>
                                <div class="text-sm">
                                    <div class="font-bold text-green-900 mb-2">Configurações Atuais</div>
                                    <div class="grid grid-cols-2 gap-2 text-green-800">
                                        <div><strong>Moeda:</strong> {{ $default_currency ?? 'AOA' }}</div>
                                        <div><strong>Taxa Câmbio:</strong> {{ number_format($default_exchange_rate ?? 1, 4) }}</div>
                                        <div><strong>Pagamento:</strong> {{ ucfirst($default_payment_method ?? 'dinheiro') }}</div>
                                        <div><strong>Formato:</strong> 
                                            @switch($number_format ?? 'angola')
                                                @case('angola') 🇦🇴 Angola @break
                                                @case('international') 🌍 Internacional @break
                                                @case('portugal') 🇵🇹 Portugal @break
                                                @case('brazil') 🇧🇷 Brasil @break
                                                @case('france') 🇫🇷 França @break
                                                @case('switzerland') 🇨🇭 Suíça @break
                                                @case('india') 🇮🇳 Índia @break
                                            @endswitch
                                        </div>
                                        <div><strong>Decimais:</strong> {{ $decimal_places ?? 2 }} casas</div>
                                        <div><strong>Arredondamento:</strong> {{ ucfirst($rounding_mode ?? 'normal') }}</div>
                                    </div>
                                </div>
                            </div>
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
                                    <option value="AOA">AOA - Kwanza ⭐</option>
                                    <option value="USD">USD - Dólar</option>
                                    <option value="EUR">EUR - Euro</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-star text-yellow-500 mr-1"></i>
                                    Padrão: AOA - Kwanza
                                </p>
                            </div>

                            {{-- Taxa de Câmbio --}}
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Taxa de Câmbio Padrão
                                </label>
                                <input type="number" step="0.0001" wire:model="default_exchange_rate" 
                                       placeholder="1.0000"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-star text-yellow-500 mr-1"></i>
                                    Padrão: 1.0000
                                </p>
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
                                <option value="dinheiro">💵 Dinheiro ⭐</option>
                                <option value="transferencia">🏦 Transferência Bancária</option>
                                <option value="multicaixa">💳 Multicaixa</option>
                                <option value="cartao">💳 Cartão de Crédito/Débito</option>
                                <option value="cheque">📄 Cheque</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-star text-yellow-500 mr-1"></i>
                                Padrão: Dinheiro
                            </p>
                        </div>

                        {{-- Formato de Números --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calculator mr-1 text-blue-500"></i>
                                Formato de Números e Decimais
                            </label>
                            <select wire:model="number_format" 
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                <option value="angola">🇦🇴 Angola - 20.000,00 (padrão)</option>
                                <option value="international">🌍 Internacional - 20,000.00</option>
                                <option value="portugal">🇵🇹 Portugal - 20.000,00</option>
                                <option value="brazil">🇧🇷 Brasil - 20.000,00</option>
                                <option value="france">🇫🇷 França - 20 000,00</option>
                                <option value="switzerland">🇨🇭 Suíça - 20'000.00</option>
                                <option value="india">🇮🇳 Índia - 20,000.00</option>
                            </select>
                            <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-xs text-blue-800">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong>Exemplo atual:</strong> 
                                    <span class="font-mono font-bold">
                                        @switch($number_format ?? 'angola')
                                            @case('angola')
                                                20.000,00 Kz
                                                @break
                                            @case('international')
                                                20,000.00 USD
                                                @break
                                            @case('portugal')
                                                20.000,00 €
                                                @break
                                            @case('brazil')
                                                R$ 20.000,00
                                                @break
                                            @case('france')
                                                20 000,00 €
                                                @break
                                            @case('switzerland')
                                                CHF 20'000.00
                                                @break
                                            @case('india')
                                                ₹20,000.00
                                                @break
                                        @endswitch
                                    </span>
                                </p>
                            </div>
                        </div>

                        {{-- Casas Decimais --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-hashtag mr-1 text-orange-500"></i>
                                    Casas Decimais
                                </label>
                                <select wire:model="decimal_places" 
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                    <option value="0">0 decimais - 20.000</option>
                                    <option value="1">1 decimal - 20.000,0</option>
                                    <option value="2" selected>2 decimais - 20.000,00 ⭐</option>
                                    <option value="3">3 decimais - 20.000,000</option>
                                    <option value="4">4 decimais - 20.000,0000</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-chart-line mr-1 text-cyan-500"></i>
                                    Arredondamento
                                </label>
                                <select wire:model="rounding_mode" 
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                    <option value="normal">Normal (matemático)</option>
                                    <option value="up">Sempre para cima</option>
                                    <option value="down">Sempre para baixo</option>
                                    <option value="half_up">Meio para cima (0,5+)</option>
                                </select>
                            </div>
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
                        {{-- Info sobre impostos --}}
                        <div class="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-300 rounded-xl">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-2xl text-blue-600 mr-3"></i>
                                <div class="text-sm">
                                    <div class="font-bold text-blue-900 mb-2">Gestão de Impostos</div>
                                    <div class="text-blue-800">
                                        <p class="mb-1">Os impostos são configurados e geridos na área específica.</p>
                                        <p class="text-xs">Aqui você apenas seleciona qual imposto usar como padrão em novos documentos.</p>
                                    </div>
                                    <div class="mt-3">
                                        <a href="{{ route('invoicing.taxes') }}" 
                                           class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition">
                                            <i class="fas fa-cog mr-2"></i>
                                            Gerir Impostos
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Impostos Cadastrados --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-list mr-1 text-purple-500"></i>
                                Impostos Disponíveis
                            </label>
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-xl p-4">
                                @if($taxes->count() > 0)
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        @foreach($taxes as $tax)
                                        <div class="bg-white border-2 {{ $tax->is_default ? 'border-green-400' : 'border-gray-200' }} rounded-lg p-3">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <div class="font-bold text-gray-900">
                                                        {{ $tax->name }}
                                                        @if($tax->is_default)
                                                        <span class="ml-2 px-2 py-0.5 bg-green-500 text-white text-xs rounded-full">PADRÃO</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-sm text-gray-600 mt-1">
                                                        Taxa: <span class="font-bold text-blue-600">{{ $tax->rate }}%</span>
                                                        @if($tax->description)
                                                        <span class="text-xs text-gray-500 ml-2">{{ $tax->description }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="ml-3">
                                                    @if($tax->type === 'iva')
                                                    <i class="fas fa-receipt text-blue-500 text-xl"></i>
                                                    @elseif($tax->type === 'irt')
                                                    <i class="fas fa-hand-holding-usd text-red-500 text-xl"></i>
                                                    @else
                                                    <i class="fas fa-percentage text-gray-500 text-xl"></i>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-6">
                                        <i class="fas fa-exclamation-circle text-4xl text-gray-300 mb-3"></i>
                                        <p class="text-gray-600 font-medium">Nenhum imposto cadastrado</p>
                                        <p class="text-sm text-gray-500 mt-1">Configure os impostos primeiro</p>
                                        <a href="{{ route('invoicing.taxes') }}" 
                                           class="mt-3 inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition">
                                            <i class="fas fa-plus mr-2"></i>
                                            Cadastrar Impostos
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Aplicar IRT automaticamente --}}
                        <div class="bg-yellow-50 border-2 border-yellow-300 rounded-xl p-4">
                            <label class="flex items-start cursor-pointer">
                                <input type="checkbox" wire:model="apply_irt_services" 
                                       class="w-5 h-5 mt-0.5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                <div class="ml-3">
                                    <span class="text-sm font-bold text-gray-900">
                                        Aplicar IRT automaticamente em serviços
                                    </span>
                                    <p class="text-xs text-gray-600 mt-1">
                                        Quando ativado, a retenção IRT (imposto sobre rendimento do trabalho) será aplicada automaticamente em documentos de serviços.
                                    </p>
                                </div>
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
                                <i class="fas fa-percentage mr-1 text-red-500"></i>
                                Desconto Máximo Permitido (%)
                            </label>
                            <input type="number" step="0.01" wire:model="max_discount_percent" 
                                   placeholder="100.00"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-star text-yellow-500 mr-1"></i>
                                Padrão: 100% (sem limite)
                            </p>
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
                                <i class="fas fa-calendar-check mr-1 text-blue-500"></i>
                                Validade Proforma (dias)
                            </label>
                            <input type="number" wire:model="proforma_validity_days" 
                                   placeholder="30"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-star text-yellow-500 mr-1"></i>
                                Padrão: 30 dias
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-clock mr-1 text-orange-500"></i>
                                Prazo Pagamento Fatura (dias)
                            </label>
                            <input type="number" wire:model="invoice_due_days" 
                                   placeholder="30"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-star text-yellow-500 mr-1"></i>
                                Padrão: 30 dias
                            </p>
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

                {{-- SAFT-AO - APENAS SUPER ADMIN --}}
                @if(auth()->user()->hasRole('Super Admin'))
                <div id="saft" class="bg-white rounded-2xl shadow-xl p-6 border-2 border-red-200">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-file-code mr-2 text-purple-600"></i>
                            SAFT-AO (Exportação Fiscal)
                        </h2>
                        <span class="px-3 py-1 bg-red-500 text-white text-xs font-bold rounded-full">
                            <i class="fas fa-shield-alt mr-1"></i>
                            APENAS SUPER ADMIN
                        </span>
                    </div>
                    
                    <div class="mb-4 p-4 bg-gradient-to-r from-red-50 to-orange-50 border-2 border-red-300 rounded-xl">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-2xl text-red-600 mr-3"></i>
                            <div class="text-sm">
                                <div class="font-bold text-red-900 mb-2">Área Restrita</div>
                                <div class="text-red-800">
                                    <p class="mb-1">Estas configurações são críticas para o funcionamento fiscal do sistema.</p>
                                    <p class="text-xs">Apenas Super Admin pode visualizar e modificar estes dados.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-certificate mr-1 text-green-500"></i>
                                Certificado Software AGT
                            </label>
                            <input type="text" wire:model="saft_software_cert" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="Ex: AGT/2024/XXXX">
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Certificado emitido pela AGT Angola
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag mr-1 text-blue-500"></i>
                                Product ID
                            </label>
                            <input type="text" wire:model="saft_product_id" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="Ex: SOSERP/v1.0">
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Identificador único do software
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-code-branch mr-1 text-purple-500"></i>
                                Versão SAFT
                            </label>
                            <input type="text" wire:model="saft_version" 
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="1.0.0">
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-star text-yellow-500 mr-1"></i>
                                Padrão: 1.0.0 (Formato AGT Angola)
                            </p>
                        </div>

                        <div class="p-4 bg-yellow-50 border-2 border-yellow-300 rounded-xl text-sm text-yellow-800">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-xl text-yellow-600 mr-2"></i>
                                <div>
                                    <div class="font-bold mb-1">Importante - AGT Angola</div>
                                    <p>Estes dados são obrigatórios para exportação SAFT-AO conforme regulamentação da AGT Angola.</p>
                                    <p class="text-xs mt-2">Alterações incorretas podem invalidar a exportação fiscal.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

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
                    
                    <div class="space-y-6">
                        {{-- Info Card --}}
                        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border-2 border-emerald-200 rounded-xl p-4">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-2xl text-emerald-600 mr-3"></i>
                                <div class="text-sm">
                                    <div class="font-bold text-emerald-900 mb-1">Configurações do POS</div>
                                    <p class="text-emerald-800">Configure o comportamento e aparência do Ponto de Venda.</p>
                                    
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
                                    
                                </div>
                            </div>
                        </div>

                        {{-- Comportamento do Sistema --}}
                        <div>
                            <h3 class="font-bold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-cog mr-2 text-indigo-600"></i>
                                Comportamento do Sistema
                            </h3>
                            <div class="space-y-3 bg-gray-50 rounded-xl p-4">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="pos_auto_print" 
                                           class="w-5 h-5 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                    <span class="ml-3 text-sm font-semibold text-gray-700">
                                        <i class="fas fa-print mr-1 text-blue-500"></i>
                                        Imprimir automaticamente após venda
                                    </span>
                                </label>

                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="pos_play_sounds" 
                                           class="w-5 h-5 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                    <span class="ml-3 text-sm font-semibold text-gray-700">
                                        <i class="fas fa-volume-up mr-1 text-green-500"></i>
                                        Reproduzir sons e notificações
                                    </span>
                                </label>

                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="pos_auto_complete_sale" 
                                           class="w-5 h-5 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                    <span class="ml-3 text-sm font-semibold text-gray-700">
                                        <i class="fas fa-check-circle mr-1 text-emerald-500"></i>
                                        Completar venda automaticamente após pagamento
                                    </span>
                                </label>

                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="pos_require_customer" 
                                           class="w-5 h-5 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                    <span class="ml-3 text-sm font-semibold text-gray-700">
                                        <i class="fas fa-user-check mr-1 text-orange-500"></i>
                                        Exigir cliente em todas as vendas
                                    </span>
                                </label>
                            </div>
                        </div>

                        {{-- Gestão de Stock --}}
                        <div>
                            <h3 class="font-bold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-boxes mr-2 text-purple-600"></i>
                                Gestão de Stock
                            </h3>
                            <div class="space-y-3 bg-gray-50 rounded-xl p-4">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="pos_validate_stock" 
                                           class="w-5 h-5 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                    <span class="ml-3 text-sm font-semibold text-gray-700">
                                        <i class="fas fa-box-open mr-1 text-cyan-500"></i>
                                        Validar disponibilidade de stock
                                    </span>
                                </label>

                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="pos_allow_negative_stock" 
                                           class="w-5 h-5 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                    <span class="ml-3 text-sm font-semibold text-gray-700">
                                        <i class="fas fa-exclamation-triangle mr-1 text-red-500"></i>
                                        Permitir stock negativo
                                    </span>
                                </label>
                                <p class="text-xs text-gray-500 ml-8">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Permitir vender produtos mesmo com stock zero ou negativo
                                </p>
                            </div>
                        </div>

                        {{-- Aparência e Interface --}}
                        <div>
                            <h3 class="font-bold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-palette mr-2 text-pink-600"></i>
                                Aparência e Interface
                            </h3>
                            <div class="space-y-4 bg-gray-50 rounded-xl p-4">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="pos_show_product_images" 
                                           class="w-5 h-5 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                    <span class="ml-3 text-sm font-semibold text-gray-700">
                                        <i class="fas fa-image mr-1 text-blue-500"></i>
                                        Mostrar imagens dos produtos
                                    </span>
                                </label>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-th mr-1 text-indigo-500"></i>
                                        Produtos por página
                                    </label>
                                    <select wire:model="pos_products_per_page" 
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                                        <option value="8">8 produtos</option>
                                        <option value="12">12 produtos ⭐</option>
                                        <option value="16">16 produtos</option>
                                        <option value="20">20 produtos</option>
                                        <option value="24">24 produtos</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-star text-yellow-500 mr-1"></i>
                                        Padrão: 12 produtos por página
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Método de Pagamento Padrão --}}
                        <div>
                            <h3 class="font-bold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-money-bill-wave mr-2 text-green-600"></i>
                                Pagamento
                            </h3>
                            <div class="bg-gray-50 rounded-xl p-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-credit-card mr-1 text-purple-500"></i>
                                    Método de Pagamento Padrão no POS
                                </label>
                                @if($paymentMethods->count() > 0)
                                    <select wire:model="pos_default_payment_method_id" 
                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                                        <option value="">Selecione...</option>
                                        @foreach($paymentMethods as $method)
                                            <option value="{{ $method->id }}">
                                                @if($method->icon)
                                                    <i class="{{ $method->icon }} mr-1"></i>
                                                @endif
                                                {{ $method->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-gray-500 mt-2">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Métodos de pagamento geridos na <a href="{{ route('treasury.payment-methods') }}" class="text-purple-600 hover:underline font-semibold">Tesouraria</a>
                                    </p>
                                @else
                                    <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        Nenhum método de pagamento cadastrado.
                                        <a href="{{ route('treasury.payment-methods') }}" class="font-bold underline ml-1">Cadastre na Tesouraria</a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Links Rápidos --}}
                        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border-2 border-emerald-200 rounded-xl p-4">
                            <h3 class="font-bold text-emerald-900 mb-3 flex items-center">
                                <i class="fas fa-external-link-alt mr-2"></i>
                                Links Rápidos
                            </h3>
                            <div class="flex flex-wrap gap-3">
                                <a href="{{ route('invoicing.pos') }}" 
                                   class="inline-flex items-center px-4 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold transition shadow-lg hover:scale-105 transform">
                                    <i class="fas fa-cash-register mr-2"></i>
                                    Abrir POS
                                </a>
                                <a href="{{ route('invoicing.pos.reports') }}" 
                                   class="inline-flex items-center px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition shadow-lg hover:scale-105 transform">
                                    <i class="fas fa-chart-line mr-2"></i>
                                    Relatórios POS
                                </a>
                            </div>
                        </div>

                        {{-- Dica --}}
                        <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-4">
                            <div class="flex items-start">
                                <i class="fas fa-lightbulb text-2xl text-yellow-600 mr-3 flex-shrink-0"></i>
                                <div>
                                    <h4 class="font-bold text-yellow-900 mb-2">Dica</h4>
                                    <p class="text-sm text-yellow-800">
                                        As configurações gerais de faturação (séries, impostos, descontos, etc.) aplicam-se também ao POS. 
                                        Configure-as acima e salve para aplicar as mudanças.
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
