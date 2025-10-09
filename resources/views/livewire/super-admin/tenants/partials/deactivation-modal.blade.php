@if($showDeactivationModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-black bg-opacity-60 transition-opacity backdrop-blur-sm"></div>
        
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                
                {{-- Header --}}
                <div class="bg-gradient-to-r from-red-600 to-orange-600 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-exclamation-triangle mr-3 animate-pulse"></i>Desativar Tenant
                        </h3>
                        <button wire:click="closeDeactivationModal" class="text-white hover:text-gray-200 transition">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                {{-- Body --}}
                <form wire:submit.prevent="confirmDeactivation" class="p-6">
                    {{-- Alert --}}
                    <div class="mb-6 p-4 bg-red-50 border-2 border-red-200 rounded-xl">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-red-600 text-2xl mr-3 mt-1"></i>
                            <div>
                                <h4 class="font-bold text-red-900 mb-2">Atenção!</h4>
                                <p class="text-sm text-red-800">
                                    Você está prestes a desativar o tenant <strong>"{{ $deactivatingTenantName }}"</strong>.
                                </p>
                                <ul class="mt-3 text-sm text-red-700 space-y-1">
                                    <li>❌ Todos os usuários perderão acesso <strong>imediatamente</strong></li>
                                    <li>❌ Nenhum módulo estará disponível</li>
                                    <li>❌ Usuários serão desconectados na próxima requisição</li>
                                    <li>✓ Os dados não serão excluídos</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Motivo da Desativação --}}
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-comment-dots mr-2"></i>Motivo da Desativação *
                        </label>
                        <textarea 
                            wire:model="deactivationReason" 
                            rows="4"
                            placeholder="Ex: Falta de pagamento, violação dos termos de serviço, solicitação do cliente..."
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition resize-none"
                        ></textarea>
                        @error('deactivationReason') 
                            <span class="text-red-500 text-xs mt-1 block flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </span> 
                        @enderror
                        <p class="text-xs text-gray-500 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Este motivo será exibido aos usuários quando tentarem acessar o sistema.
                        </p>
                    </div>
                    
                    {{-- Footer --}}
                    <div class="flex justify-end space-x-3">
                        <button 
                            type="button" 
                            wire:click="closeDeactivationModal"
                            wire:loading.attr="disabled"
                            wire:target="confirmDeactivation"
                            class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition disabled:opacity-50">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button 
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-75 cursor-not-allowed"
                            wire:target="confirmDeactivation"
                            class="px-6 py-2.5 bg-gradient-to-r from-red-600 to-orange-600 text-white rounded-xl font-semibold hover:from-red-700 hover:to-orange-700 shadow-lg hover:shadow-xl transition disabled:opacity-75">
                            <span wire:loading.remove wire:target="confirmDeactivation">
                                <i class="fas fa-ban mr-2"></i>Desativar Tenant
                            </span>
                            <span wire:loading wire:target="confirmDeactivation">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Desativando e enviando emails...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
