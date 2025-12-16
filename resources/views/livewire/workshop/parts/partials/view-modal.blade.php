<div class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        {{-- Header --}}
        <div class="sticky top-0 bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <div class="flex items-center text-white">
                <i class="fas fa-box text-2xl mr-3"></i>
                <h3 class="text-xl font-bold">Detalhes da Peça</h3>
            </div>
            <button wire:click="closeViewModal" class="text-white hover:text-gray-200 transition-colors">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Content --}}
        <div class="p-6">
            {{-- Nome e Status --}}
            <div class="flex items-start justify-between mb-6 pb-6 border-b border-gray-200">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ $viewingProduct->name }}</h2>
                    <div class="flex items-center space-x-3">
                        <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $viewingProduct->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $viewingProduct->is_active ? 'Ativo' : 'Inativo' }}
                        </span>
                        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-semibold">
                            Tipo: Produto Físico
                        </span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-orange-600">{{ number_format($viewingProduct->price, 2, ',', '.') }} Kz</div>
                    @if($viewingProduct->cost)
                        <div class="text-sm text-gray-500">Custo: {{ number_format($viewingProduct->cost, 2, ',', '.') }} Kz</div>
                        <div class="text-sm font-semibold text-green-600">
                            Margem: {{ number_format((($viewingProduct->price - $viewingProduct->cost) / $viewingProduct->price) * 100, 1) }}%
                        </div>
                    @endif
                </div>
            </div>

            {{-- Info Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                {{-- Códigos --}}
                <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="font-semibold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-hashtag text-orange-500 mr-2"></i>Códigos
                    </h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">SKU:</span>
                            <span class="font-mono font-semibold text-gray-900">{{ $viewingProduct->sku }}</span>
                        </div>
                        @if($viewingProduct->barcode)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Código de Barras:</span>
                                <span class="font-mono font-semibold text-gray-900">{{ $viewingProduct->barcode }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Categoria e Fornecedor --}}
                <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="font-semibold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-folder text-orange-500 mr-2"></i>Classificação
                    </h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Categoria:</span>
                            <span class="font-semibold text-gray-900">{{ $viewingProduct->category->name ?? '-' }}</span>
                        </div>
                        @if($viewingProduct->supplier)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Fornecedor:</span>
                                <span class="font-semibold text-gray-900">{{ $viewingProduct->supplier }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Stock --}}
                <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="font-semibold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-cubes text-orange-500 mr-2"></i>Inventário
                    </h4>
                    @if($viewingProduct->track_inventory)
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Stock Atual:</span>
                                <span class="font-bold text-xl {{ $viewingProduct->stock <= $viewingProduct->min_stock ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $viewingProduct->stock }}
                                </span>
                            </div>
                            @if($viewingProduct->min_stock)
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Stock Mínimo:</span>
                                    <span class="font-semibold text-gray-900">{{ $viewingProduct->min_stock }}</span>
                                </div>
                            @endif
                            @if($viewingProduct->stock <= $viewingProduct->min_stock)
                                <div class="flex items-center text-red-600 text-sm font-semibold">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Stock abaixo do mínimo!
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-gray-500 text-sm">Inventário não rastreado</p>
                    @endif
                </div>

                {{-- Preços --}}
                <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="font-semibold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-money-bill-wave text-orange-500 mr-2"></i>Valores
                    </h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Preço Venda:</span>
                            <span class="font-bold text-green-600">{{ number_format($viewingProduct->price, 2, ',', '.') }} Kz</span>
                        </div>
                        @if($viewingProduct->cost)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Custo:</span>
                                <span class="font-semibold text-gray-900">{{ number_format($viewingProduct->cost, 2, ',', '.') }} Kz</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Lucro/Unidade:</span>
                                <span class="font-semibold text-blue-600">{{ number_format($viewingProduct->price - $viewingProduct->cost, 2, ',', '.') }} Kz</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Descrição --}}
            @if($viewingProduct->description)
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-align-left text-orange-500 mr-2"></i>Descrição
                    </h4>
                    <p class="text-gray-600 bg-gray-50 rounded-xl p-4">{{ $viewingProduct->description }}</p>
                </div>
            @endif

            {{-- Info AGT --}}
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                <div class="flex items-start">
                    <i class="fas fa-shield-alt text-blue-600 text-xl mr-3 mt-1"></i>
                    <div class="text-sm text-blue-800">
                        <p class="font-semibold mb-1">Conformidade AGT Angola</p>
                        <p>Esta peça está cadastrada no módulo de Faturação e pode ser usada em faturas fiscais.</p>
                        <p class="mt-2 text-xs">
                            <strong>ID do Produto:</strong> #{{ $viewingProduct->id }} | 
                            <strong>Tenant:</strong> {{ $viewingProduct->tenant_id }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t border-gray-200 rounded-b-2xl">
            <div class="text-sm text-gray-500">
                Criado em {{ $viewingProduct->created_at->format('d/m/Y H:i') }}
            </div>
            <div class="space-x-3">
                <button wire:click="closeViewModal" 
                        class="px-6 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 font-semibold transition-all">
                    <i class="fas fa-times mr-2"></i>Fechar
                </button>
                <button wire:click="edit({{ $viewingProduct->id }})" 
                        class="px-6 py-2.5 bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 text-white rounded-xl font-semibold shadow-lg transition-all">
                    <i class="fas fa-edit mr-2"></i>Editar
                </button>
            </div>
        </div>
    </div>
</div>
