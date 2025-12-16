@if($showViewModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showViewModal') }" x-show="show" x-cloak>
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                <!-- Header -->
                <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-eye mr-3"></i>Detalhes do Produto
                        </h3>
                        <button wire:click="closeViewModal" class="text-white hover:text-gray-200 transition">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                @if($viewingProduct)
                    <div class="p-6">
                        <!-- Imagem Destaque e Info Básica -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <!-- Imagem -->
                            <div class="md:col-span-1">
                                @if($viewingProduct->image_url)
                                    <img src="{{ $viewingProduct->image_url }}" 
                                         alt="{{ $viewingProduct->name }}" 
                                         class="w-full h-64 object-cover rounded-xl shadow-lg border-2 border-purple-200"
                                         loading="lazy"
                                         onerror="this.src='{{ asset('images/placeholder-product.png') }}'">
                                @else
                                    <div class="w-full h-64 bg-gradient-to-br from-purple-100 to-pink-100 rounded-xl shadow-lg border-2 border-purple-200 flex items-center justify-center">
                                        <div class="text-center">
                                            <i class="fas fa-box text-6xl text-purple-300 mb-3"></i>
                                            <p class="text-purple-500 font-semibold">Sem imagem</p>
                                        </div>
                                    </div>
                                @endif
                                
                                <!-- Galeria -->
                                @if($viewingProduct->gallery && count($viewingProduct->gallery) > 0)
                                    <div class="mt-4">
                                        <p class="text-xs font-semibold text-gray-600 mb-2">Galeria:</p>
                                        <div class="grid grid-cols-4 gap-2">
                                            @foreach($viewingProduct->gallery as $image)
                                                <img src="{{ Storage::url($image) }}" 
                                                     alt="Galeria" 
                                                     class="w-full h-16 object-cover rounded-lg shadow border border-gray-200 hover:scale-110 transition cursor-pointer">
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                            
                            <!-- Info Principal -->
                            <div class="md:col-span-2 space-y-4">
                                <!-- Nome e Tipo -->
                                <div>
                                    <h2 class="text-3xl font-bold text-gray-900 mb-2">{{ $viewingProduct->name }}</h2>
                                    <div class="flex items-center space-x-3">
                                        <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm font-semibold">
                                            <i class="fas fa-{{ $viewingProduct->type === 'produto' ? 'box' : 'concierge-bell' }} mr-1"></i>
                                            {{ ucfirst($viewingProduct->type) }}
                                        </span>
                                        @if($viewingProduct->is_active)
                                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-semibold">
                                                <i class="fas fa-check-circle mr-1"></i>Ativo
                                            </span>
                                        @else
                                            <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-semibold">
                                                <i class="fas fa-times-circle mr-1"></i>Inativo
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Códigos -->
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                        <p class="text-xs text-blue-600 font-semibold mb-1">Código</p>
                                        <p class="text-lg font-bold text-blue-900">{{ $viewingProduct->code }}</p>
                                    </div>
                                    @if($viewingProduct->sku)
                                        <div class="p-3 bg-purple-50 rounded-lg border border-purple-200">
                                            <p class="text-xs text-purple-600 font-semibold mb-1">SKU</p>
                                            <p class="text-lg font-bold text-purple-900">{{ $viewingProduct->sku }}</p>
                                        </div>
                                    @endif
                                    @if($viewingProduct->barcode)
                                        <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                                            <p class="text-xs text-green-600 font-semibold mb-1">Código de Barras</p>
                                            <p class="text-lg font-bold text-green-900">{{ $viewingProduct->barcode }}</p>
                                        </div>
                                    @endif
                                </div>
                                
                                <!-- Descrição -->
                                @if($viewingProduct->description)
                                    <div class="p-4 bg-gray-50 rounded-lg">
                                        <p class="text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-align-left mr-2 text-gray-500"></i>Descrição
                                        </p>
                                        <p class="text-gray-600">{{ $viewingProduct->description }}</p>
                                    </div>
                                @endif
                                
                                <!-- Preços -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="p-4 bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl border-2 border-green-200">
                                        <p class="text-sm text-green-700 font-semibold mb-1">💰 Preço de Venda</p>
                                        <p class="text-3xl font-bold text-green-900">{{ number_format($viewingProduct->price, 2, ',', '.') }} Kz</p>
                                    </div>
                                    <div class="p-4 bg-gradient-to-br from-orange-50 to-yellow-50 rounded-xl border-2 border-orange-200">
                                        <p class="text-sm text-orange-700 font-semibold mb-1">💵 Custo</p>
                                        <p class="text-3xl font-bold text-orange-900">{{ number_format($viewingProduct->cost ?? 0, 2, ',', '.') }} Kz</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detalhes Adicionais -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Categorização -->
                            <div class="p-4 bg-cyan-50 rounded-xl border border-cyan-200">
                                <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                                    <i class="fas fa-sitemap text-cyan-600 mr-2"></i>Categorização
                                </h4>
                                <div class="space-y-2">
                                    @if($viewingProduct->category)
                                        <div class="flex items-start">
                                            <span class="text-sm text-gray-600 w-24">Categoria:</span>
                                            <span class="text-sm font-semibold text-gray-900">
                                                @if($viewingProduct->category->parent)
                                                    {{ $viewingProduct->category->parent->name }} → 
                                                @endif
                                                {{ $viewingProduct->category->name }}
                                            </span>
                                        </div>
                                    @endif
                                    @if($viewingProduct->brand)
                                        <div class="flex items-start">
                                            <span class="text-sm text-gray-600 w-24">Marca:</span>
                                            <span class="text-sm font-semibold text-gray-900">{{ $viewingProduct->brand->name }}</span>
                                        </div>
                                    @endif
                                    @if($viewingProduct->supplier)
                                        <div class="flex items-start">
                                            <span class="text-sm text-gray-600 w-24">Fornecedor:</span>
                                            <span class="text-sm font-semibold text-gray-900">{{ $viewingProduct->supplier->name }}</span>
                                        </div>
                                    @endif
                                    <div class="flex items-start">
                                        <span class="text-sm text-gray-600 w-24">Unidade:</span>
                                        <span class="text-sm font-semibold text-gray-900">{{ $viewingProduct->unit }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Informação Fiscal -->
                            <div class="p-4 bg-blue-50 rounded-xl border border-blue-200">
                                <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                                    <i class="fas fa-receipt text-blue-600 mr-2"></i>Informação Fiscal
                                </h4>
                                <div class="space-y-2">
                                    <div class="flex items-start">
                                        <span class="text-sm text-gray-600 w-32">Regime:</span>
                                        <span class="text-sm font-semibold text-gray-900">
                                            @if($viewingProduct->tax_type === 'iva')
                                                Sujeito a IVA
                                            @else
                                                Isento de IVA
                                            @endif
                                        </span>
                                    </div>
                                    @if($viewingProduct->tax_type === 'iva' && $viewingProduct->taxRate)
                                        <div class="flex items-start">
                                            <span class="text-sm text-gray-600 w-32">Taxa:</span>
                                            <span class="text-sm font-semibold text-gray-900">
                                                {{ $viewingProduct->taxRate->name }} ({{ $viewingProduct->taxRate->rate }}%)
                                            </span>
                                        </div>
                                    @endif
                                    @if($viewingProduct->tax_type === 'isento' && $viewingProduct->exemption_reason)
                                        <div class="flex items-start">
                                            <span class="text-sm text-gray-600 w-32">Motivo Isenção:</span>
                                            <span class="text-sm font-semibold text-gray-900">{{ $viewingProduct->exemption_reason }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Gestão de Stock -->
                            @if($viewingProduct->manage_stock)
                                <div class="p-4 bg-green-50 rounded-xl border border-green-200">
                                    <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                                        <i class="fas fa-warehouse text-green-600 mr-2"></i>Gestão de Stock
                                    </h4>
                                    <div class="grid grid-cols-3 gap-3">
                                        <div class="text-center p-3 bg-white rounded-lg">
                                            <p class="text-xs text-gray-600 mb-1">Atual</p>
                                            <p class="text-2xl font-bold text-green-600">{{ $viewingProduct->stock_quantity }}</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg">
                                            <p class="text-xs text-gray-600 mb-1">Mínimo</p>
                                            <p class="text-lg font-semibold text-orange-600">{{ $viewingProduct->stock_min ?? 0 }}</p>
                                        </div>
                                        <div class="text-center p-3 bg-white rounded-lg">
                                            <p class="text-xs text-gray-600 mb-1">Máximo</p>
                                            <p class="text-lg font-semibold text-blue-600">{{ $viewingProduct->stock_max ?? '-' }}</p>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="p-4 bg-gray-50 rounded-xl border border-gray-200">
                                    <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                                        <i class="fas fa-warehouse text-gray-600 mr-2"></i>Gestão de Stock
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-info-circle mr-2"></i>Stock não gerenciado para este produto
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
                
                <!-- Footer -->
                <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
                    <button wire:click="closeViewModal" 
                            class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-100 transition">
                        Fechar
                    </button>
                    @if($viewingProduct)
                        <button wire:click="edit({{ $viewingProduct->id }})" 
                                class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700 transition shadow-lg">
                            <i class="fas fa-edit mr-2"></i>Editar Produto
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
