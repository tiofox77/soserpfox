@if($showViewModal && $viewingProduct)
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black bg-opacity-50" wire:click="closeViewModal"></div>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-gradient-to-r from-emerald-500 to-teal-500 px-6 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-white rounded-xl flex items-center justify-center">
                            <i class="fas fa-box text-emerald-600 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">{{ $viewingProduct->name }}</h3>
                            <p class="text-emerald-100">{{ $viewingProduct->code }}</p>
                        </div>
                    </div>
                    <button wire:click="closeViewModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="text-center p-4 bg-emerald-50 rounded-xl">
                        <p class="text-3xl font-bold text-emerald-600">{{ number_format($viewingProduct->price, 0, ',', '.') }}</p>
                        <p class="text-xs text-gray-500 font-semibold">Kz Preço</p>
                    </div>
                    <div class="text-center p-4 bg-blue-50 rounded-xl">
                        <p class="text-3xl font-bold text-blue-600">{{ $viewingProduct->stock_quantity }}</p>
                        <p class="text-xs text-gray-500 font-semibold">Em Stock</p>
                    </div>
                </div>

                <div class="space-y-3">
                    @if($viewingProduct->category)
                        <div class="flex justify-between py-2 border-b">
                            <span class="text-gray-600">Categoria</span>
                            <span class="font-semibold">{{ $viewingProduct->category->name }}</span>
                        </div>
                    @endif
                    @if($viewingProduct->barcode)
                        <div class="flex justify-between py-2 border-b">
                            <span class="text-gray-600">Código de Barras</span>
                            <span class="font-semibold">{{ $viewingProduct->barcode }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-gray-600">Custo</span>
                        <span class="font-semibold">{{ number_format($viewingProduct->cost, 0, ',', '.') }} Kz</span>
                    </div>
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-gray-600">Stock Mínimo</span>
                        <span class="font-semibold">{{ $viewingProduct->minimum_stock }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-gray-600">Status</span>
                        <span class="px-2 py-1 rounded-lg text-xs font-bold {{ $viewingProduct->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $viewingProduct->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t flex justify-end">
                    <button wire:click="closeViewModal" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 rounded-xl font-semibold transition">
                        <i class="fas fa-times mr-2"></i>Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
