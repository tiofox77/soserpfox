<!-- Delete Confirmation Modal -->
<div x-show="$wire.showDeleteModal" 
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto" 
     aria-labelledby="modal-title" 
     role="dialog" 
     aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div x-show="$wire.showDeleteModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
             aria-hidden="true"
             @click="$wire.cancelDelete()"></div>

        <!-- Center modal -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal panel -->
        <div x-show="$wire.showDeleteModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            
            <div class="bg-white px-6 pt-6 pb-4">
                <div class="sm:flex sm:items-start">
                    <!-- Icon -->
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-16 w-16 rounded-full bg-red-100 sm:mx-0 sm:h-14 sm:w-14 animate-pulse">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                    
                    <!-- Content -->
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                        <h3 class="text-xl font-bold text-gray-900 mb-2" id="modal-title">
                            Confirmar Exclusão
                        </h3>
                        <div class="mt-3">
                            <p class="text-sm text-gray-600 mb-3">
                                Tem certeza que deseja excluir o cliente:
                            </p>
                            <div class="p-4 bg-red-50 rounded-xl border-2 border-red-200">
                                <p class="font-bold text-gray-900 text-center flex items-center justify-center">
                                    <i class="fas fa-user text-red-500 mr-2"></i>
                                    {{ $deletingClientName }}
                                </p>
                            </div>
                            <p class="text-sm text-red-600 font-semibold mt-3 flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                Esta ação não pode ser desfeita!
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="bg-gray-50 px-6 py-4 sm:flex sm:flex-row-reverse gap-3">
                <button wire:click="delete" 
                        type="button"
                        class="w-full inline-flex justify-center items-center rounded-xl border border-transparent shadow-lg px-6 py-3 bg-red-600 text-base font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto transition-all transform hover:scale-105">
                    <i class="fas fa-trash mr-2"></i>
                    Sim, Excluir
                </button>
                <button wire:click="cancelDelete" 
                        type="button"
                        class="mt-3 w-full inline-flex justify-center items-center rounded-xl border-2 border-gray-300 shadow-sm px-6 py-3 bg-white text-base font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:w-auto transition-all">
                    <i class="fas fa-times mr-2"></i>
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>
