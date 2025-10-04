<!-- Modal Confirmar Eliminação de Método de Pagamento -->
@if($showDeleteModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm animate-fadeIn" wire:click="closeDeleteModal">
    <div class="bg-white rounded-3xl shadow-2xl p-8 max-w-md w-full mx-4 transform transition-all animate-scaleIn" wire:click.stop>
        <!-- Ícone -->
        <div class="flex justify-center mb-6">
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center animate-pulse">
                <i class="fas fa-trash-alt text-red-600 text-4xl"></i>
            </div>
        </div>

        <!-- Título -->
        <h3 class="text-2xl font-bold text-gray-900 text-center mb-3">
            Eliminar Método de Pagamento
        </h3>

        <!-- Mensagem -->
        <p class="text-gray-600 text-center mb-6">
            Tem certeza que deseja eliminar o método <span class="font-bold text-red-600">{{ $methodToDeleteName }}</span>?
        </p>

        <!-- Aviso -->
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
            <div class="flex">
                <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3 mt-1"></i>
                <div>
                    <p class="text-sm text-red-700 font-semibold">Atenção!</p>
                    <p class="text-sm text-red-600 mt-1">Esta ação não pode ser revertida.</p>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="flex space-x-3">
            <button type="button" wire:click="closeDeleteModal"
                    class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-100 transition">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button type="button" wire:click="deletePaymentMethod"
                    class="flex-1 px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition shadow-lg">
                <i class="fas fa-trash-alt mr-2"></i>Sim, Eliminar
            </button>
        </div>
    </div>
</div>
@endif
