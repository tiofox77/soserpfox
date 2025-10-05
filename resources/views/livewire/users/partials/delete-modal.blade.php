@if($showDeleteModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showDeleteModal') }" x-show="show" x-cloak>
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                
                {{-- Header com ícone de aviso --}}
                <div class="bg-gradient-to-r from-red-600 to-rose-600 px-6 py-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-exclamation-triangle text-2xl text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white">Confirmar Exclusão</h3>
                    </div>
                </div>
                
                {{-- Conteúdo --}}
                <div class="px-6 py-6">
                    <div class="mb-4">
                        <p class="text-gray-700 text-base mb-2">
                            Tem certeza que deseja excluir o utilizador:
                        </p>
                        <div class="bg-red-50 border-2 border-red-200 rounded-xl p-4">
                            <div class="flex items-center">
                                <i class="fas fa-user-circle text-3xl text-red-600 mr-3"></i>
                                <div>
                                    <p class="font-bold text-gray-900 text-lg">{{ $deletingUserName }}</p>
                                    <p class="text-sm text-gray-600">Esta ação não pode ser desfeita</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-yellow-600"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-800">
                                    <span class="font-semibold">Atenção:</span> O utilizador só pode ser excluído se não tiver documentos associados.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Footer com botões --}}
                <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
                    <button type="button" 
                            wire:click="closeDeleteModal" 
                            class="px-5 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-100 transition">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button type="button" 
                            wire:click="delete" 
                            class="px-5 py-2.5 bg-gradient-to-r from-red-600 to-rose-600 text-white rounded-xl font-semibold hover:from-red-700 hover:to-rose-700 shadow-lg hover:shadow-xl transition">
                        <i class="fas fa-trash mr-2"></i>Excluir Utilizador
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
