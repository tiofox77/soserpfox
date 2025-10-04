@if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
                <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-box mr-3"></i>{{ $editingProductId ? 'Editar' : 'Novo' }} Produto
                        </h3>
                        <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <form wire:submit.prevent="save" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="col-span-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag text-purple-500 mr-2"></i>Nome *
                            </label>
                            <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                            @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-hashtag text-blue-500 mr-2"></i>Código 
                                @if(!$editingProductId)
                                    <span class="text-xs text-green-600 font-normal">
                                        <i class="fas fa-check-circle"></i> (Gerado automaticamente - editável)
                                    </span>
                                @endif
                            </label>
                            <div class="relative">
                                <input wire:model="code" 
                                       type="text" 
                                       placeholder="Ex: PROD000001"
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
                            <input wire:model="sku" type="text" placeholder="Ex: PROD-ABC-123" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                            @error('sku') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-barcode text-green-500 mr-2"></i>Código de Barras
                            </label>
                            <input wire:model="barcode" type="text" placeholder="Ex: 7891234567890" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            @error('barcode') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-cube text-indigo-500 mr-2"></i>Unidade *
                            </label>
                            <select wire:model="unit" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                                <option value="UN">Unidade</option>
                                <option value="HR">Hora</option>
                                <option value="DIA">Dia</option>
                                <option value="MÊS">Mês</option>
                                <option value="SRV">Serviço</option>
                            </select>
                            @error('unit') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-boxes text-orange-500 mr-2"></i>Tipo *
                            </label>
                            <select wire:model.live="type" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                                <option value="produto">📦 Produto</option>
                                <option value="servico">🔔 Serviço</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                O código muda automaticamente: <strong>PROD</strong> para produtos, <strong>SVC</strong> para serviços
                            </p>
                            @error('type') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="col-span-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-align-left text-gray-500 mr-2"></i>Descrição
                            </label>
                            <textarea wire:model="description" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-money-bill-wave text-green-500 mr-2"></i>Preço (Kz) *
                            </label>
                            <input wire:model="price" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            @error('price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-coins text-orange-500 mr-2"></i>Custo (Kz)
                            </label>
                            <input wire:model="cost" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                        </div>
                        
                        <!-- Seção de Categorização -->
                        <div class="col-span-3 p-4 bg-gradient-to-br from-cyan-50 to-blue-50 rounded-xl border-2 border-cyan-200">
                            <div class="flex items-center mb-4">
                                <div class="flex items-center justify-center w-10 h-10 bg-cyan-500 rounded-lg shadow-md">
                                    <i class="fas fa-sitemap text-white text-lg"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-bold text-gray-900">Categorização do Produto</h3>
                                    <p class="text-xs text-gray-600">Organize por categoria e subcategoria</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-folder text-cyan-600 mr-2"></i>Categoria *
                                    </label>
                                    <select wire:model="category_id" class="w-full px-4 py-3 border-2 border-cyan-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition bg-white shadow-sm">
                                        <option value="">📂 Selecione uma categoria...</option>
                                        @php
                                            $categories = \App\Models\Category::where('tenant_id', auth()->user()->tenant_id)
                                                ->where('is_active', true)
                                                ->whereNull('parent_id')
                                                ->orderBy('name')
                                                ->get();
                                            
                                            $subcategories = \App\Models\Category::where('tenant_id', auth()->user()->tenant_id)
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
                                            <p class="font-semibold">Categorias principais em MAIÚSCULAS</p>
                                            <p>Subcategorias identadas com └─</p>
                                        </div>
                                    </div>
                                    @error('category_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-layer-group text-blue-600 mr-2"></i>Hierarquia Selecionada
                                    </label>
                                    <div class="w-full px-4 py-3 border-2 border-blue-200 rounded-xl bg-white shadow-sm min-h-[56px] flex items-center">
                                        @if($category_id)
                                            @php
                                                $selectedCategory = \App\Models\Category::find($category_id);
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
                                                <i class="fas fa-hand-pointer mr-2"></i>Selecione uma categoria...
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-start mt-2 text-xs text-blue-600 bg-blue-50 p-2 rounded-lg">
                                        <i class="fas fa-lightbulb mr-2 mt-0.5"></i>
                                        <span>Visualize a hierarquia da categoria escolhida</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag text-pink-500 mr-2"></i>Marca
                            </label>
                            <select wire:model="brand_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                                <option value="">Nenhuma</option>
                                @foreach(\App\Models\Brand::where('tenant_id', auth()->user()->tenant_id)->where('is_active', true)->orderBy('name')->get() as $brand)
                                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Opcional - Marca ou fabricante do produto</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-truck text-orange-500 mr-2"></i>Fornecedor Padrão
                            </label>
                            <select wire:model="supplier_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                                <option value="">Nenhum</option>
                                @foreach(\App\Models\Supplier::where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get() as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Opcional - Fornecedor principal deste produto</p>
                        </div>
                        
                        <!-- Gestão de Stock -->
                        <div class="col-span-3 p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center mb-4">
                                <input type="checkbox" wire:model.live="manage_stock" id="manage_stock" class="w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                                <label for="manage_stock" class="ml-3 text-sm font-bold text-gray-900">
                                    <i class="fas fa-warehouse text-blue-500 mr-2"></i>Gerenciar Stock
                                </label>
                            </div>
                            
                            @if($manage_stock)
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-2">Qtd. Atual</label>
                                        <input wire:model="stock_quantity" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-2">Mínimo</label>
                                        <input wire:model="stock_min" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-2">Máximo</label>
                                        <input wire:model="stock_max" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 text-sm">
                                    </div>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Imagens -->
                        <div x-data="{ preview: null }">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-image text-purple-500 mr-2"></i>Imagem Destaque
                            </label>
                            
                            @if($currentFeaturedImage)
                                <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="flex items-center space-x-3">
                                        <img src="{{ Storage::url($currentFeaturedImage) }}" alt="Imagem atual" class="h-24 w-24 object-cover rounded-lg shadow-md border-2 border-gray-300">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-700">Imagem Atual</p>
                                            <p class="text-xs text-gray-500">Selecione nova para substituir</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Preview da nova imagem -->
                            <div x-show="preview" class="mb-3 p-4 bg-green-50 border-2 border-green-200 rounded-xl">
                                <div class="flex items-start space-x-3">
                                    <img :src="preview" alt="Preview" class="h-32 w-32 object-cover rounded-lg shadow-lg border-2 border-green-400">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2 mb-2">
                                            <i class="fas fa-check-circle text-green-600"></i>
                                            <span class="text-sm font-semibold text-green-700">Nova Imagem Selecionada</span>
                                        </div>
                                        <button type="button" @click="preview = null; $wire.set('featured_image', null)" 
                                                class="px-3 py-1 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg text-xs font-semibold transition">
                                            <i class="fas fa-times mr-1"></i>Remover
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <input wire:model="featured_image" type="file" accept="image/*" 
                                   @change="preview = URL.createObjectURL($event.target.files[0])"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                            <p class="text-xs text-gray-500 mt-1">Máximo 2MB - PNG, JPG, GIF</p>
                        </div>
                        
                        <div class="col-span-2" x-data="{ galleryPreviews: [] }">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-images text-pink-500 mr-2"></i>Galeria de Imagens
                            </label>
                            
                            @if(!empty($currentGallery))
                                <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-semibold text-gray-600 mb-2">Imagens Atuais:</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($currentGallery as $image)
                                            <div class="relative group">
                                                <img src="{{ Storage::url($image) }}" alt="Galeria" class="h-20 w-20 object-cover rounded-lg shadow-md border-2 border-gray-300">
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
                                        <span x-text="galleryPreviews.length"></span> Nova(s) Imagem(ns) Selecionada(s)
                                    </span>
                                    <button type="button" @click="galleryPreviews = []; $wire.set('gallery', [])" 
                                            class="ml-auto px-2 py-1 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded text-xs font-semibold transition">
                                        <i class="fas fa-times mr-1"></i>Remover Todas
                                    </button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="(preview, index) in galleryPreviews" :key="index">
                                        <div class="relative">
                                            <img :src="preview" alt="Preview" class="h-24 w-24 object-cover rounded-lg shadow-lg border-2 border-green-400">
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
                            <p class="text-xs text-gray-500 mt-1">Múltiplas imagens - Máximo 2MB cada</p>
                        </div>
                        
                        <div class="col-span-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-receipt text-blue-500 mr-2"></i>Regime de IVA *
                            </label>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="relative flex items-center p-4 border-2 rounded-xl cursor-pointer transition hover:shadow-md {{ $tax_type === 'iva' ? 'border-blue-500 bg-blue-50' : 'border-gray-300' }}">
                                        <input type="radio" wire:model.live="tax_type" value="iva" class="sr-only">
                                        <div class="flex-1">
                                            <div class="flex items-center mb-1">
                                                <i class="fas fa-percentage text-blue-500 mr-2"></i>
                                                <span class="font-bold text-gray-900">Sujeito a IVA</span>
                                            </div>
                                            <p class="text-xs text-gray-500">Produto com taxa de IVA</p>
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
                                                <span class="font-bold text-gray-900">Isento de IVA</span>
                                            </div>
                                            <p class="text-xs text-gray-500">Produto isento</p>
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
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-percent text-blue-500 mr-2"></i>Taxa de IVA *
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
                                        <option value="">Selecione a taxa...</option>
                                        @foreach($taxRates as $rate)
                                            <option value="{{ $rate->id }}">{{ $rate->name }} ({{ $rate->rate }}%) - {{ $rate->description }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <div class="p-4 bg-yellow-50 border-2 border-yellow-300 rounded-xl">
                                        <div class="flex items-start">
                                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-3 mt-1"></i>
                                            <div>
                                                <p class="text-sm font-semibold text-yellow-800">Nenhuma taxa de IVA cadastrada</p>
                                                <p class="text-xs text-yellow-700 mt-1">Por favor, cadastre as taxas primeiro em:</p>
                                                <a href="{{ route('invoicing.taxes.index') }}" target="_blank" 
                                                   class="inline-flex items-center mt-2 px-3 py-1 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-xs font-semibold transition">
                                                    <i class="fas fa-external-link-alt mr-2"></i>Ir para Taxas de IVA
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                
                                @error('tax_rate_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        
                        @if($tax_type === 'isento')
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-file-alt text-green-500 mr-2"></i>Motivo de Isenção *
                                </label>
                                <select wire:model="exemption_reason" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                    <option value="">Selecione o motivo...</option>
                                    @foreach(\App\Models\Product::EXEMPTION_REASONS as $code => $reason)
                                        <option value="{{ $code }}">{{ $code }} - {{ $reason }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Motivo legal de isenção conforme AGT Angola</p>
                                @error('exemption_reason') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="submit" 
                                wire:loading.attr="disabled"
                                wire:target="save"
                                class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-pink-700 shadow-lg hover:shadow-xl transition disabled:opacity-70 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="save">
                                <i class="fas {{ $editingProductId ? 'fa-save' : 'fa-plus' }} mr-2"></i>
                                {{ $editingProductId ? 'Atualizar' : 'Criar' }}
                            </span>
                            <span wire:loading wire:target="save" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Processando...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
