{{-- Modal Confirmacao Exclusao --}}
@if($showDeleteModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="$set('showDeleteModal', false)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 m-4 text-center">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-900 mb-2">Confirmar Exclusao</h3>
        <p class="text-gray-600 mb-6">Tem certeza que deseja remover este funcionario? Esta acao nao pode ser desfeita.</p>
        <div class="flex justify-center gap-3">
            <button wire:click="$set('showDeleteModal', false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium">Cancelar</button>
            <button wire:click="delete" wire:loading.attr="disabled" class="px-6 py-2 bg-red-600 text-white rounded-xl hover:bg-red-700 transition font-semibold disabled:opacity-50">
                <span wire:loading.remove wire:target="delete"><i class="fas fa-trash mr-2"></i>Excluir</span>
                <span wire:loading wire:target="delete"><i class="fas fa-spinner fa-spin mr-2"></i>Excluindo...</span>
            </button>
        </div>
    </div>
</div>
@endif
