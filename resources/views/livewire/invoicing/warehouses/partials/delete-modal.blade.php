<!-- Delete Confirmation Modal -->
@if($showDeleteModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all animate-scale-in">
        <!-- Icon -->
        <div class="flex justify-center pt-8 pb-4">
            <div class="w-20 h-20 bg-gradient-to-br from-red-500 to-pink-600 rounded-full flex items-center justify-center shadow-2xl shadow-red-500/50 animate-pulse">
                <i class="fas fa-warehouse text-white text-3xl"></i>
            </div>
        </div>

        <!-- Content -->
        <div class="text-center px-8 pb-4">
            <h3 class="text-2xl font-bold text-gray-900 mb-2">Excluir Armazém?</h3>
            <p class="text-gray-600 mb-4">
                Tem certeza que deseja excluir o armazém
                <span class="font-bold text-red-600 block mt-2 text-lg">{{ $deleteName }}</span>
            </p>
            <div class="bg-red-50 border-2 border-red-200 rounded-xl p-4 mb-4">
                <p class="text-sm text-red-700">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Atenção:</strong> Esta ação é irreversível. Todos os dados relacionados a este armazém serão perdidos.
                </p>
            </div>
        </div>

        <!-- Actions -->
        <div class="bg-gray-50 px-8 py-6 rounded-b-2xl flex justify-end space-x-4">
            <button type="button" wire:click="$set('showDeleteModal', false)" 
                    class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button type="button" wire:click="delete" 
                    class="bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-700 hover:to-pink-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg transition">
                <i class="fas fa-trash-alt mr-2"></i>Sim, Excluir
            </button>
        </div>
    </div>
</div>
@endif
