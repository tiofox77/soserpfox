<!-- Modal Confirmar Eliminação de Marca -->
@if($showDeleteModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm animate-fadeIn" wire:click="closeDeleteModal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 animate-slideIn" wire:click.stop>
        <!-- Header -->
        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 rounded-t-2xl">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-3xl text-white animate-pulse"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-white">
                        Confirmar Eliminação
                    </h2>
                </div>
                <button wire:click="closeDeleteModal" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Body -->
        <div class="p-6">
            <div class="mb-6">
                <div class="bg-red-50 border-2 border-red-200 rounded-xl p-4 mb-4">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-circle text-red-500 text-2xl mr-3 mt-1"></i>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-red-900 mb-2">⚠️ ATENÇÃO - Ação Irreversível!</h3>
                            <p class="text-red-800 text-sm mb-3">
                                Você está prestes a eliminar permanentemente a marca:
                            </p>
                            <div class="bg-white rounded-lg p-3 mb-3">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center">
                                        <i class="fas fa-tag text-white"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900">{{ $brandToDeleteName }}</p>
                                        <p class="text-xs text-gray-500">Esta ação não pode ser desfeita</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-4">
                    <h4 class="font-bold text-yellow-900 mb-3 flex items-center">
                        <i class="fas fa-list-check mr-2"></i>
                        O que será afetado:
                    </h4>
                    <ul class="space-y-2 text-sm text-yellow-800">
                        <li class="flex items-start">
                            <i class="fas fa-times-circle text-red-500 mr-2 mt-0.5"></i>
                            <span><strong>Dados da marca</strong></span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-500 mr-2 mt-0.5"></i>
                            <span><strong>Produtos associados</strong> ficarão sem marca</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mb-6">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 text-xl mr-3 mt-1"></i>
                    <div class="text-sm text-blue-800">
                        <strong>Nota:</strong> A marca será removida de todos os produtos associados. Os produtos continuarão a existir.
                    </div>
                </div>
            </div>
            
            <!-- Botões -->
            <div class="flex space-x-3">
                <button type="button" wire:click="closeDeleteModal"
                        class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="button" wire:click="deleteBrand"
                        class="flex-1 px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition shadow-lg">
                    <i class="fas fa-trash-alt mr-2"></i>Sim, Eliminar Marca
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideIn {
        from { 
            opacity: 0;
            transform: translateY(-20px) scale(0.95);
        }
        to { 
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    .animate-fadeIn {
        animation: fadeIn 0.2s ease-out;
    }
    
    .animate-slideIn {
        animation: slideIn 0.3s ease-out;
    }
</style>
@endif
