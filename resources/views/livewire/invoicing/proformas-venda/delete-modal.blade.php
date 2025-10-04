{{-- Delete Modal --}}
@if($showDeleteModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 animate-scale-in">
        <div class="flex items-center justify-center w-16 h-16 mx-auto bg-red-100 rounded-full mb-4">
            <i class="fas fa-trash text-red-600 text-2xl"></i>
        </div>
        <h3 class="text-xl font-bold text-center text-gray-900 mb-2">Eliminar Proforma?</h3>
        <p class="text-center text-gray-600 mb-6">Esta ação não pode ser revertida.</p>
        <div class="flex gap-3">
            <button wire:click="$set('showDeleteModal', false)" 
                    class="flex-1 px-4 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                Cancelar
            </button>
            <button wire:click="deleteProforma" 
                    class="flex-1 px-4 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition">
                Eliminar
            </button>
        </div>
    </div>
</div>
@endif
