@if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        
        <div class="flex items-start sm:items-center justify-center min-h-screen p-2 sm:p-4 text-center">
            <div class="relative w-full bg-white rounded-2xl text-left shadow-2xl transform transition-all my-4 sm:my-8 sm:max-w-6xl max-h-[94vh] overflow-y-auto">
                <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-4 sm:px-6 py-3 sm:py-4 sticky top-0 z-10">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg sm:text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-box mr-2 sm:mr-3"></i>{{ $editingProductId ? 'Editar' : 'Novo' }} Produto
                        </h3>
                        <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <form wire:submit.prevent="save" class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag text-purple-500 mr-2"></i>{{ __('Nome *') }}
                            </label>
                            <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                            @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-hashtag text-blue-500 mr-2"></i>Código 
                                @if(!$editingProductId)
                                    <span class="text-xs text-green-600 font-normal">
                                        <i class="fas fa-check-circle"></i> {{ __('(Gerado automaticamente - editável)') }}
                                    </span>
                                @endif
                            </label>
                            <div class="relative">
                                <input wire:model="code" 
                                       type="text" 
                                       placeholder="{{ __('Ex: PROD000001') }}"
                                       class="w-full px-4 py-2.5 border-2 {{ $editingProductId ? 'border-blue-300 bg-blue-50' : 'border-green-300 bg-green-50' }} rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition font-mono font-semibold">
                                @if(!$editingProductId)
                                    <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                        <span class="text-xs bg-green-600 text-white px-2 py-1 rounded-full font-semibold">
                                            <i class="fas fa-magic"></i> AUTO
                                        </span>
                                    </div>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                {{ $editingProductId ? 'Você pode editar o código do produto' : 'Código sugerido automaticamente, mas você pode alterá-lo' }}
                            </p>
                            @error('code') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-qrcode text-purple-500 mr-2"></i>SKU
                            </label>
                            <input wire:model="sku" type="text" placeholder="{{ __('Ex: PROD-ABC-123') }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                            @error('sku') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-barcode text-green-500 mr-2"></i>{{ __('Código de Barras') }}
                            </label>
                            <input wire:model="barcode" type="text" placeholder="{{ __('Ex: 7891234567890') }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            @error('barcode') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-cube text-indigo-500 mr-2"></i>{{ __('Unidade *') }}
                            </label>
                            <select wire:model="unit" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                                <option value="UN">{{ __('Unidade') }}</option>
                                <option value="HR">{{ __('Hora') }}</option>
                                <option value="DIA">{{ __('Dia') }}</option>
                                <option value="MÊS">{{ __('Mês') }}</option>
                                <option value="SRV">{{ __('Serviço') }}</option>
                            </select>
                            @error('unit') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-boxes text-orange-500 mr-2"></i>{{ __('Tipo *') }}
                            </label>
                            <select wire:model.live="type" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                                <option value="produto">📦 {{ __('Produto') }}</option>
                                <option value="servico">🔔 {{ __('Serviço') }}</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                {{-- PROD e SVC ficam dentro da frase mas não se
                                     traduzem: são os prefixos que o sistema
                                     gera de facto no código do artigo. --}}
                                {!! __('O código muda automaticamente: <strong>PROD</strong> para produtos, <strong>SVC</strong> para serviços') !!}
                            </p>
                            @error('type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="md:col-span-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-align-left text-gray-500 mr-2"></i>{{ __('Descrição') }}
                            </label>
                            <textarea wire:model="description" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-money-bill-wave text-green-500 mr-2"></i>{{ __('Preço (Kz) *') }}
                            </label>
                            <input wire:model="price" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            @error('price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-coins text-orange-500 mr-2"></i>{{ __('Custo (Kz)') }}
                            </label>
                            <input wire:model="cost" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                        </div>
                        
                        <!-- Seção de Categorização -->
                        <div class="md:col-span-3 p-4 bg-gradient-to-br from-cyan-50 to-blue-50 rounded-xl border-2 border-cyan-200">
                            <div class="flex items-center mb-4">
                                <div class="flex items-center justify-center w-10 h-10 bg-cyan-500 rounded-lg shadow-md">
                                    <i class="fas fa-sitemap text-white text-lg"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-bold text-gray-900">{{ __('Categorização do Produto') }}</h3>
                                    <p class="text-xs text-gray-600">{{ __('Organize por categoria e subcategoria') }}</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-folder text-cyan-600 mr-2"></i>{{ __('Categoria *') }}
                                    </label>
                                    <select wire:model="category_id" class="w-full px-4 py-3 border-2 border-cyan-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition bg-white shadow-sm">
                                        <option value="">📂 {{ __('Selecione uma categoria...') }}</option>
                                        @php
                                            // Empresa ACTIVA, não `users.tenant_id`: quem gere mais do que
                                            // uma empresa e trocou de empresa via a sessão continuava a ver
                                            // aqui as categorias da empresa de origem — e podia gravar um
                                            // artigo com a categoria de outra empresa. É o mesmo erro que já
                                            // foi corrigido nos cartões do topo (ver Products::render).
                                            $categories = \App\Models\Category::where('tenant_id', activeTenantId())
                                                ->where('is_active', true)
                                                ->whereNull('parent_id')
                                                ->orderBy('name')
                                                ->get();

                                            $subcategories = \App\Models\Category::where('tenant_id', activeTenantId())
                                                ->where('is_active', true)
                                                ->whereNotNull('parent_id')
                                                ->orderBy('parent_id')
                                                ->orderBy('name')
                                                ->get()
                                                ->groupBy('parent_id');
                                        @endphp
                                        
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" class="font-bold" style="background-color: #f0f9ff;">
                                                📁 {{ strtoupper($category->name) }}
                                            </option>
                                            
                                            @if(isset($subcategories[$category->id]))
                                                @foreach($subcategories[$category->id] as $subcategory)
                                                    <option value="{{ $subcategory->id }}" style="padding-left: 20px;">
                                                        &nbsp;&nbsp;&nbsp;└─ {{ $subcategory->name }}
                                                    </option>
                                                @endforeach
                                            @endif
                                        @endforeach
                                    </select>
                                    <div class="flex items-start mt-2 text-xs text-gray-600 bg-white p-2 rounded-lg">
                                        <i class="fas fa-info-circle text-cyan-500 mr-2 mt-0.5"></i>
                                        <div>
                                            <p class="font-semibold">{{ __('Categorias principais em MAIÚSCULAS') }}</p>
                                            <p>{{ __('Subcategorias identadas com └─') }}</p>
                                        </div>
                                    </div>
                                    @error('category_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-layer-group text-blue-600 mr-2"></i>{{ __('Hierarquia Selecionada') }}
                                    </label>
                                    <div class="w-full px-4 py-3 border-2 border-blue-200 rounded-xl bg-white shadow-sm min-h-[56px] flex items-center">
                                        @if($category_id)
                                            @php
                                                // Limitado à empresa activa: um id herdado (ou forjado) de
                                                // outra empresa mostrava aqui o nome da categoria dessa
                                                // empresa. Não encontrando nada, fica simplesmente vazio.
                                                $selectedCategory = \App\Models\Category::where('tenant_id', activeTenantId())
                                                    ->find($category_id);
                                            @endphp
                                            @if($selectedCategory)
                                                <div class="flex flex-col">
                                                    @if($selectedCategory->parent)
                                                        <div class="flex items-center text-sm mb-1">
                                                            <i class="fas fa-folder text-cyan-500 mr-2"></i>
                                                            <span class="font-bold text-gray-700">{{ $selectedCategory->parent->name }}</span>
                                                        </div>
                                                        <div class="flex items-center text-sm text-gray-600 ml-4">
                                                            <i class="fas fa-level-down-alt text-blue-400 mr-2"></i>
                                                            <span>{{ $selectedCategory->name }}</span>
                                                        </div>
                                                    @else
                                                        <div class="flex items-center text-sm">
                                                            <i class="fas fa-folder text-cyan-500 mr-2"></i>
                                                            <span class="font-bold text-gray-700">{{ $selectedCategory->name }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-gray-400 italic text-sm">
                                                <i class="fas fa-hand-pointer mr-2"></i>{{ __('Selecione uma categoria...') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-start mt-2 text-xs text-blue-600 bg-blue-50 p-2 rounded-lg">
                                        <i class="fas fa-lightbulb mr-2 mt-0.5"></i>
                                        <span>{{ __('Visualize a hierarquia da categoria escolhida') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag text-pink-500 mr-2"></i>{{ __('Marca') }}
                            </label>
                            <select wire:model="brand_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                                <option value="">{{ __('Nenhuma') }}</option>
                                {{-- Empresa activa, pela mesma razão da categoria. --}}
                                @foreach(\App\Models\Brand::where('tenant_id', activeTenantId())->where('is_active', true)->orderBy('name')->get() as $brand)
                                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">{{ __('Opcional - Marca ou fabricante do produto') }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-truck text-orange-500 mr-2"></i>{{ __('Fornecedor Padrão') }}
                            </label>
                            <select wire:model="supplier_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                                <option value="">{{ __('Nenhum') }}</option>
                                {{-- Empresa activa, pela mesma razão da categoria. --}}
                                @foreach(\App\Models\Supplier::where('tenant_id', activeTenantId())->orderBy('name')->get() as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">{{ __('Opcional - Fornecedor principal deste produto') }}</p>
                        </div>
                        
                        {{-- O preço decidido no balcão. Fica ao lado do stock
                             porque são as duas perguntas de comportamento do
                             artigo, e não dados dele. --}}
                        <div class="md:col-span-3 p-4 bg-amber-50 rounded-xl border border-amber-200">
                            <div class="flex items-center">
                                <input type="checkbox" wire:model="preco_no_pos" id="preco_no_pos" class="w-5 h-5 text-amber-600 rounded">
                                <label for="preco_no_pos" class="ml-3 text-sm font-bold text-gray-900">
                                    <i class="fas fa-hand-holding-dollar text-amber-500 mr-2"></i>{{ __('Perguntar o preço no POS') }}
                                </label>
                            </div>
                            <p class="text-xs text-gray-600 mt-2 ml-8">
                                {{ __('Para trabalhos à medida: no POS o preço é escrito na hora da venda. Na factura de venda escreve-se na linha, como sempre.') }}
                            </p>
                        </div>

                        <!-- Gestão de Stock -->
                        <div class="md:col-span-3 p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center mb-4">
                                <input type="checkbox" wire:model.live="manage_stock" id="manage_stock" class="w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                                <label for="manage_stock" class="ml-3 text-sm font-bold text-gray-900">
                                    <i class="fas fa-warehouse text-blue-500 mr-2"></i>{{ __('Gerenciar Stock') }}
                                </label>
                            </div>
                            
                            @if($manage_stock)
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-2">
                                            {{ $editingProductId ? 'Qtd. Atual (só leitura)' : 'Qtd. Inicial' }}
                                        </label>
                                        @if($editingProductId)
                                            {{-- Agregado derivado dos armazéns — ajustar via Gestão de Stock --}}
                                            <input value="{{ $stock_quantity }}" type="number" disabled class="w-full px-3 py-2 border border-gray-200 bg-gray-100 text-gray-500 rounded-lg text-sm cursor-not-allowed">
                                            <p class="text-[11px] text-gray-400 mt-1"><i class="fas fa-info-circle mr-1"></i>{!! __('Ajuste o stock em <strong>Gestão de Stock</strong> (fica registado no histórico).') !!}</p>
                                        @else
                                            <input wire:model="stock_quantity" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                        @endif
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-2">{{ __('Mínimo') }}</label>
                                        <input wire:model="stock_min" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-2">{{ __('Máximo') }}</label>
                                        <input wire:model="stock_max" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 text-sm">
                                    </div>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Controle de Lotes e Validade -->
                        <div class="md:col-span-3 p-4 bg-gradient-to-br from-amber-50 to-orange-50 rounded-xl border-2 border-amber-200">
                            <div class="flex items-center mb-4">
                                <div class="flex items-center justify-center w-10 h-10 bg-amber-500 rounded-lg shadow-md">
                                    <i class="fas fa-box-open text-white text-lg"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-bold text-gray-900">{{ __('Controle de Lotes e Validade') }}</h3>
                                    <p class="text-xs text-gray-600">{{ __('Rastreabilidade e gestão de validade do produto') }}</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <label class="flex items-center p-3 bg-white rounded-lg hover:bg-amber-50 cursor-pointer border-2 border-transparent hover:border-amber-300 transition group">
                                    <input type="checkbox" wire:model="track_batches" class="w-4 h-4 text-amber-600 rounded focus:ring-2 focus:ring-amber-500">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <i class="fas fa-layer-group text-amber-600 mr-2"></i>
                                            <span class="text-sm font-semibold text-gray-900">{{ __('Rastrear por Lotes') }}</span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">{{ __('Controlar produto por números de lote') }}</p>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 bg-white rounded-lg hover:bg-red-50 cursor-pointer border-2 border-transparent hover:border-red-300 transition group">
                                    <input type="checkbox" wire:model="track_expiry" class="w-4 h-4 text-red-600 rounded focus:ring-2 focus:ring-red-500">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <i class="fas fa-calendar-times text-red-600 mr-2"></i>
                                            <span class="text-sm font-semibold text-gray-900">{{ __('Controlar Validade') }}</span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">{{ __('Gerenciar data de validade do produto') }}</p>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 bg-white rounded-lg hover:bg-blue-50 cursor-pointer border-2 border-transparent hover:border-blue-300 transition group">
                                    <input type="checkbox" wire:model="require_batch_on_purchase" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <i class="fas fa-shopping-cart text-blue-600 mr-2"></i>
                                            <span class="text-sm font-semibold text-gray-900">{{ __('Exigir Lote na Compra') }}</span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">{{ __('Obrigatório informar lote ao comprar') }}</p>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 bg-white rounded-lg hover:bg-green-50 cursor-pointer border-2 border-transparent hover:border-green-300 transition group">
                                    <input type="checkbox" wire:model="require_batch_on_sale" class="w-4 h-4 text-green-600 rounded focus:ring-2 focus:ring-green-500">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <i class="fas fa-cash-register text-green-600 mr-2"></i>
                                            <span class="text-sm font-semibold text-gray-900">{{ __('Exigir Lote na Venda') }}</span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">{{ __('Obrigatório selecionar lote ao vender') }}</p>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="mt-3 p-3 bg-amber-100 border border-amber-300 rounded-lg">
                                <div class="flex items-start">
                                    <i class="fas fa-info-circle text-amber-700 mr-2 mt-0.5"></i>
                                    <div class="text-xs text-amber-800">
                                        <p class="font-semibold mb-1">{{ __('ℹ️ Informação Importante:') }}</p>
                                        <ul class="list-disc list-inside space-y-1">
                                            <li><strong>{{ __('Rastrear por Lotes:') }}</strong> {{ __('Ativa o controle de lotes para este produto') }}</li>
                                            <li><strong>{{ __('Controlar Validade:') }}</strong> {{ __('Permite definir datas de validade nos lotes') }}</li>
                                            <li><strong>{{ __('Exigir na Compra/Venda:') }}</strong> {{ __('Torna obrigatório informar o lote nas operações') }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Medicamento, Vestuário, Cosmética e Mercearia -->
                        @php
                            // Perfis da empresa (Definições de Faturação): dizem
                            // apenas o que aparece POR OMISSÃO neste formulário.
                            $perfilFarmacia  = $perfis['farmacia'] ?? false;
                            $perfilVestuario = $perfis['vestuario'] ?? false;
                            $perfilCosmetica = $perfis['cosmetica'] ?? false;
                            $perfilMercearia = $perfis['mercearia'] ?? false;

                            $temDadosMedicamento = $requires_prescription || $is_controlled
                                || filled($active_ingredient) || filled($dosage)
                                || filled($pharmaceutical_form) || filled($armed_registration);

                            $temDadosVestuario = filled($size) || filled($color)
                                || filled($gender) || filled($material);

                            $temDadosCosmetica = filled($pao_months) || filled($inci_ingredients);

                            $temDadosMercearia = filled($storage_conditions) || filled($allergens)
                                || filled($origin_country);

                            // O conteúdo líquido é dos dois ramos e por isso não
                            // vive dentro de nenhum deles — tem visibilidade
                            // própria, senão um artigo que só o tenha preenchido
                            // obrigava a abrir duas secções vazias para o ver.
                            $temEmbalagem = filled($net_content);

                            // O "ou já tem valor" não é opcional: sem ele,
                            // desligar o perfil escondia campos que continuavam
                            // gravados — e o utilizador ficava sem forma de os
                            // ver nem de os corrigir.
                            $mostrarMedicamento = $perfilFarmacia || $temDadosMedicamento;
                            $mostrarVestuario   = $perfilVestuario || $temDadosVestuario;
                            $mostrarCosmetica   = $perfilCosmetica || $temDadosCosmetica;
                            $mostrarMercearia   = $perfilMercearia || $temDadosMercearia;
                            $mostrarEmbalagem   = $perfilCosmetica || $perfilMercearia || $temEmbalagem;

                            // Nem perfil nem dados: a secção não se impõe, mas
                            // fica a um clique. Obrigar a passar pelas Definições
                            // só para marcar um artigo isolado era pior do que a
                            // linha extra que aqui se mostra.
                            $revelavel = !$mostrarMedicamento && !$mostrarVestuario
                                && !$mostrarCosmetica && !$mostrarMercearia && !$mostrarEmbalagem;

                            // Estado INICIAL de cada bloco, não um @if: todos vão
                            // sempre para o HTML e quem manda na visibilidade é o
                            // Alpine. O perfil decide o que aparece por omissão, não
                            // o que existe — uma farmácia que venda uma t-shirt tem
                            // de conseguir gravar o tamanho sem ir ligar o perfil de
                            // vestuário nas Definições.
                            $blocoMedicamento = $mostrarMedicamento || $revelavel;
                            $blocoVestuario   = $mostrarVestuario || $revelavel;
                            $blocoCosmetica   = $mostrarCosmetica || $revelavel;
                            $blocoMercearia   = $mostrarMercearia || $revelavel;
                            $blocoEmbalagem   = $mostrarEmbalagem || $revelavel;

                            // Uma loja de roupa não tem de ler "Medicamento" no
                            // cabeçalho de uma secção que só lhe mostra tamanhos.
                            // Com mais do que um ramo à vista enumerá-los daria um
                            // cabeçalho maior do que a própria secção.
                            $nomesBlocos = array_values(array_filter([
                                $blocoMedicamento ? __('Medicamento') : null,
                                $blocoVestuario ? __('Vestuário') : null,
                                $blocoCosmetica ? __('Cosmética') : null,
                                $blocoMercearia ? __('Mercearia') : null,
                            ]));

                            $tituloEspecificos = match (count($nomesBlocos)) {
                                1 => $nomesBlocos[0],
                                0 => __('Embalagem'),
                                default => __('Detalhes específicos do artigo'),
                            };

                            // A secção abre de raiz quando o artigo já é um
                            // medicamento, uma peça de roupa, um cosmético ou um
                            // alimento: quem edita um medicamento não pode ter de
                            // adivinhar onde estão os campos dele. Nos restantes
                            // artigos fica recolhida para não encher o formulário
                            // de campos que 99% do catálogo nunca usa.
                            $temEspecificos = $temDadosMedicamento || $temDadosVestuario
                                || $temDadosCosmetica || $temDadosMercearia || $temEmbalagem;

                            // Valores já usados no catálogo, para sugerir sem
                            // impor: evita ter "Azul", "azul" e "AZUL" como três
                            // cores diferentes. Vem da listagem por @include.
                            $tamanhosSugeridos = $variantes['tamanhos'] ?? [];
                            $coresSugeridas = $variantes['cores'] ?? [];
                        @endphp
                        <div class="md:col-span-3" x-data="{ revelado: @js(!$revelavel), aberto: @js($temEspecificos), med: @js($blocoMedicamento), vest: @js($blocoVestuario), cosm: @js($blocoCosmetica), merc: @js($blocoMercearia), emb: @js($blocoEmbalagem), rotulos: @js(['med' => __('Medicamento'), 'vest' => __('Vestuário'), 'cosm' => __('Cosmética'), 'merc' => __('Mercearia'), 'emb' => __('Embalagem'), 'varios' => __('Detalhes específicos do artigo')]), get titulo() { const abertos = [this.med && this.rotulos.med, this.vest && this.rotulos.vest, this.cosm && this.rotulos.cosm, this.merc && this.rotulos.merc].filter(Boolean); if (abertos.length === 1) return abertos[0]; return abertos.length === 0 ? this.rotulos.emb : this.rotulos.varios; } }">
                            {{-- O atalho para quem não trabalha com nada disto: uma
                                 linha discreta em vez da secção inteira. Nomear os
                                 quatro ramos daria uma pergunta mais comprida do
                                 que a resposta — três exemplos chegam para se
                                 perceber do que se trata. --}}
                            <button type="button" x-show="!revelado" x-cloak @click="revelado = true; aberto = true"
                                    class="text-xs text-teal-700 hover:text-teal-900 underline decoration-dotted">
                                <i class="fas fa-tags mr-1"></i>{{ __('Este artigo tem campos próprios do ramo (receita, tamanho, alergénios…)?') }}
                            </button>

                            <div x-show="revelado" x-cloak class="p-4 bg-gradient-to-br from-teal-50 to-emerald-50 rounded-xl border-2 border-teal-200">
                                <button type="button" @click="aberto = !aberto" class="w-full flex items-center text-left">
                                    <div class="flex items-center justify-center w-10 h-10 bg-teal-500 rounded-lg shadow-md">
                                        <i class="fas fa-tags text-white text-lg"></i>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        {{-- O texto do servidor é o arranque; o x-text
                                             mantém-no certo depois de se revelar
                                             outro bloco, sem ida ao servidor. --}}
                                        <h3 class="text-sm font-bold text-gray-900"
                                            x-text="titulo">{{ $tituloEspecificos }}</h3>
                                        <p class="text-xs text-gray-600">{{ __('Campos opcionais — preencha apenas o que se aplica a este artigo') }}</p>
                                    </div>
                                    <i class="fas text-teal-600 text-lg" :class="aberto ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                </button>

                                <div x-show="aberto" x-cloak class="mt-4 space-y-4">
                                    <!-- Medicamento -->
                                    <div x-show="med" x-cloak class="p-4 bg-white rounded-xl border border-teal-200">
                                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                                            <i class="fas fa-pills text-teal-600 mr-2"></i>{{ __('Medicamento') }}
                                        </h4>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                                            <label class="flex items-start p-3 bg-red-50 rounded-lg cursor-pointer border-2 border-transparent hover:border-red-300 transition">
                                                <input type="checkbox" wire:model="requires_prescription" class="mt-0.5 w-4 h-4 text-red-600 rounded focus:ring-2 focus:ring-red-500">
                                                <div class="ml-3 flex-1">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-file-prescription text-red-600 mr-2"></i>
                                                        <span class="text-sm font-semibold text-gray-900">{{ __('Exige receita médica') }}</span>
                                                    </div>
                                                    <p class="text-xs text-gray-500 mt-1">{{ __('Só pode ser dispensado com apresentação de receita') }}</p>
                                                </div>
                                            </label>

                                            <label class="flex items-start p-3 bg-purple-50 rounded-lg cursor-pointer border-2 border-transparent hover:border-purple-300 transition">
                                                <input type="checkbox" wire:model="is_controlled" class="mt-0.5 w-4 h-4 text-purple-600 rounded focus:ring-2 focus:ring-purple-500">
                                                <div class="ml-3 flex-1">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-triangle-exclamation text-purple-600 mr-2"></i>
                                                        <span class="text-sm font-semibold text-gray-900">{{ __('Psicotrópico / estupefaciente') }}</span>
                                                    </div>
                                                    <p class="text-xs text-gray-500 mt-1">{{ __('Substância sujeita a controlo especial') }}</p>
                                                </div>
                                            </label>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="md:col-span-2">
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-flask text-teal-600 mr-1"></i>{{ __('Substância activa (DCI)') }}
                                                </label>
                                                <input wire:model="active_ingredient" type="text" maxlength="255"
                                                       placeholder="{{ __('Ex: Paracetamol') }}"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent transition text-sm">
                                                @error('active_ingredient') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-weight-scale text-teal-600 mr-1"></i>{{ __('Dosagem') }}
                                                </label>
                                                <input wire:model="dosage" type="text" maxlength="60"
                                                       placeholder="{{ __('Ex: 500mg, 5mg/ml') }}"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent transition text-sm">
                                                @error('dosage') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-prescription-bottle text-teal-600 mr-1"></i>{{ __('Forma farmacêutica') }}
                                                </label>
                                                {{-- Texto livre com sugestões (datalist) em vez de select: a
                                                     lista de formas farmacêuticas é longa e a farmácia tem de
                                                     poder registar uma que não esteja prevista. --}}
                                                <input wire:model="pharmaceutical_form" type="text" maxlength="40"
                                                       list="formas-farmaceuticas"
                                                       placeholder="{{ __('Ex: comprimido, xarope, injectável') }}"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent transition text-sm">
                                                <datalist id="formas-farmaceuticas">
                                                    <option value="{{ __('comprimido') }}"></option>
                                                    <option value="{{ __('cápsula') }}"></option>
                                                    <option value="{{ __('xarope') }}"></option>
                                                    <option value="{{ __('suspensão') }}"></option>
                                                    <option value="{{ __('injectável') }}"></option>
                                                    <option value="{{ __('pomada') }}"></option>
                                                    <option value="{{ __('creme') }}"></option>
                                                    <option value="{{ __('gotas') }}"></option>
                                                    <option value="{{ __('supositório') }}"></option>
                                                </datalist>
                                                @error('pharmaceutical_form') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div class="md:col-span-2">
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-stamp text-teal-600 mr-1"></i>{{ __('N.º de registo ARMED') }}
                                                </label>
                                                <input wire:model="armed_registration" type="text" maxlength="60"
                                                       placeholder="{{ __('Registo na ARMED (Angola)') }}"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent transition text-sm font-mono">
                                                @error('armed_registration') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Vestuário -->
                                    <div x-show="vest" x-cloak class="p-4 bg-white rounded-xl border border-emerald-200">
                                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                                            <i class="fas fa-shirt text-emerald-600 mr-2"></i>{{ __('Vestuário') }}
                                        </h4>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-ruler text-emerald-600 mr-1"></i>{{ __('Tamanho') }}
                                                </label>
                                                <input wire:model="size" type="text" maxlength="20"
                                                       list="tamanhos-catalogo"
                                                       placeholder="{{ __('Ex: S, M, L, 38, 40') }}"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition text-sm">
                                                <datalist id="tamanhos-catalogo">
                                                    @foreach($tamanhosSugeridos as $t)
                                                        <option value="{{ $t }}"></option>
                                                    @endforeach
                                                </datalist>
                                                @error('size') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-palette text-emerald-600 mr-1"></i>{{ __('Cor') }}
                                                </label>
                                                <input wire:model="color" type="text" maxlength="40"
                                                       list="cores-catalogo"
                                                       placeholder="{{ __('Ex: azul-marinho') }}"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition text-sm">
                                                <datalist id="cores-catalogo">
                                                    @foreach($coresSugeridas as $c)
                                                        <option value="{{ $c }}"></option>
                                                    @endforeach
                                                </datalist>
                                                @error('color') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-venus-mars text-emerald-600 mr-1"></i>{{ __('Género') }}
                                                </label>
                                                {{-- Lista fechada: o género alimenta filtros e relatórios e
                                                     texto livre daria "M", "masc" e "Homem" a significar o
                                                     mesmo. Os valores gravados não se traduzem. --}}
                                                <select wire:model="gender" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition text-sm bg-white">
                                                    <option value="">{{ __('Não aplicável') }}</option>
                                                    <option value="masculino">{{ __('Masculino') }}</option>
                                                    <option value="feminino">{{ __('Feminino') }}</option>
                                                    <option value="unissexo">{{ __('Unissexo') }}</option>
                                                    <option value="crianca">{{ __('Criança') }}</option>
                                                </select>
                                                @error('gender') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-scroll text-emerald-600 mr-1"></i>{{ __('Composição') }}
                                                </label>
                                                <input wire:model="material" type="text" maxlength="120"
                                                       placeholder="{{ __('Ex: 100% algodão') }}"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition text-sm">
                                                @error('material') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Embalagem: o conteúdo líquido é da cosmética
                                         E da mercearia, por isso fica fora dos dois
                                         blocos em vez de repetido em ambos. --}}
                                    <div x-show="emb || cosm || merc" x-cloak class="p-4 bg-white rounded-xl border border-amber-200">
                                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                                            <i class="fas fa-box-open text-amber-600 mr-2"></i>{{ __('Embalagem') }}
                                        </h4>

                                        <div class="md:w-1/2">
                                            <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                <i class="fas fa-bottle-water text-amber-600 mr-1"></i>{{ __('Conteúdo líquido') }}
                                            </label>
                                            <input wire:model="net_content" type="text" maxlength="40"
                                                   placeholder="{{ __('Ex: 50ml, 200g, 1kg') }}"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent transition text-sm">
                                            <p class="text-xs text-gray-500 mt-1">{{ __('É o que distingue duas embalagens do mesmo produto.') }}</p>
                                            @error('net_content') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                    </div>

                                    <!-- Cosmética -->
                                    <div x-show="cosm" x-cloak class="p-4 bg-white rounded-xl border border-rose-200">
                                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                                            <i class="fas fa-pump-soap text-rose-600 mr-2"></i>{{ __('Cosmética') }}
                                        </h4>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-hourglass-half text-rose-600 mr-1"></i>{{ __('Meses após abertura (PAO)') }}
                                                </label>
                                                {{-- É o frasco aberto com "12M" no rótulo:
                                                     quanto tempo dura DEPOIS de aberto. Não
                                                     substitui o prazo de validade por abrir
                                                     — a loja precisa dos dois. --}}
                                                <input wire:model="pao_months" type="number" min="1" max="120" step="1"
                                                       placeholder="{{ __('Ex: 12') }}"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-rose-500 focus:border-transparent transition text-sm">
                                                <p class="text-xs text-gray-500 mt-1">{{ __('Validade depois de aberto, que é diferente do prazo por abrir.') }}</p>
                                                @error('pao_months') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-palette text-rose-600 mr-1"></i>{{ __('Tom') }}
                                                </label>
                                                {{-- O tom não tem campo próprio: é a mesma
                                                     coisa que a cor, que já existe. Dois
                                                     sítios para gravar o mesmo davam duas
                                                     respostas diferentes à mesma pergunta. --}}
                                                <div class="px-3 py-2 bg-rose-50 border border-rose-100 rounded-lg text-xs text-gray-600">
                                                    <span>{{ __('O tom regista-se no campo Cor.') }}</span>
                                                    <button type="button" x-show="!vest" @click="vest = true"
                                                            class="ml-1 underline decoration-dotted text-rose-700 hover:text-rose-900">
                                                        {{ __('Mostrar') }}
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="md:col-span-2">
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-list text-rose-600 mr-1"></i>{{ __('Lista INCI') }}
                                                </label>
                                                {{-- Área de texto e não uma linha: uma lista
                                                     INCI a sério tem dezenas de nomes, e é
                                                     com ela que se responde ao balcão a
                                                     "isto tem parabenos?". --}}
                                                <textarea wire:model="inci_ingredients" rows="3"
                                                          placeholder="{{ __('Ex: Aqua, Glycerin, Parfum, Sodium Chloride') }}"
                                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-rose-500 focus:border-transparent transition text-sm"></textarea>
                                                <p class="text-xs text-gray-500 mt-1">{{ __('Lista normalizada de ingredientes, tal como vem no rótulo.') }}</p>
                                                @error('inci_ingredients') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Mercearia -->
                                    <div x-show="merc" x-cloak class="p-4 bg-white rounded-xl border border-amber-200">
                                        <h4 class="text-sm font-bold text-gray-900 mb-3 flex items-center">
                                            <i class="fas fa-basket-shopping text-amber-600 mr-2"></i>{{ __('Mercearia') }}
                                        </h4>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-temperature-half text-amber-600 mr-1"></i>{{ __('Conservação') }}
                                                </label>
                                                {{-- Lista fechada, como o género: isto diz a
                                                     quem arruma se o artigo vai para a
                                                     prateleira, para o frigorífico ou para a
                                                     arca, e aparece no stock. Os valores
                                                     gravados são chaves e não se traduzem. --}}
                                                <select wire:model="storage_conditions" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent transition text-sm bg-white">
                                                    <option value="">{{ __('Não indicado') }}</option>
                                                    <option value="ambiente">{{ __('Ambiente') }}</option>
                                                    <option value="refrigerado">{{ __('Refrigerado') }}</option>
                                                    <option value="congelado">{{ __('Congelado') }}</option>
                                                </select>
                                                @error('storage_conditions') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-earth-africa text-amber-600 mr-1"></i>{{ __('País de origem') }}
                                                </label>
                                                <input wire:model="origin_country" type="text" maxlength="60"
                                                       placeholder="{{ __('Ex: Angola, Portugal, Brasil') }}"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent transition text-sm">
                                                <p class="text-xs text-gray-500 mt-1">{{ __('Obrigatório no rótulo alimentar.') }}</p>
                                                @error('origin_country') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div class="md:col-span-2">
                                                <label class="block text-xs font-semibold text-gray-600 mb-2">
                                                    <i class="fas fa-wheat-awn-circle-exclamation text-amber-600 mr-1"></i>{{ __('Alergénios') }}
                                                </label>
                                                <input wire:model="allergens" type="text" maxlength="255"
                                                       placeholder="{{ __('Ex: glúten, leite, frutos de casca rija') }}"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent transition text-sm">
                                                <p class="text-xs text-gray-500 mt-1">{{ __('Informação obrigatória no rótulo e pergunta de balcão.') }}</p>
                                                @error('allergens') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Saída para o bloco que o perfil não abriu de
                                         raiz. Sem isto, ligar só o perfil de farmácia
                                         deixava o tamanho e a cor sem forma nenhuma de
                                         serem preenchidos — o perfil passava a decidir
                                         o que EXISTE em vez do que aparece. --}}
                                    <div x-show="!med || !vest || !cosm || !merc" x-cloak class="flex flex-wrap gap-4 pt-1">
                                        <button type="button" x-show="!med" @click="med = true"
                                                class="text-xs text-teal-700 hover:text-teal-900 underline decoration-dotted">
                                            <i class="fas fa-pills mr-1"></i>{{ __('Este artigo também é medicamento') }}
                                        </button>
                                        <button type="button" x-show="!vest" @click="vest = true"
                                                class="text-xs text-emerald-700 hover:text-emerald-900 underline decoration-dotted">
                                            <i class="fas fa-shirt mr-1"></i>{{ __('Este artigo também é vestuário') }}
                                        </button>
                                        <button type="button" x-show="!cosm" @click="cosm = true"
                                                class="text-xs text-rose-700 hover:text-rose-900 underline decoration-dotted">
                                            <i class="fas fa-pump-soap mr-1"></i>{{ __('Este artigo também é cosmética') }}
                                        </button>
                                        <button type="button" x-show="!merc" @click="merc = true"
                                                class="text-xs text-amber-700 hover:text-amber-900 underline decoration-dotted">
                                            <i class="fas fa-basket-shopping mr-1"></i>{{ __('Este artigo também é mercearia') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Imagens -->
                        <div x-data="{ preview: null }">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-image text-purple-500 mr-2"></i>{{ __('Imagem Destaque') }}
                            </label>
                            
                            @if($currentFeaturedImage)
                                <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="flex items-center space-x-3">
                                        <img src="{{ Storage::url($currentFeaturedImage) }}" alt="{{ __('Imagem atual') }}" class="h-24 w-24 object-cover rounded-lg shadow-md border-2 border-gray-300">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-700">{{ __('Imagem Atual') }}</p>
                                            <p class="text-xs text-gray-500">{{ __('Selecione nova para substituir') }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Preview da nova imagem -->
                            <div x-show="preview" class="mb-3 p-4 bg-green-50 border-2 border-green-200 rounded-xl">
                                <div class="flex items-start space-x-3">
                                    <img :src="preview" alt="{{ __('Preview') }}" class="h-32 w-32 object-cover rounded-lg shadow-lg border-2 border-green-400">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2 mb-2">
                                            <i class="fas fa-check-circle text-green-600"></i>
                                            <span class="text-sm font-semibold text-green-700">{{ __('Nova Imagem Selecionada') }}</span>
                                        </div>
                                        <button type="button" @click="preview = null; $wire.set('featured_image', null)" 
                                                class="px-3 py-1 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg text-xs font-semibold transition">
                                            <i class="fas fa-times mr-1"></i>{{ __('Remover') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <input wire:model="featured_image" type="file" accept="image/*" 
                                   @change="preview = URL.createObjectURL($event.target.files[0])"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                            <p class="text-xs text-gray-500 mt-1">{{ __('Máximo 2MB - PNG, JPG, GIF') }}</p>
                        </div>
                        
                        <div class="md:col-span-2" x-data="{ galleryPreviews: [] }">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-images text-pink-500 mr-2"></i>{{ __('Galeria de Imagens') }}
                            </label>
                            
                            @if(!empty($currentGallery))
                                <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-semibold text-gray-600 mb-2">{{ __('Imagens Atuais:') }}</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($currentGallery as $image)
                                            <div class="relative group">
                                                <img src="{{ Storage::url($image) }}" alt="{{ __('Galeria') }}" class="h-20 w-20 object-cover rounded-lg shadow-md border-2 border-gray-300">
                                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 rounded-lg transition flex items-center justify-center">
                                                    <i class="fas fa-search-plus text-white opacity-0 group-hover:opacity-100 transition"></i>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Preview das novas imagens -->
                            <div x-show="galleryPreviews.length > 0" class="mb-3 p-4 bg-green-50 border-2 border-green-200 rounded-xl">
                                <div class="flex items-center space-x-2 mb-3">
                                    <i class="fas fa-check-circle text-green-600"></i>
                                    <span class="text-sm font-semibold text-green-700">
                                        <span x-text="galleryPreviews.length"></span> {{ __('Nova(s) Imagem(ns) Selecionada(s)') }}
                                    </span>
                                    <button type="button" @click="galleryPreviews = []; $wire.set('gallery', [])" 
                                            class="ml-auto px-2 py-1 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded text-xs font-semibold transition">
                                        <i class="fas fa-times mr-1"></i>{{ __('Remover Todas') }}
                                    </button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="(preview, index) in galleryPreviews" :key="index">
                                        <div class="relative">
                                            <img :src="preview" alt="{{ __('Preview') }}" class="h-24 w-24 object-cover rounded-lg shadow-lg border-2 border-green-400">
                                            <button type="button" 
                                                    @click="galleryPreviews.splice(index, 1)"
                                                    class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition">
                                                <i class="fas fa-times text-xs"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            
                            <input wire:model="gallery" type="file" accept="image/*" multiple 
                                   @change="galleryPreviews = Array.from($event.target.files).map(file => URL.createObjectURL(file))"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100">
                            <p class="text-xs text-gray-500 mt-1">{{ __('Múltiplas imagens - Máximo 2MB cada') }}</p>
                        </div>
                        
                        <div class="md:col-span-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-receipt text-blue-500 mr-2"></i>{{ __('Regime de IVA *') }}
                            </label>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="relative flex items-center p-4 border-2 rounded-xl cursor-pointer transition hover:shadow-md {{ $tax_type === 'iva' ? 'border-blue-500 bg-blue-50' : 'border-gray-300' }}">
                                        <input type="radio" wire:model.live="tax_type" value="iva" class="sr-only">
                                        <div class="flex-1">
                                            <div class="flex items-center mb-1">
                                                <i class="fas fa-percentage text-blue-500 mr-2"></i>
                                                <span class="font-bold text-gray-900">{{ __('Sujeito a IVA') }}</span>
                                            </div>
                                            <p class="text-xs text-gray-500">{{ __('Produto com taxa de IVA') }}</p>
                                        </div>
                                        @if($tax_type === 'iva')
                                            <i class="fas fa-check-circle text-blue-500 text-xl"></i>
                                        @endif
                                    </label>
                                </div>
                                <div>
                                    <label class="relative flex items-center p-4 border-2 rounded-xl cursor-pointer transition hover:shadow-md {{ $tax_type === 'isento' ? 'border-green-500 bg-green-50' : 'border-gray-300' }}">
                                        <input type="radio" wire:model.live="tax_type" value="isento" class="sr-only">
                                        <div class="flex-1">
                                            <div class="flex items-center mb-1">
                                                <i class="fas fa-ban text-green-500 mr-2"></i>
                                                <span class="font-bold text-gray-900">{{ __('Isento de IVA') }}</span>
                                            </div>
                                            <p class="text-xs text-gray-500">{{ __('Produto isento') }}</p>
                                        </div>
                                        @if($tax_type === 'isento')
                                            <i class="fas fa-check-circle text-green-500 text-xl"></i>
                                        @endif
                                    </label>
                                </div>
                            </div>
                            @error('tax_type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        @if($tax_type === 'iva')
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-percent text-blue-500 mr-2"></i>{{ __('Taxa de IVA *') }}
                                </label>
                                
                                @php
                                    // Buscar taxas da tabela CORRETA: invoicing_taxes
                                    if (!isset($taxRates)) {
                                        $taxRates = \App\Models\Invoicing\Tax::where('tenant_id', activeTenantId())
                                            ->where('is_active', true)
                                            ->where('type', 'iva')
                                            ->orderBy('rate')
                                            ->get();
                                    }
                                @endphp
                                
                                @if($taxRates->count() > 0)
                                    <select wire:model="tax_rate_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                        <option value="">{{ __('Selecione a taxa...') }}</option>
                                        @foreach($taxRates as $rate)
                                            <option value="{{ $rate->id }}">{{ $rate->name }} ({{ $rate->rate }}%) - {{ $rate->description }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <div class="p-4 bg-yellow-50 border-2 border-yellow-300 rounded-xl">
                                        <div class="flex items-start">
                                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-3 mt-1"></i>
                                            <div>
                                                <p class="text-sm font-semibold text-yellow-800">{{ __('Nenhuma taxa de IVA cadastrada') }}</p>
                                                <p class="text-xs text-yellow-700 mt-1">{{ __('Por favor, cadastre as taxas primeiro em:') }}</p>
                                                <a href="{{ route('invoicing.taxes') }}" target="_blank" 
                                                   class="inline-flex items-center mt-2 px-3 py-1 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-xs font-semibold transition">
                                                    <i class="fas fa-external-link-alt mr-2"></i>{{ __('Ir para Taxas de IVA') }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                
                                @error('tax_rate_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        
                        @if($tax_type === 'isento')
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-file-alt text-green-500 mr-2"></i>{{ __('Motivo de Isenção *') }}
                                </label>
                                <select wire:model="exemption_reason" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                    <option value="">{{ __('Selecione o motivo...') }}</option>
                                    @php
                                        $grouped = ($exemptionCodes ?? collect())->groupBy('tax_type');
                                        $groupLabels = ['IVA' => 'IVA (M01–M93)', 'IS' => 'Imposto de Selo (S01–S03)', 'IRT' => 'IRT (I01–I16)'];
                                    @endphp
                                    @forelse($grouped as $taxType => $codes)
                                        <optgroup label="{{ $groupLabels[$taxType] ?? $taxType }}">
                                            @foreach($codes as $c)
                                                <option value="{{ $c->code }}">{{ $c->code }} — {{ $c->description }}</option>
                                            @endforeach
                                        </optgroup>
                                    @empty
                                        {{-- Fallback: se a tabela ainda não foi seedada, usa a lista legada --}}
                                        @foreach(\App\Models\Product::EXEMPTION_REASONS as $code => $reason)
                                            <option value="{{ $code }}">{{ $code }} - {{ $reason }}</option>
                                        @endforeach
                                    @endforelse
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ __('Código oficial AGT (DS.120 §9.5) — agrupado por tipo de imposto.') }}
                                </p>
                                @error('exemption_reason') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" wire:click="closeModal" class="px-4 sm:px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 hover:scale-105 transition-all duration-300">
                            <i class="fas fa-times mr-2"></i>{{ __('Cancelar') }}
                        </button>
                        <button type="submit" 
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-70 scale-95"
                                class="px-4 sm:px-6 py-2.5 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-pink-700 shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-300 disabled:opacity-70 disabled:cursor-not-allowed">
                            <span wire:loading.remove>
                                <i class="fas {{ $editingProductId ? 'fa-save' : 'fa-plus' }} mr-2"></i>
                                {{ $editingProductId ? 'Atualizar' : 'Criar' }}
                            </span>
                            <span wire:loading class="flex items-center">
                                <i class="fas fa-spinner fa-spin mr-2"></i>
                                {{ __('Processando...') }}
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
