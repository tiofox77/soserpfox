{{-- Reopen Period Modal --}}
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
        <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-emerald-600 flex items-center justify-between rounded-t-xl">
            <h2 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-lock-open mr-2"></i>
                Reabrir Período
            </h2>
            <button wire:click="$set('showReopenModal', false)" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <div class="p-6">
            <div class="mb-6">
                <div class="flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mx-auto mb-4">
                    <i class="fas fa-question-circle text-green-600 text-3xl"></i>
                </div>
                
                <h3 class="text-lg font-bold text-gray-900 text-center mb-2">
                    Tem certeza que deseja reabrir este período?
                </h3>
                
                <p class="text-gray-600 text-center mb-4">
                    <strong class="text-gray-900">{{ $selectedPeriodName }}</strong>
                </p>

                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                    <div class="flex">
                        <i class="fas fa-info-circle text-blue-600 mr-3 mt-1"></i>
                        <div class="text-sm text-blue-800">
                            <p class="font-semibold mb-1">Ao reabrir:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>Será possível criar novos lançamentos</li>
                                <li>Lançamentos poderão ser editados</li>
                                <li>Use apenas para correções necessárias</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="button" 
                        wire:click="$set('showReopenModal', false)"
                        class="flex-1 px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-semibold">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="button" 
                        wire:click="reopenPeriod"
                        class="flex-1 px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold">
                    <i class="fas fa-lock-open mr-2"></i>Sim, Reabrir
                </button>
            </div>
        </div>
    </div>
</div>
