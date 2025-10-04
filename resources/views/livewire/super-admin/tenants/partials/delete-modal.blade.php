<!-- Modal Delete Confirmation -->
@if($showDeleteModal ?? false)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showDeleteModal') }" x-show="show" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity backdrop-blur-sm" wire:click="closeDeleteModal"></div>
            
            <div class="relative bg-white rounded-2xl max-w-md w-full shadow-2xl transform transition-all" @click.stop>
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-red-600 to-pink-600 rounded-t-2xl px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center text-white">
                        <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-exclamation-triangle text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold">Confirmar Exclusão</h3>
                    </div>
                    <button wire:click="closeDeleteModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Modal Body -->
                <div class="p-6">
                    <div class="flex items-start space-x-4">
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-trash text-red-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-lg font-bold text-gray-900 mb-2">Tem certeza?</h4>
                            <p class="text-sm text-gray-600">
                                Esta ação não pode ser desfeita. O tenant será permanentemente excluído do sistema.
                            </p>
                            @if($deletingTenantName ?? false)
                                <div class="mt-3 p-3 bg-red-50 rounded-lg">
                                    <p class="text-sm font-semibold text-red-800">{{ $deletingTenantName }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button wire:click="closeDeleteModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button wire:click="confirmDelete" class="px-6 py-2.5 bg-gradient-to-r from-red-600 to-pink-600 text-white rounded-xl font-semibold hover:from-red-700 hover:to-pink-700 shadow-lg hover:shadow-xl transition">
                            <i class="fas fa-trash mr-2"></i>Sim, Excluir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
