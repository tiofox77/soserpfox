{{-- Modal Excluir --}}
@if($showDeleteModal && $deletingDocumentType)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showDeleteModal') }" x-show="show" x-cloak>
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="$wire.closeDeleteModal()"></div>
        
        <div class="inline-block w-full max-w-md bg-white shadow-2xl rounded-2xl relative z-10">
            {{-- Header --}}
            <div class="px-6 py-4 bg-gradient-to-r from-red-600 to-red-700">
                <h3 class="text-xl font-bold text-white">Confirmar Exclusão</h3>
            </div>

            {{-- Body --}}
            <div class="p-6">
                <div class="flex items-center gap-4 mb-4">
                    <div class="flex-shrink-0">
                        <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-gray-900">Deseja realmente excluir este tipo de documento?</p>
                        <p class="text-sm text-gray-600 mt-2">
                            <strong>{{ $deletingDocumentType->code }}</strong> - {{ $deletingDocumentType->description }}
                        </p>
                        <p class="text-xs text-red-600 mt-2">Esta ação não poderá ser desfeita!</p>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
                <button wire:click="closeDeleteModal" 
                    class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-xl">
                    Cancelar
                </button>
                <button wire:click="delete" wire:loading.attr="disabled"
                    class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl disabled:opacity-50">
                    <span wire:loading.remove wire:target="delete">Excluir</span>
                    <span wire:loading wire:target="delete">Excluindo...</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif
