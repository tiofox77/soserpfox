@if($showDeleteModal)
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="cancelDelete"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen"></span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-6 pt-6 pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-16 w-16 rounded-full bg-red-100 sm:mx-0 sm:h-14 sm:w-14">
                        <i class="fas fa-trash text-red-600 text-2xl"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Eliminar Produto</h3>
                        <p class="text-sm text-gray-600">Tem certeza que deseja eliminar o produto:</p>
                        <p class="text-lg font-bold text-red-600 mt-2">{{ $deletingName }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-4 sm:flex sm:flex-row-reverse gap-3">
                <button wire:click="confirmDelete" class="w-full sm:w-auto px-6 py-3 bg-red-600 text-white rounded-xl font-bold hover:bg-red-700 transition">
                    <i class="fas fa-trash mr-2"></i>Eliminar
                </button>
                <button wire:click="cancelDelete" class="mt-3 sm:mt-0 w-full sm:w-auto px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold hover:bg-gray-50 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Cancelar
                </button>
            </div>
        </div>
    </div>
</div>
@endif
